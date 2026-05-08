<?php
/**
 * Global navbar component.
 * Include this file inside <body> after session_start().
 * Requires $db (PDO) from db.php.
 */

require_once __DIR__ . '/db.php';

// ── Determine logged-in user ──
$loggedIn  = isset($_SESSION['user_id']);
$userName  = '';

if ($loggedIn) {
    $stmt = $db->prepare('SELECT Name FROM USER WHERE UserID = :id');
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if ($user) {
        $userName = htmlspecialchars($user['Name']);
    } else {
        // Session references a user that no longer exists
        $loggedIn = false;
    }
}

// ── Build initials for the avatar ──
$initials = '';
if ($loggedIn && $userName) {
    $parts    = explode(' ', $userName);
    $initials = strtoupper(substr($parts[0], 0, 1));
    if (count($parts) > 1) {
        $initials .= strtoupper(substr(end($parts), 0, 1));
    }
}
?>

<nav class="navbar" id="main-navbar">
  <a href="index.php" class="nav-logo">FitHub Gym</a>

  <!-- Hamburger button (mobile) -->
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
      <?php if ($loggedIn): ?>
        <a href="edit-profile.php" class="nav-user">
          <span class="nav-avatar"><?= $initials ?></span>
          <span class="nav-username"><?= $userName ?></span>
        </a>
        <a href="logout.php" class="btn-login">Logout</a>
      <?php else: ?>
        <a href="login.php" class="btn-login">Login</a>
        <a href="register.php" class="btn-join">Join Now</a>
      <?php endif; ?>
    </div>
  </div>
</nav>
