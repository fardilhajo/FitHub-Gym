<?php
session_start();

// ── Handle login POST ──
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/includes/db.php';

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $stmt = $db->prepare('SELECT UserID, Name, PasswordHash FROM USER WHERE Username = :u AND IsActive = 1');
        $stmt->execute([':u' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['PasswordHash'])) {
            $_SESSION['user_id'] = $user['UserID'];
            $_SESSION['user_name'] = $user['Name'];
            header('Location: index.php');
            exit;
        }
        $error = 'Invalid username or password.';
    } else {
        $error = 'Please fill in all fields.';
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
          <p style="color:#ef4444; text-align:center; font-weight:600; margin-bottom:1rem;"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form action="login.php" method="post">

            <label for="username">Username</label>
            <input
              id="username"
              type="text"
              name="username"
              class="input-email"
              placeholder="Your username"
              value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
              required
            >

            <label for="password">Password</label>
            <input
              id="password"
              type="password"
              name="password"
              class="input-password"
              placeholder="Enter your password"
              minlength="6"
              required
            >
             
            <button type="submit" class="btn-clear">Login</button>

        </form>

        <p>Don't have an account?<a href="register.php">Register Here</a></p>

    </main>

  <script src="js/navbar.js"></script>
</body>
</html>
