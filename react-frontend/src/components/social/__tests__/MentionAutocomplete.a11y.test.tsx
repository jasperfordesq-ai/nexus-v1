// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Regression test: @mention suggestions must be real listbox options.
 *
 * The combobox input (MentionInput) points aria-activedescendant at
 * `mention-option-<id>`. That only works if each option actually exposes the
 * `option` role + matching id — which HeroUI Button does NOT forward, so the
 * options are native elements. This locks that contract.
 */

import { describe, it, expect, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import { MentionAutocomplete, type MentionSuggestion } from '../MentionAutocomplete';

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, unknown>) =>
      opts && 'count' in opts ? `${opts.count} suggestions` : key,
  }),
  Trans: ({ children }: { children: unknown }) => children,
  initReactI18next: { type: '3rdParty', init: () => {} },
}));

const suggestions: MentionSuggestion[] = [
  { id: 1, name: 'Alice Adams', username: 'alice', avatar_url: null },
  { id: 2, name: 'Bob Brown', username: 'bob', avatar_url: null, is_connection: true },
];

describe('MentionAutocomplete — listbox option a11y contract', () => {
  it('renders real options whose ids match the activedescendant pattern', () => {
    render(
      <MentionAutocomplete
        isOpen
        suggestions={suggestions}
        selectedIndex={1}
        isLoading={false}
        query="b"
        onSelect={vi.fn()}
        onHover={vi.fn()}
      />,
    );

    const listbox = screen.getByRole('listbox');
    expect(listbox).toHaveAttribute('id', 'mention-listbox');

    const options = within(listbox).getAllByRole('option');
    expect(options).toHaveLength(2);
    expect(options[0]).toHaveAttribute('id', 'mention-option-1');
    expect(options[1]).toHaveAttribute('id', 'mention-option-2');

    // aria-selected tracks selectedIndex (1 → second option).
    expect(options[0]).toHaveAttribute('aria-selected', 'false');
    expect(options[1]).toHaveAttribute('aria-selected', 'true');
  });
});
