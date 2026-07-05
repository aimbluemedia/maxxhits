<?php
// ============================================================
//  includes/layout/footer.php — Shared page shell (bottom half)
//  Loads Bootstrap JS, core.js and the page's own scripts.
// ============================================================
?>
    </div><!-- /.content -->
  </div><!-- /.main -->
</div><!-- /.app -->

<!-- Toast container (populated by MH.toast in core.js) -->
<div class="toast-stack" id="toastStack"></div>

<!-- Shared confirm modal (used by MH.confirm in core.js) -->
<div class="modal fade" id="mhConfirm" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-body pt-4 text-center">
        <i class="bi bi-exclamation-triangle" style="font-size:28px;color:#dc2626"></i>
        <div class="mt-2" id="mhConfirmMsg" style="font-size:14px">Are you sure?</div>
      </div>
      <div class="modal-footer border-0 justify-content-center pb-4">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-sm btn-danger" id="mhConfirmYes">Delete</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/core.js?v=<?= e(MAXXHITS_VERSION) ?>"></script>
<?php foreach (($PAGE['js'] ?? []) as $mh_js): ?>
<script src="assets/js/<?= e($mh_js) ?>?v=<?= e(MAXXHITS_VERSION) ?>"></script>
<?php endforeach; ?>
</body>
</html>
