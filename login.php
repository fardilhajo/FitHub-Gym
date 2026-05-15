<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = $db->prepare('SELECT UserID, Name, Role, PasswordHash FROM USER WHERE Username = :username AND IsActive = 1');
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['PasswordHash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['UserID'];
            $_SESSION['user_name'] = $user['Name'];
            $_SESSION['user_role'] = $user['Role'];
            redirect_to('index.php');
        }

        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - FitHub Gym</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@600;700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/reset.css">
  <link rel="stylesheet" href="css/navbar.css">
  <link rel="stylesheet" href="css/pages/auth.css">
</head>
<body>
  <?php include __DIR__ . '/includes/navbar.php'; ?>

  <main class="Content">
    <header class="page-header">
      <h1>Welcome Back</h1>
      <p>Login to access your account</p>
    </header>

    <?php if ($error): ?>
      <p class="form-message form-message--error"><?= e($error) ?></p>
    <?php endif; ?>

    <form action="login.php" method="post">
      <?= csrf_input() ?>

      <label for="username">Username</label>
      <input id="username" type="text" name="username" class="input-email" placeholder="Your username" value="<?= e($_POST['username'] ?? '') ?>" required>

      <label for="password">Password</label>
      <input id="password" type="password" name="password" class="input-password" placeholder="Enter your password" minlength="6" required>

      <button type="submit" class="btn-clear">Login</button>
    </form>

    <p>Don't have an account? <a href="register.php">Register here</a></p>
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
