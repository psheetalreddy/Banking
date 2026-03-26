<?php
/**
 * dashboard.php – Main landing page after login
 */
$page_title = 'Dashboard';
require_once __DIR__ . '/layout/header.php';
require_login();

$uid   = current_user_id();
$pdo   = get_db();

// Customer info
$cust = $pdo->prepare(
    "SELECT c.first_name, c.last_name, u.email, u.mobile
     FROM customers c JOIN users u USING(user_id) WHERE c.user_id=?"
);
$cust->execute([$uid]);
$customer = $cust->fetch();

// Account balances grouped by category
$accs = $pdo->prepare(
    "SELECT a.account_id, a.account_number, a.account_name, a.balance,
            ac.name AS category, ac.icon
     FROM accounts a
     JOIN account_categories ac ON a.category_id = ac.category_id
     WHERE a.user_id = ? AND a.status = 'active'
     ORDER BY ac.display_order, a.linked_at"
);
$accs->execute([$uid]);
$accounts = $accs->fetchAll();

// Net worth (sum of savings/current/investment – loans/CC)
$nw_stmt = $pdo->prepare("SELECT SUM(balance) FROM accounts WHERE user_id=? AND status='active'");
$nw_stmt->execute([$uid]);
$net_worth = (float)$nw_stmt->fetchColumn();

// Recent transactions (last 5 across all linked accounts)
$txns = $pdo->prepare(
    "SELECT t.txn_id, t.txn_type, t.amount, t.description, t.txn_date,
            a.account_name, a.account_number
     FROM transactions t
     JOIN accounts a ON t.account_id = a.account_id
     WHERE a.user_id = ?
     ORDER BY t.txn_date DESC
     LIMIT 5"
);
$txns->execute([$uid]);
$recent_txns = $txns->fetchAll();
?>

<main class="page-wrapper">

  <!-- Hero greeting -->
  <div class="dashboard-hero">
    <div>
      <div class="hero-greeting">Good <?= (date('H') < 12 ? 'Morning' : (date('H') < 18 ? 'Afternoon' : 'Evening')) ?>,
        <span><?= htmlspecialchars($customer['first_name']) ?>!</span>
      </div>
      <div class="hero-sub">
        <i class="bi bi-calendar3"></i> <?= date('l, d F Y') ?> &nbsp;|&nbsp;
        Last login: today
      </div>
    </div>
    <div style="display:flex;gap:1rem;flex-wrap:wrap">
      <a href="/Banking/messages/compose.php" class="btn btn-outline btn-sm">
        <i class="bi bi-chat-dots"></i> Message Us
      </a>
      <a href="/Banking/transactions/history.php" class="btn btn-outline btn-sm">
        <i class="bi bi-clock-history"></i> History
      </a>
      <a href="/Banking/transfers/index.php" class="btn btn-primary btn-sm">
        <i class="bi bi-arrow-left-right"></i> Transfer
      </a>
    </div>
  </div>

  <!-- Net Worth + quick stats -->
  <div class="grid-3 mb-3">
    <div class="stat-card">
      <div class="stat-label"><i class="bi bi-wallet2"></i> Net Balance</div>
      <div class="stat-value <?= $net_worth >= 0 ? 'positive' : 'negative' ?>">
        <?= fmt_inr($net_worth) ?>
      </div>
      <div class="stat-sub">All accounts combined</div>
      <i class="bi bi-wallet2 stat-icon"></i>
    </div>

    <?php foreach ($accounts as $acc):
      if (!in_array($acc['category'], ['Savings','Current'])) continue; ?>
    <div class="stat-card">
      <div class="stat-label"><i class="bi <?= $acc['icon'] ?>"></i> <?= htmlspecialchars($acc['category']) ?></div>
      <div class="stat-value <?= $acc['balance'] >= 0 ? 'positive' : 'negative' ?>">
        <?= fmt_inr($acc['balance']) ?>
      </div>
      <div class="stat-sub monospace"><?= htmlspecialchars($acc['account_number']) ?></div>
      <i class="bi <?= $acc['icon'] ?> stat-icon"></i>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="grid-2">
    <!-- All Accounts Summary -->
    <div class="card">
      <div class="card-header">
        <span class="card-title"><i class="bi bi-bank2"></i> Your Accounts</span>
        <a href="/Banking/accounts/index.php" class="btn btn-outline btn-sm">Manage</a>
      </div>
      <table class="data-table">
        <thead>
          <tr>
            <th>Account</th>
            <th style="text-align:right">Balance</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($accounts as $acc): ?>
          <tr>
            <td>
              <div style="font-weight:600;font-size:.9rem"><?= htmlspecialchars($acc['account_name']) ?></div>
              <div class="text-muted monospace" style="font-size:.77rem"><?= htmlspecialchars($acc['category']) ?></div>
            </td>
            <td style="text-align:right;font-weight:700;font-family:'Outfit',sans-serif" class="<?= $acc['balance'] >= 0 ? 'text-success' : 'text-danger' ?>">
              <?= fmt_inr($acc['balance']) ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Recent Transactions -->
    <div class="card">
      <div class="card-header">
        <span class="card-title"><i class="bi bi-clock-history"></i> Recent Transactions</span>
        <a href="/Banking/transactions/history.php" class="btn btn-outline btn-sm">View All</a>
      </div>
      <?php if (empty($recent_txns)): ?>
        <p class="text-muted text-center" style="padding:1.5rem 0">No transactions yet.</p>
      <?php else: ?>
      <table class="data-table">
        <thead>
          <tr><th>Description</th><th>Date</th><th style="text-align:right">Amount</th></tr>
        </thead>
        <tbody>
          <?php foreach ($recent_txns as $t): ?>
          <tr>
            <td>
              <div style="font-size:.88rem;font-weight:500"><?= htmlspecialchars($t['description']) ?></div>
              <div class="text-muted" style="font-size:.75rem"><?= htmlspecialchars($t['account_name']) ?></div>
            </td>
            <td class="text-muted" style="font-size:.78rem;white-space:nowrap"><?= fmt_date($t['txn_date'], 'd M') ?></td>
            <td style="text-align:right;white-space:nowrap">
              <span class="badge <?= txn_badge($t['txn_type']) ?>">
                <?= $t['txn_type'] === 'credit' ? '+' : '−' ?><?= fmt_inr($t['amount']) ?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- Quick links -->
  <div class="grid-3 mt-3">
    <a href="/Banking/messages/list.php" class="card" style="text-decoration:none;cursor:pointer;text-align:center">
      <i class="bi bi-bell" style="font-size:2rem;color:var(--gold)"></i>
      <div style="font-weight:600;margin-top:.5rem">Alerts & Messages</div>
      <?php if ($unread): ?>
        <div class="badge badge-unread" style="margin-top:.35rem"><?= $unread ?> unread</div>
      <?php endif; ?>
    </a>
    <a href="/Banking/transfers/index.php" class="card" style="text-decoration:none;cursor:pointer;text-align:center">
      <i class="bi bi-arrow-left-right" style="font-size:2rem;color:var(--teal)"></i>
      <div style="font-weight:600;margin-top:.5rem">Fund Transfer</div>
      <div class="text-muted" style="font-size:.8rem">Transfer to payees</div>
    </a>
    <a href="/Banking/payments/index.php" class="card" style="text-decoration:none;cursor:pointer;text-align:center">
      <i class="bi bi-credit-card-2-front" style="font-size:2rem;color:var(--success)"></i>
      <div style="font-weight:600;margin-top:.5rem">Make Payment</div>
      <div class="text-muted" style="font-size:.8rem">CC / Home Loan</div>
    </a>
  </div>

</main>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
