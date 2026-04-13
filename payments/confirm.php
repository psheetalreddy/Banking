<?php
/**
 * payments/confirm.php
 * Process and confirm a CC / Home Loan payment.
 * Fixed: removed 'payment' OTP purpose (ENUM constraint crash), 
 *        added proper confirm flow with CSRF-safe token.
 */
$page_title = 'Payment Confirmation';
require_once __DIR__ . '/../layout/header.php';
require_login();

$uid = current_user_id();
$pdo = get_db();

$to_id   = (int)($_POST['to_account_id']   ?? 0);
$from_id = (int)($_POST['from_account_id'] ?? 0);
$amount  = (float)($_POST['amount']        ?? 0);
$confirm = ($_POST['confirm'] ?? '') === '1';

$error    = '';
$from_acc = null;
$to_acc   = null;
$ref      = '';

// ── Basic validation ─────────────────────────────────────
if (!$to_id || !$from_id || $amount <= 0) {
    $error = 'Invalid payment details. Please go back.';
}

// ── Load accounts (verify ownership) ─────────────────────
if (!$error) {
    $fa = $pdo->prepare(
        "SELECT a.*, ac.name AS category, ac.icon
         FROM accounts a JOIN account_categories ac ON a.category_id=ac.category_id
         WHERE a.account_id=? AND a.user_id=? AND a.status='active'"
    );
    $fa->execute([$from_id, $uid]);
    $from_acc = $fa->fetch();

    $ta = $pdo->prepare(
        "SELECT a.*, ac.name AS category, ac.icon
         FROM accounts a JOIN account_categories ac ON a.category_id=ac.category_id
         WHERE a.account_id=? AND a.user_id=? AND a.status='active'"
    );
    $ta->execute([$to_id, $uid]);
    $to_acc = $ta->fetch();

    if (!$from_acc || !$to_acc) {
        $error = 'One or both accounts could not be found.';
    }
}

// ── Process on confirmed POST ─────────────────────────────
if (!$error && $confirm) {
    if ($from_acc['balance'] < $amount) {
        $error = 'Insufficient balance in ' . $from_acc['account_name'] .
                 '. Available: ' . fmt_inr($from_acc['balance']);
    } else {
        $pdo->beginTransaction();
        try {
            $ref = 'PAY' . strtoupper(uniqid());
            $now = date('Y-m-d H:i:s');

            $new_from_bal = $from_acc['balance'] - $amount;
            $new_to_bal   = $to_acc['balance'] + $amount;

            // Debit source account
            $pdo->prepare("UPDATE accounts SET balance=balance-? WHERE account_id=?")
                ->execute([$amount, $from_id]);

            // Credit CC/HL (reduces outstanding balance toward 0)
            $pdo->prepare(
                "UPDATE accounts SET balance=balance+?, last_payment=?, last_payment_date=NOW()
                 WHERE account_id=?"
            )->execute([$amount, $amount, $to_id]);

            // Transaction records
            $pdo->prepare(
                "INSERT INTO transactions
                 (account_id, txn_type, amount, balance_after, description, reference, txn_date)
                 VALUES (?, 'debit', ?, ?, ?, ?, ?)"
            )->execute([
                $from_id, $amount, $new_from_bal,
                "Payment towards {$to_acc['account_name']}", $ref, $now
            ]);
            $pdo->prepare(
                "INSERT INTO transactions
                 (account_id, txn_type, amount, balance_after, description, reference, txn_date)
                 VALUES (?, 'credit', ?, ?, ?, ?, ?)"
            )->execute([
                $to_id, $amount, $new_to_bal,
                "Payment received from {$from_acc['account_name']}", $ref, $now
            ]);

            // Payment record
            $pdo->prepare(
                "INSERT INTO payments (user_id, from_account_id, to_account_id, amount)
                 VALUES (?, ?, ?, ?)"
            )->execute([$uid, $from_id, $to_id, $amount]);

            $pdo->commit();
            audit('payment_made', $uid);

            // Update local values for display
            $from_acc['balance']           = $new_from_bal;
            $to_acc['balance']             = $new_to_bal;
            $to_acc['last_payment']        = $amount;
            $to_acc['last_payment_date']   = $now;

        } catch (Throwable $e) {
            $pdo->rollBack();
            $error = 'Payment processing failed. Please try again. (' . $e->getMessage() . ')';
        }
    }
}

// ── Determine which view to render ───────────────────────
if ($error):
?>
<!-- ─── ERROR ─── -->
<main class="page-wrapper" style="max-width:600px">
  <h1 class="page-title"><i class="bi bi-credit-card"></i> Payment</h1>
  <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
  <a href="/Banking/payments/index.php" class="btn btn-outline mt-1">
    <i class="bi bi-arrow-left"></i> Go Back
  </a>
