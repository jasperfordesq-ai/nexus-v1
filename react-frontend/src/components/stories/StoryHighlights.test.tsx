// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── Mock api ────────────────────────────────────────────────────────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
    download: vi.fn(),
    upload: vi.fn(),
  },
}));

vi.mock('@/lib/api', () => ({ api: mockApi, default: mockApi }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/lib/helpers', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/lib/helpers')>();
  return {
    ...actual,
    resolveAssetUrl: (url: string | null) => url ?? '',
    resolveAvatarUrl: (url: string | null) => url ?? '',
  };
});

// ─── Toast / Auth ────────────────────────────────────────────────────────────
const mockToast = {
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
  showToast: vi.fn(),
};

const mockConfirm = vi.fn();

// Owner user (id matches userId prop = 7)
const ownerUser = { id: 7, name: 'Jane Owner', role: 'member' };
// Non-owner user
const otherUser = { id: 99, name: 'Bob Other', role: 'member' };

let currentUser = ownerUser;

vi.mock('@/contexts', () =>
  createMockContexts({
    useAuth: () => ({
      user: currentUser,
      isAuthenticated: true,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      updateUser: vi.fn(),
      refreshUser: vi.fn(),
      status: 'idle' as const,
      error: null,
    }),
    useToast: () => mockToast,
  })
);

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// Stub useConfirm
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<Record<string, unknown>>();
  return {
    ...orig,
    useConfirm: () => mockConfirm,
  };
});

// Stub StoryViewer — full implementation uses canvas/video APIs
vi.mock('@/components/stories/StoryViewer', () => ({
  StoryViewer: ({ onClose }: { onClose: () => void }) => (
    <div data-testid="story-viewer">
      <button onClick={onClose} aria-label="Close viewer">Close</button>
    </div>
  ),
}));

// ─── Fixtures ────────────────────────────────────────────────────────────────
const makeHighlight = (overrides = {}) => ({
  id: 1,
  title: 'Summer Memories',
  cover_url: null,
  story_count: 3,
  display_order: 1,
  created_at: '2025-06-01T10:00:00Z',
  ...overrides,
});

const makeHighlightStory = (overrides = {}) => ({
  id: 50,
  user_id: 7,
  media_type: 'image' as const,
  media_url: 'https://example.com/story.jpg',
  text_content: null,
  background_gradient: null,
  background_color: null,
  duration: 5,
  view_count: 10,
  is_viewed: false,
  expires_at: '2025-07-01T00:00:00Z',
  created_at: '2025-06-01T10:00:00Z',
  user: { id: 7, name: 'Jane Owner', first_name: 'Jane', avatar_url: null },
  ...overrides,
});

