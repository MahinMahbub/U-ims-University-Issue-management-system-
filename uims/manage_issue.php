<?php
require "config/database.php";
require "config/auth.php";
require "config/staff.php";
require "config/mailer.php";
require_role(['Authority', 'Administrator']);
ensure_staff_tables($pdo);

$user = current_user();
$STATUSES = ['Pending Verification', 'More Information', 'Under Review', 'Verified', 'Rejected', 'In Progress', 'Resolved', 'Closed'];
$PRIORITIES = ['Low', 'Medium', 'High', 'Critical'];

$id = (int) ($_GET['id'] ?? $_POST['issue_id'] ?? 0);

$load = $pdo->prepare(
    "SELECT i.*, u.name reporter, c.category_name, l.location_name
     FROM issues i
     JOIN users u ON u.user_id = i.user_id
     JOIN categories c ON c.category_id = i.category_id
     JOIN locations l ON l.location_id = i.location_id
     WHERE i.issue_id = ?"
);
$load->execute([$id]);
$issue = $load->fetch();
if (!$issue) {
    http_response_code(404);
    exit("Issue not found.");
}

/** Redirect back to this page with a message. */
function back_to_issue(int $id, bool $ok, string $message): void
{
    flash_set($ok ? 'success' : 'error', $message);
    header("Location: manage_issue.php?id=" . $id);
    exit;
}

