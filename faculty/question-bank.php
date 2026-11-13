<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$faculty = require_faculty();
$pdo = db();
$facultyId = (int) $faculty['id'];
$errors = [];
$api = (string) ($_GET['api'] ?? '');

if ($api !== '') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        if ($api === 'list' && $_SERVER['REQUEST_METHOD'] === 'GET') {
            $query = $pdo->prepare(
                'SELECT id, category, question_type, JSON_UNQUOTE(JSON_EXTRACT(question_data, "$.text")) AS question_text
                 FROM faculty_question_bank WHERE faculty_id = ? ORDER BY category, updated_at DESC'
            );
            $query->execute([$facultyId]);
            echo json_encode(['questions' => $query->fetchAll()], JSON_THROW_ON_ERROR);
            exit;
        }
        if ($api === 'get' && $_SERVER['REQUEST_METHOD'] === 'GET') {
            $query = $pdo->prepare(
                'SELECT id, category, question_type, question_data FROM faculty_question_bank
                 WHERE id = ? AND faculty_id = ?'
            );
            $query->execute([(int) ($_GET['id'] ?? 0), $facultyId]);
            $question = $query->fetch();
            if (!$question) {
                http_response_code(404);
                throw new InvalidArgumentException('Question not found in your bank.');
            }
            echo json_encode([
                'id' => (int) $question['id'],
                'category' => $question['category'],
                'question_type' => $question['question_type'],
                'question' => json_decode($question['question_data'], true, 512, JSON_THROW_ON_ERROR),
            ], JSON_THROW_ON_ERROR);
            exit;
        }
        if ($api !== 'save' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(400);
            throw new InvalidArgumentException('Unknown question-bank request.');
        }
        verify_csrf();
        $category = clean_string($_POST['category'] ?? 'General', 100) ?: 'General';
        $question = normalize_bank_question($_POST['question_data'] ?? '');
        $save = $pdo->prepare(
            'INSERT INTO faculty_question_bank (faculty_id, category, question_type, question_data)
             VALUES (?, ?, ?, ?)'
        );
        $save->execute([
            $facultyId,
            $category,
            $question['type'],
            json_encode($question, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ]);
        echo json_encode(['saved' => true, 'id' => (int) $pdo->lastInsertId()], JSON_THROW_ON_ERROR);
        exit;
    } catch (InvalidArgumentException $error) {
        if (http_response_code() < 400) {
            http_response_code(400);
        }
        echo json_encode(['error' => $error->getMessage()]);
        exit;
    } catch (Throwable $error) {
        error_log('Faculty question bank request failed: ' . $error->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'The question-bank request could not be completed.']);
        exit;
    }
}

