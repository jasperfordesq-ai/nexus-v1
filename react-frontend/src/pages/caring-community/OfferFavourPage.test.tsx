// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { api } from '@/lib/api';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

// hasFeature is a vi.fn so individual tests can control the return value
const mockHasFeature = vi.fn(() => true);

vi.mock('@/contexts', () => ({
  useAuth: () => ({
    user: null, isAuthenticated: false, login: vi.fn(), logout: vi.fn(),
    register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(),
    status: 'idle' as const, error: null,
  }),
  useTenant: () => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    branding: { name: 'Test Tenant' },
    tenantSlug: 'test',
    tenantPath: (p: string) => `/test${p}`,
    isLoading: false,
    hasFeature: mockHasFeature,
    hasModule: vi.fn(() => true),
  }),
  useToast: () => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }),
  useTheme: () => ({ resolvedTheme: 'light', theme: 'system', toggleTheme: vi.fn(), setTheme: vi.fn() }),
  useNotifications: () => ({
    unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(),
    markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn(),
  }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({
    consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(),
    saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn(),
  }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
  usePresence: () => ({ status: 'offline', setStatus: vi.fn(), getPresence: vi.fn(), isOnline: vi.fn(() => false) }),
  usePresenceOptional: () => null,
}));

import { OfferFavourPage } from './OfferFavourPage';

// Real English strings from public/locales/en/common.json
const TITLE = 'Offer a Favour';
const SUBMIT_LABEL = 'Record Favour';
const SUCCESS_TITLE = 'Thank You!';
const ERROR_TEXT = 'Could not record your favour. Please try again.';
const TEXTAREA_LABEL = 'What did you do to help?';

describe('OfferFavourPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockHasFeature.mockReturnValue(true);
  });

  it('renders the form heading', () => {
    render(<OfferFavourPage />);
    expect(screen.getByRole('heading', { level: 1, name: TITLE })).toBeInTheDocument();
  });

  it('renders the description textarea with the correct label', () => {
    render(<OfferFavourPage />);
    // HeroUI Textarea renders the label as a <label> element
    expect(screen.getByText(TEXTAREA_LABEL)).toBeInTheDocument();
  });

  it('submit button is disabled when description is empty', () => {
    render(<OfferFavourPage />);
    // HeroUI v3 renders isDisabled on the button via data-disabled or aria-disabled.
    // We verify the button is present and cannot be used (data-disabled attribute).
    const submitBtn = screen.getByRole('button', { name: SUBMIT_LABEL });
    // At least one of data-disabled or aria-disabled should be set
    const isDisabled =
      submitBtn.hasAttribute('data-disabled') ||
      submitBtn.getAttribute('aria-disabled') === 'true' ||
      (submitBtn as HTMLButtonElement).disabled;
    expect(isDisabled).toBe(true);
  });

  it('submit button becomes enabled after entering description text', () => {
    render(<OfferFavourPage />);
    const textarea = screen.getByRole('textbox', { name: TEXTAREA_LABEL });
    fireEvent.change(textarea, { target: { value: 'I carried groceries for my neighbour.' } });
    const submitBtn = screen.getByRole('button', { name: SUBMIT_LABEL });
    expect(submitBtn).not.toHaveAttribute('aria-disabled', 'true');
  });

  it('calls api.post with correct payload when form is submitted', async () => {
    vi.mocked(api.post).mockResolvedValue({ success: true, data: {} });

    render(<OfferFavourPage />);

    const textarea = screen.getByRole('textbox', { name: TEXTAREA_LABEL });
    fireEvent.change(textarea, { target: { value: 'I will carry groceries for a neighbour.' } });

    fireEvent.click(screen.getByRole('button', { name: SUBMIT_LABEL }));

    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith(
        '/v2/caring-community/offer-favour',
        expect.objectContaining({
          description: 'I will carry groceries for a neighbour.',
        }),
      );
    });
  });

  it('shows success state after a successful submission', async () => {
    vi.mocked(api.post).mockResolvedValue({ success: true, data: {} });

    render(<OfferFavourPage />);

    const textarea = screen.getByRole('textbox', { name: TEXTAREA_LABEL });
    fireEvent.change(textarea, { target: { value: 'Helping with garden.' } });
    fireEvent.click(screen.getByRole('button', { name: SUBMIT_LABEL }));

    await waitFor(() => {
      expect(screen.getByRole('heading', { level: 1, name: SUCCESS_TITLE })).toBeInTheDocument();
    });
    // The form should no longer be present
    expect(screen.queryByRole('textbox', { name: TEXTAREA_LABEL })).not.toBeInTheDocument();
  });

  it('shows an error alert when the API returns a non-success response with an error message', async () => {
    vi.mocked(api.post).mockResolvedValue({
      success: false,
      error: 'Something went wrong',
    });

    render(<OfferFavourPage />);

    const textarea = screen.getByRole('textbox', { name: TEXTAREA_LABEL });
    fireEvent.change(textarea, { target: { value: 'Help with transport.' } });
    fireEvent.click(screen.getByRole('button', { name: SUBMIT_LABEL }));

    await waitFor(() => {
      // The page-level error <p role="alert"> contains the API error message.
      // (A second role="alert" exists from the ToastProvider portal — use text.)
      expect(screen.getByText('Something went wrong')).toBeInTheDocument();
    });
    // Should NOT show success state
    expect(screen.queryByRole('heading', { name: SUCCESS_TITLE })).not.toBeInTheDocument();
  });

  it('shows the generic error text when the API returns non-success without an error message', async () => {
    vi.mocked(api.post).mockResolvedValue({ success: false });

    render(<OfferFavourPage />);

    const textarea = screen.getByRole('textbox', { name: TEXTAREA_LABEL });
    fireEvent.change(textarea, { target: { value: 'Help with meals.' } });
    fireEvent.click(screen.getByRole('button', { name: SUBMIT_LABEL }));

    await waitFor(() => {
      expect(screen.getByText(ERROR_TEXT)).toBeInTheDocument();
    });
  });

  it('shows a generic error alert when the API call throws', async () => {
    vi.mocked(api.post).mockRejectedValue(new Error('Network error'));

    render(<OfferFavourPage />);

    const textarea = screen.getByRole('textbox', { name: TEXTAREA_LABEL });
    fireEvent.change(textarea, { target: { value: 'Help with meals.' } });
    fireEvent.click(screen.getByRole('button', { name: SUBMIT_LABEL }));

    await waitFor(() => {
      expect(screen.getByText(ERROR_TEXT)).toBeInTheDocument();
    });
  });

  // Feature-gate: when caring_community is disabled the page renders <Navigate />
  // BrowserRouter in test-utils handles the redirect; the form heading must not appear.
  it('does not render the form when caring_community feature is disabled', () => {
    mockHasFeature.mockReturnValue(false);
    render(<OfferFavourPage />);
    expect(screen.queryByRole('heading', { name: TITLE })).not.toBeInTheDocument();
  });
});
