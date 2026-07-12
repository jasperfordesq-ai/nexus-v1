// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { RegistrationQuestion } from '@/lib/api/eventRegistration';

export type RegistrationAnswerValidationCode =
  | 'required'
  | 'min_length'
  | 'max_length'
  | 'min_selections'
  | 'max_selections';

export interface RegistrationAnswerValidationError {
  code: RegistrationAnswerValidationCode;
  limit?: number;
}
type VisibilityCondition = { question_key?: string; operator?: string; value?: unknown };
type VisibilityRules = { match?: 'all' | 'any'; conditions?: VisibilityCondition[] };

function isAnswered(value: unknown): boolean {
  return value !== undefined && value !== null && value !== ''
    && (!Array.isArray(value) || value.length > 0);
}

function strictEqual(actual: unknown, expected: unknown): boolean {
  if (!Array.isArray(actual) || !Array.isArray(expected)) return actual === expected;
  return actual.length === expected.length
    && actual.every((value, index) => strictEqual(value, expected[index]));
}

function conditionMatches(condition: VisibilityCondition, answers: Record<string, unknown>): boolean {
  const actual = condition.question_key ? answers[condition.question_key] : undefined;
  const answered = isAnswered(actual);
  if (condition.operator === 'is_answered') return answered;
  if (condition.operator === 'is_not_answered') return !answered;
  if (!answered) return false;
  if (condition.operator === 'equals') return strictEqual(actual, condition.value);
  if (condition.operator === 'not_equals') return !strictEqual(actual, condition.value);
  if (condition.operator === 'contains') {
    return Array.isArray(actual)
      ? actual.some((value) => strictEqual(value, condition.value))
      : typeof actual === 'string' && typeof condition.value === 'string' && actual.includes(condition.value);
  }
  if (condition.operator === 'not_contains') {
    return Array.isArray(actual)
      ? !actual.some((value) => strictEqual(value, condition.value))
      : typeof actual !== 'string' || typeof condition.value !== 'string' || !actual.includes(condition.value);
  }
  if (condition.operator === 'in') {
    return Array.isArray(condition.value)
      && condition.value.some((value) => strictEqual(actual, value));
  }
  if (condition.operator === 'not_in') {
    return Array.isArray(condition.value)
      && !condition.value.some((value) => strictEqual(actual, value));
  }
  return false;
}

export function isRegistrationQuestionVisible(
  question: RegistrationQuestion,
  earlierVisibleAnswers: Record<string, unknown>,
): boolean {
  const rules = question.visibility_rules as VisibilityRules | null | undefined;
  if (!rules?.conditions?.length) return true;
  const matches = rules.conditions.map((condition) => conditionMatches(condition, earlierVisibleAnswers));
  return rules.match === 'any' ? matches.some(Boolean) : matches.every(Boolean);
}

export function visibleRegistrationQuestions(
  questions: RegistrationQuestion[],
  answers: Record<string, unknown>,
): RegistrationQuestion[] {
  const visibleAnswers: Record<string, unknown> = {};
  return questions.filter((question) => {
    if (!isRegistrationQuestionVisible(question, visibleAnswers)) return false;
    if (Object.prototype.hasOwnProperty.call(answers, question.stable_key)) {
      visibleAnswers[question.stable_key] = answers[question.stable_key];
    }
    return true;
  });
}

export function visibleRegistrationAnswers(
  questions: RegistrationQuestion[],
  answers: Record<string, unknown>,
): Record<string, unknown> {
  return Object.fromEntries(visibleRegistrationQuestions(questions, answers)
    .filter((question) => Object.prototype.hasOwnProperty.call(answers, question.stable_key))
    .map((question) => [question.stable_key, answers[question.stable_key]]));
}

function ruleLimit(question: RegistrationQuestion, key: string): number | undefined {
  const value = question.validation_rules?.[key];
  return typeof value === 'number' && Number.isInteger(value) && value >= 0 ? value : undefined;
}

function isComplete(question: RegistrationQuestion, value: unknown): boolean {
  if (question.question_type === 'consent' || question.question_type === 'waiver') return value === true;
  if (typeof value === 'string') return value.trim() !== '';
  return Array.isArray(value) ? value.length > 0 : value !== undefined && value !== null;
}

export function validateRegistrationAnswers(
  questions: RegistrationQuestion[],
  answers: Record<string, unknown>,
  requireComplete: boolean,
): Record<string, RegistrationAnswerValidationError> {
  const errors: Record<string, RegistrationAnswerValidationError> = {};
  for (const question of visibleRegistrationQuestions(questions, answers)) {
    const hasAnswer = Object.prototype.hasOwnProperty.call(answers, question.stable_key);
    const value = answers[question.stable_key];
    if (requireComplete && question.is_required && (!hasAnswer || !isComplete(question, value))) {
      errors[question.stable_key] = { code: 'required' };
      continue;
    }
    if (!hasAnswer) continue;
    if (typeof value === 'string') {
      const minLength = ruleLimit(question, 'min_length');
      const maxLength = ruleLimit(question, 'max_length');
      if (minLength !== undefined && value.length < minLength) {
        errors[question.stable_key] = { code: 'min_length', limit: minLength };
        continue;
      }
      if (maxLength !== undefined && value.length > maxLength) {
        errors[question.stable_key] = { code: 'max_length', limit: maxLength };
        continue;
      }
    }
    const selections = question.question_type === 'multiple_choice'
      ? (Array.isArray(value) ? value.length : 0)
      : question.question_type === 'single_choice' && value !== '' ? 1 : 0;
    const minSelections = ruleLimit(question, 'min_selections');
    const maxSelections = ruleLimit(question, 'max_selections');
    if (minSelections !== undefined && selections < minSelections) {
      errors[question.stable_key] = { code: 'min_selections', limit: minSelections };
      continue;
    }
    if (maxSelections !== undefined && selections > maxSelections) {
      errors[question.stable_key] = { code: 'max_selections', limit: maxSelections };
    }
  }
  return errors;
}
