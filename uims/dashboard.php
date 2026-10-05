<?php
require "config/database.php";
require "config/auth.php";
require_login();
if ($_SESSION['user']['role_name'] !== 'Student') {
    header("Location: authority.php");
    exit;
}
$user_id = (int) $_SESSION['user']['user_id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_issue') {
    $issue_id = (int) ($_POST['issue_id'] ?? 0);
    if ($issue_id > 0) {
        $stmt = $pdo->prepare("DELETE FROM issues WHERE issue_id=? AND user_id=? AND status NOT IN ('Verified','In Progress','Resolved','Closed')");
        $stmt->execute([$issue_id, $user_id]);
    }
    header("Location: dashboard.php");
    exit;
}
$counts = [];
$stmt = $pdo->prepare("SELECT status, COUNT(*) c FROM issues WHERE user_id=? GROUP BY status");
$stmt->execute([$user_id]);
foreach ($stmt as $r)
    $counts[$r['status']] = $r['c'];
$stmt = $pdo->prepare("SELECT i.*, c.category_name, l.location_name, d.department_name FROM issues i JOIN categories c ON c.category_id=i.category_id JOIN locations l ON l.location_id=i.location_id LEFT JOIN departments d ON d.department_id=i.department_id WHERE i.user_id=? ORDER BY i.created_at DESC");
$stmt->execute([$user_id]);
$issues = $stmt->fetchAll();

// Monthly graph: submitted issues are grouped by created_at; resolved issues
// are grouped by updated_at while their current status is Resolved/Closed.
// This keeps the graph driven by the existing Issues table only.
$chartMonths = [];
$monthCursor = new DateTime('first day of this month');
$monthCursor->modify('-5 months');
for ($m = 0; $m < 6; $m++) {
    $key = $monthCursor->format('Y-m');
    $chartMonths[$key] = [
        'label' => $monthCursor->format('M Y'),
        'submitted' => 0,
        'resolved' => 0,
    ];
    $monthCursor->modify('+1 month');
}

$chartStart = (new DateTime('first day of this month'))->modify('-5 months')->format('Y-m-d 00:00:00');

$chartStmt = $pdo->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_key, COUNT(*) AS total
    FROM issues
    WHERE user_id=? AND created_at >= ?
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')");
$chartStmt->execute([$user_id, $chartStart]);
foreach ($chartStmt as $row) {
    if (isset($chartMonths[$row['month_key']])) {
        $chartMonths[$row['month_key']]['submitted'] = (int) $row['total'];
    }
}

