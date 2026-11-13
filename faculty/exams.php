<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/exam-workflows.php';
require_once __DIR__ . '/../includes/question-types.php';

$faculty = require_faculty();
$pdo = db();
$errors = [];
$editId = (int) ($_GET['edit'] ?? 0);
$editing = null;
$editQuestions = [];
$formQuestions = [];
$selectedStudents = [];
$formValues = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : [];

if ($editId > 0) {
    $stmt = $pdo->prepare(
        "SELECT q.*, w.status, w.scheduled_start, w.scheduled_end, w.instructions, w.hod_remarks, w.reviewed_at
         FROM quizzes q JOIN exam_workflows w ON w.quiz_id = q.id
         WHERE q.id = ? AND w.faculty_id = ? AND w.status IN ('draft','rejected')"
    );
    $stmt->execute([$editId, (int) $faculty['id']]);
    $editing = $stmt->fetch() ?: null;
    if (!$editing) {
        flash('error', 'Only your draft or rejected examinations can be edited.');
        redirect('faculty/exams.php');
    }
    $studentsQuery = $pdo->prepare('SELECT student_id FROM exam_assigned_students WHERE quiz_id = ?');
    $studentsQuery->execute([$editId]);
    $selectedStudents = array_map('intval', $studentsQuery->fetchAll(PDO::FETCH_COLUMN));
    $questionQuery = $pdo->prepare(
        'SELECT q.id, q.text, q.marks, q.difficulty, q.question_type, q.programming_language,
                q.sample_input, q.expected_output, q.coding_constraints,
                o.text AS option_text, o.is_correct, o.position AS option_position
         FROM questions q LEFT JOIN options o ON o.question_id = q.id
         WHERE q.quiz_id = ? ORDER BY q.position, o.position'
    );
    $questionQuery->execute([$editId]);
    foreach ($questionQuery->fetchAll() as $row) {
        $questionId = (int) $row['id'];
        if (!isset($editQuestions[$questionId])) {
            $editQuestions[$questionId] = [
                'text' => $row['text'],
                'marks' => (int) $row['marks'],
                'type' => $row['question_type'] ?: 'mcq',
                'programming_language' => $row['programming_language'] ?? '',
                'sample_input' => $row['sample_input'] ?? '',
                'expected_output' => $row['expected_output'] ?? '',
                'coding_constraints' => $row['coding_constraints'] ?? '',
                'options' => [],
                'pairs' => [],
                'test_cases' => [],
            ];
        }
        if ($row['option_text'] !== null) {
            $optionIndex = (int) $row['option_position'] - 1;
            $editQuestions[$questionId]['options'][$optionIndex] = [
                'text' => $row['option_text'],
                'correct' => (int) $row['is_correct'] === 1,
            ];
            if ((int) $row['is_correct']) {
                $editQuestions[$questionId]['correct'] = $optionIndex;
            }
        }
    }
    $pairQuery = $pdo->prepare(
        'SELECT id, left_text, right_text, marks FROM question_matching_pairs WHERE question_id = ? ORDER BY position, id'
    );
    $testCaseQuery = $pdo->prepare(
        'SELECT input_data, expected_output FROM question_coding_test_cases WHERE question_id = ? ORDER BY position, id'
    );
    foreach ($editQuestions as $questionId => &$question) {
        $pairQuery->execute([(int) $questionId]);
        $question['pairs'] = $pairQuery->fetchAll();
        $testCaseQuery->execute([(int) $questionId]);
        $question['test_cases'] = $testCaseQuery->fetchAll();
        $question['options'] = array_values($question['options']);
    }
    unset($question);
    $formQuestions = array_values($editQuestions);
}

