<?php
require "config/database.php";
require "config/auth.php";
if (isset($_SESSION['user'])) {
    header("Location: dashboard.php");
    exit;
}

// Change this one number to change the minimum password length everywhere
// (server check, the hint under the field and the live browser check).
const PASSWORD_MIN_LENGTH = 6;
const PASSWORD_MAX_BYTES = 72; // bcrypt ignores everything past 72 bytes

// Departments come from the database, so whatever the administrator adds,
// renames or removes in the admin panel is what students see here.
$departments = $pdo->query(
    "SELECT department_id, department_name FROM departments
     ORDER BY department_name = 'Others', department_name"
)->fetchAll();

/** Which already-registered value clashes with this sign-up? */
function find_duplicate(PDO $pdo, string $student_id, string $email): array
{
    $found = [];
    $stmt = $pdo->prepare("SELECT 1 FROM users WHERE student_id = ? LIMIT 1");
    $stmt->execute([$student_id]);
    if ($stmt->fetchColumn()) {
        $found['student_id'] = true;
    }
    $stmt = $pdo->prepare("SELECT 1 FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetchColumn()) {
        $found['email'] = true;
    }
    return $found;
}

$old = ['student_id' => '', 'name' => '', 'email' => '', 'department_id' => '', 'batch' => ''];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['student_id'] = preg_replace('/\s+/', '', (string) ($_POST['student_id'] ?? ''));
    $old['name'] = trim((string) preg_replace('/\s+/u', ' ', (string) ($_POST['name'] ?? '')));
    $old['email'] = strtolower(trim((string) ($_POST['email'] ?? '')));
    $old['department_id'] = (string) (int) ($_POST['department_id'] ?? 0);
    $old['batch'] = trim((string) ($_POST['batch'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $errors['form'] = "This page expired. Please try again.";
    }

    // Validate every field first and report all problems together.
    if ($old['student_id'] === '') {
        $errors['student_id'] = "Enter your Student ID.";
    } elseif (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_\-\/]{2,29}$/', $old['student_id'])) {
        $errors['student_id'] = "Use 3 to 30 letters or digits (dashes are fine).";
    }

    if ($old['name'] === '') {
        $errors['name'] = "Enter your full name.";
    } elseif (text_length($old['name']) > 120) {
        $errors['name'] = "Keep your name under 120 characters.";
    }

    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Enter a valid email address.";
    } elseif (text_length($old['email']) > 150) {
        $errors['email'] = "Keep your email under 150 characters.";
    }

    $valid_department = false;
    foreach ($departments as $d) {
        if ((string) $d['department_id'] === $old['department_id']) {
            $valid_department = true;
            break;
        }
    }
    if (!$valid_department) {
        $errors['department_id'] = "Choose your department.";
    }

    if (text_length($old['batch']) > 30) {
        $errors['batch'] = "Keep the batch under 30 characters.";
    }

    if (text_length($password) < PASSWORD_MIN_LENGTH) {
        $errors['password'] = "Use at least " . PASSWORD_MIN_LENGTH . " characters.";
    } elseif (strlen($password) > PASSWORD_MAX_BYTES) {
        $errors['password'] = "Use at most " . PASSWORD_MAX_BYTES . " characters.";
    } elseif ($password !== $confirm) {
        $errors['confirm_password'] = "The two passwords do not match.";
    }

    // Only look at the database once the form itself is valid.
    if (!$errors) {
        $dupes = find_duplicate($pdo, $old['student_id'], $old['email']);
        if ($dupes) {
            if (isset($dupes['student_id'])) {
                $errors['student_id'] = "This Student ID is already registered.";
            }
            if (isset($dupes['email'])) {
                $errors['email'] = "This email is already registered.";
            }
            $errors['already'] = true;
        }
    }

    if (!$errors) {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO users(role_id, department_id, student_id, name, email, batch, password_hash)
                 VALUES (1, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                (int) $old['department_id'],
                $old['student_id'],
                $old['name'],
                $old['email'],
                $old['batch'] !== '' ? $old['batch'] : null,
                password_hash($password, PASSWORD_DEFAULT),
            ]);
            header("Location: login.php?registered=1");
            exit;
        } catch (PDOException $ex) {
            // 23000 = unique key violation: someone (or a double click) got there first.
            if ($ex->getCode() === '23000') {
                $dupes = find_duplicate($pdo, $old['student_id'], $old['email']);
                if (isset($dupes['student_id'])) {
                    $errors['student_id'] = "This Student ID is already registered.";
                }
                if (isset($dupes['email'])) {
                    $errors['email'] = "This email is already registered.";
                }
                $errors['already'] = true;
            } else {
                $errors['form'] = "We could not create your account. Please try again in a moment.";
            }
        }
    }
}

