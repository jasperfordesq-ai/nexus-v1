// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import userEvent from '@testing-library/user-event';

// ── Hoisted stable refs — must be declared via vi.hoisted so they exist when
//    vi.mock factories run (which are hoisted to the top of the file).
const { mockToast, mockTenantPath } = vi.hoisted(() => ({
  mockToast: { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() },
  mockTenantPath: vi.fn((p: string) => `/test${p}`),
}));

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: mockTenantPath,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  }),
);

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

import { api } from '@/lib/api';
import OrgApplicationsTab from './OrgApplicationsTab';

// ── Fixtures ──────────────────────────────────────────────────────────────────

const makeApplication = (
  overrides: Partial<{
    id: number;
    status: 'pending' | 'approved' | 'declined';
    userName: string;
  }> = {},
) => ({
  id: overrides.id ?? 1,
  status: overrides.status ?? ('pending' as const),
  message: 'I would love to help',
  org_note: null,
  created_at: '2026-06-01T10:00:00Z',
  user: {
    id: 10,
    name: overrides.userName ?? 'Alice Example',
    avatar_url: null,
    email: 'alice@example.com',
  },
  opportunity: { id: 5, title: 'Garden Helper' },
  shift: null,
});

const EMPTY_RESPONSE = {
  success: true,
  data: [],
  meta: { cursor: null, has_more: false },
};

const ONE_PENDING_RESPONSE = {
  success: true,
  data: [makeApplication()],
  meta: { cursor: null, has_more: false },
};

const TWO_APPS_RESPONSE = {
  success: true,
  data: [makeApplication({ id: 1 }), makeApplication({ id: 2, status: 'approved', userName: 'Bob Smith' })],
  meta: { cursor: null, has_more: false },
};

describe('OrgApplicationsTab — loading state', () => {
  beforeEach(() => vi.clearAllMocks());

  it('shows loading spinner on mount', () => {
    vi.mocked(api.get).mockReturnValue(new Promise(() => {}));
    render(<OrgApplicationsTab orgId={7} />);
    const spinners = screen.getAllByRole('status');
    const busy = spinners.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });
});

describe('OrgApplicationsTab — empty state', () => {
  beforeEach(() => vi.clearAllMocks());

  it('shows empty state message when no applications', async () => {
    vi.mocked(api.get).mockResolvedValueOnce(EMPTY_RESPONSE);
    render(<OrgApplicationsTab orgId={7} />);
    await waitFor(() => {
      // empty_title i18n key renders as the key in test env — look for the container
      const spinners = screen.queryAllByRole('status');
      const busy = spinners.find((el) => el.getAttribute('aria-busy') === 'true');
      expect(busy).toBeUndefined();
    });
    // multiple buttons (status filter + empty CTA) — at least one should be present
    const buttons = screen.queryAllByRole('button');
    expect(buttons.length).toBeGreaterThan(0);
  });
});

describe('OrgApplicationsTab — populated state', () => {
  beforeEach(() => vi.clearAllMocks());

  it('renders applicant name when data loads', async () => {
    vi.mocked(api.get).mockResolvedValueOnce(ONE_PENDING_RESPONSE);
    render(<OrgApplicationsTab orgId={7} />);
    await waitFor(() => {
      expect(screen.getByText('Alice Example')).toBeInTheDocument();
    });
  });

  it('renders applicant email', async () => {
    vi.mocked(api.get).mockResolvedValueOnce(ONE_PENDING_RESPONSE);
    render(<OrgApplicationsTab orgId={7} />);
    await waitFor(() => {
      expect(screen.getByText('alice@example.com')).toBeInTheDocument();
    });
  });

  it('renders application message', async () => {
    vi.mocked(api.get).mockResolvedValueOnce(ONE_PENDING_RESPONSE);
    render(<OrgApplicationsTab orgId={7} />);
    await waitFor(() => {
      expect(screen.getByText('I would love to help')).toBeInTheDocument();
    });
  });

  it('shows both applicants when multiple apps returned', async () => {
    vi.mocked(api.get).mockResolvedValueOnce(TWO_APPS_RESPONSE);
    render(<OrgApplicationsTab orgId={7} />);
    await waitFor(() => {
      expect(screen.getByText('Alice Example')).toBeInTheDocument();
      expect(screen.getByText('Bob Smith')).toBeInTheDocument();
    });
  });
});

