<?php
/**
 * accounts/edit_account.php
 * Edit account name, limits, due dates. Unlink account.
 */
$page_title = 'Account Settings';
require_once __DIR__ . '/../layout/header.php';
require_login();

$uid    = current_user_id();
$pdo    = get_db();
$acc_id = (int)($_GET['id'] ?? 0);

if (!$acc_id) redirect('/Banking/accounts/index.php');

$stmt = $pdo->prepare(
    "SELECT a.*, ac.name AS category, ac.icon
     FROM accounts a
     JOIN account_categories ac ON a.category_id = ac.category_id
     WHERE a.account_id = ? AND a.user_id = ? AND a.status = 'active'"
);
$stmt->execute([$acc_id, $uid]);
$acc = $stmt->fetch();
if (!$acc) redirect('/Banking/accounts/index.php');

$is_loan = in_array($acc['category'], ['Credit Card', 'Home Loan']);
$is_cc   = $acc['category'] === 'Credit Card';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update';

    if ($action === 'unlink') {
        $pdo->prepare("UPDATE accounts SET status='inactive' WHERE account_id=? AND user_id=?")
            ->execute([$acc_id, $uid]);
        audit('account_unlinked', $uid);
        set_flash('success', 'Account "' . $acc['account_name'] . '" has been unlinked.');
        redirect('/Banking/accounts/index.php');
    }

    // update
    $acc_name     = sanitize($_POST['account_name'] ?? '');
    $credit_limit = strlen($_POST['credit_limit'] ?? '')  ? (float)$_POST['credit_limit']  : null;
    $due_date     = strlen($_POST['due_date'] ?? '')       ? $_POST['due_date']              : null;
    $next_payment = strlen($_POST['next_payment'] ?? '')   ? (float)$_POST['next_payment']   : null;

    if (!$acc_name) {
        $error = 'Account name is required.';
    } else {
        $pdo->prepare(
            "UPDATE accounts SET account_name=?, credit_limit=?, due_date=?, next_payment=?
             WHERE account_id=? AND user_id=?"
        )->execute([$acc_name, $credit_limit, $due_date, $next_payment, $acc_id, $uid]);

        audit('account_updated', $uid);
        set_flash('success', 'Account settings saved.');
        redirect('/Banking/accounts/detail.php?id=' . $acc_id);
    }
}
?>

<main class="page-wrapper" style="max-width:640px">
  <div class="flex-between mb-2">
    <h1 class="page-title"><i class="bi bi-gear"></i> Account Settings</h1>
    <a href="/Banking/accounts/detail.php?id=<?= $acc_id ?>" class="btn btn-outline btn-sm">
      <i class="bi bi-arrow-left"></i> Back
    </a>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- Account info banner -->
  <div class="alert alert-info mb-2" style="font-size:.85rem">
    <i class="bi <?= $acc['icon'] ?>"></i>
    <div>
      <strong><?= htmlspecialchars($acc['account_name']) ?></strong> &nbsp;·&nbsp;
      <span class="monospace"><?= htmlspecialchars($acc['account_number']) ?></span> &nbsp;·&nbsp;
      <?= htmlspecialchars($acc['category']) ?>
    </div>
  </div>

  <div class="card mb-2">
    <div class="card-header">
      <span class="card-title"><i class="bi bi-pencil"></i> Edit Details</span>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="update">
      <div class="form-group">
        <label class="form-label">Account Label / Name</label>
        <input type="text" name="account_name" class="form-control" required
               value="<?= htmlspecialchars($acc['account_name']) ?>">
      </div>

      <?php if ($is_loan): ?>
      <div class="form-row">
        <?php if ($is_cc): ?>
        <div class="form-group">
          <label class="form-label">Credit Limit (₹)</label>
          <input type="number" name="credit_limit" class="form-control" step="0.01" min="0"
                 value="<?= htmlspecialchars($acc['credit_limit'] ?? '') ?>" placeholder="e.g. 300000">
        </div>
        <?php endif; ?>
        <div class="form-group">
          <label class="form-label">Payment Due Date</label>
          <input type="date" name="due_date" class="form-control"
                 value="<?= htmlspecialchars($acc['due_date'] ?? '') ?>">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Minimum / Next Payment Due (₹)</label>
        <input type="number" name="next_payment" class="form-control" step="0.01" min="0"
               value="<?= htmlspecialchars($acc['next_payment'] ?? '') ?>" placeholder="Minimum amount due">
      </div>
      <?php else: ?>
        <!-- Placeholder inputs to avoid form fields mismatch -->
        <input type="hidden" name="credit_limit" value="">
        <input type="hidden" name="due_date" value="">
        <input type="hidden" name="next_payment" value="">
      <?php endif; ?>

      <div style="text-align:right">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> Save Changes</button>
      </div>
    </form>
  </div>

  <!-- Danger Zone -->
  <div class="card" style="border-color:rgba(240,94,106,.3)">
    <div class="card-header">
      <span class="card-title" style="color:var(--danger)"><i class="bi bi-exclamation-triangle"></i> Danger Zone</span>
    </div>
    <p class="text-muted" style="font-size:.85rem;margin-bottom:1rem">
      Unlinking removes this account from your dashboard. Existing transaction history is preserved.
      You can re-link the account by adding it again.
    </p>
    <form method="POST" style="display:inline">
      <input type="hidden" name="action" value="unlink">
      <button type="submit" class="btn btn-danger"
              data-confirm="Unlink account <?= htmlspecialchars($acc['account_number']) ?>? This cannot be undone from the UI.">
        <i class="bi bi-unlink"></i> Unlink This Account
      </button>
    </form>
  </div>
</main>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
