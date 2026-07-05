<?php
// ============================================================
//  includes/people-page.php — Shared body for the two people
//  pages. Expects $PEOPLE_ROLE, $PEOPLE_TITLE and $PAGE to be
//  set by the including page (content-managers.php /
//  sales-people.php).
// ============================================================

include __DIR__ . '/layout/header.php';
?>

<div id="peoplePage" data-role="<?= e($PEOPLE_ROLE) ?>">
  <div id="peopleList">
    <div class="text-muted" style="font-size:13px">Loading…</div>
  </div>
</div>

<?php if ($mh_is_admin): ?>
<!-- Modal: add / edit person -->
<div class="modal fade" id="mPerson" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="mPersonTitle"><i class="bi bi-person-plus me-2"></i>Add person</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="perId">
        <div class="mb-3">
          <label class="form-label">Name *</label>
          <input class="form-control" id="perName" maxlength="255">
        </div>
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input type="email" class="form-control" id="perEmail" maxlength="255">
        </div>
        <div class="mb-3">
          <label class="form-label">Companies</label>
          <div id="perCompanies" class="d-flex flex-column gap-1" style="max-height:180px;overflow-y:auto">
            <!-- checkboxes injected by people.js -->
          </div>
        </div>
        <div class="mb-2">
          <label class="form-label">Notes</label>
          <textarea class="form-control" id="perNotes" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-sm btn-mh" id="btnSavePerson">Save</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/layout/footer.php'; ?>
