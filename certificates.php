<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/certificates.php';

$me = require_student();

$pdo = db();
backfill_eligible_certificates($pdo, (int) $me['id']);

$stmt = $pdo->prepare("
    SELECT c.*, u.name AS student_name, q.title AS exam_name,
           a.user_id, a.quiz_id, a.score, a.total_marks, a.submitted_at
    FROM certificates c
    JOIN users u ON u.id = c.student_id
    JOIN quizzes q ON q.id = c.exam_id
    JOIN attempts a ON a.id = c.attempt_id
    WHERE c.student_id = ? AND c.status = 'issued'
    ORDER BY c.issue_date DESC, c.id DESC
");
$stmt->execute([$me['id']]);
$certificates = array_values(array_filter(
    $stmt->fetchAll(),
    'certificate_record_matches_attempt'
));

$pageTitle = 'My Certificates';
$activeNav = 'certificates';
include __DIR__ . '/includes/header.php';
?>
<div class="container py-4">
  <div class="d-flex flex-wrap justify-content-between align-items-end mb-4 gap-2">
    <div>
      <h2 class="mb-1">My Certificates</h2>
      <p class="text-muted mb-0">Gold awards recognize results of 80% or higher; Silver awards recognize results from 50% to 79.99%.</p>
    </div>
    <span class="badge text-bg-light border"><?= count(array_filter($certificates, fn($c) => $c['status'] === 'issued')) ?> active</span>
  </div>

  <?php if (!$certificates): ?>
    <div class="card certificate-empty text-center p-5">
      <div class="feature-icon mx-auto mb-3"><i class="bi bi-award"></i></div>
      <h4>No certificates yet</h4>
      <p class="text-muted mb-4">Earn an Academic Excellence Award by scoring at least 50% in an examination.</p>
      <a class="btn btn-primary align-self-center" href="<?= e(base_url('exams.php')) ?>">Browse exams</a>
    </div>
  <?php else: ?>
    <div class="row g-4">
      <?php foreach ($certificates as $certificate):
        $typeClass = strtolower($certificate['certificate_type']);
      ?>
        <div class="col-md-6 col-xl-4">
          <article class="card achievement-card h-100 certificate-<?= e($typeClass) ?>">
            <div class="achievement-card-top d-flex justify-content-between align-items-start p-4 pb-3">
              <span class="achievement-medal"><i class="bi bi-award-fill"></i></span>
              <span class="badge <?= $certificate['status'] === 'issued' ? 'text-bg-success' : 'text-bg-secondary' ?>">
                <?= e(ucfirst($certificate['status'])) ?>
              </span>
            </div>
            <div class="px-4 pb-4 d-flex flex-column flex-grow-1">
              <div class="text-uppercase small fw-semibold achievement-kicker"><?= e($certificate['certificate_type']) ?> Academic Excellence Award</div>
              <h4 class="mt-2 mb-1"><?= e($certificate['exam_name']) ?></h4>
              <div class="text-muted mb-3"><?= e($certificate['student_name']) ?></div>
              <div class="row g-2 mb-3">
                <div class="col-6"><div class="achievement-metric"><span>Percentage</span><strong><?= number_format((float) $certificate['percentage'], 1) ?>%</strong></div></div>
                <div class="col-6"><div class="achievement-metric"><span>Grade</span><strong><?= e($certificate['grade']) ?></strong></div></div>
              </div>
              <div class="small text-muted mb-1">Issued <?= e(date('d M Y', strtotime($certificate['issue_date']))) ?></div>
              <div class="small text-muted mb-3">ID: <span class="font-monospace"><?= e($certificate['certificate_id']) ?></span></div>
              <?php if ($certificate['status'] === 'revoked' && $certificate['revocation_reason']): ?>
                <div class="small text-danger mb-3">Revoked: <?= e($certificate['revocation_reason']) ?></div>
              <?php endif; ?>
              <div class="d-flex gap-2 mt-auto">
                <a class="btn btn-outline-primary flex-fill" href="<?= e(base_url('certificate.php?id=' . rawurlencode($certificate['certificate_id']))) ?>"><i class="bi bi-eye"></i> View Certificate</a>
                <?php if ($certificate['status'] === 'issued'): ?>
                  <a class="btn btn-primary" href="<?= e(base_url('certificate-pdf.php?id=' . rawurlencode($certificate['certificate_id']))) ?>"><i class="bi bi-download"></i> Download PDF</a>
                <?php endif; ?>
              </div>
            </div>
          </article>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
