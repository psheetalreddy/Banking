<?php
/**
 * profile/edit.php – Editable profile with KYC upload trigger
 */
$page_title = 'Edit Profile';
require_once __DIR__ . '/../layout/header.php';
require_login();

$uid = current_user_id();
$pdo = get_db();

$stmt = $pdo->prepare(
    "SELECT c.*, u.email, u.mobile, u.otp_enabled
     FROM customers c JOIN users u USING(user_id)
     WHERE c.user_id = ?"
);
$stmt->execute([$uid]);
$profile = $stmt->fetch();
$cid = (int)$profile['customer_id'];

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save_profile';

    if ($action === 'save_profile') {
        $first    = sanitize($_POST['first_name'] ?? '');
        $last     = sanitize($_POST['last_name']  ?? '');
        $dob      = $_POST['dob'] ?? '';
        $mobile   = preg_replace('/\D/', '', $_POST['mobile'] ?? '');
        $addr1    = sanitize($_POST['address_line1'] ?? '');
        $addr2    = sanitize($_POST['address_line2'] ?? '');
        $city     = sanitize($_POST['city'] ?? '');
        $state    = sanitize($_POST['state'] ?? '');
        $pincode  = sanitize($_POST['pincode'] ?? '');
        $otp_en   = isset($_POST['otp_enabled']) ? 1 : 0;

        if (!$first || !$last || !$mobile) {
            $error = 'First name, last name, and mobile are required.';
        } else {
            // Detect KYC-sensitive changes
            $name_kyc    = ($first !== $profile['first_name'] || $last !== $profile['last_name']) ? 1 : (int)$profile['name_pending_kyc'];
            $dob_kyc     = ($dob && $dob !== $profile['dob']) ? 1 : (int)$profile['dob_pending_kyc'];
            $address_kyc = ($addr1 !== $profile['address_line1'] || $city !== $profile['city']) ? 1 : (int)$profile['address_pending_kyc'];

            $pdo->prepare(
                "UPDATE customers SET
                   first_name=?, last_name=?, dob=?,
                   address_line1=?, address_line2=?, city=?, state=?, pincode=?,
                   name_pending_kyc=?, dob_pending_kyc=?, address_pending_kyc=?,
                   updated_at=NOW()
                 WHERE customer_id=?"
            )->execute([$first, $last, $dob ?: null, $addr1, $addr2, $city, $state, $pincode,
                         $name_kyc, $dob_kyc, $address_kyc, $cid]);

            $pdo->prepare("UPDATE users SET mobile=?, otp_enabled=? WHERE user_id=?")
                ->execute([$mobile, $otp_en, $uid]);

            // Handle KYC document upload
            if (!empty($_FILES['kyc_doc']['name'])) {
                $upload_result = handle_kyc_upload($cid, $_FILES['kyc_doc'],
                    sanitize($_POST['doc_type'] ?? 'id_proof'),
                    sanitize($_POST['kyc_for_field'] ?? ''));
                if ($upload_result !== true) {
                    $error = $upload_result;
                }
            }

            if (!$error) {
                audit('profile_updated', $uid);
                // Update session with new name
                $_SESSION['customer_name'] = $first;
                set_flash('success', 'Profile updated successfully.');
                redirect('/Banking/profile/view.php');
            }
        }
    } elseif ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $urow = $pdo->prepare("SELECT password_hash FROM users WHERE user_id=?");
        $urow->execute([$uid]);
        $hashed = $urow->fetchColumn();

        if (!password_verify($current, $hashed)) {
            $error = 'Current password is incorrect.';
        } elseif (!preg_match('/^(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&]).{8,}$/', $new)) {
            $error = 'New password must be 8+ chars with uppercase, number & special character.';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            $pdo->prepare("UPDATE users SET password_hash=? WHERE user_id=?")
                ->execute([password_hash($new, PASSWORD_BCRYPT, ['cost'=>12]), $uid]);
            audit('password_changed', $uid);
            set_flash('success', 'Password changed successfully.');
            redirect('/Banking/profile/view.php');
        }
    }
}

/**
 * Handle KYC document upload.
 * Returns true on success, error string on failure.
 */
function handle_kyc_upload(int $cid, array $file, string $doc_type, string $for_field): bool|string {
    if ($file['error'] !== UPLOAD_ERR_OK) return 'File upload failed.';
    $allowed_ext = ['pdf','jpg','jpeg','png'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_ext)) return 'Only PDF, JPG, PNG files are accepted.';
    if ($file['size'] > 4 * 1024 * 1024) return 'File size must be under 4 MB.';

    $upload_dir = __DIR__ . '/../uploads/kyc/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    $filename  = 'KYC_' . $cid . '_' . time() . '_' . uniqid() . '.' . $ext;
    $dest      = $upload_dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) return 'Could not save the uploaded file.';

    get_db()->prepare(
        "INSERT INTO kyc_documents (customer_id, doc_type, file_name, file_path, for_field)
         VALUES (?, ?, ?, ?, ?)"
    )->execute([$cid, $doc_type, $file['name'], 'uploads/kyc/' . $filename, $for_field]);

    return true;
}
?>

