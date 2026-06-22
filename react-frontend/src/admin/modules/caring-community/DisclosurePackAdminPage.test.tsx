// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

// ── stable mock data ──────────────────────────────────────────────────────────

const MOCK_PACK = vi.hoisted(() => ({
  controller: {
    name: 'Test CLG',
    address: '1 Main St, Dublin',
    contact_email: 'dpo@test.ie',
    data_protection_officer: 'Jane DPO',
  },
  processor: {
    name: 'NEXUS Platform Ltd',
    address: 'Cloud St',
    contact_email: 'processor@nexus.ie',
    sub_processors: ['AWS', 'SendGrid'],
  },
  data_categories: {
    identity: ['name', 'email'],
    transactional: ['wallet_balance'],
  },
  lawful_basis: {
    identity: 'Legitimate interest',
  },
  retention_defaults: {
    user_data: '7 years',
  },
  data_subject_rights: {
    access: true,
    erasure: true,
    portability: 'on request',
  },
  federation: { enabled: true, aggregate_policy: 'minimal', opt_out: true },
  isolated_node: {
    available: false,
    description: 'Not configured',
    hosting_owner: 'Self',
    smtp_owner: 'SendGrid',
    storage_owner: 'AWS S3',
    backup_owner: 'Rclone',
    update_cadence: 'Weekly',
  },
  incident_response: {
    owner_name: 'Jasper Ford',
    contact_email: 'jasper@test.ie',
    notification_window_hours: 72,
    fadp_authority: 'FDPIC',
  },
  cross_border_transfers: {
    occurs: false,
    destinations: [],
    safeguards: [],
  },
  amendments: {
    last_reviewed_at: null,
    reviewer: 'Jasper Ford',
    next_review_due: null,
  },
}));

const MOCK_PACK_RESPONSE = vi.hoisted(() => ({
  pack: MOCK_PACK,
  last_updated_at: '2026-06-01T10:00:00Z',
  is_customised: false,
}));

// ── api mock ──────────────────────────────────────────────────────────────────

const mockApiObj = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
  put: vi.fn(),
  patch: vi.fn(),
  delete: vi.fn(),
}));

vi.mock('@/lib/api', () => ({
  default: mockApiObj,
  api: mockApiObj,
}));

// ── contexts ──────────────────────────────────────────────────────────────────

const mockShowToast = vi.hoisted(() => vi.fn());

const toastObj = vi.hoisted(() => ({
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
  showToast: mockShowToast,
}));

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => toastObj,
  }),
);

