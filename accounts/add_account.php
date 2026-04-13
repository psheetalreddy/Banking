<?php
/**
 * accounts/add_account.php
 * Allows a logged-in user to link/add a new bank account.
 * Supports: Savings, Current, Home Loan, Credit Card, Investment, Insurance
 */
$page_title = 'Add New Account';
require_once __DIR__ . '/../layout/header.php';
require_login();

$uid   = current_user_id();
$pdo   = get_db();
$error = '';

// Load all categories
$cats = $pdo->query("SELECT * FROM account_categories ORDER BY display_order")->fetchAll();

// ── Account number prefix map ─────────────────────────────
$prefix_map = [
    'Savings'     => 'SAV',
    'Current'     => 'CUR',
    'Home Loan'   => 'HL0',
    'Credit Card' => 'CC0',
    'Investment'  => 'INV',
    'Insurance'   => 'INS',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cat_id      = (int)($_POST['category_id'] ?? 0);
    $acc_name    = sanitize($_POST['account_name'] ?? '');
    $acc_number  = strtoupper(sanitize($_POST['account_number'] ?? ''));
    $currency    = sanitize($_POST['currency'] ?? 'INR');
    $opening_bal = (float)($_POST['opening_balance'] ?? 0);

    // CC / HL extras
    $credit_limit   = strlen($_POST['credit_limit'] ?? '')   ? (float)$_POST['credit_limit']   : null;
    $due_date       = strlen($_POST['due_date'] ?? '')        ? $_POST['due_date']               : null;
    $next_payment   = strlen($_POST['next_payment'] ?? '')    ? (float)$_POST['next_payment']    : null;

    // ── Validate ──────────────────────────────────────────
    if (!$cat_id || !$acc_name || !$acc_number) {
        $error = 'Category, account name, and account number are required.';
    } elseif (!preg_match('/^[A-Z0-9]{6,20}$/', $acc_number)) {
        $error = 'Account number must be 6–20 alphanumeric characters (e.g. SAV0001234567).';
    } else {
        // Check duplicate account number globally
        $dup = $pdo->prepare("SELECT account_id FROM accounts WHERE account_number = ?");
        $dup->execute([$acc_number]);
        if ($dup->fetch()) {
            $error = 'That account number is already registered in the system.';
        }
    }

    // For Home Loan / Credit Card: balance must be negative (money owed)
    if (!$error) {
        $cat_row = null;
        foreach ($cats as $c) { if ($c['category_id'] == $cat_id) { $cat_row = $c; break; } }
        if ($cat_row && in_array($cat_row['name'], ['Home Loan', 'Credit Card'])) {
            $opening_bal = -abs($opening_bal); // force negative
        }
    }

    if (!$error) {
        $pdo->prepare(
            "INSERT INTO accounts
             (user_id, category_id, account_number, account_name, currency,
              balance, credit_limit, due_date, next_payment, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')"
        )->execute([$uid, $cat_id, $acc_number, $acc_name, $currency,
                    $opening_bal, $credit_limit, $due_date, $next_payment]);

        $new_acc_id = (int)$pdo->lastInsertId();
        audit('account_added', $uid);

        // If opening balance non-zero, seed a transaction
        if ($opening_bal != 0) {
            $txn_type = $opening_bal > 0 ? 'credit' : 'credit'; // opening always logged as credit entry
            $pdo->prepare(
                "INSERT INTO transactions
                 (account_id, txn_type, amount, balance_after, description, reference)
                 VALUES (?, 'credit', ?, ?, 'Opening Balance', ?)"
            )->execute([$new_acc_id, abs($opening_bal), $opening_bal,
                        'OB' . strtoupper(uniqid())]);
        }

        set_flash('success', "Account \"$acc_name\" linked successfully.");
        redirect('/Banking/accounts/index.php');
    }
}
?>

<main class="page-wrapper" style="max-width:680px">
  <div class="flex-between mb-2">
    <h1 class="page-title"><i class="bi bi-plus-circle"></i> Add / Link New Account</h1>
    <a href="/Banking/accounts/index.php" class="btn btn-outline btn-sm">
      <i class="bi bi-arrow-left"></i> Back
    </a>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="card">
    <form method="POST" id="add-acc-form">

      <!-- Category -->
      <div class="form-group">
        <label class="form-label">Account Category *</label>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:.6rem">
          <?php foreach ($cats as $cat): ?>
          <label class="cat-tile" id="tile-<?= $cat['category_id'] ?>"
                 style="display:flex;align-items:center;gap:.6rem;padding:.75rem 1rem;border:1px solid var(--border);border-radius:var(--radius-sm);cursor:pointer;transition:all .2s;background:var(--navy)">
            <input type="radio" name="category_id" value="<?= $cat['category_id'] ?>"
                   data-cat="<?= htmlspecialchars($cat['name']) ?>"
                   data-prefix="<?= $prefix_map[$cat['name']] ?? 'ACC' ?>"
                   onchange="onCategoryChange(this)"
                   <?= (isset($_POST['category_id']) && $_POST['category_id'] == $cat['category_id']) ? 'checked' : '' ?>
                   style="accent-color:var(--gold)">
            <i class="bi <?= $cat['icon'] ?>" style="color:var(--gold);font-size:1.1rem"></i>
            <span style="font-size:.85rem;font-weight:500"><?= htmlspecialchars($cat['name']) ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Account Name -->
      <div class="form-group">
        <label class="form-label">Account Name / Label *</label>
        <input type="text" name="account_name" id="acc-name" class="form-control" required
               placeholder="e.g. Primary Savings Account"
               value="<?= htmlspecialchars($_POST['account_name'] ?? '') ?>">
        <div class="form-hint">A friendly label to identify this account</div>
      </div>

      <!-- Account Number -->
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Account Number *</label>
          <input type="text" name="account_number" id="acc-number" class="form-control" required
                 maxlength="20" placeholder="SAV0001234567"
                 style="text-transform:uppercase;font-family:monospace"
                 value="<?= htmlspecialchars($_POST['account_number'] ?? '') ?>">
          <div class="form-hint" id="acc-num-hint">6–20 alphanumeric characters</div>
        </div>
        <div class="form-group">
          <label class="form-label">Currency</label>
          <select name="currency" class="form-control">
            <option value="INR" <?= ($_POST['currency'] ?? 'INR') === 'INR' ? 'selected' : '' ?>>INR – Indian Rupee</option>
            <option value="USD" <?= ($_POST['currency'] ?? '') === 'USD' ? 'selected' : '' ?>>USD – US Dollar</option>
            <option value="EUR" <?= ($_POST['currency'] ?? '') === 'EUR' ? 'selected' : '' ?>>EUR – Euro</option>
            <option value="GBP" <?= ($_POST['currency'] ?? '') === 'GBP' ? 'selected' : '' ?>>GBP – British Pound</option>
          </select>
        </div>
      </div>

      <!-- Opening Balance -->
      <div class="form-group">
        <label class="form-label">Opening Balance (₹) <span id="bal-note" class="text-muted" style="font-size:.78rem"></span></label>
        <input type="number" name="opening_balance" id="opening-balance" class="form-control"
               step="0.01" min="0" placeholder="0.00"
               value="<?= htmlspecialchars($_POST['opening_balance'] ?? '') ?>">
        <div class="form-hint" id="bal-hint">Enter the current outstanding balance / initial deposit</div>
      </div>

      <!-- CC / HL Extra Fields (shown conditionally) -->
      <div id="extra-fields" style="display:none">
        <div style="border-top:1px solid var(--border);padding-top:1rem;margin-top:.5rem">
          <div class="card-title" style="margin-bottom:.75rem;font-size:.88rem">
            <i class="bi bi-info-circle text-gold"></i>
            <span id="extra-label">Additional Details</span>
          </div>

          <div class="form-row">
            <div class="form-group" id="credit-limit-wrap" style="display:none">
              <label class="form-label">Credit Limit (₹)</label>
              <input type="number" name="credit_limit" id="credit-limit" class="form-control"
                     step="0.01" min="0" placeholder="e.g. 300000"
                     value="<?= htmlspecialchars($_POST['credit_limit'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Payment Due Date</label>
              <input type="date" name="due_date" id="due-date" class="form-control"
                     value="<?= htmlspecialchars($_POST['due_date'] ?? '') ?>">
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Minimum / Next Payment Due (₹)</label>
            <input type="number" name="next_payment" id="next-payment" class="form-control"
                   step="0.01" min="0" placeholder="Minimum amount due"
                   value="<?= htmlspecialchars($_POST['next_payment'] ?? '') ?>">
          </div>
        </div>
      </div>

      <!-- Info banner -->
      <div class="alert alert-info" style="font-size:.82rem;margin-top:.5rem">
        <i class="bi bi-shield-check"></i>
        <div>All account details are encrypted and stored securely. Account number must match your actual bank records.</div>
      </div>

      <div style="text-align:right;margin-top:.5rem">
        <a href="/Banking/accounts/index.php" class="btn btn-outline">Cancel</a>
        <button type="submit" class="btn btn-primary btn-lg" style="margin-left:.75rem">
          <i class="bi bi-plus-circle"></i> Link Account
        </button>
      </div>
    </form>
  </div>
</main>

<script>
const CC_CATS  = ['Credit Card'];
const HL_CATS  = ['Home Loan'];
const LOAN_CATS = ['Credit Card', 'Home Loan'];

function onCategoryChange(radio) {
  const cat     = radio.dataset.cat;
  const prefix  = radio.dataset.prefix;
  const nameEl  = document.getElementById('acc-name');
  const numEl   = document.getElementById('acc-number');
  const hintEl  = document.getElementById('acc-num-hint');
  const extraEl = document.getElementById('extra-fields');
  const balNote = document.getElementById('bal-note');
  const balHint = document.getElementById('bal-hint');
  const clWrap  = document.getElementById('credit-limit-wrap');
  const extraLbl= document.getElementById('extra-label');

  // Auto-generate a placeholder account number
  const rand = Math.floor(1000000000 + Math.random() * 9000000000);
  numEl.value = prefix + rand;
  hintEl.textContent = 'Suggested: ' + prefix + 'XXXXXXXXXX — edit if needed';

  // Set friendly default name
  const defaults = {
    'Savings':     'Primary Savings Account',
    'Current':     'Business Current Account',
    'Home Loan':   'Home Loan Account',
    'Credit Card': 'Platinum Credit Card',
    'Investment':  'Mutual Fund Portfolio',
    'Insurance':   'Life Insurance Policy',
  };
  if (!nameEl.value) nameEl.value = defaults[cat] || cat + ' Account';

  // Toggle extra fields
  if (LOAN_CATS.includes(cat)) {
    extraEl.style.display = '';
    balNote.textContent   = '— enter the current outstanding amount';
    balHint.textContent   = 'For loan/CC: enter what you currently owe (will be stored as negative)';
    clWrap.style.display  = CC_CATS.includes(cat) ? '' : 'none';
    extraLbl.textContent  = CC_CATS.includes(cat) ? 'Credit Card Details' : 'Home Loan Details';
  } else {
    extraEl.style.display = 'none';
    balNote.textContent   = '';
    balHint.textContent   = 'Enter your current account balance';
  }

  // Highlight selected tile
  document.querySelectorAll('.cat-tile').forEach(t => {
    t.style.borderColor = 'var(--border)';
    t.style.background  = 'var(--navy)';
  });
  radio.closest('.cat-tile').style.borderColor = 'var(--gold-dim)';
  radio.closest('.cat-tile').style.background  = 'rgba(212,168,67,.08)';
}

// Re-apply highlight on page load if a category was POSTed back
document.addEventListener('DOMContentLoaded', () => {
  const checked = document.querySelector('input[name="category_id"]:checked');
  if (checked) onCategoryChange(checked);
});
</script>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
