<?php
/**
 * accounts/detail.php
 * Full account detail view: balance, info, recent transactions, quick-pay/transfer links.
 */
$page_title = 'Account Details';
require_once __DIR__ . '/../layout/header.php';
require_login();

$uid    = current_user_id();
$pdo    = get_db();
$acc_id = (int)($_GET['id'] ?? 0);

if (!$acc_id) redirect('/Banking/accounts/index.php');

// Verify ownership
$stmt = $pdo->prepare(
    "SELECT a.*, ac.name AS category, ac.icon
     FROM accounts a
     JOIN account_categories ac ON a.category_id = ac.category_id
     WHERE a.account_id = ? AND a.user_id = ? AND a.status = 'active'"
);
$stmt->execute([$acc_id, $uid]);
$acc = $stmt->fetch();
if (!$acc) {
    set_flash('danger', 'Account not found.');
    redirect('/Banking/accounts/index.php');
}

// Last 10 transactions for this account
$txns = $pdo->prepare(
    "SELECT * FROM transactions WHERE account_id = ? ORDER BY txn_date DESC LIMIT 10"
);
$txns->execute([$acc_id]);
$transactions = $txns->fetchAll();

// Stats: total credits and debits this month
$stats = $pdo->prepare(
    "SELECT
       SUM(CASE WHEN txn_type='credit' THEN amount ELSE 0 END) AS total_credit,
       SUM(CASE WHEN txn_type='debit'  THEN amount ELSE 0 END) AS total_debit,
       COUNT(*) AS txn_count
     FROM transactions
     WHERE account_id = ?
       AND txn_date >= DATE_FORMAT(NOW(),'%Y-%m-01')"
);
$stats->execute([$acc_id]);
$month_stats = $stats->fetch();

$is_loan = in_array($acc['category'], ['Credit Card', 'Home Loan']);
$is_cc   = $acc['category'] === 'Credit Card';
?>

