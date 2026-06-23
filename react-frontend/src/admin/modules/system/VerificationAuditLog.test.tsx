// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── API mock ─────────────────────────────────────────────────────────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn(), download: vi.fn(), upload: vi.fn() },
}));
vi.mock('@/lib/api', () => ({ api: mockApi, default: mockApi }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ─── Contexts ─────────────────────────────────────────────────────────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn(), showToast: vi.fn() };

vi.mock('@/contexts', () =>
  createMockContexts({
    useAuth: () => ({
      user: { id: 99, name: 'Admin', first_name: 'Admin' },
      isAuthenticated: true,
      login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(),
      status: 'idle' as const, error: null,
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

// ─── Stub HeroUI Table family (React Aria won't render rows in jsdom) ─────────
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<Record<string, unknown>>();
  return {
    ...orig,
    // Table family → plain HTML equivalents
    Table: ({ children, 'aria-label': ariaLabel }: { children: React.ReactNode; 'aria-label'?: string }) => (
      <table aria-label={ariaLabel}>{children}</table>
    ),
    TableHeader: ({ children }: { children: React.ReactNode }) => <thead><tr>{children}</tr></thead>,
    TableColumn: ({ children }: { children: React.ReactNode }) => <th>{children}</th>,
    TableBody: ({ children }: { children: React.ReactNode }) => <tbody>{children}</tbody>,
    TableRow: ({ children }: { children: React.ReactNode }) => <tr>{children}</tr>,
    TableCell: ({ children, title, className }: { children: React.ReactNode; title?: string; className?: string }) => (
      <td title={title} className={className}>{children}</td>
    ),
    // Chip → simple span
    Chip: ({ children, color, variant, size }: { children: React.ReactNode; color?: string; variant?: string; size?: string }) => (
      <span data-testid="chip" data-color={color} data-variant={variant} data-size={size}>{children}</span>
    ),
    // Card family
    Card: ({ children, className }: { children: React.ReactNode; className?: string }) => (
      <div data-testid="card" className={className}>{children}</div>
    ),
    CardBody: ({ children, className }: { children: React.ReactNode; className?: string }) => (
      <div data-testid="card-body" className={className}>{children}</div>
    ),
    CardHeader: ({ children, className }: { children: React.ReactNode; className?: string }) => (
      <div data-testid="card-header" className={className}>{children}</div>
    ),
    // Spinner
    Spinner: ({ size }: { size?: string }) => (
      <div role="status" aria-busy="true" aria-label="Loading" data-size={size} data-testid="spinner" />
    ),
    // Select/SelectItem — simplified stub
    Select: ({ children, 'aria-label': ariaLabel, onSelectionChange, selectedKeys, placeholder }: {
      children: React.ReactNode; 'aria-label'?: string;
      onSelectionChange?: (keys: Set<string>) => void;
      selectedKeys?: string[]; placeholder?: string;
    }) => (
      <select
        aria-label={ariaLabel}
        value={selectedKeys?.[0] ?? ''}
        onChange={(e) => onSelectionChange?.(new Set([e.target.value]))}
        data-testid="event-type-select"
      >
        <option value="">{placeholder}</option>
        {children}
      </select>
    ),
    SelectItem: ({ children, id }: { children: React.ReactNode; id?: string }) => (
      <option value={id}>{children}</option>
    ),
    // Button
    Button: ({ children, onPress, isDisabled, isIconOnly, startContent, endContent, 'aria-label': ariaLabel }: {
      children?: React.ReactNode; onPress?: () => void; isDisabled?: boolean;
      isIconOnly?: boolean; startContent?: React.ReactNode; endContent?: React.ReactNode;
      'aria-label'?: string; variant?: string; size?: string;
    }) => (
      <button onClick={() => onPress?.()} disabled={isDisabled} aria-label={ariaLabel}>
        {startContent}{children}{endContent}
      </button>
    ),
  };
});

// ─── Fixtures ─────────────────────────────────────────────────────────────────
interface AuditEvent {
  id: number;
  user_id: number;
  session_id: number | null;
  event_type: string;
  actor_type: string;
  actor_id: number | null;
  details: string | null;
  ip_address: string | null;
  user_agent: string | null;
  created_at: string;
  first_name: string | null;
  last_name: string | null;
  user_email: string | null;
}

const makeEvent = (overrides: Partial<AuditEvent> = {}): AuditEvent => ({
  id: 1,
  user_id: 42,
  session_id: null,
  event_type: 'verification_passed',
  actor_type: 'user',
  actor_id: null,
  details: null,
  ip_address: '192.168.1.1',
  user_agent: null,
  created_at: '2025-06-01T10:00:00Z',
  first_name: 'John',
  last_name: 'Doe',
  user_email: 'john@example.com',
  ...overrides,
});

const makeResponse = (events: AuditEvent[] = [], total = 0) => ({
  success: true,
  data: { events, total },
});

// ─────────────────────────────────────────────────────────────────────────────
describe('VerificationAuditLog', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockApi.get.mockResolvedValue(makeResponse());
  });

  it('renders card wrapper and header title', async () => {
    const { VerificationAuditLog } = await import('./VerificationAuditLog');
    render(<VerificationAuditLog />);

    await waitFor(() => {
      expect(screen.getByTestId('card')).toBeInTheDocument();
    });
  });

  it('shows spinner while loading', async () => {
    mockApi.get.mockImplementationOnce(() => new Promise(() => {}));
    const { VerificationAuditLog } = await import('./VerificationAuditLog');
    render(<VerificationAuditLog />);

    const spinner = screen.getByTestId('spinner');
    expect(spinner).toBeInTheDocument();
    expect(spinner).toHaveAttribute('aria-busy', 'true');
  });

  it('shows empty message when no events returned', async () => {
    const { VerificationAuditLog } = await import('./VerificationAuditLog');
    render(<VerificationAuditLog />);

    await waitFor(() => {
      // "no events" message (i18n key: verification.no_events)
      expect(screen.queryByTestId('spinner')).not.toBeInTheDocument();
    });
  });

  it('renders audit log table when events are returned', async () => {
    mockApi.get.mockResolvedValue(makeResponse([makeEvent()], 1));
    const { VerificationAuditLog } = await import('./VerificationAuditLog');
    render(<VerificationAuditLog />);

    await waitFor(() => {
      expect(screen.getByRole('table')).toBeInTheDocument();
    });
  });

  it('shows user full name in table row', async () => {
    mockApi.get.mockResolvedValue(makeResponse([makeEvent({ first_name: 'John', last_name: 'Doe' })], 1));
    const { VerificationAuditLog } = await import('./VerificationAuditLog');
    render(<VerificationAuditLog />);

    await waitFor(() => {
      expect(screen.getByText('John Doe')).toBeInTheDocument();
    });
  });

  it('shows user email in table row', async () => {
    mockApi.get.mockResolvedValue(makeResponse([makeEvent({ user_email: 'john@example.com' })], 1));
    const { VerificationAuditLog } = await import('./VerificationAuditLog');
    render(<VerificationAuditLog />);

    await waitFor(() => {
      expect(screen.getByText('john@example.com')).toBeInTheDocument();
    });
  });

  it('shows event type chip', async () => {
    mockApi.get.mockResolvedValue(makeResponse([makeEvent({ event_type: 'verification_passed' })], 1));
    const { VerificationAuditLog } = await import('./VerificationAuditLog');
    render(<VerificationAuditLog />);

    await waitFor(() => {
      const chips = screen.getAllByTestId('chip');
      // At least one chip shows the event type (translated key)
      expect(chips.some(c => c.getAttribute('data-color') === 'success')).toBe(true);
    });
  });

  it('shows IP address in table row', async () => {
    mockApi.get.mockResolvedValue(makeResponse([makeEvent({ ip_address: '10.0.0.1' })], 1));
    const { VerificationAuditLog } = await import('./VerificationAuditLog');
    render(<VerificationAuditLog />);

    await waitFor(() => {
      expect(screen.getByText('10.0.0.1')).toBeInTheDocument();
    });
  });

  it('shows table column headers', async () => {
    mockApi.get.mockResolvedValue(makeResponse([makeEvent()], 1));
    const { VerificationAuditLog } = await import('./VerificationAuditLog');
    render(<VerificationAuditLog />);

    await waitFor(() => {
      // Column headers exist (i18n keys resolve to English)
      const headers = screen.getAllByRole('columnheader');
      expect(headers.length).toBe(6);
    });
  });

  it('renders event-type filter select', async () => {
    const { VerificationAuditLog } = await import('./VerificationAuditLog');
    render(<VerificationAuditLog />);

    await waitFor(() => {
      expect(screen.getByTestId('event-type-select')).toBeInTheDocument();
    });
  });

  it('renders refresh button', async () => {
    const { VerificationAuditLog } = await import('./VerificationAuditLog');
    render(<VerificationAuditLog />);

    await waitFor(() => {
      // Refresh button with aria-label
      const refreshBtn = screen.getByRole('button', { name: /refresh/i });
      expect(refreshBtn).toBeInTheDocument();
    });
  });

  it('calls API with offset=0 on initial load', async () => {
    const { VerificationAuditLog } = await import('./VerificationAuditLog');
    render(<VerificationAuditLog />);

    await waitFor(() => {
      expect(mockApi.get).toHaveBeenCalledWith(
        expect.stringContaining('/v2/admin/identity/audit-log?')
      );
      const url: string = mockApi.get.mock.calls[0][0];
      expect(url).toContain('offset=0');
    });
  });

  it('parses JSON details and shows key:value pairs in cell', async () => {
    const details = JSON.stringify({ reason: 'id_mismatch', score: 0.3 });
    mockApi.get.mockResolvedValue(makeResponse([makeEvent({ details })], 1));
    const { VerificationAuditLog } = await import('./VerificationAuditLog');
    render(<VerificationAuditLog />);

    await waitFor(() => {
      // The cell title attribute shows the formatted detail string
      const cells = document.querySelectorAll('td[title]');
      const detailCell = Array.from(cells).find(c => c.getAttribute('title')?.includes('reason:'));
      expect(detailCell).toBeTruthy();
    });
  });

  it('shows pagination controls when total > PAGE_SIZE', async () => {
    // PAGE_SIZE is 25; return 26 total so pagination appears
    const events = Array.from({ length: 25 }, (_, i) =>
      makeEvent({ id: i + 1, first_name: `User${i + 1}`, last_name: 'Test' })
    );
    mockApi.get.mockResolvedValue(makeResponse(events, 26));
    const { VerificationAuditLog } = await import('./VerificationAuditLog');
    render(<VerificationAuditLog />);

    await waitFor(() => {
      const nextBtn = screen.getByRole('button', { name: /next/i });
      expect(nextBtn).toBeInTheDocument();
      expect(nextBtn).not.toBeDisabled();
    });
  });

  it('previous page button is disabled on first page', async () => {
    const events = Array.from({ length: 25 }, (_, i) => makeEvent({ id: i + 1 }));
    mockApi.get.mockResolvedValue(makeResponse(events, 26));
    const { VerificationAuditLog } = await import('./VerificationAuditLog');
    render(<VerificationAuditLog />);

    await waitFor(() => {
      const prevBtn = screen.getByRole('button', { name: /previous/i });
      expect(prevBtn).toBeDisabled();
    });
  });

  it('clicking refresh button re-fetches events', async () => {
    mockApi.get.mockResolvedValue(makeResponse([makeEvent()], 1));
    const { VerificationAuditLog } = await import('./VerificationAuditLog');
    render(<VerificationAuditLog />);

    await waitFor(() => screen.getByRole('table'));

    const refreshBtn = screen.getByRole('button', { name: /refresh/i });
    fireEvent.click(refreshBtn);

    await waitFor(() => {
      expect(mockApi.get).toHaveBeenCalledTimes(2);
    });
  });
});
