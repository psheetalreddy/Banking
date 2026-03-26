<?php
/**
 * auth/login.php
 * Step-1: email + password → Step-2: OTP (if enabled)
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';

// Already logged in → dashboard
if (!empty($_SESSION['user_id'])) {
    redirect('/Banking/dashboard.php');
}

$redirect = filter_input(INPUT_GET, 'redirect', FILTER_SANITIZE_URL)
          ?? filter_input(INPUT_POST, 'redirect', FILTER_SANITIZE_URL)
          ?? '/Banking/dashboard.php';

$error  = '';
$step   = 1;  // 1 = credentials, 2 = OTP

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';

    if ($action === 'login') {
        // ── Step 1: validate credentials ──────────────────
        $email    = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$email || !$password) {
            $error = 'Please enter your email and password.';
        } else {
            $pdo  = get_db();
            $stmt = $pdo->prepare(
                "SELECT u.user_id, u.password_hash, u.status, u.otp_enabled,
                        u.failed_attempts, u.locked_until,
                        CONCAT(c.first_name,' ',c.last_name) AS full_name
                 FROM   users u
                 JOIN   customers c USING(user_id)
                 WHERE  u.email = ? LIMIT 1"
            );
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                $error = 'Invalid email or password.';
            } elseif ($user['status'] === 'locked') {
                if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                    $error = '⚠️ Your account is temporarily locked. Try again after '
                           . fmt_datetime($user['locked_until']) . '.';
                } else {
                    // Unlock
                    $pdo->prepare("UPDATE users SET status='active', failed_attempts=0, locked_until=NULL WHERE user_id=?")
                        ->execute([$user['user_id']]);
                    $user['status'] = 'active';
                }
            }

            if (!$error && $user) {
                if (!password_verify($password, $user['password_hash'])) {
                    $attempts = $user['failed_attempts'] + 1;
                    if ($attempts >= 5) {
                        $pdo->prepare(
                            "UPDATE users SET failed_attempts=?, status='locked',
                             locked_until = DATE_ADD(NOW(), INTERVAL 30 MINUTE)
                             WHERE user_id=?"
                        )->execute([$attempts, $user['user_id']]);
                        $error = '⚠️ Too many failed attempts. Account locked for 30 minutes.';
                    } else {
                        $pdo->prepare("UPDATE users SET failed_attempts=? WHERE user_id=?")
                            ->execute([$attempts, $user['user_id']]);
                        $remaining = 5 - $attempts;
                        $error = "Invalid password. $remaining attempt(s) remaining.";
                    }
                } else {
                    // Credentials OK
                    $pdo->prepare("UPDATE users SET failed_attempts=0 WHERE user_id=?")
                        ->execute([$user['user_id']]);

                    if ($user['otp_enabled']) {
                        // Store pending user in session, go to OTP step
                        $_SESSION['pending_user_id']   = $user['user_id'];
                        $_SESSION['pending_user_name'] = $user['full_name'];
                        send_otp($user['user_id'], 'login');
                        audit('otp_sent', $user['user_id']);
                        redirect('/Banking/auth/otp_verify.php?redirect=' . urlencode($redirect));
                    } else {
                        // Login complete
                        session_regenerate_id(true);
                        $_SESSION['user_id']       = $user['user_id'];
                        $_SESSION['customer_name'] = explode(' ', $user['full_name'])[0];
                        $pdo->prepare("UPDATE users SET failed_attempts=0 WHERE user_id=?")->execute([$user['user_id']]);
                        audit('login_success', $user['user_id']);
                        set_flash('success', 'Welcome back, ' . explode(' ', $user['full_name'])[0] . '!');
                        redirect($redirect);
                    }
                }
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
  <title>Login | ArcaBank</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="/Banking/assets/css/style.css">
</head>
<body>

<div class="auth-wrapper">
  <div class="auth-box">

    <div class="auth-logo">
      <div class="brand-icon" style="margin:0 auto .75rem;width:56px;height:56px;font-size:1.6rem;border-radius:14px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#d4a843,#a07830);color:#0d1b2a;font-weight:700;">AB</div>
      <div class="auth-title">Welcome Back</div>
      <p class="auth-subtitle">Sign in to your ArcaBank account</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="" novalidate>
      <input type="hidden" name="action" value="login">
      <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

      <div class="form-group">
        <label class="form-label" for="email"><i class="bi bi-envelope"></i> Email Address / User ID</label>
        <input type="email" id="email" name="email" class="form-control"
               placeholder="you@example.com" required autocomplete="email"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>

      <div class="form-group">
        <label class="form-label" for="password"><i class="bi bi-lock"></i> Password</label>
        <div style="position:relative">
          <input type="password" id="password" name="password" class="form-control"
                 placeholder="Your password" required autocomplete="current-password"
                 style="padding-right:2.5rem">
          <button type="button" class="toggle-password"
                  data-target="password"
                  style="position:absolute;right:.65rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted)">
            <i class="bi bi-eye"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn btn-primary w-100 btn-lg mt-2" id="btn-login">
        <i class="bi bi-box-arrow-in-right"></i> Sign In
      </button>
    </form>

    <div class="text-center mt-2" style="font-size:.85rem;color:var(--text-muted)">
      <a href="/Banking/auth/forgot_password.php">Forgot Password?</a>
      &nbsp;·&nbsp;
      <a href="/Banking/auth/register.php">Register</a>
    </div>

    <!-- Demo hint
    <div class="alert alert-info mt-3" style="font-size:.8rem">
      <i class="bi bi-info-circle"></i>
      <div>
        <strong>Demo credentials:</strong><br>
        Email: <code>demo@bank.com</code><br>
        Password: <code>Demo@1234</code>
      </div>
    </div> -->

  </div>
</div>

<script src="/Banking/assets/js/main.js"></script>
</body>
</html>
