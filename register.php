<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($name === '' || $username === '' || $password === '' || $confirm === '') {
        $error = 'Please fill in all fields.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($username) < 3) {
        $error = 'Username must be at least 3 characters.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $stmt = $db->prepare('SELECT UserID FROM USER WHERE Username = :username');
        $stmt->execute([':username' => $username]);

        if ($stmt->fetch()) {
            $error = 'Username already taken.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare('INSERT INTO USER (Name, Username, PasswordHash, Role, Email, IsActive) VALUES (:name, :username, :hash, :role, :email, 1)');
            $stmt->execute([
                ':name' => $name,
                ':username' => $username,
                ':hash' => $hash,
                ':role' => 'Member',
                ':email' => $email ?: null,
            ]);

            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$db->lastInsertId();
            $_SESSION['user_name'] = $name;
            $_SESSION['user_role'] = 'Member';
            redirect_to('index.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register - FitHub Gym</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@600;700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/reset.css">
  <link rel="stylesheet" href="css/base.css">
  <link rel="stylesheet" href="css/components.css">
  <link rel="stylesheet" href="css/navbar.css">
  <link rel="stylesheet" href="css/pages/auth.css">
</head>
<body>
  <?php include __DIR__ . '/includes/navbar.php'; ?>

  <main class="Content">
    <header class="page-header">
      <h1>Join FitHub Gym</h1>
      <p>Create your account to get started</p>
    </header>

    <?php if ($error): ?>
      <p class="form-message form-message--error"><?= e($error) ?></p>
    <?php endif; ?>

    <form action="register.php" method="post">
      <?= csrf_input() ?>

      <label for="full-name">Full name</label>
      <input id="full-name" type="text" name="full_name" class="input-name" placeholder="Your name" value="<?= e($_POST['full_name'] ?? '') ?>" required>

      <label for="username">Username</label>
      <input id="username" type="text" name="username" class="input-username" placeholder="Username" value="<?= e($_POST['username'] ?? '') ?>" required>

      <label for="email">Email <span style="color:#9ca3af;font-weight:400;font-size:0.85em">(optional — for notifications)</span></label>
      <input id="email" type="email" name="email" class="input-username" placeholder="you@example.com" value="<?= e($_POST['email'] ?? '') ?>">

      <label for="password">Password</label>
      <input id="password" type="password" name="password" class="input-password" placeholder="Minimum 6 characters" minlength="6" required>

      <label for="confirm-password">Confirm password</label>
      <input id="confirm-password" type="password" name="confirm_password" class="input-confirm-password" placeholder="Re-enter your password" minlength="6" required>

      <button type="submit" class="btn-clear">Create Account</button>
    </form>

    <p>Already have an account? <a href="login.php">Log in here</a></p>
  </main>

  <footer class="main-footer">
    <div class="footer-content">
      <p>&copy; 2026 FitHub Gym. Move your body, change your life.</p>
      <div class="social-links"><a href="#">Instagram</a><a href="#">Facebook</a></div>
    </div>
  </footer>
  <script src="js/navbar.js"></script>
</body>
</html>
