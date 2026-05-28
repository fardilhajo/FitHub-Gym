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
$canReserveEquipment = membership_plan_allows($activeMembershipPlan, 'Basic');
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$returnTo = 'equipment.php';
if (!empty($_SERVER['QUERY_STRING'])) {
    $returnTo .= '?' . $_SERVER['QUERY_STRING'];
}

function parse_datetime_local(?string $value): ?DateTimeImmutable
{
    if (!$value) {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d\\TH:i', $value);
    $errors = DateTimeImmutable::getLastErrors();

    if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        return null;
    }

    return $date;
}

function format_slot(string $value): string
{
    $timestamp = strtotime($value);
    return $timestamp ? date('d M Y, H:i', $timestamp) : $value;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (!$user) {
        $_SESSION['flash'] = 'Please log in before reserving equipment.';
        redirect_to('login.php');
    }

    if ($user['Role'] !== 'Member') {
        $_SESSION['flash'] = 'Only members can reserve equipment.';
        redirect_to(safe_return_to('equipment.php'));
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'reserve_equipment' && !$canReserveEquipment) {
        $_SESSION['flash'] = 'Choose an active Basic membership or higher before reserving equipment.';
        redirect_to('membership-plans.php');
    }

    if ($action === 'reserve_equipment') {
        $equipmentId = (int)($_POST['equipment_id'] ?? 0);
        $start = parse_datetime_local($_POST['start_at'] ?? null);
        $end = parse_datetime_local($_POST['end_at'] ?? null);
        $now = new DateTimeImmutable('now');

        if (!$start || !$end) {
            $_SESSION['flash'] = 'Please choose a valid start and end time.';
        } elseif ($start <= $now) {
            $_SESSION['flash'] = 'Please choose a future start time.';
        } elseif ($end <= $start) {
            $_SESSION['flash'] = 'The end time must be after the start time. Apparently time still insists on moving forwards.';
        } else {
            $equipmentStmt = $db->prepare('SELECT EquipmentID, Name, Status, Location FROM EQUIPMENT WHERE EquipmentID = :id');
            $equipmentStmt->execute([':id' => $equipmentId]);
            $equipment = $equipmentStmt->fetch();

            if (!$equipment || $equipment['Status'] === 'Maintenance') {
                $_SESSION['flash'] = 'That equipment cannot be reserved right now.';
            } else {
                $startSql = $start->format('Y-m-d H:i:s');
                $endSql = $end->format('Y-m-d H:i:s');

                $conflictStmt = $db->prepare('
                    SELECT COUNT(*)
                    FROM EQUIPMENT_RESERVATION
                    WHERE EquipmentID = :equipment_id
                      AND Status = "Reserved"
                      AND StartAt < :end_at
                      AND EndAt > :start_at
                ');
                $conflictStmt->execute([
                    ':equipment_id' => $equipmentId,
                    ':start_at' => $startSql,
                    ':end_at' => $endSql,
                ]);

                if ((int)$conflictStmt->fetchColumn() > 0) {
                    $_SESSION['flash'] = 'That equipment is already reserved for that time slot.';
                } else {
                    $stmt = $db->prepare('
                        INSERT INTO EQUIPMENT_RESERVATION (EquipmentID, MemberID, StartAt, EndAt, Status)
                        VALUES (:equipment_id, :member_id, :start_at, :end_at, "Reserved")
                    ');
                    $stmt->execute([
                        ':equipment_id' => $equipmentId,
                        ':member_id' => $user['UserID'],
                        ':start_at' => $startSql,
                        ':end_at' => $endSql,
                    ]);

                    $_SESSION['flash'] = 'Equipment reserved successfully.';
                    notify($db, (int)$user['UserID'], 'booking_confirmed',
                        'Equipment Reserved: ' . $equipment['Name'],
                        'Your reservation for ' . $equipment['Name'] . ' in ' . $equipment['Location'] . ' is confirmed for ' . format_slot($startSql) . ' to ' . format_slot($endSql) . '.'
                    );
                }
            }
        }
    } elseif ($action === 'cancel_equipment_reservation') {
        $reservationId = (int)($_POST['reservation_id'] ?? 0);

        $detailStmt = $db->prepare('
            SELECT er.ReservationID, er.StartAt, er.EndAt, e.Name
            FROM EQUIPMENT_RESERVATION er
            JOIN EQUIPMENT e ON e.EquipmentID = er.EquipmentID
            WHERE er.ReservationID = :reservation_id
              AND er.MemberID = :member_id
              AND er.Status = "Reserved"
        ');
        $detailStmt->execute([
            ':reservation_id' => $reservationId,
            ':member_id' => $user['UserID'],
        ]);
        $reservation = $detailStmt->fetch();

        if (!$reservation) {
            $_SESSION['flash'] = 'Reservation not found.';
        } else {
            $stmt = $db->prepare('
                UPDATE EQUIPMENT_RESERVATION
                SET Status = "Cancelled"
                WHERE ReservationID = :reservation_id AND MemberID = :member_id AND Status = "Reserved"
            ');
            $stmt->execute([
                ':reservation_id' => $reservationId,
                ':member_id' => $user['UserID'],
            ]);

            $_SESSION['flash'] = 'Equipment reservation cancelled.';
            notify($db, (int)$user['UserID'], 'booking_cancelled',
                'Equipment Reservation Cancelled: ' . $reservation['Name'],
                'Your reservation for ' . $reservation['Name'] . ' from ' . format_slot($reservation['StartAt']) . ' to ' . format_slot($reservation['EndAt']) . ' has been cancelled.'
            );
        }
    }

    redirect_to(safe_return_to('equipment.php'));
}

$stats = [
    'total' => 0,
    'available' => 0,
    'in_use' => 0,
    'reserved' => 0,
    'maintenance' => 0,
];
$statRows = $db->query('SELECT Status, COUNT(*) AS Total FROM EQUIPMENT GROUP BY Status')->fetchAll();
foreach ($statRows as $row) {
    $stats['total'] += (int)$row['Total'];
    $key = strtolower(str_replace(' ', '_', $row['Status']));
    if (array_key_exists($key, $stats)) {
        $stats[$key] = (int)$row['Total'];
    }
}

$items = $db->query('
    SELECT
        e.EquipmentID,
        e.Name,
        e.Category,
        e.Status,
        e.Location,
        e.UpdatedAt,
        (
            SELECT er.StartAt
            FROM EQUIPMENT_RESERVATION er
            WHERE er.EquipmentID = e.EquipmentID
              AND er.Status = "Reserved"
              AND er.EndAt > datetime("now")
            ORDER BY er.StartAt ASC
            LIMIT 1
        ) AS NextReservationStart,
        (
            SELECT er.EndAt
            FROM EQUIPMENT_RESERVATION er
            WHERE er.EquipmentID = e.EquipmentID
              AND er.Status = "Reserved"
              AND er.EndAt > datetime("now")
            ORDER BY er.StartAt ASC
            LIMIT 1
        ) AS NextReservationEnd
    FROM EQUIPMENT e
    ORDER BY e.Category, e.Name
')->fetchAll();

$reservableItems = $db->query('
    SELECT EquipmentID, Name, Category, Location
    FROM EQUIPMENT
    WHERE Status <> "Maintenance"
    ORDER BY Category, Name
')->fetchAll();

$myReservations = [];
if ($user && $user['Role'] === 'Member') {
    $reservationStmt = $db->prepare('
        SELECT er.ReservationID, er.StartAt, er.EndAt, e.Name, e.Category, e.Location
        FROM EQUIPMENT_RESERVATION er
        JOIN EQUIPMENT e ON e.EquipmentID = er.EquipmentID
        WHERE er.MemberID = :member_id
          AND er.Status = "Reserved"
          AND er.EndAt > datetime("now")
        ORDER BY er.StartAt ASC
    ');
    $reservationStmt->execute([':member_id' => $user['UserID']]);
    $myReservations = $reservationStmt->fetchAll();
}

$defaultStart = (new DateTimeImmutable('+1 hour'))->format('Y-m-d\\TH:00');
$defaultEnd = (new DateTimeImmutable('+2 hours'))->format('Y-m-d\\TH:00');
$minStart = (new DateTimeImmutable('+5 minutes'))->format('Y-m-d\\TH:i');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>FitHub Gym - Equipment</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@600;700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/reset.css">
  <link rel="stylesheet" href="css/navbar.css">
  <link rel="stylesheet" href="css/pages/equipment.css">
</head>
<body>
  <?php include __DIR__ . '/includes/navbar.php'; ?>

  <main class="content">
    <header class="page-header">
      <h1>Equipment Availability</h1>
      <p>Live-ish availability refreshed with Ajax. Not actual gym telepathy, sadly.</p>
    </header>

    <?php if ($flash): ?>
      <p class="page-message"><?= e($flash) ?></p>
    <?php endif; ?>

    <div class="cards-container" id="equipment-stats">
      <div class="card card--default"><p class="card__label">Total</p><p class="card__value" data-stat="total"><?= e($stats['total']) ?></p></div>
      <div class="card card--green"><p class="card__label">Available</p><p class="card__value" data-stat="available"><?= e($stats['available']) ?></p></div>
      <div class="card card--red"><p class="card__label">In use</p><p class="card__value" data-stat="in_use"><?= e($stats['in_use']) ?></p></div>
      <div class="card card--yellow"><p class="card__label">Reserved</p><p class="card__value" data-stat="reserved"><?= e($stats['reserved']) ?></p></div>
    </div>

    <section class="reservation-panel">
      <div class="reservation-panel__header">
        <div>
          <h2>Reserve equipment</h2>
          <p>Choose a specific machine or weight station in the main training area and claim a time slot.</p>
        </div>
      </div>

      <?php if (!$user): ?>
        <p class="empty-state">Please <a href="login.php">log in</a> as a member to reserve equipment.</p>
      <?php elseif ($user['Role'] !== 'Member'): ?>
        <p class="empty-state">Only member accounts can reserve equipment.</p>
      <?php elseif (!$canReserveEquipment): ?>
        <p class="empty-state">Equipment reservations require an active Basic membership or higher. <a href="membership-plans.php">Choose a plan</a></p>
      <?php else: ?>
        <form class="reservation-form" method="post" action="equipment.php">
          <?= csrf_input() ?>
          <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
          <input type="hidden" name="action" value="reserve_equipment">

          <label>
            Equipment
            <select name="equipment_id" required>
              <?php foreach ($reservableItems as $item): ?>
                <option value="<?= e($item['EquipmentID']) ?>"><?= e($item['Name']) ?> · <?= e($item['Location']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>

          <label>
            Start
            <input type="datetime-local" name="start_at" min="<?= e($minStart) ?>" value="<?= e($defaultStart) ?>" required>
          </label>

          <label>
            End
            <input type="datetime-local" name="end_at" min="<?= e($minStart) ?>" value="<?= e($defaultEnd) ?>" required>
          </label>

          <button class="btn-reserve" type="submit">Reserve slot</button>
        </form>

        <div class="my-reservations">
          <h3>My upcoming equipment reservations</h3>
          <?php if (empty($myReservations)): ?>
            <p class="empty-state empty-state--compact">No upcoming reservations. Tragic freedom.</p>
          <?php else: ?>
            <div class="reservation-list">
              <?php foreach ($myReservations as $reservation): ?>
                <article class="reservation-item">
                  <div>
                    <h4><?= e($reservation['Name']) ?></h4>
                    <p><?= e($reservation['Category']) ?> · <?= e($reservation['Location']) ?></p>
                    <p><?= e(format_slot($reservation['StartAt'])) ?> - <?= e(format_slot($reservation['EndAt'])) ?></p>
                  </div>
                  <form method="post" action="equipment.php" class="inline-form">
                    <?= csrf_input() ?>
                    <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
                    <input type="hidden" name="action" value="cancel_equipment_reservation">
                    <input type="hidden" name="reservation_id" value="<?= e($reservation['ReservationID']) ?>">
                    <button class="btn-cancel-reservation" type="submit">Cancel</button>
                  </form>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="equipment-list-section">
      <h2>Gym floor</h2>
      <div class="equipment-list" id="equipment-list">
        <?php foreach ($items as $item): ?>
          <article class="equipment-item" data-status="<?= e($item['Status']) ?>">
            <div>
              <h3><?= e($item['Name']) ?></h3>
              <p><?= e($item['Category']) ?> · <?= e($item['Location']) ?></p>
              <?php if ($item['NextReservationStart']): ?>
                <p class="next-reservation">Next reserved: <?= e(format_slot($item['NextReservationStart'])) ?> - <?= e(format_slot($item['NextReservationEnd'])) ?></p>
              <?php endif; ?>
            </div>
            <span class="equipment-pill equipment-pill--<?= e(strtolower(str_replace(' ', '-', $item['Status']))) ?>"><?= e($item['Status']) ?></span>
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
  <script src="js/interactive-forms.js"></script>
</body>
</html>
