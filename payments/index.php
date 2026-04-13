<?php
/**
 * payments/index.php – Make CC / Home Loan payment
 * Updated: auto-selects account from ?pay_account= (linked from accounts/detail.php)
 */
$page_title = 'Make a Payment';
require_once __DIR__ . '/../layout/header.php';
require_login();

$uid = current_user_id();
$pdo = get_db();

// Payable accounts = CC and Home Loan
$payable_stmt = $pdo->prepare(
    "SELECT a.account_id, a.account_name, a.account_number, a.balance,
            a.credit_limit, a.due_date, a.last_payment, a.last_payment_date,
            a.next_payment, ac.name AS category, ac.icon
     FROM accounts a
     JOIN account_categories ac ON a.category_id = ac.category_id
     WHERE a.user_id = ? AND a.status='active'
       AND ac.name IN ('Credit Card','Home Loan')
     ORDER BY a.due_date ASC, a.balance ASC"
);
$payable_stmt->execute([$uid]);
$payable_accounts = $payable_stmt->fetchAll();

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

// Pre-selected payable account (from URL or POST)
$selected_pay_id = (int)($_GET['pay_account'] ?? $_POST['pay_account'] ?? 0);

// If only one payable account exists and nothing selected, auto-select it
if (!$selected_pay_id && count($payable_accounts) === 1) {
    $selected_pay_id = $payable_accounts[0]['account_id'];
}

$selected_payable = null;
foreach ($payable_accounts as $pa) {
    if ($pa['account_id'] === $selected_pay_id) {
        $selected_payable = $pa;
        break;
    }
}

// Days until due
function days_until(string $date): int {
    return (int)(new DateTime($date))->diff(new DateTime())->days * ((new DateTime($date)) >= (new DateTime()) ? 1 : -1);
}
?>