/* ---------------------------------------------------------------------------
 * Actions
 * ------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'delete_issue') {
        $pdo->prepare("DELETE FROM issues WHERE issue_id = ?")->execute([$id]);
        flash_set('success', "Issue #$id was deleted.");
        header("Location: authority.php");
        exit;
    }

    if ($action === 'update_issue') {
        $status = (string) ($_POST['status'] ?? '');
        $priority = (string) ($_POST['priority'] ?? '');
        $department = (int) ($_POST['department_id'] ?? 0);
        $message = trim((string) ($_POST['message'] ?? ''));

        if (!in_array($status, $STATUSES, true) || !in_array($priority, $PRIORITIES, true)) {
            back_to_issue($id, false, "Choose a valid status and priority.");
        }
        if ($department > 0) {
            $check = $pdo->prepare("SELECT 1 FROM departments WHERE department_id = ?");
            $check->execute([$department]);
            if (!$check->fetchColumn()) {
                back_to_issue($id, false, "That department no longer exists.");
            }
        }
        if (text_length($message) > 2000) {
            back_to_issue($id, false, "Keep the update under 2000 characters.");
        }

        // A rejected issue keeps its reason unless a new one is written.
        $reason = null;
        if ($status === 'Rejected') {
            $reason = $message !== '' ? $message : $issue['rejection_reason'];
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE issues SET status=?, priority=?, department_id=?, rejection_reason=? WHERE issue_id=?")
                ->execute([$status, $priority, $department ?: null, $reason, $id]);
            if ($message !== '') {
                $pdo->prepare("INSERT INTO issue_updates(issue_id,authority_id,status,message) VALUES(?,?,?,?)")
                    ->execute([$id, $user['user_id'], $status, $message]);
            }
            $note = null;
            if ($status !== $issue['status']) {
                $note = "Issue #$id status changed to $status.";
            } elseif ($message !== '') {
                $note = "Issue #$id has a new update.";
            }
            if ($note !== null) {
                $pdo->prepare("INSERT INTO notifications(user_id,issue_id,message) VALUES(?,?,?)")
                    ->execute([$issue['user_id'], $id, $note]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            back_to_issue($id, false, "The update could not be saved. Please try again.");
        }
        back_to_issue($id, true, "Issue updated.");
    }

    if ($action === 'assign_staff') {
        if ($issue['status'] === 'Rejected') {
            back_to_issue($id, false, "Rejected issues can't be assigned.");
        }
        $staffId = (int) ($_POST['staff_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT staff_id, name, staff_role, email FROM staff WHERE staff_id = ? AND is_active = 1");
        $stmt->execute([$staffId]);
        $person = $stmt->fetch();
        if (!$person) {
            back_to_issue($id, false, "Choose a staff member to assign.");
        }
        $note = trim((string) ($_POST['note'] ?? ''));
        if (text_length($note) > 1000) {
            back_to_issue($id, false, "Keep the note under 1000 characters.");
        }
        try {
            $pdo->prepare("INSERT INTO issue_assignments(issue_id, staff_id, assigned_by) VALUES (?,?,?)")
                ->execute([$id, $staffId, $user['user_id']]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                back_to_issue($id, false, "{$person['name']} is already assigned to this issue.");
            }
            back_to_issue($id, false, "The assignment could not be saved. Please try again.");
        }

        $done = "Assigned {$person['name']} ({$person['staff_role']}) to this issue.";
        if (empty($_POST['send_email'])) {
            back_to_issue($id, true, $done);
        }
        [$subject, $body] = compose_job_email($issue, $person, $user['name'], $note);
        if (empty($person['email'])) {
            log_email_problem($pdo, $subject, "No email address on file for {$person['name']}.", $id, $staffId, (int) $user['user_id'], $person['name']);
            back_to_issue($id, false, "$done No email was sent because {$person['name']} has no email address. An administrator can add one under Admin, Staff.");
        }
        $result = send_and_log($pdo, $person['email'], $person['name'], $subject, $body, $id, $staffId, (int) $user['user_id'], $user['role_name'] === 'Administrator');
        if ($result['status'] === 'sent') {
            back_to_issue($id, true, "$done The job details were emailed to {$person['email']}.");
        }
        if ($result['status'] === 'not_configured') {
            back_to_issue($id, false, "$done No email was sent because email is not set up yet. Administrators can see this under Admin, Emails.");
        }
        back_to_issue($id, false, "$done The email could not be sent: {$result['error']} Administrators can see this under Admin, Emails.");
    }

    if ($action === 'email_staff') {
        $assignmentId = (int) ($_POST['assignment_id'] ?? 0);
        $message = trim((string) ($_POST['message'] ?? ''));
        $stmt = $pdo->prepare(
            "SELECT s.staff_id, s.name, s.staff_role, s.email
             FROM issue_assignments a JOIN staff s ON s.staff_id = a.staff_id
             WHERE a.assignment_id = ? AND a.issue_id = ?"
        );
        $stmt->execute([$assignmentId, $id]);
        $person = $stmt->fetch();
        if (!$person) {
            back_to_issue($id, false, "That assignment no longer exists.");
        }
        if ($message === '') {
            back_to_issue($id, false, "Write a message to send.");
        }
        if (text_length($message) > 2000) {
            back_to_issue($id, false, "Keep the message under 2000 characters.");
        }
        [$subject, $body] = compose_update_email($issue, $person, $user['name'], $message);
        if (empty($person['email'])) {
            log_email_problem($pdo, $subject, "No email address on file for {$person['name']}.", $id, (int) $person['staff_id'], (int) $user['user_id'], $person['name']);
            back_to_issue($id, false, "{$person['name']} has no email address. An administrator can add one under Admin, Staff.");
        }
        $result = send_and_log($pdo, $person['email'], $person['name'], $subject, $body, $id, (int) $person['staff_id'], (int) $user['user_id'], $user['role_name'] === 'Administrator');
        if ($result['status'] === 'sent') {
            back_to_issue($id, true, "Email sent to {$person['name']} ({$person['email']}).");
        }
        back_to_issue($id, false, $result['status'] === 'not_configured'
            ? "Email is not set up yet, so nothing was sent. Administrators can see this under Admin, Emails."
            : "The email could not be sent: {$result['error']} Administrators can see this under Admin, Emails.");
    }

    if ($action === 'unassign_staff') {
        $assignmentId = (int) ($_POST['assignment_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT s.name FROM issue_assignments a JOIN staff s ON s.staff_id = a.staff_id WHERE a.assignment_id = ? AND a.issue_id = ?");
        $stmt->execute([$assignmentId, $id]);
        $name = $stmt->fetchColumn();
        if ($name === false) {
            back_to_issue($id, false, "That assignment no longer exists.");
        }
        $pdo->prepare("DELETE FROM issue_assignments WHERE assignment_id = ?")->execute([$assignmentId]);
        back_to_issue($id, true, "Removed $name from this issue.");
    }

    back_to_issue($id, false, "Unknown action.");
}

/* ---------------------------------------------------------------------------
 * Page data
 * ------------------------------------------------------------------------- */
$closed = "'" . implode("','", CLOSED_STATUSES) . "'";

$departments = $pdo->query("SELECT * FROM departments ORDER BY department_name")->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM evidence WHERE issue_id = ? ORDER BY evidence_id");
$stmt->execute([$id]);
$evidence = $stmt->fetchAll();

