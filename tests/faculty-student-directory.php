<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

$pdo = db();
$pdo->beginTransaction();

try {
    $suffix = bin2hex(random_bytes(6));
    $insertUser = $pdo->prepare(
        'INSERT INTO users (name, email, password_hash, role, is_active)
         VALUES (?, ?, ?, ?, 1)'
    );
    $insertUser->execute([
        'Directory Faculty One',
        'directory-faculty-one-' . $suffix . '@oes.test',
        password_hash('test-password', PASSWORD_BCRYPT),
        'faculty',
    ]);
    $facultyId = (int) $pdo->lastInsertId();
    $insertUser->execute([
        'Directory Faculty Two',
        'directory-faculty-two-' . $suffix . '@oes.test',
        password_hash('test-password', PASSWORD_BCRYPT),
        'faculty',
    ]);
    $otherFacultyId = (int) $pdo->lastInsertId();

    $studentIds = [];
    foreach (['One', 'Two', 'Three'] as $index => $suffixName) {
        $insertUser->execute([
            'Directory Student ' . $suffixName,
            'directory-student-' . strtolower($suffixName) . '-' . $suffix . '@oes.test',
            password_hash('test-password', PASSWORD_BCRYPT),
            'student',
        ]);
        $studentId = (int) $pdo->lastInsertId();
        $studentIds[] = $studentId;
        $pdo->prepare(
            'INSERT INTO student_profiles (user_id, registration_no, branch, semester, academic_year)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([$studentId, 'DIR-' . $suffix . '-' . ($index + 1), 'CSE', $index + 1, '2026-27']);
    }

    $pdo->prepare('INSERT INTO faculty_profiles (user_id, max_students) VALUES (?, ?)')
        ->execute([$facultyId, 3]);
    $pdo->prepare('INSERT INTO faculty_profiles (user_id, max_students) VALUES (?, ?)')
        ->execute([$otherFacultyId, 3]);

    $directory = $pdo->prepare(
        "SELECT u.id, sp.registration_no, sp.branch, sp.semester, sp.academic_year
         FROM users u LEFT JOIN student_profiles sp ON sp.user_id = u.id
         WHERE u.role = 'student' AND u.id IN (?, ?, ?) ORDER BY u.id"
    );
    $directory->execute($studentIds);
    if (count($directory->fetchAll(PDO::FETCH_ASSOC)) !== 3) {
        throw new RuntimeException('Student registration profiles are not visible in the central directory.');
    }

} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $error;
}

try {
    $lockStudents = $pdo->prepare(
        'SELECT id FROM users WHERE id IN (?, ?, ?) AND role = ? AND is_active = 1 ORDER BY id FOR UPDATE'
    );
    $sortedIds = $studentIds;
    sort($sortedIds, SORT_NUMERIC);
    $lockStudents->execute([$sortedIds[0], $sortedIds[1], $sortedIds[2], 'student']);
    if (array_map('intval', $lockStudents->fetchAll(PDO::FETCH_COLUMN)) !== $sortedIds) {
        throw new RuntimeException('The faculty could not lock the selected student rows.');
    }

    $insertAssignment = $pdo->prepare(
        'INSERT INTO faculty_student_assignments (faculty_id, student_id, assigned_by)
         VALUES (?, ?, ?)'
    );
    foreach ($studentIds as $studentId) {
        $insertAssignment->execute([$facultyId, $studentId, $facultyId]);
    }

    $assignedLookup = $pdo->prepare(
        'SELECT faculty_id FROM faculty_student_assignments WHERE student_id = ?'
    );
    foreach ($studentIds as $studentId) {
        $assignedLookup->execute([$studentId]);
        if ((int) $assignedLookup->fetchColumn() !== $facultyId) {
            throw new RuntimeException('A selected student was not assigned to the signed-in faculty.');
        }
    }

    // Assigning to second faculty MUST succeed and not overwrite existing assignment:
    $insertAssignment->execute([$otherFacultyId, $studentIds[0], $otherFacultyId]);

    $assignedFacultiesQuery = $pdo->prepare(
        'SELECT faculty_id FROM faculty_student_assignments WHERE student_id = ? ORDER BY faculty_id'
    );
    $assignedFacultiesQuery->execute([$studentIds[0]]);
    $faculties = array_map('intval', $assignedFacultiesQuery->fetchAll(PDO::FETCH_COLUMN));
    $expected = [$facultyId, $otherFacultyId];
    sort($expected);
    if ($faculties !== $expected) {
        throw new RuntimeException('Student is not assigned to both faculty members simultaneously.');
    }

    // Duplicate assignment between the same faculty and same student must be prevented:
    $duplicateBlocked = false;
    try {
        $insertAssignment->execute([$facultyId, $studentIds[0], $facultyId]);
    } catch (PDOException $error) {
        if ($error->getCode() === '23000') {
            $duplicateBlocked = true;
        } else {
            throw $error;
        }
    }
    if (!$duplicateBlocked) {
        throw new RuntimeException('Duplicate assignment between same faculty and student was not prevented.');
    }

    // Removing facultyId's assignment must not remove otherFacultyId's assignment:
    $pdo->prepare('DELETE FROM faculty_student_assignments WHERE faculty_id = ? AND student_id = ?')
        ->execute([$facultyId, $studentIds[0]]);

    $assignedFacultiesQuery->execute([$studentIds[0]]);
    $remainingFaculties = array_map('intval', $assignedFacultiesQuery->fetchAll(PDO::FETCH_COLUMN));
    if ($remainingFaculties !== [$otherFacultyId]) {
        throw new RuntimeException('Unassigning one faculty did not preserve the other faculty assignment.');
    }

    echo "Faculty directory, student profiles, multiple faculty assignments, duplicate prevention, and independent unassignment checks passed.\n";
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}
