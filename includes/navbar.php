<?php
/** Global navbar component. Include inside <body>. */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

$navUser = current_user($db);
$loggedIn = $navUser !== null;
$userName = $loggedIn ? $navUser['Name'] : '';
$role = $loggedIn ? $navUser['Role'] : '';

$initials = '';
$navUnreadCount = 0;
if ($loggedIn) {
    $parts = preg_split('/\s+/', trim($userName));
    $initials = strtoupper(substr($parts[0] ?? '', 0, 1));
    if (count($parts) > 1) {
        $initials .= strtoupper(substr(end($parts), 0, 1));
    }

    $unreadStmt = $db->prepare('SELECT COUNT(*) FROM NOTIFICATION WHERE UserID = :uid AND IsRead = 0');
    $unreadStmt->execute([':uid' => $navUser['UserID']]);
    $navUnreadCount = (int)$unreadStmt->fetchColumn();
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
        <a href="my-classes.php" class="btn-analytics">My Classes</a>
        <a href="progress.php" class="btn-analytics">My Progress</a>
        <a href="nutrition.php" class="btn-analytics">My Nutrition</a>
      <?php endif; ?>
      <?php if ($loggedIn): ?>
        <div class="nav-bell-wrapper" id="nav-bell-wrapper">
          <button class="nav-bell" id="nav-bell-btn" aria-label="Notifications" type="button">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
              <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
            </svg>
            <?php if ($navUnreadCount > 0): ?>
              <span class="bell-badge" id="bell-badge"><?= min($navUnreadCount, 99) ?></span>
            <?php else: ?>
              <span class="bell-badge bell-badge--hidden" id="bell-badge"></span>
            <?php endif; ?>
          </button>
          <div class="notif-dropdown" id="notif-dropdown" aria-hidden="true">
            <div class="notif-header">
              <span>Notifications</span>
              <button class="notif-read-all" id="notif-read-all" type="button">Mark all read</button>
            </div>
            <ul class="notif-list" id="notif-list">
              <li class="notif-empty">Loading…</li>
            </ul>
          </div>
        </div>
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
