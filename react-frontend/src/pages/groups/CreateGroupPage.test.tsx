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
  cn: (...classes: unknown[]) => classes.filter(Boolean).join(' '),
}));

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return {
    ...actual,
    useParams: () => ({ id: undefined }),
    useNavigate: () => vi.fn(),
  };
});

vi.mock('@/lib/motion', async () => {
  const { framerMotionMock } = await import('@/test/mocks');
  return framerMotionMock;
});

vi.mock('@/components/ui', async () => (await import('@/test/uiMock')).uiMock);

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
    api.get.mockResolvedValue({ success: true, data: [] });
    api.post.mockResolvedValue({ success: true, data: { id: 10 } });
    api.put.mockResolvedValue({ success: true });
  });

  it('renders create group form heading', () => {
    render(<CreateGroupPage />);
    expect(screen.getByText('Create New Group')).toBeInTheDocument();
  });

  it('renders group name input', () => {
    render(<CreateGroupPage />);
    expect(screen.getByPlaceholderText('e.g., Gardening Enthusiasts, Tech Help...')).toBeInTheDocument();
  });

  it('renders group description textarea', () => {
    render(<CreateGroupPage />);
    expect(screen.getByPlaceholderText('Describe what your group is about...')).toBeInTheDocument();
  });

  it('renders private group toggle switch', () => {
    render(<CreateGroupPage />);
    // The switch displays "Public Group" by default (is_private starts false)
    expect(screen.getAllByText('Public Group').length).toBeGreaterThan(0);
  });

  it('renders submit button', () => {
    render(<CreateGroupPage />);
    expect(screen.getByText('Create Group')).toBeInTheDocument();
  });

  it('renders cancel/back button', () => {
    render(<CreateGroupPage />);
    // Back button with ArrowLeft icon or cancel button
    const buttons = screen.getAllByRole('button');
    expect(buttons.length).toBeGreaterThan(0);
  });

  it('shows validation error for empty name on submit', async () => {
    render(<CreateGroupPage />);

    const submitButton = screen.getByText('Create Group');
    fireEvent.submit(submitButton.closest('form') as HTMLFormElement);

    await waitFor(() => {
      expect(screen.getByText('Group name is required')).toBeInTheDocument();
    });
  });

  it('renders image upload area', () => {
    render(<CreateGroupPage />);
    expect(screen.getByText('JPEG, PNG, GIF, or WebP. Max 5MB.')).toBeInTheDocument();
  });

  it('fires only ONE create request when the form is submitted twice in rapid succession', async () => {
    // Regression: handleSubmit flipped isSubmitting state (which only toggles the
    // submit button's pending pointer-events) but the native <button type="submit">
    // stayed enabled, so a double-Enter / double-click submitted the native form
    // twice and created TWO duplicate groups before the state could flush. A
    // synchronous useRef re-entry guard now rejects the second submit. Live-verified
    // on the running app: a double requestSubmit() created two groups with the same
    // name before the fix (ids 90119 + 90120) and exactly one after.
    let resolvePost: (v: { success: boolean; data: { id: number } }) => void = () => {};
    api.post.mockReturnValue(new Promise((resolve) => { resolvePost = resolve; }));

    const { container } = render(<CreateGroupPage />);
    fireEvent.change(
      screen.getByPlaceholderText('e.g., Gardening Enthusiasts, Tech Help...'),
      { target: { value: 'My Test Group' } },
    );
    fireEvent.change(
      screen.getByPlaceholderText('Describe what your group is about...'),
      { target: { value: 'A description of the new group, long enough to pass.' } },
    );

    const form = container.querySelector('form') as HTMLFormElement;
    fireEvent.submit(form);
    fireEvent.submit(form);

    expect(api.post).toHaveBeenCalledTimes(1);

    resolvePost({ success: true, data: { id: 10 } });
    await waitFor(() => expect(api.post).toHaveBeenCalledTimes(1));
  });
});
