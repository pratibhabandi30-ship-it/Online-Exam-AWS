<?php
/**
 * Misc helpers used across the app.
 */
declare(strict_types=1);

function validate_email(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

function clean_string(?string $s, int $maxLen = 255): string
{
    $s = trim((string) $s);
    $s = preg_replace('/\s+/', ' ', $s) ?? '';
    if (mb_strlen($s) > $maxLen) {
        $s = mb_substr($s, 0, $maxLen);
    }
    return $s;
}

function format_duration_seconds(int $seconds): string
{
    if ($seconds < 60) return $seconds . 's';
    $m = intdiv($seconds, 60);
    $s = $seconds % 60;
    return $m . 'm ' . $s . 's';
}

function grade_letter(float $percent): string
{
    return match (true) {
        $percent >= 90 => 'A+',
        $percent >= 80 => 'A',
        $percent >= 70 => 'B',
        $percent >= 60 => 'C',
        $percent >= 50 => 'D',
        default        => 'F',
    };
}

function paginate_offset(int $page, int $perPage): int
{
    $page = max(1, $page);
    return ($page - 1) * $perPage;
}
