<?php
require "config/database.php";
require "config/auth.php";
require "config/staff.php";
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
$updatesStmt = $pdo->prepare("SELECT iu.*,u.name authority_name FROM issue_updates iu JOIN users u ON u.user_id=iu.authority_id WHERE iu.issue_id=? ORDER BY iu.created_at DESC");
$updatesStmt->execute([$id]);
$updates = $updatesStmt->fetchAll();
$ev = $pdo->prepare("SELECT * FROM evidence WHERE issue_id=?");
$ev->execute([$id]);
$evidence = $ev->fetchAll();

// Who is fixing it. Shown to logged-in users only, and without phone numbers.
$assigned = [];
if ($user && ensure_staff_tables($pdo)) {
    $assigned = issue_assignees($pdo, [$id])[$id] ?? [];
}
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

        <?php
        // Read the current status directly from the Issues table row loaded above.
        // The tracker has exactly five user-facing stages.
        $issueStatus = trim((string) ($issue['status'] ?? ''));

        $trackingSteps = [
            ['key' => 'Pending',     'label' => 'Pending',     'hint' => 'Your issue is waiting for verification'],
            ['key' => 'Under Review','label' => 'Under Review','hint' => 'The issue is being reviewed by the authority'],
            ['key' => 'Verified',    'label' => 'Verified',    'hint' => 'The issue has been verified'],
            ['key' => 'In Progress', 'label' => 'In Progress', 'hint' => 'Work is currently underway'],
            ['key' => 'Resolved',    'label' => 'Resolved',    'hint' => 'The reported issue has been resolved'],
        ];

        // Map the exact values stored in issues.status to the five tracker stages.
        // "More Information" is still part of the pending/review phase.
        // "Closed" is treated as the final Resolved stage.
        $statusToStep = [
            'Pending Verification' => 0,
            'More Information'     => 0,
            'Under Review'         => 1,
            'Verified'             => 2,
            'In Progress'          => 3,
            'Resolved'             => 4,
            'Closed'               => 4,
        ];

        // Rejected issues deliberately have no progress tracker.
        $showTracker = $issueStatus !== 'Rejected' && array_key_exists($issueStatus, $statusToStep);
        $currentStep = $showTracker ? $statusToStep[$issueStatus] : -1;
        $progressPercent = $showTracker ? (int) round((($currentStep + 1) / count($trackingSteps)) * 100) : 0;
        ?>
        <?php if ($showTracker): ?>
            <div class="issue-tracker" aria-label="Issue progress tracking">
                <div class="issue-tracker-head">
                    <div>
                        <div class="issue-tracker-kicker">ISSUE TRACKING</div>
                        <h3>Progress</h3>
                        <p><?= e($trackingSteps[$currentStep]['hint']) ?></p>
                    </div>
                    <div class="issue-tracker-percent">
                        <strong><?= $progressPercent ?>%</strong>
                        <span><?= $currentStep + 1 ?>/<?= count($trackingSteps) ?> stages</span>
                    </div>
                </div>

                <div class="issue-tracker-progress"
                     role="progressbar"
                     aria-label="Issue progress"
                     aria-valuemin="0"
                     aria-valuemax="100"
                     aria-valuenow="<?= $progressPercent ?>">
                    <div class="issue-tracker-progress-fill" style="width: <?= $progressPercent ?>%;"></div>
                </div>

                <div class="issue-tracker-steps">
                    <?php foreach ($trackingSteps as $index => $step):
                        $completed = $index < $currentStep;
                        $active = $index === $currentStep;
                    ?>
                        <div class="issue-tracker-step <?= $completed ? 'completed' : '' ?> <?= $active ? 'active' : '' ?>">
                            <div class="issue-tracker-node" aria-hidden="true">
                                <?= $completed ? '✓' : ($index + 1) ?>
                            </div>
                            <div class="issue-tracker-label">
                                <strong><?= e($step['label']) ?></strong>
                                <span><?= e($active ? 'Current status' : ($completed ? 'Completed' : 'Next step')) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (!empty($updates)): ?>
                    <?php $latestUpdate = $updates[0] ?? null; ?>
                    <?php if ($latestUpdate): ?>
                        <div class="tracker-update">
                            <div class="tracker-update-icon">↗</div>
                            <div>
                                <strong>Latest progress update</strong>
                                <p><?= nl2br(e($latestUpdate['message'])) ?></p>
                                <small class="muted">Status: <?= e($issueStatus) ?> · By <?= e($latestUpdate['authority_name']) ?> · <?= e($latestUpdate['created_at']) ?></small>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <p class="muted">Reported by <?= e($issue['reporter']) ?> on <?= e($issue['created_at']) ?><br>Location:
            <?= e($issue['location_name']) ?><br>Department: <?= e($issue['department_name'] ?? 'Not assigned') ?>
            <?php if ($assigned): ?><br>Being fixed by: <?= e(assignees_label($assigned)) ?><?php endif; ?></p>
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