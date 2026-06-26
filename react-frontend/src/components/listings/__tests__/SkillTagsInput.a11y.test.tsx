// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Regression test: the skill-tags autocomplete is a real combobox.
 *
 * Suggestions must expose the `option` role + ids so arrow-key navigation moves
 * aria-activedescendant on the input (HeroUI Button does NOT forward role, so the
 * options are native elements), and the result count is announced.
 */

import { useState } from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, within, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { SkillTagsInput } from '../SkillTagsInput';

const mockGet = vi.fn();
vi.mock('@/lib/api', () => ({ api: { get: (...args: unknown[]) => mockGet(...args) } }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, unknown>) => {
      if (key === 'skill_tags.aria_results') return `${opts?.count} skill suggestions available`;
      if (key === 'skill_tags.aria_add_count') return `Add skill tag (${opts?.current} of ${opts?.max})`;
      return key;
    },
  }),
  Trans: ({ children }: { children: unknown }) => children,
  initReactI18next: { type: '3rdParty', init: () => {} },
}));

function Harness() {
  const [tags, setTags] = useState<string[]>([]);
  return <SkillTagsInput tags={tags} onChange={setTags} />;
}

describe('SkillTagsInput — combobox a11y', () => {
  beforeEach(() => mockGet.mockReset());

  it('renders real options and wires aria-activedescendant on arrow nav', async () => {
    mockGet.mockResolvedValue({ success: true, data: ['React', 'React Native'] });
    const user = userEvent.setup();
    render(<Harness />);

    const input = screen.getByRole('combobox');
    await user.type(input, 'rea');

    const listbox = await screen.findByRole('listbox', {}, { timeout: 3000 });
    const options = within(listbox).getAllByRole('option');
    expect(options).toHaveLength(2);
    expect(options[0]).toHaveAttribute('id', expect.stringMatching(/-opt-0$/));
    expect(input).not.toHaveAttribute('aria-activedescendant');

    await user.keyboard('{ArrowDown}');
    await waitFor(() => {
      const active = input.getAttribute('aria-activedescendant');
      expect(active).toBeTruthy();
      expect(document.getElementById(active as string)).not.toBeNull();
    });

    const selected = within(listbox)
      .getAllByRole('option')
      .find((o) => o.getAttribute('aria-selected') === 'true');
    expect(selected?.id).toBe(input.getAttribute('aria-activedescendant'));
  });

  it('announces the suggestion count to screen readers', async () => {
    mockGet.mockResolvedValue({ success: true, data: ['React', 'Vue', 'Svelte'] });
    const user = userEvent.setup();
    render(<Harness />);

    await user.type(screen.getByRole('combobox'), 'fr');
    await screen.findByRole('listbox', {}, { timeout: 3000 });
    expect(await screen.findByText(/3 skill suggestions available/)).toBeInTheDocument();
  });
});
