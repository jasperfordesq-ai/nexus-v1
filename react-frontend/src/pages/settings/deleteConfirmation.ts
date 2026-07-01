// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * GDPR Article 17 account-deletion confirmation gate.
 *
 * The delete-account modal requires the user to type a confirmation keyword
 * before the destructive action unlocks. This must be robust for TWO reasons
 * that have each broken account deletion in production:
 *
 *  1. Browsers / password managers autofill the confirmation field with the
 *     account email (it sits next to a `current-password` input). An autofilled
 *     value must NEVER satisfy the gate, but the user must still be able to
 *     type the keyword — see the `autoComplete="off"` suppression on the input.
 *
 *  2. The keyword shown to the user is localized. Historically the code compared
 *     against a hardcoded English "DELETE" while some locales (es → "ELIMINAR",
 *     fr → "SUPPRIMER") localized the placeholder, so users following the
 *     on-screen instruction could never unlock deletion. To make that class of
 *     regression impossible, we accept EITHER the localized keyword the UI told
 *     the user to type OR the canonical English "DELETE" as a permanent fallback.
 *
 * Matching is case-insensitive and whitespace-trimmed: refusing to delete an
 * account because the user typed "delete" or added a trailing space is a
 * self-inflicted GDPR failure, and the real safety gate is the password
 * re-authentication the backend enforces on erasure.
 */

/** The canonical, locale-independent confirmation keyword. Always accepted. */
export const CANONICAL_DELETE_KEYWORD = 'DELETE';

/**
 * Returns true when `input` matches the confirmation keyword.
 *
 * @param input            Raw value typed into the confirmation field.
 * @param localizedKeyword The keyword the UI instructed the user to type
 *                         (i.e. the translated placeholder). Optional — when
 *                         omitted only the canonical keyword is accepted.
 */
export function isDeleteConfirmed(input: string, localizedKeyword?: string): boolean {
  const normalized = input.trim().toUpperCase();
  if (normalized.length === 0) return false;

  if (normalized === CANONICAL_DELETE_KEYWORD) return true;

  const localized = (localizedKeyword ?? '').trim().toUpperCase();
  return localized.length > 0 && normalized === localized;
}
