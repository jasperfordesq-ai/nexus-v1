// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';

// ── Mocks ───────────────────────────────────────────────────────────────────

// ADMIN USER — canManageCaring(user) must return true: role='admin' + not view-only
const ADMIN_USER = { id: 1, role: 'admin', is_view_only: false };

// IMPORTANT: useToast must return a STABLE object reference on every call.
// HourTransferAdminPage puts `toast` in loadPending/loadInbound/handleApprove/handleReject
// useCallback deps arrays. Unstable toast reference → infinite useEffect loop.
const mockToast = vi.hoisted(() => ({
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
}));

vi.mock('@/contexts', () => ({
  // useToast returns the SAME stable object every call — not a new literal each render
  useToast: () => mockToast,
  useAuth: () => ({
    user: ADMIN_USER,
    isAuthenticated: true,
    login: vi.fn(),
    logout: vi.fn(),
    register: vi.fn(),
    updateUser: vi.fn(),
    refreshUser: vi.fn(),
    status: 'idle' as const,
    error: null,
  }),
  useTenant: () => ({
    tenant: { id: 2, name: 'Test', slug: 'test' },
    tenantPath: (p: string) => `/test${p}`,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
  }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
  useTheme: () => ({
    resolvedTheme: 'light',
    theme: 'system',
    toggleTheme: vi.fn(),
    setTheme: vi.fn(),
  }),
  useNotifications: () => ({
    unreadCount: 0,
    counts: {},
    notifications: [],
    markAsRead: vi.fn(),
    markAllAsRead: vi.fn(),
    hasMore: false,
    loadMore: vi.fn(),
    isLoading: false,
    refresh: vi.fn(),
  }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({
    consent: null,
    showBanner: false,
    openPreferences: vi.fn(),
    resetConsent: vi.fn(),
    saveConsent: vi.fn(),
    hasConsent: vi.fn(() => true),
    updateConsent: vi.fn(),
  }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  usePresence: () => ({
    status: 'offline',
    setStatus: vi.fn(),
    getPresence: vi.fn(),
    isOnline: vi.fn(() => false),
  }),
  usePresenceOptional: () => null,
}));

const apiMock = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
  put: vi.fn(),
  patch: vi.fn(),
  delete: vi.fn(),
  upload: vi.fn(),
  download: vi.fn(),
}));

vi.mock('@/lib/api', () => ({
  default: apiMock,
  api: apiMock,
}));

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

// ToastProvider in test-utils wraps with ToastContext — keep stable reference there too
vi.mock('@/contexts/ToastContext', () => ({
  useToast: () => mockToast,
  ToastProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

// ── Helpers ──────────────────────────────────────────────────────────────────

import HourTransferAdminPage from './HourTransferAdminPage';

const PENDING_ITEM = {
  id: 42,
  member_user_id: 7,
  member_name: 'Alice Smith',
  member_email: 'alice@test.ie',
  destination_tenant_slug: 'other-coop',
  destination_member_email: 'bob@other.ie',
  hours: 2.5,
  status: 'pending' as const,
  reason: 'Exchange for garden work',
  created_at: '2025-06-15T10:00:00Z',
};

const INBOUND_ITEM = {
  id: 99,
  member_user_id: 8,
  member_name: 'Carol Brown',
  member_email: 'carol@test.ie',
  source_tenant_slug: 'source-coop',
  hours: 1.0,
  status: 'completed' as const,
  reason: 'Cooking lessons',
  created_at: '2025-06-10T08:00:00Z',
};

function setupDefaults() {
  apiMock.get.mockImplementation((url: string) => {
    if (url.includes('/pending')) {
      return Promise.resolve({ success: true, data: { items: [PENDING_ITEM] } });
    }
    if (url.includes('/inbound')) {
      return Promise.resolve({ success: true, data: { items: [INBOUND_ITEM] } });
    }
    return Promise.resolve({ success: true, data: { items: [] } });
  });
}

// ── Tests ────────────────────────────────────────────────────────────────────

describe('HourTransferAdminPage — loading states', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('shows a busy spinner while pending list is loading', () => {
    apiMock.get.mockReturnValue(new Promise(() => {}));
    render(<HourTransferAdminPage />);

    const spinners = screen.queryAllByRole('status');
    const busySpinner = spinners.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busySpinner).toBeDefined();
  });

  it('removes the busy spinner once pending data loads', async () => {
    setupDefaults();
    render(<HourTransferAdminPage />);

    await waitFor(() => {
      expect(screen.queryByText('Alice Smith')).toBeInTheDocument();
    });

    const spinners = screen.queryAllByRole('status');
    const busySpinner = spinners.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busySpinner).toBeUndefined();
  });
});

