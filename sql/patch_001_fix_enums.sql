-- ============================================================
-- patch_001_fix_enums.sql
-- Fixes ENUM constraint crashes in otp_log and adds missing
-- 'email_verification' purpose used in auth/register.php.
-- Run this ONCE against banking_db.
-- ============================================================

USE banking_db;

-- Fix otp_log.purpose ENUM to include all purposes used in the app
ALTER TABLE otp_log
  MODIFY COLUMN purpose
    ENUM('login','reset','email_verification','payment')
    NOT NULL DEFAULT 'login';

-- ============================================================
-- Verify (optional – remove before production)
-- ============================================================
-- SELECT COLUMN_TYPE FROM information_schema.COLUMNS
--   WHERE TABLE_SCHEMA='banking_db'
--     AND TABLE_NAME='otp_log'
--     AND COLUMN_NAME='purpose';
