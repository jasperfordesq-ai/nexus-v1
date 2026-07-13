// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { initAll } from 'govuk-frontend';
import './app.scss';

initAll();

document.querySelectorAll<HTMLElement>('[data-alpha-print-page]').forEach((button) => {
  button.addEventListener('click', () => window.print());
});

document.querySelectorAll<HTMLFormElement>('[data-alpha-auto-submit]').forEach((form) => {
  form.querySelectorAll<HTMLSelectElement>('select').forEach((select) => {
    select.addEventListener('change', () => form.requestSubmit());
  });
});

type RegistrationCondition = { question_key?: string; operator?: string; value?: unknown };
type RegistrationVisibilityRules = { match?: 'all' | 'any'; conditions?: RegistrationCondition[] };
type RegistrationValidationRules = {
  min_length?: number;
  max_length?: number;
  min_selections?: number;
  max_selections?: number;
};

function decodeRegistrationRules<T>(encoded: string | undefined): T | null {
  if (!encoded) return null;
  try {
    const bytes = Uint8Array.from(window.atob(encoded), (character) => character.charCodeAt(0));
    return JSON.parse(new TextDecoder().decode(bytes)) as T;
  } catch {
    return null;
  }
}

function registrationAnswerPresent(value: unknown): boolean {
  return value !== undefined && value !== null && value !== ''
    && (!Array.isArray(value) || value.length > 0);
}

function registrationStrictEqual(actual: unknown, expected: unknown): boolean {
  if (!Array.isArray(actual) || !Array.isArray(expected)) return actual === expected;
  return actual.length === expected.length
    && actual.every((value, index) => registrationStrictEqual(value, expected[index]));
}

function registrationConditionMatches(
  condition: RegistrationCondition,
  answers: Record<string, unknown>,
): boolean {
  const actual = condition.question_key ? answers[condition.question_key] : undefined;
  const answered = registrationAnswerPresent(actual);
  if (condition.operator === 'is_answered') return answered;
  if (condition.operator === 'is_not_answered') return !answered;
  if (!answered) return false;
  if (condition.operator === 'equals') return registrationStrictEqual(actual, condition.value);
  if (condition.operator === 'not_equals') return !registrationStrictEqual(actual, condition.value);
  if (condition.operator === 'contains') {
    return Array.isArray(actual)
      ? actual.some((value) => registrationStrictEqual(value, condition.value))
      : typeof actual === 'string' && typeof condition.value === 'string' && actual.includes(condition.value);
  }
  if (condition.operator === 'not_contains') {
    return Array.isArray(actual)
      ? !actual.some((value) => registrationStrictEqual(value, condition.value))
      : typeof actual !== 'string' || typeof condition.value !== 'string' || !actual.includes(condition.value);
  }
  if (condition.operator === 'in') {
    return Array.isArray(condition.value)
      && condition.value.some((value) => registrationStrictEqual(actual, value));
  }
  if (condition.operator === 'not_in') {
    return Array.isArray(condition.value)
      && !condition.value.some((value) => registrationStrictEqual(actual, value));
  }
  return false;
}

function registrationQuestionVisible(
  group: HTMLElement,
  earlierVisibleAnswers: Record<string, unknown>,
): boolean {
  const rules = decodeRegistrationRules<RegistrationVisibilityRules>(group.dataset.alphaRegistrationVisibility);
  if (!rules?.conditions?.length) return true;
  const matches = rules.conditions.map((condition) => registrationConditionMatches(condition, earlierVisibleAnswers));
  return rules.match === 'any' ? matches.some(Boolean) : matches.every(Boolean);
}

function registrationGroupAnswer(group: HTMLElement): { present: boolean; value: unknown } {
  const type = group.dataset.alphaRegistrationType;
  const inputs = Array.from(group.querySelectorAll<HTMLInputElement | HTMLTextAreaElement>('input, textarea'));
  if (type === 'multiple_choice') {
    const values = inputs.filter((input) => input instanceof HTMLInputElement && input.checked)
      .map((input) => input.value);
    return { present: values.length > 0, value: values };
  }
  if (type === 'single_choice') {
    const selected = inputs.find((input) => input instanceof HTMLInputElement && input.checked);
    return { present: selected !== undefined, value: selected?.value ?? '' };
  }
  if (type === 'consent' || type === 'waiver') {
    const selected = inputs.some((input) => input instanceof HTMLInputElement && input.checked);
    return { present: selected, value: selected };
  }
  const value = inputs[0]?.value ?? '';
  return { present: true, value };
}

