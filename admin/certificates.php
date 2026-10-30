<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/certificates.php';

$admin = require_admin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'revoke') {
    verify_csrf();
    $id = isset($_POST['id']) && is_scalar($_POST['id']) ? (int) $_POST['id'] : 0;
    $reason = isset($_POST['reason']) && is_string($_POST['reason']) ? clean_string($_POST['reason'], 500) : '';
    if ($id <= 0 || $reason === '') {
        flash('error', 'A certificate and revocation reason are required.');
        redirect('admin/certificates.php');
    }

    $stmt = $pdo->prepare("
        UPDATE certificates
        SET status = 'revoked', revocation_reason = ?, revoked_at = NOW()
        WHERE id = ? AND status = 'issued'
    ");
    $stmt->execute([$reason, $id]);
    if ($stmt->rowCount() !== 1) {
        flash('error', 'Certificate not found or already revoked.');
    } else {
        flash('success', 'Certificate revoked.');
    }
    redirect('admin/certificates.php');
}

$search = isset($_GET['q']) && is_string($_GET['q']) ? clean_string($_GET['q'], 100) : '';
$level = isset($_GET['level']) && is_string($_GET['level']) ? clean_string($_GET['level'], 12) : '';
$page = isset($_GET['page']) && is_scalar($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$perPage = 50;
$allowedLevels = ['Silver', 'Gold', 'Platinum'];

$stats = ['total' => 0, 'Silver' => 0, 'Gold' => 0, 'Platinum' => 0];
foreach ($pdo->query("SELECT certificate_type, COUNT(*) AS amount FROM certificates WHERE status = 'issued' GROUP BY certificate_type") as $row) {
    $stats[$row['certificate_type']] = (int) $row['amount'];
    $stats['total'] += (int) $row['amount'];
}

$filterSql = '';
$args = [];
if ($search !== '') {
    $filterSql .= " AND (u.name LIKE ? OR c.certificate_id LIKE ?)";
    $args[] = '%' . $search . '%';
    $args[] = '%' . $search . '%';
}
if (in_array($level, $allowedLevels, true)) {
    $filterSql .= " AND c.certificate_type = ?";
    $args[] = $level;
}
$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM certificates c
    JOIN users u ON u.id = c.student_id
    WHERE 1=1 {$filterSql}
");
$countStmt->execute($args);
$totalRows = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = paginate_offset($page, $perPage);
$sql = "
    SELECT c.*, u.name AS student_name, q.title AS exam_name
    FROM certificates c
    JOIN users u ON u.id = c.student_id
    JOIN quizzes q ON q.id = c.exam_id
    WHERE 1=1 {$filterSql}
    ORDER BY c.issue_date DESC, c.id DESC
    LIMIT {$perPage} OFFSET {$offset}
";
$stmt = $pdo->prepare($sql);
$stmt->execute($args);
$certificates = $stmt->fetchAll();

$pageTitle = 'Admin · Certificates';
include __DIR__ . '/../includes/header.php';
$adminActive = 'certificates';
include __DIR__ . '/_nav.php';
?>
<div class="container">
  <div class="d-flex flex-wrap justify-content-between align-items-end mb-3 gap-2">
    <div>
      <h3 class="mb-1">Certificate management</h3>
      <p class="text-muted mb-0">Review, download, search, and revoke issued certificates.</p>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <?php foreach ([
      ['Total issued', $stats['total'], 'bi-award', 'primary'],
      ['Silver', $stats['Silver'], 'bi-award-fill', 'secondary'],
      ['Gold', $stats['Gold'], 'bi-award-fill', 'warning'],
      ['Platinum', $stats['Platinum'], 'bi-award-fill', 'info'],
    ] as [$label, $amount, $icon, $color]): ?>
      <div class="col-6 col-lg-3"><div class="card p-3 h-100">
        <div class="d-flex justify-content-between align-items-center">
          <div><div class="text-muted small text-uppercase"><?= e($label) ?></div><div class="fs-3 fw-bold"><?= (int) $amount ?></div></div>
          <div class="feature-icon text-<?= e($color) ?>"><i class="bi <?= e($icon) ?>"></i></div>
        </div>
      </div></div>
    <?php endforeach; ?>
  </div>

  <div class="card p-3">
    <form class="row g-2 mb-3" method="get">
      <div class="col-md-7"><label class="visually-hidden" for="certificateSearch">Search certificates</label>
        <input id="certificateSearch" class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search student name or certificate ID">
      </div>
      <div class="col-md-3"><label class="visually-hidden" for="certificateLevel">Certificate level</label>
        <select id="certificateLevel" name="level" class="form-select">
          <option value="">All levels</option>
          <?php foreach ($allowedLevels as $option): ?>
            <option value="<?= e($option) ?>" <?= $level === $option ? 'selected' : '' ?>><?= e($option) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2 d-grid"><button class="btn btn-primary"><i class="bi bi-search"></i> Search</button></div>
    </form>

    <?php if (!$certificates): ?>
      <p class="text-center text-muted my-4">No certificates match these filters.</p>
    <?php else: ?>
      <p class="small text-muted mb-2">Showing <?= $totalRows ? $offset + 1 : 0 ?>–<?= min($offset + $perPage, $totalRows) ?> of <?= $totalRows ?> certificates</p>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead><tr><th>Certificate ID</th><th>Student</th><th>Exam</th><th>Level</th><th>Result</th><th>Issued</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
          <tbody>
          <?php foreach ($certificates as $certificate): ?>
            <tr>
              <td class="font-monospace small"><?= e($certificate['certificate_id']) ?></td>
              <td><?= e($certificate['student_name']) ?></td>
              <td><?= e($certificate['exam_name']) ?></td>
              <td><span class="badge certificate-level-<?= e(strtolower($certificate['certificate_type'])) ?>"><?= e($certificate['certificate_type']) ?></span></td>
              <td><?= number_format((float) $certificate['percentage'], 1) ?>% · <?= e($certificate['grade']) ?></td>
              <td class="small text-muted"><?= e(date('d M Y', strtotime($certificate['issue_date']))) ?></td>
              <td>
                <span class="badge <?= $certificate['status'] === 'issued' ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= e(ucfirst($certificate['status'])) ?></span>
                <?php if ($certificate['status'] === 'revoked'): ?><div class="small text-muted mt-1"><?= e($certificate['revocation_reason']) ?></div><?php endif; ?>
              </td>
              <td class="text-end text-nowrap">
                <a class="btn btn-sm btn-outline-primary" title="View details" href="<?= e(base_url('certificate.php?id=' . rawurlencode($certificate['certificate_id']))) ?>"><i class="bi bi-eye"></i></a>
                <a class="btn btn-sm btn-outline-secondary" title="Download PDF" href="<?= e(base_url('certificate-pdf.php?id=' . rawurlencode($certificate['certificate_id']))) ?>"><i class="bi bi-download"></i></a>
                <?php if ($certificate['status'] === 'issued'): ?>
                  <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="collapse" data-bs-target="#revoke-<?= (int) $certificate['id'] ?>" aria-expanded="false" aria-controls="revoke-<?= (int) $certificate['id'] ?>"><i class="bi bi-ban"></i><span class="visually-hidden">Revoke</span></button>
                <?php endif; ?>
              </td>
            </tr>
            <?php if ($certificate['status'] === 'issued'): ?>
              <tr class="collapse" id="revoke-<?= (int) $certificate['id'] ?>">
                <td colspan="8">
                  <form method="post" class="d-flex flex-wrap gap-2 align-items-start">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="revoke">
                    <input type="hidden" name="id" value="<?= (int) $certificate['id'] ?>">
                    <label class="visually-hidden" for="reason-<?= (int) $certificate['id'] ?>">Revocation reason</label>
                    <input id="reason-<?= (int) $certificate['id'] ?>" class="form-control flex-grow-1" name="reason" maxlength="500" required placeholder="Reason for revocation">
                    <button class="btn btn-danger" type="submit">Confirm revocation</button>
                  </form>
                </td>
              </tr>
            <?php endif; ?>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
    <?php if ($totalPages > 1): ?>
      <nav class="mt-3" aria-label="Certificate pages">
        <ul class="pagination justify-content-center mb-0">
          <?php foreach (array_unique([1, max(1, $page - 1), $page, min($totalPages, $page + 1), $totalPages]) as $pageNumber):
            $query = http_build_query(array_filter([
                'q' => $search,
                'level' => $level,
                'page' => $pageNumber,
            ], static fn($value) => $value !== ''));
          ?>
            <li class="page-item <?= $pageNumber === $page ? 'active' : '' ?>">
              <a class="page-link" href="<?= e(base_url('admin/certificates.php?' . $query)) ?>" <?= $pageNumber === $page ? 'aria-current="page"' : '' ?>><?= (int) $pageNumber ?></a>
            </li>
          <?php endforeach; ?>
        </ul>
      </nav>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
