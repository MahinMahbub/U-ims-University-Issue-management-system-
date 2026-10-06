<?php
require "config/database.php";
require "config/auth.php";
require "config/staff.php";
require_role(['Authority', 'Administrator']);
ensure_staff_tables($pdo);

$STATUSES = ['Pending Verification', 'More Information', 'Under Review', 'Verified', 'In Progress', 'Resolved', 'Closed', 'Rejected'];

$status = trim($_GET['status'] ?? '');
if (!in_array($status, $STATUSES, true)) {
  $status = '';
}
$q = trim($_GET['q'] ?? '');

// Counts for the filter chips.
$counts = array_fill_keys($STATUSES, 0);
foreach ($pdo->query("SELECT status, COUNT(*) c FROM issues GROUP BY status") as $row) {
  $counts[$row['status']] = (int) $row['c'];
}
$total = array_sum($counts);

$where = [];
$params = [];
if ($status !== '') {
  $where[] = "i.status = ?";
  $params[] = $status;
}
if ($q !== '') {
  $where[] = "(i.title LIKE ? OR u.name LIKE ? OR i.issue_id = ?)";
  $params[] = "%$q%";
  $params[] = "%$q%";
  $params[] = ctype_digit($q) ? (int) $q : 0;
}
$sql = "SELECT i.issue_id, i.title, i.status, i.priority, i.created_at, c.category_name, l.location_name, u.name reporter
        FROM issues i
        JOIN categories c ON c.category_id=i.category_id
        JOIN locations l ON l.location_id=i.location_id
        JOIN users u ON u.user_id=i.user_id"
  . ($where ? " WHERE " . implode(" AND ", $where) : "")
  . " ORDER BY FIELD(i.status,'Pending Verification','More Information','Under Review','In Progress','Verified','Resolved','Closed','Rejected'), i.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$issues = $stmt->fetchAll();
$assignees = issue_assignees($pdo, array_column($issues, 'issue_id'));

$chipUrl = function (string $s) use ($q): string {
  $query = array_filter(['status' => $s, 'q' => $q], fn($v) => $v !== '');
  return 'authority.php' . ($query ? '?' . http_build_query($query) : '');
};

$page_title = "Manage issues";
require "partials/header.php";
?>
<div class="container">
  <div class="page-head">
    <h1>Manage issues</h1>
    <p class="muted">Review reports, update their status and assign staff to fix them.</p>
  </div>

  <?= flash_render() ?>

  <nav class="chips" aria-label="Filter by status">
    <a class="chip<?= $status === '' ? ' is-active' : '' ?>" href="<?= e($chipUrl('')) ?>">All <span><?= $total ?></span></a>
    <?php foreach ($STATUSES as $s): ?>
      <a class="chip<?= $status === $s ? ' is-active' : '' ?>" href="<?= e($chipUrl($s)) ?>"><?= e($s) ?> <span><?= $counts[$s] ?></span></a>
    <?php endforeach; ?>
  </nav>

  <form class="search-bar" method="get">
    <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search by title, reporter or issue number" aria-label="Search issues">
    <button class="btn secondary" type="submit">Search</button>
  </form>

  <?php if (!$issues): ?>
    <div class="card empty">No issues match. <a class="text-link" href="authority.php">Clear filters</a></div>
  <?php else: ?>
    <table class="queue">
      <thead>
        <tr>
          <th>Issue</th>
          <th>Status</th>
          <th>Priority</th>
          <th>Assigned to</th>
          <th><span class="sr-only">Open</span></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($issues as $i): $team = $assignees[(int) $i['issue_id']] ?? []; ?>
          <tr>
            <td>
              <a class="row-title" href="manage_issue.php?id=<?= (int) $i['issue_id'] ?>">#<?= (int) $i['issue_id'] ?> <?= e($i['title']) ?></a>
              <div class="muted small"><?= e($i['reporter']) ?>, <?= e($i['category_name']) ?>, <?= e($i['location_name']) ?></div>
            </td>
            <td><span class="badge <?= status_badge($i['status']) ?>"><?= e($i['status']) ?></span></td>
            <td><span class="badge"><?= e($i['priority']) ?></span></td>
            <td>
              <?php if ($team): ?>
                <?php foreach ($team as $a): ?>
                  <div class="small"><?= e($a['name']) ?> <span class="muted"><?= e(strtolower($a['staff_role'])) ?></span></div>
                <?php endforeach; ?>
              <?php else: ?>
                <span class="muted">Nobody yet</span>
              <?php endif; ?>
            </td>
            <td class="cell-right"><a class="btn secondary sm" href="manage_issue.php?id=<?= (int) $i['issue_id'] ?>">Manage</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php require "partials/footer.php"; ?>
