<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';

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

$items = $db->query('SELECT EquipmentID, Name, Category, Status, Location, UpdatedAt FROM EQUIPMENT ORDER BY Category, Name')->fetchAll();
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

    <div class="cards-container" id="equipment-stats">
      <div class="card card--default"><p class="card__label">Total</p><p class="card__value" data-stat="total"><?= e($stats['total']) ?></p></div>
      <div class="card card--green"><p class="card__label">Available</p><p class="card__value" data-stat="available"><?= e($stats['available']) ?></p></div>
      <div class="card card--red"><p class="card__label">In use</p><p class="card__value" data-stat="in_use"><?= e($stats['in_use']) ?></p></div>
      <div class="card card--yellow"><p class="card__label">Reserved</p><p class="card__value" data-stat="reserved"><?= e($stats['reserved']) ?></p></div>
    </div>

    <section class="equipment-list-section">
      <h2>Gym floor</h2>
      <div class="equipment-list" id="equipment-list">
        <?php foreach ($items as $item): ?>
          <article class="equipment-item" data-status="<?= e($item['Status']) ?>">
            <div>
              <h3><?= e($item['Name']) ?></h3>
              <p><?= e($item['Category']) ?> · <?= e($item['Location']) ?></p>
            </div>
            <span class="equipment-pill equipment-pill--<?= e(strtolower(str_replace(' ', '-', $item['Status']))) ?>"><?= e($item['Status']) ?></span>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  </main>

  <script src="js/navbar.js"></script>
</body>
</html>
