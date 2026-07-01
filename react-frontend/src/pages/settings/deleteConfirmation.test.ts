// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect } from 'vitest';
import { isDeleteConfirmed, CANONICAL_DELETE_KEYWORD } from './deleteConfirmation';

describe('isDeleteConfirmed — GDPR delete-account confirmation gate', () => {
  it('accepts the canonical keyword', () => {
    expect(isDeleteConfirmed('DELETE')).toBe(true);
    expect(isDeleteConfirmed('DELETE', 'DELETE')).toBe(true);
  });

  it('is case-insensitive so "delete" still unlocks deletion', () => {
    expect(isDeleteConfirmed('delete')).toBe(true);
    expect(isDeleteConfirmed('Delete')).toBe(true);
    expect(isDeleteConfirmed('DeLeTe')).toBe(true);
  });

  it('trims surrounding whitespace (trailing space must not block a legit user)', () => {
    expect(isDeleteConfirmed('  DELETE  ')).toBe(true);
    expect(isDeleteConfirmed('DELETE\n')).toBe(true);
    expect(isDeleteConfirmed('\tdelete ')).toBe(true);
  });

  it('accepts the localized keyword the UI instructed the user to type', () => {
    // Regression: Spanish/French users were shown "ELIMINAR"/"SUPPRIMER" but the
    // code demanded literal "DELETE", so following the on-screen instruction
    // could never unlock deletion.
    expect(isDeleteConfirmed('ELIMINAR', 'ELIMINAR')).toBe(true);
    expect(isDeleteConfirmed('eliminar', 'ELIMINAR')).toBe(true);
    expect(isDeleteConfirmed('SUPPRIMER', 'SUPPRIMER')).toBe(true);
  });

  it('still accepts canonical "DELETE" even under a localized UI (permanent fallback)', () => {
    expect(isDeleteConfirmed('DELETE', 'ELIMINAR')).toBe(true);
  });

  it('rejects an autofilled account email so browser autofill cannot trigger deletion', () => {
    // Regression: the confirmation field sits next to a current-password input,
    // so browsers autofill it with the account email. That must never satisfy
    // the gate — autofill suppression on the input lets the user type the word.
    expect(isDeleteConfirmed('exchangemembers@gmail.com')).toBe(false);
    expect(isDeleteConfirmed('exchangemembers@gmail.com', 'DELETE')).toBe(false);
  });

  it('rejects empty / whitespace-only / partial input', () => {
    expect(isDeleteConfirmed('')).toBe(false);
    expect(isDeleteConfirmed('   ')).toBe(false);
    expect(isDeleteConfirmed('DEL')).toBe(false);
    expect(isDeleteConfirmed('DELETE ME')).toBe(false);
  });

  it('ignores an empty localized keyword rather than matching empty input', () => {
    expect(isDeleteConfirmed('', '')).toBe(false);
    expect(isDeleteConfirmed('DELETE', '')).toBe(true);
  });

  it('exposes the canonical keyword constant', () => {
    expect(CANONICAL_DELETE_KEYWORD).toBe('DELETE');
  });
});