$stmt = $pdo->prepare(
    "SELECT a.assignment_id, a.assigned_at, s.staff_id, s.name, s.staff_role, s.phone, s.email, u.name AS assigned_by_name
     FROM issue_assignments a
     JOIN staff s ON s.staff_id = a.staff_id
     LEFT JOIN users u ON u.user_id = a.assigned_by
     WHERE a.issue_id = ?
     ORDER BY FIELD(s.staff_role, 'Supervisor', 'Attendant'), s.name"
);
$stmt->execute([$id]);
$team = $stmt->fetchAll();

// Active staff not yet on this job, with their current workload.
$stmt = $pdo->prepare(
    "SELECT s.staff_id, s.name, s.staff_role, s.email,
        (SELECT COUNT(*) FROM issue_assignments a2 JOIN issues i2 ON i2.issue_id = a2.issue_id
         WHERE a2.staff_id = s.staff_id AND i2.status NOT IN ($closed)) AS active_jobs
     FROM staff s
     WHERE s.is_active = 1
       AND s.staff_id NOT IN (SELECT staff_id FROM issue_assignments WHERE issue_id = ?)
     ORDER BY FIELD(s.staff_role, 'Supervisor', 'Attendant'), s.name"
);
$stmt->execute([$id]);
$available = [];
foreach ($stmt as $row) {
    $available[$row['staff_role']][] = $row;
}
$staffTotal = (int) $pdo->query("SELECT COUNT(*) FROM staff WHERE is_active = 1")->fetchColumn();