<main class="page-wrapper">
  <h1 class="page-title"><i class="bi bi-credit-card-2-front"></i> Make a Payment</h1>

  <?php if (empty($payable_accounts)): ?>
  <!-- No CC/HL accounts state -->
  <div class="card text-center" style="padding:3rem;max-width:560px;margin:0 auto">
    <i class="bi bi-credit-card" style="font-size:3rem;color:var(--text-muted)"></i>
    <h3 style="font-family:'Outfit',sans-serif;margin:1rem 0 .5rem">No Payment Accounts Found</h3>
    <p class="text-muted" style="margin-bottom:1.5rem">
      You don't have any Credit Card or Home Loan accounts linked yet.
      Add one to start making payments.
    </p>
    <a href="/Banking/accounts/add_account.php" class="btn btn-primary">
      <i class="bi bi-plus-circle"></i> Link a Credit Card / Home Loan
    </a>
  </div>

  <?php elseif (empty($source_accounts)): ?>
  <!-- No source accounts state -->
  <div class="card text-center" style="padding:3rem;max-width:560px;margin:0 auto">
    <i class="bi bi-wallet2" style="font-size:3rem;color:var(--text-muted)"></i>
    <h3 style="font-family:'Outfit',sans-serif;margin:1rem 0 .5rem">No Savings / Current Accounts</h3>
    <p class="text-muted" style="margin-bottom:1.5rem">
      You need a Savings or Current account linked to make payments from.
    </p>
    <a href="/Banking/accounts/add_account.php" class="btn btn-primary">
      <i class="bi bi-plus-circle"></i> Link a Savings Account
    </a>
  </div>

  <?php else: ?>

  <div class="grid-2">

    <!-- ── Left: Select CC/HL account ── -->
    <div>
      <div class="card">
        <div class="card-header">
          <span class="card-title"><i class="bi bi-receipt"></i> Select Account to Pay</span>
        </div>

        <?php foreach ($payable_accounts as $pa):
          $is_selected = $selected_pay_id === $pa['account_id'];
          $overdue     = $pa['due_date'] && strtotime($pa['due_date']) < time();
          $due_soon    = $pa['due_date'] && !$overdue && strtotime($pa['due_date']) < strtotime('+7 days');
        ?>
        <a href="?pay_account=<?= $pa['account_id'] ?>"
           style="display:block;text-decoration:none;
                  border:1px solid <?= $is_selected ? 'var(--gold-dim)' : 'var(--border)' ?>;
                  background:<?= $is_selected ? 'rgba(212,168,67,.06)' : 'var(--navy-card)' ?>;
                  border-radius:var(--radius-sm);padding:1rem;margin-bottom:.6rem;
                  transition:all .2s;cursor:pointer"
           onmouseover="this.style.borderColor='var(--gold-dim)'"
           onmouseout="this.style.borderColor='<?= $is_selected ? "var(--gold-dim)" : "var(--border)" ?>'">

          <div class="flex-between">
            <div>
              <div style="display:flex;align-items:center;gap:.5rem">
                <i class="bi <?= $pa['icon'] ?>" style="color:var(--gold);font-size:1.1rem"></i>
                <strong style="font-size:.95rem"><?= htmlspecialchars($pa['account_name']) ?></strong>
                <?php if ($is_selected): ?>
                  <span class="badge badge-credit" style="font-size:.7rem">Selected</span>
                <?php endif; ?>
              </div>
              <div class="monospace text-muted" style="font-size:.76rem;margin-top:.15rem">
                <?= htmlspecialchars($pa['account_number']) ?>
              </div>
            </div>
            <div style="text-align:right">
              <div style="font-weight:700;font-family:'Outfit',sans-serif;color:var(--danger);font-size:1.05rem">
                <?= fmt_inr(abs($pa['balance'])) ?>
                <span style="font-size:.72rem;color:var(--text-muted)">owed</span>
              </div>
              <?php if ($pa['due_date']): ?>
                <div style="font-size:.74rem;color:<?= $overdue ? 'var(--danger)' : ($due_soon ? 'var(--warning)' : 'var(--text-muted)') ?>">
                  <?= $overdue ? '⚠ Overdue' : ($due_soon ? '⏰ Due soon' : 'Due') ?>:
                  <?= fmt_date($pa['due_date']) ?>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <?php if ($pa['next_payment']): ?>
          <div style="margin-top:.5rem;padding-top:.5rem;border-top:1px solid rgba(255,255,255,.05);
                      font-size:.76rem;color:var(--text-muted);display:flex;gap:1rem;flex-wrap:wrap">
            <span>Min. due: <strong style="color:var(--warning)"><?= fmt_inr($pa['next_payment']) ?></strong></span>
            <?php if ($pa['last_payment']): ?>
            <span>Last paid: <strong><?= fmt_inr($pa['last_payment']) ?></strong> on <?= fmt_date($pa['last_payment_date']) ?></span>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </a>
        <?php endforeach; ?>

        <div style="padding-top:.5rem">
          <a href="/Banking/accounts/add_account.php" class="btn btn-outline btn-sm" style="font-size:.78rem;width:100%;justify-content:center">
            <i class="bi bi-plus"></i> Link Another CC / Home Loan
          </a>
        </div>
      </div>
    </div>

    <!-- ── Right: Account details + payment form ── -->
    <div>
      <?php if ($selected_payable): ?>
      <div class="card">
        <div class="card-header">
          <span class="card-title">
            <i class="bi <?= $selected_payable['icon'] ?>"></i>
            <?= htmlspecialchars($selected_payable['account_name']) ?>
          </span>
          <a href="/Banking/accounts/detail.php?id=<?= $selected_payable['account_id'] ?>"
             class="btn btn-outline btn-sm" style="font-size:.76rem">
            <i class="bi bi-eye"></i> View Account
          </a>
        </div>

        <!-- Account stats grid -->
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:.6rem;margin-bottom:1.25rem">
          <?php
          $detail_rows = [
            ['Outstanding', fmt_inr(abs($selected_payable['balance'])), 'danger'],
            ['Min. Due',    fmt_inr($selected_payable['next_payment'] ?? 0), 'warning'],
            ['Last Paid',   $selected_payable['last_payment'] ? fmt_inr($selected_payable['last_payment']) : '—', 'success'],
            ['Due Date',    fmt_date($selected_payable['due_date']), null],
          ];
          if ($selected_payable['credit_limit']) {
              $detail_rows[] = ['Credit Limit',  fmt_inr($selected_payable['credit_limit']), null];
              $detail_rows[] = ['Available',     fmt_inr($selected_payable['credit_limit'] + $selected_payable['balance']), 'success'];
          }
          foreach ($detail_rows as [$lbl, $val, $cls]): ?>
          <div style="background:rgba(255,255,255,.03);border-radius:8px;padding:.65rem .85rem">
            <div class="stat-label" style="font-size:.72rem"><?= $lbl ?></div>
            <div style="font-weight:700;font-family:'Outfit',sans-serif;font-size:.9rem"
                 class="<?= $cls ? 'text-'.$cls : '' ?>">
              <?= $val ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Payment form -->
        <form method="POST" action="/Banking/payments/confirm.php">
          <input type="hidden" name="to_account_id" value="<?= $selected_payable['account_id'] ?>">

          <div class="form-group">
            <label class="form-label">Pay From *</label>
            <select name="from_account_id" class="form-control" required onchange="updateBalanceHint(this)">
              <option value="">— Select a source account —</option>
              <?php foreach ($source_accounts as $sa): ?>
                <option value="<?= $sa['account_id'] ?>"
                        data-balance="<?= $sa['balance'] ?>">
                  <?= htmlspecialchars($sa['account_name']) ?>
                  (<?= htmlspecialchars($sa['account_number']) ?>) —
                  Balance: <?= fmt_inr($sa['balance']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <div id="balance-hint" class="form-hint"></div>
          </div>

          <div class="form-group">
            <label class="form-label">Payment Amount (₹) *</label>
            <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:.5rem">
              <!-- Quick amount buttons -->
              <?php if ($selected_payable['next_payment']): ?>
              <button type="button" class="btn btn-outline btn-sm"
                      onclick="setAmount(<?= $selected_payable['next_payment'] ?>)">
                Min. Due <?= fmt_inr($selected_payable['next_payment']) ?>
              </button>
              <?php endif; ?>
              <button type="button" class="btn btn-outline btn-sm"
                      onclick="setAmount(<?= abs($selected_payable['balance']) ?>)">
                Full Balance <?= fmt_inr(abs($selected_payable['balance'])) ?>
              </button>
            </div>
            <input type="number" name="amount" id="payment-amount" class="form-control" required
                   min="1" step="0.01"
                   value="<?= abs($selected_payable['balance']) ?>"
                   placeholder="Enter amount">
            <div class="form-hint">
              Minimum due: <strong><?= fmt_inr($selected_payable['next_payment'] ?? 0) ?></strong>
              &nbsp;|&nbsp;
              Full outstanding: <strong><?= fmt_inr(abs($selected_payable['balance'])) ?></strong>
            </div>
          </div>

          <div style="text-align:right;margin-top:1rem">
            <button type="submit" class="btn btn-primary btn-lg">
              <i class="bi bi-check-circle"></i> Review & Confirm
            </button>
          </div>
        </form>
      </div>

      <?php else: ?>
      <div class="card text-center" style="padding:3rem">
        <i class="bi bi-arrow-left-circle" style="font-size:2.5rem;color:var(--text-muted)"></i>
        <p class="text-muted mt-2">Select a Credit Card or Home Loan account on the left to continue.</p>
      </div>
      <?php endif; ?>
    </div>

  </div>
  <?php endif; ?>
</main>

<script>
function setAmount(val) {
  document.getElementById('payment-amount').value = val.toFixed(2);
}

function updateBalanceHint(sel) {
  const opt     = sel.options[sel.selectedIndex];
  const bal     = parseFloat(opt.dataset.balance || 0);
  const hintEl  = document.getElementById('balance-hint');
  const amtEl   = document.getElementById('payment-amount');
  const amount  = parseFloat(amtEl?.value || 0);

  if (!sel.value) { hintEl.textContent = ''; return; }
  const avail = '₹' + bal.toLocaleString('en-IN', { minimumFractionDigits: 2 });
  if (bal < amount) {
    hintEl.textContent = '⚠ Insufficient balance — Available: ' + avail;
    hintEl.style.color = 'var(--danger)';
  } else {
    hintEl.textContent = 'Available balance: ' + avail;
    hintEl.style.color = 'var(--success)';
  }
}

// Re-check on amount change too
document.getElementById('payment-amount')?.addEventListener('input', function() {
  const sel = document.querySelector('select[name="from_account_id"]');
  if (sel?.value) updateBalanceHint(sel);
});
</script>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
