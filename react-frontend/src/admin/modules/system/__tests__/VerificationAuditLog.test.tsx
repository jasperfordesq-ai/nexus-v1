// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for VerificationAuditLog admin component
 *
 * Tests:
 * - Renders with loading spinner initially
 * - Shows empty state when no events
 * - Renders event table with data
 * - Filter by event type works
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { HeroUIProvider } from '@heroui/react';

// ─── Common mocks ────────────────────────────────────────────────────────────

const mockGet = vi.fn();

vi.mock('@/lib/api', () => ({
  api: {
    get: (...args: unknown[]) => mockGet(...args),
    post: vi.fn().mockResolvedValue({ success: true }),
    put: vi.fn().mockResolvedValue({ success: true }),
    delete: vi.fn().mockResolvedValue({ success: true }),
  },
  tokenManager: { getTenantId: vi.fn(), getAccessToken: vi.fn() },
}));

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    user: { id: 1, first_name: 'Admin', last_name: 'User', name: 'Admin User', role: 'admin', is_super_admin: true, tenant_id: 2 },
    isAuthenticated: true,
    logout: vi.fn(),
  })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Community', slug: 'test', configuration: {} },
    tenantSlug: 'test',
    branding: { name: 'Test Community' },
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
    tenantPath: (p: string) => `/test${p}`,
  })),
  useToast: vi.fn(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), showToast: vi.fn() })),
  useNotifications: vi.fn(() => ({ counts: { messages: 0, notifications: 0 } })),
}));

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// ─── Helpers ─────────────────────────────────────────────────────────────────

function renderWithProviders(ui: React.ReactElement) {
  return render(
    <HeroUIProvider>
      <MemoryRouter>{ui}</MemoryRouter>
    </HeroUIProvider>
  );
}

const mockAuditEvents = [
  {
    id: 1,
    user_id: 10,
    session_id: 100,
    event_type: 'verification_started',
    actor_type: 'user',
    actor_id: 10,
    details: '{"provider":"mock"}',
    ip_address: '192.168.1.1',
    user_agent: 'Mozilla/5.0',
    created_at: '2026-03-07T10:00:00Z',
    first_name: 'Jane',
    last_name: 'Doe',
    user_email: 'jane@example.com',
  },
  {
    id: 2,
    user_id: 11,
    session_id: 101,
    event_type: 'verification_passed',
    actor_type: 'system',
    actor_id: null,
    details: null,
    ip_address: '10.0.0.1',
    user_agent: null,
    created_at: '2026-03-07T11:00:00Z',
    first_name: 'John',
    last_name: 'Smith',
    user_email: 'john@example.com',
  },
  {
    id: 3,
    user_id: 12,
    session_id: 102,
    event_type: 'admin_approved',
    actor_type: 'admin',
    actor_id: 1,
    details: '{"reason":"Looks good"}',
    ip_address: null,
    user_agent: null,
    created_at: '2026-03-07T12:00:00Z',
    first_name: null,
    last_name: null,
    user_email: 'anon@example.com',
  },
];

// ─── Tests ───────────────────────────────────────────────────────────────────

