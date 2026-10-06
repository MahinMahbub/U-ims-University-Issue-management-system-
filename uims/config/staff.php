<?php
declare(strict_types=1);

/*
 * Maintenance staff who fix issues, and the assignment of staff to issues.
 *
 *   staff              - supervisors and attendants (they do not log in, but have an email)
 *   issue_assignments  - which staff member is working on which issue
 *   email_log          - every email sent to staff, with its outcome
 */

const STAFF_ROLES = ['Supervisor', 'Attendant'];

// Issues in these statuses no longer count as an "active job" for a staff member.
const CLOSED_STATUSES = ['Resolved', 'Closed', 'Rejected'];

/**
 * Creates the staff tables when they are missing, so existing databases keep
 * working without a manual migration. Returns false if they could not be created.
 */
function ensure_staff_tables(PDO $pdo): bool
{
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS staff (
            staff_id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            staff_role ENUM('Supervisor','Attendant') NOT NULL,
            phone VARCHAR(30) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_staff_role (staff_role, is_active)
        ) ENGINE=InnoDB");

        $pdo->exec("CREATE TABLE IF NOT EXISTS issue_assignments (
            assignment_id INT AUTO_INCREMENT PRIMARY KEY,
            issue_id INT NOT NULL,
            staff_id INT NOT NULL,
            assigned_by INT NULL,
            assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_issue_staff (issue_id, staff_id),
            FOREIGN KEY (issue_id) REFERENCES issues(issue_id) ON DELETE CASCADE,
            FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE RESTRICT,
            FOREIGN KEY (assigned_by) REFERENCES users(user_id) ON DELETE SET NULL
        ) ENGINE=InnoDB");

        // Databases created before email support get the new column here.
        $hasEmail = $pdo->query("SHOW COLUMNS FROM staff LIKE 'email'")->fetch();
        if (!$hasEmail) {
            $pdo->exec("ALTER TABLE staff ADD COLUMN email VARCHAR(150) NULL AFTER phone");
        }

        // Every email the system sends (or fails to send), shown to administrators.
        $pdo->exec("CREATE TABLE IF NOT EXISTS email_log (
            email_id INT AUTO_INCREMENT PRIMARY KEY,
            issue_id INT NULL,
            staff_id INT NULL,
            sent_by INT NULL,
            to_email VARCHAR(150) NOT NULL,
            to_name VARCHAR(120) NULL,
            subject VARCHAR(200) NOT NULL,
            body TEXT NOT NULL,
            status ENUM('sent','failed','not_configured') NOT NULL,
            error VARCHAR(500) NULL,
            admin_seen TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_email_seen (admin_seen, created_at),
            FOREIGN KEY (issue_id) REFERENCES issues(issue_id) ON DELETE SET NULL,
            FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE SET NULL,
            FOREIGN KEY (sent_by) REFERENCES users(user_id) ON DELETE SET NULL
        ) ENGINE=InnoDB");
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Staff assigned to each of the given issues.
 * Returns [issue_id => [ ['assignment_id'=>..,'staff_id'=>..,'name'=>..,'staff_role'=>..], ... ]]
 * with supervisors listed before attendants.
 */
function issue_assignees(PDO $pdo, array $issueIds): array
{
    $issueIds = array_values(array_unique(array_map('intval', $issueIds)));
    if (!$issueIds) {
        return [];
    }
    $in = implode(',', array_fill(0, count($issueIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT a.assignment_id, a.issue_id, s.staff_id, s.name, s.staff_role
         FROM issue_assignments a
         JOIN staff s ON s.staff_id = a.staff_id
         WHERE a.issue_id IN ($in)
         ORDER BY FIELD(s.staff_role, 'Supervisor', 'Attendant'), s.name"
    );
    $stmt->execute($issueIds);
    $map = [];
    foreach ($stmt as $row) {
        $map[(int) $row['issue_id']][] = $row;
    }
    return $map;
}

/** "Mr. A (Supervisor), Mr. B (Attendant)" for plain-text display. */
function assignees_label(array $assignees): string
{
    $parts = [];
    foreach ($assignees as $a) {
        $parts[] = $a['name'] . ' (' . $a['staff_role'] . ')';
    }
    return implode(', ', $parts);
}

/** Cleans a single-line text field: trims and collapses runs of whitespace. */
function clean_line(?string $value): string
{
    return trim((string) preg_replace('/\s+/u', ' ', (string) $value));
}

/** First letter of a name, for the round avatar (works without mbstring). */
function initial_of(string $name): string
{
    if (!preg_match('/./su', trim($name), $m)) {
        return '?';
    }
    return function_exists('mb_strtoupper') ? mb_strtoupper($m[0], 'UTF-8') : strtoupper($m[0]);
}