$page_title = "Create account";
require "partials/header.php";

/** Field-level error + invalid styling helpers. */
function field_error(array $errors, string $key): string
{
    return isset($errors[$key]) && is_string($errors[$key])
        ? '<small class="field-error" id="err-' . $key . '">' . e($errors[$key]) . '</small>'
        : '';
}
function invalid(array $errors, string $key): string
{
    return isset($errors[$key]) && is_string($errors[$key]) ? ' is-invalid' : '';
}
?>
<div class="form-card">
    <h1 class="form-title">Create your account</h1>
    <p class="muted form-sub">Students only. You will use your email to sign in.</p>

    <?php if (!empty($errors['form'])): ?>
        <div class="alert error"><?= e($errors['form']) ?></div>
    <?php endif; ?>
    <?php if (!empty($errors['already'])): ?>
        <div class="alert">Already have an account? <a class="text-link" href="login.php">Log in</a> instead.</div>
    <?php endif; ?>

    <form method="post" novalidate data-once id="register-form">
        <?= csrf_field() ?>
        <div class="row">
            <div>
                <label for="student_id">Student ID</label>
                <input id="student_id" name="student_id" value="<?= e($old['student_id']) ?>" maxlength="30"
                    autocomplete="username" class="<?= invalid($errors, 'student_id') ?>" required>
                <?= field_error($errors, 'student_id') ?>
            </div>
            <div>
                <label for="name">Full name</label>
                <input id="name" name="name" value="<?= e($old['name']) ?>" maxlength="120" autocomplete="name"
                    class="<?= invalid($errors, 'name') ?>" required>
                <?= field_error($errors, 'name') ?>
            </div>
        </div>

        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="<?= e($old['email']) ?>" maxlength="150"
            autocomplete="email" class="<?= invalid($errors, 'email') ?>" required>
        <?= field_error($errors, 'email') ?>

        <div class="row">
            <div>
                <label for="department_id">Department</label>
                <select id="department_id" name="department_id" class="<?= invalid($errors, 'department_id') ?>" required>
                    <option value="">Select department</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= (int) $d['department_id'] ?>" <?= $old['department_id'] === (string) $d['department_id'] ? 'selected' : '' ?>>
                            <?= e($d['department_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= field_error($errors, 'department_id') ?>
            </div>
            <div>
                <label for="batch">Batch <span class="optional">(optional)</span></label>
                <input id="batch" name="batch" value="<?= e($old['batch']) ?>" maxlength="30"
                    class="<?= invalid($errors, 'batch') ?>">
                <?= field_error($errors, 'batch') ?>
            </div>
        </div>

        <div class="row">
            <div>
                <label for="password">Password</label>
                <input id="password" type="password" name="password" autocomplete="new-password"
                    data-min="<?= PASSWORD_MIN_LENGTH ?>" class="<?= invalid($errors, 'password') ?>" required>
                <?= field_error($errors, 'password') ?>
                <small class="hint" id="password-hint">At least <?= PASSWORD_MIN_LENGTH ?> characters.</small>
            </div>
            <div>
                <label for="confirm_password">Confirm password</label>
                <input id="confirm_password" type="password" name="confirm_password" autocomplete="new-password"
                    class="<?= invalid($errors, 'confirm_password') ?>" required>
                <?= field_error($errors, 'confirm_password') ?>
                <small class="hint" id="confirm-hint"></small>
            </div>
        </div>

        <div class="form-actions">
            <button class="btn" type="submit" data-busy="Creating account...">Create account</button>
            <span class="muted">Already registered? <a class="text-link" href="login.php">Log in</a></span>
        </div>
    </form>
</div>
<?php require "partials/footer.php"; ?>
