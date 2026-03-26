-- ============================================================
-- Online Banking Application – Seed Data
-- Run AFTER schema.sql
-- Demo credentials: demo@bank.com / Demo@1234
-- ============================================================

USE banking_db;

-- account_categories
INSERT INTO account_categories (name, icon, display_order) VALUES
('Savings',      'bi-piggy-bank',      1),
('Current',      'bi-briefcase',       2),
('Home Loan',    'bi-house-door',      3),
('Credit Card',  'bi-credit-card',     4),
('Investment',   'bi-graph-up-arrow',  5),
('Insurance',    'bi-shield-check',    6);

-- users  (password: Demo@1234)
INSERT INTO users (email, mobile, password_hash, otp_enabled, status) VALUES
('demo@bank.com', '9876543210',
 '$2y$12$e5LQ0z/J5vKLR1OkQWB1TOe6lsEpW4yTJpOCbO1XWaI0N9RIlR2Jy',
 1, 'active');

-- customers
INSERT INTO customers
  (user_id, first_name, last_name, dob, address_line1, address_line2, city, state, pincode, country)
VALUES
  (1, 'Pradeep', 'Kumar', '1985-06-15',
   '42, MG Road', 'Indiranagar', 'Bengaluru', 'Karnataka', '560038', 'India');

-- accounts
INSERT INTO accounts
  (user_id, category_id, account_number, account_name, balance, credit_limit, due_date, last_payment, last_payment_date, next_payment)
VALUES
  -- Savings
  (1, 1, 'SAV0001234567', 'Primary Savings Account',    285420.50, NULL, NULL, NULL, NULL, NULL),
  -- Current
  (1, 2, 'CUR0009876543', 'Business Current Account',   142000.00, NULL, NULL, NULL, NULL, NULL),
  -- Home Loan
  (1, 3, 'HL00056789012', 'Home Loan - MG Road',       -2450000.00, NULL, '2026-04-05', 28500.00, '2026-03-05', 28500.00),
  -- Credit Card
  (1, 4, 'CC0004321098',  'Platinum Credit Card',       -34560.00, 300000.00, '2026-03-25', 10000.00, '2026-03-01', 34560.00),
  -- Investment
  (1, 5, 'INV0007654321', 'Mutual Fund Portfolio',      520000.00, NULL, NULL, NULL, NULL, NULL),
  -- Insurance
  (1, 6, 'INS0001122334', 'Life Insurance Policy',      100000.00, NULL, NULL, NULL, NULL, NULL);

-- transactions (account 1 = savings, account 4 = credit card)
INSERT INTO transactions (account_id, txn_type, amount, balance_after, description, reference, txn_date) VALUES
  (1, 'credit', 50000.00,  285420.50, 'Salary Credit - March 2026',       'SAL202603', '2026-03-01 09:00:00'),
  (1, 'debit',   5000.00,  235420.50, 'UPI Transfer to Rahul Sharma',      'UPI20260301A', '2026-03-02 11:30:00'),
  (1, 'debit',   2800.00,  232620.50, 'Electricity Bill - BESCOM',         'BILL20260303', '2026-03-03 14:15:00'),
  (1, 'credit',  8000.00,  240620.50, 'Freelance Payment Received',        'NEFT20260305', '2026-03-05 16:45:00'),
  (1, 'debit',  10000.00,  230620.50, 'CC Bill Payment',                   'CCPAY20260306', '2026-03-06 10:00:00'),
  (1, 'debit',   1500.00,  229120.50, 'Amazon Purchase',                   'UPI20260308B', '2026-03-08 19:20:00'),
  (1, 'credit', 15000.00,  244120.50, 'Rental Income',                     'NEFT20260309', '2026-03-09 08:00:00'),
  (1, 'debit',  28500.00,  215620.50, 'Home Loan EMI',                     'EMI20260310',  '2026-03-10 07:00:00'),
  (1, 'debit',   3200.00,  212420.50, 'Grocery - BigBasket',               'UPI20260311C', '2026-03-11 20:00:00'),
  (1, 'credit', 73000.00,  285420.50, 'FD Maturity Credit',                'FD20260312',   '2026-03-12 12:00:00'),

  (4, 'debit',  12000.00,  -34560.00, 'Flipkart - Mobile Purchase',        'CC20260301X', '2026-03-01 13:00:00'),
  (4, 'debit',   5460.00,  -22560.00, 'Swiggy Orders - Feb Charges',       'CC20260215Y', '2026-02-28 23:59:00'),
  (4, 'debit',   7100.00,  -17100.00, 'MakeMyTrip Flight Booking',         'CC20260220Z', '2026-02-20 10:30:00'),
  (4, 'credit', 10000.00,  -27100.00, 'CC Payment Received',               'CCPAY20260306', '2026-03-06 10:00:00'),
  (4, 'debit',  10000.00,  -44560.00, 'Croma - Electronics',               'CC20260307A', '2026-03-07 17:00:00');

-- messages
INSERT INTO messages (user_id, direction, category, subject, body, is_read) VALUES
  (1, 'inbox', 'alert',
   'Your salary has been credited',
   'Dear Pradeep Kumar, your savings account SAV0001234567 has been credited with ₹50,000 on 01-Mar-2026. Your available balance is ₹2,85,420.50.',
   0),
  (1, 'inbox', 'alert',
   'Credit Card Payment Due',
   'Dear Pradeep Kumar, your Platinum Credit Card payment of ₹34,560 is due on 25-Mar-2026. Please ensure timely payment to avoid late fees.',
   0),
  (1, 'inbox', 'alert',
   'Home Loan EMI Processed',
   'Your Home Loan EMI of ₹28,500 has been debited from your Primary Savings Account on 10-Mar-2026.',
   1),
  (1, 'sent', 'complaint',
   'Unrecognised transaction on Credit Card',
   'I noticed a transaction of ₹5,460 from Swiggy on 28-Feb-2026 which I did not authorise. Kindly investigate and revert.',
   1);

-- payees
INSERT INTO payees (user_id, payee_name, bank_name, branch_name, account_number, ifsc_code) VALUES
  (1, 'Rahul Sharma',  'HDFC Bank',   'Koramangala, Bengaluru', '50100123456789', 'HDFC0001234'),
  (1, 'Anita Mehta',   'ICICI Bank',  'Jayanagar, Bengaluru',   '123456789012',   'ICIC0005678');
