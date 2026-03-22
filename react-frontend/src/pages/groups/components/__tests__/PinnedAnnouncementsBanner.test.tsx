// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for PinnedAnnouncementsBanner
 */

import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
  },
}));
import { api } from '@/lib/api';

import { PinnedAnnouncementsBanner } from '../PinnedAnnouncementsBanner';

const mockPinnedAnnouncements = [
  {
    id: 1,
    title: 'Important Community Update',
    content: 'Please read this important message about upcoming changes.',
    author: { name: 'Alice Admin' },
    created_at: '2026-01-01T10:00:00Z',
    is_pinned: true,
  },
  {
    id: 2,
    title: 'Event This Weekend',
    content: 'Join us this Saturday for the big event.',
    author: { name: 'Bob Moderator' },
    created_at: '2026-01-02T10:00:00Z',
    is_pinned: true,
  },
];

describe('PinnedAnnouncementsBanner', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders nothing while loading (before loaded)', () => {
    api.get.mockImplementation(() => new Promise(() => {})); // never resolves
    render(<PinnedAnnouncementsBanner groupId={1} />);
    expect(screen.queryByText('Important Community Update')).not.toBeInTheDocument();
    expect(screen.queryByText('Pinned')).not.toBeInTheDocument();
  });

  it('renders nothing when API returns empty array', async () => {
    api.get.mockResolvedValue({ success: true, data: [] });
    render(<PinnedAnnouncementsBanner groupId={1} />);
    await waitFor(() => {
      expect(screen.queryByText('Important Community Update')).not.toBeInTheDocument();
    });
  });

  it('renders pinned announcement titles', async () => {
    api.get.mockResolvedValue({ success: true, data: mockPinnedAnnouncements });
    render(<PinnedAnnouncementsBanner groupId={1} />);
    await waitFor(() => {
      expect(screen.getByText('Important Community Update')).toBeInTheDocument();
    });
  });

  it('renders multiple pinned announcements', async () => {
    api.get.mockResolvedValue({ success: true, data: mockPinnedAnnouncements });
    render(<PinnedAnnouncementsBanner groupId={1} />);
    await waitFor(() => {
      expect(screen.getByText('Important Community Update')).toBeInTheDocument();
      expect(screen.getByText('Event This Weekend')).toBeInTheDocument();
    });
  });

  it('renders announcement content', async () => {
    api.get.mockResolvedValue({ success: true, data: mockPinnedAnnouncements });
    render(<PinnedAnnouncementsBanner groupId={1} />);
    await waitFor(() => {
      expect(screen.getByText('Please read this important message about upcoming changes.')).toBeInTheDocument();
    });
  });

  it('renders Pinned chip for each announcement', async () => {
    api.get.mockResolvedValue({ success: true, data: mockPinnedAnnouncements });
    render(<PinnedAnnouncementsBanner groupId={1} />);
    await waitFor(() => {
      const pinnedChips = screen.getAllByText('Pinned');
      expect(pinnedChips).toHaveLength(2);
    });
  });

  it('silently fails when API throws error', async () => {
    api.get.mockRejectedValue(new Error('Network error'));
    render(<PinnedAnnouncementsBanner groupId={1} />);
    await waitFor(() => {
      // After load fails, loaded becomes true but pinned is empty — nothing rendered
      expect(screen.queryByText('Important Community Update')).not.toBeInTheDocument();
    });
  });

  it('calls API with correct group ID', async () => {
    api.get.mockResolvedValue({ success: true, data: [] });
    render(<PinnedAnnouncementsBanner groupId={7} />);
    await waitFor(() => {
      expect(api.get).toHaveBeenCalledWith('/v2/groups/7/announcements?pinned=1');
    });
  });

  it('handles announcements wrapped in object payload', async () => {
    api.get.mockResolvedValue({
      success: true,
      data: { announcements: mockPinnedAnnouncements },
    });
    render(<PinnedAnnouncementsBanner groupId={1} />);
    await waitFor(() => {
      expect(screen.getByText('Important Community Update')).toBeInTheDocument();
    });
  });
});
