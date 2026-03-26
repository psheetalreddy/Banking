<?php
/**
 * profile/view.php – Read-only profile display
 */
$page_title = 'My Profile';
require_once __DIR__ . '/../layout/header.php';
require_login();

$uid  = current_user_id();
$pdo  = get_db();

$stmt = $pdo->prepare(
    "SELECT c.*, u.email, u.mobile, u.otp_enabled
     FROM customers c
     JOIN users u USING(user_id)
     WHERE c.user_id = ?"
);
$stmt->execute([$uid]);
$profile = $stmt->fetch();

// KYC docs (pending)
$docs = $pdo->prepare(
    "SELECT * FROM kyc_documents WHERE customer_id=? ORDER BY uploaded_at DESC"
);
$docs->execute([$profile['customer_id']]);
$kyc_docs = $docs->fetchAll();

$kyc_fields = [
    'name_pending_kyc'    => 'Name',
    'address_pending_kyc' => 'Address',
    'dob_pending_kyc'     => 'Date of Birth',
];
?>

<main class="page-wrapper" style="max-width:860px">
  <div class="flex-between mb-2">
    <h1 class="page-title"><i class="bi bi-person-circle"></i> My Profile</h1>
    <a href="/Banking/profile/edit.php" class="btn btn-primary">
      <i class="bi bi-pencil"></i> Edit Profile
    </a>
  </div>

  <!-- KYC pending banner -->
  <?php $pending_fields = array_filter($kyc_fields, fn($k) => $profile[$k], ARRAY_FILTER_USE_KEY); ?>
  <?php if ($pending_fields): ?>
  <div class="alert alert-warning">
    <i class="bi bi-hourglass-split"></i>
    <div>
      <strong>KYC Pending Approval</strong> – The following changes are awaiting bank verification:
      <?= implode(', ', $pending_fields) ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Personal Info -->
  <div class="card mb-2">
    <div class="card-header">
      <span class="card-title"><i class="bi bi-person"></i> Personal Information</span>
    </div>
    <div class="profile-grid">
      <div class="profile-field">
        <div class="profile-field-label">Full Name</div>
        <div class="profile-field-value">
          <?= htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name']) ?>
          <?php if ($profile['name_pending_kyc']): ?>
            <span class="badge badge-kyc"><i class="bi bi-hourglass"></i> KYC Pending</span>
          <?php endif; ?>
        </div>
      </div>
      <div class="profile-field">
        <div class="profile-field-label">Date of Birth</div>
        <div class="profile-field-value">
          <?= $profile['dob'] ? fmt_date($profile['dob']) : '—' ?>
          <?php if ($profile['dob_pending_kyc']): ?>
            <span class="badge badge-kyc"><i class="bi bi-hourglass"></i> KYC Pending</span>
          <?php endif; ?>
        </div>
      </div>
      <div class="profile-field">
        <div class="profile-field-label">Email</div>
        <div class="profile-field-value"><?= htmlspecialchars($profile['email']) ?></div>
      </div>
      <div class="profile-field">
        <div class="profile-field-label">Mobile</div>
        <div class="profile-field-value"><?= htmlspecialchars($profile['mobile']) ?></div>
      </div>
      <div class="profile-field" style="grid-column:1/-1">
        <div class="profile-field-label">Address</div>
        <div class="profile-field-value" style="flex-wrap:wrap">
          <?= htmlspecialchars(implode(', ', array_filter([
              $profile['address_line1'],
              $profile['address_line2'],
              $profile['city'],
              $profile['state'],
              $profile['pincode'],
              $profile['country'],
          ]))) ?: '—' ?>
          <?php if ($profile['address_pending_kyc']): ?>
            <span class="badge badge-kyc"><i class="bi bi-hourglass"></i> KYC Pending</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Preferences -->
  <div class="card mb-2">
    <div class="card-header">
      <span class="card-title"><i class="bi bi-sliders"></i> Preferences & Security</span>
    </div>
    <div class="profile-grid">
      <div class="profile-field">
        <div class="profile-field-label">OTP on Login</div>
        <div class="profile-field-value">
          <?php if ($profile['otp_enabled']): ?>
            <span class="badge badge-success"><i class="bi bi-shield-check"></i> Enabled</span>
          <?php else: ?>
            <span class="badge badge-danger"><i class="bi bi-shield-x"></i> Disabled</span>
          <?php endif; ?>
        </div>
      </div>
      <div class="profile-field">
        <div class="profile-field-label">Password</div>
        <div class="profile-field-value">
          <span style="letter-spacing:.15em">●●●●●●●●</span>
          <a href="/Banking/profile/edit.php#change-password" style="font-size:.8rem;margin-left:.5rem">Change</a>
        </div>
      </div>
    </div>
  </div>

  <!-- KYC Documents -->
  <?php if ($kyc_docs): ?>
  <div class="card">
    <div class="card-header">
      <span class="card-title"><i class="bi bi-file-earmark-check"></i> Uploaded Documents</span>
    </div>
    <table class="data-table">
      <thead>
        <tr><th>Type</th><th>For Field</th><th>File</th><th>Status</th><th>Date</th></tr>
      </thead>
      <tbody>
        <?php foreach ($kyc_docs as $doc): ?>
        <tr>
          <td><?= ucfirst(str_replace('_', ' ', $doc['doc_type'])) ?></td>
          <td><?= ucfirst($doc['for_field'] ?? '—') ?></td>
          <td><i class="bi bi-file-earmark"></i> <?= htmlspecialchars($doc['file_name']) ?></td>
          <td>
            <span class="badge badge-<?= $doc['status']==='approved'?'success':($doc['status']==='rejected'?'danger':'pending') ?>">
              <?= ucfirst($doc['status']) ?>
            </span>
          </td>
          <td class="text-muted"><?= fmt_date($doc['uploaded_at']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
