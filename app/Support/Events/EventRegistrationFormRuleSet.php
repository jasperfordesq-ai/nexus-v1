<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Support\Events;

use App\Enums\EventRegistrationQuestionType;
use App\Exceptions\EventRegistrationFoundationException;

/** Strict, non-executable validation and conditional-visibility rule contract. */
final class EventRegistrationFormRuleSet
{
    private const VISIBILITY_OPERATORS = [
        'equals',
        'not_equals',
        'contains',
        'not_contains',
        'in',
        'not_in',
        'is_answered',
        'is_not_answered',
    ];

    /** @return array<string,mixed>|null */
    public function normalizeValidation(EventRegistrationQuestionType $type, mixed $rules): ?array
    {
        if ($rules === null || $rules === []) {
            return null;
        }
        if (! is_array($rules) || array_is_list($rules)) {
            throw new EventRegistrationFoundationException('event_registration_validation_rules_invalid');
        }

        $allowed = match ($type) {
            EventRegistrationQuestionType::ShortText,
            EventRegistrationQuestionType::LongText,
            EventRegistrationQuestionType::Dietary,
            EventRegistrationQuestionType::Accessibility => ['min_length', 'max_length', 'format'],
            EventRegistrationQuestionType::SingleChoice,
            EventRegistrationQuestionType::MultipleChoice => ['min_selections', 'max_selections'],
            default => [],
        };
        if (array_diff(array_keys($rules), $allowed) !== []) {
            throw new EventRegistrationFoundationException('event_registration_validation_rules_unknown');
        }

        $normalized = [];
        $lengthLimit = $type === EventRegistrationQuestionType::ShortText ? 500 : 10000;
        foreach (['min_length', 'max_length'] as $key) {
            if (array_key_exists($key, $rules)) {
                $normalized[$key] = $this->integer($rules[$key], 0, $lengthLimit);
            }
        }
        if (isset($normalized['min_length'], $normalized['max_length'])
            && $normalized['min_length'] > $normalized['max_length']) {
            throw new EventRegistrationFoundationException('event_registration_validation_rules_range_invalid');
        }
        if (array_key_exists('format', $rules)) {
            $format = is_string($rules['format']) ? trim($rules['format']) : '';
            if (! in_array($format, ['email', 'phone', 'url'], true)) {
                throw new EventRegistrationFoundationException('event_registration_validation_format_invalid');
            }
            $normalized['format'] = $format;
        }
        foreach (['min_selections', 'max_selections'] as $key) {
            if (array_key_exists($key, $rules)) {
                $normalized[$key] = $this->integer($rules[$key], 0, 100);
            }
        }
        if ($type === EventRegistrationQuestionType::SingleChoice) {
            foreach (['min_selections', 'max_selections'] as $key) {
                if (isset($normalized[$key]) && ! in_array($normalized[$key], [0, 1], true)) {
                    throw new EventRegistrationFoundationException('event_registration_validation_selection_invalid');
                }
            }
        }
        if (isset($normalized['min_selections'], $normalized['max_selections'])
            && $normalized['min_selections'] > $normalized['max_selections']) {
            throw new EventRegistrationFoundationException('event_registration_validation_rules_range_invalid');
        }

        ksort($normalized);

        return $normalized === [] ? null : $normalized;
    }

    /**
     * @param array<string,EventRegistrationQuestionType> $earlierQuestions
     * @return array{match:string,conditions:list<array{question_key:string,operator:string,value?:mixed}>}|null
     */
    public function normalizeVisibility(mixed $rules, array $earlierQuestions): ?array
    {
        if ($rules === null || $rules === []) {
            return null;
        }
        if (! is_array($rules) || array_is_list($rules)
            || array_diff(array_keys($rules), ['match', 'conditions']) !== []) {
            throw new EventRegistrationFoundationException('event_registration_visibility_rules_invalid');
        }
        $match = is_string($rules['match'] ?? null) ? trim($rules['match']) : '';
        $conditions = $rules['conditions'] ?? null;
        if (! in_array($match, ['all', 'any'], true)
            || ! is_array($conditions) || ! array_is_list($conditions)
            || $conditions === [] || count($conditions) > 20) {
            throw new EventRegistrationFoundationException('event_registration_visibility_rules_invalid');
        }

        $normalized = [];
        foreach ($conditions as $condition) {
            if (! is_array($condition) || array_is_list($condition)
                || array_diff(array_keys($condition), ['question_key', 'operator', 'value']) !== []) {
                throw new EventRegistrationFoundationException('event_registration_visibility_condition_invalid');
            }
            $key = is_string($condition['question_key'] ?? null)
                ? trim($condition['question_key'])
                : '';
            $operator = is_string($condition['operator'] ?? null)
                ? trim($condition['operator'])
                : '';
            if (! isset($earlierQuestions[$key]) || ! in_array($operator, self::VISIBILITY_OPERATORS, true)) {
                throw new EventRegistrationFoundationException('event_registration_visibility_condition_invalid');
            }
            $needsValue = ! in_array($operator, ['is_answered', 'is_not_answered'], true);
            if ($needsValue !== array_key_exists('value', $condition)) {
                throw new EventRegistrationFoundationException('event_registration_visibility_condition_invalid');
            }
            $normalizedCondition = ['question_key' => $key, 'operator' => $operator];
            if ($needsValue) {
                $normalizedCondition['value'] = $this->conditionValue($condition['value']);
            }
            $normalized[] = $normalizedCondition;
        }

        return ['match' => $match, 'conditions' => $normalized];
    }

