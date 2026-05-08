<?php
session_start();

// ── Handle register POST ──
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/includes/db.php';

    $name     = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (!$name || !$username || !$password || !$confirm) {
        $error = 'Please fill in all fields.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        // Check if username already exists
        $stmt = $db->prepare('SELECT UserID FROM USER WHERE Username = :u');
        $stmt->execute([':u' => $username]);

        if ($stmt->fetch()) {
            $error = 'Username already taken.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare('INSERT INTO USER (Name, Username, PasswordHash, Role, IsActive) VALUES (:name, :user, :hash, :role, 1)');
            $stmt->execute([
                ':name' => $name,
                ':user' => $username,
                ':hash' => $hash,
                ':role' => 'Member',
            ]);

            // Auto-login after register
            $_SESSION['user_id'] = $db->lastInsertId();
            $_SESSION['user_name'] = $name;
            header('Location: index.php');
            exit;
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
          <p style="color:#ef4444; text-align:center; font-weight:600; margin-bottom:1rem;"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form action="register.php" method="post">

            <label for="full-name">Full name</label>
            <input
              id="full-name"
              type="text"
              name="full_name"
              class="input-name"
              placeholder="Your Name"
              value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
              required
            >

            <label for="username">Username</label>
            <input
              id="username"
              type="text"
              name="username"
              class="input-username"
              placeholder="Username"
              value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
              required
            >

            <label for="password">Password</label>
            <input
              id="password"
              type="password"
              name="password"
              class="input-password"
              placeholder="Minimum 6 characters"
              minlength="6"
              required
            >

            <label for="confirm-password">Confirm Password</label>
            <input
              id="confirm-password"
              type="password"
              name="confirm_password"
              class="input-confirm-password"
              placeholder="Re-enter your password"
              minlength="6"
              required
            >

            <button type="submit" class="btn-clear">Create Account</button>

        </form>

        <p>Already have an account? <a href="login.php">Log in Here</a></p>

    </main>

  <script src="js/navbar.js"></script>
</body>
</html>
