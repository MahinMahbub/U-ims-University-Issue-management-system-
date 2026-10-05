<?php
require "config/database.php";
require "config/auth.php";
require "config/staff.php";
require_role(['Administrator']);
ensure_staff_tables($pdo);


$LOOKUPS = [
    'categories' => [
        'table' => 'categories', 'id' => 'category_id', 'name' => 'category_name', 'max' => 80,
        'singular' => 'category', 'title' => 'Categories',
        'hint' => 'Students choose one of these when they report an issue.',
        'usage' => [['issues', 'category_id', 'issue', 'issues']],
    ],
    'departments' => [
        'table' => 'departments', 'id' => 'department_id', 'name' => 'department_name', 'max' => 100,
        'singular' => 'department', 'title' => 'Departments',
        'hint' => 'Shown on the registration form and when an issue is routed to a department.',
        'usage' => [['issues', 'department_id', 'issue', 'issues'], ['users', 'department_id', 'user', 'users']],
    ],
    'locations' => [
        'table' => 'locations', 'id' => 'location_id', 'name' => 'location_name', 'max' => 150,
        'singular' => 'location', 'title' => 'Locations',
        'hint' => 'Places on campus where issues can happen.',
        'usage' => [['issues', 'location_id', 'issue', 'issues']],
    ],
];

$TABS = ['overview' => 'Overview', 'categories' => 'Categories', 'departments' => 'Departments', 'locations' => 'Locations', 'staff' => 'Staff'];

function lookup_usage(PDO $pdo, array $cfg, int $id): array
{
    $out = [];
    foreach ($cfg['usage'] as [$table, $column, $one, $many]) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE $column = ?");
        $stmt->execute([$id]);
        $n = (int) $stmt->fetchColumn();
        if ($n > 0) {
            $out[] = $n . ' ' . ($n === 1 ? $one : $many);
        }
    }
    return $out;
}

/** Add, rename or remove one row of a lookup list. Returns [success, message]. */
function handle_lookup(PDO $pdo, array $cfg, string $action, int $id, string $name): array
{
    $t = $cfg['table'];
    $idc = $cfg['id'];
    $nc = $cfg['name'];
    $label = $cfg['singular'];

    try {
        if ($action === 'add' || $action === 'rename') {
            if ($name === '') {
                return [false, "Enter a name first."];
            }
            if (text_length($name) > $cfg['max']) {
                return [false, ucfirst($label) . " names can be up to {$cfg['max']} characters."];
            }
            $stmt = $pdo->prepare("SELECT $idc FROM $t WHERE $nc = ? AND $idc <> ? LIMIT 1");
            $stmt->execute([$name, $action === 'rename' ? $id : 0]);
            if ($stmt->fetchColumn()) {
                return [false, "A $label named \u{201C}$name\u{201D} already exists."];
            }
        }

        if ($action === 'add') {
            $pdo->prepare("INSERT INTO $t ($nc) VALUES (?)")->execute([$name]);
            return [true, "Added \u{201C}$name\u{201D}."];
        }

        $stmt = $pdo->prepare("SELECT $nc FROM $t WHERE $idc = ?");
        $stmt->execute([$id]);
        $current = $stmt->fetchColumn();
        if ($current === false) {
            return [false, "That $label no longer exists."];
        }

        if ($action === 'rename') {
            if ($current === $name) {
                return [true, "Nothing to change."];
            }
            $pdo->prepare("UPDATE $t SET $nc = ? WHERE $idc = ?")->execute([$name, $id]);
            return [true, "Renamed \u{201C}$current\u{201D} to \u{201C}$name\u{201D}."];
        }

        if ($action === 'delete') {
            $usage = lookup_usage($pdo, $cfg, $id);
            if ($usage) {
                return [false, "\u{201C}$current\u{201D} is used by " . implode(' and ', $usage) . ", so it can't be removed. Rename it instead."];
            }
            $pdo->prepare("DELETE FROM $t WHERE $idc = ?")->execute([$id]);
            return [true, "Removed \u{201C}$current\u{201D}."];
        }
    } catch (PDOException $e) {
        return [false, "That change could not be saved. Please try again."];
    }
    return [false, "Unknown action."];
}

