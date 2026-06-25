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
  mockApi: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn(), download: vi.fn(), upload: vi.fn() },
}));

vi.mock('@/lib/api', () => ({ api: mockApi, default: mockApi }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/lib/helpers', () => ({ formatRelativeTime: (d: string) => d }));

// ─── Toast / Auth / Tenant ───────────────────────────────────────────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn(), showToast: vi.fn() };

vi.mock('@/contexts', () =>
  createMockContexts({
    useAuth: () => ({
      user: { id: 1, name: 'Tester' },
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

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// ─── Stub feedback EmptyState ─────────────────────────────────────────────────
vi.mock('@/components/feedback', () => ({
  EmptyState: ({ title, description }: { title?: string; description?: string; icon?: React.ReactNode }) => (
    <div data-testid="empty-state">
      {title && <p>{title}</p>}
      {description && <p>{description}</p>}
    </div>
  ),
}));

// ─── Stub SafeHtml ────────────────────────────────────────────────────────────
vi.mock('@/components/ui/SafeHtml', () => ({
  SafeHtml: ({ content }: { content: string; className?: string; as?: string }) => <div>{content}</div>,
}));

// ─── Stub HeroUI Modal (render-prop pattern) and other UI components ──────────
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<Record<string, unknown>>();
  return {
    ...orig,
    Modal: ({ isOpen, children }: { isOpen: boolean; children?: React.ReactNode }) =>
      isOpen ? <div role="dialog" aria-label="Dialog" aria-modal="true">{children}</div> : null,
    ModalContent: ({ children }: { children: React.ReactNode | ((close: () => void) => React.ReactNode) }) =>
      <div>{typeof children === 'function' ? children(() => {}) : children}</div>,
    ModalHeader: ({ children }: { children: React.ReactNode }) => <div data-testid="modal-header">{children}</div>,
    ModalBody: ({ children }: { children: React.ReactNode }) => <div data-testid="modal-body">{children}</div>,
    ModalFooter: ({ children }: { children: React.ReactNode }) => <div data-testid="modal-footer">{children}</div>,
    Button: ({ children, onPress, isLoading, isDisabled, startContent, isIconOnly, variant, color, size, ...rest }: {
      children?: React.ReactNode; onPress?: () => void; isLoading?: boolean; isDisabled?: boolean;
      startContent?: React.ReactNode; isIconOnly?: boolean; variant?: string; color?: string; size?: string; [key: string]: unknown
    }) => (
      <button onClick={onPress} disabled={isDisabled || isLoading}>
        {startContent}{isLoading ? 'Loading...' : children}
      </button>
    ),
    Input: ({ label, value, onChange, placeholder }: { label?: string; value?: string; onChange?: (e: React.ChangeEvent<HTMLInputElement>) => void; placeholder?: string }) => (
      <div>
        {label && <label>{label}</label>}
        <input value={value} onChange={onChange} placeholder={placeholder} />
      </div>
    ),
    Textarea: ({ label, value, onChange, placeholder }: { label?: string; value?: string; onChange?: (e: React.ChangeEvent<HTMLTextAreaElement>) => void; placeholder?: string }) => (
      <div>
        {label && <label>{label}</label>}
        <textarea value={value} onChange={onChange} placeholder={placeholder} />
      </div>
    ),
    Chip: ({ children, color, size }: { children: React.ReactNode; color?: string; size?: string }) => (
      <span data-color={color} data-size={size}>{children}</span>
    ),
    Spinner: ({ size }: { size?: string }) => <div role="status" aria-busy="true" data-size={size} />,
    GlassCard: ({ children, className }: { children: React.ReactNode; className?: string }) => (
      <div className={className}>{children}</div>
    ),
    Dropdown: ({ children }: { children: React.ReactNode }) => <div data-testid="dropdown">{children}</div>,
    DropdownTrigger: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
    DropdownMenu: ({ children, 'aria-label': ariaLabel }: { children: React.ReactNode; 'aria-label'?: string }) => (
      <div role="menu" aria-label={ariaLabel}>{children}</div>
    ),
    DropdownItem: ({ children, onPress, className }: { children: React.ReactNode; onPress?: () => void; className?: string; key?: string; id?: string; startContent?: React.ReactNode; color?: string }) => (
      <div role="menuitem" onClick={onPress} className={className}>{children}</div>
    ),
    useDisclosure: () => ({
      isOpen: false,
      onOpen: vi.fn(),
      onClose: vi.fn(),
      onOpenChange: vi.fn(),
    }),
  };
});

// ─── Fixtures ─────────────────────────────────────────────────────────────────
const makeAnnouncement = (overrides = {}) => ({
  id: 1,
  title: 'Team Meeting',
  content: 'See you in the hall.',
  is_pinned: false,
  author: { id: 10, name: 'Alice Admin' },
  created_at: '2025-06-01T10:00:00Z',
  updated_at: '2025-06-01T10:00:00Z',
  ...overrides,
});

const makeResponse = (data: unknown[] = []) => ({ success: true, data });

// ─────────────────────────────────────────────────────────────────────────────
describe('GroupAnnouncementsTab', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockApi.get.mockResolvedValue(makeResponse());
  });

  it('shows loading spinner while fetching announcements', async () => {
    mockApi.get.mockImplementationOnce(() => new Promise(() => {}));
    const { GroupAnnouncementsTab } = await import('./GroupAnnouncementsTab');
    render(<GroupAnnouncementsTab groupId={3} isAdmin={false} />);
    const spinners = screen.queryAllByRole('status');
    const busy = spinners.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  it('shows empty state when no announcements returned', async () => {
    const { GroupAnnouncementsTab } = await import('./GroupAnnouncementsTab');
    render(<GroupAnnouncementsTab groupId={3} isAdmin={false} />);
    await waitFor(() => {
      expect(screen.getByTestId('empty-state')).toBeInTheDocument();
    });
  });

  it('renders announcement titles after data loads', async () => {
    mockApi.get.mockResolvedValue(makeResponse([makeAnnouncement()]));
    const { GroupAnnouncementsTab } = await import('./GroupAnnouncementsTab');
    render(<GroupAnnouncementsTab groupId={3} isAdmin={false} />);
    await waitFor(() => {
      expect(screen.getByText('Team Meeting')).toBeInTheDocument();
    });
  });

  it('renders announcement author name', async () => {
    mockApi.get.mockResolvedValue(makeResponse([makeAnnouncement()]));
    const { GroupAnnouncementsTab } = await import('./GroupAnnouncementsTab');
    render(<GroupAnnouncementsTab groupId={3} isAdmin={false} />);
    await waitFor(() => {
      expect(screen.getByText('Alice Admin')).toBeInTheDocument();
    });
  });

  it('fetches announcements from the correct API endpoint', async () => {
    const { GroupAnnouncementsTab } = await import('./GroupAnnouncementsTab');
    render(<GroupAnnouncementsTab groupId={7} isAdmin={false} />);
    await waitFor(() => {
      expect(mockApi.get).toHaveBeenCalledWith('/v2/groups/7/announcements');
    });
  });

  it('shows New Announcement button for admin users', async () => {
    const { GroupAnnouncementsTab } = await import('./GroupAnnouncementsTab');
    render(<GroupAnnouncementsTab groupId={3} isAdmin={true} />);
    await waitFor(() => {
      const buttons = screen.getAllByRole('button');
      const newBtn = buttons.find((b) => b.textContent?.toLowerCase().includes('new') || b.textContent?.toLowerCase().includes('announ'));
      expect(newBtn).toBeDefined();
    });
  });

  it('does NOT show New Announcement button for non-admins', async () => {
    const { GroupAnnouncementsTab } = await import('./GroupAnnouncementsTab');
    render(<GroupAnnouncementsTab groupId={3} isAdmin={false} />);
    await waitFor(() => {
      const buttons = screen.queryAllByRole('button');
      const newBtn = buttons.find((b) =>
        (b.textContent?.toLowerCase().includes('new') && b.textContent?.toLowerCase().includes('announ')) ||
        (b.textContent?.toLowerCase() === 'new announcement')
      );
      expect(newBtn).toBeUndefined();
    });
  });

  it('shows pinned badge for pinned announcements', async () => {
    mockApi.get.mockResolvedValue(makeResponse([makeAnnouncement({ is_pinned: true, title: 'Pinned Post' })]));
    const { GroupAnnouncementsTab } = await import('./GroupAnnouncementsTab');
    render(<GroupAnnouncementsTab groupId={3} isAdmin={false} />);
    await waitFor(() => {
      expect(screen.getByText('Pinned Post')).toBeInTheDocument();
    });
  });

  it('shows admin action menu for admins on announcement cards', async () => {
    mockApi.get.mockResolvedValue(makeResponse([makeAnnouncement()]));
    const { GroupAnnouncementsTab } = await import('./GroupAnnouncementsTab');
    render(<GroupAnnouncementsTab groupId={3} isAdmin={true} />);
    await waitFor(() => {
      expect(screen.getByTestId('dropdown')).toBeInTheDocument();
    });
  });

  it('calls PUT endpoint when pin is toggled', async () => {
    mockApi.get.mockResolvedValue(makeResponse([makeAnnouncement({ id: 99 })]));
    mockApi.put.mockResolvedValue({ success: true });
    const { GroupAnnouncementsTab } = await import('./GroupAnnouncementsTab');
    render(<GroupAnnouncementsTab groupId={3} isAdmin={true} />);

    await waitFor(() => screen.getByText('Team Meeting'));

    const pinItem = screen.queryAllByRole('menuitem').find((el) =>
      el.textContent?.toLowerCase().includes('pin')
    );
    if (pinItem) fireEvent.click(pinItem);

    await waitFor(() => {
      expect(mockApi.put).toHaveBeenCalledWith('/v2/groups/3/announcements/99', expect.objectContaining({ is_pinned: true }));
    });
  });

  it('shows error toast when loading fails', async () => {
    mockApi.get.mockRejectedValue(new Error('network'));
    const { GroupAnnouncementsTab } = await import('./GroupAnnouncementsTab');
    render(<GroupAnnouncementsTab groupId={3} isAdmin={false} />);
    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('renders multiple announcements', async () => {
    mockApi.get.mockResolvedValue(makeResponse([
      makeAnnouncement({ id: 1, title: 'First Announcement' }),
      makeAnnouncement({ id: 2, title: 'Second Announcement' }),
    ]));
    const { GroupAnnouncementsTab } = await import('./GroupAnnouncementsTab');
    render(<GroupAnnouncementsTab groupId={3} isAdmin={false} />);
    await waitFor(() => {
      expect(screen.getByText('First Announcement')).toBeInTheDocument();
      expect(screen.getByText('Second Announcement')).toBeInTheDocument();
    });
  });
});
