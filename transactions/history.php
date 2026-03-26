<?php
/**
 * transactions/history.php – Transaction history with filters
 */
$page_title = 'Transaction History';
require_once __DIR__ . '/../layout/header.php';
require_login();

$uid = current_user_id();
$pdo = get_db();

// Get all user accounts for filter
$acc_stmt = $pdo->prepare(
    "SELECT a.account_id, a.account_name, a.account_number, ac.name AS category
     FROM accounts a JOIN account_categories ac ON a.category_id=ac.category_id
     WHERE a.user_id=? AND a.status='active' ORDER BY ac.display_order"
);
$acc_stmt->execute([$uid]);
$user_accounts = $acc_stmt->fetchAll();
$all_account_ids = array_column($user_accounts, 'account_id');

// Filter params
$filter    = $_GET['filter']     ?? 'last10';
$acc_id    = (int)($_GET['account_id'] ?? 0);
$date_from = $_GET['date_from'] ?? '';
$date_to   = $_GET['date_to']   ?? date('Y-m-d');

// Build account restriction
$account_where = '';
$params        = [];

if ($acc_id && in_array($acc_id, $all_account_ids)) {
    $account_where = ' AND a.account_id = ?';
    $params[]      = $acc_id;
} else {
    if (!$all_account_ids) {
        $transactions = [];
        goto render;
    }
    $placeholders  = implode(',', array_fill(0, count($all_account_ids), '?'));
    $account_where = " AND a.account_id IN ($placeholders)";
    $params        = array_merge($params, $all_account_ids);
}

// Build date filter
$date_where = '';
if ($filter === 'last10') {
    $limit = 'LIMIT 10';
} elseif ($filter === 'last_month') {
    $date_where = " AND t.txn_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
    $limit = '';
} elseif ($filter === 'date_range' && $date_from) {
    $date_where = " AND DATE(t.txn_date) BETWEEN ? AND ?";
    $params[]   = $date_from;
    $params[]   = $date_to;
    $limit = '';
} else {
    $limit = 'LIMIT 10';
}

$sql = "SELECT t.txn_id, t.txn_type, t.amount, t.balance_after, t.description,
               t.reference, t.txn_date, t.disputed, t.dispute_reason,
               a.account_name, a.account_number, ac.name AS category
        FROM transactions t
        JOIN accounts a ON t.account_id = a.account_id
        JOIN account_categories ac ON a.category_id = ac.category_id
        WHERE 1=1 $account_where $date_where
        ORDER BY t.txn_date DESC $limit";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Handle dispute submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dispute_txn_id'])) {
    $tid    = (int)$_POST['dispute_txn_id'];
    $reason = sanitize($_POST['dispute_reason'] ?? '');
    if ($reason) {
        $pdo->prepare(
            "UPDATE transactions SET disputed=1, dispute_reason=?
             WHERE txn_id=? AND account_id IN(" .
             implode(',', array_fill(0, count($all_account_ids), '?')) .
             ")"
        )->execute(array_merge([$reason, $tid], $all_account_ids));
        set_flash('success', 'Dispute raised successfully. Our team will review it shortly.');
        redirect('/Banking/transactions/history.php?' . http_build_query($_GET));
    }
}

render:
?>

