<?php
/**
 * Global utility functions
 */

/** Format a number as Indian Rupees */
function fmt_inr(float $amount): string {
    $negative = $amount < 0;
    $amount   = abs($amount);
    $formatted = '₹' . number_format($amount, 2);
    return $negative ? '−' . $formatted : $formatted;
}

/** Format a MySQL datetime string to a human-readable date */
function fmt_date(?string $dt, string $format = 'd M Y'): string {
    if (!$dt) return '—';
    return (new DateTime($dt))->format($format);
}

/** Format a MySQL datetime string to date + time */
function fmt_datetime(?string $dt): string {
    return fmt_date($dt, 'd M Y, h:i A');
}

/** Strip tags and trim input */
function sanitize(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/** Generate a 6-digit numeric OTP */
function generate_otp(): string {
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * "Send" OTP – in demo mode stores it in otp_log and puts it in session
 * so the UI can display it. Replace this with a real SMS gateway call.
 */
function send_otp(int $user_id, string $purpose = 'login'): string {
    $pdo  = get_db();
    $code = generate_otp();

    // Invalidate old OTPs
    $pdo->prepare("UPDATE otp_log SET used=1 WHERE user_id=? AND purpose=? AND used=0")
        ->execute([$user_id, $purpose]);

    // Insert new OTP (valid for 10 minutes)
    $pdo->prepare(
        "INSERT INTO otp_log (user_id, otp_code, purpose, expires_at)
         VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))"
    )->execute([$user_id, $code, $purpose]);

    // Demo: store in session so we can show it on screen
    $_SESSION['demo_otp'] = $code;

    return $code;
}

/**
 * Verify an OTP from otp_log (marks it used on success).
 * Returns true on success, false on failure/expiry.
 */
function verify_otp(int $user_id, string $code, string $purpose = 'login'): bool {
    $pdo = get_db();
    $row = $pdo->prepare(
        "SELECT otp_id FROM otp_log
         WHERE user_id=? AND otp_code=? AND purpose=? AND used=0 AND expires_at > NOW()
         LIMIT 1"
    );
    $row->execute([$user_id, $code, $purpose]);
    $r = $row->fetch();
    if (!$r) return false;

    $pdo->prepare("UPDATE otp_log SET used=1 WHERE otp_id=?")->execute([$r['otp_id']]);
    return true;
}

/** Write an audit log entry */
function audit(string $action, ?int $user_id = null): void {
    try {
        get_db()->prepare(
            "INSERT INTO audit_log (user_id, action, ip_address, user_agent) VALUES (?,?,?,?)"
        )->execute([
            $user_id,
            $action,
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 300),
        ]);
    } catch (Throwable) { /* non-fatal */ }
}

/** Count unread messages for a user (for badge in header) */
function unread_count(int $user_id): int {
    $st = get_db()->prepare(
        "SELECT COUNT(*) FROM messages WHERE user_id=? AND is_read=0"
    );
    $st->execute([$user_id]);
    return (int)$st->fetchColumn();
}

/** Redirect helper */
function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

/** Return CSS class for txn type */
function txn_badge(string $type): string {
    return $type === 'credit' ? 'badge-credit' : 'badge-debit';
}
