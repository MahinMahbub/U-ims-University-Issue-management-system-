<?php
require "config/database.php";
require "config/auth.php";
require "config/social.php";

$q = trim($_GET['q'] ?? '');
$cat = (int) ($_GET['category_id'] ?? 0);
$status = trim($_GET['status'] ?? '');
$priority = trim($_GET['priority'] ?? '');
$categories = $pdo->query("SELECT * FROM categories ORDER BY category_name")->fetchAll();

/* ---------------------------------------------------------------------------
 * Monthly statistics (all issues on the system, last 6 months).
 * Submitted: grouped by created_at. Resolved: issues whose current status is
 * Resolved/Closed, grouped by updated_at. Same rules as the student dashboard.
 * Only counts are exposed here, never the contents of unpublished issues.
 * ------------------------------------------------------------------------- */
$chartMonths = [];
$monthCursor = new DateTime('first day of this month');
$monthCursor->modify('-5 months');
for ($m = 0; $m < 6; $m++) {
    $chartMonths[$monthCursor->format('Y-m')] = [
        'month' => $monthCursor->format('M'),
        'year' => $monthCursor->format('Y'),
        'submitted' => 0,
        'resolved' => 0,
    ];
    $monthCursor->modify('+1 month');
}
$chartStart = (new DateTime('first day of this month'))->modify('-5 months')->format('Y-m-d 00:00:00');

$chartStmt = $pdo->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_key, COUNT(*) AS total
    FROM issues WHERE created_at >= ? GROUP BY DATE_FORMAT(created_at, '%Y-%m')");
