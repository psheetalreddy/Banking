# Online Banking Application – Implementation Plan (PHP / MySQL)

This plan covers building a complete online banking portal, delivered in two sprints:
- **Sprint-1**: Display, navigation, login/auth, messages, account management, profile
- **Sprint-2**: Transaction history, fund transfer (payees, standing instructions), payments (CC/HL)

---

## User Review Required

> [!IMPORTANT]
> **OTP Strategy**: For Sprint-1 the OTP will be simulated — a 6-digit code stored in the DB and shown as an on-screen "SMS" for demo purposes. A real SMS gateway (e.g. Twilio) can be wired in later.

> [!IMPORTANT]
> **KYC Documents**: Uploaded files (ID proof, address proof) will be stored in `uploads/kyc/` on the server. Fields awaiting KYC approval will be flagged `pending_kyc` in the DB and shown with a badge on the profile page.

> [!NOTE]
> Sprint-2 fund transfers and payments operate on **demo data inside the DB only** — no real banking network integration.

---

## Proposed Folder Structure

```
Banking/
├── config/
│   ├── db.php              # PDO connection singleton
│   ├── session.php         # session_start + auth guard helpers
│   └── functions.php       # shared utilities (format currency, dates…)
├── auth/
│   ├── login.php
│   ├── register.php
│   ├── otp_verify.php
│   ├── forgot_password.php
│   └── logout.php
├── layout/
│   ├── header.php          # Top nav bar with 4 action buttons
│   └── footer.php
├── assets/
│   ├── css/style.css
│   └── js/main.js
├── dashboard.php           # Landing page after login
├── messages/
│   ├── list.php
│   ├── view.php
│   └── compose.php
├── accounts/
│   ├── index.php           # Category accordion view
│   └── detail.php          # "Under Construction" placeholder
├── profile/
│   ├── view.php
│   ├── edit.php
│   └── upload_handler.php
├── transactions/
│   └── history.php         # Sprint-2
├── transfers/
│   ├── index.php           # Sprint-2
│   ├── add_payee.php       # Sprint-2
│   └── confirm.php         # Sprint-2
├── payments/
│   ├── index.php           # Sprint-2
│   └── confirm.php         # Sprint-2
├── uploads/
│   └── kyc/                # KYC document uploads
├── sql/
│   ├── schema.sql          # Full DB schema
│   └── seed.sql            # Demo data
└── index.php               # Redirect to dashboard or login
```

---

## Database Schema (12 tables)

| Table | Purpose |
|---|---|
| `users` | Login credentials, OTP, status |
| `customers` | Personal info (name, DOB, address) |
| `kyc_documents` | Uploaded proof docs, pending/approved status |
| `account_categories` | Savings, Home Loan, Investment, Insurance, Credit Card |
| `accounts` | Individual accounts linked to customers |
| `transactions` | All debit/credit entries |
| `messages` | Alerts, complaints, bank replies |
| `payees` | Fund transfer beneficiaries |
| `standing_instructions` | Recurring transfer schedules |
| `payments` | CC/HL payment records |
| `otp_log` | OTP codes with expiry |
| `audit_log` | Login/action audit trail |

---

## Proposed Changes

### Infrastructure & Config

#### [NEW] [db.php](file:///c:/xampp/htdocs/Banking/config/db.php)
PDO singleton connecting to `banking_db` MySQL database.

#### [NEW] [session.php](file:///c:/xampp/htdocs/Banking/config/session.php)
`require_login()` guard that redirects unauthenticated users to `auth/login.php` with a `redirect` query param, so on success they return to the intended page.

#### [NEW] [functions.php](file:///c:/xampp/htdocs/Banking/config/functions.php)
Helpers: `format_currency()`, `format_date()`, `sanitize()`, `generate_otp()`, `send_otp()` (demo).

#### [NEW] [schema.sql](file:///c:/xampp/htdocs/Banking/sql/schema.sql)
Full DDL for all 12 tables.

#### [NEW] [seed.sql](file:///c:/xampp/htdocs/Banking/sql/seed.sql)
Demo data: 1 user, savings + credit card + home-loan accounts, 20 sample transactions, 2 messages, 2 payees.

---

### Layout

#### [NEW] [header.php](file:///c:/xampp/htdocs/Banking/layout/header.php)
Responsive top bar showing:
- Bank logo / name
- Four icon buttons: **Alerts** 🔔, **Accounts** 🏦, **Profile** 👤, **Logout**
- Login button when not authenticated (each redirects through `auth/login.php`)

#### [NEW] [style.css](file:///c:/xampp/htdocs/Banking/assets/css/style.css)
Modern dark-accent banking theme, card-based layouts, responsive grid, badge counters for unread messages.

---

### Auth

#### [NEW] [login.php](file:///c:/xampp/htdocs/Banking/auth/login.php)
- Step 1: user-id + password → validate → if OTP enabled, issue OTP and redirect to `otp_verify.php`
- On failure: show error with Retry / Forgot Password / Register links

#### [NEW] [otp_verify.php](file:///c:/xampp/htdocs/Banking/auth/otp_verify.php)
Entry of 6-digit OTP. On success: set `$_SESSION['user_id']` and redirect to original destination. On failure: show retry/forgot links.

#### [NEW] [register.php](file:///c:/xampp/htdocs/Banking/auth/register.php)
Collects name, email, mobile, DOB, password → inserts into `users` + `customers`.

