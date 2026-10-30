<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../database/migrations/20261030_add_course_thumbnails.php';

$pdo = db();
$courseMappings = course_thumbnail_seed_data();
$expectedCourseCount = count($courseMappings);
$actualCourseCount = (int) $pdo->query('SELECT COUNT(*) FROM quizzes')->fetchColumn();
if ($actualCourseCount !== 25) {
    throw new RuntimeException(sprintf('Expected to preserve 25 courses; found %d.', $actualCourseCount));
}

$findCourseById = $pdo->prepare('SELECT id, title, image_path FROM quizzes WHERE id = ?');
$findCourseByTitle = $pdo->prepare('SELECT id, title, image_path FROM quizzes WHERE title = ? ORDER BY id');
$seenPaths = [];
$seenPhotos = [];
foreach ($courseMappings as $expectedId => [$title, $slug]) {
    $findCourseById->execute([$expectedId]);
    $course = $findCourseById->fetch();
    if (!$course || $course['title'] !== $title) {
        $findCourseByTitle->execute([$title]);
        $matchingCourses = $findCourseByTitle->fetchAll();
        if (count($matchingCourses) !== 1) {
            throw new RuntimeException(sprintf('Expected course ID %d or one exact-title match for "%s".', $expectedId, $title));
        }
        $course = $matchingCourses[0];
    }

    $expectedPath = 'assets/images/courses/' . $slug . '.jpg';
    if ($course['image_path'] !== $expectedPath) {
        throw new RuntimeException(sprintf('Unexpected thumbnail mapping for "%s": %s.', $title, (string) $course['image_path']));
    }
    if (isset($seenPaths[$course['image_path']])) {
        throw new RuntimeException('Two courses share the same thumbnail: ' . $course['image_path']);
    }
    $seenPaths[$course['image_path']] = true;

    $assetPath = __DIR__ . '/../' . $course['image_path'];
    $imageInfo = is_file($assetPath) ? getimagesize($assetPath) : false;
    if (
        !is_array($imageInfo)
        || ($imageInfo['mime'] ?? '') !== 'image/jpeg'
        || $imageInfo[0] !== 1200
        || $imageInfo[1] !== 675
    ) {
        throw new RuntimeException('Missing or invalid 1200x675 local photograph for: ' . $title);
    }

    $photoHash = hash_file('sha256', $assetPath);
    if (isset($seenPhotos[$photoHash])) {
        throw new RuntimeException('Two courses use the same photograph: ' . $title);
    }
    $seenPhotos[$photoHash] = true;
}

$defaultImage = __DIR__ . '/../assets/images/courses/course-default.jpg';
$defaultInfo = is_file($defaultImage) ? getimagesize($defaultImage) : false;
if (
    !is_array($defaultInfo)
    || ($defaultInfo['mime'] ?? '') !== 'image/jpeg'
    || $defaultInfo[0] !== 1200
    || $defaultInfo[1] !== 675
) {
    throw new RuntimeException('Missing or invalid default course photograph.');
}

if (count($seenPaths) !== $expectedCourseCount || count($seenPhotos) !== $expectedCourseCount) {
    throw new RuntimeException(sprintf('Expected %d unique course photographs.', $expectedCourseCount));
}

echo sprintf("Course thumbnail checks passed for %d existing course IDs and unique local 1200x675 photographs.\n", $expectedCourseCount);
