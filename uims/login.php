<?php
require "config/database.php";
require "config/auth.php";
if (isset($_SESSION['user'])) {
    header("Location: dashboard.php");
    exit;
}
$error = "";
$email = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare("SELECT u.*, r.role_name, d.department_name FROM users u JOIN roles r ON r.role_id=u.role_id LEFT JOIN departments d ON d.department_id=u.department_id WHERE u.email=? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        unset($user['password_hash']);
        session_regenerate_id(true);
        $_SESSION['user'] = $user;
        header("Location: dashboard.php");
        exit;
    }
    $error = "That email and password do not match.";
}
$page_title = "Log in";
require "partials/header.php";
?>
<div class="login-wrap">
    <div class="login-box">
        <h1 class="form-title">Welcome back</h1>
        <p class="muted form-sub">Log in to report and track issues.</p>
        <?php if (isset($_GET['registered'])): ?>
            <div class="alert success">Account created. You can log in now.</div><?php endif; ?>
        <?php if ($error): ?>
            <div class="alert error"><?= e($error) ?></div><?php endif; ?>
        <form method="post" data-once>
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="<?= e($email) ?>" autocomplete="username" required>
            <label for="password">Password</label>
            <input id="password" type="password" name="password" autocomplete="current-password" required>
            <div class="form-actions">
                <button class="btn" type="submit" data-busy="Logging in...">Log in</button>
                <span class="muted">New here? <a class="text-link" href="register.php">Create an account</a></span>
            </div>
        </form>
        <details class="demo-accounts">
            <summary>Demo accounts</summary>
            <p class="muted">student@uims.local<br>authority@uims.local<br>admin@uims.local<br>Password for all three: <strong>password</strong></p>
        </details>
    </div>
</div>
<?php require "partials/footer.php"; ?>
