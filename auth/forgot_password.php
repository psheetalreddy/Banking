<?php
/**
 * auth/forgot_password.php
 * OTP-based password reset.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';

if (!empty($_SESSION['user_id'])) redirect('/Banking/dashboard.php');

$step    = $_SESSION['reset_step'] ?? 1;
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'request_otp') {
        // ── Step 1: find user by email ──
        $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
        if (!$email) { $error = 'Enter a valid email address.'; }
        else {
            $pdo  = get_db();
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email=? AND status='active' LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            if ($user) {
                send_otp($user['user_id'], 'reset');
                $_SESSION['reset_user_id'] = $user['user_id'];
                $_SESSION['reset_step']    = 2;
                $_SESSION['reset_email']   = $email;
            }
            // Same message whether found or not (anti-enumeration)
            $success = 'If that email exists in our system, an OTP has been sent.';
            $step = 2;
        }
    } elseif ($action === 'verify_and_reset') {
        // ── Step 2: verify OTP + set new password ──
        $pending_id = (int)($_SESSION['reset_user_id'] ?? 0);
        $code       = preg_replace('/\D/', '', $_POST['otp_code'] ?? '');
        $password   = $_POST['password'] ?? '';
        $confirm    = $_POST['confirm_password'] ?? '';

        if (!$pending_id) { redirect('/Banking/auth/forgot_password.php'); }

        if (!preg_match('/^(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&]).{8,}$/', $password)) {
            $error = 'Password must be 8+ chars with uppercase, number & special char.';
            $step  = 2;
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
            $step  = 2;
        } elseif (!verify_otp($pending_id, $code, 'reset')) {
            $error = 'Invalid or expired OTP.';
            $step  = 2;
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            get_db()->prepare("UPDATE users SET password_hash=? WHERE user_id=?")
                    ->execute([$hash, $pending_id]);
            unset($_SESSION['reset_step'], $_SESSION['reset_user_id'], $_SESSION['reset_email'], $_SESSION['demo_otp']);
            set_flash('success', 'Password reset successfully. Please log in.');
            redirect('/Banking/auth/login.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Forgot Password | ArcaBank</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="/Banking/assets/css/style.css">
</head>
<body>

<div class="auth-wrapper">
  <div class="auth-box">

    <div class="auth-logo">
      <div class="brand-icon" style="margin:0 auto .75rem;width:56px;height:56px;font-size:1.6rem;border-radius:14px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#d4a843,#a07830);color:#0d1b2a;font-weight:700;">
        <i class="bi bi-key"></i>
      </div>
      <div class="auth-title">Reset Password</div>
      <p class="auth-subtitle">
        <?= $step === 1 ? 'Enter your registered email to receive an OTP' : 'Enter the OTP and your new password' ?>
      </p>
    </div>

    <?php if ($error):   ?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>

    <?php if ($step === 1): ?>
    <form method="POST">
      <input type="hidden" name="action" value="request_otp">
      <div class="form-group">
        <label class="form-label">Email Address</label>
        <input type="email" name="email" class="form-control" required placeholder="you@example.com">
      </div>
      <button type="submit" class="btn btn-primary w-100 btn-lg">
        <i class="bi bi-send"></i> Send OTP
      </button>
    </form>

    <?php else: ?>
    <?php $demo_otp = $_SESSION['demo_otp'] ?? ''; ?>
    <?php if ($demo_otp): ?>
    <div class="alert alert-info" style="font-size:.82rem">
      <i class="bi bi-phone"></i>
      <div><strong>Demo OTP:</strong>
        <span style="font-size:1.4rem;font-weight:700;letter-spacing:.15em;color:var(--gold)"><?= $demo_otp ?></span>
      </div>
    </div>
    <?php endif; ?>
    <form method="POST">
      <input type="hidden" name="action" value="verify_and_reset">

      <div class="form-group">
        <label class="form-label">Enter OTP</label>
        <div class="otp-group">
          <?php for($i=0;$i<6;$i++): ?>
            <input type="text" class="otp-input" maxlength="1" pattern="\d" inputmode="numeric" <?= $i===0?'autofocus':'' ?>>
          <?php endfor; ?>
        </div>
        <input type="hidden" name="otp_code" id="otp_code" value="">
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">New Password</label>
          <input type="password" name="password" class="form-control" required placeholder="New password">
        </div>
        <div class="form-group">
          <label class="form-label">Confirm Password</label>
          <input type="password" name="confirm_password" class="form-control" required placeholder="Repeat password">
        </div>
      </div>

      <button type="submit" class="btn btn-primary w-100 btn-lg">
        <i class="bi bi-check-circle"></i> Reset Password
      </button>
    </form>
    <?php endif; ?>

    <div class="text-center mt-2" style="font-size:.85rem">
      <a href="/Banking/auth/login.php">Back to Login</a>
    </div>

  </div>
</div>

<script src="/Banking/assets/js/main.js"></script>
</body>
</html>
