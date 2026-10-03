<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function require_login(): void
{
    if (!isset($_SESSION['user'])) {
        header("Location: /uims/login.php");
        exit;
    }
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_role(array $roles): void
{
    require_login();
    $role = $_SESSION['user']['role_name'] ?? '';
    if (!in_array($role, $roles, true)) {
        http_response_code(403);
        exit("Access denied.");
    }
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>