function registrationQuestionError(
  group: HTMLElement,
  present: boolean,
  value: unknown,
): string {
  const required = group.dataset.alphaRegistrationRequired === '1';
  const type = group.dataset.alphaRegistrationType;
  const complete = type === 'consent' || type === 'waiver'
    ? value === true
    : typeof value === 'string'
      ? value.trim() !== ''
      : Array.isArray(value) && value.length > 0;
  if (required && (!present || !complete)) return group.dataset.alphaRegistrationRequiredMessage ?? '';
  if (!present) return '';

  const rules = decodeRegistrationRules<RegistrationValidationRules>(group.dataset.alphaRegistrationValidation);
  if (typeof value === 'string') {
    const length = Array.from(value).length;
    if (typeof rules?.min_length === 'number' && length < rules.min_length) {
      return group.dataset.alphaRegistrationMinLengthMessage ?? '';
    }
    if (typeof rules?.max_length === 'number' && length > rules.max_length) {
      return group.dataset.alphaRegistrationMaxLengthMessage ?? '';
    }
  }
  const selections = type === 'multiple_choice'
    ? (Array.isArray(value) ? value.length : 0)
    : type === 'single_choice' && value !== '' ? 1 : 0;
  if (typeof rules?.min_selections === 'number' && selections < rules.min_selections) {
    return group.dataset.alphaRegistrationMinSelectionsMessage ?? '';
  }
  if (typeof rules?.max_selections === 'number' && selections > rules.max_selections) {
    return group.dataset.alphaRegistrationMaxSelectionsMessage ?? '';
  }
  return '';
}

document.querySelectorAll<HTMLFormElement>('[data-alpha-registration-form]').forEach((form) => {
  const groups = Array.from(form.querySelectorAll<HTMLElement>('[data-alpha-registration-question]'));
  const update = () => {
    const visibleAnswers: Record<string, unknown> = {};
    groups.forEach((group) => {
      const visible = registrationQuestionVisible(group, visibleAnswers);
      const controls = Array.from(group.querySelectorAll<HTMLInputElement | HTMLTextAreaElement>('input, textarea'));
      group.hidden = !visible;
      controls.forEach((control) => { control.disabled = !visible; });
      const validationTarget = controls[0];
      if (!visible) {
        validationTarget?.setCustomValidity('');
        return;
      }
      const answer = registrationGroupAnswer(group);
      if (answer.present) {
        const key = group.dataset.alphaRegistrationQuestion;
        if (key) visibleAnswers[key] = answer.value;
      }
      validationTarget?.setCustomValidity(registrationQuestionError(group, answer.present, answer.value));
    });
  };

  form.addEventListener('input', update);
  form.addEventListener('change', update);
  update();
});

// Escape user-supplied text before inserting it into an autocomplete suggestion
// (the suggestion template is rendered as innerHTML by accessible-autocomplete).
function escapeHtml(value: string): string {
  return value.replace(/[&<>"']/g, (c) => (
    { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c] as string
  ));
}

// Progressive enhancement: turn the wallet recipient search into an accessible
// autocomplete (the official GOV.UK component). The no-JS path — a plain search
// input that reloads with server-rendered results — stays the primary, fully
// working experience; we only remove it once the enhancement has initialised.
const recipientContainer = document.querySelector<HTMLElement>('[data-alpha-recipient-autocomplete]');
if (recipientContainer) {
  const source = recipientContainer.dataset.source ?? '';
  const target = recipientContainer.dataset.target ?? '';
  const noJsInput = document.getElementById('recipient_q');
  const noJsSubmit = document.querySelector<HTMLElement>('[data-alpha-recipient-submit]');

  if (source && target) {
    // Dynamic import keeps the autocomplete bundle off every other page.
    Promise.all([
      import('accessible-autocomplete'),
      import('accessible-autocomplete/dist/accessible-autocomplete.min.css'),
    ])
      .then(([mod]) => {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const accessibleAutocomplete: any = (mod as any).default ?? mod;

        accessibleAutocomplete({
          element: recipientContainer,
          id: 'recipient_q',
          minLength: 2,
          displayMenu: 'overlay',
          confirmOnBlur: false,
          // eslint-disable-next-line @typescript-eslint/no-explicit-any
          source: async (query: string, populateResults: (results: any[]) => void) => {
            try {
              const res = await fetch(`${source}?q=${encodeURIComponent(query)}`, {
                headers: { Accept: 'application/json' },
              });
              if (!res.ok) {
                populateResults([]);
                return;
              }
              const data = await res.json();
              populateResults(Array.isArray(data?.results) ? data.results : []);
            } catch {
              populateResults([]);
            }
          },
          templates: {
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            inputValue: (r: any) => (r && r.name ? String(r.name) : ''),
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            suggestion: (r: any) => {
              if (!r) return '';
              const name = escapeHtml(String(r.name ?? ''));
              const meta = [r.location, r.since].filter(Boolean).map((x: string) => escapeHtml(String(x))).join(' · ');
              return meta ? `${name} <span class="nexus-alpha-ac-meta">— ${meta}</span>` : name;
            },
          },
          // eslint-disable-next-line @typescript-eslint/no-explicit-any
          onConfirm: (r: any) => {
            if (r && r.id) {
              window.location.href = `${target}?recipient_id=${encodeURIComponent(String(r.id))}#transfer`;
            }
          },
        });

        // The enhancement is live — drop the no-JS input + button so there is no
        // duplicate id and the autocomplete is the single recipient control.
        noJsInput?.remove();
        noJsSubmit?.remove();
        recipientContainer.querySelector('input')?.setAttribute('aria-describedby', 'recipient-q-hint');
      })
      .catch(() => {
        // Enhancement failed to load — leave the no-JS search exactly as it was.
      });
  }
}
