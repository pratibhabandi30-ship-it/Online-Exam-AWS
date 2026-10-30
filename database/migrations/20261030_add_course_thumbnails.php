<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';

function course_thumbnail_seed_data(): array
{
    return [
        1 => ['PHP Fundamentals', 'php-fundamentals', '1555949963-aa79dcee981c'],
        2 => ['JavaScript Essentials', 'javascript-essentials', '1461749280684-dccba630e2f6'],
        3 => ['Database & SQL', 'database-sql', '1563986768609-322da13575f3'],
        4 => ['Computer Networks', 'computer-networks', '1520869562399-e772f042f422'],
        5 => ['Data Structures Quick Quiz', 'data-structures', '1522202176988-66273c2fd55f'],
        10 => ['Python Programming', 'python-programming', '1517694712202-14dd9538aa97'],
        11 => ['Java Programming', 'java-programming', '1515879218367-8466d910aaa4'],
        12 => ['C Programming', 'c-programming', '1504639725590-34d0984388bd'],
        13 => ['C++ Programming', 'cpp-programming', '1555066931-4365d14bab8c'],
        14 => ['Object-Oriented Programming', 'object-oriented-programming', '1531403009284-440f080d1e12'],
        15 => ['Operating Systems', 'operating-systems', '1561736778-92e52a7769ef'],
        16 => ['Software Engineering', 'software-engineering', '1552664730-d307ca884978'],
        17 => ['Computer Organization and Architecture', 'computer-organization-architecture', '1518770660439-4636190af475'],
        18 => ['Theory of Computation', 'theory-of-computation', '1519389950473-47ba0277781c'],
        19 => ['Compiler Design', 'compiler-design', '1563206767-5b18f218e8de'],
        20 => ['HTML & CSS', 'html-css', '1522542550221-31fd19575a2d'],
        21 => ['React.js Development', 'react-development', '1516321318423-f06f85e504b3'],
        22 => ['PHP & MySQL Web Development', 'php-mysql-web-development', '1498050108023-c5249f4df085'],
        23 => ['Full Stack Web Development', 'full-stack-web-development', '1531482615713-2afd69097998'],
        24 => ['Artificial Intelligence', 'artificial-intelligence', '1581092160562-40aa08e78837'],
        25 => ['Machine Learning', 'machine-learning', '1551288049-bebda4e38f71'],
        26 => ['Data Science Fundamentals', 'data-science-fundamentals', '1460925895917-afdab827c52f'],
        27 => ['Deep Learning', 'deep-learning', '1581091226825-a6a2a5aee158'],
        28 => ['Cloud Computing and AWS', 'cloud-computing-aws', '1558494949-ef010cbdcc31'],
        29 => ['Cyber Security Fundamentals', 'cyber-security-fundamentals', '1562813733-b31f71025d54'],
    ];
}

function add_course_thumbnails(PDO $pdo): int
{
    $mapping = course_thumbnail_seed_data();
    $imageDirectory = __DIR__ . '/../../assets/images/courses';
    $updateRecords = [];
    $selectCourseById = $pdo->prepare('SELECT id, title FROM quizzes WHERE id = ?');
    $selectCourseByTitle = $pdo->prepare('SELECT id FROM quizzes WHERE title = ? ORDER BY id');

    foreach ($mapping as $id => [$title, $slug, $photoId]) {
        $selectCourseById->execute([$id]);
        $course = $selectCourseById->fetch();
        if ($course && $course['title'] === $title) {
            $courseId = (int) $course['id'];
        } else {
            $selectCourseByTitle->execute([$title]);
            $matches = $selectCourseByTitle->fetchAll(PDO::FETCH_COLUMN);
            if (count($matches) !== 1) {
                throw new RuntimeException(sprintf(
                    'Expected one existing course named "%s" at ID %d or by exact title; found %d exact-title matches.',
                    $title,
                    $id,
                    count($matches)
                ));
            }
            $courseId = (int) $matches[0];
        }

        $imagePath = $imageDirectory . DIRECTORY_SEPARATOR . $slug . '.jpg';
        $imageInfo = is_file($imagePath) ? getimagesize($imagePath) : false;
        if (
            !is_array($imageInfo)
            || ($imageInfo['mime'] ?? '') !== 'image/jpeg'
            || $imageInfo[0] !== 1200
            || $imageInfo[1] !== 675
        ) {
            throw new RuntimeException(sprintf(
                'Missing or invalid 1200x675 course photograph for course ID %d: %s',
                $id,
                $imagePath
            ));
        }

        $updateRecords[] = [
            'id' => $courseId,
            'image_path' => 'assets/images/courses/' . $slug . '.jpg',
        ];
    }

    $columnExists = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $columnExists->execute(['quizzes', 'image_path']);
    if ((int) $columnExists->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE quizzes ADD COLUMN image_path VARCHAR(255) DEFAULT NULL AFTER `intro`');
    }

    $pdo->beginTransaction();
    try {
        $updateCourse = $pdo->prepare('UPDATE quizzes SET image_path = ? WHERE id = ?');
        foreach ($updateRecords as $record) {
            $updateCourse->execute([$record['image_path'], $record['id']]);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }

    return count($updateRecords);
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        $count = add_course_thumbnails(db());
        echo sprintf("Local course photographs assigned to %d existing course IDs. No course records were created or deleted.\n", $count);
    } catch (Throwable $error) {
        fwrite(STDERR, 'Course thumbnail migration failed: ' . $error->getMessage() . PHP_EOL);
        exit(1);
    }
}
