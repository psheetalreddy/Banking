<?php
/** index.php – redirect to dashboard or login */
require_once __DIR__ . '/config/session.php';
if (!empty($_SESSION['user_id'])) {
    header('Location: /Banking/dashboard.php');
} else {
    header('Location: /Banking/auth/login.php');
}
exit;
