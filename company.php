<?php
// ============================================================
//  company.php?id=X — Single company content board
//  Posts table with month filter, platform stats, and the
//  post editor (incl. auto-publish fields).
// ============================================================

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$company_id = $_GET['id'] ?? '';
if (!$company_id || !can_view_company($company_id)) {
    header('Location: companies.php');
    exit;
}

// Resolve company name server-side for the title
$company_name = 'Company';
if ($pdo = db_connect()) {
    $stmt = $pdo->prepare('SELECT name FROM companies WHERE id = ?');
    $stmt->execute([$company_id]);
    $n = $stmt->fetchColumn();
    if ($n === false) { header('Location: companies.php'); exit; }
    $company_name = $n;
}

$can_edit_posts = is_admin() || has_role('content_manager');

$PAGE = [
    'id'    => 'companies',
    'title' => $company_name,
    'js'    => ['company.js'],
];

include __DIR__ . '/includes/layout/header.php';
?>

<div id="companyPage"
     data-company-id="<?= e($company_id) ?>"
     data-company-name="<?= e($company_name) ?>"
     data-can-edit="<?= $can_edit_posts ? '1' : '0' ?>">

  <div class="stats">
    <div class="sc"><div class="sc-lbl">Total</div><div class="sc-val" id="csTotal">—</div></div>
    <div class="sc pub"><div class="sc-lbl"><i class="bi bi-check-circle me-1"></i>Published</div><div class="sc-val" id="csPublished">—</div></div>
    <div class="sc sch"><div class="sc-lbl"><i class="bi bi-clock me-1"></i>Scheduled</div><div class="sc-val" id="csScheduled">—</div></div>
    <div class="sc err"><div class="sc-lbl"><i class="bi bi-exclamation-circle me-1"></i>Failed</div><div class="sc-val" id="csFailed">—</div></div>
  </div>

  <div class="mh-card" id="coDetailCard" style="display:none"></div>

  <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
    <button class="btn btn-sm btn-outline-secondary" id="btnPrevMonth"><i class="bi bi-chevron-left"></i></button>
    <strong id="monthLabel" style="min-width:130px;text-align:center"></strong>
    <button class="btn btn-sm btn-outline-secondary" id="btnNextMonth"><i class="bi bi-chevron-right"></i></button>
    <button class="btn btn-sm btn-outline-secondary" id="btnAllMonths">All dates</button>
    <div id="platformFilter" class="d-flex gap-1 flex-wrap ms-2"></div>
  </div>

  <div id="postsList">
    <div class="text-muted" style="font-size:13px">Loading…</div>
  </div>
</div>

<?php if ($can_edit_posts): ?>
<!-- Modal: add / edit post -->
<div class="modal fade" id="mPost" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="mPostTitle"><i class="bi bi-file-plus me-2"></i>Add post</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="pId">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label">Title *</label>
            <input class="form-control" id="pTitle" maxlength="500">
          </div>
          <div class="col-12">
            <label class="form-label">Content <span class="text-muted fw-normal">(body used when auto-publishing)</span></label>
            <textarea class="form-control" id="pContent" rows="4"></textarea>
          </div>
          <div class="col-md-4">
            <label class="form-label">Platform *</label>
            <select class="form-select" id="pPlatform">
              <option value="blog">Blog</option>
              <option value="facebook">Facebook</option>
              <option value="instagram">Instagram</option>
              <option value="twitter">X / Twitter</option>
              <option value="linkedin">LinkedIn</option>
              <option value="tiktok">TikTok</option>
              <option value="youtube">YouTube</option>
              <option value="reddit">Reddit</option>
              <option value="pinterest">Pinterest</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Status</label>
            <select class="form-select" id="pStatus">
              <option value="draft">Draft</option>
              <option value="scheduled" selected>Scheduled</option>
              <option value="published">Published</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Assignee</label>
            <input class="form-control" id="pAssignee" maxlength="255">
          </div>
          <div class="col-md-4">
            <label class="form-label">Date</label>
            <input type="date" class="form-control" id="pDate">
          </div>
          <div class="col-md-4">
            <label class="form-label">Time <span class="text-muted fw-normal">(auto-publish)</span></label>
            <input type="time" class="form-control" id="pTime">
          </div>
          <div class="col-md-4">
            <label class="form-label">Publishing</label>
            <select class="form-select" id="pMode">
              <option value="manual">Manual (tracking only)</option>
              <option value="auto">Auto-publish via channel</option>
            </select>
          </div>
          <div class="col-md-6" id="pChannelWrap" style="display:none">
            <label class="form-label">Channel *</label>
            <select class="form-select" id="pChannel"><option value="">— pick a channel —</option></select>
            <div class="form-text">Channels are managed on the Automation page.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Image URL <span class="text-muted fw-normal">(required for Instagram)</span></label>
            <input class="form-control" id="pImage" placeholder="https://…/image.jpg">
          </div>
          <div class="col-md-6">
            <label class="form-label">Post URL <span class="text-muted fw-normal">(filled automatically after publish)</span></label>
            <input class="form-control" id="pUrl" placeholder="https://…">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-sm btn-mh" id="btnSavePost">Save post</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/layout/footer.php'; ?>
