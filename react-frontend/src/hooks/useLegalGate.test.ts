// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { renderHook, act, waitFor } from '@testing-library/react';
import { useLegalGate } from './useLegalGate';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
  },
}));

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(),

  useTheme: () => ({ resolvedTheme: 'light', toggleTheme: vi.fn(), theme: 'system', setTheme: vi.fn() }),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
  useToast: () => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }),
  useTenant: () => ({ tenant: { id: 2, name: 'Test', slug: 'test', tagline: null }, branding: { name: 'Test', logo_url: null }, tenantSlug: 'test', tenantPath: (p) => '/test' + p, isLoading: false, hasFeature: vi.fn(() => true), hasModule: vi.fn(() => true) }),
}));

import { api } from '@/lib/api';
import { useAuth } from '@/contexts';

const mockApi = api as unknown as { get: ReturnType<typeof vi.fn>; post: ReturnType<typeof vi.fn> };
const mockUseAuth = useAuth as ReturnType<typeof vi.fn>;

const pendingDoc = {
  document_id: 1,
  document_type: 'terms',
  title: 'Terms of Service',
  current_version_id: 2,
  current_version: '2.0',
  acceptance_status: 'not_accepted' as const,
  accepted_at: null,
};

describe('useLegalGate', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockUseAuth.mockReturnValue({ isAuthenticated: true, isLoading: false });
  });

  it('returns hasPending false while auth is loading', () => {
    mockUseAuth.mockReturnValue({ isAuthenticated: false, isLoading: true });
    const { result } = renderHook(() => useLegalGate());
    expect(result.current.hasPending).toBe(false);
    expect(result.current.isLoading).toBe(false);
  });

  it('returns hasPending false when unauthenticated', () => {
    mockUseAuth.mockReturnValue({ isAuthenticated: false, isLoading: false });
    const { result } = renderHook(() => useLegalGate());
    expect(result.current.hasPending).toBe(false);
    expect(result.current.pendingDocs).toHaveLength(0);
  });

  it('fetches legal status when authenticated', async () => {
    mockApi.get.mockResolvedValue({
      success: true,
      data: { has_pending: true, documents: [pendingDoc] },
    });

    const { result } = renderHook(() => useLegalGate());

    await waitFor(() => expect(result.current.isLoading).toBe(false));

    expect(result.current.hasPending).toBe(true);
    expect(result.current.pendingDocs).toHaveLength(1);
    expect(result.current.pendingDocs[0].title).toBe('Terms of Service');
  });

  it('filters out documents with current acceptance status', async () => {
    const currentDoc = { ...pendingDoc, acceptance_status: 'current' as const };
    mockApi.get.mockResolvedValue({
      success: true,
      data: { has_pending: false, documents: [currentDoc] },
    });

    const { result } = renderHook(() => useLegalGate());
    await waitFor(() => expect(result.current.isLoading).toBe(false));

    expect(result.current.pendingDocs).toHaveLength(0);
  });

  it('handles API failure gracefully — does not block app', async () => {
    mockApi.get.mockRejectedValue(new Error('Network error'));

    const { result } = renderHook(() => useLegalGate());
    await waitFor(() => expect(result.current.isLoading).toBe(false));

    expect(result.current.hasPending).toBe(false);
    expect(result.current.pendingDocs).toHaveLength(0);
  });

  it('handles API returning success false', async () => {
    mockApi.get.mockResolvedValue({ success: false, error: 'Server error' });

    const { result } = renderHook(() => useLegalGate());
    await waitFor(() => expect(result.current.isLoading).toBe(false));

    expect(result.current.hasPending).toBe(false);
  });

  it('acceptAll calls post and clears pending state on success', async () => {
    mockApi.get.mockResolvedValue({
      success: true,
      data: { has_pending: true, documents: [pendingDoc] },
    });
    mockApi.post.mockResolvedValue({ success: true });

    const { result } = renderHook(() => useLegalGate());
    await waitFor(() => expect(result.current.isLoading).toBe(false));

    await act(async () => {
      await result.current.acceptAll();
    });

    expect(mockApi.post).toHaveBeenCalledWith('/v2/legal/acceptance/accept-all', {});
    expect(result.current.hasPending).toBe(false);
    expect(result.current.pendingDocs).toHaveLength(0);
  });

  it('acceptAll sets isAccepting true while posting', async () => {
    mockApi.get.mockResolvedValue({
      success: true,
      data: { has_pending: false, documents: [] },
    });
    let resolvePost: (value: unknown) => void;
    mockApi.post.mockReturnValue(new Promise((r) => { resolvePost = r; }));

    const { result } = renderHook(() => useLegalGate());
    await waitFor(() => expect(result.current.isLoading).toBe(false));

    act(() => {
      void result.current.acceptAll();
    });

    expect(result.current.isAccepting).toBe(true);

    await act(async () => {
      resolvePost!({ success: true });
    });

    expect(result.current.isAccepting).toBe(false);
  });

  it('refresh triggers a re-fetch', async () => {
    mockApi.get.mockResolvedValue({
      success: true,
      data: { has_pending: false, documents: [] },
    });

    const { result } = renderHook(() => useLegalGate());
    await waitFor(() => expect(result.current.isLoading).toBe(false));

    expect(mockApi.get).toHaveBeenCalledTimes(1);

    act(() => {
      result.current.refresh();
    });

    await waitFor(() => expect(mockApi.get).toHaveBeenCalledTimes(2));
  });
});