$studentsQuery = $pdo->prepare(
    "SELECT u.id, u.name, u.email, sp.registration_no, sp.branch
     FROM users u
     LEFT JOIN student_profiles sp ON sp.user_id = u.id
     WHERE u.role = 'student' AND u.is_active = 1
       AND NOT EXISTS (
           SELECT 1 FROM audit_logs deleted
           WHERE deleted.action = 'account.deleted' AND deleted.entity_type = 'student'
             AND deleted.entity_id = CAST(u.id AS CHAR)
       )
     ORDER BY u.name, u.id"
);
$studentsQuery->execute();
$eligibleStudents = $studentsQuery->fetchAll();
$studentBranches = array_values(array_unique(array_filter(array_column($eligibleStudents, 'branch'))));
sort($studentBranches, SORT_NATURAL | SORT_FLAG_CASE);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'save_exam') {
            $intent = (string) ($_POST['intent'] ?? 'draft');
            if (!in_array($intent, ['draft', 'request'], true)) {
                throw new InvalidArgumentException('Choose Save Draft or Submit for HOD Approval.');
            }
            $quizId = (int) ($_POST['quiz_id'] ?? 0);
            $title = clean_string($_POST['title'] ?? '', 150);
            $subject = clean_string($_POST['subject'] ?? '', 60);
            $description = clean_string($_POST['description'] ?? '', 1000);
            $duration = (int) ($_POST['duration'] ?? 0);
            $passingPercent = (int) ($_POST['passing_percent'] ?? 50);
            $startRaw = clean_string($_POST['scheduled_start'] ?? '', 16);
            $endRaw = clean_string($_POST['scheduled_end'] ?? '', 16);
            $instructions = clean_string($_POST['instructions'] ?? '', 2000);
            $rawStudentIds = $_POST['students'] ?? [];
            if (!is_array($rawStudentIds)) {
                throw new InvalidArgumentException('Choose valid students for this examination.');
            }
            $studentIds = [];
            foreach ($rawStudentIds as $value) {
                if (!is_string($value) && !is_int($value)) {
                    throw new InvalidArgumentException('Choose valid students for this examination.');
                }
                $studentId = filter_var((string) $value, FILTER_VALIDATE_INT);
                if ($studentId === false || $studentId < 1) {
                    throw new InvalidArgumentException('Choose valid students for this examination.');
                }
                $studentIds[] = (int) $studentId;
            }
            $studentIds = array_values(array_unique($studentIds));
            $rawQuestions = is_array($_POST['questions'] ?? null) ? $_POST['questions'] : [];
            $formQuestions = array_values(array_filter($rawQuestions, 'is_array'));

            if (mb_strlen($title) < 3 || $duration < 1 || $duration > 300 || $passingPercent < 0 || $passingPercent > 100) {
                throw new InvalidArgumentException('Enter a title, duration from 1 to 300 minutes, and passing percentage from 0 to 100.');
            }
            if (!$studentIds) {
                throw new InvalidArgumentException('Select at least one student for this examination.');
            }
            if (($startRaw === '') !== ($endRaw === '')) {
                throw new InvalidArgumentException('Enter both schedule values or leave both blank while the exam is a draft.');
            }
            if ($startRaw !== '') {
                validate_exam_schedule($startRaw, $endRaw, $duration, $intent === 'request');
            } elseif ($intent === 'request') {
                throw new InvalidArgumentException('Set the examination date, start time, and end time before requesting HOD approval.');
            }
            $questions = [];
            foreach ($rawQuestions as $raw) {
                if (!is_array($raw)) {
                    continue;
                }
                $type = (string) ($raw['type'] ?? 'mcq');
                if (!in_array($type, ['mcq', 'fill_blank', 'match', 'true_false', 'coding'], true)) {
                    throw new InvalidArgumentException('Choose a supported question type.');
                }
                $text = clean_string($raw['text'] ?? '', 1000);
                $rawOptions = is_array($raw['options'] ?? null) ? $raw['options'] : [];
                $rawPairs = is_array($raw['pairs'] ?? null) ? $raw['pairs'] : [];
                $rawCases = is_array($raw['test_cases'] ?? null) ? $raw['test_cases'] : [];
                $hasContent = $text !== '' || $rawOptions || $rawPairs || !empty($raw['programming_language']);
                if (!$hasContent) {
                    continue;
                }
                if (mb_strlen($text) < 3) {
                    throw new InvalidArgumentException('Each question needs at least 3 characters of question text.');
                }
                $marks = filter_var($raw['marks'] ?? null, FILTER_VALIDATE_INT);
                $options = [];
                $pairs = [];
                $testCases = [];
                $correct = filter_var($raw['correct'] ?? null, FILTER_VALIDATE_INT);
                $programmingLanguage = null;
                $sampleInput = null;
                $expectedOutput = null;
                $codingConstraints = null;

                if (in_array($type, ['mcq', 'fill_blank', 'true_false'], true)) {
                    $minimum = $type === 'mcq' ? 4 : 2;
                    foreach ($rawOptions as $rawOption) {
                        $optionText = is_array($rawOption)
                            ? clean_string($rawOption['text'] ?? '', 500)
                            : clean_string(is_string($rawOption) ? $rawOption : '', 500);
                        if ($optionText !== '') {
                            $options[] = $optionText;
                        }
                    }
                    $maximum = $type === 'fill_blank' ? 12 : 4;
                    if ($type === 'true_false') {
                        $options = ['True', 'False'];
                        $maximum = 2;
                    }
                    if (count($options) < $minimum || count($options) > $maximum
                        || count(array_unique(array_map('mb_strtolower', $options))) !== count($options)
                        || $correct === false || $correct < 0 || $correct >= count($options)
                        || $marks === false || $marks < 1 || $marks > 255) {
                        throw new InvalidArgumentException(
                            $type === 'mcq'
                                ? 'MCQ questions require four different options, one correct answer, and 1–255 marks.'
                                : ($type === 'true_false'
                                    ? 'True/False questions require a correct answer and 1–255 marks.'
                                    : 'Fill-in-the-blank questions need a blank (____), 2–12 different options, one correct answer, and 1–255 marks.')
                        );
                    }
                    if ($type === 'fill_blank' && !preg_match('/_{2,}/', $text)) {
                        throw new InvalidArgumentException('Fill-in-the-blank text must include a blank marker such as ____.');
                    }
                    if ($type === 'true_false') {
                        $correct = (int) $correct;
                    }
                } elseif ($type === 'match') {
                    $marks = 0;
                    foreach ($rawPairs as $rawPair) {
                        if (!is_array($rawPair)) {
                            continue;
                        }
                        $left = clean_string($rawPair['left'] ?? '', 500);
                        $right = clean_string($rawPair['right'] ?? '', 500);
                        $pairMarks = filter_var($rawPair['marks'] ?? null, FILTER_VALIDATE_INT);
                        if ($left === '' && $right === '') {
                            continue;
                        }
                        if ($left === '' || $right === '' || $pairMarks === false || $pairMarks < 1 || $pairMarks > 255) {
                            throw new InvalidArgumentException('Every matching pair needs both sides and 1–255 marks.');
                        }
                        $pairs[] = ['left' => $left, 'right' => $right, 'marks' => $pairMarks];
                        $marks += $pairMarks;
                    }
                    if (count($pairs) < 2 || count($pairs) > 50 || $marks > 255
                        || count(array_unique(array_column($pairs, 'left'))) !== count($pairs)
                        || count(array_unique(array_column($pairs, 'right'))) !== count($pairs)) {
                        throw new InvalidArgumentException('Matching questions need 2–50 unique pairs and a total of no more than 255 marks.');
                    }
                } else {
                    $programmingLanguage = (string) ($raw['programming_language'] ?? '');
                    if (!in_array($programmingLanguage, ['PHP', 'Python', 'C', 'C++', 'Java', 'JavaScript'], true)
                        || $marks === false || $marks < 1 || $marks > 255) {
                        throw new InvalidArgumentException('Choose a supported programming language and allocate 1–255 marks.');
                    }
                    $sampleInput = trim(is_string($raw['sample_input'] ?? null) ? $raw['sample_input'] : '');
                    $expectedOutput = trim(is_string($raw['expected_output'] ?? null) ? $raw['expected_output'] : '');
                    $codingConstraints = trim(is_string($raw['coding_constraints'] ?? null) ? $raw['coding_constraints'] : '');
                    if (($sampleInput === '') !== ($expectedOutput === '')) {
                        throw new InvalidArgumentException('Provide both sample input and expected output, or leave both blank.');
                    }
                    foreach ($rawCases as $rawCase) {
                        if (!is_array($rawCase)) {
                            continue;
                        }
                        $caseInput = trim(is_string($rawCase['input'] ?? null) ? $rawCase['input'] : '');
                        $caseOutput = trim(is_string($rawCase['expected_output'] ?? null) ? $rawCase['expected_output'] : '');
                        if ($caseInput !== '' || $caseOutput !== '') {
                            if ($caseInput === '' || $caseOutput === '') {
                                throw new InvalidArgumentException('Each hidden test case needs both input and expected output.');
                            }
                            $testCases[] = ['input' => mb_substr($caseInput, 0, 10000), 'expected_output' => mb_substr($caseOutput, 0, 10000)];
                        }
                    }
                    if (count($testCases) > 50) {
                        throw new InvalidArgumentException('A coding question can have at most 50 hidden test cases.');
                    }
                    $sampleInput = $sampleInput === '' ? null : mb_substr($sampleInput, 0, 10000);
                    $expectedOutput = $expectedOutput === '' ? null : mb_substr($expectedOutput, 0, 10000);
                    $codingConstraints = $codingConstraints === '' ? null : mb_substr($codingConstraints, 0, 5000);
                }
                $questions[] = [
                    'type' => $type,
                    'text' => $text,
                    'marks' => (int) $marks,
                    'options' => $options,
                    'correct' => $correct === false ? 0 : (int) $correct,
                    'pairs' => $pairs,
                    'programming_language' => $programmingLanguage,
                    'sample_input' => $sampleInput,
                    'expected_output' => $expectedOutput,
                    'coding_constraints' => $codingConstraints,
                    'test_cases' => $testCases,
                ];
            }
            $formQuestions = $questions;
            if (!$questions || count($questions) > 100) {
                throw new InvalidArgumentException('Add between 1 and 100 complete multiple-choice questions.');
            }
            $questionTotalMarks = array_sum(array_column($questions, 'marks'));
            if ($questionTotalMarks < 1) {
                throw new InvalidArgumentException('Total marks must be at least one.');
            }

            $isExistingExam = $quizId > 0;
            $pdo->beginTransaction();
            $studentPlaceholders = implode(',', array_fill(0, count($studentIds), '?'));
            $activeStudents = $pdo->prepare(
                "SELECT id FROM users
                 WHERE id IN ($studentPlaceholders) AND role = 'student' AND is_active = 1
                 ORDER BY id FOR UPDATE"
            );
            $sortedStudentIds = $studentIds;
            sort($sortedStudentIds, SORT_NUMERIC);
            $activeStudents->execute($sortedStudentIds);
            $activeStudentIds = array_map('intval', $activeStudents->fetchAll(PDO::FETCH_COLUMN));
            if ($activeStudentIds !== $sortedStudentIds) {
                throw new InvalidArgumentException('One or more selected students are no longer active.');
            }
            $deletedStudents = $pdo->prepare(
                "SELECT COUNT(*) FROM audit_logs
                 WHERE action = 'account.deleted' AND entity_type = 'student'
                   AND entity_id IN ($studentPlaceholders)"
            );
            $deletedStudents->execute(array_map('strval', $studentIds));
            if ((int) $deletedStudents->fetchColumn() > 0) {
                throw new InvalidArgumentException('Deleted student accounts cannot be added to an examination.');
            }
            if ($quizId > 0) {
                $owned = $pdo->prepare(
                    "SELECT q.id, w.status FROM quizzes q JOIN exam_workflows w ON w.quiz_id = q.id
                     WHERE q.id = ? AND w.faculty_id = ? FOR UPDATE"
                );
                $owned->execute([$quizId, (int) $faculty['id']]);
                $ownedRow = $owned->fetch();
                if (!$ownedRow || !in_array($ownedRow['status'], ['draft', 'rejected'], true)) {
                    throw new InvalidArgumentException('That examination can no longer be edited.');
                }
                $attempts = $pdo->prepare('SELECT COUNT(*) FROM attempts WHERE quiz_id = ?');
                $attempts->execute([$quizId]);
                if ((int) $attempts->fetchColumn() > 0) {
                    throw new InvalidArgumentException('An examination with attempts cannot be rescheduled or reassigned.');
                }
                $pdo->prepare(
                    'UPDATE quizzes SET title = ?, tag = ?, intro = ?, duration_minutes = ?, passing_percent = ?, is_published = 0
                     WHERE id = ?'
                )->execute([$title, $subject ?: null, $description ?: null, $duration, $passingPercent, $quizId]);
                $pdo->prepare(
                    "UPDATE exam_workflows SET scheduled_start = ?, scheduled_end = ?, instructions = ?, status = 'draft'
                     WHERE quiz_id = ?"
                )->execute([
                    $startRaw !== '' ? str_replace('T', ' ', $startRaw) . ':00' : null,
                    $endRaw !== '' ? str_replace('T', ' ', $endRaw) . ':00' : null,
                    $instructions ?: null,
                    $quizId,
                ]);
                $pdo->prepare('DELETE FROM questions WHERE quiz_id = ?')->execute([$quizId]);
                $pdo->prepare('DELETE FROM exam_assigned_students WHERE quiz_id = ?')->execute([$quizId]);
            } else {
                $pdo->prepare(
                    'INSERT INTO quizzes (title, tag, intro, duration_minutes, passing_percent, difficulty, is_published, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, 0, ?)'
                )->execute([$title, $subject ?: null, $description ?: null, $duration, $passingPercent, 'Beginner', (int) $faculty['id']]);
                $quizId = (int) $pdo->lastInsertId();
                $pdo->prepare(
                    "INSERT INTO exam_workflows (quiz_id, faculty_id, status, scheduled_start, scheduled_end, instructions)
                     VALUES (?, ?, 'draft', ?, ?, ?)"
                )->execute([
                    $quizId,
                    (int) $faculty['id'],
                    $startRaw !== '' ? str_replace('T', ' ', $startRaw) . ':00' : null,
                    $endRaw !== '' ? str_replace('T', ' ', $endRaw) . ':00' : null,
                    $instructions ?: null,
                ]);
            }
            $insertQuestion = $pdo->prepare(
                "INSERT INTO questions (quiz_id, text, marks, difficulty, position, question_type,
                    programming_language, sample_input, expected_output, coding_constraints)
                 VALUES (?, ?, ?, 'Beginner', ?, ?, ?, ?, ?, ?)"
            );
            $insertOption = $pdo->prepare(
                'INSERT INTO options (question_id, text, is_correct, position) VALUES (?, ?, ?, ?)'
            );
            $insertPair = $pdo->prepare(
                'INSERT INTO question_matching_pairs (question_id, left_text, right_text, marks, position) VALUES (?, ?, ?, ?, ?)'
            );
            $insertTestCase = $pdo->prepare(
                'INSERT INTO question_coding_test_cases (question_id, input_data, expected_output, position) VALUES (?, ?, ?, ?)'
            );
            foreach ($questions as $position => $question) {
                $insertQuestion->execute([
                    $quizId,
                    $question['text'],
                    $question['marks'],
                    $position + 1,
                    $question['type'],
                    $question['programming_language'],
                    $question['sample_input'],
                    $question['expected_output'],
                    $question['coding_constraints'],
                ]);
                $questionId = (int) $pdo->lastInsertId();
                foreach ($question['options'] as $optionPosition => $option) {
                    $insertOption->execute([$questionId, $option, $optionPosition === $question['correct'] ? 1 : 0, $optionPosition + 1]);
                }
                foreach ($question['pairs'] as $pairPosition => $pair) {
                    $insertPair->execute([$questionId, $pair['left'], $pair['right'], $pair['marks'], $pairPosition + 1]);
                }
                foreach ($question['test_cases'] as $casePosition => $testCase) {
                    $insertTestCase->execute([$questionId, $testCase['input'], $testCase['expected_output'], $casePosition + 1]);
                }
            }
            $assign = $pdo->prepare('INSERT INTO exam_assigned_students (quiz_id, student_id) VALUES (?, ?)');
            foreach ($studentIds as $studentId) {
                $assign->execute([$quizId, $studentId]);
            }
            $auditAction = $isExistingExam ? 'exam.saved' : 'exam.created';
            if ($intent === 'request') {
                $scheduleStart = new DateTimeImmutable($startRaw);
                $scheduleEnd = new DateTimeImmutable($endRaw);
                ensure_no_exam_schedule_conflict($pdo, $quizId, $scheduleStart, $scheduleEnd);
                $pdo->prepare(
                    "UPDATE exam_workflows SET status = 'pending', requested_at = NOW(), reviewed_at = NULL,
                        reviewed_by = NULL, hod_remarks = NULL WHERE quiz_id = ?"
                )->execute([$quizId]);
                notify_role(
                    $pdo,
                    'admin',
                    'exam_request',
                    'Exam approval requested',
                    $title . ' is awaiting your review.',
                    'admin/approvals.php'
                );
                $auditAction = 'exam.requested';
            }
            $pdo->prepare(
                'INSERT INTO audit_logs (actor_id, action, entity_type, entity_id) VALUES (?, ?, ?, ?)'
            )->execute([(int) $faculty['id'], $auditAction, 'quiz', (string) $quizId]);
            $pdo->commit();
            flash(
                'success',
                $intent === 'request'
                    ? 'Exam submitted for HOD approval. Status: Pending Approval.'
                    : ($isExistingExam ? 'Draft saved.' : 'Exam draft created.')
            );
            redirect('faculty/exams.php');
        }

        if ($action === 'submit_request') {
            $quizId = (int) ($_POST['quiz_id'] ?? 0);
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                "SELECT q.title, q.duration_minutes, w.status, w.scheduled_start, w.scheduled_end
                 FROM quizzes q JOIN exam_workflows w ON w.quiz_id = q.id
                 WHERE q.id = ? AND w.faculty_id = ? FOR UPDATE"
            );
            $stmt->execute([$quizId, (int) $faculty['id']]);
            $exam = $stmt->fetch();
            if (!$exam || !in_array($exam['status'], ['draft', 'rejected'], true)) {
                throw new InvalidArgumentException('Only a draft or rejected exam can be submitted.');
            }
            $questionCount = $pdo->prepare('SELECT COUNT(*) FROM questions WHERE quiz_id = ?');
            $questionCount->execute([$quizId]);
            $assignedCount = $pdo->prepare('SELECT COUNT(*) FROM exam_assigned_students WHERE quiz_id = ?');
            $assignedCount->execute([$quizId]);
            if ((int) $questionCount->fetchColumn() < 1 || (int) $assignedCount->fetchColumn() < 1) {
                throw new InvalidArgumentException('The exam needs questions and at least one assigned student before approval.');
            }
            if (!$exam['scheduled_start'] || !$exam['scheduled_end']) {
                throw new InvalidArgumentException('Set the examination schedule before requesting HOD approval.');
            }
            $startAt = new DateTimeImmutable($exam['scheduled_start']);
            $endAt = new DateTimeImmutable($exam['scheduled_end']);
            validate_exam_schedule($startAt->format('Y-m-d\TH:i'), $endAt->format('Y-m-d\TH:i'), (int) $exam['duration_minutes']);
            ensure_no_exam_schedule_conflict($pdo, $quizId, $startAt, $endAt);
            $pdo->prepare(
                "UPDATE exam_workflows SET status = 'pending', requested_at = NOW(), reviewed_at = NULL,
                    reviewed_by = NULL, hod_remarks = NULL WHERE quiz_id = ?"
            )->execute([$quizId]);
            notify_role($pdo, 'admin', 'exam_request', 'Exam approval requested', $exam['title'] . ' is awaiting your review.', 'admin/approvals.php');
            $pdo->prepare(
                'INSERT INTO audit_logs (actor_id, action, entity_type, entity_id) VALUES (?, ?, ?, ?)'
            )->execute([(int) $faculty['id'], 'exam.requested', 'quiz', (string) $quizId]);
            $pdo->commit();
            flash('success', 'Exam request sent to the HOD.');
            redirect('faculty/exams.php');
        }

        if ($action === 'publish_exam') {
            $quizId = (int) ($_POST['quiz_id'] ?? 0);
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                "SELECT q.title, w.status, w.scheduled_start FROM quizzes q JOIN exam_workflows w ON w.quiz_id = q.id
                 WHERE q.id = ? AND w.faculty_id = ? FOR UPDATE"
            );
            $stmt->execute([$quizId, (int) $faculty['id']]);
            $exam = $stmt->fetch();
            if (!$exam || $exam['status'] !== 'approved') {
                throw new InvalidArgumentException('Only an HOD-approved exam can be published.');
            }
            $pdo->prepare('UPDATE quizzes SET is_published = 1 WHERE id = ?')->execute([$quizId]);
            $pdo->prepare(
                "UPDATE exam_workflows SET status = 'published', published_at = NOW() WHERE quiz_id = ?"
            )->execute([$quizId]);
            $recipients = $pdo->prepare('SELECT student_id FROM exam_assigned_students WHERE quiz_id = ?');
            $recipients->execute([$quizId]);
            foreach ($recipients->fetchAll(PDO::FETCH_COLUMN) as $studentId) {
                notify_user($pdo, (int) $studentId, 'exam_published', 'New examination published', $exam['title'] . ' is scheduled for ' . date('d M Y, H:i', strtotime($exam['scheduled_start'])) . '.', 'exams.php');
            }
            $pdo->prepare(
                'INSERT INTO audit_logs (actor_id, action, entity_type, entity_id) VALUES (?, ?, ?, ?)'
            )->execute([(int) $faculty['id'], 'exam.published', 'quiz', (string) $quizId]);
            $pdo->commit();
            flash('success', 'Approved exam published to its assigned students.');
            redirect('faculty/exams.php');
        }

        throw new InvalidArgumentException('Unknown examination action.');
    } catch (InvalidArgumentException $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errors[] = $error->getMessage();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Faculty exam workflow failed: ' . $error->getMessage());
        $errors[] = 'Could not save this examination. Please check the schedule and try again.';
    }
}