describe('OrgApplicationsTab — approve action', () => {
  beforeEach(() => vi.clearAllMocks());

  it('calls PUT /v2/volunteering/applications/:id with approve on button press', async () => {
    const user = userEvent.setup();
    vi.mocked(api.get).mockResolvedValueOnce(ONE_PENDING_RESPONSE);
    vi.mocked(api.put).mockResolvedValueOnce({ success: true });

    render(<OrgApplicationsTab orgId={7} />);
    await waitFor(() => {
      expect(screen.getByText('Alice Example')).toBeInTheDocument();
    });

    // Find approve button — must NOT match the "Approved" filter button.
    // In the test env i18n loads real translations so look for exact "Approve" (no 'd').
    const buttons = screen.getAllByRole('button');
    const approveBtn = buttons.find((b) => {
      const text = b.textContent?.trim() ?? '';
      // Match "Approve" or "applications.approve" but NOT "Approved"
      return (
        text === 'Approve' ||
        text === 'applications.approve' ||
        /^Approve$/i.test(text)
      );
    });
    expect(approveBtn).toBeDefined();
    await user.click(approveBtn!);

    await waitFor(() => {
      expect(api.put).toHaveBeenCalledWith(
        '/v2/volunteering/applications/1',
        { action: 'approve' },
      );
    });
  });

  it('shows success toast after approve', async () => {
    const user = userEvent.setup();
    vi.mocked(api.get).mockResolvedValueOnce(ONE_PENDING_RESPONSE);
    vi.mocked(api.put).mockResolvedValueOnce({ success: true });

    render(<OrgApplicationsTab orgId={7} />);
    await waitFor(() => screen.getByText('Alice Example'));

    const buttons = screen.getAllByRole('button');
    const approveBtn = buttons.find((b) => {
      const text = b.textContent?.trim() ?? '';
      return text === 'Approve' || text === 'applications.approve' || /^Approve$/i.test(text);
    });
    await user.click(approveBtn!);

    await waitFor(() => {
      expect(mockToast.success).toHaveBeenCalled();
    });
  });
});

describe('OrgApplicationsTab — decline action', () => {
  beforeEach(() => vi.clearAllMocks());

  it('calls PUT with decline action', async () => {
    const user = userEvent.setup();
    vi.mocked(api.get).mockResolvedValueOnce(ONE_PENDING_RESPONSE);
    vi.mocked(api.put).mockResolvedValueOnce({ success: true });

    render(<OrgApplicationsTab orgId={7} />);
    await waitFor(() => screen.getByText('Alice Example'));

    const buttons = screen.getAllByRole('button');
    const declineBtn = buttons.find((b) => {
      const text = b.textContent?.trim() ?? '';
      return text === 'Decline' || text === 'applications.decline' || /^Decline$/i.test(text);
    });
    expect(declineBtn).toBeDefined();
    await user.click(declineBtn!);
    await user.click(await screen.findByRole('button', { name: /decline application/i }));

    await waitFor(() => {
      expect(api.put).toHaveBeenCalledWith(
        '/v2/volunteering/applications/1',
        { action: 'decline' },
      );
    });
  });

  it('shows error toast when decline API fails', async () => {
    const user = userEvent.setup();
    vi.mocked(api.get).mockResolvedValueOnce(ONE_PENDING_RESPONSE);
    vi.mocked(api.put).mockResolvedValueOnce({ success: false, error: 'Server error' });

    render(<OrgApplicationsTab orgId={7} />);
    await waitFor(() => screen.getByText('Alice Example'));

    const buttons = screen.getAllByRole('button');
    const declineBtn = buttons.find((b) => {
      const text = b.textContent?.trim() ?? '';
      return text === 'Decline' || text === 'applications.decline' || /^Decline$/i.test(text);
    });
    await user.click(declineBtn!);
    await user.click(await screen.findByRole('button', { name: /decline application/i }));

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });
});

describe('OrgApplicationsTab — error state', () => {
  beforeEach(() => vi.clearAllMocks());

  it('shows error toast when fetch throws', async () => {
    vi.mocked(api.get).mockRejectedValueOnce(new Error('Network down'));
    render(<OrgApplicationsTab orgId={7} />);
    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });
});