$chartStmt->execute([$chartStart]);
foreach ($chartStmt as $row) {
    if (isset($chartMonths[$row['month_key']])) {
        $chartMonths[$row['month_key']]['submitted'] = (int) $row['total'];
    }
}
$chartStmt = $pdo->prepare("SELECT DATE_FORMAT(updated_at, '%Y-%m') AS month_key, COUNT(*) AS total
    FROM issues WHERE status IN ('Resolved','Closed') AND updated_at >= ? GROUP BY DATE_FORMAT(updated_at, '%Y-%m')");
$chartStmt->execute([$chartStart]);
foreach ($chartStmt as $row) {
    if (isset($chartMonths[$row['month_key']])) {
        $chartMonths[$row['month_key']]['resolved'] = (int) $row['total'];
    }
}

// Pick an axis whose gridlines land on whole numbers, so bar heights match the labels.
$chartPeak = 1;
foreach ($chartMonths as $month) {
    $chartPeak = max($chartPeak, $month['submitted'], $month['resolved']);
}
$axisIntervals = $chartPeak <= 4 ? $chartPeak : 4;
$axisStep = (int) ceil($chartPeak / $axisIntervals);
$axisMax = $axisStep * $axisIntervals;
$chartSummary = [];
foreach ($chartMonths as $month) {
    $chartSummary[] = "{$month['month']} {$month['year']}: {$month['submitted']} submitted, {$month['resolved']} resolved";
}

/* ---------------------------------------------------------------------------
 * Feed query. Rejected issues are included so decisions stay transparent.
 * ------------------------------------------------------------------------- */
$where = ["i.status IN (" . in_placeholders(FEED_STATUSES) . ")"];
$params = FEED_STATUSES;
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
$sql = "SELECT i.*, u.name AS reporter, c.category_name, l.location_name, d.department_name,
        TIMESTAMPDIFF(SECOND, i.created_at, NOW()) AS age_seconds
    FROM issues i
    JOIN users u ON u.user_id=i.user_id
    JOIN categories c ON c.category_id=i.category_id
    JOIN locations l ON l.location_id=i.location_id
    LEFT JOIN departments d ON d.department_id=i.department_id
    WHERE " . implode(" AND ", $where) . " ORDER BY i.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$issues = $stmt->fetchAll();

/* Evidence previews, reaction counts and comment counts for the issues on screen. */
$evidenceByIssue = [];
$reactionCounts = [];
$myReactions = [];
$commentCounts = [];
$socialReady = ensure_social_tables($pdo);
$user = current_user();
$userId = (int) ($user['user_id'] ?? 0);

if ($issues) {
    $ids = array_map('intval', array_column($issues, 'issue_id'));
    $in = in_placeholders($ids);

    $stmt = $pdo->prepare("SELECT issue_id, file_path, file_type FROM evidence WHERE issue_id IN ($in) ORDER BY evidence_id");
    $stmt->execute($ids);
    foreach ($stmt as $row) {
        $evidenceByIssue[(int) $row['issue_id']][] = $row;
    }

    if ($socialReady) {
        $stmt = $pdo->prepare("SELECT issue_id, reaction, COUNT(*) c FROM issue_reactions WHERE issue_id IN ($in) GROUP BY issue_id, reaction");
        $stmt->execute($ids);
        foreach ($stmt as $row) {
            $reactionCounts[(int) $row['issue_id']][$row['reaction']] = (int) $row['c'];
        }
        if ($userId > 0) {
            $stmt = $pdo->prepare("SELECT issue_id, reaction FROM issue_reactions WHERE user_id=? AND issue_id IN ($in)");
            $stmt->execute(array_merge([$userId], $ids));
            foreach ($stmt as $row) {
                $myReactions[(int) $row['issue_id']] = $row['reaction'];
            }
        }
        $stmt = $pdo->prepare("SELECT issue_id, COUNT(*) c FROM issue_comments WHERE issue_id IN ($in) GROUP BY issue_id");
        $stmt->execute($ids);
        foreach ($stmt as $row) {
            $commentCounts[(int) $row['issue_id']] = (int) $row['c'];
        }
    }
}

$statusBadge = [
    'Verified' => 'green',
    'In Progress' => 'blue',
    'Resolved' => 'green',
    'Closed' => '',
    'Rejected' => 'red',
];

$page_title = "Verified Issues";
require "partials/header.php";
?>
<div class="container">
    <h1>Verified Issues</h1>
    <p class="muted">Reports reviewed by the authority. Rejected reports stay visible with the reason for the decision.</p>

    <section class="card issue-chart-card" aria-labelledby="chart-title">
        <div class="issue-chart-header">
            <div>
                <h2 id="chart-title">Monthly Issue Overview</h2>
                <p class="muted">Submitted vs. resolved issues over the last 6 months.</p>
            </div>
            <div class="issue-chart-legend" aria-hidden="true">
                <span><i class="chart-dot submitted"></i>Submitted</span>
                <span><i class="chart-dot resolved"></i>Resolved</span>
            </div>
        </div>

        <div class="issue-chart issue-chart--aligned" role="img" aria-label="<?= e('Submitted and resolved issues by month. ' . implode('; ', $chartSummary)) ?>">
            <div class="issue-chart-yaxis" aria-hidden="true">
                <?php for ($tick = $axisMax; $tick >= 0; $tick -= $axisStep): ?>
                    <span><?= $tick ?></span>
                <?php endfor; ?>
            </div>
            <div class="issue-chart-plot">
                <div class="issue-chart-gridlines" aria-hidden="true">
                    <?php for ($g = 0; $g <= $axisIntervals; $g++): ?><span></span><?php endfor; ?>
                </div>
                <div class="issue-chart-bars">
                    <?php foreach ($chartMonths as $month):
                        $submittedHeight = ($month['submitted'] / $axisMax) * 100;
                        $resolvedHeight = ($month['resolved'] / $axisMax) * 100;
                    ?>
                        <div class="issue-chart-month">
                            <div class="issue-chart-columns">
                                <div class="chart-bar submitted-bar" style="height: <?= round($submittedHeight, 2) ?>%;" data-value="<?= $month['submitted'] ?>"></div>
                                <div class="chart-bar resolved-bar" style="height: <?= round($resolvedHeight, 2) ?>%;" data-value="<?= $month['resolved'] ?>"></div>
                            </div>
                            <span class="issue-chart-month-label"><?= e($month['month']) ?><span class="issue-chart-year"> <?= e($month['year']) ?></span></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="issue-chart-note">Resolved includes issues whose current status is <strong>Resolved</strong> or <strong>Closed</strong>.</div>
    </section>

    <form class="filters" method="get">
        <input name="q" value="<?= e($q) ?>" placeholder="Search title or description" aria-label="Search issues">
        <select name="category_id" aria-label="Category">
            <option value="">All categories</option><?php foreach ($categories as $c): ?>
                <option value="<?= $c['category_id'] ?>" <?= $cat === (int) $c['category_id'] ? 'selected' : '' ?>>
                    <?= e($c['category_name']) ?></option><?php endforeach; ?>
        </select>
        <select name="status" aria-label="Status">
            <option value="">All statuses</option>
            <?php foreach (FEED_STATUSES as $s): ?>
                <option <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?>
        </select>
        <select name="priority" aria-label="Priority">
            <option value="">All priorities</option><?php foreach (['Low', 'Medium', 'High', 'Critical'] as $p): ?>
                <option <?= $priority === $p ? 'selected' : '' ?>><?= $p ?></option><?php endforeach; ?>
        </select>
        <button class="btn">Filter</button>
    </form>

    <div class="feed-summary">
        <span><?= count($issues) ?> <?= count($issues) === 1 ? 'issue' : 'issues' ?></span>
    </div>

    <?php if (!$issues): ?>
        <div class="card feed-empty">
            <strong>No issues match these filters.</strong>
            <p class="muted">Try a different search or <a class="feed-link" href="issues.php">clear all filters</a>.</p>
        </div>
    <?php else: ?>
        <div class="feed-grid feed" data-csrf="<?= $user ? e(csrf_token()) : '' ?>">
            <?php foreach ($issues as $i):
                $id = (int) $i['issue_id'];
                $title = (string) $i['title'];
                $isRejected = $i['status'] === 'Rejected';
                $files = $evidenceByIssue[$id] ?? [];

                // Thumbnail: first image evidence wins; otherwise a placeholder that says what is attached.
                $thumb = null;
                foreach ($files as $f) {
                    if (evidence_is_image($f)) { $thumb = (string) $f['file_path']; break; }
                }
                $hasPdf = false;
                foreach ($files as $f) {
                    if (!evidence_is_image($f)) { $hasPdf = true; break; }
                }
                if (count($files) > 1) {
                    $badge = count($files) . ' files';
                } elseif ($files) {
                    $badge = $thumb ? 'Photo' : 'PDF';
                } else {
                    $badge = '';
                }
                if ($thumb) {
                    $placeholder = 'Preview unavailable';
                } elseif ($hasPdf) {
                    $placeholder = 'PDF evidence';
                } else {
                    $placeholder = 'No evidence attached';
                }

                $myReaction = $myReactions[$id] ?? null;
                $commentTotal = $commentCounts[$id] ?? 0;
            ?>
                <article class="feed-card<?= $isRejected ? ' is-rejected' : '' ?>" data-issue="<?= $id ?>">
                    <div class="feed-main">
                        <a class="feed-thumb" href="issue.php?id=<?= $id ?>" aria-label="Open issue: <?= e($title) ?>">
                            <span class="feed-thumb-empty">
                                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <?php if ($hasPdf && !$thumb): ?>
                                        <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"></path><path d="M14 3v5h5"></path><path d="M9 14h6M9 17h4"></path>
                                    <?php else: ?>
                                        <rect x="3" y="5" width="18" height="14" rx="2"></rect><circle cx="9" cy="10.5" r="1.5"></circle><path d="m21 16-5-5-8 8"></path>
                                    <?php endif; ?>
                                </svg>
                                <span><?= e($placeholder) ?></span>
                            </span>
                            <?php if ($thumb): ?>
                                <img src="<?= e($thumb) ?>" alt="Evidence photo for <?= e($title) ?>" loading="lazy" onerror="this.remove()">
                            <?php endif; ?>
                            <?php if ($badge): ?><span class="feed-thumb-badge"><?= e($badge) ?></span><?php endif; ?>
                        </a>

                        <div class="feed-body">
                            <h3 class="feed-title"><a href="issue.php?id=<?= $id ?>"><?= e($title) ?></a></h3>
                            <div class="feed-by">
                                <span><?= e((string) $i['reporter']) ?></span>
                                <?php if (!$isRejected): ?>
                                    <svg class="feed-check" viewBox="0 0 24 24" role="img" aria-label="Verified by the authority">
                                        <title>Verified by the authority</title>
                                        <path d="M12 2.5l2.4 1.7 2.9-.1 1 2.7 2.4 1.7-.9 2.8.9 2.8-2.4 1.7-1 2.7-2.9-.1L12 21.5l-2.4-1.7-2.9.1-1-2.7-2.4-1.7.9-2.8-.9-2.8 2.4-1.7 1-2.7 2.9.1z"></path>
                                        <path class="tick" d="m8.6 12.2 2.4 2.4 4.6-4.9"></path>
                                    </svg>
                                <?php endif; ?>
                            </div>
                            <?php // Non-breaking space before each dot keeps a separator from starting a wrapped line. ?>
                            <div class="feed-meta" title="Reported <?= e((string) $i['created_at']) ?>"><?= implode("\u{00A0}\u{00B7} ", [e((string) $i['category_name']), e((string) $i['location_name']), e(time_ago((int) $i['age_seconds']))]) ?></div>
                            <div class="feed-tags">
                                <span class="badge"><?= e((string) $i['priority']) ?></span>
                                <span class="badge <?= $statusBadge[$i['status']] ?? '' ?>"><?= e((string) $i['status']) ?></span>
                                <?php if (!empty($i['department_name'])): ?><span class="badge"><?= e((string) $i['department_name']) ?></span><?php endif; ?>
                            </div>
                            <?php if ($isRejected): ?>
                                <?php $reason = trim((string) ($i['rejection_reason'] ?? '')); ?>
                                <div class="feed-reason" title="<?= e($reason) ?>">
                                    <strong>Why it was rejected</strong>
                                    <span class="feed-reason-text"><?= e($reason !== '' ? $reason : 'No reason was recorded.') ?></span>
                                </div>
                            <?php else: ?>
                                <p class="feed-excerpt"><?= e((string) $i['description']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($socialReady): ?>
                        <div class="feed-actions">
                            <div class="react-group" role="group" aria-label="React to this issue">
                                <?php foreach (SOCIAL_REACTIONS as $key => $r):
                                    $count = $reactionCounts[$id][$key] ?? 0;
                                    $active = $myReaction === $key;
                                ?>
                                    <?php if ($user): ?>
                                        <button type="button" class="react-btn<?= $active ? ' is-active' : '' ?>" data-reaction="<?= e($key) ?>" aria-pressed="<?= $active ? 'true' : 'false' ?>">
                                            <span aria-hidden="true"><?= $r['emoji'] ?></span>
                                            <span><?= e($r['label']) ?></span>
                                            <span class="react-count"><?= $count ?: '' ?></span>
                                        </button>
                                    <?php else: ?>
                                        <a class="react-btn" href="login.php" title="Log in to react">
                                            <span aria-hidden="true"><?= $r['emoji'] ?></span>
                                            <span><?= e($r['label']) ?></span>
                                            <span class="react-count"><?= $count ?: '' ?></span>
                                        </a>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="comment-toggle" aria-expanded="false" aria-controls="comments-<?= $id ?>">
                                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20.5l1.5-4.6A8 8 0 1 1 21 12z"></path></svg>
                                <span>Comments</span>
                                <span class="comment-count"><?= $commentTotal ?></span>
                            </button>
                        </div>
                        <p class="feed-note" role="status"></p>
                        <div class="feed-comments" id="comments-<?= $id ?>" hidden>
                            <div class="feed-comment-list" aria-live="polite"></div>
                            <?php if ($user): ?>
                                <form class="feed-comment-form">
                                    <input type="text" name="body" maxlength="<?= COMMENT_MAX_LENGTH ?>" placeholder="Add a comment" aria-label="Add a comment" autocomplete="off">
                                    <button class="btn" type="submit">Send</button>
                                </form>
                            <?php else: ?>
                                <p class="muted feed-login-note"><a class="feed-link" href="login.php">Log in</a> to join the conversation.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php require "partials/footer.php"; ?>
