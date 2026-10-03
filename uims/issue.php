<?php
require "config/database.php";
require "config/auth.php";
$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT i.*,u.name reporter,c.category_name,l.location_name,d.department_name FROM issues i JOIN users u ON u.user_id=i.user_id JOIN categories c ON c.category_id=i.category_id JOIN locations l ON l.location_id=i.location_id LEFT JOIN departments d ON d.department_id=i.department_id WHERE i.issue_id=?");
$stmt->execute([$id]);
$issue = $stmt->fetch();
if (!$issue) {
    http_response_code(404);
    exit("Issue not found.");
}
$user = current_user();
if ($issue['status'] === 'Pending Verification' && (!$user || ($user['user_id'] != $issue['user_id'] && !in_array($user['role_name'], ['Authority', 'Administrator'], true)))) {
    http_response_code(403);
    exit("This issue is not public yet.");
}
$updates = $pdo->prepare("SELECT iu.*,u.name authority_name FROM issue_updates iu JOIN users u ON u.user_id=iu.authority_id WHERE iu.issue_id=? ORDER BY iu.created_at DESC");
$updates->execute([$id]);
$ev = $pdo->prepare("SELECT * FROM evidence WHERE issue_id=?");
$ev->execute([$id]);
$evidence = $ev->fetchAll();
$page_title = "Issue #" . $id;
require "partials/header.php";
?>
<div class="container">
    <div class="card">
        <div class="issue-title"><?= e($issue['title']) ?></div>
        <div class="issue-meta"><span class="badge"><?= e($issue['category_name']) ?></span><span
                class="badge"><?= e($issue['priority']) ?></span><span class="badge blue"><?= e($issue['status']) ?></span>
        </div>
        <p><?= nl2br(e($issue['description'])) ?></p>
        <p class="muted">Reported by <?= e($issue['reporter']) ?> · <?= e($issue['created_at']) ?><br>Location:
            <?= e($issue['location_name']) ?> · Department: <?= e($issue['department_name'] ?? 'Not assigned') ?></p>
        <?php if ($evidence): ?>
            <h3>Evidence</h3><?php foreach ($evidence as $file): ?>
                <p><a class="btn secondary" target="_blank" href="<?= e($file['file_path']) ?>">View evidence</a></p>
                <?php endforeach; ?><?php endif; ?>
    </div>
    <h2>Resolution Updates</h2>
    <?php foreach ($updates as $u): ?>
        <div class="card" style="margin-bottom:12px"><strong><?= e($u['status'] ?? 'Update') ?></strong>
            <p><?= nl2br(e($u['message'])) ?></p><small class="muted">By <?= e($u['authority_name']) ?> ·
                <?= e($u['created_at']) ?></small>
        </div><?php endforeach; ?>
</div>
<?php require "partials/footer.php"; ?>