$page_title = "Manage issue #" . $id;
require "partials/header.php";
?>
<div class="container">
  <p class="crumb"><a class="text-link" href="authority.php">Manage issues</a> / #<?= $id ?></p>

  <div class="page-head">
    <h1><?= e($issue['title']) ?></h1>
    <div class="issue-meta">
      <span class="badge <?= status_badge($issue['status']) ?>"><?= e($issue['status']) ?></span>
      <span class="badge"><?= e($issue['priority']) ?></span>
      <span class="badge"><?= e($issue['category_name']) ?></span>
      <span class="badge"><?= e($issue['location_name']) ?></span>
    </div>
  </div>

  <?= flash_render() ?>

  <div class="manage-grid">
    <div class="stack">
      <section class="card">
        <h2 class="card-title">Report</h2>
        <p class="prose"><?= nl2br(e($issue['description'])) ?></p>
        <p class="muted small">Reported by <?= e($issue['reporter']) ?> on <?= e($issue['created_at']) ?></p>
        <?php if ($evidence): ?>
          <div class="chip-row">
            <?php foreach ($evidence as $n => $file): ?>
              <a class="btn secondary sm" target="_blank" rel="noopener" href="<?= e($file['file_path']) ?>">Evidence <?= count($evidence) > 1 ? $n + 1 : '' ?></a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <p class="small"><a class="text-link" href="issue.php?id=<?= $id ?>">View the public page</a></p>
      </section>

      <section class="card">
        <h2 class="card-title">Update status</h2>
        <form method="post" data-once>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update_issue">
          <input type="hidden" name="issue_id" value="<?= $id ?>">
          <div class="row">
            <div>
              <label for="status">Status</label>
              <select id="status" name="status">
                <?php foreach ($STATUSES as $s): ?><option <?= $issue['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div>
              <label for="priority">Priority</label>
              <select id="priority" name="priority">
                <?php foreach ($PRIORITIES as $p): ?><option <?= $issue['priority'] === $p ? 'selected' : '' ?>><?= e($p) ?></option><?php endforeach; ?>
              </select>
            </div>
          </div>
          <label for="department_id">Department</label>
          <select id="department_id" name="department_id">
            <option value="">No department</option>
            <?php foreach ($departments as $d): ?>
              <option value="<?= (int) $d['department_id'] ?>" <?= (int) $issue['department_id'] === (int) $d['department_id'] ? 'selected' : '' ?>><?= e($d['department_name']) ?></option>
            <?php endforeach; ?>
          </select>
          <label for="message">Message to the reporter <span class="optional">(optional)</span></label>
          <textarea id="message" name="message" maxlength="2000" placeholder="Share progress, or explain a rejection."></textarea>
          <div class="form-actions"><button class="btn" type="submit" data-busy="Saving...">Save update</button></div>
        </form>
      </section>
    </div>

    <div class="stack">
      <section class="card">
        <h2 class="card-title">Assigned staff</h2>
        <?php if ($team): ?>
          <ul class="assignees">
            <?php foreach ($team as $a): ?>
              <li>
                <div class="assignee-row">
                  <span class="avatar" aria-hidden="true"><?= e(initial_of($a['name'])) ?></span>
                  <div class="assignee-info">
                    <strong><?= e($a['name']) ?></strong>
                    <span class="muted small"><?= e($a['staff_role']) ?><?= $a['phone'] ? ', ' . e($a['phone']) : '' ?></span>
                    <?php if ($a['email']): ?>
                      <span class="muted small"><?= e($a['email']) ?></span>
                    <?php else: ?>
                      <span class="no-email small">No email on file</span>
                    <?php endif; ?>
                  </div>
                  <form method="post" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="unassign_staff">
                    <input type="hidden" name="issue_id" value="<?= $id ?>">
                    <input type="hidden" name="assignment_id" value="<?= (int) $a['assignment_id'] ?>">
                    <button class="btn secondary sm" type="submit" data-confirm="Remove <?= e($a['name']) ?> from this issue?" aria-label="Remove <?= e($a['name']) ?> from this issue">Remove</button>
                  </form>
                </div>
                <?php if ($a['email']): ?>
                  <details class="assignee-mail">
                    <summary>Send <?= e($a['name']) ?> an email</summary>
                    <form method="post" data-once>
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="email_staff">
                      <input type="hidden" name="issue_id" value="<?= $id ?>">
                      <input type="hidden" name="assignment_id" value="<?= (int) $a['assignment_id'] ?>">
                      <textarea name="message" maxlength="2000" rows="3" placeholder="e.g. Please finish this today. The washroom is closed until it is fixed." aria-label="Message to <?= e($a['name']) ?>" required></textarea>
                      <div class="form-actions"><button class="btn sm" type="submit" data-busy="Sending...">Send email</button></div>
                    </form>
                  </details>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="muted">Nobody is assigned to this issue yet.</p>
        <?php endif; ?>

        <?php if ($issue['status'] === 'Rejected'): ?>
          <p class="muted small">Rejected issues can't be assigned. Change the status first to assign staff.</p>
        <?php elseif ($staffTotal === 0): ?>
          <p class="muted small">
            There is no staff to assign yet.
            <?php if ($user['role_name'] === 'Administrator'): ?>
              <a class="text-link" href="admin.php?tab=staff">Add supervisors and attendants</a>.
            <?php else: ?>
              Ask an administrator to add supervisors and attendants.
            <?php endif; ?>
          </p>
        <?php elseif (!$available): ?>
          <p class="muted small">All active staff are already assigned to this issue.</p>
        <?php else: ?>
          <form method="post" class="assign-form" data-once>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="assign_staff">
            <input type="hidden" name="issue_id" value="<?= $id ?>">
            <label for="staff_id">Assign someone</label>
            <select id="staff_id" name="staff_id" required>
              <option value="">Choose a person</option>
              <?php foreach ($available as $role => $people): ?>
                <optgroup label="<?= e($role) ?>s">
                  <?php foreach ($people as $p):
                    $detail = (int) $p['active_jobs'] . ' active ' . ((int) $p['active_jobs'] === 1 ? 'job' : 'jobs') . ($p['email'] ? '' : ', no email');
                  ?>
                    <option value="<?= (int) $p['staff_id'] ?>"><?= e($p['name']) ?> (<?= e($detail) ?>)</option>
                  <?php endforeach; ?>
                </optgroup>
              <?php endforeach; ?>
            </select>
            <label for="job_note">Note for them <span class="optional">(optional, goes in the email)</span></label>
            <textarea id="job_note" name="note" maxlength="1000" rows="2" placeholder="e.g. Bring a replacement tap. Ask security for the washroom key."></textarea>
            <label class="check"><input type="checkbox" name="send_email" value="1" checked> Email them the job details</label>
            <div class="form-actions"><button class="btn" type="submit" data-busy="Assigning...">Assign</button></div>
          </form>
        <?php endif; ?>
      </section>

      <section class="card danger-zone">
        <h2 class="card-title">Delete issue</h2>
        <p class="muted small">This permanently removes the issue, its evidence records, updates and assignments.</p>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete_issue">
          <input type="hidden" name="issue_id" value="<?= $id ?>">
          <button class="btn danger" type="submit" data-confirm="Delete issue #<?= $id ?> permanently?">Delete issue</button>
        </form>
      </section>
    </div>
  </div>
</div>
<?php require "partials/footer.php"; ?>