<main class="page-wrapper">
  <h1 class="page-title"><i class="bi bi-clock-history"></i> Transaction History</h1>

  <!-- Filter bar -->
  <div class="card mb-3">
    <form method="GET" class="d-flex gap-2" style="flex-wrap:wrap;align-items:flex-end">
      <div class="form-group" style="flex:1;min-width:160px;margin:0">
        <label class="form-label">Account</label>
        <select name="account_id" class="form-control">
          <option value="">All Accounts</option>
          <?php foreach ($user_accounts as $a): ?>
            <option value="<?= $a['account_id'] ?>" <?= $acc_id===$a['account_id']?'selected':'' ?>>
              <?= htmlspecialchars($a['account_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group" style="flex:1;min-width:160px;margin:0">
        <label class="form-label">Period</label>
        <select name="filter" id="filter-sel" class="form-control" onchange="toggleDateRange()">
          <option value="last10"     <?= $filter==='last10'     ?'selected':'' ?>>Last 10 Transactions</option>
          <option value="last_month" <?= $filter==='last_month' ?'selected':'' ?>>Last 1 Month</option>
          <option value="date_range" <?= $filter==='date_range' ?'selected':'' ?>>Date Range</option>
        </select>
      </div>

      <div id="date-range-wrap" class="d-flex gap-2" style="<?= $filter==='date_range'?'':'display:none!important' ?>">
        <div class="form-group" style="margin:0">
          <label class="form-label">From</label>
          <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>">
        </div>
        <div class="form-group" style="margin:0">
          <label class="form-label">To</label>
          <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>">
        </div>
      </div>

      <div style="margin:0">
        <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Search</button>
      </div>
    </form>
  </div>

  <!-- Results -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">
        <i class="bi bi-list-ul"></i>
        <?= count($transactions) ?> Transaction<?= count($transactions)!==1?'s':'' ?>
      </span>
    </div>

    <?php if (empty($transactions)): ?>
      <p class="text-muted text-center" style="padding:2rem">No transactions found for the selected criteria.</p>
    <?php else: ?>
    <table class="data-table">
      <thead>
        <tr>
          <th>Date</th>
          <th>Description</th>
          <th>Account</th>
          <th>Reference</th>
          <th style="text-align:right">Amount</th>
          <th style="text-align:right">Balance</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($transactions as $t): ?>
        <tr class="txn-row-toggle" data-id="<?= $t['txn_id'] ?>" style="cursor:pointer">
          <td class="text-muted" style="white-space:nowrap;font-size:.82rem"><?= fmt_datetime($t['txn_date']) ?></td>
          <td>
            <div style="font-weight:500;font-size:.9rem"><?= htmlspecialchars($t['description']) ?></div>
            <?php if ($t['disputed']): ?>
              <span class="badge badge-danger" style="font-size:.7rem"><i class="bi bi-exclamation-triangle"></i> Disputed</span>
            <?php endif; ?>
          </td>
          <td>
            <div style="font-size:.82rem"><?= htmlspecialchars($t['account_name']) ?></div>
            <div class="monospace text-muted" style="font-size:.74rem"><?= htmlspecialchars($t['category']) ?></div>
          </td>
          <td class="monospace text-muted" style="font-size:.78rem"><?= htmlspecialchars($t['reference'] ?? '—') ?></td>
          <td style="text-align:right;white-space:nowrap">
            <span class="badge <?= txn_badge($t['txn_type']) ?>">
              <?= $t['txn_type']==='credit'?'+':'−' ?><?= fmt_inr($t['amount']) ?>
            </span>
          </td>
          <td style="text-align:right;font-weight:600;white-space:nowrap;font-family:'Outfit',sans-serif;font-size:.9rem"
              class="<?= $t['balance_after']>=0?'text-success':'text-danger' ?>">
            <?= fmt_inr($t['balance_after']) ?>
          </td>
          <td>
            <i class="bi bi-chevron-down text-muted" style="font-size:.8rem"></i>
          </td>
        </tr>
        <!-- Expandable detail row -->
        <tr id="txn-detail-<?= $t['txn_id'] ?>" class="hidden">
          <td colspan="7" style="padding:0">
            <div style="background:rgba(0,0,0,.2);padding:1rem 1.5rem;border-bottom:1px solid var(--border)">
              <div style="display:flex;gap:2rem;flex-wrap:wrap;font-size:.85rem">
                <div><span class="text-muted">Type:</span> <?= ucfirst($t['txn_type']) ?></div>
                <div><span class="text-muted">Amount:</span> <?= fmt_inr($t['amount']) ?></div>
                <div><span class="text-muted">Balance after:</span> <?= fmt_inr($t['balance_after']) ?></div>
                <div><span class="text-muted">Ref:</span> <?= htmlspecialchars($t['reference'] ?? 'N/A') ?></div>
                <div><span class="text-muted">Date:</span> <?= fmt_datetime($t['txn_date']) ?></div>
              </div>
              <?php if ($t['dispute_reason']): ?>
                <div style="margin-top:.5rem"><span class="text-muted">Dispute Reason:</span> <?= htmlspecialchars($t['dispute_reason']) ?></div>
              <?php endif; ?>

              <!-- Dispute button for Credit Card transactions -->
              <?php if ($t['category'] === 'Credit Card' && !$t['disputed']): ?>
              <div style="margin-top:.75rem">
                <button class="btn btn-danger btn-sm" onclick="toggleEl('dispute-form-<?= $t['txn_id'] ?>')">
                  <i class="bi bi-flag"></i> Raise Dispute
                </button>
                <div id="dispute-form-<?= $t['txn_id'] ?>" class="hidden" style="margin-top:.75rem">
                  <form method="POST">
                    <input type="hidden" name="dispute_txn_id" value="<?= $t['txn_id'] ?>">
                    <div class="form-group">
                      <label class="form-label">Reason for Dispute</label>
                      <textarea name="dispute_reason" class="form-control" rows="2" required
                                placeholder="Describe why you are disputing this transaction…"></textarea>
                    </div>
                    <button type="submit" class="btn btn-danger btn-sm">Submit Dispute</button>
                    <button type="button" class="btn btn-outline btn-sm"
                            onclick="toggleEl('dispute-form-<?= $t['txn_id'] ?>')">Cancel</button>
                  </form>
                </div>
              </div>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</main>

<script>
function toggleDateRange() {
  const sel = document.getElementById('filter-sel');
  const wrap = document.getElementById('date-range-wrap');
  wrap.style.display = sel.value === 'date_range' ? 'flex' : 'none';
}
</script>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
