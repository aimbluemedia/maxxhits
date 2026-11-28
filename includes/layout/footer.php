<?php
// ============================================================
//  includes/layout/footer.php — Shared shell (bottom half)
// ============================================================
?>
  </div><!-- /#content -->
</main>

<div id="toast" role="status"></div>

<!-- Shared confirm dialog (MH.confirm) -->
<div class="mh-overlay" id="confirmOverlay">
  <div class="mh-modal small">
    <div class="mh-modal-body center">
      <div class="confirm-icon">!</div>
      <div id="confirmMsg">Are you sure?</div>
    </div>
    <div class="mh-modal-footer center">
      <button type="button" id="confirmNo">Cancel</button>
      <button type="button" class="danger" id="confirmYes">Confirm</button>
    </div>
  </div>
</div>

<script src="assets/js/core.js?v=<?= e(MH_VERSION) ?>"></script>
<?php foreach (($PAGE['js'] ?? []) as $mh_js): ?>
<script src="assets/js/<?= e($mh_js) ?>?v=<?= e(MH_VERSION) ?>"></script>
<?php endforeach; ?>
</body>
</html>