/** Add, edit, switch on/off or remove a staff member. Returns [success, message]. */
function handle_staff(PDO $pdo, string $action, array $in): array
{
    $id = (int) ($in['id'] ?? 0);
    $name = clean_line($in['name'] ?? '');
    $role = (string) ($in['staff_role'] ?? '');
    $phone = clean_line($in['phone'] ?? '');

    try {
        if ($action === 'add' || $action === 'edit') {
            if ($name === '') {
                return [false, "Enter the staff member's name."];
            }
            if (text_length($name) > 120) {
                return [false, "Names can be up to 120 characters."];
            }
            if (!in_array($role, STAFF_ROLES, true)) {
                return [false, "Choose Supervisor or Attendant."];
            }
            if ($phone !== '' && !preg_match('/^[0-9+\-() ]{5,30}$/', $phone)) {
                return [false, "Phone numbers can only use digits, spaces, + - and brackets."];
            }
        }

        if ($action === 'add') {
            $pdo->prepare("INSERT INTO staff(name, staff_role, phone) VALUES (?,?,?)")
                ->execute([$name, $role, $phone !== '' ? $phone : null]);
            return [true, "Added $name as $role."];
        }

        $stmt = $pdo->prepare("SELECT name, is_active FROM staff WHERE staff_id = ?");
        $stmt->execute([$id]);
        $current = $stmt->fetch();
        if (!$current) {
            return [false, "That staff member no longer exists."];
        }

        if ($action === 'edit') {
            $pdo->prepare("UPDATE staff SET name = ?, staff_role = ?, phone = ? WHERE staff_id = ?")
                ->execute([$name, $role, $phone !== '' ? $phone : null, $id]);
            return [true, "Saved changes for $name."];
        }

        if ($action === 'toggle') {
            $next = (int) $current['is_active'] === 1 ? 0 : 1;
            $pdo->prepare("UPDATE staff SET is_active = ? WHERE staff_id = ?")->execute([$next, $id]);
            return [true, $next ? "{$current['name']} can be assigned to jobs again." : "{$current['name']} will no longer appear when assigning jobs."];
        }

        if ($action === 'delete') {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM issue_assignments WHERE staff_id = ?");
            $stmt->execute([$id]);
            if ((int) $stmt->fetchColumn() > 0) {
                return [false, "{$current['name']} has job history, so they can't be removed. Deactivate them instead."];
            }
            $pdo->prepare("DELETE FROM staff WHERE staff_id = ?")->execute([$id]);
            return [true, "Removed {$current['name']}."];
        }
    } catch (PDOException $e) {
        return [false, "That change could not be saved. Please try again."];
    }
    return [false, "Unknown action."];
}

/* ---------------------------------------------------------------------------
 * Form handling (post, then redirect so a refresh never repeats the action)
 * ------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $kind = (string) ($_POST['kind'] ?? '');
    $back = 'overview';

    if (isset($LOOKUPS[$kind])) {
        $back = $kind;
        [$ok, $message] = handle_lookup($pdo, $LOOKUPS[$kind], $action, (int) ($_POST['id'] ?? 0), clean_line($_POST['name'] ?? ''));
        flash_set($ok ? 'success' : 'error', $message);
    } elseif ($kind === 'staff') {
        $back = 'staff';
        [$ok, $message] = handle_staff($pdo, $action, $_POST);
        flash_set($ok ? 'success' : 'error', $message);
    }
    header("Location: admin.php?tab=" . $back);
    exit;
}

$tab = (string) ($_GET['tab'] ?? 'overview');
if (!isset($TABS[$tab])) {
    $tab = 'overview';
}

/* ---------------------------------------------------------------------------
 * Data for the selected tab
 * ------------------------------------------------------------------------- */
$closed = "'" . implode("','", CLOSED_STATUSES) . "'";

if ($tab === 'overview') {
    $stats = [
        'Total issues' => (int) $pdo->query("SELECT COUNT(*) FROM issues")->fetchColumn(),
        'Resolved' => (int) $pdo->query("SELECT COUNT(*) FROM issues WHERE status='Resolved'")->fetchColumn(),
        'Unresolved' => (int) $pdo->query("SELECT COUNT(*) FROM issues WHERE status NOT IN ($closed)")->fetchColumn(),
        'Critical' => (int) $pdo->query("SELECT COUNT(*) FROM issues WHERE priority='Critical'")->fetchColumn(),
        'Need a job assigned' => (int) $pdo->query("SELECT COUNT(*) FROM issues i WHERE i.status IN ('Verified','In Progress') AND NOT EXISTS (SELECT 1 FROM issue_assignments a WHERE a.issue_id=i.issue_id)")->fetchColumn(),
    ];
    $cats = $pdo->query("SELECT c.category_name, COUNT(i.issue_id) total FROM categories c LEFT JOIN issues i ON i.category_id=c.category_id GROUP BY c.category_id, c.category_name ORDER BY total DESC, c.category_name")->fetchAll();
    $catMax = max(1, (int) max(array_merge([0], array_column($cats, 'total'))));
    $activeJobs = $pdo->query(
        "SELECT i.issue_id, i.title, i.status, s.name, s.staff_role
         FROM issue_assignments a
         JOIN issues i ON i.issue_id = a.issue_id
         JOIN staff s ON s.staff_id = a.staff_id
         WHERE i.status NOT IN ($closed)
         ORDER BY a.assigned_at DESC LIMIT 6"
    )->fetchAll();
}

