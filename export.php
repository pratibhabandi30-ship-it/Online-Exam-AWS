<?php
/**
 * Export endpoint.
 *  - ?type=csv&attempt=N      single attempt as CSV (owner or admin)
 *  - ?type=pdf&attempt=N      printable HTML report (browser's "Save as PDF")
 *  - ?type=attempts_csv[&quiz=N]   admin-only bulk CSV
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$me   = require_login();
$type = $_GET['type'] ?? '';
$pdo  = db();

function csv_header(string $filename): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
}

if ($type === 'csv') {
    $attemptId = (int) ($_GET['attempt'] ?? 0);
    $stmt = $pdo->prepare("SELECT a.*, u.name AS uname, u.email AS uemail, q.title AS qtitle, w.faculty_id, w.quiz_id AS managed_quiz_id, rp.published_at AS results_published_at FROM attempts a JOIN users u ON u.id=a.user_id JOIN quizzes q ON q.id=a.quiz_id LEFT JOIN exam_workflows w ON w.quiz_id=a.quiz_id LEFT JOIN result_publications rp ON rp.quiz_id=a.quiz_id WHERE a.id=? LIMIT 1");
    $stmt->execute([$attemptId]);
    $a = $stmt->fetch();
    if (!$a || !attempt_export_allowed($pdo, $me, $a)) {
        http_response_code(404); die('Not found.');
    }

    $brk = $pdo->prepare("
        SELECT q.text AS qtext, aa.is_correct, sel.text AS picked,
               (SELECT GROUP_CONCAT(text SEPARATOR ' / ') FROM options WHERE question_id=q.id AND is_correct=1) AS correct
        FROM attempt_answers aa JOIN questions q ON q.id=aa.question_id LEFT JOIN options sel ON sel.id=aa.selected_option_id
        WHERE aa.attempt_id=? ORDER BY q.position, q.id
    ");
    $brk->execute([$attemptId]);

    csv_header('attempt-' . $attemptId . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Online Exam System – Attempt report']);
    fputcsv($out, []);
    fputcsv($out, ['Student', $a['uname'], 'Email', $a['uemail']]);
    fputcsv($out, ['Quiz',    $a['qtitle']]);
    fputcsv($out, ['Submitted at', $a['submitted_at'], 'Time taken', format_duration_seconds((int)$a['time_taken_sec'])]);
    fputcsv($out, ['Score',  $a['score'] . '/' . $a['total_marks'], 'Percent', $a['total_marks']>0 ? round($a['score']*100/$a['total_marks'],1).'%' : '0%']);
    fputcsv($out, ['Correct', $a['correct_count'], 'Wrong', $a['wrong_count'], 'Skipped', $a['unanswered_count']]);
    fputcsv($out, []);
    fputcsv($out, ['#','Question','Your answer','Correct answer','Result']);
    $i = 1;
    foreach ($brk as $b) {
        fputcsv($out, [$i++, $b['qtext'], $b['picked'] ?? '— skipped —', $b['correct'], $b['is_correct'] ? 'Correct' : 'Wrong']);
    }
    fclose($out);
    exit;
}

if ($type === 'pdf') {
    $attemptId = (int) ($_GET['attempt'] ?? 0);
    $stmt = $pdo->prepare("SELECT a.*, u.name AS uname, u.email AS uemail, u.college, q.title AS qtitle, q.tag, q.passing_percent, w.faculty_id, w.quiz_id AS managed_quiz_id, rp.published_at AS results_published_at FROM attempts a JOIN users u ON u.id=a.user_id JOIN quizzes q ON q.id=a.quiz_id LEFT JOIN exam_workflows w ON w.quiz_id=a.quiz_id LEFT JOIN result_publications rp ON rp.quiz_id=a.quiz_id WHERE a.id=? LIMIT 1");
    $stmt->execute([$attemptId]);
    $a = $stmt->fetch();
    if (!$a || !attempt_export_allowed($pdo, $me, $a)) {
        http_response_code(404); die('Not found.');
    }
    $brk = $pdo->prepare("
        SELECT q.text AS qtext, aa.is_correct, sel.text AS picked, q.marks,
               (SELECT GROUP_CONCAT(text SEPARATOR ' / ') FROM options WHERE question_id=q.id AND is_correct=1) AS correct
        FROM attempt_answers aa JOIN questions q ON q.id=aa.question_id LEFT JOIN options sel ON sel.id=aa.selected_option_id
        WHERE aa.attempt_id=? ORDER BY q.position, q.id
    ");
    $brk->execute([$attemptId]);
    $rows = $brk->fetchAll();
    $pct = $a['total_marks']>0 ? round($a['score']*100/$a['total_marks'],1) : 0;
    $grade = grade_letter($pct);
    $passed = $pct >= (float) $a['passing_percent'];

    ?><!doctype html>
    <html><head>
      <meta charset="utf-8">
      <title>Attempt report · <?= e($a['qtitle']) ?></title>
      <style>
        @page { size: A4; margin: 1.2cm; }
        body { font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; color:#111; }
        h1 { font-size: 22px; margin: 0 0 6px; }
        h1 span { color: #4f46e5; }
        .sub { color: #555; font-size: 13px; }
        .grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 10px; margin: 18px 0; }
        .card { border: 1px solid #e5e7eb; border-radius: 10px; padding: 10px 14px; }
        .card .lbl { font-size: 11px; color: #6b7280; text-transform: uppercase; }
        .card .val { font-size: 20px; font-weight: 700; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; }
        .ok   { background:#dcfce7; color:#166534; }
        .bad  { background:#fee2e2; color:#991b1b; }
        .warn { background:#fef3c7; color:#92400e; }
        table { width:100%; border-collapse: collapse; margin-top: 12px; font-size: 13px; }
        th, td { border-bottom:1px solid #e5e7eb; padding: 8px; text-align:left; vertical-align: top; }
        th { background:#f9fafb; }
        .toolbar { margin-bottom: 12px; }
        .toolbar button { background:#4f46e5; color:white; border:0; padding:8px 14px; border-radius:8px; font-size:13px; cursor:pointer; }
        @media print { .toolbar { display:none; } }
      </style>
    </head>
    <body>
      <div class="toolbar">
        <button onclick="window.print()">Print / Save as PDF</button>
      </div>

      <h1>Online Exam System · <span>Attempt report</span></h1>
      <div class="sub"><?= e($a['qtitle']) ?> &middot; <?= e($a['tag'] ?: 'General') ?> &middot; Submitted <?= e(date('d M Y, H:i', strtotime($a['submitted_at']))) ?></div>

      <div class="grid">
        <div class="card"><div class="lbl">Student</div><div class="val" style="font-size:15px"><?= e($a['uname']) ?></div><div class="sub"><?= e($a['uemail']) ?></div></div>
        <div class="card"><div class="lbl">Score</div><div class="val"><?= (int)$a['score'] ?>/<?= (int)$a['total_marks'] ?></div></div>
        <div class="card"><div class="lbl">Percent</div><div class="val"><?= $pct ?>%</div>
          <span class="badge <?= $passed?'ok':'bad' ?>"><?= $passed?'PASSED':'FAILED' ?> · <?= e($grade) ?></span>
        </div>
        <div class="card"><div class="lbl">Time taken</div><div class="val" style="font-size:16px"><?= e(format_duration_seconds((int)$a['time_taken_sec'])) ?></div></div>
      </div>

      <p class="sub">Correct: <strong><?= (int) $a['correct_count'] ?></strong> &middot; Wrong: <strong><?= (int) $a['wrong_count'] ?></strong> &middot; Skipped: <strong><?= (int) $a['unanswered_count'] ?></strong></p>

      <table>
        <thead><tr><th style="width:30px">#</th><th>Question</th><th>Your answer</th><th>Correct</th><th>Result</th></tr></thead>
        <tbody>
        <?php $i=1; foreach ($rows as $r): ?>
          <tr>
            <td><?= $i++ ?></td>
            <td><?= e($r['qtext']) ?></td>
            <td><?= e($r['picked'] ?? '— skipped —') ?></td>
            <td><?= e($r['correct'] ?? '') ?></td>
            <td><span class="badge <?= $r['is_correct']?'ok':($r['picked']?'bad':'warn') ?>"><?= $r['is_correct']?'Correct':($r['picked']?'Wrong':'Skipped') ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>

      <p class="sub" style="margin-top:24px">Generated by Online Exam System on <?= e(date('d M Y, H:i')) ?>.</p>
      <script>window.onload = () => setTimeout(() => window.print(), 350);</script>
    </body></html>
    <?php
    exit;
}

function attempt_export_allowed(PDO $pdo, array $user, array $attempt): bool
{
    if ($user['role'] === 'admin') {
        return true;
    }
    if ($user['role'] === 'student') {
        return (int) $attempt['user_id'] === (int) $user['id']
            && ($attempt['managed_quiz_id'] === null || $attempt['results_published_at'] !== null);
    }
    if ($user['role'] === 'faculty' && (int) ($attempt['faculty_id'] ?? 0) === (int) $user['id']) {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM exam_assigned_students WHERE quiz_id = ? AND student_id = ?'
        );
        $stmt->execute([(int) $attempt['quiz_id'], (int) $attempt['user_id']]);
        return (int) $stmt->fetchColumn() > 0;
    }
    return false;
}

if ($type === 'attempts_csv') {
    if ($me['role'] !== 'admin') { http_response_code(403); die('Admin only.'); }
    $quizId = (int) ($_GET['quiz'] ?? 0);
    $sql = "SELECT a.id, a.submitted_at, u.name AS uname, u.email AS uemail, u.college, q.title AS qtitle, q.tag, a.score, a.total_marks, a.correct_count, a.wrong_count, a.unanswered_count, a.time_taken_sec
            FROM attempts a JOIN users u ON u.id=a.user_id JOIN quizzes q ON q.id=a.quiz_id WHERE 1=1";
    $args = [];
    if ($quizId > 0) { $sql .= " AND a.quiz_id=?"; $args[] = $quizId; }
    $sql .= " ORDER BY a.submitted_at DESC";
    $stmt = $pdo->prepare($sql); $stmt->execute($args);

    csv_header('attempts-' . date('Ymd-His') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Attempt ID','Submitted','Student','Email','College','Quiz','Tag','Score','Total','Correct','Wrong','Skipped','Percent','Time (sec)']);
    foreach ($stmt as $r) {
        $pct = $r['total_marks']>0 ? round($r['score']*100/$r['total_marks'],1) : 0;
        fputcsv($out, [$r['id'], $r['submitted_at'], $r['uname'], $r['uemail'], $r['college'], $r['qtitle'], $r['tag'], $r['score'], $r['total_marks'], $r['correct_count'], $r['wrong_count'], $r['unanswered_count'], $pct . '%', $r['time_taken_sec']]);
    }
    fclose($out);
    exit;
}

http_response_code(400);
echo 'Unknown export type.';
