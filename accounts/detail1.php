<?php
/**
 * accounts/detail.php – Placeholder (enabled Sprint-2)
 */
$page_title = 'Account Details';
require_once __DIR__ . '/../layout/header.php';
require_login();
?>
<main class="page-wrapper" style="max-width:600px;text-align:center;padding-top:4rem">
  <div class="card" style="padding:3rem">
    <i class="bi bi-tools" style="font-size:4rem;color:var(--warning)"></i>
    <h2 style="font-family:'Outfit',sans-serif;margin:1rem 0 .5rem">Under Construction</h2>
    <p class="text-muted">Detailed account view will be available in <strong>Sprint-2</strong>.</p>
    <a href="/Banking/accounts/index.php" class="btn btn-primary mt-3">
      <i class="bi bi-arrow-left"></i> Back to Accounts
    </a>
  </div>
</main>
<?php require_once __DIR__ . '/../layout/footer.php'; ?>