$examsQuery = $pdo->prepare(
    'SELECT q.id, q.title, q.duration_minutes, w.status, w.scheduled_start, w.scheduled_end,
            w.hod_remarks, w.reviewed_at, w.requested_at, reviewer.name AS reviewer_name,
            (SELECT COUNT(*) FROM exam_assigned_students s WHERE s.quiz_id = q.id) AS student_count,
            (SELECT COUNT(*) FROM questions questions WHERE questions.quiz_id = q.id) AS question_count,
            (SELECT COALESCE(SUM(questions.marks), 0) FROM questions questions WHERE questions.quiz_id = q.id) AS total_marks
     FROM exam_workflows w JOIN quizzes q ON q.id = w.quiz_id
     LEFT JOIN users reviewer ON reviewer.id = w.reviewed_by
     WHERE w.faculty_id = ? ORDER BY w.updated_at DESC'
);
$examsQuery->execute([(int) $faculty['id']]);
$exams = $examsQuery->fetchAll();

$pageTitle = 'Faculty · Examinations';
$activeNav = 'faculty-exams';
$facultyShellEnabled = true;
include __DIR__ . '/../includes/header.php';
$facultyActive = 'exams';
include __DIR__ . '/_nav.php';
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-end mb-3">
    <div><h2 class="mb-1">My examinations</h2><p class="text-muted mb-0">Create drafts, request HOD approval, and publish approved exams.</p></div>
    <?php if (!$editing): ?><a href="<?= e(base_url('faculty/exams.php?new=1')) ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Create exam</a><?php endif; ?>
  </div>
  <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>

  <?php if (isset($_GET['new']) || $editing): ?>
    <div class="card p-4 mb-4">
      <h5><?= $editing ? 'Edit exam draft' : 'Create examination' ?></h5>
      <?php if ($editing && $editing['hod_remarks']): ?>
        <div class="alert alert-warning">
          <strong>HOD remarks:</strong> <?= e($editing['hod_remarks']) ?>
          <?php if (!empty($editing['reviewed_at'])): ?><div class="small mt-1">Reviewed <?= e(date('d M Y, H:i', strtotime($editing['reviewed_at']))) ?></div><?php endif; ?>
        </div>
      <?php endif; ?>
      <form method="post" id="examBuilder" novalidate>
        <?= csrf_field() ?><input type="hidden" name="action" value="save_exam">
        <?php if ($editing): ?><input type="hidden" name="quiz_id" value="<?= (int) $editing['id'] ?>"><?php endif; ?>
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label" for="examTitle">Examination title</label><input class="form-control" id="examTitle" name="title" minlength="3" maxlength="150" required value="<?= e((string) ($formValues['title'] ?? $editing['title'] ?? '')) ?>"></div>
          <div class="col-md-6"><label class="form-label" for="examSubject">Subject / course</label><input class="form-control" id="examSubject" name="subject" maxlength="60" value="<?= e((string) ($formValues['subject'] ?? $editing['tag'] ?? '')) ?>"></div>
          <div class="col-12"><label class="form-label" for="examDescription">Description</label><textarea class="form-control" id="examDescription" name="description" rows="2"><?= e((string) ($formValues['description'] ?? $editing['intro'] ?? '')) ?></textarea></div>
          <div class="col-md-3"><label class="form-label" for="examDuration">Duration (minutes)</label><input class="form-control" id="examDuration" type="number" name="duration" min="1" max="300" required value="<?= e((string) ($formValues['duration'] ?? $editing['duration_minutes'] ?? 30)) ?>"></div>
          <div class="col-md-3"><label class="form-label" for="passingPercent">Passing percentage</label><input class="form-control" id="passingPercent" type="number" name="passing_percent" min="0" max="100" required value="<?= e((string) ($formValues['passing_percent'] ?? $editing['passing_percent'] ?? 50)) ?>"></div>
          <div class="col-md-3"><label class="form-label" for="scheduledStart">Scheduled date and start time</label><input class="form-control" id="scheduledStart" type="datetime-local" name="scheduled_start" value="<?= e((string) ($formValues['scheduled_start'] ?? (isset($editing['scheduled_start']) && $editing['scheduled_start'] ? date('Y-m-d\TH:i', strtotime($editing['scheduled_start'])) : ''))) ?>"></div>
          <div class="col-md-3"><label class="form-label" for="scheduledEnd">Scheduled end time</label><input class="form-control" id="scheduledEnd" type="datetime-local" name="scheduled_end" value="<?= e((string) ($formValues['scheduled_end'] ?? (isset($editing['scheduled_end']) && $editing['scheduled_end'] ? date('Y-m-d\TH:i', strtotime($editing['scheduled_end'])) : ''))) ?>"></div>
          <div class="col-12"><label class="form-label" for="examInstructions">Instructions</label><textarea class="form-control" id="examInstructions" name="instructions" rows="2"><?= e((string) ($formValues['instructions'] ?? $editing['instructions'] ?? '')) ?></textarea></div>
          <div class="col-12"><label class="form-label">Students selected for this examination</label>
            <?php if (!$eligibleStudents): ?><div class="alert alert-warning">No active registered students are available for this examination.</div><?php else: ?>
            <?php $postedStudentIds = isset($formValues['students']) && is_array($formValues['students']) ? array_map('intval', $formValues['students']) : null; ?>
            <div class="row g-2 mb-2">
              <div class="col-md-6"><label class="visually-hidden" for="examStudentSearch">Search students</label><input class="form-control" type="search" id="examStudentSearch" placeholder="Search by name, email or registration"></div>
              <div class="col-md-4"><label class="visually-hidden" for="examStudentBranch">Filter students by branch</label><select class="form-select" id="examStudentBranch"><option value="">All branches</option><?php foreach ($studentBranches as $branch): ?><option value="<?= e(mb_strtolower((string) $branch)) ?>"><?= e((string) $branch) ?></option><?php endforeach; ?></select></div>
              <div class="col-md-2"><button class="btn btn-outline-secondary w-100" type="button" id="clearStudentFilters">Clear filters</button></div>
            </div>
            <div class="small text-muted mb-2">Students may be selected for this examination regardless of their faculty assignment.</div>
            <div class="row g-2" id="examStudentList"><?php foreach ($eligibleStudents as $student): ?>
              <div class="col-md-6" data-exam-student-row data-student-search="<?= e(mb_strtolower(implode(' ', [$student['name'], $student['email'], $student['registration_no'] ?? '']))) ?>" data-student-branch="<?= e(mb_strtolower((string) ($student['branch'] ?? ''))) ?>"><label class="border rounded p-2 w-100">
                <input type="checkbox" name="students[]" value="<?= (int) $student['id'] ?>" data-exam-student <?= in_array((int) $student['id'], $postedStudentIds ?? $selectedStudents, true) ? 'checked' : '' ?>>
                <?= e($student['name']) ?>
                <small class="text-muted"><?= e($student['registration_no'] ?: $student['email']) ?><?= $student['branch'] ? ' · ' . e($student['branch']) : '' ?></small>
              </label></div>
            <?php endforeach; ?></div>
            <div class="small text-muted mt-2" id="examStudentFilterEmpty" hidden>No students match these filters.</div>
            <?php endif; ?>
          </div>
        </div>
        <div id="examValidation" class="alert alert-danger mt-3 d-none" role="alert"></div>
        <hr><div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
          <h5 class="mb-0">Questions</h5>
          <div class="d-flex flex-wrap gap-2">
            <select class="form-select form-select-sm" id="questionBankSelect" aria-label="Choose a saved question"><option value="">Loading question bank…</option></select>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="importBankQuestion">Add saved question</button>
            <button type="button" class="btn btn-sm btn-outline-primary" id="addQuestion">Add question</button>
          </div>
        </div>
        <div id="questionList"></div>
        <template id="questionTemplate">
          <div class="border rounded p-3 mb-3 question-entry">
            <div class="d-flex justify-content-between align-items-center gap-2"><strong>Question</strong><div class="d-flex gap-2"><button type="button" class="btn btn-sm btn-outline-primary" data-save-bank>Save to Question Bank</button><button type="button" class="btn btn-sm btn-outline-danger remove-question">Remove</button></div></div>
            <div class="row g-2 mt-1">
              <div class="col-md-8"><label class="form-label">Question type</label><select class="form-select" data-field="type"><option value="mcq">Multiple Choice Questions (MCQ)</option><option value="fill_blank">Fill in the Blanks with Options</option><option value="match">Match the Following</option><option value="true_false">True or False</option><option value="coding">Programming / Coding Question</option></select></div>
              <div class="col-md-4"><label class="form-label">Marks</label><input class="form-control" type="number" data-field="marks" min="1" max="255" value="1" required></div>
              <div class="col-12"><label class="form-label">Question text</label><textarea class="form-control" data-field="text" rows="2" required></textarea></div>
            </div>
            <div data-section="options" class="mt-3">
              <div class="d-flex justify-content-between align-items-center"><label class="form-label mb-1">Answer options</label><button class="btn btn-sm btn-outline-secondary" type="button" data-add-option>Add option</button></div>
              <div data-options-container></div>
              <div class="form-text">Choose the correct option. MCQ requires exactly four options.</div>
            </div>
            <div data-section="matching" class="mt-3 d-none">
              <div class="d-flex justify-content-between align-items-center"><label class="form-label mb-1">Column A and Column B pairs</label><button class="btn btn-sm btn-outline-secondary" type="button" data-add-pair>Add pair</button></div>
              <div data-pairs-container></div>
              <div class="form-text">Students see a numbered/lettered two-column table with Column B randomized, then enter matches such as 1-B. Marks are awarded for each correct pair.</div>
            </div>
            <div data-section="coding" class="mt-3 d-none">
              <label class="form-label">Programming language</label>
              <select class="form-select mb-2" data-field="programming_language"><option>PHP</option><option>Python</option><option>C</option><option>C++</option><option>Java</option><option>JavaScript</option></select>
              <div class="row g-2">
                <div class="col-md-6"><label class="form-label">Sample input (optional)</label><textarea class="form-control" rows="3" data-field="sample_input"></textarea></div>
                <div class="col-md-6"><label class="form-label">Expected output (optional)</label><textarea class="form-control" rows="3" data-field="expected_output"></textarea></div>
                <div class="col-12"><label class="form-label">Coding constraints (optional)</label><textarea class="form-control" rows="2" data-field="coding_constraints"></textarea></div>
              </div>
              <div class="d-flex justify-content-between align-items-center mt-3"><label class="form-label mb-1">Hidden test cases (not shown to students)</label><button class="btn btn-sm btn-outline-secondary" type="button" data-add-test-case>Add test case</button></div>
              <div data-test-cases-container></div>
              <div class="form-text">Student code is stored for manual evaluation and is never executed on this web server.</div>
            </div>
          </div>
        </template>
        <div class="card bg-light border-0 p-3 mt-3" id="examPreview">
          <div class="d-flex justify-content-between align-items-start gap-3">
            <div><span class="badge text-bg-secondary">Preview</span><h5 class="mt-2 mb-1" data-preview-title>Examination title</h5><div class="text-muted small" data-preview-subject>Subject not set</div></div>
            <div class="text-end small"><div><strong data-preview-duration>30</strong> minutes</div><div><strong data-preview-total>0</strong> total marks</div><div>Pass: <strong data-preview-passing>50%</strong></div></div>
          </div>
          <p class="mb-2 mt-2 small" data-preview-description>No description provided.</p>
          <div class="small text-muted mb-2" data-preview-schedule>Schedule not set</div>
          <div class="small text-muted mb-2" data-preview-students>0 students selected</div>
          <ol class="mb-0 small" data-preview-questions><li class="text-muted">Add a question to preview it here.</li></ol>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-3">
          <button class="btn btn-outline-primary" type="submit" name="intent" value="draft"><i class="bi bi-save"></i> Save Draft</button>
          <button class="btn btn-primary" type="submit" name="intent" value="request"><i class="bi bi-send"></i> Submit for HOD Approval</button>
        </div>
        <a class="btn btn-outline-secondary mt-3" href="<?= e(base_url('faculty/exams.php')) ?>">Cancel</a>
      </form>
    </div>
  <?php endif; ?>

  <div class="card p-3"><div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>Exam</th><th>Schedule</th><th>Students / questions / marks</th><th>Status and dates</th><th>HOD remarks</th><th></th></tr></thead>
    <tbody><?php foreach ($exams as $exam): $badge = ['draft'=>'secondary','pending'=>'warning','approved'=>'success','rejected'=>'danger','published'=>'primary','completed'=>'dark','cancelled'=>'dark'][$exam['status']] ?? 'secondary'; ?>
      <tr>
        <td><?= e($exam['title']) ?><div class="small text-muted"><?= (int) $exam['duration_minutes'] ?> min</div></td>
        <td><?= $exam['scheduled_start'] ? e(date('d M Y, H:i', strtotime($exam['scheduled_start']))) : 'Not scheduled' ?><div class="small text-muted"><?= $exam['scheduled_end'] ? e(date('d M Y, H:i', strtotime($exam['scheduled_end']))) : '' ?></div></td>
        <td><?= (int) $exam['student_count'] ?> students · <?= (int) $exam['question_count'] ?> questions · <?= (int) $exam['total_marks'] ?> marks</td>
        <td><span class="badge bg-<?= $badge ?>"><?= e($exam['status'] === 'pending' ? 'Pending Approval' : ucfirst($exam['status'])) ?></span>
          <?php if ($exam['requested_at']): ?><div class="small text-muted mt-1">Requested <?= e(date('d M Y, H:i', strtotime($exam['requested_at']))) ?></div><?php endif; ?>
          <?php if ($exam['reviewed_at']): ?><div class="small text-muted"><?= e(ucfirst($exam['status'])) ?> by <?= e($exam['reviewer_name'] ?: 'HOD') ?> · <?= e(date('d M Y, H:i', strtotime($exam['reviewed_at']))) ?></div><?php endif; ?>
        </td>
        <td><?= e($exam['hod_remarks'] ?: '—') ?></td>
        <td class="text-end">
          <?php if (in_array($exam['status'], ['draft','rejected'], true)): ?>
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('faculty/exams.php?edit=' . (int) $exam['id'])) ?>">Edit</a>
            <?php if ($exam['question_count'] > 0 && $exam['student_count'] > 0 && $exam['scheduled_start'] && $exam['scheduled_end']): ?>
              <form method="post" class="d-inline" data-confirm="Submit this exam and schedule for HOD approval?"><?= csrf_field() ?><input type="hidden" name="action" value="submit_request"><input type="hidden" name="quiz_id" value="<?= (int) $exam['id'] ?>"><button class="btn btn-sm btn-outline-primary">Request approval</button></form>
            <?php endif; ?>
          <?php elseif ($exam['status'] === 'approved'): ?>
            <form method="post" class="d-inline" data-confirm="Publish this approved examination to assigned students?"><?= csrf_field() ?><input type="hidden" name="action" value="publish_exam"><input type="hidden" name="quiz_id" value="<?= (int) $exam['id'] ?>"><button class="btn btn-sm btn-primary">Publish exam</button></form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div></div>
