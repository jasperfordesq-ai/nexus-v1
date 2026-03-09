// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for GroupAnnouncementsTab
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (_key: string, fallback: string, _opts?: object) => fallback ?? _key,
  }),
}));

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn().mockResolvedValue({ success: true, data: [] }),
    post: vi.fn().mockResolvedValue({ success: true }),
    put: vi.fn().mockResolvedValue({ success: true }),
    delete: vi.fn().mockResolvedValue({ success: true }),
  },
}));

vi.mock('@/contexts', () => ({
  useToast: vi.fn(() => ({
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
    warning: vi.fn(),
  })),
}));

vi.mock('@/components/ui', () => ({
  GlassCard: ({ children, className }: { children: React.ReactNode; className?: string }) => (
    <div data-testid="glass-card" className={className}>{children}</div>
  ),
}));

vi.mock('@/components/feedback', () => ({
  EmptyState: ({ title, description }: { title: string; description?: string }) => (
    <div data-testid="empty-state">
      <div>{title}</div>
      {description && <div>{description}</div>}
    </div>
  ),
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

vi.mock('@/lib/helpers', () => ({
  formatRelativeTime: (date: string) => `relative-${date}`,
}));

import { GroupAnnouncementsTab } from '../GroupAnnouncementsTab';
import { api } from '@/lib/api';

const makeAnnouncement = (id = 1, isPinned = false) => ({
  id,
  title: `Announcement ${id}`,
  content: `Content for announcement ${id}`,
  is_pinned: isPinned,
  author: { id: 5, name: 'Admin User' },
  created_at: '2026-03-01T10:00:00Z',
});

describe('GroupAnnouncementsTab', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the Announcements heading', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
    render(<GroupAnnouncementsTab groupId={1} isAdmin={false} />);
    await waitFor(() => {
      expect(screen.getByText('Announcements')).toBeInTheDocument();
    });
  });

  it('shows empty state for non-admin when no announcements', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
    render(<GroupAnnouncementsTab groupId={1} isAdmin={false} />);
    await waitFor(() => {
      expect(screen.getByTestId('empty-state')).toBeInTheDocument();
      expect(screen.getByText('No announcements')).toBeInTheDocument();
    });
  });

  it('shows admin-specific empty state description when isAdmin is true', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
    render(<GroupAnnouncementsTab groupId={1} isAdmin={true} />);
    await waitFor(() => {
      expect(screen.getByText('Create an announcement to share with the group')).toBeInTheDocument();
    });
  });

  it('renders New Announcement button for admins', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
    render(<GroupAnnouncementsTab groupId={1} isAdmin={true} />);
    await waitFor(() => {
      expect(screen.getByRole('button', { name: /New Announcement/i })).toBeInTheDocument();
    });
  });

  it('does not render New Announcement button for non-admins', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
    render(<GroupAnnouncementsTab groupId={1} isAdmin={false} />);
    await waitFor(() => {
      expect(screen.queryByRole('button', { name: /New Announcement/i })).not.toBeInTheDocument();
    });
  });

  it('renders announcement titles and content', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: { announcements: [makeAnnouncement(1), makeAnnouncement(2)] },
    });
    render(<GroupAnnouncementsTab groupId={1} isAdmin={false} />);
    await waitFor(() => {
      expect(screen.getByText('Announcement 1')).toBeInTheDocument();
      expect(screen.getByText('Content for announcement 1')).toBeInTheDocument();
      expect(screen.getByText('Announcement 2')).toBeInTheDocument();
    });
  });

  it('shows Pinned chip for pinned announcements', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: { announcements: [makeAnnouncement(1, true)] },
    });
    render(<GroupAnnouncementsTab groupId={1} isAdmin={false} />);
    await waitFor(() => {
      expect(screen.getByText('Pinned')).toBeInTheDocument();
    });
  });

  it('calls POST when New Announcement form is submitted', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
    vi.mocked(api.post).mockResolvedValue({ success: true });
    render(<GroupAnnouncementsTab groupId={1} isAdmin={true} />);
    await waitFor(() => screen.getByRole('button', { name: /New Announcement/i }));

    fireEvent.click(screen.getByRole('button', { name: /New Announcement/i }));
    await waitFor(() => {
      expect(screen.getByText('New Announcement')).toBeInTheDocument();
    });

    // Fill in the form fields
    fireEvent.change(screen.getByPlaceholderText('Announcement title'), {
      target: { value: 'Test Title' },
    });
    fireEvent.change(screen.getByPlaceholderText('Write your announcement...'), {
      target: { value: 'Test Content' },
    });
    fireEvent.click(screen.getByRole('button', { name: /Post Announcement/i }));

    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith('/v2/groups/1/announcements', expect.objectContaining({
        title: 'Test Title',
        content: 'Test Content',
      }));
    });
  });
});