    /** @param array<string,mixed>|null $rules @param array<string,mixed> $answers */
    public function isVisible(?array $rules, array $answers): bool
    {
        if ($rules === null) {
            return true;
        }
        $matches = array_map(
            fn (array $condition): bool => $this->conditionMatches($condition, $answers),
            $rules['conditions'],
        );

        return $rules['match'] === 'all'
            ? ! in_array(false, $matches, true)
            : in_array(true, $matches, true);
    }

    /** @param array<string,mixed>|null $rules */
    public function assertValue(EventRegistrationQuestionType $type, mixed $value, ?array $rules): void
    {
        if ($rules === null) {
            return;
        }
        if (is_string($value)) {
            $length = mb_strlen($value);
            if (isset($rules['min_length']) && $length < $rules['min_length']) {
                throw new EventRegistrationFoundationException('event_registration_answer_too_short');
            }
            if (isset($rules['max_length']) && $length > $rules['max_length']) {
                throw new EventRegistrationFoundationException('event_registration_answer_too_long');
            }
            if (isset($rules['format']) && ! $this->validFormat($value, $rules['format'])) {
                throw new EventRegistrationFoundationException('event_registration_answer_format_invalid');
            }
        }
        $selections = $type === EventRegistrationQuestionType::MultipleChoice
            ? (is_array($value) ? count($value) : 0)
            : ($type === EventRegistrationQuestionType::SingleChoice && $value !== '' ? 1 : 0);
        if (isset($rules['min_selections']) && $selections < $rules['min_selections']) {
            throw new EventRegistrationFoundationException('event_registration_answer_selection_minimum');
        }
        if (isset($rules['max_selections']) && $selections > $rules['max_selections']) {
            throw new EventRegistrationFoundationException('event_registration_answer_selection_maximum');
        }
    }

    private function integer(mixed $value, int $min, int $max): int
    {
        $parsed = filter_var($value, FILTER_VALIDATE_INT);
        if ($parsed === false || $parsed < $min || $parsed > $max) {
            throw new EventRegistrationFoundationException('event_registration_validation_rules_invalid');
        }

        return (int) $parsed;
    }

    private function conditionValue(mixed $value): mixed
    {
        if (is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }
        if (is_string($value) && mb_strlen($value) <= 1000) {
            return trim($value);
        }
        if (is_array($value) && array_is_list($value) && count($value) <= 100) {
            return array_map(function (mixed $item): string|int|float|bool {
                if (! is_string($item) && ! is_int($item) && ! is_float($item) && ! is_bool($item)) {
                    throw new EventRegistrationFoundationException('event_registration_visibility_value_invalid');
                }

                return is_string($item) ? trim($item) : $item;
            }, $value);
        }

        throw new EventRegistrationFoundationException('event_registration_visibility_value_invalid');
    }

    /** @param array<string,mixed> $condition @param array<string,mixed> $answers */
    private function conditionMatches(array $condition, array $answers): bool
    {
        $answered = array_key_exists($condition['question_key'], $answers)
            && $answers[$condition['question_key']] !== null
            && $answers[$condition['question_key']] !== ''
            && $answers[$condition['question_key']] !== [];
        if ($condition['operator'] === 'is_answered') {
            return $answered;
        }
        if ($condition['operator'] === 'is_not_answered') {
            return ! $answered;
        }
        if (! $answered) {
            return false;
        }

        $actual = $answers[$condition['question_key']];
        $expected = $condition['value'];

        return match ($condition['operator']) {
            'equals' => $actual === $expected,
            'not_equals' => $actual !== $expected,
            'contains' => is_array($actual)
                ? in_array($expected, $actual, true)
                : (is_string($actual) && is_string($expected) && str_contains($actual, $expected)),
            'not_contains' => is_array($actual)
                ? ! in_array($expected, $actual, true)
                : (! is_string($actual) || ! is_string($expected) || ! str_contains($actual, $expected)),
            'in' => is_array($expected) && in_array($actual, $expected, true),
            'not_in' => is_array($expected) && ! in_array($actual, $expected, true),
            default => false,
        };
    }

    private function validFormat(string $value, string $format): bool
    {
        return match ($format) {
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'url' => filter_var($value, FILTER_VALIDATE_URL) !== false,
            'phone' => \App\Core\Validator::isPhone($value),
            default => false,
        };
    }
}