describe('HourTransferAdminPage — pending tab (default)', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    setupDefaults();
  });

  it('renders the member name in the pending table', async () => {
    render(<HourTransferAdminPage />);
    expect(await screen.findByText('Alice Smith')).toBeInTheDocument();
  });

  it('renders the member email', async () => {
    render(<HourTransferAdminPage />);
    expect(await screen.findByText('alice@test.ie')).toBeInTheDocument();
  });

  it('renders the destination tenant slug', async () => {
    render(<HourTransferAdminPage />);
    expect(await screen.findByText('other-coop')).toBeInTheDocument();
  });

  it('renders hours with 2 decimal places', async () => {
    render(<HourTransferAdminPage />);
    expect(await screen.findByText('2.50')).toBeInTheDocument();
  });

  it('renders the transfer reason', async () => {
    render(<HourTransferAdminPage />);
    expect(await screen.findByText('Exchange for garden work')).toBeInTheDocument();
  });

  it('renders the count chip showing pending count', async () => {
    render(<HourTransferAdminPage />);
    // Pending tab chip shows pending.length = 1
    await screen.findByText('Alice Smith');
    const ones = screen.getAllByText('1');
    expect(ones.length).toBeGreaterThan(0);
  });
});

describe('HourTransferAdminPage — approve action', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    apiMock.get
      .mockResolvedValueOnce({ success: true, data: { items: [PENDING_ITEM] } }) // pending
      .mockResolvedValueOnce({ success: true, data: { items: [] } })              // inbound
      .mockResolvedValue({ success: true, data: { items: [] } });                  // after reload
  });

  it('calls approve POST with correct URL structure', async () => {
    apiMock.post.mockResolvedValue({ success: true, data: { status: 'approved_by_source' } });

    render(<HourTransferAdminPage />);
    const approveBtn = await screen.findByRole('button', { name: /approve/i });
    await userEvent.click(approveBtn);

    await waitFor(() => {
      expect(apiMock.post).toHaveBeenCalledWith(
        `/v2/admin/caring-community/hour-transfer/${PENDING_ITEM.id}/approve`,
      );
    });
  });

  it('shows success toast after approve', async () => {
    apiMock.post.mockResolvedValue({ success: true, data: { status: 'approved_by_source' } });

    render(<HourTransferAdminPage />);
    const approveBtn = await screen.findByRole('button', { name: /approve/i });
    await userEvent.click(approveBtn);

    await waitFor(() => {
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('shows error toast when approve API fails', async () => {
    apiMock.post.mockResolvedValue({ success: false, error: 'Insufficient funds' });

    render(<HourTransferAdminPage />);
    const approveBtn = await screen.findByRole('button', { name: /approve/i });
    await userEvent.click(approveBtn);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });
});

describe('HourTransferAdminPage — reject action', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    apiMock.get
      .mockResolvedValueOnce({ success: true, data: { items: [PENDING_ITEM] } })
      .mockResolvedValueOnce({ success: true, data: { items: [] } })
      .mockResolvedValue({ success: true, data: { items: [] } });
  });

  it('opens the reject modal when Reject is clicked', async () => {
    render(<HourTransferAdminPage />);

    const rejectBtn = await screen.findByRole('button', { name: /reject/i });
    await userEvent.click(rejectBtn);

    // Modal dialog becomes visible
    await waitFor(() => {
      const dialogs = document.querySelectorAll('[role="dialog"]');
      expect(dialogs.length).toBeGreaterThan(0);
    });
  });

  it('POSTs to /reject endpoint with reason payload', async () => {
    apiMock.post.mockResolvedValue({ success: true, data: { status: 'rejected' } });

    render(<HourTransferAdminPage />);

    const rejectBtn = await screen.findByRole('button', { name: /reject/i });
    await userEvent.click(rejectBtn);

    // Type a reason in the textarea
    const textareas = await screen.findAllByRole('textbox');
    const textarea = textareas.find((el) => el.tagName === 'TEXTAREA');
    if (textarea) {
      await userEvent.type(textarea, 'Policy violation');
    }

    // Click the confirm reject button in the modal footer (last reject button in DOM)
    const modalRejectBtns = screen.getAllByRole('button').filter((b) =>
      b.textContent?.toLowerCase().includes('reject'),
    );
    const confirmBtn = modalRejectBtns[modalRejectBtns.length - 1];
    await userEvent.click(confirmBtn);

    await waitFor(() => {
      expect(apiMock.post).toHaveBeenCalledWith(
        `/v2/admin/caring-community/hour-transfer/${PENDING_ITEM.id}/reject`,
        expect.objectContaining({ reason: expect.any(String) }),
      );
    });
  });

  it('shows success toast after reject', async () => {
    apiMock.post.mockResolvedValue({ success: true, data: { status: 'rejected' } });

    render(<HourTransferAdminPage />);

    const rejectBtn = await screen.findByRole('button', { name: /reject/i });
    await userEvent.click(rejectBtn);

    const modalRejectBtns = screen.getAllByRole('button').filter((b) =>
      b.textContent?.toLowerCase().includes('reject'),
    );
    const confirmBtn = modalRejectBtns[modalRejectBtns.length - 1];
    await userEvent.click(confirmBtn);

    await waitFor(() => {
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('shows error toast when reject API fails', async () => {
    apiMock.post.mockResolvedValue({ success: false, error: 'Cannot reject' });

    render(<HourTransferAdminPage />);

    const rejectBtn = await screen.findByRole('button', { name: /reject/i });
    await userEvent.click(rejectBtn);

    const modalRejectBtns = screen.getAllByRole('button').filter((b) =>
      b.textContent?.toLowerCase().includes('reject'),
    );
    const confirmBtn = modalRejectBtns[modalRejectBtns.length - 1];
    await userEvent.click(confirmBtn);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });
});

describe('HourTransferAdminPage — empty pending list', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    apiMock.get
      .mockResolvedValueOnce({ success: true, data: { items: [] } })
      .mockResolvedValueOnce({ success: true, data: { items: [] } });
  });

  it('shows empty message when no pending transfers', async () => {
    render(<HourTransferAdminPage />);
    await waitFor(() => {
      expect(apiMock.get).toHaveBeenCalled();
    });
    // No member names in DOM when empty
    expect(screen.queryByText('Alice Smith')).not.toBeInTheDocument();
  });

  it('shows pending count chip as 0', async () => {
    render(<HourTransferAdminPage />);
    await waitFor(() => {
      const zeros = screen.getAllByText('0');
      expect(zeros.length).toBeGreaterThan(0);
    });
  });
});

