<?php
/**
 * payments/index.php – Make CC / Home Loan payment
 */
$page_title = 'Make a Payment';
require_once __DIR__ . '/../layout/header.php';
require_login();

$uid = current_user_id();
$pdo = get_db();

// Payable accounts = CC and Home Loan (negative balance = amount owed)
$payable = $pdo->prepare(
    "SELECT a.account_id, a.account_name, a.account_number, a.balance,
            a.credit_limit, a.due_date, a.last_payment, a.last_payment_date,
            a.next_payment, ac.name AS category, ac.icon
     FROM accounts a
     JOIN account_categories ac ON a.category_id = ac.category_id
     WHERE a.user_id = ? AND a.status='active'
       AND ac.name IN ('Credit Card','Home Loan')
     ORDER BY a.due_date ASC"
);
$payable->execute([$uid]);
$payable_accounts = $payable->fetchAll();

// Source accounts (savings / current)
$src_stmt = $pdo->prepare(
    "SELECT a.account_id, a.account_name, a.account_number, a.balance, ac.name AS category
     FROM accounts a
     JOIN account_categories ac ON a.category_id = ac.category_id
     WHERE a.user_id = ? AND a.status='active'
       AND ac.name IN ('Savings','Current')
     ORDER BY a.balance DESC"
);
$src_stmt->execute([$uid]);
$source_accounts = $src_stmt->fetchAll();

$selected_pay_id = (int)($_GET['pay_account'] ?? 0);
$selected_payable = null;
if ($selected_pay_id) {
    foreach ($payable_accounts as $pa) {
        if ($pa['account_id'] === $selected_pay_id) {
            $selected_payable = $pa; break;
        }
    }
}
?>

<main class="page-wrapper">
  <h1 class="page-title"><i class="bi bi-credit-card-2-front"></i> Make a Payment</h1>

  <div class="grid-2">

    <!-- Left: Select CC/HL account -->
    <div>
      <div class="card">
        <div class="card-header">
          <span class="card-title"><i class="bi bi-receipt"></i> Select Account to Pay</span>
        </div>
        <?php if (empty($payable_accounts)): ?>
          <p class="text-muted text-center" style="padding:1.5rem">No credit card or home loan accounts found.</p>
        <?php endif; ?>
        <?php foreach ($payable_accounts as $pa): ?>
        <a href="?pay_account=<?= $pa['account_id'] ?>"
           class="card mb-1" style="display:block;text-decoration:none;border:1px solid <?= $selected_pay_id===$pa['account_id']?'var(--gold-dim)':'var(--border)' ?>;background:<?= $selected_pay_id===$pa['account_id']?'rgba(212,168,67,.06)':'var(--navy-card)' ?>">
          <div class="flex-between">
            <div>
              <div style="display:flex;align-items:center;gap:.5rem">
                <i class="bi <?= $pa['icon'] ?>" style="color:var(--gold)"></i>
                <strong><?= htmlspecialchars($pa['account_name']) ?></strong>
              </div>
              <div class="monospace text-muted" style="font-size:.77rem;margin-top:.15rem"><?= htmlspecialchars($pa['account_number']) ?></div>
            </div>
            <div style="text-align:right">
              <div style="font-weight:700;font-family:'Outfit',sans-serif;color:var(--danger);font-size:1.1rem">
                <?= fmt_inr(abs($pa['balance'])) ?> <span style="font-size:.75rem;color:var(--text-muted)">owed</span>
              </div>
              <?php if ($pa['due_date']): ?>
                <div style="font-size:.76rem;color:var(--warning)">Due: <?= fmt_date($pa['due_date']) ?></div>
              <?php endif; ?>
            </div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Right: Account details + pay form -->
    <div>
      <?php if ($selected_payable): ?>
      <div class="card mb-2">
        <div class="card-header">
          <span class="card-title">
            <i class="bi <?= $selected_payable['icon'] ?>"></i>
            <?= htmlspecialchars($selected_payable['account_name']) ?>
          </span>
        </div>

        <div class="grid-3" style="grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:.75rem;margin-bottom:1.25rem">
          <?php $details = [
            ['Outstanding Balance', fmt_inr(abs($selected_payable['balance'])), 'danger'],
            ['Minimum Due',         fmt_inr($selected_payable['next_payment'] ?? 0), 'warning'],
            ['Last Payment',        $selected_payable['last_payment'] ? fmt_inr($selected_payable['last_payment']) : '—', 'success'],
            ['Last Payment Date',   fmt_date($selected_payable['last_payment_date']), null],
            ['Next Due Date',       fmt_date($selected_payable['due_date']), 'warning'],
          ];
          if ($selected_payable['credit_limit']):
            $details[] = ['Credit Limit', fmt_inr($selected_payable['credit_limit']), null];
          endif;
          foreach ($details as [$lbl, $val, $cls]): ?>
          <div style="background:rgba(255,255,255,.03);border-radius:8px;padding:.75rem">
            <div class="stat-label"><?= $lbl ?></div>
            <div style="font-weight:700;font-family:'Outfit',sans-serif;font-size:.95rem" class="<?= $cls ? 'text-'.$cls : '' ?>">
              <?= $val ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <form method="POST" action="/Banking/payments/confirm.php">
          <input type="hidden" name="to_account_id" value="<?= $selected_payable['account_id'] ?>">

          <div class="form-group">
            <label class="form-label">Pay From</label>
            <select name="from_account_id" class="form-control" required>
              <option value="">— Select account —</option>
              <?php foreach ($source_accounts as $sa): ?>
                <option value="<?= $sa['account_id'] ?>">
                  <?= htmlspecialchars($sa['account_name']) ?>
                  (<?= htmlspecialchars($sa['account_number']) ?>) –
                  Available: <?= fmt_inr($sa['balance']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Payment Amount (₹)</label>
            <input type="number" name="amount" class="form-control" required
                   min="1" step="0.01"
                   value="<?= abs($selected_payable['balance']) ?>"
                   placeholder="<?= abs($selected_payable['balance']) ?>">
            <div class="form-hint">
              Minimum due: <?= fmt_inr($selected_payable['next_payment'] ?? 0) ?> &nbsp;|&nbsp;
              Full outstanding: <?= fmt_inr(abs($selected_payable['balance'])) ?>
            </div>
          </div>

          <div style="text-align:right;margin-top:.5rem">
            <button type="submit" class="btn btn-primary btn-lg">
              <i class="bi bi-check-circle"></i> Review & Confirm
            </button>
          </div>
        </form>
      </div>

      <?php else: ?>
      <div class="card text-center" style="padding:3rem">
        <i class="bi bi-arrow-left-circle" style="font-size:2.5rem;color:var(--text-muted)"></i>
        <p class="text-muted mt-2">Select a Credit Card or Home Loan account on the left.</p>
      </div>
      <?php endif; ?>
    </div>

  </div>
</main>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
