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
 * Send OTP via email using PHPMailer and SMTP
 * Requires: composer require phpmailer/phpmailer
 * Configuration: check config/mail.php
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

    // Get user email
    $stmt = $pdo->prepare("SELECT u.email, c.first_name FROM users u JOIN customers c USING(user_id) WHERE u.user_id=?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if ($user && $user['email']) {
        try {
            $mailConfig = require __DIR__ . '/mail.php';
            $smtpConfig = $mailConfig['smtp'];
            $otpConfig  = $mailConfig['otp'];

            // PHPMailer
            $mail = new \PHPMailer\PHPMailer\PHPMailer();
            $mail->isSMTP();
            $mail->Host       = $smtpConfig['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtpConfig['username'];
            $mail->Password   = $smtpConfig['password'];
            $mail->SMTPSecure = $smtpConfig['encryption'];
            $mail->Port       = $smtpConfig['port'];

            $mail->setFrom($smtpConfig['from_email'], $smtpConfig['from_name']);
            $mail->addAddress($user['email'], $user['first_name']);
            $mail->Subject = $otpConfig['subject'];

            // HTML email body
            $mail->isHTML(true);
            $mail->Body = "
                <div style='font-family: Arial, sans-serif; max-width: 500px; margin: 0 auto;'>
                    <h2>ArcaBank OTP Verification</h2>
                    <p>Hi {$user['first_name']},</p>
                    <p>Your one-time password (OTP) for {$purpose} is:</p>
                    <div style='background: #f5f5f5; padding: 20px; text-align: center; border-radius: 8px; margin: 20px 0;'>
                        <h1 style='font-size: 32px; letter-spacing: 5px; color: #d4a843; margin: 0;'>{$code}</h1>
                    </div>
                    <p style='color: #666;'>This OTP is valid for " . $otpConfig['expiry_minutes'] . " minutes.</p>
                    <p style='color: #666;'><strong>Do not share this code with anyone.</strong></p>
                    <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
                    <p style='color: #999; font-size: 12px;'>If you didn't request this, please ignore this email.</p>
                </div>
            ";

            $mail->AltBody = "Your OTP is: {$code}. Valid for {$otpConfig['expiry_minutes']} minutes. Do not share this code.";

            if (!$mail->send()) {
                error_log("OTP email failed for user {$user_id}: " . $mail->ErrorInfo);
            }
        } catch (Throwable $e) {
            error_log("OTP send error for user {$user_id}: " . $e->getMessage());
        }
    }

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
