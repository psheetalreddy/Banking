<?php
/**
 * messages/compose.php – New message / complaint / query
 */
$page_title = 'New Message';
require_once __DIR__ . '/../layout/header.php';
require_login();

$uid    = current_user_id();
$pdo    = get_db();
$error  = '';

// Pre-fill from reply form
$prefill_subject  = sanitize($_GET['subject'] ?? $_POST['subject'] ?? '');
$prefill_category = sanitize($_GET['category'] ?? $_POST['category'] ?? 'query');
$prefill_parent   = (int)($_GET['parent_id'] ?? $_POST['parent_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject  = sanitize($_POST['subject'] ?? '');
    $category = in_array($_POST['category'], ['complaint','query','feedback','reply'])
                ? $_POST['category'] : 'query';
    $body     = strip_tags(trim($_POST['body'] ?? ''));
    $parent   = (int)($_POST['parent_id'] ?? 0);

    if (!$subject || !$body) {
        $error = 'Subject and message body are required.';
    } else {
        $pdo->prepare(
            "INSERT INTO messages (user_id, direction, category, subject, body, parent_id)
             VALUES (?, 'sent', ?, ?, ?, ?)"
        )->execute([$uid, $category, $subject, $body, $parent ?: null]);
        set_flash('success', 'Your message has been submitted.');
        redirect('/Banking/messages/list.php');
    }
}
?>

<main class="page-wrapper" style="max-width:700px">
  <div class="flex-between mb-2">
    <h1 class="page-title"><i class="bi bi-pencil-square"></i> New Message</h1>
    <a href="/Banking/messages/list.php" class="btn btn-outline btn-sm">
      <i class="bi bi-arrow-left"></i> Back
    </a>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="card">
    <form method="POST">
      <?php if ($prefill_parent): ?>
        <input type="hidden" name="parent_id" value="<?= $prefill_parent ?>">
      <?php endif; ?>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Category</label>
          <select name="category" class="form-control">
            <option value="query"     <?= $prefill_category==='query'     ?'selected':'' ?>>General Query</option>
            <option value="complaint" <?= $prefill_category==='complaint' ?'selected':'' ?>>Complaint</option>
            <option value="feedback"  <?= $prefill_category==='feedback'  ?'selected':'' ?>>Feedback</option>
            <option value="reply"     <?= $prefill_category==='reply'     ?'selected':'' ?>>Reply</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Subject</label>
          <input type="text" name="subject" class="form-control" required
                 placeholder="Brief subject line"
                 value="<?= htmlspecialchars($prefill_subject) ?>">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Message</label>
        <textarea name="body" class="form-control" rows="7" required
                  placeholder="Describe your query, complaint or feedback in detail…"></textarea>
      </div>

      <div style="text-align:right">
        <a href="/Banking/messages/list.php" class="btn btn-outline">Cancel</a>
        <button type="submit" class="btn btn-primary" style="margin-left:.5rem">
          <i class="bi bi-send"></i> Submit
        </button>
      </div>
    </form>
  </div>
</main>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
