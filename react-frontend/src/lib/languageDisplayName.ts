// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/** Resolve a BCP 47 language code in the administrator's active locale. */
export function languageDisplayName(code: string, locale?: string): string {
  try {
    return new Intl.DisplayNames(locale ? [locale] : undefined, {
      type: 'language',
      fallback: 'code',
    }).of(code) ?? code.toUpperCase();
  } catch {
    return code.toUpperCase();
  }
}
