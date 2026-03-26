<?php
/**
 * transfers/confirm.php – Confirm and process fund transfer
 */
$page_title = 'Confirm Transfer';
require_once __DIR__ . '/../layout/header.php';
require_login();

$uid = current_user_id();
$pdo = get_db();

$from_id  = (int)($_POST['from_account'] ?? 0);
$payee_id = (int)($_POST['payee_id']     ?? 0);
$amount   = (float)($_POST['amount']     ?? 0);
$date     = $_POST['transfer_date']      ?? date('Y-m-d');
$remarks  = sanitize($_POST['remarks']   ?? '');
$period   = $_POST['periodicity']        ?? 'monthly';
$instances = (int)($_POST['instances']  ?? 0);
$is_si    = $instances > 0;

// Validate inputs
$error = '';

if (!$from_id || !$payee_id || $amount <= 0) {
    $error = 'Invalid transfer details. Please go back and try again.';
}

// Verify from account belongs to user
$from_acc = null;
$payee    = null;

if (!$error) {
    $fa = $pdo->prepare("SELECT * FROM accounts WHERE account_id=? AND user_id=? AND status='active'");
    $fa->execute([$from_id, $uid]);
    $from_acc = $fa->fetch();
    if (!$from_acc) $error = 'Selected source account not found.';
}

if (!$error) {
    $pstmt = $pdo->prepare("SELECT * FROM payees WHERE payee_id=? AND user_id=? AND is_active=1");
    $pstmt->execute([$payee_id, $uid]);
    $payee = $pstmt->fetch();
    if (!$payee) $error = 'Selected payee not found.';
}

$confirmed = isset($_POST['confirm']) && $_POST['confirm'] === '1';

if (!$error && $confirmed) {
    // Check balance
    if ($from_acc['balance'] < $amount) {
        $error = 'Insufficient balance. Available balance: ' . fmt_inr($from_acc['balance']);
    } else {
        $pdo->beginTransaction();
        try {
            $new_balance = $from_acc['balance'] - $amount;
            $ref = 'TFR' . strtoupper(uniqid());

            // Debit source account
            $pdo->prepare(
                "UPDATE accounts SET balance=balance-? WHERE account_id=?"
            )->execute([$amount, $from_id]);

            // Insert transaction
            $pdo->prepare(
                "INSERT INTO transactions
                 (account_id, txn_type, amount, balance_after, description, reference, txn_date)
                 VALUES (?, 'debit', ?, ?, ?, ?, ?)"
            )->execute([
                $from_id, $amount, $new_balance,
                "Fund Transfer to {$payee['payee_name']} – " . ($remarks ?: 'Transfer'),
                $ref, $date . ' 00:00:00'
            ]);

            // Standing instruction
            if ($is_si) {
                $pdo->prepare(
                    "INSERT INTO standing_instructions
                     (user_id, from_account_id, payee_id, amount, periodicity, total_instances, next_run_date)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                )->execute([$uid, $from_id, $payee_id, $amount, $period, $instances, $date]);
            }

            $pdo->commit();
            audit('fund_transfer', $uid);

            set_flash('success', 'Transfer of ' . fmt_inr($amount) . ' to ' . $payee['payee_name'] . ' confirmed! Ref: ' . $ref);
            redirect('/Banking/dashboard.php');
        } catch (Throwable $e) {
            $pdo->rollBack();
            $error = 'Transfer failed. Please try again.';
        }
    }
}
?>

<main class="page-wrapper" style="max-width:600px">
  <h1 class="page-title"><i class="bi bi-check-circle"></i> Confirm Transfer</h1>

  <?php if ($error): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
    <a href="/Banking/transfers/index.php" class="btn btn-outline mt-2">
      <i class="bi bi-arrow-left"></i> Go Back
    </a>
  <?php else: ?>

  <!-- Summary Card -->
  <div class="card mb-2">
    <div style="display:flex;align-items:center;justify-content:center;padding:1.5rem 0;flex-direction:column">
      <div style="font-size:2.5rem;font-family:'Outfit',sans-serif;font-weight:700;color:var(--gold)">
        <?= fmt_inr($amount) ?>
      </div>
      <div class="text-muted mt-1">Transfer Amount</div>
    </div>

    <table style="width:100%;border-collapse:collapse">
      <?php $rows = [
        ['From Account', $from_acc['account_name'] . ' (' . $from_acc['account_number'] . ')'],
        ['Available Balance', fmt_inr($from_acc['balance'])],
        ['To Payee', $payee['payee_name']],
        ['Payee Bank', $payee['bank_name'] . ($payee['branch_name'] ? ', ' . $payee['branch_name'] : '')],
        ['Payee A/C', $payee['account_number']],
        ['IFSC', $payee['ifsc_code']],
        ['Transfer Date', fmt_date($date)],
        ['Remarks', $remarks ?: '—'],
      ];
      if ($is_si) {
        $rows[] = ['Standing Instruction', ucfirst($period) . ' × ' . $instances . ' instance(s)'];
      }
      foreach ($rows as [$label, $val]): ?>
      <tr style="border-top:1px solid var(--border)">
        <td style="padding:.6rem 1rem;color:var(--text-muted);font-size:.85rem;width:40%"><?= $label ?></td>
        <td style="padding:.6rem 1rem;font-weight:500;font-size:.9rem"><?= htmlspecialchars($val) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>

    <?php if ($from_acc['balance'] < $amount): ?>
      <div class="alert alert-danger mt-2"><i class="bi bi-exclamation-triangle"></i>
        Insufficient balance for this transfer.
      </div>
    <?php endif; ?>
  </div>

  <form method="POST">
    <!-- Re-post all values -->
    <input type="hidden" name="from_account"  value="<?= $from_id ?>">
    <input type="hidden" name="payee_id"      value="<?= $payee_id ?>">
    <input type="hidden" name="amount"        value="<?= $amount ?>">
    <input type="hidden" name="transfer_date" value="<?= htmlspecialchars($date) ?>">
    <input type="hidden" name="remarks"       value="<?= htmlspecialchars($remarks) ?>">
    <input type="hidden" name="periodicity"   value="<?= htmlspecialchars($period) ?>">
    <input type="hidden" name="instances"     value="<?= $is_si ? $instances : 0 ?>">
    <input type="hidden" name="confirm"       value="1">

    <div style="display:flex;gap:.75rem;justify-content:flex-end">
      <a href="/Banking/transfers/index.php" class="btn btn-outline">
        <i class="bi bi-arrow-left"></i> Cancel
      </a>
      <button type="submit" class="btn btn-primary btn-lg"
              <?= $from_acc['balance'] < $amount ? 'disabled' : '' ?>>
        <i class="bi bi-check-circle"></i> Confirm Transfer
      </button>
    </div>
  </form>
  <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