if (isset($LOOKUPS[$tab])) {
    $cfg = $LOOKUPS[$tab];
    $subqueries = [];
    foreach ($cfg['usage'] as $i => [$table, $column]) {
        $subqueries[] = "(SELECT COUNT(*) FROM $table WHERE $table.$column = t.{$cfg['id']}) AS use_$i";
    }
    $rows = $pdo->query("SELECT t.{$cfg['id']} AS id, t.{$cfg['name']} AS name, " . implode(', ', $subqueries) . " FROM {$cfg['table']} t ORDER BY t.{$cfg['name']}")->fetchAll();
}

if ($tab === 'staff') {
    $staff = $pdo->query(
        "SELECT s.*,
            (SELECT COUNT(*) FROM issue_assignments a JOIN issues i ON i.issue_id = a.issue_id WHERE a.staff_id = s.staff_id AND i.status NOT IN ($closed)) AS active_jobs,
            (SELECT COUNT(*) FROM issue_assignments a WHERE a.staff_id = s.staff_id) AS total_jobs
         FROM staff s ORDER BY s.is_active DESC, FIELD(s.staff_role, 'Supervisor', 'Attendant'), s.name"
    )->fetchAll();
}

$page_title = "Admin";
require "partials/header.php";
?>
<div class="container">
  <div class="page-head">
    <h1>Admin</h1>
    <p class="muted">Manage the lists students choose from, and the staff who fix issues.</p>
  </div>

  <nav class="tabs" aria-label="Admin sections">
    <?php foreach ($TABS as $key => $label): ?>
      <a href="admin.php?tab=<?= $key ?>"<?= $tab === $key ? ' class="is-active" aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>

  <?= flash_render() ?>

  <?php if ($tab === 'overview'): ?>
    <div class="stat-row">
      <?php foreach ($stats as $label => $value): ?>
        <div class="stat-tile">
          <div class="stat"><?= $value ?></div>
          <div class="muted"><?= e($label) ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="two-col">
      <section class="card">
        <h2 class="card-title">Issues by category</h2>
        <?php if (!$cats): ?>
          <p class="muted">No categories yet.</p>
        <?php else: ?>
          <?php foreach ($cats as $c): ?>
            <div class="bar-row">
              <span class="bar-name"><?= e($c['category_name']) ?></span>
              <div class="bar"><div class="bar-fill" style="--width: <?= round(($c['total'] / $catMax) * 100, 1) ?>%"></div></div>
              <span class="bar-count"><?= (int) $c['total'] ?></span>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </section>

      <section class="card">
        <h2 class="card-title">Open jobs</h2>
        <?php if (!$activeJobs): ?>
          <p class="muted">No jobs are assigned right now. Assign staff from <a class="text-link" href="authority.php">Manage issues</a>.</p>
        <?php else: ?>
          <ul class="plain-list">
            <?php foreach ($activeJobs as $j): ?>
              <li>
                <a href="manage_issue.php?id=<?= (int) $j['issue_id'] ?>"><strong>#<?= (int) $j['issue_id'] ?> <?= e($j['title']) ?></strong></a>
                <span class="muted"><?= e($j['name']) ?>, <?= e(strtolower($j['staff_role'])) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>
    </div>

  <?php elseif (isset($LOOKUPS[$tab])): ?>
    <div class="panel-head">
      <div>
        <h2><?= e($cfg['title']) ?></h2>
        <p class="muted"><?= e($cfg['hint']) ?></p>
      </div>
    </div>

    <form method="post" class="inline-add" data-once>
      <?= csrf_field() ?>
      <input type="hidden" name="kind" value="<?= e($tab) ?>">
      <input type="hidden" name="action" value="add">
      <input name="name" maxlength="<?= (int) $cfg['max'] ?>" placeholder="New <?= e($cfg['singular']) ?>" aria-label="New <?= e($cfg['singular']) ?> name" required>
      <button class="btn" type="submit">Add <?= e($cfg['singular']) ?></button>
    </form>

    <?php if (!$rows): ?>
      <div class="card empty">No <?= e(strtolower($cfg['title'])) ?> yet. Add the first one above.</div>
    <?php else: ?>
      <table class="lookup-table">
        <thead>
          <tr><th>Name</th><th>Used by</th><th><span class="sr-only">Remove</span></th></tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r):
            $usage = [];
            foreach ($cfg['usage'] as $i => [$table, $column, $one, $many]) {
                $n = (int) $r["use_$i"];
                if ($n > 0) $usage[] = $n . ' ' . ($n === 1 ? $one : $many);
            }
          ?>
            <tr>
              <td>
                <form method="post" class="rename-form" data-rename>
                  <?= csrf_field() ?>
                  <input type="hidden" name="kind" value="<?= e($tab) ?>">
                  <input type="hidden" name="action" value="rename">
                  <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                  <input name="name" value="<?= e($r['name']) ?>" data-original="<?= e($r['name']) ?>" maxlength="<?= (int) $cfg['max'] ?>" aria-label="Rename <?= e($r['name']) ?>" required>
                  <button class="btn secondary sm" type="submit" disabled>Save</button>
                </form>
              </td>
              <td class="muted"><?= $usage ? e(implode(', ', $usage)) : 'Not used yet' ?></td>
              <td class="cell-right">
                <form method="post" class="inline-form">
                  <?= csrf_field() ?>
                  <input type="hidden" name="kind" value="<?= e($tab) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                  <?php if ($usage): ?>
                    <button class="btn danger sm" type="button" disabled title="In use, so it can't be removed. Rename it instead.">Remove</button>
                  <?php else: ?>
                    <button class="btn danger sm" type="submit" data-confirm="Remove &quot;<?= e($r['name']) ?>&quot;?">Remove</button>
                  <?php endif; ?>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

  <?php elseif ($tab === 'staff'): ?>
    <div class="panel-head">
      <div>
        <h2>Staff</h2>
        <p class="muted">Supervisors and attendants who fix issues. Assign them to a job from the issue's page under Manage issues.</p>
      </div>
    </div>

    <form method="post" class="card staff-add" data-once>
      <?= csrf_field() ?>
      <input type="hidden" name="kind" value="staff">
      <input type="hidden" name="action" value="add">
      <div>
        <label for="staff-name">Name</label>
        <input id="staff-name" name="name" maxlength="120" placeholder="e.g. Mr. A" required>
      </div>
      <div>
        <label for="staff-role">Role</label>
        <select id="staff-role" name="staff_role" required>
          <?php foreach (STAFF_ROLES as $role): ?><option><?= e($role) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="staff-phone">Phone <span class="optional">(optional)</span></label>
        <input id="staff-phone" name="phone" maxlength="30" inputmode="tel">
      </div>
      <button class="btn" type="submit">Add staff</button>
    </form>

    <?php if (!$staff): ?>
      <div class="card empty">No staff yet. Add a supervisor or attendant above, then assign them to issues.</div>
    <?php else: ?>
      <div class="staff-grid">
        <?php foreach ($staff as $s):
          $active = (int) $s['is_active'] === 1;
          $hasHistory = (int) $s['total_jobs'] > 0;
        ?>
          <article class="staff-card<?= $active ? '' : ' is-inactive' ?>">
            <div class="staff-head">
              <span class="avatar" aria-hidden="true"><?= e(initial_of($s['name'])) ?></span>
              <div class="staff-id">
                <strong><?= e($s['name']) ?></strong>
                <span class="muted"><?= $s['phone'] ? e($s['phone']) : 'No phone number' ?></span>
              </div>
              <span class="badge <?= $s['staff_role'] === 'Supervisor' ? 'blue' : '' ?>"><?= e($s['staff_role']) ?></span>
            </div>

            <p class="staff-jobs">
              <strong><?= (int) $s['active_jobs'] ?></strong> active <?= (int) $s['active_jobs'] === 1 ? 'job' : 'jobs' ?>
              <span class="muted">of <?= (int) $s['total_jobs'] ?> assigned<?= $active ? '' : ', inactive' ?></span>
            </p>

            <details class="staff-edit">
              <summary>Edit details</summary>
              <form method="post" data-once>
                <?= csrf_field() ?>
                <input type="hidden" name="kind" value="staff">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" value="<?= (int) $s['staff_id'] ?>">
                <label>Name</label>
                <input name="name" value="<?= e($s['name']) ?>" maxlength="120" required>
                <label>Role</label>
                <select name="staff_role">
                  <?php foreach (STAFF_ROLES as $role): ?><option <?= $s['staff_role'] === $role ? 'selected' : '' ?>><?= e($role) ?></option><?php endforeach; ?>
                </select>
                <label>Phone</label>
                <input name="phone" value="<?= e((string) $s['phone']) ?>" maxlength="30" inputmode="tel">
                <div class="form-actions"><button class="btn sm" type="submit">Save changes</button></div>
              </form>
            </details>

            <div class="staff-actions">
              <form method="post" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="kind" value="staff">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= (int) $s['staff_id'] ?>">
                <button class="btn secondary sm" type="submit"><?= $active ? 'Deactivate' : 'Activate' ?></button>
              </form>
              <form method="post" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="kind" value="staff">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $s['staff_id'] ?>">
                <?php if ($hasHistory): ?>
                  <button class="btn danger sm" type="button" disabled title="Has job history. Deactivate instead.">Remove</button>
                <?php else: ?>
                  <button class="btn danger sm" type="submit" data-confirm="Remove <?= e($s['name']) ?>?">Remove</button>
                <?php endif; ?>
              </form>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php require "partials/footer.php"; ?>
