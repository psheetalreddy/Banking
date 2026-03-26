-- ============================================================
-- Online Banking Application – Database Schema
-- Database: banking_db
-- ============================================================

CREATE DATABASE IF NOT EXISTS banking_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE banking_db;

-- ------------------------------------------------------------
-- 1. users  (login credentials)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  user_id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email          VARCHAR(120) NOT NULL UNIQUE,
  mobile         VARCHAR(20)  NOT NULL,
  password_hash  VARCHAR(255) NOT NULL,
  otp_enabled    TINYINT(1)   NOT NULL DEFAULT 1,
  status         ENUM('active','locked','pending') NOT NULL DEFAULT 'pending',
  failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until   DATETIME     DEFAULT NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 2. customers  (personal / KYC info)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS customers (
  customer_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id        INT UNSIGNED NOT NULL UNIQUE,
  first_name     VARCHAR(80)  NOT NULL,
  last_name      VARCHAR(80)  NOT NULL,
  dob            DATE         DEFAULT NULL,
  address_line1  VARCHAR(160) DEFAULT NULL,
  address_line2  VARCHAR(160) DEFAULT NULL,
  city           VARCHAR(80)  DEFAULT NULL,
  state          VARCHAR(80)  DEFAULT NULL,
  pincode        VARCHAR(12)  DEFAULT NULL,
  country        VARCHAR(60)  NOT NULL DEFAULT 'India',
  preferences    JSON         DEFAULT NULL,
  -- KYC pending flags
  name_pending_kyc    TINYINT(1) NOT NULL DEFAULT 0,
  address_pending_kyc TINYINT(1) NOT NULL DEFAULT 0,
  dob_pending_kyc     TINYINT(1) NOT NULL DEFAULT 0,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_cust_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 3. kyc_documents
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS kyc_documents (
  doc_id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id    INT UNSIGNED NOT NULL,
  doc_type       ENUM('id_proof','address_proof','other') NOT NULL,
  file_name      VARCHAR(255) NOT NULL,
  file_path      VARCHAR(500) NOT NULL,
  for_field      VARCHAR(60)  DEFAULT NULL,   -- 'name','dob','address'
  status         ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  uploaded_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at    DATETIME     DEFAULT NULL,
  CONSTRAINT fk_doc_cust FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 4. account_categories  (lookup)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS account_categories (
  category_id    TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name           VARCHAR(60) NOT NULL UNIQUE,
  icon           VARCHAR(60) DEFAULT 'bi-bank',
  display_order  TINYINT UNSIGNED NOT NULL DEFAULT 99
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 5. accounts
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS accounts (
  account_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id        INT UNSIGNED NOT NULL,
  category_id    TINYINT UNSIGNED NOT NULL,
  account_number VARCHAR(30) NOT NULL UNIQUE,
  account_name   VARCHAR(120) NOT NULL,
  currency       CHAR(3)      NOT NULL DEFAULT 'INR',
  balance        DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  credit_limit   DECIMAL(15,2) DEFAULT NULL,   -- for CC
  due_date       DATE         DEFAULT NULL,     -- for CC/HL
  last_payment   DECIMAL(15,2) DEFAULT NULL,
  last_payment_date DATE       DEFAULT NULL,
  next_payment   DECIMAL(15,2) DEFAULT NULL,
  status         ENUM('active','inactive','closed') NOT NULL DEFAULT 'active',
  linked_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_acc_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  CONSTRAINT fk_acc_cat  FOREIGN KEY (category_id) REFERENCES account_categories(category_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 6. transactions
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS transactions (
  txn_id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  account_id     INT UNSIGNED NOT NULL,
  txn_type       ENUM('credit','debit') NOT NULL,
  amount         DECIMAL(15,2) NOT NULL,
  balance_after  DECIMAL(15,2) NOT NULL,
  description    VARCHAR(255)  NOT NULL,
  reference      VARCHAR(60)   DEFAULT NULL,
  txn_date       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  disputed       TINYINT(1)    NOT NULL DEFAULT 0,
  dispute_reason VARCHAR(500)  DEFAULT NULL,
  CONSTRAINT fk_txn_acc FOREIGN KEY (account_id) REFERENCES accounts(account_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 7. messages  (alerts / complaints / queries)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS messages (
  message_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id        INT UNSIGNED NOT NULL,
  direction      ENUM('inbox','sent') NOT NULL DEFAULT 'inbox',
  category       ENUM('alert','complaint','query','feedback','reply') NOT NULL DEFAULT 'query',
  subject        VARCHAR(200) NOT NULL,
  body           TEXT         NOT NULL,
  is_read        TINYINT(1)   NOT NULL DEFAULT 0,
  parent_id      INT UNSIGNED DEFAULT NULL,    -- thread support
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_msg_user   FOREIGN KEY (user_id)   REFERENCES users(user_id) ON DELETE CASCADE,
  CONSTRAINT fk_msg_parent FOREIGN KEY (parent_id) REFERENCES messages(message_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 8. payees  (fund transfer beneficiaries)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS payees (
  payee_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id        INT UNSIGNED NOT NULL,
  payee_name     VARCHAR(120) NOT NULL,
  bank_name      VARCHAR(120) NOT NULL,
  branch_name    VARCHAR(120) DEFAULT NULL,
  account_number VARCHAR(30)  NOT NULL,
  ifsc_code      VARCHAR(20)  NOT NULL,
  is_active      TINYINT(1)   NOT NULL DEFAULT 1,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_payee_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 9. standing_instructions
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS standing_instructions (
  si_id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id        INT UNSIGNED NOT NULL,
  from_account_id INT UNSIGNED NOT NULL,
  payee_id       INT UNSIGNED NOT NULL,
  amount         DECIMAL(15,2) NOT NULL,
  periodicity    ENUM('daily','weekly','monthly','yearly') NOT NULL DEFAULT 'monthly',
  total_instances SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  executed_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  next_run_date  DATE         NOT NULL,
  status         ENUM('active','paused','completed','cancelled') NOT NULL DEFAULT 'active',
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_si_user    FOREIGN KEY (user_id)          REFERENCES users(user_id) ON DELETE CASCADE,
  CONSTRAINT fk_si_acc     FOREIGN KEY (from_account_id)  REFERENCES accounts(account_id),
  CONSTRAINT fk_si_payee   FOREIGN KEY (payee_id)         REFERENCES payees(payee_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 10. payments  (CC / home-loan)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS payments (
  payment_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         INT UNSIGNED NOT NULL,
  from_account_id INT UNSIGNED NOT NULL,
  to_account_id   INT UNSIGNED NOT NULL,
  amount          DECIMAL(15,2) NOT NULL,
  payment_date    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status          ENUM('success','failed','pending') NOT NULL DEFAULT 'success',
  CONSTRAINT fk_pay_user   FOREIGN KEY (user_id)          REFERENCES users(user_id) ON DELETE CASCADE,
  CONSTRAINT fk_pay_from   FOREIGN KEY (from_account_id)  REFERENCES accounts(account_id),
  CONSTRAINT fk_pay_to     FOREIGN KEY (to_account_id)    REFERENCES accounts(account_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 11. otp_log
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS otp_log (
  otp_id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id        INT UNSIGNED NOT NULL,
  otp_code       CHAR(6)      NOT NULL,
  purpose        ENUM('login','reset') NOT NULL DEFAULT 'login',
  expires_at     DATETIME     NOT NULL,
  used           TINYINT(1)   NOT NULL DEFAULT 0,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_otp_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 12. audit_log
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_log (
  log_id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id        INT UNSIGNED DEFAULT NULL,
  action         VARCHAR(120) NOT NULL,
  ip_address     VARCHAR(45)  DEFAULT NULL,
  user_agent     VARCHAR(300) DEFAULT NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
