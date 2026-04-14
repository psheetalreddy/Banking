<?php
/**
 * transfers/add_payee.php – Add a new payee
 */
$page_title = 'Add Payee';
require_once __DIR__ . '/../layout/header.php';
require_login();

$uid   = current_user_id();
$pdo   = get_db();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = sanitize($_POST['payee_name']     ?? '');
    $bank    = sanitize($_POST['bank_name']      ?? '');
    $branch  = sanitize($_POST['branch_name']    ?? '');
    $accno   = sanitize($_POST['account_number'] ?? '');
    $ifsc    = strtoupper(sanitize($_POST['ifsc_code'] ?? ''));

    if (!$name || !$bank || !$accno || !$ifsc) {
        $error = 'Payee name, bank name, account number, and IFSC code are required.';
    } elseif (!preg_match('/^ARCA0[A-Z0-9]{2}\d{4}$/', $ifsc)) {
        $error = 'Enter a valid IFSC code (e.g. ARCA0001234).';
    } elseif (!preg_match('/^[A-Z]{3}\d{10}$/', $accno)) {
        $error = 'Enter a valid account number (e.g. SAV0009876543).';
    } else {
        $acc_check = $pdo->prepare("SELECT 1 FROM accounts WHERE account_number = ?");
        $acc_check->execute([$accno]);
        if (!$acc_check->fetch()) {
            $error = "Account doesn't exist.";
        } else {
            $pdo->prepare(
                "INSERT INTO payees (user_id, payee_name, bank_name, branch_name, account_number, ifsc_code)
                 VALUES (?, ?, ?, ?, ?, ?)"
            )->execute([$uid, $name, $bank, $branch, $accno, $ifsc]);
            $new_id = $pdo->lastInsertId();
            audit('payee_added', $uid);
            set_flash('success', "Payee \"$name\" added successfully.");
            redirect('/Banking/transfers/index.php?payee_id=' . $new_id);
        }
    }
}
?>

<main class="page-wrapper" style="max-width:600px">
  <div class="flex-between mb-2">
    <h1 class="page-title"><i class="bi bi-person-plus"></i> Add New Payee</h1>
    <a href="/Banking/transfers/index.php" class="btn btn-outline btn-sm">
      <i class="bi bi-arrow-left"></i> Back
    </a>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="card">
    <form method="POST">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Payee Full Name *</label>
          <input type="text" name="payee_name" class="form-control" required
                 value="<?= htmlspecialchars($_POST['payee_name'] ?? '') ?>" placeholder="John Doe">
        </div>
        <div class="form-group">
          <label class="form-label">Bank Name *</label>
          <input type="text" name="bank_name" class="form-control" required
                 value="<?= htmlspecialchars($_POST['bank_name'] ?? '') ?>" placeholder="ARCA Bank">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Branch Name</label>
          <input type="text" name="branch_name" class="form-control"
                 value="<?= htmlspecialchars($_POST['branch_name'] ?? '') ?>" placeholder="Chennai">
        </div>
        <div class="form-group">
          <label class="form-label">IFSC Code *</label>
          <input type="text" name="ifsc_code" class="form-control" required
                 value="<?= htmlspecialchars($_POST['ifsc_code'] ?? '') ?>"
                 placeholder="ARCA0001234" maxlength="11" style="text-transform:uppercase">
          <div class="form-hint">Format: AAAA0XXXXXX</div>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Account Number *</label>
        <input type="text" name="account_number" class="form-control" required
               value="<?= htmlspecialchars($_POST['account_number'] ?? '') ?>"
               placeholder="SAV0009876543" maxlength="13" class="monospace" style="text-transform:uppercase">
      </div>

      <div class="form-group">
        <label class="form-label">Confirm Account Number *</label>
        <input type="text" class="form-control" placeholder="SAV0009876543"
               oninput="checkAccMatch(this)" maxlength="13" class="monospace" style="text-transform:uppercase">
        <div id="acc-match-msg" class="form-hint"></div>
      </div>

      <div style="text-align:right;margin-top:.5rem">
        <a href="/Banking/transfers/index.php" class="btn btn-outline">Cancel</a>
        <button type="submit" class="btn btn-primary" style="margin-left:.5rem" id="save-payee-btn">
          <i class="bi bi-person-check"></i> Save Payee
        </button>
      </div>
    </form>
  </div>
</main>

<script>
function checkAccMatch(confirmInput) {
  const original = document.querySelector('input[name="account_number"]').value;
  const msg = document.getElementById('acc-match-msg');
  if (!confirmInput.value) { msg.textContent = ''; return; }
  if (confirmInput.value === original) {
    msg.textContent = '✓ Account numbers match';
    msg.style.color = 'var(--success)';
  } else {
    msg.textContent = '✗ Account numbers do not match';
    msg.style.color = 'var(--danger)';
  }
}
</script>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
