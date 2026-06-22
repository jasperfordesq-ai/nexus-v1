// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };

vi.mock('@/contexts', () => createMockContexts({
  useToast: () => mockToast,
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

import { TranslationTab } from './TranslationTab';
import { api } from '@/lib/api';

const DEFAULT_PREFS = {
  feed: { prefers_chronological: false },
  translation: { auto_translate_ugc: false, auto_translate_target_locale: 'en' },
};

describe('TranslationTab', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(api.get).mockResolvedValue({ success: true, data: DEFAULT_PREFS });
  });

  it('shows both preference sections after loading', async () => {
    render(<TranslationTab />);
    await waitFor(() => expect(api.get).toHaveBeenCalledWith('/v2/users/me/preferences'));

    // Two sections rendered — personalisation and translation
    // Both heading elements should exist (h2 tags)
    const headings = screen.getAllByRole('heading', { level: 2 });
    expect(headings.length).toBeGreaterThanOrEqual(2);
  });

  it('renders the feed personalisation toggle', async () => {
    render(<TranslationTab />);
    await waitFor(() => expect(api.get).toHaveBeenCalled());

    // Switch for chronological feed preference
    const switches = screen.getAllByRole('switch');
    expect(switches.length).toBeGreaterThanOrEqual(1);
  });

  it('renders the auto-translate toggle', async () => {
    render(<TranslationTab />);
    await waitFor(() => expect(api.get).toHaveBeenCalled());

    const switches = screen.getAllByRole('switch');
    // At least 2 switches: chronological + auto-translate
    expect(switches.length).toBeGreaterThanOrEqual(2);
  });

  it('renders the language select for translation target', async () => {
    render(<TranslationTab />);
    await waitFor(() => expect(api.get).toHaveBeenCalled());

    // The target locale select renders
    // HeroUI Select renders a button or combobox
    const selectBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.includes('EN') || b.textContent?.includes('en') ||
      b.getAttribute('aria-label')?.toLowerCase().includes('locale') ||
      b.getAttribute('aria-label')?.toLowerCase().includes('language')
    );
    expect(selectBtn).toBeInTheDocument();
  });

  it('pre-populates preferences from API response', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: {
        feed: { prefers_chronological: true },
        translation: { auto_translate_ugc: true, auto_translate_target_locale: 'de' },
      },
    });

    render(<TranslationTab />);
    await waitFor(() => expect(api.get).toHaveBeenCalled());

    // HeroUI Switch uses data-selected (not aria-checked) to indicate selected state.
    // Find a switch element that is selected (selected switches have data-selected attribute).
    const switches = screen.getAllByRole('switch');
    const hasAnySelected = switches.some(
      (sw) =>
        sw.getAttribute('data-selected') !== null ||
        sw.getAttribute('aria-checked') === 'true' ||
        // HeroUI may place data-selected on a child; check the parent group too
        sw.closest('[data-selected]') !== null,
    );
    expect(hasAnySelected).toBe(true);
  });

  it('calls PUT on save and shows a success toast', async () => {
    vi.mocked(api.put).mockResolvedValue({ success: true });
    render(<TranslationTab />);
    await waitFor(() => expect(api.get).toHaveBeenCalled());

    const saveBtn = screen.getByRole('button', { name: /save/i });
    fireEvent.click(saveBtn);

    await waitFor(() => {
      expect(api.put).toHaveBeenCalledWith(
        '/v2/users/me/preferences',
        expect.objectContaining({
          feed: expect.any(Object),
          translation: expect.any(Object),
        }),
      );
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('shows error toast when PUT fails', async () => {
    vi.mocked(api.put).mockResolvedValue({ success: false });
    render(<TranslationTab />);
    await waitFor(() => expect(api.get).toHaveBeenCalled());

    fireEvent.click(screen.getByRole('button', { name: /save/i }));

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('shows error toast when PUT throws', async () => {
    vi.mocked(api.put).mockRejectedValue(new Error('Network error'));
    render(<TranslationTab />);
    await waitFor(() => expect(api.get).toHaveBeenCalled());

    fireEvent.click(screen.getByRole('button', { name: /save/i }));

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('saves the locale payload when translation is enabled', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: {
        feed: { prefers_chronological: false },
        translation: { auto_translate_ugc: true, auto_translate_target_locale: 'fr' },
      },
    });
    vi.mocked(api.put).mockResolvedValue({ success: true });

    render(<TranslationTab />);
    await waitFor(() => expect(api.get).toHaveBeenCalled());

    fireEvent.click(screen.getByRole('button', { name: /save/i }));

    await waitFor(() => {
      expect(api.put).toHaveBeenCalledWith(
        '/v2/users/me/preferences',
        expect.objectContaining({
          translation: expect.objectContaining({
            auto_translate_target_locale: 'fr',
          }),
        }),
      );
    });
  });
});
