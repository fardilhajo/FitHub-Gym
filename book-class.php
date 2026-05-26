<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/notifications.php';
require_once __DIR__ . '/includes/feature-schema.php';
require_once __DIR__ . '/includes/membership-access.php';

ensure_booking_feature_schema($db);

$user = current_user($db);
$activeMembershipPlan = active_membership_plan($db, $user);
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

function get_class_for_booking(PDO $db, int $classId): ?array
{
    $stmt = $db->prepare('
        SELECT fc.ClassID, fc.Name, fc.Type, fc.Day, fc.StartTime, fc.Capacity,
               COUNT(CASE WHEN ce.Status = "Enrolled" THEN 1 END) AS EnrolledCount
        FROM FITNESS_CLASS fc
        LEFT JOIN CLASS_ENROLLMENT ce ON ce.ClassID = fc.ClassID
        WHERE fc.ClassID = :class_id AND fc.IsActive = 1
        GROUP BY fc.ClassID
    ');
    $stmt->execute([':class_id' => $classId]);
    $class = $stmt->fetch();

    return $class ?: null;
}

function promote_next_waitlisted_member(PDO $db, array $class): ?array
{
    $countStmt = $db->prepare('SELECT COUNT(*) FROM CLASS_ENROLLMENT WHERE ClassID = :class_id AND Status = "Enrolled"');
    $countStmt->execute([':class_id' => $class['ClassID']]);
    if ((int)$countStmt->fetchColumn() >= (int)$class['Capacity']) {
        return null;
    }

    $waitStmt = $db->prepare('
        SELECT cw.WaitlistID, cw.MemberID, u.Name
        FROM CLASS_WAITLIST cw
        JOIN USER u ON u.UserID = cw.MemberID
        LEFT JOIN MEMBER_MEMBERSHIP mm ON mm.MemberID = cw.MemberID AND mm.Status = "Active"
        LEFT JOIN MEMBERSHIP_PLAN mp ON mp.PlanID = mm.PlanID AND mp.IsActive = 1
        WHERE cw.ClassID = :class_id AND cw.Status = "Waiting"
        ORDER BY CASE mp.Name WHEN "Elite" THEN 3 WHEN "Premium" THEN 2 WHEN "Basic" THEN 1 ELSE 0 END DESC, cw.JoinedAt ASC, cw.WaitlistID ASC
        LIMIT 1
    ');
    $waitStmt->execute([':class_id' => $class['ClassID']]);
    $next = $waitStmt->fetch();

    if (!$next) {
        return null;
    }

    $enrollStmt = $db->prepare('
        INSERT INTO CLASS_ENROLLMENT (ClassID, MemberID, Status)
        VALUES (:class_id, :member_id, "Enrolled")
        ON CONFLICT(ClassID, MemberID) DO UPDATE SET Status = "Enrolled", EnrolledAt = CURRENT_TIMESTAMP
    ');
    $enrollStmt->execute([
        ':class_id' => $class['ClassID'],
        ':member_id' => $next['MemberID'],
    ]);

    $updateStmt = $db->prepare('
        UPDATE CLASS_WAITLIST
        SET Status = "Enrolled", PromotedAt = CURRENT_TIMESTAMP
        WHERE WaitlistID = :waitlist_id
    ');
    $updateStmt->execute([':waitlist_id' => $next['WaitlistID']]);

    return $next;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (!$user) {
        $_SESSION['flash'] = 'Please log in before booking a class.';
        redirect_to('login.php');
    }

    if ($user['Role'] !== 'Member') {
        $_SESSION['flash'] = 'Only members can book classes or join waitlists.';
        redirect_to('book-class.php');
    }

    $classId = (int)($_POST['class_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $class = get_class_for_booking($db, $classId);

    if (!$class) {
        $_SESSION['flash'] = 'Class not found.';
        redirect_to('book-class.php');
    }

    if (in_array($action, ['enroll', 'join_waitlist'], true)) {
        $requiredTier = class_required_membership($class);
        if (!membership_plan_allows($activeMembershipPlan, 'Basic')) {
            $_SESSION['flash'] = 'Choose an active membership plan before booking classes.';
            redirect_to('membership-plans.php');
        }

        if (!membership_plan_allows($activeMembershipPlan, $requiredTier)) {
            $_SESSION['flash'] = membership_upgrade_message($requiredTier, $class['Name']);
            redirect_to('book-class.php');
        }
    }

    if ($action === 'enroll') {
        $db->beginTransaction();
        try {
            $class = get_class_for_booking($db, $classId);
            if (!$class) {
                throw new RuntimeException('Class not found.');
            }

            $alreadyStmt = $db->prepare('SELECT 1 FROM CLASS_ENROLLMENT WHERE ClassID = :class_id AND MemberID = :member_id AND Status = "Enrolled"');
            $alreadyStmt->execute([':class_id' => $classId, ':member_id' => $user['UserID']]);

            if ($alreadyStmt->fetchColumn()) {
                $_SESSION['flash'] = 'You are already enrolled in this class.';
            } elseif ((int)$class['EnrolledCount'] >= (int)$class['Capacity']) {
                $waitStmt = $db->prepare('
                    INSERT INTO CLASS_WAITLIST (ClassID, MemberID, Status)
                    VALUES (:class_id, :member_id, "Waiting")
                    ON CONFLICT(ClassID, MemberID) DO UPDATE SET Status = "Waiting", JoinedAt = CURRENT_TIMESTAMP, PromotedAt = NULL
                ');
                $waitStmt->execute([':class_id' => $classId, ':member_id' => $user['UserID']]);
                $_SESSION['flash'] = 'This class is full, so you were added to the waitlist.';
            } else {
                $stmt = $db->prepare('
                    INSERT INTO CLASS_ENROLLMENT (ClassID, MemberID, Status)
                    VALUES (:class_id, :member_id, "Enrolled")
                    ON CONFLICT(ClassID, MemberID) DO UPDATE SET Status = "Enrolled", EnrolledAt = CURRENT_TIMESTAMP
                ');
                $stmt->execute([':class_id' => $classId, ':member_id' => $user['UserID']]);

                $waitStmt = $db->prepare('UPDATE CLASS_WAITLIST SET Status = "Enrolled", PromotedAt = CURRENT_TIMESTAMP WHERE ClassID = :class_id AND MemberID = :member_id AND Status = "Waiting"');
                $waitStmt->execute([':class_id' => $classId, ':member_id' => $user['UserID']]);

                $_SESSION['flash'] = 'Class booked successfully.';
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            $_SESSION['flash'] = 'Could not book this class. Please try again.';
        }

        if ($_SESSION['flash'] === 'Class booked successfully.') {
            notify($db, (int)$user['UserID'], 'booking_confirmed',
                'Booking Confirmed: ' . $class['Name'],
                'Your booking for ' . $class['Name'] . ' on ' . $class['Day'] . ' at ' . $class['StartTime'] . ' has been confirmed. See you there!'
            );
        } elseif ($_SESSION['flash'] === 'This class is full, so you were added to the waitlist.') {
            notify($db, (int)$user['UserID'], 'general',
                'Waitlist Joined: ' . $class['Name'],
                'You joined the waitlist for ' . $class['Name'] . '. You will be enrolled automatically if a spot opens.'
            );
        }
    } elseif ($action === 'join_waitlist') {
        $db->beginTransaction();
        try {
            $class = get_class_for_booking($db, $classId);
            if (!$class) {
                throw new RuntimeException('Class not found.');
            }

            $alreadyStmt = $db->prepare('SELECT 1 FROM CLASS_ENROLLMENT WHERE ClassID = :class_id AND MemberID = :member_id AND Status = "Enrolled"');
            $alreadyStmt->execute([':class_id' => $classId, ':member_id' => $user['UserID']]);

            if ($alreadyStmt->fetchColumn()) {
                $_SESSION['flash'] = 'You are already enrolled in this class.';
            } elseif ((int)$class['EnrolledCount'] < (int)$class['Capacity']) {
                $stmt = $db->prepare('
                    INSERT INTO CLASS_ENROLLMENT (ClassID, MemberID, Status)
                    VALUES (:class_id, :member_id, "Enrolled")
                    ON CONFLICT(ClassID, MemberID) DO UPDATE SET Status = "Enrolled", EnrolledAt = CURRENT_TIMESTAMP
                ');
                $stmt->execute([':class_id' => $classId, ':member_id' => $user['UserID']]);

                $waitStmt = $db->prepare('UPDATE CLASS_WAITLIST SET Status = "Enrolled", PromotedAt = CURRENT_TIMESTAMP WHERE ClassID = :class_id AND MemberID = :member_id AND Status = "Waiting"');
                $waitStmt->execute([':class_id' => $classId, ':member_id' => $user['UserID']]);

                $_SESSION['flash'] = 'A spot was available, so you were booked directly.';
            } else {
                $stmt = $db->prepare('
                    INSERT INTO CLASS_WAITLIST (ClassID, MemberID, Status)
                    VALUES (:class_id, :member_id, "Waiting")
                    ON CONFLICT(ClassID, MemberID) DO UPDATE SET Status = "Waiting", JoinedAt = CURRENT_TIMESTAMP, PromotedAt = NULL
                ');
                $stmt->execute([':class_id' => $classId, ':member_id' => $user['UserID']]);
                $_SESSION['flash'] = 'You joined the class waitlist.';
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            $_SESSION['flash'] = 'Could not update the waitlist. Please try again.';
        }

        if (in_array($_SESSION['flash'], ['You joined the class waitlist.', 'This class is full, so you were added to the waitlist.'], true)) {
            notify($db, (int)$user['UserID'], 'general',
                'Waitlist Joined: ' . $class['Name'],
                'You joined the waitlist for ' . $class['Name'] . '. You will be enrolled automatically if a spot opens.'
            );
        } elseif ($_SESSION['flash'] === 'A spot was available, so you were booked directly.') {
            notify($db, (int)$user['UserID'], 'booking_confirmed',
                'Booking Confirmed: ' . $class['Name'],
                'Your booking for ' . $class['Name'] . ' on ' . $class['Day'] . ' at ' . $class['StartTime'] . ' has been confirmed. See you there!'
            );
        }
    } elseif ($action === 'leave_waitlist') {
        $stmt = $db->prepare('UPDATE CLASS_WAITLIST SET Status = "Cancelled" WHERE ClassID = :class_id AND MemberID = :member_id AND Status = "Waiting"');
        $stmt->execute([':class_id' => $classId, ':member_id' => $user['UserID']]);
        $_SESSION['flash'] = $stmt->rowCount() > 0 ? 'You left the waitlist.' : 'You were not on that waitlist.';
    } elseif ($action === 'cancel') {
        $promoted = null;
        $db->beginTransaction();
        try {
            $stmt = $db->prepare('UPDATE CLASS_ENROLLMENT SET Status = "Cancelled" WHERE ClassID = :class_id AND MemberID = :member_id AND Status = "Enrolled"');
            $stmt->execute([':class_id' => $classId, ':member_id' => $user['UserID']]);

            if ($stmt->rowCount() > 0) {
                $promoted = promote_next_waitlisted_member($db, $class);
                $_SESSION['flash'] = $promoted
                    ? 'Booking cancelled. The first waitlisted member was automatically enrolled.'
                    : 'Booking cancelled.';
            } else {
                $_SESSION['flash'] = 'You were not enrolled in that class.';
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            $_SESSION['flash'] = 'Could not cancel this booking. Please try again.';
        }

        if (strpos($_SESSION['flash'], 'Booking cancelled') === 0) {
            notify($db, (int)$user['UserID'], 'booking_cancelled',
                'Booking Cancelled: ' . $class['Name'],
                'Your booking for ' . $class['Name'] . ' on ' . $class['Day'] . ' at ' . $class['StartTime'] . ' has been cancelled.'
            );
        }

        if ($promoted) {
            notify($db, (int)$promoted['MemberID'], 'booking_confirmed',
                'Waitlist Spot Opened: ' . $class['Name'],
                'Good news: a spot opened for ' . $class['Name'] . ', so you were automatically enrolled.'
            );
        }
    }

    redirect_to('book-class.php');
}

$q = trim($_GET['q'] ?? '');
$type = trim($_GET['type'] ?? '');
$day = trim($_GET['day'] ?? '');
$trainer = (int)($_GET['trainer'] ?? 0);

$where = ['fc.IsActive = 1'];
$params = [];

if ($q !== '') {
    $where[] = '(fc.Name LIKE :q OR fc.Type LIKE :q OR fc.Description LIKE :q OR u.Name LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}
if ($type !== '') {
    $where[] = 'fc.Type = :type';
    $params[':type'] = $type;
}
if ($day !== '') {
    $where[] = 'fc.Day = :day';
    $params[':day'] = $day;
}
if ($trainer > 0) {
    $where[] = 'fc.TrainerID = :trainer';
    $params[':trainer'] = $trainer;
}

$sql = '
    SELECT
        fc.ClassID,
        fc.Name,
        fc.Type,
        fc.Day,
        fc.StartTime,
        fc.EndTime,
        fc.Room,
        fc.Capacity,
        fc.Description,
        fc.ImageURL,
        u.Name AS TrainerName,
        COALESCE(enrolled.EnrolledCount, 0) AS EnrolledCount,
        COALESCE(waiting.WaitlistCount, 0) AS WaitlistCount,
        reviews.AvgRating,
        COALESCE(reviews.ReviewCount, 0) AS ReviewCount
    FROM FITNESS_CLASS fc
    JOIN USER u ON fc.TrainerID = u.UserID
    LEFT JOIN (
        SELECT ClassID, COUNT(*) AS EnrolledCount
        FROM CLASS_ENROLLMENT
        WHERE Status = "Enrolled"
        GROUP BY ClassID
    ) enrolled ON enrolled.ClassID = fc.ClassID
    LEFT JOIN (
        SELECT ClassID, COUNT(*) AS WaitlistCount
        FROM CLASS_WAITLIST
        WHERE Status = "Waiting"
        GROUP BY ClassID
    ) waiting ON waiting.ClassID = fc.ClassID
    LEFT JOIN (
        SELECT ClassID, ROUND(AVG(Rating), 1) AS AvgRating, COUNT(*) AS ReviewCount
        FROM CLASS_REVIEW
        GROUP BY ClassID
    ) reviews ON reviews.ClassID = fc.ClassID
    WHERE ' . implode(' AND ', $where) . '
    ORDER BY fc.Day, fc.StartTime
';
$stmt = $db->prepare($sql);
$stmt->execute($params);
$classes = $stmt->fetchAll();

$typeOptions = $db->query('SELECT DISTINCT Type FROM FITNESS_CLASS WHERE IsActive = 1 ORDER BY Type')->fetchAll(PDO::FETCH_COLUMN);
$dayOptions = $db->query('SELECT DISTINCT Day FROM FITNESS_CLASS WHERE IsActive = 1 ORDER BY CASE Day WHEN "Monday" THEN 1 WHEN "Tuesday" THEN 2 WHEN "Wednesday" THEN 3 WHEN "Thursday" THEN 4 WHEN "Friday" THEN 5 WHEN "Saturday" THEN 6 ELSE 7 END')->fetchAll(PDO::FETCH_COLUMN);
$trainerOptions = $db->query('SELECT UserID, Name FROM USER WHERE Role = "Trainer" AND IsActive = 1 ORDER BY Name')->fetchAll();

$enrolledClassIds = [];
$waitlistedClassIds = [];
if ($user) {
    $enrolledStmt = $db->prepare('SELECT ClassID FROM CLASS_ENROLLMENT WHERE MemberID = :member AND Status = "Enrolled"');
    $enrolledStmt->execute([':member' => $user['UserID']]);
    $enrolledClassIds = array_map('intval', $enrolledStmt->fetchAll(PDO::FETCH_COLUMN));

    $waitlistStmt = $db->prepare('SELECT ClassID FROM CLASS_WAITLIST WHERE MemberID = :member AND Status = "Waiting"');
    $waitlistStmt->execute([':member' => $user['UserID']]);
    $waitlistedClassIds = array_map('intval', $waitlistStmt->fetchAll(PDO::FETCH_COLUMN));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>FitHub Gym - Classes</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@600;700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/reset.css">
  <link rel="stylesheet" href="css/navbar.css">
  <link rel="stylesheet" href="css/pages/booking.css">
</head>
<body>
  <?php include __DIR__ . '/includes/navbar.php'; ?>

  <main class="content">
    <header class="page-header">
      <h1>Class Schedule</h1>
      <p>Filter by type, trainer, day or time, because apparently staring at eight cards was too primitive.</p>
    </header>

    <?php if ($flash): ?>
      <p class="page-message"><?= e($flash) ?></p>
    <?php endif; ?>

    <section class="filter-section">
      <div class="filter-title"><h2>Filters</h2></div>
      <form class="filter-controls" method="get" action="book-class.php">
        <input type="text" name="q" class="input-search" placeholder="Search classes..." value="<?= e($q) ?>">

        <select name="type" class="select-field">
          <option value="">All Types</option>
          <?php foreach ($typeOptions as $option): ?>
            <option value="<?= e($option) ?>" <?= $type === $option ? 'selected' : '' ?>><?= e($option) ?></option>
          <?php endforeach; ?>
        </select>

        <select name="day" class="select-field">
          <option value="">All Days</option>
          <?php foreach ($dayOptions as $option): ?>
            <option value="<?= e($option) ?>" <?= $day === $option ? 'selected' : '' ?>><?= e($option) ?></option>
          <?php endforeach; ?>
        </select>

        <select name="trainer" class="select-field">
          <option value="0">All Trainers</option>
          <?php foreach ($trainerOptions as $option): ?>
            <option value="<?= e($option['UserID']) ?>" <?= $trainer === (int)$option['UserID'] ? 'selected' : '' ?>><?= e($option['Name']) ?></option>
          <?php endforeach; ?>
        </select>

        <a class="btn-clear" href="book-class.php">Clear</a>
      </form>
    </section>

    <section class="classes-container">
      <p class="results-count">Showing <?= count($classes) ?> available <?= count($classes) === 1 ? 'class' : 'classes' ?></p>
      <div class="classes-grid">
        <?php foreach ($classes as $class): ?>
          <?php
            $enrolled = (int)$class['EnrolledCount'];
            $capacity = (int)$class['Capacity'];
            $spotsLeft = max(0, $capacity - $enrolled);
            $waitlistCount = (int)$class['WaitlistCount'];
            $isFull = $spotsLeft === 0;
            $isEnrolled = in_array((int)$class['ClassID'], $enrolledClassIds, true);
            $isWaitlisted = in_array((int)$class['ClassID'], $waitlistedClassIds, true);
            $image = $class['ImageURL'] ?: 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?auto=format&fit=crop&w=700&q=80';
            $requiredTier = class_required_membership($class);
            $canBookThisClass = $user && $user['Role'] === 'Member' && membership_plan_allows($activeMembershipPlan, $requiredTier);
          ?>
          <article class="class-card">
            <div class="card-image">
              <img src="<?= e($image) ?>" alt="<?= e($class['Name']) ?>">
              <span class="tag-type"><?= e($class['Type']) ?></span>
            </div>
            <div class="card-body">
              <h3><?= e($class['Name']) ?></h3>
              <p class="class-description"><?= e($class['Description']) ?></p>
              <div class="class-info">
                <span><strong>Trainer:</strong> <?= e($class['TrainerName']) ?></span>
                <span>📅 <?= e($class['Day']) ?></span>
                <span>🕒 <?= e($class['StartTime']) ?> - <?= e($class['EndTime']) ?></span>
                <span>📍 <?= e($class['Room']) ?></span>
                <span><strong>Membership:</strong> <?= e(membership_tier_label($requiredTier)) ?> required</span>
                <?php if ($waitlistCount > 0): ?>
                  <span>⏳ <?= $waitlistCount ?> on waitlist</span>
                <?php endif; ?>
              </div>
              <?php if ($class['AvgRating']): ?>
                <div class="class-rating">
                  <?php $avg = (float)$class['AvgRating']; $filled = round($avg); ?>
                  <?php for ($i = 1; $i <= 5; $i++): ?>
                    <span class="star <?= $i <= $filled ? 'star--filled' : '' ?>">★</span>
                  <?php endfor; ?>
                  <span class="rating-avg"><?= e($avg) ?></span>
                  <span class="rating-count">(<?= (int)$class['ReviewCount'] ?>)</span>
                </div>
              <?php endif; ?>
              <div class="class-footer">
                <span class="capacity <?= $isFull ? 'full' : '' ?>"><?= $spotsLeft ?> / <?= $capacity ?> spots left</span>
                <?php if (!$user): ?>
                  <a class="btn-book" href="login.php">Login to book</a>
                <?php elseif ($user['Role'] !== 'Member'): ?>
                  <button class="btn-book" type="button" disabled>Members only</button>
                <?php elseif ($isEnrolled): ?>
                  <form method="post" action="book-class.php" class="inline-form">
                    <?= csrf_input() ?>
                    <input type="hidden" name="class_id" value="<?= e($class['ClassID']) ?>">
                    <input type="hidden" name="action" value="cancel">
                    <button class="btn-book btn-book--cancel" type="submit">Cancel</button>
                  </form>
                <?php elseif ($isWaitlisted): ?>
                  <form method="post" action="book-class.php" class="inline-form">
                    <?= csrf_input() ?>
                    <input type="hidden" name="class_id" value="<?= e($class['ClassID']) ?>">
                    <input type="hidden" name="action" value="leave_waitlist">
                    <button class="btn-book btn-book--waitlist-cancel" type="submit">Leave waitlist</button>
                  </form>
                <?php elseif (!$canBookThisClass): ?>
                  <a class="btn-book" href="membership-plans.php"><?= membership_plan_allows($activeMembershipPlan, 'Basic') ? e(membership_tier_label($requiredTier) . ' required') : 'Choose a plan' ?></a>
                <?php else: ?>
                  <form method="post" action="book-class.php" class="inline-form">
                    <?= csrf_input() ?>
                    <input type="hidden" name="class_id" value="<?= e($class['ClassID']) ?>">
                    <input type="hidden" name="action" value="<?= $isFull ? 'join_waitlist' : 'enroll' ?>">
                    <button class="btn-book <?= $isFull ? 'btn-book--waitlist' : '' ?>" type="submit"><?= $isFull ? 'Join waitlist' : 'Book Now' ?></button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
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
    (function () {
      const form = document.querySelector('.filter-controls');
      if (!form) return;

      const selects = form.querySelectorAll('select');
      const textInput = form.querySelector('input[name="q"]');
      let debounceTimer;

      selects.forEach(function (sel) {
        sel.addEventListener('change', function () { form.submit(); });
      });

      if (textInput) {
        textInput.addEventListener('input', function () {
          clearTimeout(debounceTimer);
          debounceTimer = setTimeout(function () { form.submit(); }, 400);
        });
      }
    })();
  </script>
</body>
</html>
