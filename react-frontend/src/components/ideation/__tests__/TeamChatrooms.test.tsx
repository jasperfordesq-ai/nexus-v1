// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for TeamChatrooms component
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { api } from '@/lib/api';

// jsdom does not implement scrollIntoView
Element.prototype.scrollIntoView = vi.fn();

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
};

vi.mock('@/contexts', () => ({
  useToast: vi.fn(() => mockToast),
  useAuth: vi.fn(() => ({
    isAuthenticated: true,
    user: { id: 1, name: 'Test User', role: 'user' },
    login: vi.fn(),
    logout: vi.fn(),
    register: vi.fn(),
    updateUser: vi.fn(),
    refreshUser: vi.fn(),
    status: 'idle',
    error: null,
  })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    branding: { name: 'Test', logo_url: null },
    tenantSlug: 'test',
    tenantPath: (p: string) => '/test' + p,
    isLoading: false,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
  })),
  useTheme: () => ({ resolvedTheme: 'light', toggleTheme: vi.fn(), theme: 'system', setTheme: vi.fn() }),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: vi.fn((url: string | null) => url || '/default-avatar.png'),
  resolveAssetUrl: vi.fn((url: string) => url),
  formatRelativeTime: vi.fn(() => '5 min ago'),
}));

import { TeamChatrooms } from '../TeamChatrooms';

const mockChatrooms = [
  {
    id: 1,
    group_id: 10,
    name: 'general',
    description: 'General chat',
    messages_count: 5,
    created_at: '2026-01-01T00:00:00Z',
  },
  {
    id: 2,
    group_id: 10,
    name: 'random',
    description: null,
    messages_count: 0,
    created_at: '2026-01-02T00:00:00Z',
  },
];

const mockMessages = [
  {
    id: 100,
    chatroom_id: 1,
    user_id: 1,
    body: 'Hello world',
    created_at: '2026-01-01T12:00:00Z',
    author: { id: 1, name: 'Alice', avatar_url: null },
  },
  {
    id: 101,
    chatroom_id: 1,
    user_id: 2,
    body: 'Hi Alice!',
    created_at: '2026-01-01T12:01:00Z',
    author: { id: 2, name: 'Bob', avatar_url: null },
  },
];

describe('TeamChatrooms', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders loading spinner initially', () => {
    vi.mocked(api.get).mockImplementation(() => new Promise(() => {}));

    render(<TeamChatrooms groupId={10} isGroupAdmin={false} />);
    // HeroUI Spinner renders with aria-label="Loading"
    expect(screen.getByLabelText('Loading')).toBeInTheDocument();
  });

  it('renders messages after loading', async () => {
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url.includes('/chatrooms') && !url.includes('/messages')) {
        return Promise.resolve({ success: true, data: mockChatrooms });
      }
      if (url.includes('/messages')) {
        return Promise.resolve({ success: true, data: mockMessages });
      }
      return Promise.resolve({ success: true, data: [] });
    });

    render(<TeamChatrooms groupId={10} isGroupAdmin={false} />);

    await waitFor(() => {
      expect(screen.getByText('Hello world')).toBeInTheDocument();
      expect(screen.getByText('Hi Alice!')).toBeInTheDocument();
    });

    // Author names
    expect(screen.getByText('Alice')).toBeInTheDocument();
    expect(screen.getByText('Bob')).toBeInTheDocument();
  });

  it('renders empty state when no chatrooms', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });

    render(<TeamChatrooms groupId={10} isGroupAdmin={false} />);

    await waitFor(() => {
      // Both sidebar and main area show the translated empty_title
      const emptyTexts = screen.getAllByText('No Messages Yet');
      expect(emptyTexts.length).toBeGreaterThanOrEqual(1);
    });
  });

  it('shows create channel button for admins', async () => {
    vi.mocked(api.get)
      .mockResolvedValueOnce({ success: true, data: mockChatrooms })
      .mockResolvedValueOnce({ success: true, data: mockMessages });

    render(<TeamChatrooms groupId={10} isGroupAdmin={true} />);

    await waitFor(() => {
      // Create Channel button has aria-label from t('chatrooms.create')
      expect(screen.getByLabelText('Create Channel')).toBeInTheDocument();
    });
  });

  it('shows message input when authenticated', async () => {
    vi.mocked(api.get)
      .mockResolvedValueOnce({ success: true, data: mockChatrooms })
      .mockResolvedValueOnce({ success: true, data: mockMessages });

    render(<TeamChatrooms groupId={10} isGroupAdmin={false} />);

    await waitFor(() => {
      expect(screen.getByLabelText('Send')).toBeInTheDocument();
    });
  });

  it('shows delete channel button for admin', async () => {
    vi.mocked(api.get)
      .mockResolvedValueOnce({ success: true, data: mockChatrooms })
      .mockResolvedValueOnce({ success: true, data: mockMessages });

    render(<TeamChatrooms groupId={10} isGroupAdmin={true} />);

    await waitFor(() => {
      expect(screen.getByLabelText('Delete Channel')).toBeInTheDocument();
    });
  });

  it('fetches chatrooms on mount', async () => {
    vi.mocked(api.get)
      .mockResolvedValueOnce({ success: true, data: mockChatrooms })
      .mockResolvedValueOnce({ success: true, data: mockMessages });

    render(<TeamChatrooms groupId={10} isGroupAdmin={false} />);

    await waitFor(() => {
      expect(api.get).toHaveBeenCalledWith('/v2/groups/10/chatrooms');
    });
  });

  it('fetches messages for the first chatroom', async () => {
    vi.mocked(api.get)
      .mockResolvedValueOnce({ success: true, data: mockChatrooms })
      .mockResolvedValueOnce({ success: true, data: mockMessages });

    render(<TeamChatrooms groupId={10} isGroupAdmin={false} />);

    await waitFor(() => {
      expect(api.get).toHaveBeenCalledWith('/v2/group-chatrooms/1/messages');
    });
  });

  it('renders Channels heading', async () => {
    vi.mocked(api.get)
      .mockResolvedValueOnce({ success: true, data: mockChatrooms })
      .mockResolvedValueOnce({ success: true, data: mockMessages });

    render(<TeamChatrooms groupId={10} isGroupAdmin={false} />);

    await waitFor(() => {
      expect(screen.getByText('Channels')).toBeInTheDocument();
    });
  });
});
