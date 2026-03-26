<?php
/**
 * accounts/index.php – Account categories accordion
 */
$page_title = 'Account Management';
require_once __DIR__ . '/../layout/header.php';
require_login();

$uid = current_user_id();
$pdo = get_db();

// Handle unlink
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unlink'])) {
    $acc_id = (int)$_POST['account_id'];
    // Verify ownership
    $chk = $pdo->prepare("SELECT account_id FROM accounts WHERE account_id=? AND user_id=?");
    $chk->execute([$acc_id, $uid]);
    if ($chk->fetch()) {
        $pdo->prepare("UPDATE accounts SET status='inactive' WHERE account_id=?")->execute([$acc_id]);
        audit('account_unlinked', $uid);
        set_flash('success', 'Account unlinked successfully.');
    }
    redirect('/Banking/accounts/index.php');
}

// Fetch all active accounts grouped by category
$stmt = $pdo->prepare(
    "SELECT a.account_id, a.account_number, a.account_name, a.balance,
            a.credit_limit, a.due_date, a.next_payment, a.category_id,
            ac.name AS category, ac.icon, ac.display_order
     FROM accounts a
     JOIN account_categories ac ON a.category_id = ac.category_id
     WHERE a.user_id = ? AND a.status = 'active'
     ORDER BY ac.display_order, a.linked_at"
);
$stmt->execute([$uid]);
$all_accounts = $stmt->fetchAll();

// Group by category
$grouped = [];
foreach ($all_accounts as $acc) {
    $grouped[$acc['category']][] = $acc;
}
?>

<main class="page-wrapper">
  <h1 class="page-title"><i class="bi bi-bank2"></i> Account Management</h1>

  <?php if (empty($grouped)): ?>
    <div class="card text-center" style="padding:3rem">
      <i class="bi bi-bank2" style="font-size:3rem;color:var(--text-muted)"></i>
      <p class="text-muted mt-2">No accounts linked to your profile.</p>
    </div>
  <?php else: ?>

  <?php foreach ($grouped as $category => $accounts):
    $first = $accounts[0];
    $total = array_sum(array_column($accounts, 'balance'));
  ?>
  <div class="accordion-item open">
    <div class="accordion-header">
      <div class="acc-left">
        <i class="bi <?= $first['icon'] ?> accordion-icon"></i>
        <div>
          <div class="accordion-cat-name"><?= htmlspecialchars($category) ?></div>
          <div class="accordion-meta">
            <?= count($accounts) ?> account<?= count($accounts)>1?'s':'' ?> &nbsp;·&nbsp;
            Total: <strong class="<?= $total >= 0 ? 'text-success' : 'text-danger' ?>"><?= fmt_inr($total) ?></strong>
          </div>
        </div>
      </div>
      <i class="bi bi-chevron-down accordion-chevron"></i>
    </div>

    <div class="accordion-body">
      <?php foreach ($accounts as $acc): ?>
      <div class="acc-account-row">
        <div class="acc-account-info">
          <div class="acc-name"><?= htmlspecialchars($acc['account_name']) ?></div>
          <div class="acc-num">A/C: <?= htmlspecialchars($acc['account_number']) ?></div>
          <?php if ($acc['due_date']): ?>
            <div style="font-size:.75rem;color:var(--warning)">
              <i class="bi bi-calendar-event"></i> Due: <?= fmt_date($acc['due_date']) ?>
              &nbsp;|&nbsp; Next Payment: <?= fmt_inr($acc['next_payment'] ?? 0) ?>
            </div>
          <?php endif; ?>
          <?php if ($acc['credit_limit']): ?>
            <div style="font-size:.75rem;color:var(--text-muted)">
              Limit: <?= fmt_inr($acc['credit_limit']) ?>
              &nbsp;|&nbsp; Available: <?= fmt_inr($acc['credit_limit'] + $acc['balance']) ?>
            </div>
          <?php endif; ?>
        </div>

        <div style="display:flex;align-items:center;gap:1.25rem">
          <div class="acc-balance <?= $acc['balance'] >= 0 ? 'pos' : 'neg' ?>">
            <?= fmt_inr($acc['balance']) ?>
          </div>
          <div class="acc-actions">
            <!-- View Details – disabled (Sprint-2) -->
            <span class="btn btn-outline btn-sm under-construction"
                  title="Coming in Sprint-2">
              <i class="bi bi-eye"></i>
              <span style="font-size:.72rem">Under Construction</span>
            </span>

            <!-- Unlink -->
            <form method="POST" style="display:inline">
              <input type="hidden" name="account_id" value="<?= $acc['account_id'] ?>">
              <input type="hidden" name="unlink" value="1">
              <button type="submit" class="btn btn-danger btn-sm"
                      data-confirm="Unlink account <?= htmlspecialchars($acc['account_number']) ?>?">
                <i class="bi bi-unlink"></i>
              </button>
            </form>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
