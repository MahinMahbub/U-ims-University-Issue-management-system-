<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

/*
 * Helpers for the public issue feed (issues.php): reactions, comments,
 * CSRF protection and relative timestamps.
 */

// Statuses that appear on the public feed. Rejected issues are shown on purpose,
// together with the rejection reason, so authority decisions stay transparent.
const FEED_STATUSES = ['Verified', 'In Progress', 'Resolved', 'Closed', 'Rejected'];

// One reaction per person per issue. Keys are stored in issue_reactions.reaction.
const SOCIAL_REACTIONS = [
    'support' => ['emoji' => "\u{1F44D}", 'label' => 'Support'],
    'same'    => ['emoji' => "\u{1F64B}", 'label' => 'Same here'],
    'urgent'  => ['emoji' => "\u{1F525}", 'label' => 'Urgent'],
];

const COMMENT_MAX_LENGTH = 500;

/**
 * Creates the reactions/comments tables if they do not exist yet, so the feed
 * keeps working on databases that were set up from an older database.sql.
 * Returns false when the tables are missing and could not be created.
 */
function ensure_social_tables(PDO $pdo): bool
{
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS issue_reactions (
            reaction_id INT AUTO_INCREMENT PRIMARY KEY,
            issue_id INT NOT NULL,
            user_id INT NOT NULL,
            reaction VARCHAR(20) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_issue_user (issue_id, user_id),
            FOREIGN KEY (issue_id) REFERENCES issues(issue_id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB");

        $pdo->exec("CREATE TABLE IF NOT EXISTS issue_comments (
            comment_id INT AUTO_INCREMENT PRIMARY KEY,
            issue_id INT NOT NULL,
            user_id INT NOT NULL,
            body VARCHAR(500) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_issue_created (issue_id, created_at),
            FOREIGN KEY (issue_id) REFERENCES issues(issue_id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB");
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/** "3 days ago" style text from an age in seconds (computed by MySQL, so time zones always agree). */
function time_ago(int $seconds): string
{
    $seconds = max(0, $seconds);
    if ($seconds < 60) {
        return 'just now';
    }
    $units = [
        [31536000, 'year'],
        [2592000, 'month'],
        [604800, 'week'],
        [86400, 'day'],
        [3600, 'hour'],
        [60, 'minute'],
    ];
    foreach ($units as [$size, $name]) {
        if ($seconds >= $size) {
            $n = intdiv($seconds, $size);
            return $n . ' ' . $name . ($n > 1 ? 's' : '') . ' ago';
        }
    }
    return 'just now';
}

/** True when an evidence row points at an image we can preview as a thumbnail. */
function evidence_is_image(array $row): bool
{
    $type = strtolower((string) ($row['file_type'] ?? ''));
    if (strpos($type, 'image/') === 0) {
        return true;
    }
    $ext = strtolower(pathinfo((string) ($row['file_path'] ?? ''), PATHINFO_EXTENSION));
    return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
}

/** SQL placeholders for an IN (...) list, e.g. "?,?,?". */
function in_placeholders(array $values): string
{
    return implode(',', array_fill(0, count($values), '?'));
}
