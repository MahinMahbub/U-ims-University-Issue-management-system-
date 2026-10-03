<?php
require "config/database.php";
require "config/auth.php";
$q = trim($_GET['q'] ?? '');
$cat = (int) ($_GET['category_id'] ?? 0);
$status = trim($_GET['status'] ?? '');
$priority = trim($_GET['priority'] ?? '');
$categories = $pdo->query("SELECT * FROM categories ORDER BY category_name")->fetchAll();
$where = ["i.status IN ('Verified','In Progress','Resolved','Closed')"];
$params = [];
if ($q !== '') {
    $where[] = "(i.title LIKE ? OR i.description LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($cat) {
    $where[] = "i.category_id=?";
    $params[] = $cat;
}
if ($status) {
    $where[] = "i.status=?";
    $params[] = $status;
}
if ($priority) {
    $where[] = "i.priority=?";
    $params[] = $priority;
}
$sql = "SELECT i.*,c.category_name,l.location_name,d.department_name FROM issues i JOIN categories c ON c.category_id=i.category_id JOIN locations l ON l.location_id=i.location_id LEFT JOIN departments d ON d.department_id=i.department_id WHERE " . implode(" AND ", $where) . " ORDER BY i.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$issues = $stmt->fetchAll();
$page_title = "Verified Issues";
require "partials/header.php";
?>
<div class="container">
    <h1>Verified Issues</h1>
    <p class="muted">Publicly visible reports that passed authority verification.</p>
    <form class="filters" method="get">
        <input name="q" value="<?= e($q) ?>" placeholder="Search title or description">
        <select name="category_id">
            <option value="">All categories</option><?php foreach ($categories as $c): ?>
                <option value="<?= $c['category_id'] ?>" <?= $cat === $c['category_id'] ? 'selected' : '' ?>>
                    <?= e($c['category_name']) ?></option><?php endforeach; ?>
        </select>
        <select name="status">
            <option value="">All statuses</option>
            <?php foreach (['Verified', 'In Progress', 'Resolved', 'Closed'] as $s): ?>
                <option <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?>
        </select>
        <select name="priority">
            <option value="">All priorities</option><?php foreach (['Low', 'Medium', 'High', 'Critical'] as $p): ?>
                <option <?= $priority === $p ? 'selected' : '' ?>><?= $p ?></option><?php endforeach; ?>
        </select>
        <button class="btn">Filter</button>
    </form>
    <table>
        <tr>
            <th>Issue</th>
            <th>Category</th>
            <th>Location</th>
            <th>Priority</th>
            <th>Status</th>
            <th>Department</th>
        </tr>
        <?php foreach ($issues as $i): ?>
            <tr>
                <td><a href="issue.php?id=<?= $i['issue_id'] ?>"><strong><?= e($i['title']) ?></strong></a><br><span
                        class="muted"><?= e(substr($i['description'], 0, 100)) ?>...</span></td>
                <td><?= e($i['category_name']) ?></td>
                <td><?= e($i['location_name']) ?></td>
                <td><?= e($i['priority']) ?></td>
                <td><?= e($i['status']) ?></td>
                <td><?= e($i['department_name'] ?? 'Not assigned') ?></td>
            </tr><?php endforeach; ?>
    </table>
</div>
<?php require "partials/footer.php"; ?>