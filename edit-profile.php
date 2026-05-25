<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';

require_login();

$userId = (int)$_SESSION['user_id'];
$error = '';
$success = '';

$profileStmt = $db->prepare('SELECT UserID, Name, Username, Role, ProfilePhoto, Email FROM USER WHERE UserID = :id AND IsActive = 1');
$profileStmt->execute([':id' => $userId]);
$profileUser = $profileStmt->fetch();

if (!$profileUser) {
    session_destroy();
    redirect_to('login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($name === '' || $username === '') {
        $error = 'Name and username are required.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($username) < 3) {
        $error = 'Username must be at least 3 characters.';
    } elseif ($password !== '' && strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== '' && $password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $check = $db->prepare('SELECT UserID FROM USER WHERE Username = :username AND UserID != :id');
        $check->execute([':username' => $username, ':id' => $userId]);

        if ($check->fetch()) {
            $error = 'Username already taken.';
        } else {
            $photoPath = $profileUser['ProfilePhoto'];

            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/uploads/photos/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $allowedMime = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/gif' => 'gif',
                    'image/webp' => 'webp',
                ];
                $mime = mime_content_type($_FILES['photo']['tmp_name']);

                if (!isset($allowedMime[$mime])) {
                    $error = 'Invalid image format. Use JPG, PNG, GIF or WebP.';
                } elseif ($_FILES['photo']['size'] > 5 * 1024 * 1024) {
                    $error = 'Image too large. Maximum 5 MB.';
                } else {
                    $filename = 'user_' . $userId . '_' . bin2hex(random_bytes(8)) . '.' . $allowedMime[$mime];
                    $destPath = $uploadDir . $filename;

                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $destPath)) {
                        $photoPath = 'uploads/photos/' . $filename;
                    } else {
                        $error = 'Failed to upload photo.';
                    }
                }
            }

            if ($error === '') {
                if ($password !== '') {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $upd = $db->prepare('UPDATE USER SET Name = :name, Username = :username, PasswordHash = :hash, ProfilePhoto = :photo, Email = :email WHERE UserID = :id');
                    $upd->execute([':name' => $name, ':username' => $username, ':hash' => $hash, ':photo' => $photoPath, ':email' => $email ?: null, ':id' => $userId]);
                } else {
                    $upd = $db->prepare('UPDATE USER SET Name = :name, Username = :username, ProfilePhoto = :photo, Email = :email WHERE UserID = :id');
                    $upd->execute([':name' => $name, ':username' => $username, ':photo' => $photoPath, ':email' => $email ?: null, ':id' => $userId]);
                }

                $_SESSION['user_name'] = $name;
                $profileStmt->execute([':id' => $userId]);
                $profileUser = $profileStmt->fetch();
                $success = 'Profile updated successfully.';
            }
        }
    }
}

$avatarSrc = $profileUser['ProfilePhoto'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($profileUser['Name']) . '&background=f18c4f&color=fff&size=128';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Profile - FitHub Gym</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@600;700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/reset.css">
  <link rel="stylesheet" href="css/navbar.css">
  <link rel="stylesheet" href="css/pages/edit-profile.css">
</head>
<body>
  <?php include __DIR__ . '/includes/navbar.php'; ?>

  <main class="edit-profile-content">
    <header class="page-header">
      <h1>Edit Profile</h1>
      <p>Update your personal information and account settings</p>
    </header>

    <?php if ($error): ?>
      <p class="form-message form-message--error"><?= e($error) ?></p>
    <?php endif; ?>

    <?php if ($success): ?>
      <p class="form-message form-message--success"><?= e($success) ?></p>
    <?php endif; ?>

    <form action="edit-profile.php" method="post" enctype="multipart/form-data" class="edit-profile-form">
      <?= csrf_input() ?>

      <div class="avatar-section">
        <div class="avatar-wrapper">
          <img id="avatar-preview" src="<?= e($avatarSrc) ?>" alt="Profile photo" class="avatar-img">
          <label for="photo" class="avatar-upload-btn" aria-label="Upload profile photo">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
              <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
              <circle cx="12" cy="13" r="4"/>
            </svg>
          </label>
          <input id="photo" type="file" name="photo" accept="image/*" class="avatar-file-input">
        </div>
        <p class="avatar-hint">Click the camera icon to upload a new photo</p>
      </div>

      <fieldset class="form-group">
        <legend>Personal Information</legend>

        <label for="full-name">Full Name</label>
        <input id="full-name" type="text" name="full_name" placeholder="Your full name" value="<?= e($profileUser['Name']) ?>" required>

        <label for="username">Username</label>
        <input id="username" type="text" name="username" placeholder="Username" value="<?= e($profileUser['Username']) ?>" required>

        <label for="email">Email <span class="legend-hint">(optional — used for notifications)</span></label>
        <input id="email" type="email" name="email" placeholder="you@example.com" value="<?= e($profileUser['Email'] ?? '') ?>">
      </fieldset>

      <fieldset class="form-group">
        <legend>Change Password <span class="legend-hint">(leave blank to keep current)</span></legend>

        <label for="password">New Password</label>
        <input id="password" type="password" name="password" placeholder="Minimum 6 characters" minlength="6">

        <label for="confirm-password">Confirm New Password</label>
        <input id="confirm-password" type="password" name="confirm_password" placeholder="Re-enter new password" minlength="6">
      </fieldset>

      <button type="submit" class="btn-save">Save Changes</button>
    </form>

    <p class="back-link"><a href="index.php">&larr; Back to Dashboard</a></p>
  </main>

  <script>
    const photoInput = document.getElementById('photo');
    if (photoInput) {
      photoInput.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = e => { document.getElementById('avatar-preview').src = e.target.result; };
        reader.readAsDataURL(file);
      });
    }
  </script>
  <footer class="main-footer">
    <div class="footer-content">
      <p>&copy; 2026 FitHub Gym. Move your body, change your life.</p>
      <div class="social-links"><a href="#">Instagram</a><a href="#">Facebook</a></div>
    </div>
  </footer>
  <script src="js/navbar.js"></script>
</body>
</html>
