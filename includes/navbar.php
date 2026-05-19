<?php
/** Global navbar component. Include inside <body>. */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

$navUser = current_user($db);
$loggedIn = $navUser !== null;
$userName = $loggedIn ? $navUser['Name'] : '';
$role = $loggedIn ? $navUser['Role'] : '';

$initials = '';
if ($loggedIn) {
    $parts = preg_split('/\s+/', trim($userName));
    $initials = strtoupper(substr($parts[0] ?? '', 0, 1));
    if (count($parts) > 1) {
        $initials .= strtoupper(substr(end($parts), 0, 1));
    }
}
?>

<nav class="navbar" id="main-navbar">
  <a href="index.php" class="nav-logo">FitHub Gym</a>

  <button class="nav-toggle" id="nav-toggle" type="button" aria-label="Toggle navigation" aria-expanded="false">
    <span class="hamburger-line"></span>
    <span class="hamburger-line"></span>
    <span class="hamburger-line"></span>
  </button>

  <div class="nav-menu" id="nav-menu">
    <ul class="nav-links">
      <li><a href="index.php">Home</a></li>
      <li><a href="book-class.php">Classes</a></li>
      <li><a href="trainers.php">Trainers</a></li>
      <li><a href="equipment.php">Equipment</a></li>
      <?php if ($role === 'Admin'): ?>
        <li><a href="admin.php" class="nav-admin-link">Admin Dashboard</a></li>
      <?php endif; ?>
    </ul>

    <div class="nav-search" id="nav-search-wrapper" role="search">
      <span class="nav-search-icon" aria-hidden="true">⌕</span>
      <input id="navbar-search" class="nav-search-input" type="search" placeholder="Search classes" autocomplete="off" aria-label="Search classes">
      <div id="search-results" class="search-results" aria-live="polite"></div>
    </div>

    <div class="nav-actions">
      <?php if ($loggedIn && $role === 'Trainer'): ?>
        <a href="trainer-analytics.php" class="btn-analytics">My Analytics</a>
        <a href="trainer-nutrition.php" class="btn-analytics">Nutrition Plans</a>
      <?php elseif ($loggedIn && $role === 'Member'): ?>
        <a href="progress.php" class="btn-analytics">My Progress</a>
        <a href="nutrition.php" class="btn-analytics">My Nutrition</a>
      <?php endif; ?>
      <?php if ($loggedIn): ?>
        <a href="edit-profile.php" class="nav-user" title="Edit profile">
          <span class="nav-avatar"><?= e($initials) ?></span>
          <span class="nav-username"><?= e($userName) ?></span>
          <span class="nav-role"><?= e($role) ?></span>
        </a>
        <a href="logout.php" class="btn-login">Logout</a>
      <?php else: ?>
        <a href="login.php" class="btn-login">Login</a>
        <a href="register.php" class="btn-join">Join Now</a>
      <?php endif; ?>
    </div>
  </div>
</nav>
