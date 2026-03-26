<?php
/**
 * messages/view.php – View a single message
 */
$page_title = 'View Message';
require_once __DIR__ . '/../layout/header.php';
require_login();

$uid = current_user_id();
$pdo = get_db();
$mid = (int)($_GET['id'] ?? 0);

if (!$mid) redirect('/Banking/messages/list.php');

$stmt = $pdo->prepare(
    "SELECT * FROM messages WHERE message_id=? AND user_id=? LIMIT 1"
);
$stmt->execute([$mid, $uid]);
$msg = $stmt->fetch();
if (!$msg) redirect('/Banking/messages/list.php');

// Mark as read
if (!$msg['is_read']) {
    $pdo->prepare("UPDATE messages SET is_read=1 WHERE message_id=?")->execute([$mid]);
}

// Any replies?
$replies = $pdo->prepare(
    "SELECT * FROM messages WHERE parent_id=? AND user_id=? ORDER BY created_at"
);
$replies->execute([$mid, $uid]);
$thread = $replies->fetchAll();
?>

<main class="page-wrapper" style="max-width:780px">
  <div class="flex-between mb-2">
    <a href="/Banking/messages/list.php" class="btn btn-outline btn-sm">
      <i class="bi bi-arrow-left"></i> Back to Inbox
    </a>
    <div style="display:flex;gap:.5rem">
      <form method="POST" action="/Banking/messages/list.php" style="display:inline">
        <input type="hidden" name="action" value="mark_unread">
        <input type="hidden" name="message_id" value="<?= $mid ?>">
        <button class="btn btn-outline btn-sm"><i class="bi bi-envelope"></i> Mark Unread</button>
      </form>
      <form method="POST" action="/Banking/messages/list.php" style="display:inline">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="message_id" value="<?= $mid ?>">
        <button class="btn btn-danger btn-sm"
                data-confirm="Delete this message?"><i class="bi bi-trash"></i> Delete</button>
      </form>
    </div>
  </div>

  <div class="card">
    <div style="border-bottom:1px solid var(--border);padding-bottom:1rem;margin-bottom:1.25rem">
      <h2 style="font-family:'Outfit',sans-serif;font-size:1.3rem;font-weight:700;margin-bottom:.4rem">
        <?= htmlspecialchars($msg['subject']) ?>
      </h2>
      <div style="display:flex;gap:1rem;flex-wrap:wrap">
        <span class="badge badge-<?= $msg['direction']==='inbox'?'credit':'pending' ?>">
          <?= ucfirst($msg['direction']) ?>
        </span>
        <span class="badge badge-read"><?= ucfirst($msg['category']) ?></span>
        <span class="text-muted" style="font-size:.8rem">
          <i class="bi bi-clock"></i> <?= fmt_datetime($msg['created_at']) ?>
        </span>
      </div>
    </div>

    <div style="line-height:1.8;white-space:pre-wrap;font-size:.92rem">
      <?= htmlspecialchars($msg['body']) ?>
    </div>
  </div>

  <!-- Thread replies -->
  <?php foreach ($thread as $r): ?>
  <div class="card mt-2" style="border-left:3px solid var(--teal)">
    <div style="font-size:.78rem;color:var(--text-muted);margin-bottom:.5rem">
      <i class="bi bi-reply"></i> Reply · <?= fmt_datetime($r['created_at']) ?>
    </div>
    <div style="white-space:pre-wrap;font-size:.9rem"><?= htmlspecialchars($r['body']) ?></div>
  </div>
  <?php endforeach; ?>

  <!-- Reply form -->
  <div class="card mt-2">
    <div class="card-header">
      <span class="card-title"><i class="bi bi-reply"></i> Reply / Add Note</span>
    </div>
    <form method="POST" action="/Banking/messages/compose.php">
      <input type="hidden" name="parent_id" value="<?= $mid ?>">
      <input type="hidden" name="subject" value="Re: <?= htmlspecialchars($msg['subject']) ?>">
      <input type="hidden" name="category" value="reply">
      <div class="form-group">
        <textarea name="body" class="form-control" rows="4" placeholder="Type your reply…" required></textarea>
      </div>
      <div style="text-align:right">
        <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i> Send Reply</button>
      </div>
    </form>
  </div>
</main>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
