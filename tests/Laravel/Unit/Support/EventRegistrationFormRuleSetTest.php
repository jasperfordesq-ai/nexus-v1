<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Support;

use App\Enums\EventRegistrationQuestionType;
use App\Exceptions\EventRegistrationFoundationException;
use App\Support\Events\EventRegistrationFormRuleSet;
use PHPUnit\Framework\TestCase;

final class EventRegistrationFormRuleSetTest extends TestCase
{
    private EventRegistrationFormRuleSet $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rules = new EventRegistrationFormRuleSet();
    }

    public function test_visibility_matches_all_supported_operators(): void
    {
        $answers = ['audience' => 'member', 'tags' => ['access'], 'empty' => ''];
        $conditions = [
            ['question_key' => 'audience', 'operator' => 'equals', 'value' => 'member'],
            ['question_key' => 'audience', 'operator' => 'not_equals', 'value' => 'guest'],
            ['question_key' => 'tags', 'operator' => 'contains', 'value' => 'access'],
            ['question_key' => 'tags', 'operator' => 'not_contains', 'value' => 'blocked'],
            ['question_key' => 'audience', 'operator' => 'in', 'value' => ['member', 'staff']],
            ['question_key' => 'audience', 'operator' => 'not_in', 'value' => ['guest']],
            ['question_key' => 'audience', 'operator' => 'is_answered'],
            ['question_key' => 'empty', 'operator' => 'is_not_answered'],
        ];

        self::assertTrue($this->rules->isVisible(['match' => 'all', 'conditions' => $conditions], $answers));
        self::assertTrue($this->rules->isVisible([
            'match' => 'any',
            'conditions' => [
                ['question_key' => 'audience', 'operator' => 'equals', 'value' => 'guest'],
                ['question_key' => 'tags', 'operator' => 'contains', 'value' => 'access'],
            ],
        ], $answers));
    }

    public function test_text_validation_rules_cannot_exceed_the_submission_hard_limits(): void
    {
        self::assertSame(
            ['max_length' => 500, 'min_length' => 1],
            $this->rules->normalizeValidation(
                EventRegistrationQuestionType::ShortText,
                ['min_length' => 1, 'max_length' => 500],
            ),
        );
        self::assertSame(
            ['max_length' => 10000],
            $this->rules->normalizeValidation(
                EventRegistrationQuestionType::LongText,
                ['max_length' => 10000],
            ),
        );

        $this->expectException(EventRegistrationFoundationException::class);
        $this->expectExceptionMessage('event_registration_validation_rules_invalid');
        $this->rules->normalizeValidation(
            EventRegistrationQuestionType::ShortText,
            ['max_length' => 501],
        );
    }

    public function test_value_validation_enforces_length_and_selection_ranges(): void
    {
        $this->rules->assertValue(
            EventRegistrationQuestionType::ShortText,
            'valid',
            ['min_length' => 3, 'max_length' => 5],
        );
        $this->rules->assertValue(
            EventRegistrationQuestionType::MultipleChoice,
            ['one', 'two'],
            ['min_selections' => 2, 'max_selections' => 3],
        );

        $this->expectException(EventRegistrationFoundationException::class);
        $this->expectExceptionMessage('event_registration_answer_selection_maximum');
        $this->rules->assertValue(
            EventRegistrationQuestionType::MultipleChoice,
            ['one', 'two', 'three', 'four'],
            ['min_selections' => 2, 'max_selections' => 3],
        );
    }
}
