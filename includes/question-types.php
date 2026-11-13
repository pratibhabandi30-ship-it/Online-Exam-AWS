<?php
declare(strict_types=1);

function matching_column_label(int $index): string
{
    if ($index < 0) {
        throw new InvalidArgumentException('A matching column index cannot be negative.');
    }

    $label = '';
    do {
        $label = chr(65 + ($index % 26)) . $label;
        $index = intdiv($index, 26) - 1;
    } while ($index >= 0);

    return $label;
}

function parse_matching_answer(string $text, array $matchingPairs, array $columnBOrder): array
{
    $pairIds = array_map(static fn(array $pair): int => (int) $pair['id'], $matchingPairs);
    $orderIds = array_map('intval', $columnBOrder);
    $sortedPairIds = $pairIds;
    $sortedOrderIds = $orderIds;
    sort($sortedPairIds);
    sort($sortedOrderIds);

    if (count($pairIds) < 2 || count($pairIds) > 50 || $sortedPairIds !== $sortedOrderIds) {
        throw new InvalidArgumentException('The matching question order is invalid. Reload the exam and try again.');
    }

    $text = trim($text);
    $selectedPairs = [];
    if ($text !== '') {
        $letterToPairId = [];
        foreach ($orderIds as $index => $pairId) {
            $letterToPairId[matching_column_label($index)] = $pairId;
        }

        foreach (preg_split('/\s*,\s*/', $text) ?: [] as $entry) {
            if (!preg_match('/^(\d+)\s*-\s*([A-Z]+)$/i', $entry, $matches)) {
                throw new InvalidArgumentException('Enter matching answers as number-letter pairs, for example: 1-B, 2-A.');
            }
            $leftIndex = (int) $matches[1] - 1;
            $rightLabel = strtoupper($matches[2]);
            if (!isset($pairIds[$leftIndex]) || !isset($letterToPairId[$rightLabel])) {
                throw new InvalidArgumentException('A matching answer uses a number or letter that is not in this question.');
            }
            $leftId = $pairIds[$leftIndex];
            $rightId = $letterToPairId[$rightLabel];
            if (isset($selectedPairs[$leftId]) || in_array($rightId, $selectedPairs, true)) {
                throw new InvalidArgumentException('Use each Column A number and Column B letter only once.');
            }
            $selectedPairs[$leftId] = $rightId;
        }
    }

    return [
        'pairs' => $selectedPairs,
        'display_order' => $orderIds,
        'text' => $text,
    ];
}

function grade_exam_question(
    array $question,
    array $answer,
    array $correctOptions = [],
    array $matchingPairs = []
): array {
    $type = (string) ($question['question_type'] ?? 'mcq');
    $marks = (int) ($question['marks'] ?? 0);

    if (in_array($type, ['mcq', 'fill_blank', 'true_false'], true)) {
        $rawSelectedId = $answer['option_id'] ?? null;
        $selectedId = is_scalar($rawSelectedId) ? filter_var((string) $rawSelectedId, FILTER_VALIDATE_INT) : false;
        $validOptions = array_map('intval', array_keys($correctOptions));
        if ($selectedId === false || $selectedId < 1 || !in_array($selectedId, $validOptions, true)) {
            return ['selected_option_id' => null, 'answer_text' => null, 'is_correct' => false, 'awarded_marks' => 0, 'unanswered' => true];
        }
        $isCorrect = (bool) $correctOptions[$selectedId];
        return [
            'selected_option_id' => $selectedId,
            'answer_text' => null,
            'is_correct' => $isCorrect,
            'awarded_marks' => $isCorrect ? $marks : 0,
            'unanswered' => false,
        ];
    }

    if ($type === 'match') {
        $selectedPairs = is_array($answer['pairs'] ?? null) ? $answer['pairs'] : [];
        $validPairIds = array_map(static fn(array $pair): int => (int) $pair['id'], $matchingPairs);
        $earned = 0;
        $answered = false;
        foreach ($matchingPairs as $pair) {
            $pairId = (int) $pair['id'];
            $rawSelectedId = $selectedPairs[$pairId] ?? null;
            $selectedId = is_scalar($rawSelectedId) ? filter_var((string) $rawSelectedId, FILTER_VALIDATE_INT) : false;
            if ($selectedId !== false && in_array((int) $selectedId, $validPairIds, true)) {
                $answered = true;
                if ((int) $selectedId === $pairId) {
                    $earned += (int) $pair['marks'];
                }
            }
        }
        return [
            'selected_option_id' => null,
            'answer_text' => json_encode([
                'pairs' => $selectedPairs,
                'display_order' => array_map('intval', $answer['display_order'] ?? $validPairIds),
                'text' => (string) ($answer['text'] ?? ''),
            ], JSON_THROW_ON_ERROR),
            'is_correct' => $earned >= $marks,
            'awarded_marks' => $earned,
            'unanswered' => !$answered,
        ];
    }

    if ($type === 'coding') {
        $code = is_string($answer['code'] ?? null) ? $answer['code'] : '';
        return [
            'selected_option_id' => null,
            'answer_text' => $code === '' ? null : $code,
            'is_correct' => false,
            'awarded_marks' => null,
            'unanswered' => trim($code) === '',
        ];
    }

    throw new InvalidArgumentException('Unsupported examination question type.');
}
