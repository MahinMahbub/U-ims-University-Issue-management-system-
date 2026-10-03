<?php
$page_title = "Home";
require "partials/header.php";
?>
<main class="container hero">
  <h1>Report problems.<br><span id="hero-animated" class="hero-animated">Track progress.</span><br>Improve the facilities.</h1>
  <p>U-IMS centralizes student issue reporting, authority verification, evidence, progress updates and resolution
    tracking.</p>
  <div class="actions">
    <a class="btn" href="/uims/register.php">Report an Issue</a>
    <a class="btn secondary" href="/uims/issues.php">Browse Verified Issues</a>
  </div>
</main>
<section class="container grid">
  <div class="card">
    <h3>Structured Reporting</h3>
    <p class="muted">Submit category, location, description, priority and supporting evidence.</p>
  </div>
  <div class="card">
    <h3>Verification</h3>
    <p class="muted">Authorities verify reports before they become publicly visible.</p>
  </div>
  <div class="card">
    <h3>Transparency</h3>
    <p class="muted">Students can follow statuses and resolution updates from one dashboard.</p>
  </div>
</section>
<?php require "partials/footer.php"; ?>