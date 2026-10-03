<?php
require "config/database.php";
require "config/auth.php";
require_role(['Administrator']);

$error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $type = $_POST['type'] ?? '';

  // Existing add features are unchanged.
  if ($type === 'category') {
    $v = trim($_POST['name'] ?? '');
    if ($v)
      $pdo->prepare("INSERT IGNORE INTO categories(category_name) VALUES(?)")->execute([$v]);
  }
  if ($type === 'department') {
    $v = trim($_POST['name'] ?? '');
    if ($v)
      $pdo->prepare("INSERT IGNORE INTO departments(department_name) VALUES(?)")->execute([$v]);
  }
  if ($type === 'location') {
    $v = trim($_POST['name'] ?? '');
    if ($v)
      $pdo->prepare("INSERT IGNORE INTO locations(location_name) VALUES(?)")->execute([$v]);
  }

  if ($type === 'delete_category') {
    $category_id = (int) ($_POST['category_id'] ?? 0);
    if ($category_id > 0) {
      try {
        $check = $pdo->prepare("SELECT COUNT(*) FROM issues WHERE category_id=?");
        $check->execute([$category_id]);
        if ((int) $check->fetchColumn() > 0) {
          $error = "This category cannot be removed because it is already used by one or more issues.";
        } else {
          $pdo->prepare("DELETE FROM categories WHERE category_id=?")->execute([$category_id]);
        }
      } catch (PDOException $e) {
        $error = "Unable to remove this category.";
      }
    }
  }

  if ($error === "") {
    header("Location: admin.php");
    exit;
  }
}

$stats = [
  'Total Issues' => $pdo->query("SELECT COUNT(*) FROM issues")->fetchColumn(),
  'Resolved' => $pdo->query("SELECT COUNT(*) FROM issues WHERE status='Resolved'")->fetchColumn(),
  'Unresolved' => $pdo->query("SELECT COUNT(*) FROM issues WHERE status NOT IN ('Resolved','Closed','Rejected')")->fetchColumn(),
  'Critical' => $pdo->query("SELECT COUNT(*) FROM issues WHERE priority='Critical'")->fetchColumn()
];
$cats = $pdo->query("SELECT c.category_id,c.category_name,COUNT(i.issue_id) total FROM categories c LEFT JOIN issues i ON i.category_id=c.category_id GROUP BY c.category_id ORDER BY total DESC, c.category_name")->fetchAll();
$page_title = "Administrator";
require "partials/header.php";
?>
<div class="container">
  <h1>Administrator Panel</h1>

  <?php if ($error): ?>
    <div class="alert error"><?= e($error) ?></div>
  <?php endif; ?>

  <div class="grid"><?php foreach ($stats as $k => $v): ?>
      <div class="card">
        <div class="muted"><?= e($k) ?></div>
        <div class="stat"><?= $v ?></div>
      </div><?php endforeach; ?>
  </div>

  <div class="grid" style="margin-top:20px">
    <div class="card">
      <h3>Add Category</h3>
      <form method="post"><input type="hidden" name="type" value="category"><input name="name" required><br><br><button
          class="btn">Add</button></form>
    </div>
    <div class="card">
      <h3>Add Department</h3>
      <form method="post"><input type="hidden" name="type" value="department"><input name="name"
          required><br><br><button class="btn">Add</button></form>
    </div>
    <div class="card">
      <h3>Add Location</h3>
      <form method="post"><input type="hidden" name="type" value="location"><input name="name" required><br><br><button
          class="btn">Add</button></form>
    </div>
  </div>

  <h2>Categories</h2>
  <table>
    <tr>
      <th>Category</th>
      <th>Issues</th>
      <th>Action</th>
    </tr>
    <?php foreach ($cats as $c): ?>
      <tr>
        <td><?= e($c['category_name']) ?></td>
        <td><?= $c['total'] ?></td>
        <td class="admin-action-cell">
          <form method="post" class="inline-form">
            <input type="hidden" name="type" value="delete_category">
            <input type="hidden" name="category_id" value="<?= (int) $c['category_id'] ?>">
            <button class="btn danger" type="submit"
              data-confirm="Remove the category &quot;<?= e($c['category_name']) ?>&quot;?">Remove</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>

  <h2>Issues by Category</h2>
  <table>
    <tr>
      <th>Category</th>
      <th>Issues</th>
    </tr>
    <?php foreach ($cats as $c): ?>
      <tr>
        <td><?= e($c['category_name']) ?></td>
        <td><?= $c['total'] ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php require "partials/footer.php"; ?>