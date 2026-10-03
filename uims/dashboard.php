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
        $stmt = $pdo->prepare("DELETE FROM issues WHERE issue_id=? AND user_id=?");
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
                    <td><span
                            class="badge <?= in_array($i['status'], ['Resolved', 'Verified']) ? 'green' : ($i['status'] === 'Rejected' ? 'red' : 'orange') ?>"><?= e($i['status']) ?></span>
                    </td>
                    <td><?= e($i['created_at']) ?></td>
                    <td>
                        <div class="issue-actions">
                            <a class="btn secondary" href="edit_issue.php?id=<?= (int) $i['issue_id'] ?>">Edit</a>
                            <form method="post" class="inline-form">
                            <input type="hidden" name="action" value="delete_issue">
                            <input type="hidden" name="issue_id" value="<?= (int) $i['issue_id'] ?>">
                            <button class="btn danger" type="submit"
                                data-confirm="Delete this issue permanently?">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr><?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>
<?php require "partials/footer.php"; ?>