#### [NEW] [forgot_password.php](file:///c:/xampp/htdocs/Banking/auth/forgot_password.php)
Email/OTP-based reset flow.

#### [NEW] [logout.php](file:///c:/xampp/htdocs/Banking/auth/logout.php)
Destroys session, redirects to login.

---

### Dashboard (Sprint-1)

#### [NEW] [dashboard.php](file:///c:/xampp/htdocs/Banking/dashboard.php)
- Greeting card with customer name
- Account summary cards (balance per category)
- Recent-transactions table (last 5 entries)
- Quick-action buttons linking to the four header sections

---

### Messages (Sprint-1)

#### [NEW] [list.php](file:///c:/xampp/htdocs/Banking/messages/list.php)
Paginated table of all messages with status badges (unread/read). Actions: **Open**, **Mark Unread**, **Delete** (POST handlers at top of file).

#### [NEW] [view.php](file:///c:/xampp/htdocs/Banking/messages/view.php)
Full message thread view. Auto-marks as read on open.

#### [NEW] [compose.php](file:///c:/xampp/htdocs/Banking/messages/compose.php)
Form: subject, category (complaint / query / feedback), message body → inserts into `messages`.

---

### Account Management (Sprint-1)

#### [NEW] [index.php](file:///c:/xampp/htdocs/Banking/accounts/index.php)
Accordion UI grouped by `account_categories`. Each row shows account number + masked balance. Per-account actions: **View Details** (disabled, "Under Construction"), **Unlink Account**.

---

### Profile Management (Sprint-1)

#### [NEW] [view.php](file:///c:/xampp/htdocs/Banking/profile/view.php)
Read-only display of personal info + current KYC badge status.

#### [NEW] [edit.php](file:///c:/xampp/htdocs/Banking/profile/edit.php)
- Editable: name, address, DOB, mobile, password, preferences
- Name / DOB / address edits set `pending_kyc = 1` and require a document upload
- Conditional file uploader shown for KYC-sensitive fields

#### [NEW] [upload_handler.php](file:///c:/xampp/htdocs/Banking/profile/upload_handler.php)
Validates file type (pdf/jpg/png), moves to `uploads/kyc/`, records in `kyc_documents`.

---

### Sprint-2: Transaction History

#### [NEW] [history.php](file:///c:/xampp/htdocs/Banking/transactions/history.php)
Filter bar: Last 10 / Last Month / Date Range. Results table with expandable rows for details. Credit-card transactions show a **Dispute** button that opens a modal form.

---

### Sprint-2: Fund Transfer

#### [NEW] [index.php](file:///c:/xampp/htdocs/Banking/transfers/index.php)
- Left panel: source accounts (savings/current)
- Right panel: saved payees with info tooltips, **Select**, **Delete**
- **Add New Payee** CTA → `add_payee.php`

#### [NEW] [add_payee.php](file:///c:/xampp/htdocs/Banking/transfers/add_payee.php)
Form: Name, Bank, Branch, Account No, IFSC. On save, returns to `index.php` with new payee pre-selected.

#### [NEW] [confirm.php](file:///c:/xampp/htdocs/Banking/transfers/confirm.php)
- Amount entry
- Optional standing instruction (periodicity, instances, start date)
- Transfer date picker
- Confirmation card → inserts into `transactions` (and `standing_instructions` if recurring)

---

### Sprint-2: Make Payment

#### [NEW] [index.php](file:///c:/xampp/htdocs/Banking/payments/index.php)
Select CC or HL account → show card with Balance, Last Payment, Next Due, Due Date.
**Make Payment** → selects source savings/checking account → checks balance → confirm.

#### [NEW] [confirm.php](file:///c:/xampp/htdocs/Banking/payments/confirm.php)
Processes payment, updates CC/HL balance, debits source account, shows updated card details.

---

## Verification Plan

### Automated / Browser Tests (using Antigravity browser tool)

| # | Test | Steps |
|---|---|---|
| 1 | **Login flow** | Navigate to `http://localhost/Banking/`, click any nav button, verify redirect to login, enter demo credentials (user: `demo@bank.com`, pass: `Demo@1234`), enter OTP shown on screen, verify redirect to dashboard |
| 2 | **Alerts CRUD** | From dashboard click Alerts, verify message list, open a message, mark unread, delete message, compose new message |
| 3 | **Account accordion** | Click Accounts, expand each category, verify balance display, click Delete link, verify removal |
| 4 | **Profile edit + KYC** | Click Profile → Edit, change name (should trigger doc upload requirement), upload a sample PDF, save, verify pending KYC badge |
| 5 | **Transaction history** | Navigate to `transactions/history.php`, select "Last 10", verify table, switch to "Date Range", verify filtered results |
| 6 | **Fund transfer** | Go to transfers, add a new payee, select it, enter amount ₹500, set standing instruction monthly×3, confirm, verify success |
| 7 | **Make Payment** | Go to payments, select CC account (demo data), click Make Payment, select savings source, confirm, verify updated balance |

### Manual Verification (by developer)
1. Open `http://localhost/Banking/sql/` — it should **403** (directory listing must be disabled or `.htaccess` added)
2. Verify uploaded KYC files appear in `c:\xampp\htdocs\Banking\uploads\kyc\`
3. Try logging in with wrong password 3× — verify lockout / error messages
