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

const mockToast = {
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
  showToast: vi.fn(),
};

vi.mock('@/contexts', () => createMockContexts({
  useToast: () => mockToast,
}));

import { api } from '@/lib/api';
import { TranslateButton } from './TranslateButton';

const DEFAULT_PROPS = {
  contentType: 'listing',
  contentId: '123',
  sourceText: 'Bonjour le monde',
  // sourceLocale is 'fr'; test environment i18n is 'en' → locales differ → button renders
  sourceLocale: 'fr',
};

describe('TranslateButton — rendering conditions', () => {
  beforeEach(() => vi.clearAllMocks());

  it('renders the Translate button when sourceLocale differs from user locale', () => {
    render(<TranslateButton {...DEFAULT_PROPS} />);
    expect(screen.getByRole('button', { name: /translate/i })).toBeInTheDocument();
  });

  it('returns null when hidden=true', () => {
    render(<TranslateButton {...DEFAULT_PROPS} hidden={true} />);
    expect(screen.queryByRole('button')).not.toBeInTheDocument();
  });

  it('returns null when sourceLocale matches user locale (en)', () => {
    render(
      <TranslateButton
        contentType="listing"
        contentId="1"
        sourceText="Hello world"
        sourceLocale="en"
      />
    );
    expect(screen.queryByRole('button')).not.toBeInTheDocument();
  });

  it('returns null when sourceText is empty', () => {
    render(<TranslateButton {...DEFAULT_PROPS} sourceText="" />);
    expect(screen.queryByRole('button')).not.toBeInTheDocument();
  });

  it('returns null when sourceText is whitespace only', () => {
    render(<TranslateButton {...DEFAULT_PROPS} sourceText="   " />);
    expect(screen.queryByRole('button')).not.toBeInTheDocument();
  });
});

describe('TranslateButton — translate action', () => {
  beforeEach(() => vi.clearAllMocks());

  it('calls POST /v2/ugc-translate on first click', async () => {
    vi.mocked(api.post).mockResolvedValue({
      success: true,
      data: {
        translated_text: 'Hello world',
        source_locale: 'fr',
        target_locale: 'en',
        cached: false,
      },
    });

    render(<TranslateButton {...DEFAULT_PROPS} />);
    fireEvent.click(screen.getByRole('button', { name: /translate/i }));

    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith(
        '/v2/ugc-translate',
        expect.objectContaining({
          content_type: 'listing',
          content_id: '123',
          source_text: 'Bonjour le monde',
          source_locale: 'fr',
          target_locale: 'en',
        })
      );
    });
  });

  it('switches button label to "Show original" after successful translation', async () => {
    vi.mocked(api.post).mockResolvedValue({
      success: true,
      data: { translated_text: 'Hello world', source_locale: 'fr', target_locale: 'en', cached: false },
    });

    render(<TranslateButton {...DEFAULT_PROPS} />);
    fireEvent.click(screen.getByRole('button', { name: /translate/i }));

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /show original/i })).toBeInTheDocument();
    });
  });

  it('calls onTextChange with translated text after translate', async () => {
    const onTextChange = vi.fn();
    vi.mocked(api.post).mockResolvedValue({
      success: true,
      data: { translated_text: 'Hello world', source_locale: 'fr', target_locale: 'en', cached: false },
    });

    render(<TranslateButton {...DEFAULT_PROPS} onTextChange={onTextChange} />);
    fireEvent.click(screen.getByRole('button', { name: /translate/i }));

    await waitFor(() => {
      expect(onTextChange).toHaveBeenCalledWith('Hello world', true);
    });
  });

  it('toggles back to original on second click (no new API call)', async () => {
    const onTextChange = vi.fn();
    vi.mocked(api.post).mockResolvedValue({
      success: true,
      data: { translated_text: 'Hello world', source_locale: 'fr', target_locale: 'en', cached: false },
    });

    render(<TranslateButton {...DEFAULT_PROPS} onTextChange={onTextChange} />);
    // First click — translate
    fireEvent.click(screen.getByRole('button', { name: /translate/i }));
    await waitFor(() => expect(screen.getByRole('button', { name: /show original/i })).toBeInTheDocument());

    // Second click — revert
    fireEvent.click(screen.getByRole('button', { name: /show original/i }));
    await waitFor(() => {
      // Should be back to "Translate"
      expect(screen.getByRole('button', { name: /^translate$/i })).toBeInTheDocument();
      // API should have been called only once
      expect(api.post).toHaveBeenCalledTimes(1);
      // onTextChange called with original text and isTranslated=false
      expect(onTextChange).toHaveBeenLastCalledWith('Bonjour le monde', false);
    });
  });

  it('does NOT re-call the API on second translate click (uses cached translation)', async () => {
    vi.mocked(api.post).mockResolvedValue({
      success: true,
      data: { translated_text: 'Hello world', source_locale: 'fr', target_locale: 'en', cached: true },
    });

    render(<TranslateButton {...DEFAULT_PROPS} />);
    // Translate
    fireEvent.click(screen.getByRole('button', { name: /translate/i }));
    await waitFor(() => expect(screen.getByRole('button', { name: /show original/i })).toBeInTheDocument());

    // Revert
    fireEvent.click(screen.getByRole('button', { name: /show original/i }));
    await waitFor(() => expect(screen.getByRole('button', { name: /^translate$/i })).toBeInTheDocument());

    // Translate again — should use cached value, no new POST
    fireEvent.click(screen.getByRole('button', { name: /^translate$/i }));
    await waitFor(() => expect(screen.getByRole('button', { name: /show original/i })).toBeInTheDocument());
    expect(api.post).toHaveBeenCalledTimes(1);
  });
});

describe('TranslateButton — error handling', () => {
  beforeEach(() => vi.clearAllMocks());

  it('shows error toast when API returns success:false', async () => {
    vi.mocked(api.post).mockResolvedValue({ success: false, error: 'Rate limited' });

    render(<TranslateButton {...DEFAULT_PROPS} />);
    fireEvent.click(screen.getByRole('button', { name: /translate/i }));

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalledWith('Rate limited');
    });
    // Button should remain showing "Translate" (not switched to "Show original")
    expect(screen.getByRole('button', { name: /^translate$/i })).toBeInTheDocument();
  });

  it('shows fallback error toast when API throws', async () => {
    vi.mocked(api.post).mockRejectedValue(new Error('Network error'));

    render(<TranslateButton {...DEFAULT_PROPS} />);
    fireEvent.click(screen.getByRole('button', { name: /translate/i }));

    await waitFor(() => {
      expect(mockToast.showToast).toHaveBeenCalled();
    });
  });
});
