// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import React from 'react';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import userEvent from '@testing-library/user-event';

// ─── Hoist mock data ──────────────────────────────────────────────────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  },
}));

vi.mock('@/lib/api', () => ({
  api: mockApi,
  default: mockApi,
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

vi.mock('@/lib/helpers', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/lib/helpers')>();
  return {
    ...actual,
    formatRelativeTime: (d: string) => `relative:${d}`,
  };
});

// ─── Contexts ─────────────────────────────────────────────────────────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
  })
);

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/components/seo/PageMeta', () => ({ PageMeta: () => null }));

// ─── Stub feedback components ─────────────────────────────────────────────────
vi.mock('@/components/feedback', () => ({
  EmptyState: ({ title, description }: { title: string; description?: string; action?: React.ReactNode }) => (
    <div data-testid="empty-state">
      <p>{title}</p>
      {description && <p>{description}</p>}
    </div>
  ),
}));

// ─── Fixtures ─────────────────────────────────────────────────────────────────
const makePage = (overrides = {}): Record<string, unknown> => ({
  id: 1,
  title: 'Getting Started',
  slug: 'getting-started',
  parent_id: null,
  sort_order: 0,
  is_published: true,
  author: { id: 10, name: 'Alice' },
  updated_at: '2024-06-01T00:00:00Z',
  ...overrides,
});

const makePageDetail = (overrides = {}): Record<string, unknown> => ({
  ...makePage(),
  content: 'This is the page content.',
  ...overrides,
});

const makeRevision = (overrides = {}): Record<string, unknown> => ({
  id: 1,
  change_summary: 'Initial version',
  editor: { id: 10, name: 'Alice' },
  created_at: '2024-06-01T00:00:00Z',
  ...overrides,
});

