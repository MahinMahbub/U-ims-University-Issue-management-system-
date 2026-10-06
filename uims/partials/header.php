<?php
require_once __DIR__ . '/../config/auth.php';
$user = current_user();
$current_page = basename($_SERVER['SCRIPT_NAME'] ?? '');
$is_staff_user = $user && $user['role_name'] !== 'Student';

// Administrators see how many staff emails they have not looked at yet.
$unseen_emails = 0;
if ($user && $user['role_name'] === 'Administrator' && isset($pdo) && $pdo instanceof PDO) {
  require_once __DIR__ . '/../config/mailer.php';
  $unseen_emails = unseen_email_count($pdo);
}

/** Adds the active-page class to a nav link. */
$nav_class = function (array $pages) use ($current_page): string {
  return in_array($current_page, $pages, true) ? ' class="is-active" aria-current="page"' : '';
};
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= isset($page_title) ? e($page_title) . ' - U-IMS' : 'U-IMS' ?></title>
  <script>
    (function () {
      try {
        if (localStorage.getItem('uims-theme') === 'dark') document.documentElement.classList.add('dark');
      } catch (e) {}
    })();
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&display=swap">
  <link rel="stylesheet" href="assets/style.css?v=8">
</head>

<body>
  <header class="navbar" id="navbar">
    <div class="nav-inner">
      <a class="brand" href="/uims/index.php" aria-label="U-IMS home">
        <img src="assets/United_International_University_Monogram.svg" alt="" class="brand-mark">
        <span>U-IMS</span>
      </a>
      <button class="nav-toggle" id="nav-toggle" type="button" aria-label="Open menu" aria-expanded="false" aria-controls="nav-links">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 7h16M4 12h16M4 17h16"></path></svg>
      </button>
      <nav class="navlinks" id="nav-links" aria-label="Main">
        <a href="/uims/issues.php"<?= $nav_class(['issues.php']) ?>>Verified Issues</a>
        <?php if ($user): ?>
          <?php if (!$is_staff_user): ?>
            <a href="/uims/dashboard.php"<?= $nav_class(['dashboard.php', 'edit_issue.php']) ?>>Dashboard</a>
            <a href="/uims/submit_issue.php"<?= $nav_class(['submit_issue.php']) ?>>Report issue</a>
          <?php else: ?>
            <a href="/uims/authority.php"<?= $nav_class(['authority.php', 'manage_issue.php']) ?>>Manage issues</a>
          <?php endif; ?>
          <?php if ($user['role_name'] === 'Administrator'): ?>
            <a href="/uims/admin.php<?= $unseen_emails > 0 ? '?tab=emails' : '' ?>"<?= $nav_class(['admin.php']) ?>>Admin<?php if ($unseen_emails > 0): ?> <span class="count-badge" title="<?= $unseen_emails ?> new staff email<?= $unseen_emails === 1 ? '' : 's' ?>"><?= $unseen_emails ?></span><?php endif; ?></a>
          <?php endif; ?>
          <a href="/uims/logout.php">Log out</a>
        <?php else: ?>
          <a href="/uims/login.php"<?= $nav_class(['login.php']) ?>>Log in</a>
          <a class="btn sm" href="/uims/register.php">Create account</a>
        <?php endif; ?>
        <button class="theme-toggle" id="theme-toggle" type="button" aria-label="Switch to dark theme" title="Switch to dark theme">
          <svg class="theme-icon theme-icon-sun" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <circle cx="12" cy="12" r="4"></circle>
            <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"></path>
          </svg>
          <svg class="theme-icon theme-icon-moon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M20.3 14.6A8.7 8.7 0 0 1 9.4 3.7 8.7 8.7 0 1 0 20.3 14.6Z"></path>
          </svg>
        </button>
      </nav>
    </div>
  </header>
  <main id="main">
