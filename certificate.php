<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/certificates.php';

$me = require_login();
$certificateId = isset($_GET['id']) && is_string($_GET['id']) ? clean_string($_GET['id'], 32) : '';
$certificate = $certificateId !== '' ? find_certificate(db(), $certificateId) : null;
if (!$certificate || !certificate_record_matches_attempt($certificate)) {
    http_response_code(404);
    die('Certificate not found.');
}
if ((int) $certificate['student_id'] !== (int) $me['id'] && $me['role'] !== 'admin') {
    http_response_code(404);
    die('Certificate not found.');
}

$verificationUrl = base_url('verify-certificate.php?id=' . rawurlencode($certificate['certificate_id']));
$pageTitle = 'Academic Excellence Award';
include __DIR__ . '/includes/header.php';
?>
<div class="container py-4 certificate-preview" id="certificatePreview">
  <?php if ($certificate['status'] === 'revoked'): ?>
    <div class="alert alert-danger"><strong>This certificate has been revoked.</strong>
      <?= e($certificate['revocation_reason'] ?: 'It is no longer valid.') ?>
    </div>
  <?php endif; ?>
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 certificate-actions">
    <a class="btn btn-outline-secondary" href="<?= e(base_url($me['role'] === 'admin' ? 'admin/certificates.php' : 'certificates.php')) ?>"><i class="bi bi-arrow-left"></i> Back</a>
    <div class="d-flex gap-2">
      <?php if ($certificate['status'] === 'issued' || $me['role'] === 'admin'): ?>
        <a class="btn btn-primary" href="<?= e(base_url('certificate-pdf.php?id=' . rawurlencode($certificate['certificate_id']))) ?>"><i class="bi bi-download"></i> Download PDF</a>
      <?php endif; ?>
      <button class="btn btn-outline-primary" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
      <a class="btn btn-outline-primary" target="_blank" rel="noopener" href="<?= e($verificationUrl) ?>"><i class="bi bi-patch-check"></i> Verify</a>
    </div>
  </div>
  <?php include __DIR__ . '/includes/certificate-template.php'; ?>
</div>
<style>
@media print {
  @page { size: A4 landscape; margin: 0; }
  html, body { width: 297mm !important; height: 210mm !important; margin: 0 !important; padding: 0 !important; overflow: hidden !important; }
  .oes-navbar, .oes-footer, .admin-sidebar, .admin-topbar, .admin-sidebar-overlay,
  .certificate-actions, .alert { display: none !important; }
  body { background: #fff !important; }
  .admin-shell { display: block !important; width: 297mm !important; min-height: 0 !important; margin: 0 !important; padding: 0 !important; }
  .admin-main, .admin-shell-page.student-shell-page .admin-main { margin: 0 !important; padding: 0 !important; transition: none !important; }
  .admin-main > .container, .admin-shell-page.student-shell-page .admin-main > .container { padding: 0 !important; }
  .container { width: 297mm !important; max-width: none !important; margin: 0 !important; padding: 0 !important; }
  #certificatePreview { position: absolute !important; top: 0 !important; left: 0 !important; width: 297mm !important; height: 210mm !important; margin: 0 !important; padding: 0 !important; overflow: hidden !important; }
  #certificatePreview .achievement-certificate { position: absolute !important; top: 0 !important; left: 0 !important; width: 297mm !important; height: 210mm !important; max-width: none !important; margin: 0 !important; }
}
</style>
<?php include __DIR__ . '/includes/footer.php'; ?>
