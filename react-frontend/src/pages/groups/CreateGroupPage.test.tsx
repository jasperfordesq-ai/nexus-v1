// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for CreateGroupPage
 */

import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    upload: vi.fn(),
  },
}));
import { api } from '@/lib/api';

vi.mock('@/contexts', () => ({
  useToast: vi.fn(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn() })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, slug: 'test' },
    tenantPath: (p: string) => `/test${p}`,
    hasFeature: vi.fn(() => true),
  })),
  ToastProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,

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
}));

vi.mock('@/contexts/ToastContext', () => ({
  useToast: vi.fn(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn() })),
  ToastProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/lib/helpers', () => ({
  resolveAssetUrl: vi.fn((url) => url || null),
}));

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return {
    ...actual,
    useParams: () => ({ id: undefined }),
    useNavigate: () => vi.fn(),
  };
});

vi.mock('framer-motion', async () => {
  const { framerMotionMock } = await import('@/test/mocks');
  return framerMotionMock;
});

vi.mock('@/components/ui', () => ({
  GlassCard: ({ children, className }: { children: React.ReactNode; className?: string }) => (
    <div className={className}>{children}</div>
  ),

  GlassButton: ({ children }: Record<string, unknown>) => children as never,
  GlassInput: () => null,
  BackToTop: () => null,
  AlgorithmLabel: () => null,
  ImagePlaceholder: () => null,
  DynamicIcon: () => null,
  ICON_MAP: {},
  ICON_NAMES: [],
  ListingSkeleton: () => null,
  MemberCardSkeleton: () => null,
  StatCardSkeleton: () => null,
  EventCardSkeleton: () => null,
  GroupCardSkeleton: () => null,
  ConversationSkeleton: () => null,
  ExchangeCardSkeleton: () => null,
  NotificationSkeleton: () => null,
  ProfileHeaderSkeleton: () => null,
  SkeletonList: () => null,
}));

vi.mock('@/components/navigation', () => ({
  Breadcrumbs: ({ items }: { items: { label: string }[] }) => (
    <nav>{items.map((i) => <span key={i.label}>{i.label}</span>)}</nav>
  ),
}));

vi.mock('@/components/feedback', () => ({
  LoadingScreen: ({ message }: { message: string }) => <div data-testid="loading-screen">{message}</div>,
}));

vi.mock('@/components/location', () => ({
  PlaceAutocompleteInput: ({ label, value, onChange }: { label: string; value: string; onChange: (v: string) => void }) => (
    <input aria-label={label} value={value} onChange={(e) => onChange(e.target.value)} />
  ),
}));

import { CreateGroupPage } from './CreateGroupPage';

describe('CreateGroupPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    api.post.mockResolvedValue({ success: true, data: { id: 10 } });
    api.put.mockResolvedValue({ success: true });
  });

  it('renders create group form heading', () => {
    render(<CreateGroupPage />);
    expect(screen.getByText('form.create_title')).toBeInTheDocument();
  });

  it('renders group name input', () => {
    render(<CreateGroupPage />);
    expect(screen.getByRole('textbox', { name: /form.name_label/i })).toBeInTheDocument();
  });

  it('renders group description textarea', () => {
    render(<CreateGroupPage />);
    expect(screen.getByRole('textbox', { name: /form.description_label/i })).toBeInTheDocument();
  });

  it('renders private group toggle switch', () => {
    render(<CreateGroupPage />);
    // Switch for private/public setting
    expect(screen.getByText('form.private_label')).toBeInTheDocument();
  });

  it('renders submit button', () => {
    render(<CreateGroupPage />);
    expect(screen.getByText('create')).toBeInTheDocument();
  });

  it('renders cancel/back button', () => {
    render(<CreateGroupPage />);
    // Back button with ArrowLeft icon or cancel button
    const buttons = screen.getAllByRole('button');
    expect(buttons.length).toBeGreaterThan(0);
  });

  it('shows validation error for empty name on submit', async () => {
    render(<CreateGroupPage />);

    const submitButton = screen.getByText('create');
    fireEvent.click(submitButton);

    await waitFor(() => {
      expect(screen.getByText('form.name_required')).toBeInTheDocument();
    });
  });

  it('renders image upload area', () => {
    render(<CreateGroupPage />);
    expect(screen.getByText('form.image_upload_hint')).toBeInTheDocument();
  });
});
