<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/notifications.php';

require_login();
$user = current_user($db);

if (!$user || !in_array($user['Role'], ['Trainer', 'Admin'])) {
    redirect_to('index.php');
}

$trainerId = (int)$user['UserID'];
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

// ── POST Action Handlers ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_pt_notes') {
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');

        // Ensure the booking belongs to this trainer
        $stmt = $db->prepare('UPDATE PERSONAL_TRAINING_BOOKING SET Notes = :notes WHERE BookingID = :id AND TrainerID = :trainerId');
        $stmt->execute([':notes' => $notes, ':id' => $bookingId, ':trainerId' => $trainerId]);

        $_SESSION['flash'] = 'Session notes updated successfully.';
        redirect_to('trainer-roster.php');

    } elseif ($action === 'cancel_pt_booking') {
        $bookingId = (int)($_POST['booking_id'] ?? 0);

        // Fetch details before cancelling to notify the member correctly
        $stmt = $db->prepare('
            SELECT ptb.MemberID, ta.Day, ta.StartTime, ta.EndTime
            FROM PERSONAL_TRAINING_BOOKING ptb
            JOIN TRAINER_AVAILABILITY ta ON ptb.AvailabilityID = ta.AvailabilityID
            WHERE ptb.BookingID = :id AND ptb.TrainerID = :trainerId AND ptb.Status = "Booked"
        ');
        $stmt->execute([':id' => $bookingId, ':trainerId' => $trainerId]);
        $booking = $stmt->fetch();

        if ($booking) {
            $cancelStmt = $db->prepare('UPDATE PERSONAL_TRAINING_BOOKING SET Status = "Cancelled" WHERE BookingID = :id');
            $cancelStmt->execute([':id' => $bookingId]);

            // Notify member via db and email
            $msg = sprintf("Your personal training session with Coach %s on %s (%s - %s) was cancelled by the trainer.", $user['Name'], $booking['Day'], $booking['StartTime'], $booking['EndTime']);
            notify($db, (int)$booking['MemberID'], 'booking_cancelled', 'Personal Training Session Cancelled', $msg);

            $_SESSION['flash'] = 'Personal training session cancelled and client notified.';
        } else {
            $_SESSION['flash'] = 'Could not find booking or booking is already cancelled.';
        }
        redirect_to('trainer-roster.php');

    } elseif ($action === 'cancel_class_enrollment') {
        $enrollmentId = (int)($_POST['enrollment_id'] ?? 0);

        // Find enrollment details (ensuring the class is taught by this trainer)
        $stmt = $db->prepare('
            SELECT ce.MemberID, fc.Name AS ClassName, fc.Day, fc.StartTime
            FROM CLASS_ENROLLMENT ce
            JOIN FITNESS_CLASS fc ON ce.ClassID = fc.ClassID
            WHERE ce.EnrollmentID = :id AND fc.TrainerID = :trainerId AND ce.Status = "Enrolled"
        ');
        $stmt->execute([':id' => $enrollmentId, ':trainerId' => $trainerId]);
        $enrollment = $stmt->fetch();

        if ($enrollment) {
            $cancelStmt = $db->prepare('UPDATE CLASS_ENROLLMENT SET Status = "Cancelled" WHERE EnrollmentID = :id');
            $cancelStmt->execute([':id' => $enrollmentId]);

            // Notify member via db and email
            $msg = sprintf("Your enrollment in the class '%s' on %s at %s was cancelled by Coach %s.", $enrollment['ClassName'], $enrollment['Day'], $enrollment['StartTime'], $user['Name']);
            notify($db, (int)$enrollment['MemberID'], 'booking_cancelled', 'Class Enrollment Cancelled', $msg);

            $_SESSION['flash'] = 'Member removed from class roster and notified.';
        } else {
            $_SESSION['flash'] = 'Could not find enrollment or it is already cancelled.';
        }
        redirect_to('trainer-roster.php');

    } elseif ($action === 'send_notification') {
        $memberId = (int)($_POST['member_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if ($memberId > 0 && $title !== '' && $message !== '') {
            // Check if member is an active client of this trainer
            $checkStmt = $db->prepare('
                SELECT 1 FROM CLASS_ENROLLMENT ce
                JOIN FITNESS_CLASS fc ON ce.ClassID = fc.ClassID
                WHERE fc.TrainerID = :trainerId AND ce.MemberID = :memberId AND ce.Status = "Enrolled"
                UNION
                SELECT 1 FROM PERSONAL_TRAINING_BOOKING ptb
                WHERE ptb.TrainerID = :trainerId AND ptb.MemberID = :memberId AND ptb.Status = "Booked"
            ');
            $checkStmt->execute([':trainerId' => $trainerId, ':memberId' => $memberId]);

            if ($checkStmt->fetch()) {
                notify($db, $memberId, 'general', $title, $message);
                $_SESSION['flash'] = 'Alert message sent and client notified via email.';
            } else {
                $_SESSION['flash'] = 'Error: The selected user is not in your active roster.';
            }
        } else {
            $_SESSION['flash'] = 'Error: Title and message cannot be empty.';
        }
        redirect_to('trainer-roster.php');
    }
}

// ── GET filters ───────────────────────────────────────────────────────────────
$searchTerm = trim($_GET['search'] ?? '');
$filterType = trim($_GET['type'] ?? 'All');
$filterClass = (int)($_GET['class_id'] ?? 0);
$filterAvailability = (int)($_GET['availability_id'] ?? 0);

// ── Fetch Helper Lists for Filtering ──────────────────────────────────────────
// Classes taught by this trainer
$classFilterListStmt = $db->prepare('SELECT ClassID, Name, Day, StartTime FROM FITNESS_CLASS WHERE TrainerID = :tid AND IsActive = 1 ORDER BY Day, StartTime');
$classFilterListStmt->execute([':tid' => $trainerId]);
$trainerClasses = $classFilterListStmt->fetchAll();

// Availability slots of this trainer
$ptFilterListStmt = $db->prepare('SELECT AvailabilityID, Day, StartTime, EndTime, Location FROM TRAINER_AVAILABILITY WHERE TrainerID = :tid AND IsActive = 1 ORDER BY Day, StartTime');
$ptFilterListStmt->execute([':tid' => $trainerId]);
$trainerSlots = $ptFilterListStmt->fetchAll();

// ── Fetch Roster Clients ───────────────────────────────────────────────────────
$queryParams = [':tid' => $trainerId];
$sql = '
    SELECT DISTINCT u.UserID, u.Name, u.Username, u.ProfilePhoto, u.Email
    FROM USER u
';

$subqueries = [];

// Subquery for Class clients
if ($filterType === 'All' || $filterType === 'Class') {
    $classSub = '
        SELECT ce.MemberID
        FROM CLASS_ENROLLMENT ce
        JOIN FITNESS_CLASS fc ON ce.ClassID = fc.ClassID
        WHERE fc.TrainerID = :tid AND ce.Status = "Enrolled" AND fc.IsActive = 1
    ';
    if ($filterClass > 0) {
        $classSub .= ' AND fc.ClassID = :class_id';
        $queryParams[':class_id'] = $filterClass;
    }
    $subqueries[] = $classSub;
}

// Subquery for PT clients
if ($filterType === 'All' || $filterType === 'PT') {
    $ptSub = '
        SELECT ptb.MemberID
        FROM PERSONAL_TRAINING_BOOKING ptb
        WHERE ptb.TrainerID = :tid AND ptb.Status = "Booked"
    ';
    if ($filterAvailability > 0) {
        $ptSub .= ' AND ptb.AvailabilityID = :availability_id';
        $queryParams[':availability_id'] = $filterAvailability;
    }
    $subqueries[] = $ptSub;
}

// Combine relations
if (!empty($subqueries)) {
    $sql .= ' WHERE u.UserID IN (' . implode(' UNION ', $subqueries) . ')';
} else {
    $sql .= ' WHERE 0 = 1';
}

// Search name filter
if ($searchTerm !== '') {
    $sql .= ' AND (u.Name LIKE :search OR u.Username LIKE :search)';
    $queryParams[':search'] = '%' . $searchTerm . '%';
}

$sql .= ' ORDER BY u.Name';

$clientsStmt = $db->prepare($sql);
$clientsStmt->execute($queryParams);
$clients = $clientsStmt->fetchAll(PDO::FETCH_ASSOC);

// Populate class enrollments and PT bookings for each client
foreach ($clients as &$client) {
    // Classes
    $cStmt = $db->prepare('
        SELECT fc.ClassID, fc.Name AS ClassName, fc.Type, fc.Day, fc.StartTime, fc.EndTime, fc.Room, ce.EnrollmentID
        FROM CLASS_ENROLLMENT ce
        JOIN FITNESS_CLASS fc ON ce.ClassID = fc.ClassID
        WHERE fc.TrainerID = :tid AND ce.MemberID = :mid AND ce.Status = "Enrolled" AND fc.IsActive = 1
        ORDER BY fc.Day, fc.StartTime
    ');
    $cStmt->execute([':tid' => $trainerId, ':mid' => $client['UserID']]);
    $client['classes'] = $cStmt->fetchAll(PDO::FETCH_ASSOC);

    // PT sessions
    $pStmt = $db->prepare('
        SELECT ptb.BookingID, ptb.SessionDate, ptb.Status, ptb.Notes, ta.Day, ta.StartTime, ta.EndTime, ta.Location
        FROM PERSONAL_TRAINING_BOOKING ptb
        JOIN TRAINER_AVAILABILITY ta ON ptb.AvailabilityID = ta.AvailabilityID
        WHERE ptb.TrainerID = :tid AND ptb.MemberID = :mid AND ptb.Status = "Booked" AND ta.IsActive = 1
        ORDER BY ta.Day, ta.StartTime
    ');
    $pStmt->execute([':tid' => $trainerId, ':mid' => $client['UserID']]);
    $client['pt_bookings'] = $pStmt->fetchAll(PDO::FETCH_ASSOC);
}
unset($client);

// ── Calculate Dashboard Statistics ────────────────────────────────────────────
// Unique client count
$totalUniqueClientsStmt = $db->prepare('
    SELECT COUNT(DISTINCT UserID) FROM (
        SELECT ce.MemberID AS UserID
        FROM CLASS_ENROLLMENT ce
        JOIN FITNESS_CLASS fc ON ce.ClassID = fc.ClassID
        WHERE fc.TrainerID = :tid AND ce.Status = "Enrolled" AND fc.IsActive = 1
        UNION
        SELECT ptb.MemberID AS UserID
        FROM PERSONAL_TRAINING_BOOKING ptb
        WHERE ptb.TrainerID = :tid AND ptb.Status = "Booked"
    )
');
$totalUniqueClientsStmt->execute([':tid' => $trainerId]);
$totalClientsCount = (int)$totalUniqueClientsStmt->fetchColumn();

// Class enrollment count
$totalClassStudentsStmt = $db->prepare('
    SELECT COUNT(*)
    FROM CLASS_ENROLLMENT ce
    JOIN FITNESS_CLASS fc ON ce.ClassID = fc.ClassID
    WHERE fc.TrainerID = :tid AND ce.Status = "Enrolled" AND fc.IsActive = 1
');
$totalClassStudentsStmt->execute([':tid' => $trainerId]);
$totalClassStudentsCount = (int)$totalClassStudentsStmt->fetchColumn();

// PT Booking count
$totalPtSessionsStmt = $db->prepare('
    SELECT COUNT(*)
    FROM PERSONAL_TRAINING_BOOKING
    WHERE TrainerID = :tid AND Status = "Booked"
');
$totalPtSessionsStmt->execute([':tid' => $trainerId]);
$totalPtSessionsCount = (int)$totalPtSessionsStmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Client Roster – FitHub</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@600;700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/reset.css">
  <link rel="stylesheet" href="css/base.css">
  <link rel="stylesheet" href="css/components.css">
  <link rel="stylesheet" href="css/navbar.css">
  <link rel="stylesheet" href="css/pages/roster.css">
</head>
<body>
  <?php include __DIR__ . '/includes/navbar.php'; ?>

  <main class="roster-container">
    <header class="roster-header">
      <p class="eyebrow">Trainer Dashboard</p>
      <h1>Client Roster</h1>
      <p class="subtitle">Manage class rosters, schedule details, session feedback notes, and client alerts.</p>
    </header>

    <?php if ($flash): ?>
      <p class="page-message"><?= e($flash) ?></p>
    <?php endif; ?>

    <!-- Summary cards -->
    <section class="summary-grid">
      <div class="stat-card">
        <span class="stat-icon">👥</span>
        <p class="stat-value"><?= $totalClientsCount ?></p>
        <p class="stat-label">Total Unique Clients</p>
      </div>
      <div class="stat-card">
        <span class="stat-icon">🏋️</span>
        <p class="stat-value"><?= $totalClassStudentsCount ?></p>
        <p class="stat-label">Active Class Students</p>
      </div>
      <div class="stat-card">
        <span class="stat-icon">📅</span>
        <p class="stat-value"><?= $totalPtSessionsCount ?></p>
        <p class="stat-label">PT Sessions Booked</p>
      </div>
    </section>

    <!-- Toolbar / Filters -->
    <section class="toolbar">
      <form method="get" action="trainer-roster.php" style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;flex:1;margin:0;">
        <div class="toolbar-search">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
          </svg>
          <input type="search" name="search" placeholder="Search by client name..." value="<?= e($searchTerm) ?>">
        </div>

        <select name="type" onchange="this.form.submit()">
          <option value="All" <?= $filterType === 'All' ? 'selected' : '' ?>>All Relationship Types</option>
          <option value="Class" <?= $filterType === 'Class' ? 'selected' : '' ?>>Class Students Only</option>
          <option value="PT" <?= $filterType === 'PT' ? 'selected' : '' ?>>PT Clients Only</option>
        </select>

        <?php if ($filterType === 'Class' && !empty($trainerClasses)): ?>
          <select name="class_id" onchange="this.form.submit()">
            <option value="0">All Taught Classes</option>
            <?php foreach ($trainerClasses as $tc): ?>
              <option value="<?= $tc['ClassID'] ?>" <?= $filterClass === (int)$tc['ClassID'] ? 'selected' : '' ?>>
                <?= e($tc['Name']) ?> (<?= e($tc['Day']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>

        <?php if ($filterType === 'PT' && !empty($trainerSlots)): ?>
          <select name="availability_id" onchange="this.form.submit()">
            <option value="0">All PT Slots</option>
            <?php foreach ($trainerSlots as $ts): ?>
              <option value="<?= $ts['AvailabilityID'] ?>" <?= $filterAvailability === (int)$ts['AvailabilityID'] ? 'selected' : '' ?>>
                <?= e($ts['Day']) ?> · <?= e($ts['StartTime']) ?>-<?= e($ts['EndTime']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>

        <button type="submit" class="btn-primary">Apply Filters</button>
        <?php if ($searchTerm !== '' || $filterType !== 'All' || $filterClass > 0 || $filterAvailability > 0): ?>
          <a href="trainer-roster.php" class="btn-secondary">Clear</a>
        <?php endif; ?>
      </form>
      <div class="toolbar-count">
        Showing <strong><?= count($clients) ?></strong> client<?= count($clients) !== 1 ? 's' : '' ?>
      </div>
    </section>

    <!-- Client Cards Grid -->
    <section class="roster-grid">
      <?php if (empty($clients)): ?>
        <article class="empty-state">
          <span class="empty-icon">🔍</span>
          <h3>No clients found</h3>
          <p>Try modifying your search query or filters to locate your active clients.</p>
        </article>
      <?php else: ?>
        <?php foreach ($clients as $client):
            $parts = preg_split('/\s+/', trim($client['Name']));
            $initials = strtoupper(substr($parts[0] ?? '', 0, 1));
            if (count($parts) > 1) {
                $initials .= strtoupper(substr(end($parts), 0, 1));
            }
            $photo = $client['ProfilePhoto'] ?: '';
        ?>
          <article class="client-card">
            <div class="client-header">
              <?php if ($photo): ?>
                <img src="<?= e($photo) ?>" alt="<?= e($client['Name']) ?>" class="client-avatar">
              <?php else: ?>
                <div class="client-avatar"><?= e($initials) ?></div>
              <?php endif; ?>
              <div class="client-identity">
                <span class="client-name"><?= e($client['Name']) ?></span>
                <span class="client-username">@<?= e($client['Username']) ?></span>
                <a href="mailto:<?= e($client['Email']) ?>" class="client-email"><?= e($client['Email'] ?: 'no email listed') ?></a>
              </div>
            </div>

            <div class="client-badges">
              <?php if (!empty($client['classes'])): ?>
                <span class="badge badge--class">Class Client</span>
              <?php endif; ?>
              <?php if (!empty($client['pt_bookings'])): ?>
                <span class="badge badge--pt">PT Client</span>
              <?php endif; ?>
            </div>

            <div class="client-body">
              <!-- Class Enrollments Section -->
              <?php if (!empty($client['classes'])): ?>
                <div>
                  <h4 class="roster-section-title">Enrolled Classes</h4>
                  <div class="item-list">
                    <?php foreach ($client['classes'] as $cls): ?>
                      <div class="roster-item-card">
                        <div class="roster-item-top">
                          <span class="roster-item-name"><?= e($cls['ClassName']) ?></span>
                          <form method="post" action="trainer-roster.php" class="inline-form" onsubmit="return confirm('Remove this client from the class roster?')">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="cancel_class_enrollment">
                            <input type="hidden" name="enrollment_id" value="<?= $cls['EnrollmentID'] ?>">
                            <button type="submit" class="btn-danger btn-icon" title="Remove Client from Class">✕</button>
                          </form>
                        </div>
                        <span class="roster-item-meta">
                          Type: <strong><?= e($cls['Type']) ?></strong> · Room: <strong><?= e($cls['Room']) ?></strong>
                          <br>
                          Schedule: <strong><?= e($cls['Day']) ?> · <?= e($cls['StartTime']) ?>-<?= e($cls['EndTime']) ?></strong>
                        </span>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endif; ?>

              <!-- PT Sessions Section -->
              <?php if (!empty($client['pt_bookings'])): ?>
                <div>
                  <h4 class="roster-section-title">Personal Training Sessions</h4>
                  <div class="item-list">
                    <?php foreach ($client['pt_bookings'] as $booking): ?>
                      <div class="roster-item-card">
                        <div class="roster-item-top">
                          <span class="roster-item-name">One-on-One Session</span>
                          <form method="post" action="trainer-roster.php" class="inline-form" onsubmit="return confirm('Cancel this PT session booking?')">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="cancel_pt_booking">
                            <input type="hidden" name="booking_id" value="<?= $booking['BookingID'] ?>">
                            <button type="submit" class="btn-danger btn-icon">Cancel Session</button>
                          </form>
                        </div>

                        <span class="roster-item-meta">
                          Schedule: <strong><?= e($booking['Day']) ?> · <?= e($booking['StartTime']) ?>-<?= e($booking['EndTime']) ?></strong>
                          <br>
                          Location: <strong><?= e($booking['Location'] ?? 'PT Studio') ?></strong>
                        </span>

                        <div class="notes-block">
                          <strong>Session Notes:</strong>
                          <p><?= $booking['Notes'] ? e($booking['Notes']) : '<em>No notes added yet. Click Edit Notes below to start detailing this session.</em>' ?></p>
                        </div>

                        <div class="roster-item-actions">
                          <button class="btn-secondary" onclick="toggleForm('notes-form-<?= $booking['BookingID'] ?>')">
                            ✏️ Edit Notes
                          </button>
                        </div>

                        <div id="notes-form-<?= $booking['BookingID'] ?>" class="notes-form-container u-hidden">
                          <form method="post" action="trainer-roster.php" class="notes-form">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="update_pt_notes">
                            <input type="hidden" name="booking_id" value="<?= $booking['BookingID'] ?>">
                            <textarea name="notes" class="notes-textarea" placeholder="Add session feedback, workout details, or progress goals..."><?= e($booking['Notes']) ?></textarea>
                            <div class="notes-form-buttons">
                              <button type="button" class="btn-ghost" onclick="toggleForm('notes-form-<?= $booking['BookingID'] ?>')">Cancel</button>
                              <button type="submit" class="btn-primary" style="height:1.85rem;padding:0 0.75rem;">Save Notes</button>
                            </div>
                          </form>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endif; ?>

              <!-- Custom Alert Notification Drawer Form -->
              <div id="notif-form-<?= $client['UserID'] ?>" class="notification-form-container u-hidden">
                <h5 style="margin:0 0 0.25rem 0;font-size:0.85rem;font-weight:700;color:var(--text-dark);">Send Alert Message</h5>
                <form method="post" action="trainer-roster.php" style="display:flex;flex-direction:column;gap:0.4rem;">
                  <?= csrf_input() ?>
                  <input type="hidden" name="action" value="send_notification">
                  <input type="hidden" name="member_id" value="<?= $client['UserID'] ?>">
                  <input type="text" name="title" class="notif-input" placeholder="Notification Title (e.g. Schedule Reminder)" required>
                  <textarea name="message" class="notif-textarea" placeholder="Type your alert message here..." required></textarea>
                  <div style="display:flex;justify-content:flex-end;gap:0.35rem;margin-top:0.2rem;">
                    <button type="button" class="btn-ghost" onclick="toggleForm('notif-form-<?= $client['UserID'] ?>')">Cancel</button>
                    <button type="submit" class="btn-primary" style="height:1.85rem;padding:0 0.75rem;">Send Alert</button>
                  </div>
                </form>
              </div>

            </div>

            <div class="client-footer">
              <a href="trainer-nutrition.php?member_id=<?= $client['UserID'] ?>" class="btn-secondary">
                🍏 Nutrition Plan
              </a>
              <button class="btn-ghost" onclick="toggleForm('notif-form-<?= $client['UserID'] ?>')">
                🔔 Send Alert
              </button>
            </div>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>
  </main>

  <footer class="main-footer">
    <div class="footer-content">
      <p>&copy; 2026 FitHub Gym. Move your body, change your life.</p>
      <div class="social-links"><a href="#">Instagram</a><a href="#">Facebook</a></div>
    </div>
  </footer>

  <script src="js/navbar.js"></script>
  <script>
    function toggleForm(formId) {
      const form = document.getElementById(formId);
      if (form) {
        form.classList.toggle('u-hidden');
      }
    }
  </script>
</body>
</html>
