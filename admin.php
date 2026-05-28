<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';

require_login();
$user = current_user($db);

if (!$user || $user['Role'] !== 'Admin') {
    redirect_to('index.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>FitHub Gym — Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@600;700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/reset.css">
  <link rel="stylesheet" href="css/base.css">
  <link rel="stylesheet" href="css/components.css">
  <link rel="stylesheet" href="css/navbar.css">
  <link rel="stylesheet" href="css/pages/admin.css">
</head>
<body>

  <?php include __DIR__ . '/includes/navbar.php'; ?>

  <div id="admin-root"></div>
  <div id="toast-stack" style="position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;display:flex;flex-direction:column;gap:0.5rem"></div>

  <!-- Admin app (order matters: data → views → app) -->
  <script src="js/admin/admin-data.js"></script>
  <script src="js/admin/admin-views.js"></script>
  <script src="js/admin/admin-app.js"></script>

  <script src="js/navbar.js"></script>
</body>
</html>
