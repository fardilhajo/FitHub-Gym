<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect_to(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_input(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        echo 'Invalid request token.';
        exit;
    }
}

function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        redirect_to('login.php');
    }
}

function current_user(PDO $db): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = $db->prepare('SELECT UserID, Name, Username, Role, ProfilePhoto, IsActive FROM USER WHERE UserID = :id AND IsActive = 1');
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        session_unset();
        return null;
    }

    return $user;
}
