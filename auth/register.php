<?php
/**
 * auth/register.php
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';

if (!empty($_SESSION['user_id'])) redirect('/Banking/dashboard.php');

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first    = sanitize($_POST['first_name'] ?? '');
    $last     = sanitize($_POST['last_name']  ?? '');
    $email    = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $mobile   = preg_replace('/\D/', '', $_POST['mobile'] ?? '');
    $dob      = $_POST['dob'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (!$first || !$last || !$email || !$mobile || !$dob || !$password) {
        $error = 'All fields are required.';
    } elseif (strlen($mobile) < 10) {
        $error = 'Enter a valid 10-digit mobile number.';
    } elseif (!preg_match('/^(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&]).{8,}$/', $password)) {
        $error = 'Password must be at least 8 characters with 1 uppercase, 1 number, and 1 special character.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $pdo = get_db();
        $dup = $pdo->prepare("SELECT user_id FROM users WHERE email=? LIMIT 1");
        $dup->execute([$email]);
        if ($dup->fetch()) {
            $error = 'An account with that email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->beginTransaction();
            try {
                $pdo->prepare(
                    "INSERT INTO users (email, mobile, password_hash, otp_enabled, status)
                     VALUES (?, ?, ?, 1, 'active')"
                )->execute([$email, $mobile, $hash]);
                $uid = (int)$pdo->lastInsertId();

                $pdo->prepare(
                    "INSERT INTO customers (user_id, first_name, last_name, dob)
                     VALUES (?, ?, ?, ?)"
                )->execute([$uid, $first, $last, $dob]);

                $pdo->commit();
                audit('register', $uid);
                
                // Send verification email
                send_otp($uid, 'email_verification');
                
                set_flash('success', 'Account created! A verification email has been sent.');
                redirect('/Banking/auth/login.php');
            } catch (Throwable $e) {
                $pdo->rollBack();
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Register | ArcaBank</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="/Banking/assets/css/style.css">
</head>
<body>

<div class="auth-wrapper">
  <div class="auth-box" style="max-width:500px">

    <div class="auth-logo">
      <div class="brand-icon" style="margin:0 auto .75rem;width:56px;height:56px;font-size:1.6rem;border-radius:14px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#d4a843,#a07830);color:#0d1b2a;font-weight:700;">AB</div>
      <div class="auth-title">Create Account</div>
      <p class="auth-subtitle">Join ArcaBank – Banking made simple</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" novalidate>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">First Name</label>
          <input type="text" name="first_name" class="form-control" required
                 value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" placeholder="John">
        </div>
        <div class="form-group">
          <label class="form-label">Last Name</label>
          <input type="text" name="last_name" class="form-control" required
                 value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" placeholder="Doe">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Email Address</label>
        <input type="email" name="email" class="form-control" required
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="you@example.com">
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Mobile Number</label>
          <input type="tel" name="mobile" class="form-control" required
                 value="<?= htmlspecialchars($_POST['mobile'] ?? '') ?>" placeholder="9876543210">
        </div>
        <div class="form-group">
          <label class="form-label">Date of Birth</label>
          <input type="date" name="dob" class="form-control" required
                 value="<?= htmlspecialchars($_POST['dob'] ?? '') ?>">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Password</label>
          <input type="password" name="password" id="password" class="form-control" required placeholder="Min 8 chars">
          <div class="form-hint">8+ chars, uppercase, number, special char</div>
        </div>
        <div class="form-group">
          <label class="form-label">Confirm Password</label>
          <input type="password" name="confirm_password" class="form-control" required placeholder="Repeat password">
        </div>
      </div>

      <button type="submit" class="btn btn-primary w-100 btn-lg mt-2">
        <i class="bi bi-person-plus"></i> Create Account
      </button>
    </form>

    <div class="text-center mt-2" style="font-size:.85rem;color:var(--text-muted)">
      Already have an account? <a href="/Banking/auth/login.php">Sign In</a>
    </div>

  </div>
</div>

<script src="/Banking/assets/js/main.js"></script>
</body>
</html>
