<?php
require "config/database.php";
require "config/auth.php";
if (isset($_SESSION['user'])) {
    header("Location: dashboard.php");
    exit;
}

$departments = $pdo->query("SELECT * FROM departments ORDER BY department_name")->fetchAll();
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = trim($_POST['student_id'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $department_id = (int) ($_POST['department_id'] ?? 0);
    $batch = trim($_POST['batch'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!$student_id || !$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$department_id || strlen($password) < 6) {
        $error = "Please fill all required fields correctly. Password must be at least 6 characters.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO users(role_id,department_id,student_id,name,email,batch,password_hash) VALUES (1,?,?,?,?,?,?,?)");
            $stmt->execute([$department_id, $student_id, $name, $email, $batch, password_hash($password, PASSWORD_DEFAULT)]);
            header("Location: login.php?registered=1");
            exit;
        } catch (PDOException $e) {
            $error = "Registration failed. Student ID or email may already exist.";
        }
    }
}
$page_title = "Register";
require "partials/header.php";
?>
<div class="form-card">
    <h2>Student Registration</h2>
    <?php if ($error): ?>
        <div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
        <div class="row">
            <div><label>Student ID</label><input name="student_id" required></div>
            <div><label>Name</label><input name="name" required></div>
        </div>
        <label>Email</label><input type="email" name="email" required>
        <div class="row">
            <div><label>Department</label><select name="department_id" required>
                    <option value="">Select</option><?php foreach ($departments as $d): ?>
                        <option value="<?= $d['department_id'] ?>"><?= e($d['department_name']) ?></option><?php endforeach; ?>
                </select></div>
            <div><label>Batch</label><input name="batch"></div>
        </div>
        <div class="row">
            <div><label>Password</label><input type="password" name="password" required></div>
            <div><label>Confirm Password</label><input type="password" name="confirm_password" required></div>
        </div>
        <br><button class="btn" type="submit">Create Account</button>
    </form>
</div>
<?php require "partials/footer.php"; ?>