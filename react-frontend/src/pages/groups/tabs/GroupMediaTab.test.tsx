// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import React from 'react';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

// ── Hoist mock data ───────────────────────────────────────────────────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
    upload: vi.fn(),
  },
}));

// ── Mock api ──────────────────────────────────────────────────────────────────
vi.mock('@/lib/api', () => ({
  api: mockApi,
  default: mockApi,
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/lib/helpers', () => ({ formatRelativeTime: () => '2 days ago' }));

// ── Contexts ──────────────────────────────────────────────────────────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };
const mockConfirm = vi.fn();

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
  })
);

// ── Stub HeroUI heavy components ──────────────────────────────────────────────
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<Record<string, unknown>>();
  return {
    ...orig,
    // useDisclosure stub
    useDisclosure: () => ({
      isOpen: false,
      onOpen: vi.fn(),
      onClose: vi.fn(),
    }),
    // useConfirm stub — returns the mock function
    useConfirm: () => mockConfirm,
    // Modal stub — always closed unless tested directly
    Modal: ({ children, isOpen }: { children?: React.ReactNode; isOpen?: boolean }) =>
      isOpen ? <div role="dialog" aria-label="Dialog">{children as React.ReactNode}</div> : null,
    ModalContent: ({ children }: { children?: React.ReactNode }) => <>{children as React.ReactNode}</>,
    ModalBody: ({ children }: { children?: React.ReactNode }) => <div>{children as React.ReactNode}</div>,
    // ToggleButtonGroup stub
    ToggleButtonGroup: ({ children, onSelectionChange, selectedKeys }: {
      children?: React.ReactNode;
      onSelectionChange?: (keys: Set<string>) => void;
      selectedKeys?: Set<string>;
    }) => (
      <div data-testid="toggle-group" data-selected={[...(selectedKeys ?? [])].join(',')}>
        {React.Children.map(children as React.ReactNode, (child) => {
          if (React.isValidElement(child)) {
            return React.cloneElement(child as React.ReactElement<{
              onClick?: () => void;
              id?: string;
            }>, {
              onClick: () => onSelectionChange?.(new Set([(child.props as { id?: string }).id ?? ''])),
            });
          }
          return child;
        })}
      </div>
    ),
    ToggleButton: ({ children, id, onClick }: {
      children?: React.ReactNode;
      id?: string;
      onClick?: () => void;
    }) => (
      <button data-testid={`filter-${id}`} onClick={onClick}>{children as React.ReactNode}</button>
    ),
    GlassCard: ({ children, onClick }: { children?: React.ReactNode; onClick?: () => void }) => (
      <div data-testid="glass-card" onClick={onClick}>{children as React.ReactNode}</div>
    ),
    Spinner: ({ size }: { size?: string }) => <div role="status" aria-busy="true" aria-label="loading" data-size={size} />,
    Button: ({ children, onPress, isDisabled, 'aria-label': label, ...rest }: {
      children?: React.ReactNode;
      onPress?: () => void;
      isDisabled?: boolean;
      'aria-label'?: string;
      [key: string]: unknown;
    }) => (
      <button
        onClick={onPress}
        disabled={!!isDisabled}
        aria-label={label}
        {...(rest as React.ButtonHTMLAttributes<HTMLButtonElement>)}
      >
        {children as React.ReactNode}
      </button>
    ),
  };
});

vi.mock('@/components/feedback', () => ({
  EmptyState: ({ title, description }: { title: string; description?: string }) => (
    <div data-testid="empty-state">
      <p>{title}</p>
      {description && <p>{description}</p>}
    </div>
  ),
}));

// ── Fixtures ──────────────────────────────────────────────────────────────────
const makeMediaItem = (overrides = {}) => ({
  id: 1,
  group_id: 42,
  type: 'image' as const,
  url: 'https://cdn.example.com/photo.jpg',
  thumbnail_url: 'https://cdn.example.com/thumb.jpg',
  caption: 'A test photo',
  uploaded_by: 99,
  uploader_name: 'Alice',
  uploader_avatar: null,
  created_at: '2025-06-01T10:00:00Z',
  ...overrides,
});

