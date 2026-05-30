<?php
/** Global navbar component. Include inside <body>. */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/membership-access.php';

$navUser = current_user($db);
$loggedIn = $navUser !== null;
$userName = $loggedIn ? $navUser['Name'] : '';
$role = $loggedIn ? $navUser['Role'] : '';
$navMembershipPlan = ($loggedIn && $role === 'Member') ? active_membership_plan($db, $navUser) : null;
$navHasBasic = membership_plan_allows($navMembershipPlan, 'Basic');
$navHasPremium = membership_plan_allows($navMembershipPlan, 'Premium');
$navHasElite = membership_plan_allows($navMembershipPlan, 'Elite');

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

<?php if ($loggedIn): ?>
  <script>
    window.CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;
  </script>
<?php endif; ?>

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

    <div class="nav-actions">
      <?php if ($loggedIn && $role === 'Trainer'): ?>
        <a href="trainer-analytics.php" class="btn-analytics">My Analytics</a>
        <a href="trainer-roster.php" class="btn-analytics">My Roster</a>
        <a href="trainer-nutrition.php" class="btn-analytics">Nutrition Plans</a>
      <?php elseif ($loggedIn && $role === 'Member'): ?>
        <?php if ($navHasBasic): ?>
          <a href="my-classes.php" class="btn-analytics">My Classes</a>
          <a href="progress.php" class="btn-analytics">My Progress</a>
        <?php endif; ?>
        <?php if ($navHasPremium): ?>
          <a href="nutrition.php" class="btn-analytics">My Nutrition</a>
        <?php endif; ?>
        <?php if ($navHasElite): ?>
          <a href="personal-training.php" class="btn-analytics">Personal Training</a>
        <?php endif; ?>
        <?php if (!$navHasBasic): ?>
          <a href="membership-plans.php" class="btn-analytics">Choose Plan</a>
        <?php endif; ?>
        <a href="reports.php" class="btn-analytics">Reports</a>
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
        <div class="nav-user-dropdown" id="nav-user-dropdown">
          <button class="nav-user nav-user-toggle" type="button" aria-haspopup="true" aria-expanded="false" id="nav-user-btn">
            <span class="nav-avatar"><?= e($initials) ?></span>
            <span class="nav-username"><?= e($userName) ?></span>
            <span class="nav-role"><?= e($role) ?></span>
            <span class="nav-caret" aria-hidden="true">&#9662;</span>
          </button>
          <div class="nav-user-menu" id="nav-user-menu" role="menu">
            <a href="edit-profile.php" class="nav-user-menu-item" role="menuitem">Edit Profile</a>
            <?php if ($role === 'Member' || $role === 'Admin'): ?>
              <a href="membership-plans.php" class="nav-user-menu-item" role="menuitem">Membership Plans</a>
            <?php endif; ?>
            <?php if ($role === 'Admin'): ?>
              <a href="admin.php" class="nav-user-menu-item" role="menuitem">Admin Dashboard</a>
            <?php endif; ?>
            <a href="logout.php" class="nav-user-menu-item nav-user-menu-item--logout" role="menuitem">Logout</a>
          </div>
        </div>
      <?php else: ?>
        <a href="login.php" class="btn-login">Login</a>
        <a href="register.php" class="btn-join">Join Now</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