$chartStmt = $pdo->prepare("SELECT DATE_FORMAT(updated_at, '%Y-%m') AS month_key, COUNT(*) AS total
    FROM issues
    WHERE user_id=? AND status IN ('Resolved','Closed') AND updated_at >= ?
    GROUP BY DATE_FORMAT(updated_at, '%Y-%m')");
$chartStmt->execute([$user_id, $chartStart]);
foreach ($chartStmt as $row) {
    if (isset($chartMonths[$row['month_key']])) {
        $chartMonths[$row['month_key']]['resolved'] = (int) $row['total'];
    }
}

$chartMax = 1;
foreach ($chartMonths as $month) {
    $chartMax = max($chartMax, $month['submitted'], $month['resolved']);
}

$page_title = "Student Dashboard";
require "partials/header.php";
?>
<div class="container">
    <h1>Student Dashboard</h1>
    <p class="muted">Welcome, <?= e($_SESSION['user']['name']) ?>.</p>
    <div class="grid">
        <?php foreach (['Pending Verification', 'Under Review', 'Verified', 'In Progress', 'Resolved', 'Rejected'] as $s): ?>
            <div class="card">
                <div class="muted"><?= e($s) ?></div>
                <div class="stat"><?= (int) ($counts[$s] ?? 0) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="actions"><a class="btn" href="submit_issue.php">+ Report New Issue</a></div>

    <div class="card issue-chart-card">
        <div class="issue-chart-header">
            <div>
                <h2>Monthly Issue Overview</h2>
                <p class="muted">Submitted vs. resolved issues over the last 6 months.</p>
            </div>
            <div class="issue-chart-legend" aria-label="Chart legend">
                <span><i class="chart-dot submitted"></i>Submitted</span>
                <span><i class="chart-dot resolved"></i>Resolved</span>
            </div>
        </div>

        <div class="issue-chart" style="--chart-max: <?= (int) $chartMax ?>;">
            <div class="issue-chart-yaxis" aria-hidden="true">
                <?php for ($tick = $chartMax; $tick >= 0; $tick = max(0, $tick - max(1, (int) ceil($chartMax / 4)))): ?>
                    <span><?= $tick ?></span>
                    <?php if ($tick === 0) break; ?>
                <?php endfor; ?>
            </div>
            <div class="issue-chart-plot">
                <div class="issue-chart-gridlines" aria-hidden="true">
                    <span></span><span></span><span></span><span></span><span></span>
                </div>
                <div class="issue-chart-bars">
                    <?php foreach ($chartMonths as $month):
                        $submittedHeight = $chartMax > 0 ? ($month['submitted'] / $chartMax) * 100 : 0;
                        $resolvedHeight = $chartMax > 0 ? ($month['resolved'] / $chartMax) * 100 : 0;
                    ?>
                        <div class="issue-chart-month" title="<?= e($month['label']) ?>">
                            <div class="issue-chart-columns">
                                <div class="chart-bar submitted-bar" style="height: <?= max($submittedHeight, $month['submitted'] > 0 ? 6 : 0) ?>%;" data-value="<?= $month['submitted'] ?>"></div>
                                <div class="chart-bar resolved-bar" style="height: <?= max($resolvedHeight, $month['resolved'] > 0 ? 6 : 0) ?>%;" data-value="<?= $month['resolved'] ?>"></div>
                            </div>
                            <span class="issue-chart-month-label"><?= e($month['label']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="issue-chart-note">Resolved includes issues whose current status is <strong>Resolved</strong> or <strong>Closed</strong>.</div>
    </div>

    <h2>Issue History</h2>
    <?php if (!$issues): ?>
        <div class="card">No issues submitted yet.</div><?php else: ?>
        <table>
            <tr>
                <th>Issue</th>
                <th>Category</th>
                <th>Location</th>
                <th>Priority</th>
                <th>Status</th>
                <th>Date</th>
                <th>Action</th>
            </tr>
            <?php foreach ($issues as $i): ?>
                <tr>
                    <td><a href="issue.php?id=<?= $i['issue_id'] ?>"><strong><?= e($i['title']) ?></strong></a></td>
                    <td><?= e($i['category_name']) ?></td>
                    <td><?= e($i['location_name']) ?></td>
                    <td><span class="badge"><?= e($i['priority']) ?></span></td>
                    <td><span class="badge <?= status_badge($i['status']) ?>"><?= e($i['status']) ?></span>
                    </td>
                    <td><?= e($i['created_at']) ?></td>
                    <td>
                        <div class="issue-actions">
                            <?php $isLocked = in_array($i['status'], ['Verified', 'In Progress', 'Resolved', 'Closed'], true); ?>
                            <?php if ($isLocked): ?>
                                <button class="btn locked" type="button" disabled title="This issue is locked after verification">
                                    &#128274; Locked
                                </button>
                            <?php else: ?>
                                <a class="btn secondary" href="edit_issue.php?id=<?= (int) $i['issue_id'] ?>">Edit</a>
                                <form method="post" class="inline-form">
                                    <input type="hidden" name="action" value="delete_issue">
                                    <input type="hidden" name="issue_id" value="<?= (int) $i['issue_id'] ?>">
                                    <button class="btn danger" type="submit"
                                        data-confirm="Delete this issue permanently?">Delete</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr><?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>
<?php require "partials/footer.php"; ?>