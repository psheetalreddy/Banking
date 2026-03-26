<?php
/**
 * messages/list.php – List all messages/alerts
 */
$page_title = 'Messages & Alerts';
require_once __DIR__ . '/../layout/header.php';
require_login();

$uid = current_user_id();
$pdo = get_db();

// Handle actions (CSRF token omitted for brevity – add in production)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $mid    = (int)($_POST['message_id'] ?? 0);

    if ($action === 'mark_unread' && $mid) {
        $pdo->prepare("UPDATE messages SET is_read=0 WHERE message_id=? AND user_id=?")->execute([$mid, $uid]);
        set_flash('success', 'Message marked as unread.');
    } elseif ($action === 'delete' && $mid) {
        $pdo->prepare("DELETE FROM messages WHERE message_id=? AND user_id=?")->execute([$mid, $uid]);
        set_flash('success', 'Message deleted.');
    }
    redirect('/Banking/messages/list.php');
}

// Fetch messages (newest first)
$stmt = $pdo->prepare(
    "SELECT message_id, direction, category, subject, LEFT(body,120) AS preview,
            is_read, created_at
     FROM messages
     WHERE user_id = ?
     ORDER BY created_at DESC"
);
$stmt->execute([$uid]);
$messages = $stmt->fetchAll();

$cat_icons = [
    'alert'     => 'bi-bell-fill',
    'complaint' => 'bi-exclamation-octagon',
    'query'     => 'bi-question-circle',
    'feedback'  => 'bi-star',
    'reply'     => 'bi-reply',
];
?>

<main class="page-wrapper">
  <div class="flex-between mb-2">
    <h1 class="page-title"><i class="bi bi-bell"></i> Alerts & Messages</h1>
    <a href="/Banking/messages/compose.php" class="btn btn-primary">
      <i class="bi bi-plus-circle"></i> New Message
    </a>
  </div>

  <?php if (empty($messages)): ?>
    <div class="card text-center" style="padding:3rem">
      <i class="bi bi-inbox" style="font-size:3rem;color:var(--text-muted)"></i>
      <p class="text-muted mt-2">Your inbox is empty.</p>
      <a href="/Banking/messages/compose.php" class="btn btn-primary mt-2">Send a message</a>
    </div>
  <?php else: ?>
    <?php foreach ($messages as $msg): ?>
    <div class="msg-row <?= $msg['is_read'] ? '' : 'unread' ?>">
      <div class="msg-icon">
        <i class="bi <?= $cat_icons[$msg['category']] ?? 'bi-envelope' ?>"></i>
      </div>

      <div style="flex:1;min-width:0">
        <div class="flex-between">
          <div>
            <a href="/Banking/messages/view.php?id=<?= $msg['message_id'] ?>" class="msg-subject">
              <?= htmlspecialchars($msg['subject']) ?>
            </a>
            <?php if (!$msg['is_read']): ?>
              <span class="badge badge-unread" style="margin-left:.4rem">New</span>
            <?php endif; ?>
          </div>
          <span class="msg-time"><?= fmt_datetime($msg['created_at']) ?></span>
        </div>
        <div class="msg-preview"><?= htmlspecialchars($msg['preview']) ?>…</div>
        <div class="mt-1">
          <span class="badge badge-<?= $msg['direction'] === 'inbox' ? 'credit' : 'pending' ?>"
                style="margin-right:.3rem">
            <?= ucfirst($msg['direction']) ?>
          </span>
          <span class="badge badge-read"><?= ucfirst($msg['category']) ?></span>
        </div>
      </div>

      <div class="msg-actions">
        <a href="/Banking/messages/view.php?id=<?= $msg['message_id'] ?>"
           class="btn btn-outline btn-sm" title="Open">
          <i class="bi bi-eye"></i>
        </a>
        <form method="POST" style="display:inline">
          <input type="hidden" name="action" value="mark_unread">
          <input type="hidden" name="message_id" value="<?= $msg['message_id'] ?>">
          <button type="submit" class="btn btn-outline btn-sm" title="Mark Unread">
            <i class="bi bi-envelope"></i>
          </button>
        </form>
        <form method="POST" style="display:inline">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="message_id" value="<?= $msg['message_id'] ?>">
          <button type="submit" class="btn btn-danger btn-sm" title="Delete"
                  data-confirm="Delete this message permanently?">
            <i class="bi bi-trash"></i>
          </button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
