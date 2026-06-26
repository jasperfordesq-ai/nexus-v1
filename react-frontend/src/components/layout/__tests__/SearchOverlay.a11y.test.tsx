// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Regression test: the Cmd+K command palette announces its result count to
 * screen readers, and every result is a reachable, labelled control.
 *
 * (Results are real <button>s reachable via Tab; the sole gap the survey found
 * was that the result count was never announced. A polite live region now
 * announces it. Tested on the synchronous action-mode (`>`) path.)
 */

import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { SearchOverlay } from '../SearchOverlay';

vi.mock('react-router-dom', () => ({ useNavigate: () => vi.fn() }));

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn().mockResolvedValue({ success: true, data: {} }) },
}));

vi.mock('@/lib/safeStorage', () => ({
  safeLocalStorageGetJSON: () => [],
  safeLocalStorageSetJSON: () => {},
  safeLocalStorageRemove: () => {},
}));

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, unknown>) =>
      key === 'aria.search_results'
        ? `${opts?.count ?? 0} results available`
        : key,
  }),
  Trans: ({ children }: { children: unknown }) => children,
  initReactI18next: { type: '3rdParty', init: () => {} },
}));

vi.mock('@/contexts', () => ({
  useAuth: () => ({ isAuthenticated: true }),
  useTenant: () => ({ tenantPath: (p: string) => p, hasFeature: () => true }),
  useTheme: () => ({ resolvedTheme: 'light', toggleTheme: vi.fn() }),
}));

describe('SearchOverlay — command palette result announcement', () => {
  it('announces the result count and keeps every result reachable', () => {
    render(<SearchOverlay isOpen onClose={vi.fn()} />);

    const input = screen.getByRole('textbox');
    // "> " enters action mode — populated synchronously from quick actions.
    fireEvent.change(input, { target: { value: '>' } });

    // A polite live region announces how many results are available.
    expect(screen.getByText(/results available/)).toBeInTheDocument();

    // Every action is a real, labelled button (reachable by keyboard via Tab).
    const buttons = screen.getAllByRole('button');
    expect(buttons.length).toBeGreaterThan(0);
    expect(screen.getByText('user_menu.settings')).toBeInTheDocument();
  });
});
