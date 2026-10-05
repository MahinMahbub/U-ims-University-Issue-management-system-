<?php
require "config/database.php";
require "config/auth.php";
require "config/social.php";

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function json_out(array $data, int $status = 200)
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Any unexpected failure still answers with JSON so the page can show a friendly message.
set_exception_handler(function (Throwable $e) {
    error_log('issue_social.php: ' . $e->getMessage());
    json_out(['error' => 'Something went wrong. Try again.'], 500);
});

/** Only issues that appear on the public feed can be reacted to or discussed. */
function feed_issue_exists(PDO $pdo, int $issueId): bool
{
    if ($issueId <= 0) {
        return false;
    }
    $stmt = $pdo->prepare("SELECT 1 FROM issues WHERE issue_id=? AND status IN (" . in_placeholders(FEED_STATUSES) . ")");
    $stmt->execute(array_merge([$issueId], FEED_STATUSES));
    return (bool) $stmt->fetchColumn();
}

function reaction_summary(PDO $pdo, int $issueId, int $userId): array
{
    $counts = array_fill_keys(array_keys(SOCIAL_REACTIONS), 0);
    $stmt = $pdo->prepare("SELECT reaction, COUNT(*) c FROM issue_reactions WHERE issue_id=? GROUP BY reaction");
    $stmt->execute([$issueId]);
    foreach ($stmt as $row) {
        if (isset($counts[$row['reaction']])) {
            $counts[$row['reaction']] = (int) $row['c'];
        }
    }
    $mine = null;
    if ($userId > 0) {
        $stmt = $pdo->prepare("SELECT reaction FROM issue_reactions WHERE issue_id=? AND user_id=?");
        $stmt->execute([$issueId, $userId]);
        $found = $stmt->fetchColumn();
        $mine = ($found !== false && isset($counts[$found])) ? $found : null;
    }
    return ['counts' => $counts, 'mine' => $mine];
}

function comment_count(PDO $pdo, int $issueId): int
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM issue_comments WHERE issue_id=?");
    $stmt->execute([$issueId]);
    return (int) $stmt->fetchColumn();
}

function comment_payload(array $row, ?array $viewer): array
{
    $isStaff = in_array($row['role_name'], ['Authority', 'Administrator'], true);
    $viewerId = (int) ($viewer['user_id'] ?? 0);
    $viewerIsStaff = $viewer && in_array($viewer['role_name'] ?? '', ['Authority', 'Administrator'], true);
    return [
        'id' => (int) $row['comment_id'],
        'name' => $row['name'],
        'role' => $isStaff ? $row['role_name'] : '',
        'body' => $row['body'],
        'ago' => time_ago((int) $row['age_seconds']),
        'can_delete' => $viewerId > 0 && ((int) $row['user_id'] === $viewerId || $viewerIsStaff),
    ];
}

const COMMENT_SELECT = "SELECT c.comment_id, c.user_id, c.body, u.name, r.role_name,
        TIMESTAMPDIFF(SECOND, c.created_at, NOW()) AS age_seconds
    FROM issue_comments c
    JOIN users u ON u.user_id = c.user_id
    JOIN roles r ON r.role_id = u.role_id";

if (!ensure_social_tables($pdo)) {
    json_out(['error' => 'Reactions and comments are unavailable right now.'], 503);
}

$viewer = current_user();
$method = $_SERVER['REQUEST_METHOD'];

// Reading comments is public, like the feed itself.
if ($method === 'GET') {
    $issueId = (int) ($_GET['issue_id'] ?? 0);
    if (($_GET['action'] ?? '') !== 'comments' || !feed_issue_exists($pdo, $issueId)) {
        json_out(['error' => 'Issue not found.'], 404);
    }
    $stmt = $pdo->prepare("SELECT * FROM (" . COMMENT_SELECT . " WHERE c.issue_id=? ORDER BY c.created_at DESC, c.comment_id DESC LIMIT 100) latest ORDER BY comment_id ASC");
    $stmt->execute([$issueId]);
    $comments = array_map(fn($row) => comment_payload($row, $viewer), $stmt->fetchAll());
    json_out(['comments' => $comments, 'count' => comment_count($pdo, $issueId)]);
}

