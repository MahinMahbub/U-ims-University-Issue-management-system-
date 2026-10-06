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

/* ---------------------------------------------------------------------------
 * CSRF protection (shared by the feed, registration and every admin form)
 * ------------------------------------------------------------------------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_valid(?string $token): bool
{
    return is_string($token) && $token !== ''
        && isset($_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $token);
}

/** Hidden input to drop inside every POST form. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Stops the request when a POST does not carry a valid token. */
function require_csrf(): void
{
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        http_response_code(419);
        exit("Your session expired. Go back, refresh the page and try again.");
    }
}

/* ---------------------------------------------------------------------------
 * Flash messages: set one before a redirect, show it on the next page.
 * ------------------------------------------------------------------------- */

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/** Returns the pending message as HTML (and clears it), or an empty string. */
function flash_render(): string
{
    if (empty($_SESSION['flash'])) {
        return '';
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    $class = $flash['type'] === 'error' ? 'alert error' : 'alert success';
    return '<div class="' . $class . '" role="status">' . e((string) $flash['message']) . '</div>';
}

/** Badge colour for an issue status (used on every page that lists issues). */
function status_badge(string $status): string
{
    switch ($status) {
        case 'Resolved':
        case 'Verified':
            return 'green';
        case 'Rejected':
            return 'red';
        case 'In Progress':
            return 'blue';
        case 'Closed':
            return '';
        default:
            return 'orange';
    }
}

/** Character count that works with or without the mbstring extension. */
function text_length(string $text): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($text, 'UTF-8');
    }
    return (int) preg_match_all('/./su', $text);
}
?>
