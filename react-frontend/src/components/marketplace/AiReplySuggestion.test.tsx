// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { api } from '@/lib/api';
import { AiReplySuggestion } from './AiReplySuggestion';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

vi.mock('@/contexts', () => ({
  useTheme: () => ({ resolvedTheme: 'light', theme: 'system', toggleTheme: vi.fn(), setTheme: vi.fn() }),
  useToast: () => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }),
  useAuth: () => ({ user: null, isAuthenticated: false, login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle', error: null }),
  useTenant: () => ({ tenant: { id: 2, name: 'Test', slug: 'test' }, tenantPath: (p: string) => `/test${p}`, hasFeature: vi.fn(() => true), hasModule: vi.fn(() => true) }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  usePresence: () => ({ status: 'offline', setStatus: vi.fn(), getPresence: vi.fn(), isOnline: vi.fn(() => false) }),
  usePresenceOptional: () => null,
}));

const defaultProps = {
  listingId: 10,
  buyerMessage: 'Is this still available?',
  onUseReply: vi.fn(),
};

describe('AiReplySuggestion — pre-generation state', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the "Suggest Reply" button initially', () => {
    render(<AiReplySuggestion {...defaultProps} />);

    // The i18n key ai_reply.suggest resolves from the marketplace namespace
    const button = screen.getByRole('button');
    expect(button).toBeInTheDocument();
  });

  it('does not show the generated reply card initially', () => {
    render(<AiReplySuggestion {...defaultProps} />);

    // Regenerate / Copy / Use Reply buttons only appear post-generation
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument();
  });

  it('disables the suggest button when buyerMessage is empty', () => {
    render(
      <AiReplySuggestion
        listingId={1}
        buyerMessage="   "
        onUseReply={vi.fn()}
      />
    );

    const button = screen.getByRole('button');
    const isDisabled =
      button.hasAttribute('disabled') ||
      button.getAttribute('aria-disabled') === 'true' ||
      button.getAttribute('data-disabled') === 'true';
    expect(isDisabled).toBe(true);
  });

  it('enables the suggest button when buyerMessage has content', () => {
    render(<AiReplySuggestion {...defaultProps} />);

    const button = screen.getByRole('button');
    const isDisabled =
      button.hasAttribute('disabled') ||
      button.getAttribute('aria-disabled') === 'true' ||
      button.getAttribute('data-disabled') === 'true';
    expect(isDisabled).toBe(false);
  });
});

describe('AiReplySuggestion — generation flow', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('calls the correct API endpoint when suggest button is clicked', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({
      data: { reply: 'Yes, it is still available!' },
    });

    render(<AiReplySuggestion {...defaultProps} />);

    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith(
        `/v2/marketplace/listings/${defaultProps.listingId}/auto-reply`,
        { message: defaultProps.buyerMessage },
      );
    });
  });

  it('renders the suggested reply in a textarea after generation', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({
      data: { reply: 'Happy to help you with that!' },
    });

    render(<AiReplySuggestion {...defaultProps} />);

    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => {
      expect(screen.getByRole('textbox')).toBeInTheDocument();
    });

    // The textarea value should contain the AI reply
    const textarea = screen.getByRole('textbox') as HTMLTextAreaElement;
    expect(textarea.value).toBe('Happy to help you with that!');
  });

  it('calls onUseReply with the reply text when "Use Reply" is clicked', async () => {
    const onUseReply = vi.fn();
    vi.mocked(api.post).mockResolvedValueOnce({
      data: { reply: 'Great question!' },
    });

    render(
      <AiReplySuggestion
        listingId={5}
        buyerMessage="Is this available?"
        onUseReply={onUseReply}
      />
    );

    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => {
      expect(screen.getByRole('textbox')).toBeInTheDocument();
    });

    // Find the "Use Reply" button — it's one of the action buttons in the card
    const buttons = screen.getAllByRole('button');
    // The Use Reply button is the last button in the action row
    const useReplyButton = buttons[buttons.length - 1];
    fireEvent.click(useReplyButton);

    expect(onUseReply).toHaveBeenCalledWith('Great question!');
  });

  it('shows an error alert when API call fails', async () => {
    vi.mocked(api.post).mockRejectedValueOnce(new Error('Server error'));

    render(<AiReplySuggestion {...defaultProps} />);

    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => {
      // The error <span role="alert"> rendered by AiReplySuggestion will have
      // non-empty text content (the i18n key ai_reply.error). We distinguish it
      // from the always-present ToastProvider alert containers (which are empty).
      const alerts = screen.queryAllByRole('alert');
      const errorAlert = alerts.find((el) => el.textContent && el.textContent.trim().length > 0);
      expect(errorAlert).toBeInTheDocument();
    });
  });

  it('does not show error text before any attempt', () => {
    render(<AiReplySuggestion {...defaultProps} />);

    // The ToastProvider always renders a role="alert" region in the DOM.
    // We check that no *visible* error message span appears before generation.
    // The error span is a <span role="alert" class="...text-danger"> — it only
    // renders after a failed generate attempt.
    const alerts = screen.queryAllByRole('alert');
    // All pre-existing alerts must be empty (ToastProvider containers, not error spans)
    const hasErrorText = alerts.some((el) => el.textContent && el.textContent.trim().length > 0);
    expect(hasErrorText).toBe(false);
  });

  it('calls API again when Regenerate button is clicked', async () => {
    vi.mocked(api.post)
      .mockResolvedValueOnce({ data: { reply: 'First reply' } })
      .mockResolvedValueOnce({ data: { reply: 'Second reply' } });

    render(<AiReplySuggestion {...defaultProps} />);

    // First generate
    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => {
      expect(screen.getByRole('textbox')).toBeInTheDocument();
    });

    // Click Regenerate (first button in the action row of the generated card)
    const actionButtons = screen.getAllByRole('button');
    // Regenerate is the first button after generation
    fireEvent.click(actionButtons[0]);

    await waitFor(() => {
      expect(api.post).toHaveBeenCalledTimes(2);
    });
  });

  it('handles empty reply from API gracefully', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({
      data: { reply: undefined },
    });

    render(<AiReplySuggestion {...defaultProps} />);

    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => {
      expect(screen.getByRole('textbox')).toBeInTheDocument();
    });

    const textarea = screen.getByRole('textbox') as HTMLTextAreaElement;
    expect(textarea.value).toBe('');
  });

  // NOTE: The Copy button path (navigator.clipboard) is not tested here because
  // jsdom does not implement navigator.clipboard by default and stubbing it
  // requires additional setup that is not worth the fragility. The copy
  // fallback silently swallows the clipboard error, so this branch is
  // genuinely unreachable without a clipboard polyfill.
});