describe('VerificationAuditLog', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders with loading spinner initially', async () => {
    // Never resolve to keep loading state
    mockGet.mockReturnValue(new Promise(() => {}));

    const { default: VerificationAuditLog } = await import('../VerificationAuditLog');
    renderWithProviders(<VerificationAuditLog />);

    expect(screen.getByText('Verification Audit Log')).toBeTruthy();
    // HeroUI Spinner renders with role="status"
    expect(document.querySelector('[role="status"]') || document.querySelector('.animate-spinner-ease-spin')).toBeTruthy();
  });

  it('shows empty state when no events', async () => {
    mockGet.mockResolvedValue({ success: true, data: { events: [], total: 0 } });

    const { default: VerificationAuditLog } = await import('../VerificationAuditLog');
    renderWithProviders(<VerificationAuditLog />);

    await waitFor(() => {
      expect(screen.getByText('No verification events yet.')).toBeTruthy();
    });
  });

  it('renders event table with data', async () => {
    mockGet.mockResolvedValue({ success: true, data: { events: mockAuditEvents, total: 3 } });

    const { default: VerificationAuditLog } = await import('../VerificationAuditLog');
    renderWithProviders(<VerificationAuditLog />);

    await waitFor(() => {
      expect(screen.getByText('Jane Doe')).toBeTruthy();
    });

    // Check user names
    expect(screen.getByText('John Smith')).toBeTruthy();
    expect(screen.getByText('User #12')).toBeTruthy(); // null first/last name fallback

    // Check emails
    expect(screen.getByText('jane@example.com')).toBeTruthy();
    expect(screen.getByText('john@example.com')).toBeTruthy();

    // Check event type chips (also present in the filter Select options, so use getAllByText)
    expect(screen.getAllByText('Started').length).toBeGreaterThanOrEqual(1);
    expect(screen.getAllByText('Passed').length).toBeGreaterThanOrEqual(1);
    expect(screen.getAllByText('Admin Approved').length).toBeGreaterThanOrEqual(1);

    // Check actor types
    expect(screen.getByText('user')).toBeTruthy();
    expect(screen.getByText('system')).toBeTruthy();
    expect(screen.getByText('admin')).toBeTruthy();

    // Check IP addresses
    expect(screen.getByText('192.168.1.1')).toBeTruthy();
    expect(screen.getByText('10.0.0.1')).toBeTruthy();

    // Check total events chip
    expect(screen.getByText('3 events')).toBeTruthy();

    // Check table headers
    expect(screen.getByText('TIME')).toBeTruthy();
    expect(screen.getByText('USER')).toBeTruthy();
    expect(screen.getByText('EVENT')).toBeTruthy();
    expect(screen.getByText('ACTOR')).toBeTruthy();
    expect(screen.getByText('IP')).toBeTruthy();
    expect(screen.getByText('DETAILS')).toBeTruthy();
  });

  it('renders parsed JSON details', async () => {
    mockGet.mockResolvedValue({
      success: true,
      data: { events: [mockAuditEvents[0]], total: 1 },
    });

    const { default: VerificationAuditLog } = await import('../VerificationAuditLog');
    renderWithProviders(<VerificationAuditLog />);

    await waitFor(() => {
      expect(screen.getByText('provider: mock')).toBeTruthy();
    });
  });

  it('calls API with correct parameters', async () => {
    mockGet.mockResolvedValue({ success: true, data: { events: [], total: 0 } });

    const { default: VerificationAuditLog } = await import('../VerificationAuditLog');
    renderWithProviders(<VerificationAuditLog />);

    await waitFor(() => {
      expect(mockGet).toHaveBeenCalledWith(
        expect.stringContaining('/v2/admin/identity/audit-log')
      );
    });

    // Check default query params
    expect(mockGet).toHaveBeenCalledWith(
      expect.stringContaining('limit=25')
    );
    expect(mockGet).toHaveBeenCalledWith(
      expect.stringContaining('offset=0')
    );
  });

  it('renders refresh button', async () => {
    mockGet.mockResolvedValue({ success: true, data: { events: [], total: 0 } });

    const { default: VerificationAuditLog } = await import('../VerificationAuditLog');
    renderWithProviders(<VerificationAuditLog />);

    await waitFor(() => {
      expect(screen.getByLabelText('Refresh')).toBeTruthy();
    });
  });

  it('renders filter dropdown', async () => {
    mockGet.mockResolvedValue({ success: true, data: { events: [], total: 0 } });

    const { default: VerificationAuditLog } = await import('../VerificationAuditLog');
    renderWithProviders(<VerificationAuditLog />);

    await waitFor(() => {
      expect(screen.getByText('No verification events yet.')).toBeTruthy();
    });

    // The Select component with "All events" placeholder
    const selectTrigger = document.querySelector('[data-slot="trigger"]');
    expect(selectTrigger).toBeTruthy();
  });

  it('does not show pagination when total is less than page size', async () => {
    mockGet.mockResolvedValue({ success: true, data: { events: mockAuditEvents, total: 3 } });

    const { default: VerificationAuditLog } = await import('../VerificationAuditLog');
    renderWithProviders(<VerificationAuditLog />);

    await waitFor(() => {
      expect(screen.getByText('Jane Doe')).toBeTruthy();
    });

    // With only 3 events and PAGE_SIZE=25, no pagination should show
    expect(screen.queryByText('Previous')).toBeFalsy();
    expect(screen.queryByText('Next')).toBeFalsy();
  });

  it('shows pagination when total exceeds page size', async () => {
    mockGet.mockResolvedValue({ success: true, data: { events: mockAuditEvents, total: 50 } });

    const { default: VerificationAuditLog } = await import('../VerificationAuditLog');
    renderWithProviders(<VerificationAuditLog />);

    await waitFor(() => {
      expect(screen.getByText('Jane Doe')).toBeTruthy();
    });

    expect(screen.getByText('Page 1 of 2')).toBeTruthy();
    expect(screen.getByText('Previous')).toBeTruthy();
    expect(screen.getByText('Next')).toBeTruthy();
  });

  it('handles API failure silently', async () => {
    mockGet.mockRejectedValue(new Error('Server error'));

    const { default: VerificationAuditLog } = await import('../VerificationAuditLog');
    renderWithProviders(<VerificationAuditLog />);

    // After error, loading should stop and empty state should show
    await waitFor(() => {
      expect(screen.getByText('No verification events yet.')).toBeTruthy();
    });
  });
});