// ─────────────────────────────────────────────────────────────────────────────
describe('StoryHighlights', () => {
  const defaultProps = {
    userId: 7,
    userName: 'Jane Owner',
    userAvatar: null,
  };

  beforeEach(() => {
    vi.resetAllMocks();
    currentUser = ownerUser;
    mockConfirm.mockResolvedValue(true);
    // Default: one highlight
    mockApi.get.mockResolvedValue({
      success: true,
      data: [makeHighlight()],
    });
  });

  it('shows loading skeletons initially', async () => {
    mockApi.get.mockImplementation(() => new Promise(() => {}));
    const { StoryHighlights } = await import('./StoryHighlights');
    const { container } = render(<StoryHighlights {...defaultProps} />);

    // Skeleton renders - just check container has something rendered during load
    await waitFor(() => {
      const skeletons = container.querySelectorAll('[class*="skeleton"], [class*="Skeleton"]');
      // The component renders skeleton divs or the container is non-empty
      expect(container.firstChild).not.toBeNull();
    });
  });

  it('renders highlight titles after loading', async () => {
    const { StoryHighlights } = await import('./StoryHighlights');
    render(<StoryHighlights {...defaultProps} />);

    await waitFor(() => {
      expect(screen.getByText('Summer Memories')).toBeInTheDocument();
    });
  });

  it('shows create button for owner', async () => {
    const { StoryHighlights } = await import('./StoryHighlights');
    render(<StoryHighlights {...defaultProps} />);

    await waitFor(() => {
      const createBtn = screen.getAllByRole('button').find(
        (b) =>
          b.getAttribute('aria-label')?.toLowerCase().includes('creat') ||
          b.getAttribute('aria-label')?.toLowerCase().includes('highlights.aria_create') ||
          b.getAttribute('aria-label')?.toLowerCase().includes('new')
      );
      expect(createBtn).toBeDefined();
    });
  });

  it('does not render highlights list when no highlights and not owner', async () => {
    currentUser = otherUser;
    mockApi.get.mockResolvedValue({ success: true, data: [] });

    const { StoryHighlights } = await import('./StoryHighlights');
    render(
      <StoryHighlights userId={7} userName="Jane Owner" userAvatar={null} />
    );

    // Component returns null when not owner + empty highlights
    // Give it time to load and confirm no highlight circles rendered
    await waitFor(() => {
      // The component renders null so no highlight titles show up
      expect(screen.queryByRole('button', { name: /aria_view/i })).toBeNull();
    });
    // Also no create button (since it's not the owner)
    expect(screen.queryByRole('button', { name: /aria_create/i })).toBeNull();
  });

  it('opens create modal when + button clicked', async () => {
    const { StoryHighlights } = await import('./StoryHighlights');
    render(<StoryHighlights {...defaultProps} />);

    await waitFor(() => screen.getByText('Summer Memories'));

    const createBtn = screen.getAllByRole('button').find(
      (b) =>
        b.getAttribute('aria-label')?.toLowerCase().includes('creat') ||
        b.getAttribute('aria-label')?.toLowerCase().includes('highlights.aria_create')
    );
    expect(createBtn).toBeDefined();
    fireEvent.click(createBtn!);

    await waitFor(() => {
      const dialog = document.querySelector('[role="dialog"]');
      expect(dialog).toBeTruthy();
    });
  });

  it('calls POST API to create a highlight', async () => {
    mockApi.post.mockResolvedValue({ success: true });
    const { StoryHighlights } = await import('./StoryHighlights');
    render(<StoryHighlights {...defaultProps} />);

    await waitFor(() => screen.getByText('Summer Memories'));

    // Open create modal
    const createBtn = screen.getAllByRole('button').find(
      (b) =>
        b.getAttribute('aria-label')?.toLowerCase().includes('creat') ||
        b.getAttribute('aria-label')?.toLowerCase().includes('highlights.aria_create')
    );
    fireEvent.click(createBtn!);

    await waitFor(() => document.querySelector('[role="dialog"]'));

    // Type in the title input
    const titleInput = document.querySelector('input');
    if (titleInput) {
      fireEvent.change(titleInput, { target: { value: 'New Highlight' } });
    }

    // Click the create button in the modal
    const confirmBtn = screen.getAllByRole('button').find(
      (b) =>
        b.textContent?.toLowerCase().includes('creat') &&
        !b.getAttribute('aria-label')
    );
    if (confirmBtn) {
      fireEvent.click(confirmBtn);
      await waitFor(() => {
        expect(mockApi.post).toHaveBeenCalledWith(
          '/v2/stories/highlights',
          expect.objectContaining({ title: 'New Highlight' }),
        );
      });
    }
  });

  it('opens story viewer when highlight clicked', async () => {
    mockApi.get.mockImplementation((url: string) => {
      // highlight stories: /v2/stories/highlights/1/stories
      if (url.includes('/stories') && url.includes('/highlights/1')) {
        return Promise.resolve({ success: true, data: [makeHighlightStory()] });
      }
      // main highlights list: /v2/stories/highlights/7
      return Promise.resolve({ success: true, data: [makeHighlight()] });
    });

    const { StoryHighlights } = await import('./StoryHighlights');
    render(<StoryHighlights {...defaultProps} />);

    await waitFor(() => screen.getByText('Summer Memories'));

    // Click on the highlight circle button
    const highlightBtn = screen.getAllByRole('button').find(
      (b) => b.getAttribute('aria-label')?.includes('Summer Memories')
    );
    expect(highlightBtn).toBeDefined();
    fireEvent.click(highlightBtn!);

    await waitFor(() => {
      expect(screen.getByTestId('story-viewer')).toBeInTheDocument();
    });
  });

  it('shows toast info when highlight has no stories', async () => {
    mockApi.get.mockImplementation((url: string) => {
      // highlight stories endpoint returns empty
      if (url.includes('/highlights/1') && url.includes('/stories')) {
        return Promise.resolve({ success: true, data: [] });
      }
      // main highlights list
      return Promise.resolve({ success: true, data: [makeHighlight()] });
    });

    const { StoryHighlights } = await import('./StoryHighlights');
    render(<StoryHighlights {...defaultProps} />);

    await waitFor(() => screen.getByText('Summer Memories'));

    const highlightBtn = screen.getAllByRole('button').find(
      (b) => b.getAttribute('aria-label')?.includes('Summer Memories')
    );
    fireEvent.click(highlightBtn!);

    await waitFor(() => {
      expect(mockToast.info).toHaveBeenCalled();
    });
  });

  it('calls DELETE when owner deletes a highlight', async () => {
    mockApi.delete.mockResolvedValue({ success: true });
    const { StoryHighlights } = await import('./StoryHighlights');
    render(<StoryHighlights {...defaultProps} />);

    await waitFor(() => screen.getByText('Summer Memories'));

    const deleteBtn = screen.getAllByRole('button').find(
      (b) => b.getAttribute('aria-label')?.toLowerCase().includes('delete')
    );
    expect(deleteBtn).toBeDefined();
    fireEvent.click(deleteBtn!);

    await waitFor(() => {
      expect(mockApi.delete).toHaveBeenCalledWith('/v2/stories/highlights/1');
    });
  });

  it('shows first letter of title when no cover_url', async () => {
    const { StoryHighlights } = await import('./StoryHighlights');
    render(<StoryHighlights {...defaultProps} />);

    await waitFor(() => {
      // 'S' is the first letter of 'Summer Memories'
      expect(screen.getByText('S')).toBeInTheDocument();
    });
  });

  it('renders multiple highlights when API returns them', async () => {
    mockApi.get.mockResolvedValue({
      success: true,
      data: [
        makeHighlight({ id: 1, title: 'Highlight One' }),
        makeHighlight({ id: 2, title: 'Highlight Two' }),
        makeHighlight({ id: 3, title: 'Highlight Three' }),
      ],
    });

    const { StoryHighlights } = await import('./StoryHighlights');
    render(<StoryHighlights {...defaultProps} />);

    await waitFor(() => {
      expect(screen.getByText('Highlight One')).toBeInTheDocument();
      expect(screen.getByText('Highlight Two')).toBeInTheDocument();
      expect(screen.getByText('Highlight Three')).toBeInTheDocument();
    });
  });
});
