<?php
require "config/database.php";
require "config/auth.php";
if (isset($_SESSION['user'])) {
    header("Location: dashboard.php");
    exit;
}
$error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare("SELECT u.*, r.role_name, d.department_name FROM users u JOIN roles r ON r.role_id=u.role_id LEFT JOIN departments d ON d.department_id=u.department_id WHERE u.email=? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        unset($user['password_hash']);
        $_SESSION['user'] = $user;
        header("Location: dashboard.php");
        exit;
    }
    $error = "Invalid email or password.";
}
$page_title = "Login";
require "partials/header.php";
?>
<div class="login-wrap">
    <div class="login-box">
        <h2>Sign in to U-IMS</h2>
        <?php if (isset($_GET['registered'])): ?>
            <div class="alert">Account created. You can now log in.</div><?php endif; ?>
        <?php if ($error): ?>
            <div class="alert error"><?= e($error) ?></div><?php endif; ?>
        <form method="post">
            <label>Email</label><input type="email" name="email" required>
            <label>Password</label><input type="password" name="password" required>
            <br><br><button class="btn" type="submit">Login</button>
        </form>
        <p class="muted">Demo: student@uims.local / password<br>authority@uims.local / password<br>admin@uims.local /
            password</p>
    </div>
</div>
<?php require "partials/footer.php"; ?>