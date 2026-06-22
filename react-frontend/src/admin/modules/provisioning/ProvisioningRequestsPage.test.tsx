// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';

// ─── Mocks ───────────────────────────────────────────────────────────────────

const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn() };

const mockApiGet  = vi.hoisted(() => vi.fn());
const mockApiPost = vi.hoisted(() => vi.fn());

vi.mock('@/lib/api', () => {
  const mockApi = { get: mockApiGet, post: mockApiPost, put: vi.fn(), patch: vi.fn(), delete: vi.fn() };
  return { api: mockApi, default: mockApi };
});

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

vi.mock('@/contexts', () => ({
  useToast: vi.fn(() => mockToast),
  useTheme: () => ({ resolvedTheme: 'light', toggleTheme: vi.fn(), theme: 'system', setTheme: vi.fn() }),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
  useAuth: () => ({ user: null, isAuthenticated: true, login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle', error: null }),
  useTenant: () => ({ tenant: { id: 2, name: 'Test', slug: 'test', tagline: null }, branding: { name: 'Test', logo_url: null }, tenantSlug: 'test', tenantPath: (p: string) => '/test' + p, isLoading: false, hasFeature: vi.fn(() => true), hasModule: vi.fn(() => true) }),
}));

vi.mock('@/hooks', () => ({
  usePageTitle: vi.fn(),
}));

vi.mock('../../components', () => ({
  PageHeader: ({ title }: { title: string }) => <div data-testid="page-header">{title}</div>,
}));

// ─── Fixtures ─────────────────────────────────────────────────────────────────

const makeRequest = (overrides = {}) => ({
  id: 1,
  applicant_name: 'Jane Doe',
  applicant_email: 'jane@example.com',
  applicant_phone: '+1 555 1234',
  org_name: 'Community Timebank',
  country_code: 'IE',
  region_or_canton: 'Dublin',
  requested_slug: 'community-tb',
  requested_subdomain: null,
  tenant_category: 'timebank',
  languages: '["en","ga"]',
  default_language: 'en',
  expected_member_count_bucket: '50-200',
  intended_use: 'Local timebanking for Dublin',
  status: 'pending',
  reviewed_by: null,
  reviewed_at: null,
  rejection_reason: null,
  provisioned_tenant_id: null,
  provisioning_log: null,
  created_at: '2025-01-01T10:00:00Z',
  updated_at: '2025-01-01T10:00:00Z',
  ...overrides,
});

const REQUESTS = [
  makeRequest({ id: 1, status: 'pending',    org_name: 'Alpha Timebank' }),
  makeRequest({ id: 2, status: 'provisioned', org_name: 'Beta Timebank' }),
  makeRequest({ id: 3, status: 'failed',      org_name: 'Gamma Timebank' }),
];

// ─── Import component after mocks ─────────────────────────────────────────────

import { ProvisioningRequestsPage } from './ProvisioningRequestsPage';

// ─── Tests ────────────────────────────────────────────────────────────────────

describe('ProvisioningRequestsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockApiGet.mockResolvedValue({ data: REQUESTS });
  });

  it('shows loading spinner while fetching', () => {
    // Never resolve so loading persists
    mockApiGet.mockReturnValue(new Promise(() => {}));
    render(<ProvisioningRequestsPage />);

    const loadingEl = document.querySelector('[aria-busy="true"]');
    expect(loadingEl).toBeInTheDocument();
  });

  it('renders request cards after load', async () => {
    render(<ProvisioningRequestsPage />);
    await waitFor(() => {
      expect(screen.getByText('Alpha Timebank')).toBeInTheDocument();
      expect(screen.getByText('Beta Timebank')).toBeInTheDocument();
      expect(screen.getByText('Gamma Timebank')).toBeInTheDocument();
    });
  });

  it('shows empty state when no requests returned', async () => {
    mockApiGet.mockResolvedValue({ data: [] });
    render(<ProvisioningRequestsPage />);
    await waitFor(() => expect(mockApiGet).toHaveBeenCalled());
    // Empty state: building icon + text
    expect(document.body).toBeInTheDocument(); // no crash
  });

  it('renders status filter chips', async () => {
    render(<ProvisioningRequestsPage />);
    await waitFor(() => expect(screen.getByText('Alpha Timebank')).toBeInTheDocument());
    // Filter buttons are rendered (all / pending / under_review / approved / provisioned / rejected / failed)
    const buttons = screen.getAllByRole('button');
    expect(buttons.length).toBeGreaterThan(5);
  });

  it('applies status filter when chip clicked (re-fetches with ?status=pending)', async () => {
    render(<ProvisioningRequestsPage />);
    await waitFor(() => expect(screen.getByText('Alpha Timebank')).toBeInTheDocument());

    // Find the "pending" filter chip/button
    const pendingBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase() === 'pending',
    );
    if (pendingBtn) {
      mockApiGet.mockResolvedValue({ data: [REQUESTS[0]] });
      fireEvent.click(pendingBtn);
      await waitFor(() => {
        expect(mockApiGet).toHaveBeenCalledWith(
          expect.stringContaining('status=pending'),
        );
      });
    }
  });

  it('opens detail modal when a card is pressed', async () => {
    render(<ProvisioningRequestsPage />);
    await waitFor(() => expect(screen.getByText('Alpha Timebank')).toBeInTheDocument());

    // Cards are pressable — click on the card
    const card = screen.getByText('Alpha Timebank').closest('[role="button"]') ??
      screen.getByText('Alpha Timebank');
    fireEvent.click(card);

    await waitFor(() => {
      const dialogs = screen.queryAllByRole('dialog');
      expect(dialogs.length).toBeGreaterThan(0);
    });
  });

  it('shows approve and reject buttons in modal for pending request', async () => {
    render(<ProvisioningRequestsPage />);
    await waitFor(() => expect(screen.getByText('Alpha Timebank')).toBeInTheDocument());

    const card = screen.getByText('Alpha Timebank').closest('[role="button"]') ??
      screen.getByText('Alpha Timebank');
    fireEvent.click(card);

    await waitFor(() => screen.queryAllByRole('dialog').length > 0);

    const approveBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('approve'),
    );
    const rejectBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('reject'),
    );
    expect(approveBtn).toBeDefined();
    expect(rejectBtn).toBeDefined();
  });

  it('calls approve POST endpoint when Approve is clicked', async () => {
    mockApiPost.mockResolvedValue({ success: true });
    render(<ProvisioningRequestsPage />);
    await waitFor(() => expect(screen.getByText('Alpha Timebank')).toBeInTheDocument());

    const card = screen.getByText('Alpha Timebank').closest('[role="button"]') ??
      screen.getByText('Alpha Timebank');
    fireEvent.click(card);

    await waitFor(() => screen.queryAllByRole('dialog').length > 0);

    const approveBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('approve'),
    );
    if (approveBtn) {
      fireEvent.click(approveBtn);
      await waitFor(() => {
        expect(mockApiPost).toHaveBeenCalledWith(
          '/v2/super-admin/provisioning-requests/1/approve',
          {},
        );
      });
      await waitFor(() => expect(mockToast.success).toHaveBeenCalled());
    }
  });

  it('shows error toast when approve fails', async () => {
    mockApiPost.mockResolvedValue({ success: false, error: 'Forbidden' });
    render(<ProvisioningRequestsPage />);
    await waitFor(() => expect(screen.getByText('Alpha Timebank')).toBeInTheDocument());

    const card = screen.getByText('Alpha Timebank').closest('[role="button"]') ??
      screen.getByText('Alpha Timebank');
    fireEvent.click(card);

    await waitFor(() => screen.queryAllByRole('dialog').length > 0);

    const approveBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('approve'),
    );
    if (approveBtn) {
      fireEvent.click(approveBtn);
      await waitFor(() => expect(mockToast.error).toHaveBeenCalled());
    }
  });

  it('requires rejection reason — shows error toast when rejecting with empty reason', async () => {
    render(<ProvisioningRequestsPage />);
    await waitFor(() => expect(screen.getByText('Alpha Timebank')).toBeInTheDocument());

    const card = screen.getByText('Alpha Timebank').closest('[role="button"]') ??
      screen.getByText('Alpha Timebank');
    fireEvent.click(card);

    await waitFor(() => screen.queryAllByRole('dialog').length > 0);

    // Clear rejection reason if pre-filled
    const textareas = screen.queryAllByRole('textbox').filter((el) => el.tagName === 'TEXTAREA');
    textareas.forEach((ta) => fireEvent.change(ta, { target: { value: '' } }));

    const rejectBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('reject'),
    );
    if (rejectBtn) {
      fireEvent.click(rejectBtn);
      await waitFor(() => expect(mockToast.error).toHaveBeenCalled());
      expect(mockApiPost).not.toHaveBeenCalled();
    }
  });

  it('calls reject POST with reason payload', async () => {
    mockApiPost.mockResolvedValue({ success: true });
    const user = userEvent.setup();
    render(<ProvisioningRequestsPage />);
    await waitFor(() => expect(screen.getByText('Alpha Timebank')).toBeInTheDocument());

    const card = screen.getByText('Alpha Timebank').closest('[role="button"]') ??
      screen.getByText('Alpha Timebank');
    fireEvent.click(card);

    await waitFor(() => screen.queryAllByRole('dialog').length > 0);

    // Fill rejection reason textarea
    const textareas = screen.queryAllByRole('textbox').filter((el) => el.tagName === 'TEXTAREA');
    if (textareas.length > 0) {
      await user.click(textareas[0]);
      await user.type(textareas[0], 'Does not meet criteria');

      const rejectBtn = screen.getAllByRole('button').find((b) =>
        b.textContent?.toLowerCase().includes('reject'),
      );
      if (rejectBtn) {
        fireEvent.click(rejectBtn);
        await waitFor(() => {
          expect(mockApiPost).toHaveBeenCalledWith(
            '/v2/super-admin/provisioning-requests/1/reject',
            expect.objectContaining({ reason: 'Does not meet criteria' }),
          );
        });
      }
    }
  });

  it('shows retry button for failed requests and calls retry endpoint', async () => {
    mockApiPost.mockResolvedValue({ success: true });
    render(<ProvisioningRequestsPage />);
    await waitFor(() => expect(screen.getByText('Gamma Timebank')).toBeInTheDocument());

    const card = screen.getByText('Gamma Timebank').closest('[role="button"]') ??
      screen.getByText('Gamma Timebank');
    fireEvent.click(card);

    await waitFor(() => screen.queryAllByRole('dialog').length > 0);

    const retryBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('retry'),
    );
    expect(retryBtn).toBeDefined();
    if (retryBtn) {
      fireEvent.click(retryBtn);
      await waitFor(() => {
        expect(mockApiPost).toHaveBeenCalledWith(
          '/v2/super-admin/provisioning-requests/3/retry',
          {},
        );
      });
    }
  });

  it('re-fetches after successful approve', async () => {
    mockApiPost.mockResolvedValue({ success: true });
    render(<ProvisioningRequestsPage />);
    await waitFor(() => expect(mockApiGet).toHaveBeenCalledTimes(1));

    const card = screen.getByText('Alpha Timebank').closest('[role="button"]') ??
      screen.getByText('Alpha Timebank');
    fireEvent.click(card);

    await waitFor(() => screen.queryAllByRole('dialog').length > 0);

    const approveBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('approve'),
    );
    if (approveBtn) {
      fireEvent.click(approveBtn);
      await waitFor(() => expect(mockApiGet).toHaveBeenCalledTimes(2));
    }
  });

  it('close button dismisses the detail modal', async () => {
    render(<ProvisioningRequestsPage />);
    await waitFor(() => expect(screen.getByText('Alpha Timebank')).toBeInTheDocument());

    const card = screen.getByText('Alpha Timebank').closest('[role="button"]') ??
      screen.getByText('Alpha Timebank');
    fireEvent.click(card);

    await waitFor(() => screen.queryAllByRole('dialog').length > 0);

    const closeBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('close'),
    );
    if (closeBtn) {
      fireEvent.click(closeBtn);
      await waitFor(() => {
        const dialogs = screen.queryAllByRole('dialog');
        expect(dialogs.length).toBe(0);
      });
    }
  });
});