<main class="page-wrapper" style="max-width:780px">
  <div class="flex-between mb-2">
    <h1 class="page-title"><i class="bi bi-pencil-square"></i> Edit Profile</h1>
    <a href="/Banking/profile/view.php" class="btn btn-outline btn-sm">
      <i class="bi bi-arrow-left"></i> Cancel
    </a>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- Personal Info Form -->
  <div class="card mb-2">
    <div class="card-header">
      <span class="card-title"><i class="bi bi-person"></i> Personal Information</span>
    </div>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="save_profile">

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">First Name</label>
          <input type="text" name="first_name" class="form-control" required
                 value="<?= htmlspecialchars($profile['first_name']) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Last Name</label>
          <input type="text" name="last_name" class="form-control" required
                 value="<?= htmlspecialchars($profile['last_name']) ?>">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Date of Birth</label>
          <input type="date" name="dob" class="form-control"
                 value="<?= htmlspecialchars($profile['dob'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Mobile Number</label>
          <input type="tel" name="mobile" class="form-control" required
                 value="<?= htmlspecialchars($profile['mobile']) ?>">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Email <span class="text-muted">(cannot be changed)</span></label>
        <input type="email" class="form-control" value="<?= htmlspecialchars($profile['email']) ?>" disabled>
      </div>

      <div class="form-group">
        <label class="form-label">Address Line 1</label>
        <input type="text" name="address_line1" class="form-control"
               value="<?= htmlspecialchars($profile['address_line1'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Address Line 2</label>
        <input type="text" name="address_line2" class="form-control"
               value="<?= htmlspecialchars($profile['address_line2'] ?? '') ?>">
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">City</label>
          <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($profile['city'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">State</label>
          <input type="text" name="state" class="form-control" value="<?= htmlspecialchars($profile['state'] ?? '') ?>">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Pincode</label>
          <input type="text" name="pincode" class="form-control" value="<?= htmlspecialchars($profile['pincode'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">OTP on Login</label>
          <div style="display:flex;align-items:center;gap:.75rem;padding-top:.4rem">
            <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer">
              <input type="checkbox" name="otp_enabled" <?= $profile['otp_enabled'] ? 'checked' : '' ?>>
              Enable OTP verification on login
            </label>
          </div>
        </div>
      </div>

      <!-- KYC Document Upload (shown when changing sensitive fields) -->
      <div class="card mb-2 mt-1" style="border:1px solid var(--gold-dim);background:rgba(212,168,67,.04)">
        <div class="card-title" style="margin-bottom:1rem;font-size:.93rem">
          <i class="bi bi-upload text-gold"></i> Document Upload
          <span class="text-muted" style="font-size:.8rem;font-weight:400"> — required when changing name, DOB, or address</span>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Document Type</label>
            <select name="doc_type" class="form-control">
              <option value="id_proof">ID Proof (Aadhaar / Passport / DL)</option>
              <option value="address_proof">Address Proof</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">For Field</label>
            <select name="kyc_for_field" class="form-control">
              <option value="">— Select —</option>
              <option value="name">Name Change</option>
              <option value="dob">Date of Birth</option>
              <option value="address">Address Change</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Upload Document <span class="text-muted">(PDF/JPG/PNG, max 4 MB)</span></label>
          <input type="file" name="kyc_doc" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
        </div>
      </div>

      <div style="text-align:right">
        <button type="submit" class="btn btn-primary btn-lg">
          <i class="bi bi-check-circle"></i> Save Changes
        </button>
      </div>
    </form>
  </div>

  <!-- Change Password -->
  <div class="card" id="change-password">
    <div class="card-header">
      <span class="card-title"><i class="bi bi-key"></i> Change Password</span>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="change_password">
      <div class="form-group">
        <label class="form-label">Current Password</label>
        <input type="password" name="current_password" class="form-control" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">New Password</label>
          <input type="password" name="new_password" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Confirm New Password</label>
          <input type="password" name="confirm_password" class="form-control" required>
        </div>
      </div>
      <div class="form-hint mb-2">8+ characters, 1 uppercase, 1 number, 1 special character</div>
      <div style="text-align:right">
        <button type="submit" class="btn btn-outline">
          <i class="bi bi-shield-lock"></i> Update Password
        </button>
      </div>
    </form>
  </div>
</main>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