<main class="page-wrapper" style="max-width:960px">
  <div class="flex-between mb-2">
    <h1 class="page-title">
      <i class="bi <?= $acc['icon'] ?>"></i>
      <?= htmlspecialchars($acc['account_name']) ?>
    </h1>
    <div style="display:flex;gap:.5rem">
      <a href="/Banking/accounts/index.php" class="btn btn-outline btn-sm">
        <i class="bi bi-arrow-left"></i> All Accounts
      </a>
      <a href="/Banking/accounts/edit_account.php?id=<?= $acc_id ?>" class="btn btn-outline btn-sm">
        <i class="bi bi-pencil"></i> Edit
      </a>
    </div>
  </div>

  <!-- Account Hero Card -->
  <div class="card mb-2" style="background:linear-gradient(135deg,var(--navy-light),var(--navy-card));position:relative;overflow:hidden">
    <div style="position:absolute;right:2rem;top:50%;transform:translateY(-50%);font-size:6rem;opacity:.05">
      <i class="bi <?= $acc['icon'] ?>"></i>
    </div>
    <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:1.5rem">
      <div>
        <div style="font-size:.78rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.07em;margin-bottom:.25rem">
          <?= htmlspecialchars($acc['category']) ?> · <?= htmlspecialchars($acc['currency']) ?>
        </div>
        <div style="font-family:'Outfit',sans-serif;font-size:2.2rem;font-weight:700;color:<?= $acc['balance'] >= 0 ? 'var(--success)' : 'var(--danger)' ?>">
          <?= fmt_inr($acc['balance']) ?>
        </div>
        <div class="monospace text-muted" style="font-size:.85rem;margin-top:.3rem">
          <?= htmlspecialchars($acc['account_number']) ?>
        </div>
      </div>

      <div style="display:flex;flex-direction:column;gap:.5rem">
        <?php if ($is_loan): ?>
          <?php if ($acc['credit_limit']): ?>
          <div class="stat-card" style="padding:.75rem 1.25rem;min-width:160px">
            <div class="stat-label">Credit Limit</div>
            <div style="font-weight:700;font-family:'Outfit',sans-serif"><?= fmt_inr($acc['credit_limit']) ?></div>
          </div>
          <div class="stat-card" style="padding:.75rem 1.25rem">
            <div class="stat-label">Available Credit</div>
            <div style="font-weight:700;font-family:'Outfit',sans-serif;color:var(--success)">
              <?= fmt_inr($acc['credit_limit'] + $acc['balance']) ?>
            </div>
          </div>
          <?php endif; ?>
          <?php if ($acc['due_date']): ?>
          <div class="stat-card" style="padding:.75rem 1.25rem">
            <div class="stat-label">Payment Due</div>
            <div style="font-weight:700;color:var(--warning)"><?= fmt_date($acc['due_date']) ?></div>
          </div>
          <?php endif; ?>
        <?php else: ?>
          <div class="stat-card" style="padding:.75rem 1.25rem;min-width:160px">
            <div class="stat-label">Credits This Month</div>
            <div style="font-weight:700;font-family:'Outfit',sans-serif;color:var(--success)">
              <?= fmt_inr($month_stats['total_credit'] ?? 0) ?>
            </div>
          </div>
          <div class="stat-card" style="padding:.75rem 1.25rem">
            <div class="stat-label">Debits This Month</div>
            <div style="font-weight:700;font-family:'Outfit',sans-serif;color:var(--danger)">
              <?= fmt_inr($month_stats['total_debit'] ?? 0) ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Quick Actions -->
    <div style="border-top:1px solid var(--border);margin-top:1.25rem;padding-top:1rem;display:flex;gap:.75rem;flex-wrap:wrap">
      <?php if (!$is_loan): ?>
      <a href="/Banking/transfers/index.php" class="btn btn-primary btn-sm">
        <i class="bi bi-arrow-left-right"></i> Transfer Funds
      </a>
      <a href="/Banking/transactions/history.php?account_id=<?= $acc_id ?>" class="btn btn-outline btn-sm">
        <i class="bi bi-clock-history"></i> Full History
      </a>
      <?php else: ?>
      <a href="/Banking/payments/index.php?pay_account=<?= $acc_id ?>" class="btn btn-primary btn-sm">
        <i class="bi bi-credit-card"></i> Make a Payment
      </a>
      <a href="/Banking/transactions/history.php?account_id=<?= $acc_id ?>" class="btn btn-outline btn-sm">
        <i class="bi bi-clock-history"></i> View Statements
      </a>
      <?php endif; ?>
      <a href="/Banking/accounts/edit_account.php?id=<?= $acc_id ?>" class="btn btn-outline btn-sm">
        <i class="bi bi-gear"></i> Account Settings
      </a>
    </div>
  </div>

  <!-- Payment Info for CC/HL -->
  <?php if ($is_loan && ($acc['last_payment'] || $acc['next_payment'])): ?>
  <div class="card mb-2">
    <div class="card-header">
      <span class="card-title"><i class="bi bi-receipt"></i> Payment Summary</span>
    </div>
    <div class="profile-grid" style="grid-template-columns:repeat(auto-fill,minmax(200px,1fr))">
      <?php $rows = [
        ['Outstanding Balance',  fmt_inr(abs($acc['balance'])), 'danger'],
        ['Next Payment Due',     fmt_inr($acc['next_payment'] ?? 0), 'warning'],
        ['Last Payment Amount',  $acc['last_payment'] ? fmt_inr($acc['last_payment']) : '—', 'success'],
        ['Last Payment Date',    fmt_date($acc['last_payment_date']), null],
        ['Payment Due Date',     fmt_date($acc['due_date']), 'warning'],
      ];
      if ($acc['credit_limit']) $rows[] = ['Credit Limit', fmt_inr($acc['credit_limit']), null];
      foreach ($rows as [$lbl, $val, $cls]): ?>
      <div class="profile-field">
        <div class="profile-field-label"><?= $lbl ?></div>
        <div class="profile-field-value <?= $cls ? 'text-'.$cls : '' ?>"><?= $val ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Recent Transactions -->
  <div class="card">
    <div class="card-header">
      <span class="card-title"><i class="bi bi-clock-history"></i> Recent Transactions</span>
      <a href="/Banking/transactions/history.php?account_id=<?= $acc_id ?>" class="btn btn-outline btn-sm">
        View All
      </a>
    </div>
    <?php if (empty($transactions)): ?>
      <p class="text-muted text-center" style="padding:2rem">No transactions yet on this account.</p>
    <?php else: ?>
    <table class="data-table">
      <thead>
        <tr>
          <th>Date</th>
          <th>Description</th>
          <th>Reference</th>
          <th style="text-align:right">Amount</th>
          <th style="text-align:right">Balance</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($transactions as $t): ?>
        <tr>
          <td class="text-muted" style="white-space:nowrap;font-size:.8rem"><?= fmt_datetime($t['txn_date']) ?></td>
          <td style="font-size:.88rem">
            <?= htmlspecialchars($t['description']) ?>
            <?php if ($t['disputed']): ?>
              <span class="badge badge-danger" style="font-size:.68rem;margin-left:.3rem">Disputed</span>
            <?php endif; ?>
          </td>
          <td class="monospace text-muted" style="font-size:.76rem"><?= htmlspecialchars($t['reference'] ?? '—') ?></td>
          <td style="text-align:right;white-space:nowrap">
            <span class="badge <?= txn_badge($t['txn_type']) ?>">
              <?= $t['txn_type'] === 'credit' ? '+' : '−' ?><?= fmt_inr($t['amount']) ?>
            </span>
          </td>
          <td style="text-align:right;font-weight:600;font-family:'Outfit',sans-serif;font-size:.88rem"
              class="<?= $t['balance_after'] >= 0 ? 'text-success' : 'text-danger' ?>">
            <?= fmt_inr($t['balance_after']) ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</main>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
