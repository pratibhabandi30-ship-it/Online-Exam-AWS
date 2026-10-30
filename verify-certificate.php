<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/certificates.php';

$certificateId = isset($_GET['id']) && is_string($_GET['id']) ? clean_string($_GET['id'], 32) : '';
$certificate = $certificateId !== '' ? find_certificate(db(), $certificateId) : null;
$recordMatches = $certificate && certificate_record_matches_attempt($certificate);
$isValid = $recordMatches && $certificate['status'] === 'issued';

$pageTitle = 'Verify Certificate';
include __DIR__ . '/includes/header.php';
?>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-8">
      <div class="card p-4 p-md-5">
        <div class="text-center mb-4">
          <div class="feature-icon mx-auto mb-3"><i class="bi bi-patch-check"></i></div>
          <h2>Certificate verification</h2>
          <?php if ($isValid): ?>
            <span class="badge text-bg-success fs-6"><i class="bi bi-check-circle"></i> Valid certificate</span>
          <?php else: ?>
            <span class="badge text-bg-danger fs-6"><i class="bi bi-x-circle"></i> Invalid certificate</span>
          <?php endif; ?>
        </div>
        <?php if (!$recordMatches): ?>
          <p class="text-center text-muted mb-0">No valid certificate was found for the supplied certificate ID.</p>
        <?php else: ?>
          <dl class="row mb-0">
            <dt class="col-sm-4">Student</dt><dd class="col-sm-8"><?= e($certificate['student_name']) ?></dd>
            <dt class="col-sm-4">Exam</dt><dd class="col-sm-8"><?= e($certificate['exam_name']) ?></dd>
            <dt class="col-sm-4">Achievement level</dt><dd class="col-sm-8"><?= e($certificate['certificate_type']) ?></dd>
            <dt class="col-sm-4">Percentage</dt><dd class="col-sm-8"><?= number_format((float) $certificate['percentage'], 1) ?>%</dd>
            <dt class="col-sm-4">Grade</dt><dd class="col-sm-8"><?= e($certificate['grade']) ?></dd>
            <dt class="col-sm-4">Issue date</dt><dd class="col-sm-8"><?= e(date('d M Y', strtotime($certificate['issue_date']))) ?></dd>
            <dt class="col-sm-4">Certificate ID</dt><dd class="col-sm-8 font-monospace"><?= e($certificate['certificate_id']) ?></dd>
          </dl>
          <?php if (!$isValid): ?><p class="text-danger mt-3 mb-0">This certificate has been revoked and is not valid.</p><?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
