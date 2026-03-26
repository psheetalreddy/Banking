<?php
/**
 * payments/confirm.php – Process and confirm a CC/HL payment
 */
$page_title = 'Payment Confirmation';
require_once __DIR__ . '/../layout/header.php';
require_login();

$uid = current_user_id();
$pdo = get_db();

$to_id   = (int)($_POST['to_account_id']   ?? 0);
$from_id = (int)($_POST['from_account_id'] ?? 0);
$amount  = (float)($_POST['amount']        ?? 0);

$error   = '';
$from_acc = null;
$to_acc   = null;

if (!$to_id || !$from_id || $amount <= 0) {
    $error = 'Invalid payment details. Please go back.';
}

// Load accounts (verify ownership)
if (!$error) {
    $fa = $pdo->prepare("SELECT a.*, ac.name AS category, ac.icon
                          FROM accounts a JOIN account_categories ac ON a.category_id=ac.category_id
                          WHERE a.account_id=? AND a.user_id=? AND a.status='active'");
    $fa->execute([$from_id, $uid]);
    $from_acc = $fa->fetch();

    $ta = $pdo->prepare("SELECT a.*, ac.name AS category, ac.icon
                          FROM accounts a JOIN account_categories ac ON a.category_id=ac.category_id
                          WHERE a.account_id=? AND a.user_id=? AND a.status='active'");
    $ta->execute([$to_id, $uid]);
    $to_acc = $ta->fetch();

    if (!$from_acc || !$to_acc) $error = 'One or both accounts could not be found.';
}

// Check balance
if (!$error && $from_acc['balance'] < $amount) {
    $error = 'Insufficient balance in ' . $from_acc['account_name'] .
             '. Available: ' . fmt_inr($from_acc['balance']);
}

$confirmed = isset($_POST['confirm']) && $_POST['confirm'] === '1';

if (!$error && $confirmed) {
    $pdo->beginTransaction();
    try {
        $ref = 'PAY' . strtoupper(uniqid());
        $now = date('Y-m-d H:i:s');

        // Debit source
        $pdo->prepare("UPDATE accounts SET balance=balance-? WHERE account_id=?")
            ->execute([$amount, $from_id]);
        $new_from_bal = $from_acc['balance'] - $amount;

        // Credit CC/HL (reduces outstanding)
        $pdo->prepare("UPDATE accounts SET balance=balance+?,
                        last_payment=?, last_payment_date=NOW() WHERE account_id=?")
            ->execute([$amount, $amount, $to_id]);
        $new_to_bal = $to_acc['balance'] + $amount;

        // Record transactions
        $pdo->prepare(
            "INSERT INTO transactions (account_id,txn_type,amount,balance_after,description,reference,txn_date)
             VALUES (?,?,?,?,?,?,?)"
        )->execute([$from_id, 'debit', $amount, $new_from_bal,
                    "Payment towards {$to_acc['account_name']}", $ref, $now]);
        $pdo->prepare(
            "INSERT INTO transactions (account_id,txn_type,amount,balance_after,description,reference,txn_date)
             VALUES (?,?,?,?,?,?,?)"
        )->execute([$to_id, 'credit', $amount, $new_to_bal,
                    "Payment received from {$from_acc['account_name']}", $ref, $now]);

        // Record payment
        $pdo->prepare(
            "INSERT INTO payments (user_id,from_account_id,to_account_id,amount) VALUES (?,?,?,?)"
        )->execute([$uid, $from_id, $to_id, $amount]);

        $pdo->commit();
        audit('payment_made', $uid);

        // Reload updated balances for display
        $from_acc['balance'] = $new_from_bal;
        $to_acc['balance']   = $new_to_bal;
        $to_acc['last_payment'] = $amount;
        $to_acc['last_payment_date'] = $now;
        ?>

<!-- ─── SUCCESS PAGE ─── -->
<main class="page-wrapper" style="max-width:600px">
  <div class="card text-center" style="padding:2.5rem">
    <div style="width:72px;height:72px;border-radius:50%;background:rgba(34,201,151,.15);display:flex;align-items:center;justify-content:center;margin:0 auto 1rem">
      <i class="bi bi-check-lg" style="font-size:2.5rem;color:var(--success)"></i>
    </div>
    <h2 style="font-family:'Outfit',sans-serif;font-size:1.5rem;font-weight:700;margin-bottom:.35rem">
      Payment Successful!
    </h2>
    <p class="text-muted">Your payment has been processed.</p>
    <div style="font-size:2rem;font-weight:700;font-family:'Outfit',sans-serif;color:var(--gold);margin:1rem 0">
      <?= fmt_inr($amount) ?>
    </div>
    <div class="monospace text-muted" style="font-size:.8rem;margin-bottom:1.5rem">Ref: <?= $ref ?></div>

    <div style="background:rgba(255,255,255,.04);border-radius:8px;padding:1rem;text-align:left;margin-bottom:1.25rem">
      <div class="profile-grid" style="grid-template-columns:1fr 1fr">
        <div class="profile-field">
          <div class="profile-field-label">Paid From</div>
          <div class="profile-field-value"><?= htmlspecialchars($from_acc['account_name']) ?></div>
        </div>
        <div class="profile-field">
          <div class="profile-field-label">New Balance</div>
          <div class="profile-field-value text-success"><?= fmt_inr($from_acc['balance']) ?></div>
        </div>
        <div class="profile-field">
          <div class="profile-field-label">Paid To</div>
          <div class="profile-field-value"><?= htmlspecialchars($to_acc['account_name']) ?></div>
        </div>
        <div class="profile-field">
          <div class="profile-field-label">Outstanding After</div>
          <div class="profile-field-value text-danger"><?= fmt_inr(abs($to_acc['balance'])) ?></div>
        </div>
      </div>
    </div>

    <div style="display:flex;gap:.75rem;justify-content:center">
      <a href="/Banking/payments/index.php" class="btn btn-outline">Make Another Payment</a>
      <a href="/Banking/dashboard.php" class="btn btn-primary">Go to Dashboard</a>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/../layout/footer.php';
    } catch (Throwable $e) {
        $pdo->rollBack();
        $error = 'Payment processing failed. Please try again.';
    }
}

if ($error || !$confirmed):
?>

<!-- ─── REVIEW / ERROR PAGE ─── -->
<main class="page-wrapper" style="max-width:600px">
  <h1 class="page-title"><i class="bi bi-credit-card"></i> Payment Review</h1>

  <?php if ($error): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
    <a href="/Banking/payments/index.php" class="btn btn-outline mt-1">
      <i class="bi bi-arrow-left"></i> Go Back
    </a>
  <?php else: ?>

  <div class="card mb-2">
    <table style="width:100%;border-collapse:collapse">
    <?php $rows = [
      ['Pay To',         $to_acc['account_name'] . ' (' . $to_acc['account_number'] . ')'],
      ['Outstanding',    fmt_inr(abs($to_acc['balance']))],
      ['Pay From',       $from_acc['account_name'] . ' (' . $from_acc['account_number'] . ')'],
      ['Available Bal.', fmt_inr($from_acc['balance'])],
      ['Payment Amount', fmt_inr($amount)],
      ['Balance After',  fmt_inr($from_acc['balance'] - $amount)],
    ];
    foreach ($rows as [$lbl, $val]): ?>
      <tr style="border-top:1px solid var(--border)">
        <td style="padding:.65rem 1rem;color:var(--text-muted);font-size:.85rem;width:45%"><?= $lbl ?></td>
        <td style="padding:.65rem 1rem;font-weight:500"><?= htmlspecialchars($val) ?></td>
      </tr>
    <?php endforeach; ?>
    </table>

    <?php if ($from_acc['balance'] < $amount): ?>
      <div class="alert alert-danger mt-2"><i class="bi bi-exclamation-triangle"></i>
        Insufficient balance for this payment.
      </div>
    <?php endif; ?>
  </div>

  <form method="POST">
    <input type="hidden" name="to_account_id"   value="<?= $to_id ?>">
    <input type="hidden" name="from_account_id" value="<?= $from_id ?>">
    <input type="hidden" name="amount"           value="<?= $amount ?>">
    <input type="hidden" name="confirm"          value="1">

    <div style="display:flex;gap:.75rem;justify-content:flex-end">
      <a href="/Banking/payments/index.php" class="btn btn-outline">
        <i class="bi bi-arrow-left"></i> Cancel
      </a>
      <button type="submit" class="btn btn-primary btn-lg"
              <?= $from_acc['balance'] < $amount ? 'disabled' : '' ?>>
        <i class="bi bi-check-circle"></i> Confirm Payment
      </button>
    </div>
  </form>
  <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
<?php endif; ?>