if ($method !== 'POST') {
    json_out(['error' => 'Method not allowed.'], 405);
}
if (!$viewer) {
    json_out(['error' => 'Log in to react or comment.'], 401);
}
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? null);
if (!csrf_valid($token)) {
    json_out(['error' => 'Your session expired. Refresh the page and try again.'], 403);
}

$userId = (int) $viewer['user_id'];
$action = $_POST['action'] ?? '';

if ($action === 'react') {
    $issueId = (int) ($_POST['issue_id'] ?? 0);
    $reaction = $_POST['reaction'] ?? '';
    if (!isset(SOCIAL_REACTIONS[$reaction]) || !feed_issue_exists($pdo, $issueId)) {
        json_out(['error' => 'Reaction not available.'], 422);
    }
    $current = reaction_summary($pdo, $issueId, $userId)['mine'];
    if ($current === $reaction) {
        // Tapping the active reaction again removes it.
        $pdo->prepare("DELETE FROM issue_reactions WHERE issue_id=? AND user_id=?")->execute([$issueId, $userId]);
    } else {
        $pdo->prepare("INSERT INTO issue_reactions(issue_id,user_id,reaction) VALUES(?,?,?) ON DUPLICATE KEY UPDATE reaction=VALUES(reaction)")
            ->execute([$issueId, $userId, $reaction]);
    }
    json_out(reaction_summary($pdo, $issueId, $userId));
}

if ($action === 'comment') {
    $issueId = (int) ($_POST['issue_id'] ?? 0);
    $body = trim((string) ($_POST['body'] ?? ''));
    if (!feed_issue_exists($pdo, $issueId)) {
        json_out(['error' => 'Issue not found.'], 404);
    }
    if ($body === '') {
        json_out(['error' => 'Write something before sending.'], 422);
    }
    if (text_length($body) > COMMENT_MAX_LENGTH) {
        json_out(['error' => 'Comments can be up to ' . COMMENT_MAX_LENGTH . ' characters.'], 422);
    }
    // Basic flood control: one comment every 3 seconds per person.
    $stmt = $pdo->prepare("SELECT TIMESTAMPDIFF(SECOND, MAX(created_at), NOW()) FROM issue_comments WHERE user_id=?");
    $stmt->execute([$userId]);
    $since = $stmt->fetchColumn();
    if ($since !== null && $since !== false && (int) $since < 3) {
        json_out(['error' => 'You are posting too quickly. Wait a moment and try again.'], 429);
    }
    $pdo->prepare("INSERT INTO issue_comments(issue_id,user_id,body) VALUES(?,?,?)")->execute([$issueId, $userId, $body]);
    $newId = (int) $pdo->lastInsertId();
    $stmt = $pdo->prepare(COMMENT_SELECT . " WHERE c.comment_id=?");
    $stmt->execute([$newId]);
    json_out([
        'comment' => comment_payload($stmt->fetch(), $viewer),
        'count' => comment_count($pdo, $issueId),
    ], 201);
}

if ($action === 'delete_comment') {
    $commentId = (int) ($_POST['comment_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT issue_id, user_id FROM issue_comments WHERE comment_id=?");
    $stmt->execute([$commentId]);
    $row = $stmt->fetch();
    if (!$row) {
        json_out(['error' => 'Comment not found.'], 404);
    }
    $isStaff = in_array($viewer['role_name'] ?? '', ['Authority', 'Administrator'], true);
    if ((int) $row['user_id'] !== $userId && !$isStaff) {
        json_out(['error' => 'You can only delete your own comments.'], 403);
    }
    $pdo->prepare("DELETE FROM issue_comments WHERE comment_id=?")->execute([$commentId]);
    json_out(['count' => comment_count($pdo, (int) $row['issue_id'])]);
}

json_out(['error' => 'Unknown action.'], 400);