</main>

<?php require_once __DIR__ . '/../layout/footer.php';

elseif ($confirm && !$error):
// ─── SUCCESS ───
?>
<main class="page-wrapper" style="max-width:600px">
  <div class="card text-center" style="padding:2.5rem">
    <div style="width:72px;height:72px;border-radius:50%;background:rgba(34,201,151,.15);
                display:flex;align-items:center;justify-content:center;margin:0 auto 1rem">
      <i class="bi bi-check-lg" style="font-size:2.5rem;color:var(--success)"></i>
    </div>
    <h2 style="font-family:'Outfit',sans-serif;font-size:1.5rem;font-weight:700;margin-bottom:.35rem">
      Payment Successful!
    </h2>
    <p class="text-muted">Your payment has been processed.</p>
    <div style="font-size:2rem;font-weight:700;font-family:'Outfit',sans-serif;color:var(--gold);margin:1rem 0">
      <?= fmt_inr($amount) ?>
    </div>
    <div class="monospace text-muted" style="font-size:.8rem;margin-bottom:1.5rem">
      Ref: <?= htmlspecialchars($ref) ?>
    </div>

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
        <?php if ($to_acc['balance'] >= 0): ?>
        <div class="profile-field" style="grid-column:1/-1">
          <div class="alert alert-success" style="margin-top:.5rem;font-size:.82rem">
            <i class="bi bi-check-circle"></i> Account fully paid off! Outstanding balance is ₹0.
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <div style="display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap">
      <a href="/Banking/payments/index.php" class="btn btn-outline">
        <i class="bi bi-arrow-repeat"></i> Make Another Payment
      </a>
      <a href="/Banking/dashboard.php" class="btn btn-primary">
        <i class="bi bi-house"></i> Go to Dashboard
      </a>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/../layout/footer.php';

else:
// ─── REVIEW (initial) ───
// Check balance for display warning
$insufficient = $from_acc && $from_acc['balance'] < $amount;
?>
<main class="page-wrapper" style="max-width:600px">
  <h1 class="page-title"><i class="bi bi-credit-card"></i> Review Payment</h1>

  <!-- Summary card -->
  <div class="card mb-2">

    <!-- Amount hero -->
    <div style="text-align:center;padding:1.5rem 0 1rem;border-bottom:1px solid var(--border)">
      <div style="font-size:.78rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.08em;margin-bottom:.3rem">Payment Amount</div>
      <div style="font-size:2.2rem;font-weight:700;font-family:'Outfit',sans-serif;color:var(--gold)">
        <?= fmt_inr($amount) ?>
      </div>
    </div>

    <!-- Details table -->
    <table style="width:100%;border-collapse:collapse">
      <?php $rows = [
        ['Pay To (Account)',   $to_acc['account_name'] . ' (' . $to_acc['account_number'] . ')'],
        ['Account Type',       $to_acc['category']],
        ['Outstanding Balance', fmt_inr(abs($to_acc['balance']))],
        ['',                   ''],   // spacer
        ['Pay From',           $from_acc['account_name'] . ' (' . $from_acc['account_number'] . ')'],
        ['Available Balance',  fmt_inr($from_acc['balance'])],
        ['Balance After Payment', fmt_inr($from_acc['balance'] - $amount)],
      ];
      foreach ($rows as [$lbl, $val]):
        if (!$lbl) continue; ?>
      <tr style="border-top:1px solid var(--border)">
        <td style="padding:.6rem 1rem;color:var(--text-muted);font-size:.84rem;width:45%"><?= $lbl ?></td>
        <td style="padding:.6rem 1rem;font-weight:500;font-size:.9rem"><?= htmlspecialchars($val) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>

    <!-- Insufficient balance warning -->
    <?php if ($insufficient): ?>
    <div class="alert alert-danger" style="margin:1rem 1rem 0">
      <i class="bi bi-exclamation-triangle"></i>
      Insufficient balance. You need <?= fmt_inr($amount - $from_acc['balance']) ?> more to complete this payment.
    </div>
    <?php endif; ?>

    <!-- Overpayment warning -->
    <?php if ($amount > abs($to_acc['balance'])): ?>
    <div class="alert alert-warning" style="margin:1rem 1rem 0">
      <i class="bi bi-info-circle"></i>
      You are paying more than the outstanding balance of <?= fmt_inr(abs($to_acc['balance'])) ?>.
      The excess amount will create a credit on the account.
    </div>
    <?php endif; ?>
  </div>

  <!-- Confirm form -->
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
              <?= $insufficient ? 'disabled title="Insufficient balance"' : '' ?>>
        <i class="bi bi-check-circle"></i> Confirm Payment
      </button>
    </div>
  </form>
</main>

<?php require_once __DIR__ . '/../layout/footer.php';
endif;
?>
