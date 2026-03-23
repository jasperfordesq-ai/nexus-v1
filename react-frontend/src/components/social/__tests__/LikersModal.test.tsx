// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for LikersModal component.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { HeroUIProvider } from '@heroui/react';

// ─── Mocks ──────────────────────────────────────────────────────────────────

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, fallback?: string) => fallback ?? key,
    i18n: { language: 'en', changeLanguage: vi.fn() },
  }),
  initReactI18next: { type: '3rdParty', init: () => {} },
}));

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({ user: { id: 1 }, isAuthenticated: true })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test', slug: 'test' },
    tenantSlug: 'test',
    branding: { name: 'Test' },
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
    tenantPath: (p: string) => `/test${p}`,
  })),
  useToast: vi.fn(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn() })),
  useTheme: vi.fn(() => ({ resolvedTheme: 'light', theme: 'light', setTheme: vi.fn() })),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
}));

vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: (url: string | null | undefined) => url || '/default-avatar.png',
  formatRelativeTime: vi.fn(() => '2 hours ago'),
}));

import { LikersModal } from '../LikersModal';

function W({ children }: { children: React.ReactNode }) {
  return (
    <HeroUIProvider>
      <MemoryRouter initialEntries={['/test']}>
        {children}
      </MemoryRouter>
    </HeroUIProvider>
  );
}

const mockLikers = [
  { id: 1, name: 'Alice', avatar_url: null, liked_at: '2026-01-01T00:00:00Z' },
  { id: 2, name: 'Bob', avatar_url: null, liked_at: '2026-01-02T00:00:00Z' },
];

describe('LikersModal', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders when isOpen is true', async () => {
    const loadLikers = vi.fn().mockResolvedValue({
      likers: mockLikers,
      total_count: 2,
      has_more: false,
    });

    render(
      <W>
        <LikersModal
          isOpen={true}
          onClose={vi.fn()}
          loadLikers={loadLikers}
          likesCount={2}
        />
      </W>,
    );

    expect(screen.getByRole('dialog')).toBeInTheDocument();
  });

  it('does not render when isOpen is false', () => {
    const loadLikers = vi.fn().mockResolvedValue({
      likers: [],
      total_count: 0,
      has_more: false,
    });

    render(
      <W>
        <LikersModal
          isOpen={false}
          onClose={vi.fn()}
          loadLikers={loadLikers}
          likesCount={0}
        />
      </W>,
    );

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('displays "Liked by" header', async () => {
    const loadLikers = vi.fn().mockResolvedValue({
      likers: mockLikers,
      total_count: 2,
      has_more: false,
    });

    render(
      <W>
        <LikersModal
          isOpen={true}
          onClose={vi.fn()}
          loadLikers={loadLikers}
          likesCount={2}
        />
      </W>,
    );

    expect(screen.getByText(/Liked by/)).toBeInTheDocument();
  });

  it('shows likers after loading', async () => {
    const loadLikers = vi.fn().mockResolvedValue({
      likers: mockLikers,
      total_count: 2,
      has_more: false,
    });

    render(
      <W>
        <LikersModal
          isOpen={true}
          onClose={vi.fn()}
          loadLikers={loadLikers}
          likesCount={2}
        />
      </W>,
    );

    await waitFor(() => {
      expect(screen.getByText('Alice')).toBeInTheDocument();
      expect(screen.getByText('Bob')).toBeInTheDocument();
    });
  });

  it('shows "No likes yet" when list is empty', async () => {
    const loadLikers = vi.fn().mockResolvedValue({
      likers: [],
      total_count: 0,
      has_more: false,
    });

    render(
      <W>
        <LikersModal
          isOpen={true}
          onClose={vi.fn()}
          loadLikers={loadLikers}
          likesCount={0}
        />
      </W>,
    );

    await waitFor(() => {
      expect(screen.getByText('No likes yet')).toBeInTheDocument();
    });
  });

  it('shows Load More button when has_more is true', async () => {
    const loadLikers = vi.fn().mockResolvedValue({
      likers: mockLikers,
      total_count: 10,
      has_more: true,
    });

    render(
      <W>
        <LikersModal
          isOpen={true}
          onClose={vi.fn()}
          loadLikers={loadLikers}
          likesCount={10}
        />
      </W>,
    );

    await waitFor(() => {
      expect(screen.getByText('Load More')).toBeInTheDocument();
    });
  });

  it('calls loadLikers when opened', async () => {
    const loadLikers = vi.fn().mockResolvedValue({
      likers: [],
      total_count: 0,
      has_more: false,
    });

    render(
      <W>
        <LikersModal
          isOpen={true}
          onClose={vi.fn()}
          loadLikers={loadLikers}
          likesCount={0}
        />
      </W>,
    );

    await waitFor(() => {
      expect(loadLikers).toHaveBeenCalledWith(1);
    });
  });
});