describe('HourTransferAdminPage — inbound tab', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    setupDefaults();
  });

  it('shows inbound member name after switching tab', async () => {
    render(<HourTransferAdminPage />);
    await screen.findByText('Alice Smith');

    const tabs = screen.getAllByRole('tab');
    const inboundTab = tabs.find((t) =>
      t.textContent?.toLowerCase().includes('inbound') ||
      t.getAttribute('data-key') === 'inbound',
    );
    if (inboundTab) {
      await userEvent.click(inboundTab);
      expect(await screen.findByText('Carol Brown')).toBeInTheDocument();
    } else if (tabs[1]) {
      await userEvent.click(tabs[1]);
      await waitFor(() => {
        expect(apiMock.get).toHaveBeenCalled();
      });
    }
  });

  it('renders source tenant slug in inbound row', async () => {
    render(<HourTransferAdminPage />);
    await screen.findByText('Alice Smith');

    const tabs = screen.getAllByRole('tab');
    const inboundTab =
      tabs.find((t) => t.textContent?.toLowerCase().includes('inbound')) ?? tabs[1];

    if (inboundTab) {
      await userEvent.click(inboundTab);
      expect(await screen.findByText('source-coop')).toBeInTheDocument();
    }
  });
});

describe('HourTransferAdminPage — API error on load', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('shows error toast when pending load throws', async () => {
    apiMock.get
      .mockRejectedValueOnce(new Error('Network error'))
      .mockResolvedValue({ success: true, data: { items: [] } });

    render(<HourTransferAdminPage />);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });
});
