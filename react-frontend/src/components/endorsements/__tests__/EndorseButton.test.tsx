// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for EndorseButton component
 */

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

const mockToast = {
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
};

vi.mock('@/contexts', () => ({
  useToast: vi.fn(() => mockToast),

  useTheme: () => ({ resolvedTheme: 'light', toggleTheme: vi.fn(), theme: 'system', setTheme: vi.fn() }),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
  useAuth: () => ({ user: null, isAuthenticated: false, login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle', error: null }),
  useTenant: () => ({ tenant: { id: 2, name: 'Test', slug: 'test', tagline: null }, branding: { name: 'Test', logo_url: null }, tenantSlug: 'test', tenantPath: (p) => '/test' + p, isLoading: false, hasFeature: vi.fn(() => true), hasModule: vi.fn(() => true) }),
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

import { EndorseButton } from '../EndorseButton';

describe('EndorseButton — default (non-compact)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders as a button', () => {
    render(
      <EndorseButton
        memberId={5}
        skillName="Gardening"
        endorsementCount={3}
        isEndorsed={false}
      />
    );
    expect(screen.getByRole('button')).toBeInTheDocument();
  });

  it('shows endorsement count when count > 0', () => {
    render(
      <EndorseButton
        memberId={5}
        skillName="Gardening"
        endorsementCount={7}
        isEndorsed={false}
      />
    );
    expect(screen.getByText(/7/)).toBeInTheDocument();
  });

  it('calls POST endpoint when endorsing a skill', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({ success: true });

    render(
      <EndorseButton
        memberId={5}
        skillName="Cooking"
        endorsementCount={2}
        isEndorsed={false}
      />
    );

    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith(
        '/v2/members/5/endorse',
        expect.objectContaining({ skill_name: 'Cooking' })
      );
    });
  });

  it('calls DELETE endpoint when removing endorsement', async () => {
    vi.mocked(api.delete).mockResolvedValueOnce({ success: true });

    render(
      <EndorseButton
        memberId={5}
        skillName="Cooking"
        endorsementCount={3}
        isEndorsed={true}
      />
    );

    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => {
      expect(api.delete).toHaveBeenCalledWith(
        '/v2/members/5/endorse',
        expect.any(Object)
      );
    });
  });

  it('optimistically increments count when endorsing', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({ success: true });

    render(
      <EndorseButton
        memberId={5}
        skillName="Cooking"
        endorsementCount={2}
        isEndorsed={false}
      />
    );

    fireEvent.click(screen.getByRole('button'));

    // Optimistic update should show 3 immediately
    await waitFor(() => {
      expect(screen.getByText(/3/)).toBeInTheDocument();
    });
  });

  it('reverts count on API failure', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({ success: false, error: 'Server error' });

    render(
      <EndorseButton
        memberId={5}
        skillName="Cooking"
        endorsementCount={2}
        isEndorsed={false}
      />
    );

    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => {
      // Count should revert to original 2 after failure
      expect(screen.getByText(/2/)).toBeInTheDocument();
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('calls onEndorsementChange callback after successful endorse', async () => {
    const onEndorsementChange = vi.fn();
    vi.mocked(api.post).mockResolvedValueOnce({ success: true });

    render(
      <EndorseButton
        memberId={5}
        skillName="Cooking"
        endorsementCount={1}
        isEndorsed={false}
        onEndorsementChange={onEndorsementChange}
      />
    );

    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => {
      expect(onEndorsementChange).toHaveBeenCalled();
    });
  });
});

describe('EndorseButton — compact mode', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders compact button without visible text label', () => {
    render(
      <EndorseButton
        memberId={10}
        skillName="Driving"
        endorsementCount={5}
        isEndorsed={false}
        compact
      />
    );
    // In compact mode it renders a button with an aria-label
    const btn = screen.getByRole('button');
    expect(btn).toBeInTheDocument();
    expect(btn).toHaveAttribute('aria-label');
  });

  it('renders count when endorsement count > 0 in compact mode', () => {
    render(
      <EndorseButton
        memberId={10}
        skillName="Driving"
        endorsementCount={4}
        isEndorsed={false}
        compact
      />
    );
    expect(screen.getByText('4')).toBeInTheDocument();
  });

  it('does not render count when endorsement count is 0 in compact mode', () => {
    render(
      <EndorseButton
        memberId={10}
        skillName="Driving"
        endorsementCount={0}
        isEndorsed={false}
        compact
      />
    );
    expect(screen.queryByText('0')).not.toBeInTheDocument();
  });
});
