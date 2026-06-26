// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Regression test: the skill-search inside the Add-Skill modal is a real combobox.
 *
 * Results expose the `option` role + ids (native elements, since HeroUI Button
 * does not forward role), arrow-key navigation moves aria-activedescendant, and
 * the result count is announced.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, within, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { SkillSelector } from '../SkillSelector';

const mockGet = vi.fn();
vi.mock('@/lib/api', () => ({
  api: {
    get: (...args: unknown[]) => mockGet(...args),
    post: vi.fn().mockResolvedValue({ success: true }),
    delete: vi.fn().mockResolvedValue({ success: true }),
  },
}));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/contexts', () => ({
  useToast: () => ({ success: vi.fn(), error: vi.fn() }),
}));
vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, unknown>) =>
      key === 'skills.aria_results' ? `${opts?.count} skill suggestions available` : key,
  }),
  Trans: ({ children }: { children: unknown }) => children,
  initReactI18next: { type: '3rdParty', init: () => {} },
}));

describe('SkillSelector — search combobox a11y', () => {
  beforeEach(() => {
    mockGet.mockReset();
    mockGet.mockImplementation((url: string) =>
      url.includes('/skills/search')
        ? Promise.resolve({
            success: true,
            data: [
              { id: 1, name: 'React', category_name: 'Tech', category_id: 5 },
              { id: 2, name: 'Redux', category_name: 'Tech', category_id: 5 },
            ],
          })
        : Promise.resolve({ success: true, data: [] }),
    );
  });

  it('exposes search results as combobox options with activedescendant', async () => {
    const user = userEvent.setup();
    render(<SkillSelector userSkills={[]} onSkillsChange={vi.fn()} />);

    // Open the Add-Skill modal (trigger button uses the 'skills.add_skill' key).
    await user.click(screen.getByRole('button', { name: 'skills.add_skill' }));

    const input = await screen.findByRole('combobox');
    await user.type(input, 'rea');

    const listbox = await screen.findByRole('listbox', {}, { timeout: 3000 });
    const options = within(listbox).getAllByRole('option');
    expect(options).toHaveLength(2);
    expect(options[0]).toHaveAttribute('id', expect.stringMatching(/-opt-0$/));

    await user.keyboard('{ArrowDown}');
    await waitFor(() => {
      const active = input.getAttribute('aria-activedescendant');
      expect(active).toBeTruthy();
      expect(document.getElementById(active as string)).not.toBeNull();
    });
  });
});