// ─────────────────────────────────────────────────────────────────────────────
describe('GroupWikiTab', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockApi.get.mockResolvedValue({ success: true, data: [] });
  });

  it('shows a loading spinner while fetching pages', async () => {
    mockApi.get.mockImplementationOnce(() => new Promise(() => {}));
    const { GroupWikiTab } = await import('./GroupWikiTab');
    render(<GroupWikiTab groupId={5} isAdmin={false} isMember={true} />);

    const statuses = screen.getAllByRole('status');
    const busy = statuses.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  it('renders empty state when no pages exist', async () => {
    mockApi.get.mockResolvedValue({ data: [] });
    const { GroupWikiTab } = await import('./GroupWikiTab');
    render(<GroupWikiTab groupId={5} isAdmin={false} isMember={true} />);

    await waitFor(() => {
      expect(screen.getByTestId('empty-state')).toBeInTheDocument();
    });
  });

  it('renders page list in sidebar when pages are loaded', async () => {
    mockApi.get.mockResolvedValue({ data: [makePage()] });
    const { GroupWikiTab } = await import('./GroupWikiTab');
    render(<GroupWikiTab groupId={5} isAdmin={false} isMember={true} />);

    await waitFor(() => {
      expect(screen.getByText('Getting Started')).toBeInTheDocument();
    });
  });

  it('shows a "New Page" button for members', async () => {
    mockApi.get.mockResolvedValue({ data: [] });
    const { GroupWikiTab } = await import('./GroupWikiTab');
    render(<GroupWikiTab groupId={5} isAdmin={false} isMember={true} />);

    await waitFor(() => {
      const btn = screen.getAllByRole('button').find((b) =>
        b.textContent?.toLowerCase().includes('new') || b.textContent?.toLowerCase().includes('page')
      );
      expect(btn).toBeInTheDocument();
    });
  });

  it('does NOT show "New Page" button for non-members', async () => {
    mockApi.get.mockResolvedValue({ data: [] });
    const { GroupWikiTab } = await import('./GroupWikiTab');
    render(<GroupWikiTab groupId={5} isAdmin={false} isMember={false} />);

    // wait until empty state appears (i.e. load complete)
    await waitFor(() => {
      expect(screen.getByTestId('empty-state')).toBeInTheDocument();
    });

    // Non-members should not see a "New Page" button in the header area
    // The empty state action button is also absent for non-members
    const newPageBtn = screen.queryAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('new') && b.textContent?.toLowerCase().includes('page')
    );
    expect(newPageBtn).toBeUndefined();
  });

  it('loads page detail when a page is clicked', async () => {
    mockApi.get
      .mockResolvedValueOnce({ data: [makePage()] })
      .mockResolvedValueOnce({ data: makePageDetail() });

    const { GroupWikiTab } = await import('./GroupWikiTab');
    render(<GroupWikiTab groupId={5} isAdmin={false} isMember={true} />);

    await waitFor(() => screen.getByText('Getting Started'));

    // click the page in the sidebar
    const pageBtn = screen.getAllByRole('button').find(
      (b) => b.textContent?.trim() === 'Getting Started'
    );
    fireEvent.click(pageBtn!);

    await waitFor(() => {
      expect(screen.getByText('This is the page content.')).toBeInTheDocument();
    });
  });

  it('shows edit button for members when viewing a page', async () => {
    mockApi.get
      .mockResolvedValueOnce({ data: [makePage()] })
      .mockResolvedValueOnce({ data: makePageDetail() });

    const { GroupWikiTab } = await import('./GroupWikiTab');
    render(<GroupWikiTab groupId={5} isAdmin={false} isMember={true} />);

    await waitFor(() => screen.getByText('Getting Started'));
    const pageBtn = screen.getAllByRole('button').find(
      (b) => b.textContent?.trim() === 'Getting Started'
    );
    fireEvent.click(pageBtn!);

    await waitFor(() => {
      const editBtn = screen.getAllByRole('button').find((b) =>
        b.textContent?.toLowerCase().includes('edit') || b.getAttribute('aria-label')?.toLowerCase().includes('edit')
      );
      expect(editBtn).toBeInTheDocument();
    });
  });

  it('shows delete button for admins when viewing a page', async () => {
    mockApi.get
      .mockResolvedValueOnce({ data: [makePage()] })
      .mockResolvedValueOnce({ data: makePageDetail() });

    const { GroupWikiTab } = await import('./GroupWikiTab');
    render(<GroupWikiTab groupId={5} isAdmin={true} isMember={true} />);

    await waitFor(() => screen.getByText('Getting Started'));
    const pageBtn = screen.getAllByRole('button').find(
      (b) => b.textContent?.trim() === 'Getting Started'
    );
    fireEvent.click(pageBtn!);

    await waitFor(() => {
      const deleteBtn = screen.getAllByRole('button').find((b) =>
        b.textContent?.toLowerCase().includes('delete') || b.getAttribute('aria-label')?.toLowerCase().includes('delete')
      );
      expect(deleteBtn).toBeInTheDocument();
    });
  });

  it('does NOT show delete button for non-admins', async () => {
    mockApi.get
      .mockResolvedValueOnce({ data: [makePage()] })
      .mockResolvedValueOnce({ data: makePageDetail() });

    const { GroupWikiTab } = await import('./GroupWikiTab');
    render(<GroupWikiTab groupId={5} isAdmin={false} isMember={true} />);

    await waitFor(() => screen.getByText('Getting Started'));
    const pageBtn = screen.getAllByRole('button').find(
      (b) => b.textContent?.trim() === 'Getting Started'
    );
    fireEvent.click(pageBtn!);

    await waitFor(() => screen.getByText('This is the page content.'));

    const deleteBtn = screen.queryAllByRole('button').find((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('delete')
    );
    expect(deleteBtn).toBeUndefined();
  });

  it('opens history revisions when history button is clicked', async () => {
    mockApi.get
      .mockResolvedValueOnce({ data: [makePage()] })
      .mockResolvedValueOnce({ data: makePageDetail() })
      .mockResolvedValueOnce({ data: [makeRevision()] });

    const { GroupWikiTab } = await import('./GroupWikiTab');
    render(<GroupWikiTab groupId={5} isAdmin={false} isMember={true} />);

    await waitFor(() => screen.getByText('Getting Started'));
    const pageBtn = screen.getAllByRole('button').find(
      (b) => b.textContent?.trim() === 'Getting Started'
    );
    fireEvent.click(pageBtn!);

    await waitFor(() => screen.getByText('This is the page content.'));

    const historyBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('histor') || b.getAttribute('aria-label')?.toLowerCase().includes('histor')
    );
    fireEvent.click(historyBtn!);

    await waitFor(() => {
      expect(screen.getByText('Initial version')).toBeInTheDocument();
    });
  });

  it('shows error toast when page list fails to load', async () => {
    mockApi.get.mockRejectedValueOnce(new Error('network'));
    const { GroupWikiTab } = await import('./GroupWikiTab');
    render(<GroupWikiTab groupId={5} isAdmin={false} isMember={true} />);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('opens create modal when "New Page" is clicked', async () => {
    mockApi.get.mockResolvedValue({ data: [] });
    const { GroupWikiTab } = await import('./GroupWikiTab');
    render(<GroupWikiTab groupId={5} isAdmin={false} isMember={true} />);

    await waitFor(() => {
      // wait for empty state to confirm loaded
      expect(screen.getByTestId('empty-state')).toBeInTheDocument();
    });

    const newPageBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('new') || b.textContent?.toLowerCase().includes('create')
    );
    if (newPageBtn) fireEvent.click(newPageBtn);

    await waitFor(() => {
      const dialog = document.querySelector('[role="dialog"]');
      expect(dialog).toBeTruthy();
    });
  });

  it('shows draft chip for unpublished pages', async () => {
    mockApi.get.mockResolvedValue({ data: [makePage({ is_published: false })] });
    const { GroupWikiTab } = await import('./GroupWikiTab');
    render(<GroupWikiTab groupId={5} isAdmin={true} isMember={true} />);

    await waitFor(() => {
      const draftChip = screen.getAllByText(/draft/i);
      expect(draftChip.length).toBeGreaterThan(0);
    });
  });
});
