<?php
/**
 * transfers/index.php – Fund Transfer main page
 */
$page_title = 'Fund Transfer';
require_once __DIR__ . '/../layout/header.php';
require_login();

$uid = current_user_id();
$pdo = get_db();

// Source accounts (savings/current only)
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

// Payees
$pay_stmt = $pdo->prepare(
    "SELECT * FROM payees WHERE user_id=? AND is_active=1 ORDER BY payee_name"
);
$pay_stmt->execute([$uid]);
$payees = $pay_stmt->fetchAll();

// Handle payee delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_payee'])) {
    $pid = (int)$_POST['payee_id'];
    $pdo->prepare("UPDATE payees SET is_active=0 WHERE payee_id=? AND user_id=?")
        ->execute([$pid, $uid]);
    set_flash('success', 'Payee removed successfully.');
    redirect('/Banking/transfers/index.php');
}

// Pre-selected payee from add_payee redirect
$selected_payee_id = (int)($_GET['payee_id'] ?? $_POST['payee_id'] ?? 0);
?>

<main class="page-wrapper">
  <h1 class="page-title"><i class="bi bi-arrow-left-right"></i> Fund Transfer</h1>

  <div class="grid-2">

    <!-- Left: Source Account Selection -->
    <div>
      <div class="card mb-2">
        <div class="card-header">
          <span class="card-title"><i class="bi bi-wallet2"></i> Transfer From</span>
        </div>
        <?php foreach ($source_accounts as $acc): ?>
        <label style="display:flex;align-items:center;gap:.75rem;padding:.75rem;border:1px solid var(--border);border-radius:var(--radius-sm);margin-bottom:.5rem;cursor:pointer;transition:background var(--transition)"
               onmouseover="this.style.background='rgba(255,255,255,.03)'" onmouseout="this.style.background=''">
          <input type="radio" name="from_account" form="transfer-form" value="<?= $acc['account_id'] ?>"
                 <?= $acc === reset($source_accounts) ? 'checked' : '' ?> style="accent-color:var(--gold)">
          <div style="flex:1">
            <div style="font-weight:600;font-size:.92rem"><?= htmlspecialchars($acc['account_name']) ?></div>
            <div class="monospace text-muted" style="font-size:.78rem"><?= htmlspecialchars($acc['account_number']) ?></div>
          </div>
          <div style="font-weight:700;font-family:'Outfit',sans-serif;color:var(--success)">
            <?= fmt_inr($acc['balance']) ?>
          </div>
        </label>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Right: Payees -->
    <div>
      <div class="card">
        <div class="card-header">
          <span class="card-title"><i class="bi bi-people"></i> Saved Payees</span>
          <a href="/Banking/transfers/add_payee.php" class="btn btn-primary btn-sm">
            <i class="bi bi-plus"></i> Add Payee
          </a>
        </div>

        <?php if (empty($payees)): ?>
          <p class="text-muted text-center" style="padding:1.5rem">
            No payees saved yet. <a href="/Banking/transfers/add_payee.php">Add your first payee.</a>
          </p>
        <?php else: ?>
        <?php foreach ($payees as $p): ?>
        <div class="card" style="margin-bottom:.6rem;padding:1rem;cursor:pointer"
             id="payee-card-<?= $p['payee_id'] ?>">
          <div style="display:flex;align-items:flex-start;gap:.75rem">
            <div style="width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--navy-light),var(--navy-mid));display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--gold);font-size:1rem;flex-shrink:0">
              <?= strtoupper(substr($p['payee_name'], 0, 1)) ?>
            </div>
            <div style="flex:1">
              <div style="font-weight:600"><?= htmlspecialchars($p['payee_name']) ?></div>
              <div class="text-muted" style="font-size:.8rem"><?= htmlspecialchars($p['bank_name']) ?></div>
              <!-- Expandable details -->
              <div id="payee-info-<?= $p['payee_id'] ?>" class="hidden mt-1" style="font-size:.8rem;color:var(--text-muted);line-height:1.8">
                Branch: <?= htmlspecialchars($p['branch_name'] ?? '—') ?><br>
                A/C: <span class="monospace"><?= htmlspecialchars($p['account_number']) ?></span><br>
                IFSC: <span class="monospace"><?= htmlspecialchars($p['ifsc_code']) ?></span>
              </div>
            </div>
            <div style="display:flex;flex-direction:column;gap:.35rem;align-items:flex-end">
              <button type="button" class="btn btn-outline btn-sm"
                      onclick="toggleEl('payee-info-<?= $p['payee_id'] ?>')">
                <i class="bi bi-info-circle"></i>
              </button>
              <label class="btn btn-success btn-sm" style="cursor:pointer">
                <input type="radio" name="payee_id" form="transfer-form"
                       value="<?= $p['payee_id'] ?>"
                       <?= $selected_payee_id === $p['payee_id'] ? 'checked' : '' ?>
                       style="display:none"
                       onchange="document.getElementById('selected-payee-name').textContent='<?= htmlspecialchars(addslashes($p['payee_name'])) ?>';document.getElementById('transfer-amount-section').style.display=''">
                <i class="bi bi-check-circle"></i> Select
              </label>
              <form method="POST" style="display:inline">
                <input type="hidden" name="payee_id" value="<?= $p['payee_id'] ?>">
                <input type="hidden" name="delete_payee" value="1">
                <button type="submit" class="btn btn-danger btn-sm"
                        data-confirm="Remove payee <?= htmlspecialchars($p['payee_name']) ?>?">
                  <i class="bi bi-trash"></i>
                </button>
              </form>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Transfer Amount Section (shown after payee selected) -->
  <div id="transfer-amount-section" style="<?= $selected_payee_id ? '' : 'display:none' ?>">
    <div class="card mt-2">
      <div class="card-header">
        <span class="card-title"><i class="bi bi-send"></i> Transfer Details</span>
        <span class="text-muted" style="font-size:.85rem">To: <strong id="selected-payee-name">
          <?php if ($selected_payee_id):
            $sp = array_filter($payees, fn($p) => $p['payee_id']==$selected_payee_id);
            echo htmlspecialchars(reset($sp)['payee_name'] ?? '');
          endif; ?>
        </strong></span>
      </div>

      <form id="transfer-form" method="POST" action="/Banking/transfers/confirm.php">
        <input type="hidden" name="payee_id" id="hidden-payee-id" value="<?= $selected_payee_id ?>">

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Amount (₹)</label>
            <input type="number" name="amount" class="form-control" min="1" step="0.01"
                   required placeholder="0.00">
          </div>
          <div class="form-group">
            <label class="form-label">Transfer Date</label>
            <input type="date" name="transfer_date" class="form-control"
                   min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Remarks (optional)</label>
          <input type="text" name="remarks" class="form-control" placeholder="Payment for…">
        </div>

        <!-- Standing Instruction -->
        <div class="form-group">
          <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer">
            <input type="checkbox" id="si-toggle" onclick="toggleEl('si-options')" style="accent-color:var(--gold)">
            Set up Standing Instruction (recurring transfer)
          </label>
        </div>
        <div id="si-options" class="hidden card" style="padding:1rem;background:rgba(212,168,67,.04);border-color:var(--gold-dim)">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Frequency</label>
              <select name="periodicity" class="form-control">
                <option value="monthly">Monthly</option>
                <option value="weekly">Weekly</option>
                <option value="yearly">Yearly</option>
                <option value="daily">Daily</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Number of Instances</label>
              <input type="number" name="instances" class="form-control" min="1" max="60"
                     value="12" placeholder="12">
            </div>
          </div>
        </div>

        <div style="text-align:right;margin-top:1rem">
          <a href="/Banking/transfers/index.php" class="btn btn-outline">Cancel</a>
          <button type="submit" class="btn btn-primary" style="margin-left:.5rem">
            <i class="bi bi-check-circle"></i> Review & Confirm
          </button>
        </div>
      </form>
    </div>
  </div>
</main>

<script>
// Keep hidden payee_id in sync with radio
document.querySelectorAll('input[name="payee_id"][form="transfer-form"]').forEach(r => {
  r.addEventListener('change', () => {
    document.getElementById('hidden-payee-id').value = r.value;
  });
});
</script>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
