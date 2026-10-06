<?php
require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/config/auth.php";

$statsTotal = 0;
$priorityStats = [];
$dateStats = [];

try {
    $statsTotal = (int) $pdo->query("SELECT COUNT(*) FROM issues")->fetchColumn();

    $priorityStats = $pdo->query("
        SELECT priority, COUNT(*) AS issue_count
        FROM issues
        GROUP BY priority
        ORDER BY FIELD(priority, 'Critical', 'High', 'Medium', 'Low')
    ")->fetchAll();

    // The most recent days that had reports.
    $dateStats = $pdo->query("
        SELECT DATE(created_at) AS issue_date, COUNT(*) AS issue_count
        FROM issues
        GROUP BY DATE(created_at)
        ORDER BY issue_date DESC
        LIMIT 7
    ")->fetchAll();
} catch (PDOException $e) {
    $statsError = "Statistics are temporarily unavailable.";
}

$priorityCounts = [];
foreach ($priorityStats as $row) {
    $priorityCounts[$row['priority']] = (int) $row['issue_count'];
}

$viewer = current_user();
if ($viewer && $viewer['role_name'] === 'Student') {
    $ctaLink = "submit_issue.php";
} elseif ($viewer) {
    $ctaLink = "authority.php";
} else {
    $ctaLink = "register.php";
}
$ctaLabel = $viewer && $viewer['role_name'] !== 'Student' ? "Manage issues" : "Report an issue";

$page_title = "Home";
require "partials/header.php";
?>
<section class="container hero">
  <h1>Report problems.<br><span id="hero-animated" class="hero-animated">Track progress.</span><br>Improve the facilities.</h1>
  <p class="lede">U-IMS brings issue reporting, authority verification, evidence, progress updates and resolution tracking into one place.</p>
  <div class="actions">
    <a class="btn" href="<?= e($ctaLink) ?>"><?= e($ctaLabel) ?></a>
    <a class="btn secondary" href="issues.php">Browse verified issues</a>
  </div>
</section>

<section class="container steps" aria-label="How it works">
  <div class="step">
    <span class="step-num">1</span>
    <h3>Report</h3>
    <p class="muted">Send the category, location, description, priority and any photo or PDF as evidence.</p>
  </div>
  <div class="step">
    <span class="step-num">2</span>
    <h3>Verify</h3>
    <p class="muted">Authorities check each report before it becomes publicly visible.</p>
  </div>
  <div class="step">
    <span class="step-num">3</span>
    <h3>Track</h3>
    <p class="muted">Follow the status and every resolution update until the problem is fixed.</p>
  </div>
</section>

<section class="container stats-section">
  <div class="section-head">
    <h2>Issue statistics</h2>
    <p class="muted">Reports submitted so far, by priority and by day.</p>
  </div>

  <?php if (isset($statsError)): ?>
    <div class="alert error"><?= e($statsError) ?></div>
  <?php else: ?>
    <div class="stat-row">
      <div class="stat-tile"><div class="stat"><?= $statsTotal ?></div><div class="muted">Total issues</div></div>
      <div class="stat-tile"><div class="stat"><?= $priorityCounts['Critical'] ?? 0 ?></div><div class="muted">Critical</div></div>
      <div class="stat-tile"><div class="stat"><?= $priorityCounts['High'] ?? 0 ?></div><div class="muted">High priority</div></div>
      <div class="stat-tile"><div class="stat"><?= ($priorityCounts['Medium'] ?? 0) + ($priorityCounts['Low'] ?? 0) ?></div><div class="muted">Medium and low</div></div>
    </div>

    <div class="two-col">
      <div class="card">
        <h3 class="card-title">By priority</h3>
        <?php if ($priorityStats): ?>
          <?php foreach ($priorityStats as $row):
            $count = (int) $row['issue_count'];
            $percentage = $statsTotal > 0 ? ($count / $statsTotal) * 100 : 0;
          ?>
            <div class="bar-row">
              <span class="bar-name"><?= e($row['priority']) ?></span>
              <div class="bar"><div class="bar-fill" style="--width: <?= number_format($percentage, 2, '.', '') ?>%"></div></div>
              <span class="bar-count"><?= $count ?></span>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p class="muted">No issues have been submitted yet.</p>
        <?php endif; ?>
      </div>

      <div class="card">
        <h3 class="card-title">Recent days</h3>
        <?php if ($dateStats): ?>
          <table class="plain-table">
            <thead><tr><th>Date</th><th class="cell-right">Reports</th></tr></thead>
            <tbody>
              <?php foreach ($dateStats as $row): ?>
                <tr>
                  <td><?= e(date("F j, Y", strtotime($row['issue_date']))) ?></td>
                  <td class="cell-right"><strong><?= (int) $row['issue_count'] ?></strong></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <p class="muted">No issues have been submitted yet.</p>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</section>
<?php require "partials/footer.php"; ?>