</div>
<?php if ($editing || isset($_GET['new'])): ?>
<script>
(() => {
  const studentSearch = document.getElementById('examStudentSearch');
  const studentBranch = document.getElementById('examStudentBranch');
  const studentRows = Array.from(document.querySelectorAll('[data-exam-student-row]'));
  const emptyStudentFilter = document.getElementById('examStudentFilterEmpty');
  const filterStudents = () => {
    const searchTerm = studentSearch?.value.trim().toLocaleLowerCase() || '';
    const branch = studentBranch?.value || '';
    let visibleRows = 0;
    studentRows.forEach((row) => {
      const visible = row.dataset.studentSearch.includes(searchTerm)
        && (!branch || row.dataset.studentBranch === branch);
      row.hidden = !visible;
      if (visible) visibleRows++;
    });
    if (emptyStudentFilter) emptyStudentFilter.hidden = visibleRows > 0;
  };
  studentSearch?.addEventListener('input', filterStudents);
  studentBranch?.addEventListener('change', filterStudents);
  document.getElementById('clearStudentFilters')?.addEventListener('click', () => {
    if (studentSearch) studentSearch.value = '';
    if (studentBranch) studentBranch.value = '';
    filterStudents();
  });

  const list = document.getElementById('questionList');
  const template = document.getElementById('questionTemplate');
  const form = document.getElementById('examBuilder');
  const validation = document.getElementById('examValidation');
  const value = (selector) => form.querySelector(selector).value.trim();
  let count = 0;
  const typeNames = {
    mcq: 'Multiple Choice',
    fill_blank: 'Fill in the Blanks',
    match: 'Match the Following',
    true_false: 'True or False',
    coding: 'Programming / Coding'
  };
  const setField = (entry, field, name, fieldValue) => {
    const control = entry.querySelector(`[data-field="${field}"]`);
    control.name = name;
    if (fieldValue !== undefined && fieldValue !== null) control.value = String(fieldValue);
    return control;
  };
  const addOption = (entry, index, option = {}, fixed = false) => {
    const container = entry.querySelector('[data-options-container]');
    const row = document.createElement('div');
    row.className = 'input-group mb-2 option-row';
    const text = document.createElement('input');
    text.type = 'text';
    text.className = 'form-control';
    text.maxLength = 500;
    text.placeholder = `Option ${index + 1}`;
    text.required = true;
    text.name = `questions[${entry.dataset.index}][options][${index}][text]`;
    text.value = typeof option === 'string' ? option : (option.text || '');
    const choice = document.createElement('span');
    choice.className = 'input-group-text';
    const radio = document.createElement('input');
    radio.type = 'radio';
    radio.className = 'form-check-input mt-0';
    radio.name = `questions[${entry.dataset.index}][correct]`;
    radio.value = String(index);
    radio.required = index === 0;
    radio.checked = (typeof option === 'object' && option.correct) || Number(entry.dataset.correct || 0) === index;
    choice.appendChild(radio);
    row.append(choice, text);
    if (!fixed) {
      const remove = document.createElement('button');
      remove.type = 'button';
      remove.className = 'btn btn-outline-danger';
      remove.textContent = 'Remove';
      remove.addEventListener('click', () => {
        row.remove();
        renumberOptions(entry);
        updatePreview();
      });
      row.appendChild(remove);
    }
    container.appendChild(row);
  };
  const renumberOptions = (entry) => {
    entry.querySelectorAll('.option-row').forEach((row, index) => {
      row.querySelector('input[type="text"]').name = `questions[${entry.dataset.index}][options][${index}][text]`;
      row.querySelector('input[type="radio"]').value = String(index);
      row.querySelector('input[type="text"]').placeholder = `Option ${index + 1}`;
    });
  };
  const addPair = (entry, index, pair = {}) => {
    const container = entry.querySelector('[data-pairs-container]');
    const row = document.createElement('div');
    row.className = 'row g-2 align-items-center mb-2 pair-row';
    const left = document.createElement('input');
    left.className = 'form-control';
    left.maxLength = 500;
    left.placeholder = 'Column A';
    left.value = pair.left || pair.left_text || '';
    left.name = `questions[${entry.dataset.index}][pairs][${index}][left]`;
    const right = document.createElement('input');
    right.className = 'form-control';
    right.maxLength = 500;
    right.placeholder = 'Column B';
    right.value = pair.right || pair.right_text || '';
    right.name = `questions[${entry.dataset.index}][pairs][${index}][right]`;
    const marks = document.createElement('input');
    marks.type = 'number';
    marks.className = 'form-control';
    marks.min = '1';
    marks.max = '255';
    marks.value = String(pair.marks || 1);
    marks.name = `questions[${entry.dataset.index}][pairs][${index}][marks]`;
    const remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'btn btn-outline-danger';
    remove.textContent = 'Remove';
    remove.addEventListener('click', () => {
      row.remove();
      renumberPairs(entry);
      updatePreview();
    });
    const leftCol = document.createElement('div');
    leftCol.className = 'col-md-4';
    leftCol.appendChild(left);
    const rightCol = document.createElement('div');
    rightCol.className = 'col-md-4';
    rightCol.appendChild(right);
    const marksCol = document.createElement('div');
    marksCol.className = 'col-6 col-md-2';
    marksCol.appendChild(marks);
    const removeCol = document.createElement('div');
    removeCol.className = 'col-6 col-md-2';
    removeCol.appendChild(remove);
    row.append(leftCol, rightCol, marksCol, removeCol);
    container.appendChild(row);
  };
  const renumberPairs = (entry) => {
    entry.querySelectorAll('.pair-row').forEach((row, index) => {
      const controls = row.querySelectorAll('input');
      controls[0].name = `questions[${entry.dataset.index}][pairs][${index}][left]`;
      controls[1].name = `questions[${entry.dataset.index}][pairs][${index}][right]`;
      controls[2].name = `questions[${entry.dataset.index}][pairs][${index}][marks]`;
    });
  };
  const addTestCase = (entry, index, testCase = {}) => {
    const container = entry.querySelector('[data-test-cases-container]');
    const row = document.createElement('div');
    row.className = 'row g-2 align-items-start mb-2 test-case-row';
    const input = document.createElement('textarea');
    input.className = 'form-control';
    input.rows = 2;
    input.placeholder = 'Hidden input';
    input.value = testCase.input || testCase.input_data || '';
    input.name = `questions[${entry.dataset.index}][test_cases][${index}][input]`;
    const expected = document.createElement('textarea');
    expected.className = 'form-control';
    expected.rows = 2;
    expected.placeholder = 'Expected output';
    expected.value = testCase.expected_output || '';
    expected.name = `questions[${entry.dataset.index}][test_cases][${index}][expected_output]`;
    const remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'btn btn-outline-danger';
    remove.textContent = 'Remove';
    remove.addEventListener('click', () => {
      row.remove();
      renumberCases(entry);
      updatePreview();
    });
    const inputCol = document.createElement('div');
    inputCol.className = 'col-md-5';
    inputCol.appendChild(input);
    const outputCol = document.createElement('div');
    outputCol.className = 'col-md-5';
    outputCol.appendChild(expected);
    const removeCol = document.createElement('div');
    removeCol.className = 'col-md-2';
    removeCol.appendChild(remove);
    row.append(inputCol, outputCol, removeCol);
    container.appendChild(row);
  };
  const renumberCases = (entry) => {
    entry.querySelectorAll('.test-case-row').forEach((row, index) => {
      const controls = row.querySelectorAll('textarea');
      controls[0].name = `questions[${entry.dataset.index}][test_cases][${index}][input]`;
      controls[1].name = `questions[${entry.dataset.index}][test_cases][${index}][expected_output]`;
    });
  };
  const configureQuestion = (entry, initial = null) => {
    const type = entry.querySelector('[data-field="type"]').value;
    entry.querySelector('[data-section="options"]').classList.toggle('d-none', !['mcq', 'fill_blank', 'true_false'].includes(type));
    entry.querySelector('[data-section="matching"]').classList.toggle('d-none', type !== 'match');
    entry.querySelector('[data-section="coding"]').classList.toggle('d-none', type !== 'coding');
    const marks = entry.querySelector('[data-field="marks"]');
    marks.readOnly = type === 'match';
    if (type !== 'match') marks.value = String(initial?.marks || marks.value || 1);
    if (type === 'match' && initial?.pairs?.length) {
      entry.querySelector('[data-pairs-container]').replaceChildren();
      initial.pairs.forEach((pair, index) => addPair(entry, index, pair));
    }
  };
  const updatePreview = () => {
    const entries = Array.from(list.querySelectorAll('.question-entry'));
    const previewQuestions = document.querySelector('[data-preview-questions]');
    const title = value('[name="title"]');
    const subject = value('[name="subject"]');
    const description = value('[name="description"]');
    const duration = value('[name="duration"]') || '0';
    const passing = value('[name="passing_percent"]') || '0';
    const start = value('[name="scheduled_start"]');
    const end = value('[name="scheduled_end"]');
    const students = form.querySelectorAll('[name="students[]"]:checked').length;
    let totalMarks = 0;

    document.querySelector('[data-preview-title]').textContent = title || 'Examination title';
    document.querySelector('[data-preview-subject]').textContent = subject || 'Subject not set';
    document.querySelector('[data-preview-description]').textContent = description || 'No description provided.';
    document.querySelector('[data-preview-duration]').textContent = duration;
    document.querySelector('[data-preview-passing]').textContent = `${passing}%`;
    document.querySelector('[data-preview-students]').textContent = `${students} student${students === 1 ? '' : 's'} selected`;
    document.querySelector('[data-preview-schedule]').textContent = start && end
      ? `${new Date(start).toLocaleString()} – ${new Date(end).toLocaleString()}`
      : 'Schedule not set';
    previewQuestions.replaceChildren();

    entries.forEach((entry, index) => {
      const questionText = entry.querySelector('[data-field="text"]').value.trim();
      const type = entry.querySelector('[data-field="type"]').value;
      const pairRows = Array.from(entry.querySelectorAll('.pair-row'));
      const marks = type === 'match'
        ? pairRows.reduce((total, row) => total + (Number(row.querySelectorAll('input')[2].value) || 0), 0)
        : (Number(entry.querySelector('[data-field="marks"]').value) || 0);
      totalMarks += marks;
      const item = document.createElement('li');
      item.className = 'mb-2';
      const prompt = document.createElement('div');
      prompt.textContent = `${typeNames[type]}: ${questionText || `Question ${index + 1}`} (${marks} mark${marks === 1 ? '' : 's'})`;
      item.appendChild(prompt);
      if (['mcq', 'fill_blank', 'true_false'].includes(type)) {
        const optionList = document.createElement('ul');
        entry.querySelectorAll('.option-row').forEach((row, optionIndex) => {
          const optionItem = document.createElement('li');
          const option = row.querySelector('input[type="text"]').value.trim();
          const isCorrect = row.querySelector('input[type="radio"]').checked;
          optionItem.textContent = `${option || 'Option not entered'}${isCorrect ? ' (correct)' : ''}`;
          optionList.appendChild(optionItem);
        });
        item.appendChild(optionList);
      } else if (type === 'match') {
        pairRows.forEach((row) => {
          const pair = row.querySelectorAll('input');
          const pairItem = document.createElement('div');
          pairItem.textContent = `${pair[0].value || 'Column A'} → ${pair[1].value || 'Column B'} (${pair[2].value || 0} marks)`;
          item.appendChild(pairItem);
        });
      } else {
        const language = entry.querySelector('[data-field="programming_language"]').value;
        const languagePreview = document.createElement('div');
        languagePreview.textContent = `Language: ${language}`;
        item.appendChild(languagePreview);
      }
      previewQuestions.appendChild(item);
    });

    document.querySelector('[data-preview-total]').textContent = String(totalMarks);
    if (entries.length === 0) {
      const empty = document.createElement('li');
      empty.className = 'text-muted';
      empty.textContent = 'Add a question to preview it here.';
      previewQuestions.appendChild(empty);
    }
  };
  const addQuestion = (initial = null) => {
    const fragment = template.content.cloneNode(true);
    const entry = fragment.querySelector('.question-entry');
    const questionIndex = count++;
    entry.dataset.index = String(questionIndex);
    setField(entry, 'type', `questions[${questionIndex}][type]`, initial?.type || 'mcq');
    setField(entry, 'text', `questions[${questionIndex}][text]`, initial?.text || '');
    setField(entry, 'marks', `questions[${questionIndex}][marks]`, initial?.marks || 1);
    for (const field of ['programming_language', 'sample_input', 'expected_output', 'coding_constraints']) {
      setField(entry, field, `questions[${questionIndex}][${field}]`, initial?.[field] || '');
    }
    entry.dataset.correct = String(initial?.correct ?? 0);
    const initialOptions = Array.isArray(initial?.options) ? initial.options : [];
    let defaults = initialOptions;
    if (!defaults.length && entry.querySelector('[data-field="type"]').value === 'mcq') defaults = ['', '', '', ''];
    if (!defaults.length && entry.querySelector('[data-field="type"]').value === 'true_false') defaults = ['True', 'False'];
    entry.querySelector('[data-options-container]').replaceChildren();
    defaults.forEach((option, index) => {
      const fixed = entry.querySelector('[data-field="type"]').value !== 'fill_blank';
      addOption(entry, index, option, fixed);
    });
    (initial?.pairs || []).forEach((pair, index) => addPair(entry, index, pair));
    (initial?.test_cases || []).forEach((testCase, index) => addTestCase(entry, index, testCase));
    if (initial?.type === 'true_false') {
      entry.querySelector('[data-options-container]').replaceChildren();
      ['True', 'False'].forEach((option, index) => addOption(entry, index, {
        text: option, correct: Number(initial.correct ?? 0) === index
      }, true));
    }
    configureQuestion(entry, initial);
    entry.querySelector('[data-field="type"]').addEventListener('change', () => {
      const type = entry.querySelector('[data-field="type"]').value;
      entry.querySelector('[data-options-container]').replaceChildren();
      if (type === 'mcq') ['', '', '', ''].forEach((option, index) => addOption(entry, index, option, true));
      if (type === 'fill_blank') ['', '', '', ''].forEach((option, index) => addOption(entry, index, option));
      if (type === 'true_false') ['True', 'False'].forEach((option, index) => addOption(entry, index, {
        text: option, correct: index === 0
      }, true));
      entry.querySelector('[data-pairs-container]').replaceChildren();
      if (type === 'match') {
        addPair(entry, 0);
        addPair(entry, 1);
      }
      entry.querySelector('[data-test-cases-container]').replaceChildren();
      configureQuestion(entry);
      updatePreview();
    });
    entry.querySelector('[data-add-option]').addEventListener('click', () => {
      const optionCount = entry.querySelectorAll('.option-row').length;
      if (optionCount < 12) addOption(entry, optionCount);
      updatePreview();
    });
    entry.querySelector('[data-add-pair]').addEventListener('click', () => {
      const pairCount = entry.querySelectorAll('.pair-row').length;
      if (pairCount < 50) addPair(entry, pairCount);
      updatePreview();
    });
    entry.querySelector('[data-add-test-case]').addEventListener('click', () => {
      const caseCount = entry.querySelectorAll('.test-case-row').length;
      if (caseCount < 50) addTestCase(entry, caseCount);
    });
    entry.querySelector('.remove-question').addEventListener('click', () => {
      entry.remove();
      updatePreview();
    });
    entry.addEventListener('input', updatePreview);
    entry.addEventListener('change', updatePreview);
    list.appendChild(fragment);
    updatePreview();
    return entry;
  };
  const serializeQuestion = (entry) => {
    const type = entry.querySelector('[data-field="type"]').value;
    const options = Array.from(entry.querySelectorAll('.option-row')).map((row, index) => ({
      text: row.querySelector('input[type="text"]').value.trim(),
      correct: row.querySelector('input[type="radio"]').checked
    }));
    return {
      type,
      text: entry.querySelector('[data-field="text"]').value.trim(),
      marks: Number(entry.querySelector('[data-field="marks"]').value) || 1,
      options,
      correct: Math.max(0, options.findIndex((option) => option.correct)),
      pairs: Array.from(entry.querySelectorAll('.pair-row')).map((row) => {
        const fields = row.querySelectorAll('input');
        return {left: fields[0].value.trim(), right: fields[1].value.trim(), marks: Number(fields[2].value) || 1};
      }),
      test_cases: Array.from(entry.querySelectorAll('.test-case-row')).map((row) => {
        const fields = row.querySelectorAll('textarea');
        return {input: fields[0].value, expected_output: fields[1].value};
      }),
      programming_language: entry.querySelector('[data-field="programming_language"]').value,
      sample_input: entry.querySelector('[data-field="sample_input"]').value,
      expected_output: entry.querySelector('[data-field="expected_output"]').value,
      coding_constraints: entry.querySelector('[data-field="coding_constraints"]').value
    };
  };
  document.getElementById('questionBankSelect').innerHTML = '<option value="">Loading question bank…</option>';
  fetch('question-bank.php?api=list')
    .then((response) => response.json())
    .then((data) => {
      const selector = document.getElementById('questionBankSelect');
      selector.replaceChildren(new Option('Choose a saved question', ''));
      (data.questions || []).forEach((item) => {
        const option = new Option(`${item.category} · ${item.question_type.replaceAll('_', ' ')} · ${item.question_text}`, item.id);
        selector.add(option);
      });
      if (!data.questions?.length) selector.replaceChildren(new Option('No saved questions yet', ''));
    })
    .catch(() => {
      document.getElementById('questionBankSelect').replaceChildren(new Option('Question bank unavailable', ''));
    });
  document.getElementById('importBankQuestion').addEventListener('click', async () => {
    const id = document.getElementById('questionBankSelect').value;
    if (!id) return;
    try {
      const response = await fetch(`question-bank.php?api=get&id=${encodeURIComponent(id)}`);
      const data = await response.json();
      if (!response.ok) throw new Error(data.error || 'Unable to load saved question.');
      addQuestion(data.question);
    } catch (error) {
      validation.textContent = error.message || 'Unable to load saved question.';
      validation.classList.remove('d-none');
    }
  });
  document.getElementById('addQuestion').addEventListener('click', () => addQuestion());
  list.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-save-bank]');
    if (!button) return;
    const entry = button.closest('.question-entry');
    const question = serializeQuestion(entry);
    if (question.text.length < 3) {
      validation.textContent = 'Enter a question before saving it to the bank.';
      validation.classList.remove('d-none');
      return;
    }
    const category = value('[name="subject"]') || window.prompt('Question category', 'General');
    if (category === null || !category.trim()) return;
    const payload = new URLSearchParams({
      csrf_token: form.querySelector('[name="csrf_token"]').value,
      category: category.trim(),
      question_data: JSON.stringify(question)
    });
    button.disabled = true;
    try {
      const response = await fetch('question-bank.php?api=save', {method: 'POST', body: payload});
      const data = await response.json();
      if (!response.ok) throw new Error(data.error || 'Unable to save this question.');
      validation.textContent = 'Question saved to your bank.';
      validation.className = 'alert alert-success mt-3';
      validation.classList.remove('d-none');
      const selector = document.getElementById('questionBankSelect');
      selector.add(new Option(`${category.trim()} · ${question.type.replaceAll('_', ' ')} · ${question.text}`, data.id));
      selector.value = String(data.id);
    } catch (error) {
      validation.textContent = error.message || 'Unable to save this question.';
      validation.className = 'alert alert-danger mt-3';
      validation.classList.remove('d-none');
    } finally {
      button.disabled = false;
    }
  });
  const initialQuestions = <?= json_encode(array_values(array_map(
      static fn(array $question): array => [
          'type' => is_scalar($question['type'] ?? null) ? (string) $question['type'] : 'mcq',
          'text' => is_scalar($question['text'] ?? null) ? (string) $question['text'] : '',
          'marks' => is_scalar($question['marks'] ?? null) ? (int) $question['marks'] : 1,
          'options' => is_array($question['options'] ?? null) ? array_values($question['options']) : [],
          'correct' => is_scalar($question['correct'] ?? null) ? (int) $question['correct'] : 0,
          'pairs' => is_array($question['pairs'] ?? null) ? array_values($question['pairs']) : [],
          'test_cases' => is_array($question['test_cases'] ?? null) ? array_values($question['test_cases']) : [],
          'programming_language' => is_scalar($question['programming_language'] ?? null) ? (string) $question['programming_language'] : 'PHP',
          'sample_input' => is_scalar($question['sample_input'] ?? null) ? (string) $question['sample_input'] : '',
          'expected_output' => is_scalar($question['expected_output'] ?? null) ? (string) $question['expected_output'] : '',
          'coding_constraints' => is_scalar($question['coding_constraints'] ?? null) ? (string) $question['coding_constraints'] : '',
      ],
      $formQuestions
  )), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  initialQuestions.forEach(addQuestion);
  if (initialQuestions.length === 0) addQuestion();

  form.addEventListener('input', updatePreview);
  form.addEventListener('change', updatePreview);
  form.addEventListener('submit', (event) => {
    const errors = [];
    const intent = event.submitter ? event.submitter.value : 'draft';
    const entries = Array.from(list.querySelectorAll('.question-entry'));
    const duration = Number(value('[name="duration"]'));
    const passing = Number(value('[name="passing_percent"]'));
    const selectedStudents = form.querySelectorAll('[name="students[]"]:checked').length;
    if (value('[name="title"]').length < 3) errors.push('Enter an examination title with at least 3 characters.');
    if (!Number.isInteger(duration) || duration < 1 || duration > 300) errors.push('Duration must be between 1 and 300 minutes.');
    if (!Number.isInteger(passing) || passing < 0 || passing > 100) errors.push('Passing percentage must be between 0 and 100.');
    if (selectedStudents < 1) errors.push('Select at least one student for this examination.');
    if (entries.length < 1 || entries.length > 100) errors.push('Add between 1 and 100 questions.');
    entries.forEach((entry, index) => {
      const questionText = entry.querySelector('[data-field="text"]').value.trim();
      const type = entry.querySelector('[data-field="type"]').value;
      let marks = Number(entry.querySelector('[data-field="marks"]').value);
      if (questionText.length < 3) errors.push(`Question ${index + 1} needs at least 3 characters of question text.`);
      if (type === 'mcq' || type === 'fill_blank' || type === 'true_false') {
        const options = Array.from(entry.querySelectorAll('.option-row input[type="text"]')).map((option) => option.value.trim());
        const min = type === 'fill_blank' ? 2 : (type === 'true_false' ? 2 : 4);
        const max = type === 'fill_blank' ? 12 : (type === 'true_false' ? 2 : 4);
        if (options.length < min || options.length > max || options.some((option) => !option)
          || new Set(options.map((option) => option.toLowerCase())).size !== options.length
          || !entry.querySelector('.option-row input[type="radio"]:checked')
          || !Number.isInteger(marks) || marks < 1 || marks > 255) {
          errors.push(`Question ${index + 1} has invalid options, correct answer, or marks.`);
        }
        if (type === 'fill_blank' && !/_{2,}/.test(questionText)) errors.push(`Question ${index + 1} must include a blank marker (____).`);
      } else if (type === 'match') {
        const pairs = Array.from(entry.querySelectorAll('.pair-row'));
        const lefts = pairs.map((row) => row.querySelectorAll('input')[0].value.trim());
        const rights = pairs.map((row) => row.querySelectorAll('input')[1].value.trim());
        marks = pairs.reduce((sum, row) => sum + (Number(row.querySelectorAll('input')[2].value) || 0), 0);
        entry.querySelector('[data-field="marks"]').value = String(marks);
        if (pairs.length < 2 || pairs.length > 50 || lefts.some((item) => !item) || rights.some((item) => !item)
          || new Set(lefts).size !== pairs.length || new Set(rights).size !== pairs.length
          || pairs.some((row) => Number(row.querySelectorAll('input')[2].value) < 1) || marks > 255) {
          errors.push(`Question ${index + 1} needs 2–50 unique, complete matching pairs with marks.`);
        }
        entry.querySelector('[data-field="marks"]').value = String(marks);
      } else if (!Number.isInteger(marks) || marks < 1 || marks > 255) {
        errors.push(`Question ${index + 1} must have between 1 and 255 marks.`);
      }
      if (type === 'coding') {
        const sampleInput = entry.querySelector('[data-field="sample_input"]').value.trim();
        const expected = entry.querySelector('[data-field="expected_output"]').value.trim();
        if ((sampleInput === '') !== (expected === '')) errors.push(`Question ${index + 1} must provide both sample input and expected output.`);
        const incompleteCase = Array.from(entry.querySelectorAll('.test-case-row')).some((row) => {
          const fields = row.querySelectorAll('textarea');
          return (fields[0].value.trim() === '') !== (fields[1].value.trim() === '');
        });
        if (incompleteCase) errors.push(`Question ${index + 1} has an incomplete hidden test case.`);
      }
    });
    const start = value('[name="scheduled_start"]');
    const end = value('[name="scheduled_end"]');
    if ((start && !end) || (!start && end)) errors.push('Enter both schedule values or leave both blank for a draft.');
    if (intent === 'request') {
      const startTime = start ? new Date(start).getTime() : NaN;
      const endTime = end ? new Date(end).getTime() : NaN;
      if (!Number.isFinite(startTime) || !Number.isFinite(endTime)) {
        errors.push('Set the examination date, start time, and end time before requesting HOD approval.');
      } else {
        if (startTime <= Date.now()) errors.push('Examinations cannot be scheduled in the past.');
        if (endTime - startTime < duration * 60000) errors.push('The scheduled window must be at least as long as the exam duration.');
      }
    }
    if (errors.length) {
      event.preventDefault();
      validation.textContent = errors.join(' ');
      validation.classList.remove('d-none');
      validation.scrollIntoView({ behavior: 'smooth', block: 'center' });
    } else {
      validation.classList.add('d-none');
    }
  });
})();
</script>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
