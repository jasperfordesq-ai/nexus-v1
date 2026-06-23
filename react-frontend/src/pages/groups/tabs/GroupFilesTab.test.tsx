// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';
import userEvent from '@testing-library/user-event';

// ─── Mock api ────────────────────────────────────────────────────────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn(), download: vi.fn(), upload: vi.fn() },
}));

vi.mock('@/lib/api', () => ({ api: mockApi, default: mockApi }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: (url: string | null) => url ?? '',
  formatRelativeTime: () => '2 days ago',
}));

// ─── Context ─────────────────────────────────────────────────────────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn(), showToast: vi.fn() };
const mockConfirm = vi.fn(() => Promise.resolve(true));

vi.mock('@/contexts', () =>
  createMockContexts({
    useAuth: () => ({
      user: { id: 1, name: 'Alice', role: 'member' },
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
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

vi.mock('@/contexts/ToastContext', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/contexts/ToastContext')>();
  return {
    ...actual,
    useToast: () => mockToast,
  };
});

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/components/seo/PageMeta', () => ({ PageMeta: () => null }));

// ─── Stub heavy HeroUI components that misbehave in jsdom ─────────────────────
vi.mock('@/components/ui', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/components/ui')>();
  return {
    ...actual,
    useConfirm: () => mockConfirm,
    Modal: ({ isOpen, children }: { isOpen?: boolean; children?: React.ReactNode }) =>
      isOpen ? <div role="dialog">{children}</div> : null,
    ModalContent: ({ children }: { children?: ((onClose: () => void) => React.ReactNode) | React.ReactNode }) => (
      <div>{typeof children === 'function' ? children(() => {}) : children}</div>
    ),
    ModalHeader: ({ children }: { children?: React.ReactNode }) => <div data-testid="modal-header">{children}</div>,
    ModalBody: ({ children }: { children?: React.ReactNode }) => <div>{children}</div>,
    ModalFooter: ({ children }: { children?: React.ReactNode }) => <div>{children}</div>,
    Dropdown: ({ children }: { children?: React.ReactNode }) => <div data-testid="dropdown">{children}</div>,
    DropdownTrigger: ({ children }: { children?: React.ReactNode }) => <div>{children}</div>,
    DropdownMenu: ({ children }: { children?: React.ReactNode }) => <div data-testid="dropdown-menu">{children}</div>,
    DropdownItem: ({ children, onPress }: { children?: React.ReactNode; onPress?: () => void }) => (
      <button onClick={onPress} data-testid="dropdown-item">{children}</button>
    ),
    SearchField: ({ placeholder, onValueChange, ...rest }: { placeholder?: string; onValueChange?: (v: string) => void; [key: string]: unknown }) => (
      <input
        placeholder={placeholder}
        onChange={(e) => onValueChange?.(e.target.value)}
        aria-label={rest['aria-label'] as string}
      />
    ),
    ToggleButtonGroup: ({ children }: { children?: React.ReactNode }) => <div data-testid="folder-filter">{children}</div>,
    ToggleButton: ({ children, id }: { children?: React.ReactNode; id?: string }) => (
      <button data-testid={`toggle-btn-${id}`}>{children}</button>
    ),
  };
});

// ─── Stub EmptyState ──────────────────────────────────────────────────────────
vi.mock('@/components/feedback', () => ({
  EmptyState: ({ title }: { title: string }) => <div data-testid="empty-state">{title}</div>,
}));

// ─── Fixtures ─────────────────────────────────────────────────────────────────
const makeFile = (overrides = {}) => ({
  id: 1,
  group_id: 10,
  file_name: 'meeting-notes.pdf',
  file_path: '/uploads/meeting-notes.pdf',
  file_type: 'application/pdf',
  file_size: 102400, // 100 KB
  uploaded_by: 1,
  uploader_name: 'Alice',
  uploader_avatar: null,
  folder: null,
  description: null,
  download_count: 3,
  created_at: '2025-06-01T10:00:00Z',
  ...overrides,
});

const makeFilesResponse = (items = [] as object[], extra = {}) => ({
  success: true,
  data: { items, cursor: null, has_more: false, ...extra },
});

const makeFoldersResponse = (folders = [] as object[]) => ({
  success: true,
  data: folders,
});

// ─────────────────────────────────────────────────────────────────────────────
describe('GroupFilesTab', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('/folders')) return Promise.resolve(makeFoldersResponse());
      return Promise.resolve(makeFilesResponse());
    });
    mockApi.delete.mockResolvedValue({ success: true });
    mockApi.download.mockResolvedValue(undefined);
    mockApi.upload.mockResolvedValue({ success: true });
    mockConfirm.mockResolvedValue(true);
  });

  it('shows loading spinner on initial render', async () => {
    mockApi.get.mockImplementation(() => new Promise(() => {}));
    const { GroupFilesTab } = await import('./GroupFilesTab');
    render(<GroupFilesTab groupId={10} isAdmin={false} />);
    // Multiple role="status" exist (harness ToastProvider + component spinner)
    // Find the one with aria-busy="true" from the component
    const statusEls = screen.getAllByRole('status');
    const busyEl = statusEls.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busyEl).toBeDefined();
  });

  it('shows empty state when no files are returned', async () => {
    const { GroupFilesTab } = await import('./GroupFilesTab');
    render(<GroupFilesTab groupId={10} isAdmin={false} />);
    await waitFor(() => {
      expect(screen.getByTestId('empty-state')).toBeInTheDocument();
    });
  });

  it('renders file name when files are returned', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('/folders')) return Promise.resolve(makeFoldersResponse());
      return Promise.resolve(makeFilesResponse([makeFile()]));
    });
    const { GroupFilesTab } = await import('./GroupFilesTab');
    render(<GroupFilesTab groupId={10} isAdmin={false} />);
    await waitFor(() => {
      expect(screen.getByText('meeting-notes.pdf')).toBeInTheDocument();
    });
  });

  it('renders uploader name alongside file', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('/folders')) return Promise.resolve(makeFoldersResponse());
      return Promise.resolve(makeFilesResponse([makeFile()]));
    });
    const { GroupFilesTab } = await import('./GroupFilesTab');
    render(<GroupFilesTab groupId={10} isAdmin={false} />);
    await waitFor(() => {
      expect(screen.getByText('Alice')).toBeInTheDocument();
    });
  });

  it('shows upload button for members', async () => {
    const { GroupFilesTab } = await import('./GroupFilesTab');
    render(<GroupFilesTab groupId={10} isAdmin={false} isMember={true} />);
    await waitFor(() => {
      const btn = screen.getAllByRole('button').find(
        (b) => b.textContent?.toLowerCase().includes('upload')
      );
      expect(btn).toBeDefined();
    });
  });

  it('hides upload button when isMember is false', async () => {
    const { GroupFilesTab } = await import('./GroupFilesTab');
    render(<GroupFilesTab groupId={10} isAdmin={false} isMember={false} />);
    await waitFor(() => screen.getByTestId('empty-state'));
    const uploadBtns = screen.queryAllByRole('button').filter(
      (b) => b.textContent?.toLowerCase().includes('upload')
    );
    expect(uploadBtns).toHaveLength(0);
  });

  it('shows download button for each file', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('/folders')) return Promise.resolve(makeFoldersResponse());
      return Promise.resolve(makeFilesResponse([makeFile()]));
    });
    const { GroupFilesTab } = await import('./GroupFilesTab');
    render(<GroupFilesTab groupId={10} isAdmin={false} />);
    await waitFor(() => {
      const downloadBtns = screen.getAllByRole('button').filter(
        (b) => b.getAttribute('aria-label')?.toLowerCase().includes('download')
      );
      expect(downloadBtns.length).toBeGreaterThan(0);
    });
  });

  it('calls download API when download button is clicked', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('/folders')) return Promise.resolve(makeFoldersResponse());
      return Promise.resolve(makeFilesResponse([makeFile({ id: 5 })]));
    });
    const { GroupFilesTab } = await import('./GroupFilesTab');
    render(<GroupFilesTab groupId={10} isAdmin={false} />);
    await waitFor(() => screen.getByText('meeting-notes.pdf'));

    const downloadBtn = screen.getAllByRole('button').find(
      (b) => b.getAttribute('aria-label')?.toLowerCase().includes('download')
    );
    if (downloadBtn) {
      await userEvent.click(downloadBtn);
      await waitFor(() => {
        expect(mockApi.download).toHaveBeenCalledWith(
          '/v2/groups/10/files/5/download',
          expect.objectContaining({ filename: 'meeting-notes.pdf' })
        );
      });
    }
  });

  it('shows delete option in dropdown for admin users', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('/folders')) return Promise.resolve(makeFoldersResponse());
      return Promise.resolve(makeFilesResponse([makeFile({ id: 7, uploaded_by: 99 })]));
    });
    const { GroupFilesTab } = await import('./GroupFilesTab');
    render(<GroupFilesTab groupId={10} isAdmin={true} currentUserId={1} />);
    await waitFor(() => {
      expect(screen.getByTestId('dropdown')).toBeInTheDocument();
    });
  });

  it('calls delete API when delete is confirmed', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('/folders')) return Promise.resolve(makeFoldersResponse());
      return Promise.resolve(makeFilesResponse([makeFile({ id: 8, uploaded_by: 1 })]));
    });
    const { GroupFilesTab } = await import('./GroupFilesTab');
    render(<GroupFilesTab groupId={10} isAdmin={true} currentUserId={1} />);
    await waitFor(() => screen.getByText('meeting-notes.pdf'));

    const deleteItem = screen.getByTestId('dropdown-item');
    await userEvent.click(deleteItem);
    await waitFor(() => {
      expect(mockApi.delete).toHaveBeenCalledWith('/v2/groups/10/files/8');
    });
  });

  it('shows error toast when download fails', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('/folders')) return Promise.resolve(makeFoldersResponse());
      return Promise.resolve(makeFilesResponse([makeFile({ id: 9 })]));
    });
    mockApi.download.mockRejectedValue(new Error('network'));
    const { GroupFilesTab } = await import('./GroupFilesTab');
    render(<GroupFilesTab groupId={10} isAdmin={false} />);
    await waitFor(() => screen.getByText('meeting-notes.pdf'));

    const downloadBtn = screen.getAllByRole('button').find(
      (b) => b.getAttribute('aria-label')?.toLowerCase().includes('download')
    );
    if (downloadBtn) {
      await userEvent.click(downloadBtn);
      await waitFor(() => {
        expect(mockToast.error).toHaveBeenCalled();
      });
    }
  });

  it('shows load more button when has_more is true', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('/folders')) return Promise.resolve(makeFoldersResponse());
      return Promise.resolve(makeFilesResponse([makeFile()], { has_more: true, cursor: 'abc' }));
    });
    const { GroupFilesTab } = await import('./GroupFilesTab');
    render(<GroupFilesTab groupId={10} isAdmin={false} />);
    await waitFor(() => {
      const loadMore = screen.getAllByRole('button').find(
        (b) => b.textContent?.toLowerCase().includes('more') || b.textContent?.toLowerCase().includes('load')
      );
      expect(loadMore).toBeDefined();
    });
  });

  it('renders folder filter chips when folders are returned', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('/folders')) {
        return Promise.resolve(makeFoldersResponse([{ folder: 'Documents', file_count: 3 }]));
      }
      return Promise.resolve(makeFilesResponse([makeFile()]));
    });
    const { GroupFilesTab } = await import('./GroupFilesTab');
    render(<GroupFilesTab groupId={10} isAdmin={false} />);
    await waitFor(() => {
      expect(screen.getByTestId('folder-filter')).toBeInTheDocument();
    });
  });

  it('shows success toast after file deletion', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('/folders')) return Promise.resolve(makeFoldersResponse());
      return Promise.resolve(makeFilesResponse([makeFile({ id: 20, uploaded_by: 1 })]));
    });
    const { GroupFilesTab } = await import('./GroupFilesTab');
    render(<GroupFilesTab groupId={10} isAdmin={true} currentUserId={1} />);
    await waitFor(() => screen.getByText('meeting-notes.pdf'));

    const deleteItem = screen.getByTestId('dropdown-item');
    await userEvent.click(deleteItem);
    await waitFor(() => {
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('renders search field', async () => {
    const { GroupFilesTab } = await import('./GroupFilesTab');
    render(<GroupFilesTab groupId={10} isAdmin={false} />);
    // SearchField renders as an input with placeholder
    await waitFor(() => {
      const inputs = screen.getAllByRole('textbox');
      expect(inputs.length).toBeGreaterThan(0);
    });
  });
});
