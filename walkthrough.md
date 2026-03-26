# ArcaBank – Online Banking App Walkthrough

## What Was Built

A complete PHP/MySQL online banking portal covering **Sprint-1** and **Sprint-2**, deployed at `http://localhost/Banking/`.

---

## File Structure (30+ files)

```
Banking/
├── config/          db.php · session.php · functions.php
├── sql/             schema.sql (12 tables) · seed.sql (demo data)
├── auth/            login · otp_verify · register · forgot_password · logout
├── layout/          header.php · footer.php
├── assets/          style.css (dark navy/gold theme) · main.js
├── dashboard.php
├── messages/        list · view · compose
├── accounts/        index (accordion) · detail (placeholder)
├── profile/         view · edit (KYC aware)
├── transactions/    history (3 filter modes)
├── transfers/       index · add_payee · confirm
├── payments/        index · confirm
└── uploads/kyc/     (KYC document storage)
```

---

## Demo Credentials

| Field | Value |
|---|---|
| Email | `demo@bank.com` |
| Password | `Demo@1234` |
| OTP | Shown on-screen in the demo blue box |

---

## Smoke Test Results (All Passed ✅)

| # | Feature | Result |
|---|---|---|
| 1 | Root → Login redirect | ✅ |
| 2-3 | Login + OTP flow | ✅ Account lockout after 5 failures confirmed |
| 4 | Dashboard | ✅ Greeting, net balance, accounts, recent txns |
| 5-7 | Alerts & Messages | ✅ List, view, mark-unread, delete, compose |
| 8 | Account accordion | ✅ Categories expand/collapse with balances |
| 9 | Profile view | ✅ Personal info, KYC badges, preferences |
| 10 | Transaction history | ✅ 3 filter modes: last-10, last-month, date-range |
| 11 | Fund Transfer | ✅ Source accounts + payees + standing instruction |
| 12 | Make Payment | ✅ CC/HL balance cards + source account + confirm |
| 13 | Logout | ✅ Session destroy + redirect |

---

## Screenshots

### Dashboard
![Dashboard](file:///C:/Users/pradeep/.gemini/antigravity/brain/f7636b8a-7c36-447c-a858-af30a2d3378b/dashboard_main_1773420189826.png)

### Profile Page
![Profile](file:///C:/Users/pradeep/.gemini/antigravity/brain/f7636b8a-7c36-447c-a858-af30a2d3378b/profile_view_1773420224249.png)

### Transaction History
![Transactions](file:///C:/Users/pradeep/.gemini/antigravity/brain/f7636b8a-7c36-447c-a858-af30a2d3378b/transactions_history_1773420243980.png)

---

## Browser Session Recording

![Full app walkthrough recording](file:///C:/Users/pradeep/.gemini/antigravity/brain/f7636b8a-7c36-447c-a858-af30a2d3378b/banking_app_smoke_test_1773419721473.webp)

---

## Key Design Decisions

- **OTP Demo mode** — OTP is shown in a blue info box on the verify page. Replace [send_otp()](file:///C:/xampp/htdocs/Banking/config/functions.php#35-58) in [config/functions.php](file:///C:/xampp/htdocs/Banking/config/functions.php) with a real SMS gateway call for production.
- **KYC tracking** — name/DOB/address edits set `pending_kyc=1` flags in the DB; the profile view shows orange "KYC Pending" badges automatically.
- **Account lockout** — 5 failed logins → 30-minute account lock (configurable).
- **Standing instructions** — stored in `standing_instructions` table; a cron job would process them in production.
- **Security** — [.htaccess](file:///C:/xampp/htdocs/Banking/sql/.htaccess) blocks directory listing on `uploads/` and [sql/](file:///C:/xampp/htdocs/Banking/sql/seed.sql); KYC files validated by MIME extension before storage.

---

## To Test With Demo Data (Pradeep Kumar's Account)

The [seed.sql](file:///C:/xampp/htdocs/Banking/sql/seed.sql) created a full demo account. If the demo user was locked during testing, unlock it with:

```sql
UPDATE banking_db.users SET status='active', failed_attempts=0, locked_until=NULL WHERE email='demo@bank.com';
```

Then log in with `demo@bank.com` / `Demo@1234` to see the pre-seeded accounts, 15 transactions, and 2 payees.