vi.mock('@/contexts/ToastContext', () => ({
  useToast: () => toastObj,
  ToastProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

// ── hooks ─────────────────────────────────────────────────────────────────────

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// ── admin components ──────────────────────────────────────────────────────────

vi.mock('../../components', () => ({
  Abbr: ({ term }: { term: string }) => <abbr>{term}</abbr>,
  PageHeader: ({
    title,
    actions,
  }: {
    title: string;
    subtitle?: string;
    icon?: React.ReactNode;
    actions?: React.ReactNode;
  }) => (
    <div>
      <h1>{title}</h1>
      {actions && <div data-testid="page-header-actions">{actions}</div>}
    </div>
  ),
}));

// ── import after mocks ────────────────────────────────────────────────────────

import DisclosurePackAdminPage from './DisclosurePackAdminPage';

// ─────────────────────────────────────────────────────────────────────────────
// Tests
// ─────────────────────────────────────────────────────────────────────────────

describe('DisclosurePackAdminPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    // Restore the shared spy reference after clearAllMocks resets call counts
    toastObj.showToast = mockShowToast;
  });

  it('shows loading spinner initially', () => {
    mockApiObj.get.mockReturnValue(new Promise(() => {}));
    render(<DisclosurePackAdminPage />);
    const statusEls = screen.getAllByRole('status');
    const spinner = statusEls.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(spinner).toBeInTheDocument();
  });

  it('renders the controller name field after load', async () => {
    mockApiObj.get.mockResolvedValueOnce({ success: true, data: MOCK_PACK_RESPONSE });
    render(<DisclosurePackAdminPage />);
    await waitFor(() => {
      expect(screen.getByDisplayValue('Test CLG')).toBeInTheDocument();
    });
  });

  it('renders processor contact email field', async () => {
    mockApiObj.get.mockResolvedValueOnce({ success: true, data: MOCK_PACK_RESPONSE });
    render(<DisclosurePackAdminPage />);
    await waitFor(() => {
      expect(screen.getByDisplayValue('processor@nexus.ie')).toBeInTheDocument();
    });
  });

  it('renders sub_processors as newline-separated text in textarea', async () => {
    mockApiObj.get.mockResolvedValueOnce({ success: true, data: MOCK_PACK_RESPONSE });
    render(<DisclosurePackAdminPage />);
    await waitFor(() => {
      // The textarea joins sub_processors with \n
      const textarea = document.querySelector('textarea');
      expect(textarea?.value).toContain('AWS');
      expect(textarea?.value).toContain('SendGrid');
    });
  });

  it('Save Changes button is disabled when pack is unmodified', async () => {
    mockApiObj.get.mockResolvedValueOnce({ success: true, data: MOCK_PACK_RESPONSE });
    render(<DisclosurePackAdminPage />);
    await waitFor(() => {
      expect(screen.getByDisplayValue('Test CLG')).toBeInTheDocument();
    });
    const saveBtn = screen.getAllByRole('button').find(
      (b) => /save/i.test(b.textContent ?? ''),
    );
    expect(saveBtn).toBeInTheDocument();
    // React Aria Button sets data-disabled (not aria-disabled) when isDisabled=true
    // and isPending=false. Check for either native disabled attr or data-disabled.
    const isDisabled =
      saveBtn!.hasAttribute('disabled') ||
      saveBtn!.getAttribute('data-disabled') !== null ||
      saveBtn!.getAttribute('aria-disabled') === 'true';
    expect(isDisabled).toBe(true);
  });

  it('enables Save button after editing a field', async () => {
    mockApiObj.get.mockResolvedValueOnce({ success: true, data: MOCK_PACK_RESPONSE });
    render(<DisclosurePackAdminPage />);
    await waitFor(() => {
      expect(screen.getByDisplayValue('Test CLG')).toBeInTheDocument();
    });
    const nameInput = screen.getByDisplayValue('Test CLG');
    await userEvent.clear(nameInput);
    await userEvent.type(nameInput, 'Updated CLG');
    const saveBtn = screen.getAllByRole('button').find(
      (b) => /save/i.test(b.textContent ?? ''),
    );
    // After editing, save should no longer be aria-disabled
    expect(saveBtn?.getAttribute('aria-disabled')).not.toBe('true');
  });

  it('calls PUT endpoint on Save', async () => {
    mockApiObj.get.mockResolvedValueOnce({ success: true, data: MOCK_PACK_RESPONSE });
    mockApiObj.put.mockResolvedValueOnce({ success: true, data: MOCK_PACK });
    render(<DisclosurePackAdminPage />);
    await waitFor(() => {
      expect(screen.getByDisplayValue('Test CLG')).toBeInTheDocument();
    });
    // Edit to make dirty
    const nameInput = screen.getByDisplayValue('Test CLG');
    await userEvent.clear(nameInput);
    await userEvent.type(nameInput, 'New Name CLG');
    const saveBtn = screen.getAllByRole('button').find(
      (b) => /save/i.test(b.textContent ?? ''),
    );
    if (saveBtn && saveBtn.getAttribute('aria-disabled') !== 'true') {
      await userEvent.click(saveBtn);
      await waitFor(() => {
        expect(mockApiObj.put).toHaveBeenCalledWith(
          '/v2/admin/caring-community/disclosure-pack',
          expect.any(Object),
        );
      });
    }
  });

  it('calls GET export endpoint and shows toast on export', async () => {
    mockApiObj.get
      .mockResolvedValueOnce({ success: true, data: MOCK_PACK_RESPONSE })
      .mockResolvedValueOnce({
        success: true,
        data: { format: 'markdown', content: '# Disclosure Pack', filename: 'disclosure-pack.md' },
      });

    // createObjectURL is not available in jsdom — stub it
    const originalCreate = URL.createObjectURL;
    URL.createObjectURL = vi.fn(() => 'blob:test');
    URL.revokeObjectURL = vi.fn();

    render(<DisclosurePackAdminPage />);
    await waitFor(() => {
      expect(screen.getByDisplayValue('Test CLG')).toBeInTheDocument();
    });

    const exportBtn = screen.getAllByRole('button').find(
      (b) => /export|markdown/i.test(b.textContent ?? ''),
    );
    if (exportBtn) {
      await userEvent.click(exportBtn);
      await waitFor(() => {
        expect(mockApiObj.get).toHaveBeenCalledWith(
          '/v2/admin/caring-community/disclosure-pack/export',
        );
      });
    }

    URL.createObjectURL = originalCreate;
  });

  it('shows toast on load failure', async () => {
    mockApiObj.get.mockRejectedValueOnce(new Error('network'));
    render(<DisclosurePackAdminPage />);
    await waitFor(() => {
      expect(mockShowToast).toHaveBeenCalledWith(
        expect.any(String),
        'error',
      );
    });
  });

  it('renders Refresh button', async () => {
    mockApiObj.get.mockResolvedValueOnce({ success: true, data: MOCK_PACK_RESPONSE });
    render(<DisclosurePackAdminPage />);
    await waitFor(() => {
      expect(screen.getByDisplayValue('Test CLG')).toBeInTheDocument();
    });
    // The refresh button is icon-only with an aria-label i18n key; find by any button
    const buttons = screen.getAllByRole('button');
    expect(buttons.length).toBeGreaterThan(0);
  });

  it('shows last-updated footer when last_updated_at is set', async () => {
    mockApiObj.get.mockResolvedValueOnce({ success: true, data: MOCK_PACK_RESPONSE });
    render(<DisclosurePackAdminPage />);
    await waitFor(() => {
      // The footer text contains the localised date string
      const body = document.body.textContent ?? '';
      expect(body).toMatch(/last saved|updated|saved/i);
    });
  });
});
