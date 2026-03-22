// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for AiAssistButton component
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

import { AiAssistButton } from '../AiAssistButton';

describe('AiAssistButton', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the AI assist button', () => {
    render(
      <AiAssistButton
        type="listing"
        title="Gardening Help"
        onGenerated={vi.fn()}
      />
    );
    const btn = screen.getByRole('button');
    expect(btn).toBeInTheDocument();
  });

  it('is disabled when title is empty', () => {
    render(
      <AiAssistButton
        type="listing"
        title=""
        onGenerated={vi.fn()}
      />
    );
    const btn = screen.getByRole('button');
    expect(btn).toBeDisabled();
  });

  it('is enabled when title has content', () => {
    render(
      <AiAssistButton
        type="listing"
        title="Piano lessons"
        onGenerated={vi.fn()}
      />
    );
    const btn = screen.getByRole('button');
    expect(btn).not.toBeDisabled();
  });

  it('calls onGenerated with content on successful API response', async () => {
    const onGenerated = vi.fn();
    vi.mocked(api.post).mockResolvedValueOnce({
      success: true,
      data: { content: 'Generated description here' },
    });

    render(
      <AiAssistButton
        type="listing"
        title="Gardening services"
        onGenerated={onGenerated}
      />
    );

    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => {
      expect(onGenerated).toHaveBeenCalledWith('Generated description here');
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('shows error toast on failed API response', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({
      success: false,
      data: null,
    });

    render(
      <AiAssistButton
        type="event"
        title="Community Workshop"
        onGenerated={vi.fn()}
      />
    );

    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('shows rate limit error toast on 429 status', async () => {
    vi.mocked(api.post).mockRejectedValueOnce({ status: 429 });

    render(
      <AiAssistButton
        type="listing"
        title="My listing"
        onGenerated={vi.fn()}
      />
    );

    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('shows unavailable error toast on 403 status', async () => {
    vi.mocked(api.post).mockRejectedValueOnce({ status: 403 });

    render(
      <AiAssistButton
        type="listing"
        title="My listing"
        onGenerated={vi.fn()}
      />
    );

    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('calls API with correct type in the URL', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({
      success: true,
      data: { content: 'Event description' },
    });

    render(
      <AiAssistButton
        type="event"
        title="Summer Gathering"
        onGenerated={vi.fn()}
      />
    );

    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith(
        '/ai/generate/event',
        expect.objectContaining({ title: 'Summer Gathering' })
      );
    });
  });

  it('passes context to API call when provided', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({
      success: true,
      data: { content: 'Content with context' },
    });

    render(
      <AiAssistButton
        type="listing"
        title="Teaching"
        context={{ category: 'education' }}
        onGenerated={vi.fn()}
      />
    );

    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith(
        '/ai/generate/listing',
        expect.objectContaining({ context: { category: 'education' } })
      );
    });
  });
});
