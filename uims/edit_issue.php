<?php
require "config/database.php";
require "config/auth.php";
require_login();

if ($_SESSION['user']['role_name'] !== 'Student') {
    header("Location: authority.php");
    exit;
}

$user_id = (int) $_SESSION['user']['user_id'];
$issue_id = (int) ($_GET['id'] ?? $_POST['issue_id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM issues WHERE issue_id=? AND user_id=?");
$stmt->execute([$issue_id, $user_id]);
$issue = $stmt->fetch();
if (!$issue) {
    http_response_code(404);
    exit("Issue not found.");
}

// Once an issue is verified, students can no longer edit it.
$lockedStatuses = ['Verified', 'In Progress', 'Resolved', 'Closed'];
if (in_array($issue['status'], $lockedStatuses, true)) {
    header("Location: issue.php?id=" . $issue_id);
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
    } elseif (!in_array($priority, ['Low', 'Medium', 'High', 'Critical'], true)) {
        $error = "Invalid priority selected.";
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE issues SET title=?, description=?, category_id=?, location_id=?, observed_at=?, priority=? WHERE issue_id=? AND user_id=?");
            $stmt->execute([$title, $desc, $cat, $loc, $observed ?: null, $priority, $issue_id, $user_id]);

            // Evidence is optional, so existing issue editing works exactly like before.
            if (isset($_FILES['evidence']) && $_FILES['evidence']['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($_FILES['evidence']['error'] !== UPLOAD_ERR_OK) {
                    throw new RuntimeException("Could not upload the evidence file.");
                }
                $allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
                if (!in_array($_FILES['evidence']['type'], $allowed, true)) {
                    throw new RuntimeException("Only JPG, PNG, WEBP or PDF evidence is allowed.");
                }
                if ($_FILES['evidence']['size'] > 5 * 1024 * 1024) {
                    throw new RuntimeException("Evidence must be 5MB or smaller.");
                }
                $ext = strtolower(pathinfo($_FILES['evidence']['name'], PATHINFO_EXTENSION));
                $name = uniqid('evidence_', true) . '.' . $ext;
                $target = __DIR__ . '/uploads/' . $name;
                if (!move_uploaded_file($_FILES['evidence']['tmp_name'], $target)) {
                    throw new RuntimeException("Could not save evidence.");
                }
                $pdo->prepare("INSERT INTO evidence(issue_id,file_path,file_type) VALUES(?,?,?)")
                    ->execute([$issue_id, 'uploads/' . $name, $_FILES['evidence']['type']]);
            }

            $pdo->commit();
            header("Location: issue.php?id=" . $issue_id);
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        }
    }
}

$page_title = "Edit Issue #" . $issue_id;
require "partials/header.php";
?>
<div class="form-card">
    <h2>Edit Issue</h2>
    <p class="muted">Update your issue details or add evidence later.</p>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="issue_id" value="<?= $issue_id ?>">
        <label>Issue Title *</label>
        <input name="title" value="<?= e($issue['title']) ?>" required>

        <label>Description *</label>
        <textarea name="description" required><?= e($issue['description']) ?></textarea>

        <div class="row">
            <div>
                <label>Category *</label>
                <select name="category_id" required>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['category_id'] ?>" <?= $issue['category_id'] == $c['category_id'] ? 'selected' : '' ?>><?= e($c['category_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Location *</label>
                <select name="location_id" required>
                    <?php foreach ($locations as $l): ?>
                        <option value="<?= $l['location_id'] ?>" <?= $issue['location_id'] == $l['location_id'] ? 'selected' : '' ?>><?= e($l['location_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="row">
            <div>
                <label>Date/time observed</label>
                <input type="datetime-local" name="observed_at" value="<?= $issue['observed_at'] ? e(date('Y-m-d\\TH:i', strtotime($issue['observed_at']))) : '' ?>">
            </div>
            <div>
                <label>Priority</label>
                <select name="priority">
                    <?php foreach (['Low', 'Medium', 'High', 'Critical'] as $p): ?>
                        <option <?= $issue['priority'] === $p ? 'selected' : '' ?>><?= $p ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <label>Add supporting evidence</label>
        <input id="evidence" type="file" name="evidence" accept=".jpg,.jpeg,.png,.webp,.pdf">
        <small id="file-name" class="muted">No new file selected — max 5MB</small>

        <div class="actions">
            <button class="btn" type="submit">Save Changes</button>
            <a class="btn secondary" href="issue.php?id=<?= $issue_id ?>">Cancel</a>
        </div>
    </form>
</div>
<?php require "partials/footer.php"; ?>
