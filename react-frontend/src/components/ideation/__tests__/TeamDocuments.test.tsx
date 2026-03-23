// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for TeamDocuments component
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { api } from '@/lib/api';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
    upload: vi.fn(),
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
  resolveAssetUrl: vi.fn((url: string) => url || '/file'),
  formatRelativeTime: vi.fn(() => '2 days ago'),
}));

import { TeamDocuments } from '../TeamDocuments';

const mockDocuments = [
  {
    id: 1,
    group_id: 10,
    user_id: 1,
    filename: 'doc1.pdf',
    original_name: 'Project Plan.pdf',
    mime_type: 'application/pdf',
    size: 1024000,
    url: '/uploads/doc1.pdf',
    created_at: '2026-01-01T00:00:00Z',
    uploader: { id: 1, name: 'Alice', avatar_url: null },
  },
  {
    id: 2,
    group_id: 10,
    user_id: 2,
    filename: 'img1.png',
    original_name: 'Screenshot.png',
    mime_type: 'image/png',
    size: 512000,
    url: '/uploads/img1.png',
    created_at: '2026-01-02T00:00:00Z',
    uploader: { id: 2, name: 'Bob', avatar_url: '/bob.jpg' },
  },
];

describe('TeamDocuments', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders loading spinner initially', () => {
    vi.mocked(api.get).mockImplementation(() => new Promise(() => {}));

    render(<TeamDocuments groupId={10} isGroupAdmin={false} />);
    expect(screen.getByLabelText('Loading')).toBeInTheDocument();
  });

  it('renders document list after loading', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: mockDocuments });

    render(<TeamDocuments groupId={10} isGroupAdmin={false} />);

    await waitFor(() => {
      expect(screen.getByText('Project Plan.pdf')).toBeInTheDocument();
      expect(screen.getByText('Screenshot.png')).toBeInTheDocument();
    });
  });

  it('renders empty state when no documents', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: [] });

    render(<TeamDocuments groupId={10} isGroupAdmin={false} />);

    await waitFor(() => {
      expect(screen.getByText('No Documents Yet')).toBeInTheDocument();
    });
  });

  it('renders document title and upload button', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: mockDocuments });

    render(<TeamDocuments groupId={10} isGroupAdmin={false} />);

    await waitFor(() => {
      expect(screen.getByText('Documents')).toBeInTheDocument();
      expect(screen.getByText('Upload Document')).toBeInTheDocument();
    });
  });

  it('shows max size notice', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: [] });

    render(<TeamDocuments groupId={10} isGroupAdmin={false} />);

    await waitFor(() => {
      expect(screen.getByText('Max file size: 10 MB')).toBeInTheDocument();
    });
  });

  it('shows file extension chips', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: mockDocuments });

    render(<TeamDocuments groupId={10} isGroupAdmin={false} />);

    await waitFor(() => {
      expect(screen.getByText('PDF')).toBeInTheDocument();
      expect(screen.getByText('PNG')).toBeInTheDocument();
    });
  });

  it('shows uploader names', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: mockDocuments });

    render(<TeamDocuments groupId={10} isGroupAdmin={false} />);

    await waitFor(() => {
      expect(screen.getByText('Alice')).toBeInTheDocument();
      expect(screen.getByText('Bob')).toBeInTheDocument();
    });
  });

  it('shows download buttons for each document', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: mockDocuments });

    render(<TeamDocuments groupId={10} isGroupAdmin={false} />);

    await waitFor(() => {
      const downloadBtns = screen.getAllByLabelText('Download');
      expect(downloadBtns).toHaveLength(2);
    });
  });

  it('shows delete buttons for own documents', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: mockDocuments });

    render(<TeamDocuments groupId={10} isGroupAdmin={false} />);

    await waitFor(() => {
      // User id=1 owns doc id=1. aria-label is t('comments.delete') = "Delete"
      const deleteBtns = screen.getAllByLabelText('Delete');
      expect(deleteBtns.length).toBeGreaterThanOrEqual(1);
    });
  });
});
