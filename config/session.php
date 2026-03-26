<?php
/**
 * Session bootstrap + auth helpers
 */
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,   // set true for HTTPS
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/**
 * Ensure the user is authenticated.
 * If not, redirect to login with a return URL so they land back after login.
 */
function require_login(): void {
    if (empty($_SESSION['user_id'])) {
        $return = urlencode($_SERVER['REQUEST_URI'] ?? '/Banking/dashboard.php');
        header('Location: /Banking/auth/login.php?redirect=' . $return);
        exit;
    }
}

/** Return the currently logged-in customer full name (cached in session). */
function current_user_name(): string {
    return $_SESSION['customer_name'] ?? 'Customer';
}

/** Return the currently logged-in user_id or null. */
function current_user_id(): ?int {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

/** Destroy the session and log the user out. */
function logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Flash message helpers */
function set_flash(string $type, string $msg): void {
    $_SESSION['flash'][$type] = $msg;
}

function get_flash(): array {
    $flash = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flash;
}
