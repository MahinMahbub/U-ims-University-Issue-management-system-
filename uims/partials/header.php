<?php
require_once __DIR__ . '/../config/auth.php';
$user = current_user();
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
  <link rel="stylesheet" href="assets/style.css?v=5">
</head>

<body>
  <nav class="navbar">
    <a class="brand" href="/uims/index.php">U-<span style="color: white">IMS</span></a>
    <div class="navlinks">
      <button class="theme-toggle" id="theme-toggle" type="button" aria-label="Switch to dark theme" title="Switch to dark theme">
        <svg class="theme-icon theme-icon-sun" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <circle cx="12" cy="12" r="4"></circle>
          <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"></path>
        </svg>
        <svg class="theme-icon theme-icon-moon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="M20.3 14.6A8.7 8.7 0 0 1 9.4 3.7 8.7 8.7 0 1 0 20.3 14.6Z"></path>
        </svg>
      </button>
      <a href="/uims/issues.php">Verified Issues</a>
      <?php if ($user): ?>
        <?php if ($user['role_name'] === 'Student'): ?>
          <a href="/uims/dashboard.php">Dashboard</a>
          <a href="/uims/submit_issue.php">Report Issue</a>
        <?php else: ?>
          <a href="/uims/authority.php">Authority</a>
        <?php endif; ?>
        <?php if ($user['role_name'] === 'Administrator'): ?>
          <a href="/uims/admin.php">Admin</a>
        <?php endif; ?>
        <a href="/uims/logout.php">Logout</a>
      <?php else: ?>
        <a href="/uims/login.php">Login</a>
        <a class="btn" href="/uims/register.php">Register</a>
      <?php endif; ?>
    </div>
  </nav>