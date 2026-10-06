<?php
require "config/database.php";
require "config/auth.php";
require_login();
if ($_SESSION['user']['role_name'] !== 'Student') {
  header("Location: authority.php");
  exit;
}
$categories = $pdo->query("SELECT * FROM categories ORDER BY category_name")->fetchAll();
$locations = $pdo->query("SELECT * FROM locations ORDER BY location_name")->fetchAll();
$error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $title = trim($_POST['title'] ?? '');
  $desc = trim($_POST['description'] ?? '');
  $cat = (int) ($_POST['category_id'] ?? 0);
  $loc = (int) ($_POST['location_id'] ?? 0);
  $priority = $_POST['priority'] ?? 'Medium';
  $observed = $_POST['observed_at'] ?? null;
  if (!$title || !$desc || !$cat || !$loc) {
    $error = "Please complete all required fields.";
  } else {
    $pdo->beginTransaction();
    try {
      $stmt = $pdo->prepare("INSERT INTO issues(user_id,category_id,location_id,title,description,observed_at,priority) VALUES(?,?,?,?,?,?,?)");
      $stmt->execute([$_SESSION['user']['user_id'], $cat, $loc, $title, $desc, $observed ?: null, $priority]);
      $issue_id = (int) $pdo->lastInsertId();
      if (isset($_FILES['evidence']) && $_FILES['evidence']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        if (!in_array($_FILES['evidence']['type'], $allowed, true))
          throw new RuntimeException("Only JPG, PNG, WEBP or PDF evidence is allowed.");
        if ($_FILES['evidence']['size'] > 5 * 1024 * 1024)
          throw new RuntimeException("Evidence must be 5MB or smaller.");
        $ext = strtolower(pathinfo($_FILES['evidence']['name'], PATHINFO_EXTENSION));
        $name = uniqid('evidence_', true) . '.' . $ext;
        $target = __DIR__ . '/uploads/' . $name;
        if (!move_uploaded_file($_FILES['evidence']['tmp_name'], $target))
          throw new RuntimeException("Could not save evidence.");
        $pdo->prepare("INSERT INTO evidence(issue_id,file_path,file_type) VALUES(?,?,?)")->execute([$issue_id, 'uploads/' . $name, $_FILES['evidence']['type']]);
      }
      $pdo->commit();
      header("Location: issue.php?id=" . $issue_id);
      exit;
    } catch (Throwable $e) {
      $pdo->rollBack();
      $error = $e->getMessage();
    }
  }
}
$page_title = "Report Issue";
require "partials/header.php";
?>
<div class="form-card">
  <h2>Report a University Issue</h2>
  <?php if ($error): ?>
    <div class="alert error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data">
    <label>Issue Title *</label><input name="title" placeholder="e.g. Broken Air Conditioner in Room 305" required>
    <label>Description *</label><textarea name="description" placeholder="Describe the problem clearly..."
      required></textarea>
    <div class="row">
      <div><label>Category *</label><select name="category_id" required>
          <option value="">Select category</option><?php foreach ($categories as $c): ?>
            <option value="<?= $c['category_id'] ?>"><?= e($c['category_name']) ?></option><?php endforeach; ?>
        </select></div>
      <div><label>Location *</label><select name="location_id" required>
          <option value="">Select location</option><?php foreach ($locations as $l): ?>
            <option value="<?= $l['location_id'] ?>"><?= e($l['location_name']) ?></option><?php endforeach; ?>
        </select></div>
    </div>
    <div class="row">
      <div><label>Date/time observed</label><input type="datetime-local" name="observed_at"></div>
      <div><label>Priority suggestion</label><select name="priority">
          <option>Low</option>
          <option selected>Medium</option>
          <option>High</option>
          <option>Critical</option>
        </select></div>
    </div>
    <label>Supporting evidence</label><input id="evidence" type="file" name="evidence"
      accept=".jpg,.jpeg,.png,.webp,.pdf"><small id="file-name" class="muted">No file selected — max 5MB</small>
    <br><br><button class="btn" type="submit">Submit Issue</button>
  </form>
</div>
<?php require "partials/footer.php"; ?>