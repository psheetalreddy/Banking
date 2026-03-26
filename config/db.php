<?php
/**
 * Database connection – PDO singleton
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'banking_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die('<div style="font-family:sans-serif;padding:2rem;color:#c00">
                    <h2>Database Error</h2><p>' . htmlspecialchars($e->getMessage()) . '</p>
                 </div>');
        }
    }
    return $pdo;
}