function normalize_bank_question(string $json): array
{
    try {
        $question = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        throw new InvalidArgumentException('Question data must be valid JSON.');
    }
    if (!is_array($question)) {
        throw new InvalidArgumentException('Question data must be a JSON object.');
    }
    $types = ['mcq', 'fill_blank', 'match', 'true_false', 'coding'];
    $type = (string) ($question['type'] ?? '');
    $text = trim((string) ($question['text'] ?? ''));
    $marks = filter_var($question['marks'] ?? null, FILTER_VALIDATE_INT);
    if (!in_array($type, $types, true) || mb_strlen($text) < 3 || mb_strlen($text) > 1000
        || $marks === false || $marks < 1 || $marks > 255) {
        throw new InvalidArgumentException('Include a supported question type, 3–1,000 character prompt, and marks from 1 to 255.');
    }
    $normalized = ['type' => $type, 'text' => $text, 'marks' => (int) $marks];

    if (in_array($type, ['mcq', 'fill_blank', 'true_false'], true)) {
        $options = is_array($question['options'] ?? null) ? array_values($question['options']) : [];
        $min = $type === 'fill_blank' ? 2 : 2;
        $max = $type === 'fill_blank' ? 12 : ($type === 'mcq' ? 4 : 2);
        $correct = filter_var($question['correct'] ?? null, FILTER_VALIDATE_INT);
        $optionTexts = [];
        foreach ($options as $option) {
            $optionText = trim((string) (is_array($option) ? ($option['text'] ?? '') : $option));
            if ($optionText === '' || mb_strlen($optionText) > 500) {
                throw new InvalidArgumentException('Every answer option must contain 1–500 characters.');
            }
            $optionTexts[] = $optionText;
        }
        if (count($options) < $min || count($options) > $max || count(array_unique(array_map('mb_strtolower', $optionTexts))) !== count($optionTexts)
            || $correct === false || !array_key_exists($correct, $options)) {
            throw new InvalidArgumentException('Question options must be complete, unique, and include a valid correct-answer index.');
        }
        if ($type === 'fill_blank' && !preg_match('/_{2,}/', $text)) {
            throw new InvalidArgumentException('Fill-in-the-blank questions must include a ____ marker.');
        }
        if ($type === 'true_false' && array_map('mb_strtolower', $optionTexts) !== ['true', 'false']) {
            throw new InvalidArgumentException('True/False choices must be True and False, in that order.');
        }
        $normalized['options'] = array_map(
            static fn(string $optionText, int $index): array => ['text' => $optionText, 'correct' => $index === $correct],
            $optionTexts,
            array_keys($optionTexts)
        );
        $normalized['correct'] = (int) $correct;
    } elseif ($type === 'match') {
        $pairs = is_array($question['pairs'] ?? null) ? array_values($question['pairs']) : [];
        if (count($pairs) < 2 || count($pairs) > 50) {
            throw new InvalidArgumentException('Matching questions need between 2 and 50 pairs.');
        }
        $lefts = [];
        $rights = [];
        $totalMarks = 0;
        foreach ($pairs as $pair) {
            if (!is_array($pair)) {
                throw new InvalidArgumentException('Each matching pair must include both columns and marks.');
            }
            $left = trim((string) ($pair['left'] ?? $pair['left_text'] ?? ''));
            $right = trim((string) ($pair['right'] ?? $pair['right_text'] ?? ''));
            $pairMarks = filter_var($pair['marks'] ?? 1, FILTER_VALIDATE_INT);
            if ($left === '' || $right === '' || mb_strlen($left) > 500 || mb_strlen($right) > 500
                || $pairMarks === false || $pairMarks < 1 || $pairMarks > 255) {
                throw new InvalidArgumentException('Each matching pair needs valid Column A, Column B, and marks.');
            }
            $lefts[] = mb_strtolower($left);
            $rights[] = mb_strtolower($right);
            $totalMarks += $pairMarks;
            $normalized['pairs'][] = ['left' => $left, 'right' => $right, 'marks' => (int) $pairMarks];
        }
        if (count(array_unique($lefts)) !== count($lefts) || count(array_unique($rights)) !== count($rights) || $totalMarks > 255) {
            throw new InvalidArgumentException('Matching pairs must be unique and total no more than 255 marks.');
        }
        $normalized['marks'] = $totalMarks;
    } else {
        $languages = ['PHP', 'Python', 'C', 'C++', 'Java', 'JavaScript'];
        $language = (string) ($question['programming_language'] ?? '');
        if (!in_array($language, $languages, true)) {
            throw new InvalidArgumentException('Choose a supported programming language.');
        }
        $normalized['programming_language'] = $language;
        $normalized['sample_input'] = (string) ($question['sample_input'] ?? '');
        $normalized['expected_output'] = (string) ($question['expected_output'] ?? '');
        $normalized['coding_constraints'] = (string) ($question['coding_constraints'] ?? '');
        if (mb_strlen($normalized['sample_input']) > 5000 || mb_strlen($normalized['expected_output']) > 5000
            || mb_strlen($normalized['coding_constraints']) > 5000
            || (($normalized['sample_input'] === '') !== ($normalized['expected_output'] === ''))) {
            throw new InvalidArgumentException('Programming sample input/output must both be provided or both left blank; each field is limited to 5,000 characters.');
        }
        $cases = is_array($question['test_cases'] ?? null) ? array_values($question['test_cases']) : [];
        if (count($cases) > 50) {
            throw new InvalidArgumentException('A programming question can include up to 50 hidden test cases.');
        }
        foreach ($cases as $case) {
            if (!is_array($case)) {
                throw new InvalidArgumentException('Every hidden test case needs input and expected output.');
            }
            $input = (string) ($case['input'] ?? $case['input_data'] ?? '');
            $expected = (string) ($case['expected_output'] ?? '');
            if (($input === '') !== ($expected === '') || mb_strlen($input) > 10000 || mb_strlen($expected) > 10000) {
                throw new InvalidArgumentException('Hidden test case input and expected output must both be present and limited to 10,000 characters.');
            }
            if ($input !== '') {
                $normalized['test_cases'][] = ['input' => $input, 'expected_output' => $expected];
            }
        }
    }
    return $normalized;
}

