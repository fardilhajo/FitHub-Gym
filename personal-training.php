<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/feature-schema.php';

ensure_booking_feature_schema($db);
$user = current_user($db);
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (!$user || $user['Role'] !== 'Member') {
        $_SESSION['flash'] = 'Please log in as a member before booking personal training.';
        redirect_to('login.php');
    }

    $availabilityId = (int)($_POST['availability_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    $slotStmt = $db->prepare('SELECT AvailabilityID, TrainerID FROM TRAINER_AVAILABILITY WHERE AvailabilityID = :id AND IsActive = 1');
    $slotStmt->execute([':id' => $availabilityId]);
    $slot = $slotStmt->fetch();

    if (!$slot) {
        $_SESSION['flash'] = 'That trainer slot does not exist anymore.';
        redirect_to('personal-training.php');
    }

    if ($action === 'book') {
        $stmt = $db->prepare('
            INSERT INTO PERSONAL_TRAINING_BOOKING (AvailabilityID, TrainerID, MemberID, Status)
            VALUES (:availability, :trainer, :member, "Booked")
            ON CONFLICT(AvailabilityID, MemberID) DO UPDATE SET Status = "Booked", CreatedAt = CURRENT_TIMESTAMP
        ');
        $stmt->execute([
            ':availability' => $availabilityId,
            ':trainer' => $slot['TrainerID'],
            ':member' => $user['UserID'],
        ]);
        $_SESSION['flash'] = 'Personal training session booked.';
    } elseif ($action === 'cancel') {
        $stmt = $db->prepare('UPDATE PERSONAL_TRAINING_BOOKING SET Status = "Cancelled" WHERE AvailabilityID = :availability AND MemberID = :member');
        $stmt->execute([':availability' => $availabilityId, ':member' => $user['UserID']]);
        $_SESSION['flash'] = 'Personal training booking cancelled.';
    }

    redirect_to('personal-training.php');
}

$trainer = (int)($_GET['trainer'] ?? 0);
$day = trim($_GET['day'] ?? '');
$where = ['ta.IsActive = 1', 'u.IsActive = 1'];
$params = [];
if ($trainer > 0) {
    $where[] = 'ta.TrainerID = :trainer';
    $params[':trainer'] = $trainer;
}
if ($day !== '') {
    $where[] = 'ta.Day = :day';
    $params[':day'] = $day;
}

$stmt = $db->prepare('
    SELECT ta.AvailabilityID, ta.Day, ta.StartTime, ta.EndTime, ta.Location,
           u.UserID AS TrainerID, u.Name AS TrainerName, u.ProfilePhoto,
           tp.Specializations,
           COUNT(CASE WHEN ptb.Status = "Booked" THEN 1 END) AS BookedCount
    FROM TRAINER_AVAILABILITY ta
    JOIN USER u ON u.UserID = ta.TrainerID
    LEFT JOIN TRAINER_PROFILE tp ON tp.TrainerID = u.UserID
    LEFT JOIN PERSONAL_TRAINING_BOOKING ptb ON ptb.AvailabilityID = ta.AvailabilityID
    WHERE ' . implode(' AND ', $where) . '
    GROUP BY ta.AvailabilityID
    ORDER BY CASE ta.Day WHEN "Monday" THEN 1 WHEN "Tuesday" THEN 2 WHEN "Wednesday" THEN 3 WHEN "Thursday" THEN 4 WHEN "Friday" THEN 5 WHEN "Saturday" THEN 6 ELSE 7 END, ta.StartTime
');
$stmt->execute($params);
$slots = $stmt->fetchAll();

$trainerOptions = $db->query('SELECT UserID, Name FROM USER WHERE Role = "Trainer" AND IsActive = 1 ORDER BY Name')->fetchAll();
$dayOptions = $db->query('SELECT DISTINCT Day FROM TRAINER_AVAILABILITY WHERE IsActive = 1 ORDER BY CASE Day WHEN "Monday" THEN 1 WHEN "Tuesday" THEN 2 WHEN "Wednesday" THEN 3 WHEN "Thursday" THEN 4 WHEN "Friday" THEN 5 WHEN "Saturday" THEN 6 ELSE 7 END')->fetchAll(PDO::FETCH_COLUMN);

$bookedSlotIds = [];
if ($user) {
    $bookedStmt = $db->prepare('SELECT AvailabilityID FROM PERSONAL_TRAINING_BOOKING WHERE MemberID = :member AND Status = "Booked"');
    $bookedStmt->execute([':member' => $user['UserID']]);
    $bookedSlotIds = array_map('intval', $bookedStmt->fetchAll(PDO::FETCH_COLUMN));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>FitHub Gym - Personal Training</title>
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
      <h1>Personal Training</h1>
      <p>Browse trainer availability and book a one-on-one session before your motivation notices what is happening.</p>
    </header>

    <?php if ($flash): ?><p class="page-message"><?= e($flash) ?></p><?php endif; ?>

    <section class="filter-section">
      <div class="filter-title"><h2>Find a slot</h2></div>
      <form class="filter-controls" method="get" action="personal-training.php">
        <select name="trainer" class="select-field">
          <option value="0">All Trainers</option>
          <?php foreach ($trainerOptions as $option): ?>
            <option value="<?= e($option['UserID']) ?>" <?= $trainer === (int)$option['UserID'] ? 'selected' : '' ?>><?= e($option['Name']) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="day" class="select-field">
          <option value="">All Days</option>
          <?php foreach ($dayOptions as $option): ?>
            <option value="<?= e($option) ?>" <?= $day === $option ? 'selected' : '' ?>><?= e($option) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-clear">Apply</button>
        <a class="btn-clear" href="personal-training.php">Clear</a>
      </form>
    </section>

    <section class="classes-container">
      <p class="results-count">Showing <?= count($slots) ?> available <?= count($slots) === 1 ? 'slot' : 'slots' ?></p>
      <div class="classes-grid feature-grid">
        <?php foreach ($slots as $slot): ?>
          <?php
            $isBookedByUser = in_array((int)$slot['AvailabilityID'], $bookedSlotIds, true);
            $isTaken = (int)$slot['BookedCount'] > 0 && !$isBookedByUser;
            $photo = $slot['ProfilePhoto'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($slot['TrainerName']) . '&background=f18c4f&color=fff';
            $specs = array_filter(array_map('trim', explode(',', $slot['Specializations'] ?? '')));
          ?>
          <article class="class-card">
            <div class="card-image">
              <img src="<?= e($photo) ?>" alt="<?= e($slot['TrainerName']) ?>">
              <span class="tag-type">1:1 Coaching</span>
            </div>
            <div class="card-body">
              <h3><?= e($slot['TrainerName']) ?></h3>
              <p class="class-description"><?= e($specs ? implode(' · ', array_slice($specs, 0, 3)) : 'Personal Trainer') ?></p>
              <div class="class-info">
                <span>📅 <?= e($slot['Day']) ?></span>
                <span>🕒 <?= e($slot['StartTime']) ?> - <?= e($slot['EndTime']) ?></span>
                <span>📍 <?= e($slot['Location']) ?></span>
              </div>
              <div class="class-footer">
                <span class="capacity <?= $isTaken ? 'full' : '' ?>"><?= $isTaken ? 'Taken' : 'Available' ?></span>
                <?php if (!$user): ?>
                  <a class="btn-book" href="login.php">Login to book</a>
                <?php elseif ($user['Role'] !== 'Member'): ?>
                  <button class="btn-book" disabled>Members only</button>
                <?php elseif ($isBookedByUser): ?>
                  <form method="post" action="personal-training.php" class="inline-form">
                    <?= csrf_input() ?>
                    <input type="hidden" name="availability_id" value="<?= e($slot['AvailabilityID']) ?>">
                    <input type="hidden" name="action" value="cancel">
                    <button class="btn-book btn-book--cancel" type="submit">Cancel</button>
                  </form>
                <?php else: ?>
                  <form method="post" action="personal-training.php" class="inline-form">
                    <?= csrf_input() ?>
                    <input type="hidden" name="availability_id" value="<?= e($slot['AvailabilityID']) ?>">
                    <input type="hidden" name="action" value="book">
                    <button class="btn-book" type="submit" <?= $isTaken ? 'disabled' : '' ?>><?= $isTaken ? 'Taken' : 'Book' ?></button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  </main>
  <script src="js/navbar.js"></script>
</body>
</html>
