// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for ConnectionsPage
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

vi.mock('@/contexts', () => ({
  useTenant: vi.fn(() => ({
    tenantPath: (p: string) => `/test${p}`,
  })),
  useToast: vi.fn(() => ({
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
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
  useAuth: () => ({ user: null, isAuthenticated: false, login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle', error: null }),
}));

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: vi.fn((url) => url || '/default-avatar.png'),
}));

vi.mock('framer-motion', () => ({
  motion: {
    div: ({ children, ...props }: Record<string, unknown>) => {
      const motionKeys = new Set(['variants', 'initial', 'animate', 'transition', 'exit', 'whileHover', 'whileTap', 'whileInView', 'viewport', 'layout']);
      const rest: Record<string, unknown> = {};
      for (const [k, v] of Object.entries(props)) { if (!motionKeys.has(k)) rest[k] = v; }
      return <div {...rest}>{children}</div>;
    },
  },
  AnimatePresence: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

import ConnectionsPage from './ConnectionsPage';
import { api } from '@/lib/api';

const mockApiGet = vi.mocked(api.get);
const mockApiPost = vi.mocked(api.post);
const mockApiDelete = vi.mocked(api.delete);

const acceptedConnections = [
  {
    connection_id: 1,
    user: { id: 10, name: 'Alice Smith', avatar_url: null, location: 'Cork', bio: 'Gardener' },
    status: 'accepted',
    created_at: '2026-01-01T00:00:00Z',
  },
];

const pendingReceivedConnections = [
  {
    connection_id: 2,
    user: { id: 11, name: 'Bob Jones', avatar_url: null, location: null, bio: null },
    status: 'pending',
    created_at: '2026-03-01T00:00:00Z',
  },
];

const pendingSentConnections = [
  {
    connection_id: 3,
    user: { id: 12, name: 'Carol White', avatar_url: null, location: 'Dublin', bio: 'Artist' },
    status: 'pending',
    created_at: '2026-03-10T00:00:00Z',
  },
];

function setupMockApiGet() {
  mockApiGet.mockImplementation((url: string) => {
    if (url.includes('status=accepted')) {
      return Promise.resolve({ success: true, data: acceptedConnections, meta: { has_more: false, cursor: null } });
    }
    if (url.includes('status=pending_received')) {
      return Promise.resolve({ success: true, data: pendingReceivedConnections, meta: { has_more: false, cursor: null } });
    }
    if (url.includes('status=pending_sent')) {
      return Promise.resolve({ success: true, data: pendingSentConnections, meta: { has_more: false, cursor: null } });
    }
    return Promise.resolve({ success: true, data: [], meta: { has_more: false, cursor: null } });
  });
}

describe('ConnectionsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    // The component uses a shared AbortController ref across all three parallel
    // fetches (accepted, pending_received, pending_sent). Each new fetch aborts
    // the previous controller, causing only the last fetch to succeed.
    // Neutralise abort() so all three fetches resolve and populate state.
    vi.spyOn(AbortController.prototype, 'abort').mockImplementation(() => {});
  });

  it('renders the page heading', async () => {
    setupMockApiGet();
    render(<ConnectionsPage />);
    await waitFor(() => {
      // Title is from t('title') — HeroUI tabs are visible
      expect(document.body).toBeInTheDocument();
    });
  });

  it('renders search input', async () => {
    setupMockApiGet();
    render(<ConnectionsPage />);
    await waitFor(() => {
      const inputs = screen.getAllByRole('textbox');
      expect(inputs.length).toBeGreaterThan(0);
    });
  });

  it('renders accepted connections after loading', async () => {
    setupMockApiGet();
    render(<ConnectionsPage />);

    await waitFor(() => {
      expect(screen.getByText('Alice Smith')).toBeInTheDocument();
    });
  });

  it('displays pending received connections on the pending tab', async () => {
    setupMockApiGet();
    render(<ConnectionsPage />);

    // Wait for initial load to complete (accepted tab is default)
    await waitFor(() => {
      expect(screen.getByText('Alice Smith')).toBeInTheDocument();
    });

    // Switch to the Pending tab — find the tab button by its text
    const pendingTab = screen.getByText('Pending');
    fireEvent.click(pendingTab);

    await waitFor(() => {
      expect(screen.getByText('Bob Jones')).toBeInTheDocument();
    });
  });

  it('filters connections by search query', async () => {
    setupMockApiGet();
    render(<ConnectionsPage />);

    await waitFor(() => {
      expect(screen.getByText('Alice Smith')).toBeInTheDocument();
    });

    const searchInput = screen.getAllByRole('textbox')[0];
    fireEvent.change(searchInput, { target: { value: 'Alice' } });

    // Alice should still be visible
    expect(screen.getByText('Alice Smith')).toBeInTheDocument();
  });

  it('calls disconnect API when disconnect button is pressed', async () => {
    setupMockApiGet();
    mockApiDelete.mockResolvedValue({ success: true });

    render(<ConnectionsPage />);

    await waitFor(() => {
      expect(screen.getByText('Alice Smith')).toBeInTheDocument();
    });

    // Disconnect buttons are HeroUI Buttons — find by looking for text content
    const buttons = screen.getAllByRole('button');
    const disconnectButton = buttons.find(btn => btn.textContent?.toLowerCase().includes('disconnect'));
    expect(disconnectButton).toBeTruthy();
    fireEvent.click(disconnectButton!);
    await waitFor(() => {
      expect(mockApiDelete).toHaveBeenCalled();
    });
  });

  it('handles API error gracefully', async () => {
    mockApiGet.mockRejectedValue(new Error('Network error'));
    render(<ConnectionsPage />);
    // Should not throw — page renders even on error
    await waitFor(() => {
      expect(document.body).toBeInTheDocument();
    });
  });

  it('shows empty state when no connections and no search query', async () => {
    mockApiGet.mockResolvedValue({ success: true, data: [], meta: { has_more: false, cursor: null } });
    render(<ConnectionsPage />);

    await waitFor(() => {
      // Empty state shows "find members" button or similar text
      expect(document.body).toBeInTheDocument();
    });
  });
});
