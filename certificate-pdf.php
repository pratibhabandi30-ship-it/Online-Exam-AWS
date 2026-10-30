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
if ($certificate['status'] !== 'issued' && $me['role'] !== 'admin') {
    http_response_code(410);
    die('This certificate has been revoked.');
}

$autoload = __DIR__ . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    die('Certificate PDF support is not installed. Run composer install in the project directory.');
}
require_once $autoload;
if (!class_exists(\Dompdf\Dompdf::class)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    die('Certificate PDF support is unavailable. Run composer install in the project directory.');
}

$verificationUrl = base_url('verify-certificate.php?id=' . rawurlencode($certificate['certificate_id']));
ob_start();
include __DIR__ . '/includes/certificate-template.php';
$template = ob_get_clean();
$html = '<!doctype html><html><head><meta charset="utf-8"></head><body>' . $template . '</body></html>';

$dompdf = new \Dompdf\Dompdf();
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

$safeName = preg_replace('/[^A-Za-z0-9_-]+/', '_', $certificate['student_name']) ?: 'Student';
$filename = $safeName . '_' . $certificate['certificate_type'] . '_Certificate.pdf';
$dompdf->stream($filename, ['Attachment' => true]);
exit;
