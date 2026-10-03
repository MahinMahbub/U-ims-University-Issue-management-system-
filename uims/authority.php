<?php
require "config/database.php";
require "config/auth.php";
require_role(['Authority', 'Administrator']);
$user = current_user();
$isAdmin = $user['role_name'] === 'Administrator';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $id = (int) ($_POST['issue_id'] ?? 0);

  if ($action === 'delete_issue') {
    if ($id > 0) {
      $pdo->prepare("DELETE FROM issues WHERE issue_id=?")->execute([$id]);
    }
    header("Location: authority.php");
    exit;
  }

  $status = $_POST['status'] ?? '';
  $priority = $_POST['priority'];
  $department = (int) ($_POST['department_id'] ?? 0);
  $message = trim($_POST['message'] ?? '');
  $allowed = ['Pending Verification', 'More Information', 'Under Review', 'Verified', 'Rejected', 'In Progress', 'Resolved', 'Closed'];
  if (in_array($status, $allowed, true)) {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("UPDATE issues SET status=?,priority=?,department_id=?,rejection_reason=? WHERE issue_id=?");
    $stmt->execute([$status, $priority, $department ?: null, $status === 'Rejected' ? $message : null, $id]);
    if ($message !== '')
      $pdo->prepare("INSERT INTO issue_updates(issue_id,authority_id,status,message) VALUES(?,?,?,?)")->execute([$id, $user['user_id'], $status, $message]);
    $owner = $pdo->prepare("SELECT user_id,title FROM issues WHERE issue_id=?");
    $owner->execute([$id]);
    $row = $owner->fetch();
    if ($row)
      $pdo->prepare("INSERT INTO notifications(user_id,issue_id,message) VALUES(?,?,?)")->execute([$row['user_id'], $id, "Issue #$id status changed to $status."]);
    $pdo->commit();
  }
  header("Location: authority.php");
  exit;
}
$departments = $pdo->query("SELECT * FROM departments ORDER BY department_name")->fetchAll();
$issues = $pdo->query("SELECT i.*,c.category_name,l.location_name,u.name reporter,d.department_name FROM issues i JOIN categories c ON c.category_id=i.category_id JOIN locations l ON l.location_id=i.location_id JOIN users u ON u.user_id=i.user_id LEFT JOIN departments d ON d.department_id=i.department_id ORDER BY FIELD(i.status,'Pending Verification','More Information','Under Review','In Progress','Verified','Resolved','Closed','Rejected'), i.created_at DESC")->fetchAll();
$page_title = "Authority Dashboard";
require "partials/header.php";
?>
<div class="container">
  <h1>Authority Dashboard</h1>
  <div class="grid">
    <?php foreach (['Pending Verification', 'Under Review', 'In Progress', 'Resolved', 'Rejected'] as $s):
      $n = 0;
      foreach ($issues as $i)
        if ($i['status'] === $s)
          $n++; ?>
      <div class="card">
        <div class="muted"><?= e($s) ?></div>
        <div class="stat"><?= $n ?></div>
      </div>
    <?php endforeach; ?>
  </div>
  <h2>Issue Management</h2>
  <table>
    <tr>
      <th>Issue</th>
      <th>Reporter</th>
      <th>Category / Location</th>
      <th>Current</th>
      <th>Action</th>
    </tr>
    <?php foreach ($issues as $i): ?>
      <tr>
        <td><a href="issue.php?id=<?= $i['issue_id'] ?>"><strong>#<?= $i['issue_id'] ?>   <?= e($i['title']) ?></strong></a>
        </td>
        <td><?= e($i['reporter']) ?></td>
        <td><?= e($i['category_name']) ?><br><?= e($i['location_name']) ?></td>
        <td><?= e($i['status']) ?></td>
        <td>
          <div class="authority-actions">
          <form method="post" class="authority-update-form">
            <input type="hidden" name="issue_id" value="<?= $i['issue_id'] ?>">
            <select
              name="status"><?php foreach (['Pending Verification', 'More Information', 'Under Review', 'Verified', 'Rejected', 'In Progress', 'Resolved', 'Closed'] as $s): ?>
                <option <?= $i['status'] === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?>
            </select>
            <select name="priority"><?php foreach (['Low', 'Medium', 'High', 'Critical'] as $p): ?>
                <option <?= $i['priority'] === $p ? 'selected' : '' ?>><?= $p ?></option><?php endforeach; ?>
            </select>
            <select name="department_id">
              <option value="">No department</option><?php foreach ($departments as $d): ?>
                <option value="<?= $d['department_id'] ?>" <?= $i['department_id'] == $d['department_id'] ? 'selected' : '' ?>>
                  <?= e($d['department_name']) ?>
                </option><?php endforeach; ?>
            </select>
            <input name="message" placeholder="Progress/update message">
            <input type="hidden" name="action" value="update_issue">
            <button class="btn success" type="submit">Save</button>
          </form>
          <form method="post" class="authority-delete-form">
            <input type="hidden" name="action" value="delete_issue">
            <input type="hidden" name="issue_id" value="<?= (int) $i['issue_id'] ?>">
            <button class="btn danger" type="submit"
              data-confirm="Delete issue #<?= (int) $i['issue_id'] ?> permanently?">Delete Issue</button>
          </form>
          </div>
        </td>
      </tr><?php endforeach; ?>
  </table>
</div>
<?php require "partials/footer.php"; ?>