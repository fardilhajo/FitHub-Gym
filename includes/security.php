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

function safe_return_to(string $fallback): string
{
    $candidate = $_POST['return_to'] ?? $_GET['return_to'] ?? '';

    if (!is_string($candidate) || trim($candidate) === '') {
        return $fallback;
    }

    $candidate = trim($candidate);

    if (str_starts_with($candidate, '//')) {
        return $fallback;
    }

    $parts = parse_url($candidate);
    if ($parts === false || isset($parts['scheme']) || isset($parts['host'])) {
        return $fallback;
    }

    $candidatePath = $parts['path'] ?? '';
    $fallbackPath = parse_url($fallback, PHP_URL_PATH) ?: $fallback;

    if (basename($candidatePath) !== basename($fallbackPath)) {
        return $fallback;
    }

    $target = basename($fallbackPath);
    if (!empty($parts['query'])) {
        $target .= '?' . $parts['query'];
    }

    return $target;
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

    if (empty($token) && isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
    }

    if (empty($token)) {
        $input = file_get_contents('php://input');
        if (!empty($input)) {
            $data = json_decode($input, true);
            if (is_array($data) && isset($data['csrf_token'])) {
                $token = $data['csrf_token'];
            }
        }
    }

    if (!is_string($token) || empty($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        echo 'Invalid CSRF token';
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
