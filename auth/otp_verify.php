<?php
/**
 * auth/otp_verify.php
 * Verify 6-digit OTP issued during login.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';

if (!empty($_SESSION['user_id'])) redirect('/Banking/dashboard.php');
if (empty($_SESSION['pending_user_id'])) redirect('/Banking/auth/login.php');

$redirect   = filter_input(INPUT_GET, 'redirect', FILTER_SANITIZE_URL)
            ?? filter_input(INPUT_POST, 'redirect', FILTER_SANITIZE_URL)
            ?? '/Banking/dashboard.php';
$pending_id = (int)$_SESSION['pending_user_id'];
$demo_otp   = $_SESSION['demo_otp'] ?? '';
$error      = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = preg_replace('/\D/', '', $_POST['otp_code'] ?? '');

    if (strlen($code) !== 6) {
        $error = 'Please enter the complete 6-digit OTP.';
    } elseif (verify_otp($pending_id, $code, 'login')) {
        // ── OTP correct ──
        session_regenerate_id(true);
        $_SESSION['user_id']       = $pending_id;
        $_SESSION['customer_name'] = explode(' ', $_SESSION['pending_user_name'])[0];
        unset($_SESSION['pending_user_id'], $_SESSION['pending_user_name'], $_SESSION['demo_otp']);

        // Reset failed attempts
        get_db()->prepare("UPDATE users SET failed_attempts=0 WHERE user_id=?")->execute([$pending_id]);
        audit('login_success_otp', $pending_id);
        set_flash('success', 'Login successful – welcome back!');
        redirect($redirect);
    } else {
        $error = 'Invalid or expired OTP. Please try again or request a new one.';
        audit('otp_failed', $pending_id);
    }
}

// Resend OTP
if (isset($_GET['resend'])) {
    send_otp($pending_id, 'login');
    $demo_otp = $_SESSION['demo_otp'];
    set_flash('info', 'A new OTP has been sent.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>OTP Verification | ArcaBank</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="/Banking/assets/css/style.css">
</head>
<body>

<div class="auth-wrapper">
  <div class="auth-box">

    <div class="auth-logo">
      <div class="brand-icon" style="margin:0 auto .75rem;width:56px;height:56px;font-size:1.6rem;border-radius:14px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#d4a843,#a07830);color:#0d1b2a;font-weight:700;">
        <i class="bi bi-shield-lock" style="font-size:1.6rem"></i>
      </div>
      <div class="auth-title">OTP Verification</div>
      <p class="auth-subtitle">Enter the 6-digit code sent to your registered mobile/email</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Demo hint -->
    <?php if ($demo_otp): ?>
    <div class="alert alert-info" style="font-size:.82rem">
      <i class="bi bi-phone"></i>
      <div><strong>Demo OTP (shown for testing):</strong><br>
        <span style="font-size:1.4rem;font-weight:700;letter-spacing:.15em;color:var(--gold)"><?= $demo_otp ?></span>
      </div>
    </div>
    <?php endif; ?>

    <form method="POST" id="otp-form">
      <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
      <input type="hidden" name="otp_code" id="otp_code" value="">

      <div class="otp-group">
        <?php for($i=0;$i<6;$i++): ?>
          <input type="text" class="otp-input" maxlength="1" pattern="\d"
                 inputmode="numeric" autocomplete="one-time-code"
                 <?= $i===0 ? 'autofocus' : '' ?>>
        <?php endfor; ?>
      </div>

      <button type="submit" class="btn btn-primary w-100 btn-lg mt-2" id="btn-verify">
        <i class="bi bi-check-circle"></i> Verify OTP
      </button>
    </form>

    <div class="text-center mt-2" style="font-size:.84rem;color:var(--text-muted)">
      Didn't receive it?
      <a href="?resend=1&redirect=<?= urlencode($redirect) ?>">Resend OTP</a>
      &nbsp;·&nbsp;
      <a href="/Banking/auth/login.php">Back to Login</a>
    </div>

  </div>
</div>

<script src="/Banking/assets/js/main.js"></script>
</body>
</html>
