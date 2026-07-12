// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { RegistrationQuestion } from '@/lib/api/eventRegistration';
import {
  validateRegistrationAnswers,
  visibleRegistrationAnswers,
  visibleRegistrationQuestions,
} from './eventRegistrationFormRules';

function question(stableKey: string, patch: Partial<RegistrationQuestion> = {}): RegistrationQuestion {
  return {
    id: Math.max(1, stableKey.length),
    stable_key: stableKey,
    position: 1,
    question_type: 'short_text',
    prompt: stableKey,
    is_required: false,
    data_classification: 'internal',
    purpose: 'test',
    retention_days: 30,
    ...patch,
  };
}

describe('eventRegistrationFormRules', () => {
  it('matches the complete server visibility contract without hidden-answer cascades', () => {
    const questions = [
      question('audience'),
      question('tags', { question_type: 'multiple_choice' }),
      question('equals', { visibility_rules: { match: 'all', conditions: [{ question_key: 'audience', operator: 'equals', value: 'member' }] } }),
      question('not_equals', { visibility_rules: { match: 'all', conditions: [{ question_key: 'audience', operator: 'not_equals', value: 'guest' }] } }),
      question('contains', { visibility_rules: { match: 'all', conditions: [{ question_key: 'tags', operator: 'contains', value: 'access' }] } }),
      question('not_contains', { visibility_rules: { match: 'all', conditions: [{ question_key: 'tags', operator: 'not_contains', value: 'blocked' }] } }),
      question('in', { visibility_rules: { match: 'all', conditions: [{ question_key: 'audience', operator: 'in', value: ['member', 'staff'] }] } }),
      question('not_in', { visibility_rules: { match: 'all', conditions: [{ question_key: 'audience', operator: 'not_in', value: ['guest'] }] } }),
      question('answered', { visibility_rules: { match: 'all', conditions: [{ question_key: 'audience', operator: 'is_answered' }] } }),
      question('unanswered', { visibility_rules: { match: 'all', conditions: [{ question_key: 'equals', operator: 'is_not_answered' }] } }),
      question('cascade', { visibility_rules: { match: 'all', conditions: [{ question_key: 'unanswered', operator: 'is_answered' }] } }),
    ];
    const answers = { audience: 'member', tags: ['access'], equals: 'visible', unanswered: 'stale', cascade: 'stale' };

    expect(visibleRegistrationQuestions(questions, answers).map(({ stable_key }) => stable_key)).toEqual([
      'audience', 'tags', 'equals', 'not_equals', 'contains', 'not_contains', 'in', 'not_in', 'answered',
    ]);
    expect(visibleRegistrationAnswers(questions, answers)).toEqual({
      audience: 'member', tags: ['access'], equals: 'visible',
    });
  });

  it('enforces required and min/max rules only for visible questions', () => {
    const questions = [
      question('name', { is_required: true, validation_rules: { min_length: 3, max_length: 5 } }),
      question('choices', {
        question_type: 'multiple_choice',
        validation_rules: { min_selections: 2, max_selections: 3 },
      }),
      question('hidden_required', {
        is_required: true,
        visibility_rules: { match: 'all', conditions: [{ question_key: 'name', operator: 'equals', value: 'show' }] },
      }),
    ];

    expect(validateRegistrationAnswers(questions, { name: '  ', choices: ['one'] }, true)).toEqual({
      name: { code: 'required' },
      choices: { code: 'min_selections', limit: 2 },
    });
    expect(validateRegistrationAnswers(questions, { name: 'ab', choices: ['one', 'two', 'three', 'four'] }, false)).toEqual({
      name: { code: 'min_length', limit: 3 },
      choices: { code: 'max_selections', limit: 3 },
    });
    expect(validateRegistrationAnswers(questions, { name: 'abcdef', choices: ['one', 'two'] }, false)).toEqual({
      name: { code: 'max_length', limit: 5 },
    });
  });
});
