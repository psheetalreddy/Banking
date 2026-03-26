<?php
/**
 * layout/header.php
 * Include at the top of every page AFTER requiring config files.
 * Variables expected in scope:
 *   $page_title (string)  – shown in <title>
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';

$logged_in   = !empty($_SESSION['user_id']);
$user_id     = $logged_in ? (int)$_SESSION['user_id'] : 0;
$unread      = $logged_in ? unread_count($user_id) : 0;
$flash       = get_flash();
$page_title  = $page_title ?? 'ArcaBank';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="ArcaBank – Secure Online Banking Portal">
  <title><?= htmlspecialchars($page_title) ?> | ArcaBank</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="/Banking/assets/css/style.css">
</head>
<body>

<header class="site-header">
  <a href="/Banking/dashboard.php" class="brand">
    <div class="brand-icon">AB</div>
    <div>
      <div class="brand-name">Arca<span>Bank</span></div>
      <div class="brand-tagline">Your Trusted Digital Bank</div>
    </div>
  </a>

  <nav class="nav-actions">
    <?php if ($logged_in): ?>

      <!-- Alerts / Messages -->
      <a href="/Banking/messages/list.php"
         class="nav-btn" data-path="/Banking/messages" id="nav-alerts">
        <i class="bi bi-bell"></i>
        <span>Alerts</span>
        <?php if ($unread > 0): ?>
          <span class="badge"><?= $unread ?></span>
        <?php endif; ?>
      </a>

      <!-- Account Management -->
      <a href="/Banking/accounts/index.php"
         class="nav-btn" data-path="/Banking/accounts" id="nav-accounts">
        <i class="bi bi-bank2"></i>
        <span>Accounts</span>
      </a>

      <!-- Profile -->
      <a href="/Banking/profile/view.php"
         class="nav-btn" data-path="/Banking/profile" id="nav-profile">
        <i class="bi bi-person-circle"></i>
        <span><?= htmlspecialchars(current_user_name()) ?></span>
      </a>

      <!-- Logout -->
      <a href="/Banking/auth/logout.php" class="nav-btn" id="nav-logout">
        <i class="bi bi-box-arrow-right"></i>
        <span>Logout</span>
      </a>

    <?php else: ?>

      <a href="/Banking/auth/login.php" class="nav-btn btn-login" id="nav-login">
        <i class="bi bi-lock"></i>
        <span>Login</span>
      </a>
      <a href="/Banking/auth/register.php" class="nav-btn btn-outline" id="nav-register">
        <i class="bi bi-person-plus"></i>
        <span>Register</span>
      </a>

    <?php endif; ?>
  </nav>
</header>

<?php if (!empty($flash)): ?>
<div style="padding:.5rem 1.5rem 0;">
  <?php foreach ($flash as $type => $msg): ?>
    <div class="alert alert-<?= htmlspecialchars($type) ?>" data-autohide>
      <i class="bi bi-<?= $type === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
      <?= htmlspecialchars($msg) ?>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