$editId = (int) ($_GET['edit'] ?? 0);
$editing = null;
if ($editId > 0) {
    $query = $pdo->prepare('SELECT * FROM faculty_question_bank WHERE id = ? AND faculty_id = ?');
    $query->execute([$editId, $facultyId]);
    $editing = $query->fetch() ?: null;
    if (!$editing) {
        http_response_code(404);
        die('Question not found in your bank.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'delete') {
            $delete = $pdo->prepare('DELETE FROM faculty_question_bank WHERE id = ? AND faculty_id = ?');
            $delete->execute([(int) ($_POST['id'] ?? 0), $facultyId]);
            if ($delete->rowCount() !== 1) {
                throw new InvalidArgumentException('This question is no longer available to delete.');
            }
            flash('success', 'Question removed from your bank.');
        } elseif ($action === 'save_record') {
            $id = (int) ($_POST['id'] ?? 0);
            $category = clean_string($_POST['category'] ?? '', 100);
            if ($category === '') {
                throw new InvalidArgumentException('Enter a category name.');
            }
            $question = normalize_bank_question((string) ($_POST['question_data'] ?? ''));
            if ($id > 0) {
                $save = $pdo->prepare(
                    'UPDATE faculty_question_bank SET category = ?, question_type = ?, question_data = ?
                     WHERE id = ? AND faculty_id = ?'
                );
                $save->execute([
                    $category,
                    $question['type'],
                    json_encode($question, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    $id,
                    $facultyId,
                ]);
                if ($save->rowCount() === 0) {
                    $owner = $pdo->prepare('SELECT 1 FROM faculty_question_bank WHERE id = ? AND faculty_id = ?');
                    $owner->execute([$id, $facultyId]);
                    if (!$owner->fetchColumn()) {
                        throw new InvalidArgumentException('This question is no longer available to edit.');
                    }
                }
                flash('success', 'Question-bank entry updated.');
            } else {
                $save = $pdo->prepare(
                    'INSERT INTO faculty_question_bank (faculty_id, category, question_type, question_data)
                     VALUES (?, ?, ?, ?)'
                );
                $save->execute([
                    $facultyId,
                    $category,
                    $question['type'],
                    json_encode($question, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                ]);
                flash('success', 'Question added to your bank.');
            }
            redirect('faculty/question-bank.php');
        } else {
            throw new InvalidArgumentException('Unknown question-bank action.');
        }
        redirect('faculty/question-bank.php');
    } catch (InvalidArgumentException $error) {
        $errors[] = $error->getMessage();
    } catch (Throwable $error) {
        error_log('Faculty question-bank operation failed: ' . $error->getMessage());
        $errors[] = 'The question could not be saved. Please try again.';
    }
}

$questionsQuery = $pdo->prepare(
    'SELECT id, category, question_type, question_data, updated_at
     FROM faculty_question_bank WHERE faculty_id = ? ORDER BY category, updated_at DESC'
);
$questionsQuery->execute([$facultyId]);
$bankQuestions = $questionsQuery->fetchAll();

$pageTitle = 'Faculty · Question Bank';
$activeNav = 'faculty-question-bank';
$facultyShellEnabled = true;
include __DIR__ . '/../includes/header.php';
$facultyActive = 'question-bank';
include __DIR__ . '/_nav.php';
?>
<div class="container py-4">
  <div class="mb-4"><h2 class="mb-1">Question bank</h2><p class="text-muted mb-0">Save and reuse all five supported question types in your examination builder.</p></div>
  <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
  <?php if ($message = flash('success')): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
  <div class="alert alert-info"><i class="bi bi-info-circle"></i> To save a fully configured question, use <strong>Save to Question Bank</strong> in My Exams. Entries can also be created or edited below as structured JSON.</div>
  <div class="row g-4">
    <div class="col-lg-5"><div class="card p-4">
      <h5><?= $editing ? 'Edit bank question' : 'Add a bank question' ?></h5>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="save_record">
        <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>"><?php endif; ?>
        <div class="mb-3"><label class="form-label">Category</label><input class="form-control" name="category" maxlength="100" required value="<?= e($editing['category'] ?? '') ?>" placeholder="e.g. Data structures"></div>
        <div class="mb-3"><label class="form-label">Question data (JSON)</label><textarea class="form-control font-monospace" name="question_data" rows="12" required><?= e($editing['question_data'] ?? '{"type":"mcq","text":"Enter the question prompt","marks":1,"options":[{"text":"Option A","correct":true},{"text":"Option B","correct":false},{"text":"Option C","correct":false},{"text":"Option D","correct":false}],"correct":0}') ?></textarea>
          <div class="form-text">The data format follows the existing examination question editor. Use <code>options</code>, <code>pairs</code>, or <code>test_cases</code> as appropriate for the type.</div></div>
        <button class="btn btn-primary" type="submit"><?= $editing ? 'Save question' : 'Add question' ?></button>
        <?php if ($editing): ?><a class="btn btn-outline-secondary" href="<?= e(base_url('faculty/question-bank.php')) ?>">Cancel</a><?php endif; ?>
      </form>
    </div></div>
    <div class="col-lg-7"><div class="card p-3"><div class="table-responsive"><table class="table align-middle mb-0">
      <thead><tr><th>Question</th><th>Category</th><th>Type</th><th>Updated</th><th></th></tr></thead>
      <tbody><?php foreach ($bankQuestions as $bankQuestion):
        $data = json_decode($bankQuestion['question_data'], true);
      ?><tr>
        <td><?= e($data['text'] ?? 'Question') ?><div class="small text-muted"><?= (int) ($data['marks'] ?? 0) ?> marks</div></td>
        <td><?= e($bankQuestion['category']) ?></td>
        <td><?= e(str_replace('_', ' ', ucfirst($bankQuestion['question_type']))) ?></td>
        <td class="small text-muted"><?= e(date('d M Y', strtotime($bankQuestion['updated_at']))) ?></td>
        <td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('faculty/question-bank.php?edit=' . (int) $bankQuestion['id'])) ?>">Edit</a>
          <form method="post" class="d-inline" data-confirm="Remove this question from your bank?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $bankQuestion['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form></td>
      </tr><?php endforeach; ?></tbody>
    </table></div><?php if (!$bankQuestions): ?><div class="text-center text-muted py-5">Your question bank is empty. Save a question from My Exams to get started.</div><?php endif; ?></div></div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
