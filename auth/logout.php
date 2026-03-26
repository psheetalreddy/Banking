<?php
/**
 * auth/logout.php
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';

$uid = current_user_id();
audit('logout', $uid);
logout();
set_flash('success', 'You have been logged out successfully.');
redirect('/Banking/auth/login.php');