const makeMediaResponse = (items = [] as ReturnType<typeof makeMediaItem>[], extras = {}) => ({
  success: true,
  data: {
    items,
    cursor: null,
    has_more: false,
    ...extras,
  },
});

// ── Tests ─────────────────────────────────────────────────────────────────────
describe('GroupMediaTab', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockConfirm.mockResolvedValue(true);
    mockApi.get.mockResolvedValue(makeMediaResponse());
  });

  it('shows loading spinner while media fetches', async () => {
    mockApi.get.mockImplementationOnce(() => new Promise(() => {}));
    const { GroupMediaTab } = await import('./GroupMediaTab');
    render(<GroupMediaTab groupId={42} isAdmin={true} />);

    const spinners = screen.getAllByRole('status');
    const busy = spinners.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  it('renders empty state when no media items', async () => {
    const { GroupMediaTab } = await import('./GroupMediaTab');
    render(<GroupMediaTab groupId={42} isAdmin={true} />);

    await waitFor(() => {
      expect(screen.getByTestId('empty-state')).toBeInTheDocument();
    });
  });

  it('renders media cards when items are returned', async () => {
    mockApi.get.mockResolvedValue(makeMediaResponse([makeMediaItem()]));
    const { GroupMediaTab } = await import('./GroupMediaTab');
    render(<GroupMediaTab groupId={42} isAdmin={true} />);

    await waitFor(() => {
      expect(screen.getByTestId('glass-card')).toBeInTheDocument();
    });
  });

  it('renders multiple media cards', async () => {
    mockApi.get.mockResolvedValue(
      makeMediaResponse([
        makeMediaItem({ id: 1 }),
        makeMediaItem({ id: 2 }),
        makeMediaItem({ id: 3 }),
      ])
    );
    const { GroupMediaTab } = await import('./GroupMediaTab');
    render(<GroupMediaTab groupId={42} isAdmin={true} />);

    await waitFor(() => {
      expect(screen.getAllByTestId('glass-card')).toHaveLength(3);
    });
  });

  it('renders upload button for members', async () => {
    const { GroupMediaTab } = await import('./GroupMediaTab');
    render(<GroupMediaTab groupId={42} isAdmin={false} isMember={true} />);

    await waitFor(() => {
      const uploadBtn = screen.getAllByRole('button').find(
        (b) => b.getAttribute('aria-label')?.toLowerCase().includes('upload')
      );
      expect(uploadBtn).toBeDefined();
    });
  });

  it('does not render upload button for non-members', async () => {
    const { GroupMediaTab } = await import('./GroupMediaTab');
    render(<GroupMediaTab groupId={42} isAdmin={false} isMember={false} />);

    await waitFor(() => {
      expect(screen.queryByTestId('empty-state')).toBeInTheDocument();
    });

    const uploadBtn = screen.queryAllByRole('button').find(
      (b) => b.getAttribute('aria-label')?.toLowerCase().includes('upload')
    );
    expect(uploadBtn).toBeUndefined();
  });

  it('renders filter toggle buttons', async () => {
    const { GroupMediaTab } = await import('./GroupMediaTab');
    render(<GroupMediaTab groupId={42} isAdmin={true} />);

    await waitFor(() => {
      expect(screen.getByTestId('filter-all')).toBeInTheDocument();
      expect(screen.getByTestId('filter-image')).toBeInTheDocument();
      expect(screen.getByTestId('filter-video')).toBeInTheDocument();
    });
  });

  it('shows load more button when has_more is true', async () => {
    mockApi.get.mockResolvedValue(
      makeMediaResponse([makeMediaItem()], { has_more: true, cursor: 'abc' })
    );
    const { GroupMediaTab } = await import('./GroupMediaTab');
    render(<GroupMediaTab groupId={42} isAdmin={true} />);

    await waitFor(() => {
      const loadMoreBtn = screen.getAllByRole('button').find(
        (b) => b.textContent?.toLowerCase().includes('more') || b.textContent?.toLowerCase().includes('load')
      );
      expect(loadMoreBtn).toBeDefined();
    });
  });

  it('renders delete button for admin', async () => {
    mockApi.get.mockResolvedValue(makeMediaResponse([makeMediaItem()]));
    const { GroupMediaTab } = await import('./GroupMediaTab');
    render(<GroupMediaTab groupId={42} isAdmin={true} />);

    await waitFor(() => {
      const deleteBtn = screen.queryAllByRole('button').find(
        (b) => b.getAttribute('aria-label')?.toLowerCase().includes('delete')
      );
      expect(deleteBtn).toBeDefined();
    });
  });

  it('calls DELETE api after confirm on delete click', async () => {
    mockApi.get.mockResolvedValue(makeMediaResponse([makeMediaItem({ id: 5 })]));
    mockApi.delete.mockResolvedValue({ success: true });
    mockConfirm.mockResolvedValue(true);

    const { GroupMediaTab } = await import('./GroupMediaTab');
    render(<GroupMediaTab groupId={42} isAdmin={true} />);

    await waitFor(() => expect(screen.getByTestId('glass-card')).toBeInTheDocument());

    const deleteBtn = screen.queryAllByRole('button').find(
      (b) => b.getAttribute('aria-label')?.toLowerCase().includes('delete')
    );
    if (deleteBtn) {
      fireEvent.click(deleteBtn);
      await waitFor(() => {
        expect(mockApi.delete).toHaveBeenCalledWith('/v2/groups/42/media/5');
      });
    }
  });

  it('shows success toast after delete', async () => {
    mockApi.get.mockResolvedValue(makeMediaResponse([makeMediaItem({ id: 5 })]));
    mockApi.delete.mockResolvedValue({ success: true });
    mockConfirm.mockResolvedValue(true);

    const { GroupMediaTab } = await import('./GroupMediaTab');
    render(<GroupMediaTab groupId={42} isAdmin={true} />);

    await waitFor(() => expect(screen.getByTestId('glass-card')).toBeInTheDocument());

    const deleteBtn = screen.queryAllByRole('button').find(
      (b) => b.getAttribute('aria-label')?.toLowerCase().includes('delete')
    );
    if (deleteBtn) {
      fireEvent.click(deleteBtn);
      await waitFor(() => {
        expect(mockToast.success).toHaveBeenCalled();
      });
    }
  });

  it('does not delete if confirm is cancelled', async () => {
    mockApi.get.mockResolvedValue(makeMediaResponse([makeMediaItem({ id: 5 })]));
    mockConfirm.mockResolvedValue(false);

    const { GroupMediaTab } = await import('./GroupMediaTab');
    render(<GroupMediaTab groupId={42} isAdmin={true} />);

    await waitFor(() => expect(screen.getByTestId('glass-card')).toBeInTheDocument());

    const deleteBtn = screen.queryAllByRole('button').find(
      (b) => b.getAttribute('aria-label')?.toLowerCase().includes('delete')
    );
    if (deleteBtn) {
      fireEvent.click(deleteBtn);
      await waitFor(() => {
        expect(mockApi.delete).not.toHaveBeenCalled();
      });
    }
  });

  it('changes filter when Photos tab clicked', async () => {
    const { GroupMediaTab } = await import('./GroupMediaTab');
    render(<GroupMediaTab groupId={42} isAdmin={true} />);

    await waitFor(() => screen.getByTestId('filter-image'));

    fireEvent.click(screen.getByTestId('filter-image'));

    await waitFor(() => {
      // API should be called with type=image
      const calls = mockApi.get.mock.calls;
      const imageCall = calls.find(([url]: [string]) => url.includes('type=image'));
      expect(imageCall).toBeDefined();
    });
  });
});
