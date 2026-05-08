<?php session_start(); ?>
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
    <header class="page-header"><h1>Equipment Availability</h1></header>

    <div class="cards-container">
      <div class="card card--default"><p class="card__label">Total</p><p class="card__value">24</p></div>
      <div class="card card--green"><p class="card__label">Available</p><p class="card__value">16</p></div>
      <div class="card card--red"><p class="card__label">In use</p><p class="card__value">6</p></div>
      <div class="card card--yellow"><p class="card__label">Reserved</p><p class="card__value">2</p></div>
    </div>
  </main>

  <script src="js/navbar.js"></script>
</body>
</html>
