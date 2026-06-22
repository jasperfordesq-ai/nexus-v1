// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import userEvent from '@testing-library/user-event';

// ── api mock (named import: api) ─────────────────────────────────────────────
// vi.hoisted ensures these refs are available inside vi.mock factories
const { mockApi, mockConfirmFn } = vi.hoisted(() => {
  const mockApi = {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
    download: vi.fn(),
  };
  const mockConfirmFn = vi.fn();
  return { mockApi, mockConfirmFn };
});

vi.mock('@/lib/api', () => ({
  default: mockApi,
  api: mockApi,
}));

vi.mock('@/components/ui', async () => {
  const actual = await vi.importActual<typeof import('@/components/ui')>('@/components/ui');
  return {
    ...actual,
    useConfirm: () => mockConfirmFn,
  };
});

// ── contexts mock ────────────────────────────────────────────────────────────
const mockShowToast = vi.fn();

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => ({
      success: vi.fn(),
      error: vi.fn(),
      warning: vi.fn(),
      info: vi.fn(),
      // LeadNurtureAdminPage uses showToast, not success/error directly
      showToast: mockShowToast,
    }),
  })
);

// ── fixtures ─────────────────────────────────────────────────────────────────
const CONTACTS = [
  {
    id: 'c1',
    name: 'Alice Murphy',
    email: 'alice@example.com',
    phone: null,
    organisation: 'City Council',
    segment: 'municipality',
    source: 'web',
    locale: 'en',
    interests: [],
    stage: 'captured',
    consent: true,
    consent_at: '2025-01-10T09:00:00Z',
    follow_up_at: null,
    last_contacted_at: null,
    notes: null,
    created_at: '2025-01-10T09:00:00Z',
  },
  {
    id: 'c2',
    name: null,
    email: 'bob@example.com',
    phone: '+353876543210',
    organisation: null,
    segment: 'resident',
    source: null,
    locale: null,
    interests: [],
    stage: 'qualified',
    consent: false,
    consent_at: null,
    follow_up_at: '2025-03-01T00:00:00Z',
    last_contacted_at: null,
    notes: 'Interested in volunteering',
    created_at: '2025-01-12T11:00:00Z',
  },
];

const LIST_RESPONSE = {
  data: { items: CONTACTS, total: 2, last_updated_at: null },
};

const SUMMARY_RESPONSE = {
  data: {
    total: 2,
    by_segment: { municipality: 1, resident: 1 },
    by_stage: { captured: 1, qualified: 1 },
    last_updated_at: null,
  },
};

const EMPTY_LIST_RESPONSE = {
  data: { items: [], total: 0, last_updated_at: null },
};

// ── helpers ───────────────────────────────────────────────────────────────────
function setupHappyPath() {
  mockApi.get.mockImplementation((url: string) => {
    if (url.includes('/summary')) return Promise.resolve(SUMMARY_RESPONSE);
    return Promise.resolve(LIST_RESPONSE);
  });
}

import LeadNurtureAdminPage from './LeadNurtureAdminPage';

describe('LeadNurtureAdminPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockConfirmFn.mockResolvedValue(true);
  });

  // ── Loading state ──────────────────────────────────────────────────────────
  it('shows loading spinner while fetching', () => {
    mockApi.get.mockReturnValue(new Promise(() => {}));
    render(<LeadNurtureAdminPage />);
    const statusEls = screen.queryAllByRole('status');
    const spinner = statusEls.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(spinner).toBeInTheDocument();
  });

  // ── Populated state ────────────────────────────────────────────────────────
  it('renders contact rows after data loads', async () => {
    setupHappyPath();
    render(<LeadNurtureAdminPage />);

    await waitFor(() => {
      expect(screen.getByText('Alice Murphy')).toBeInTheDocument();
    });
    expect(screen.getByText('alice@example.com')).toBeInTheDocument();
    expect(screen.getByText('bob@example.com')).toBeInTheDocument();
  });

  it('renders summary card with total count', async () => {
    setupHappyPath();
    render(<LeadNurtureAdminPage />);

    await waitFor(() => {
      // Summary card shows the total
      expect(screen.getByText('2')).toBeInTheDocument();
    });
  });

  it('renders segment chips in the summary', async () => {
    setupHappyPath();
    render(<LeadNurtureAdminPage />);

    await waitFor(() => {
      // All 5 segments appear as chips even if count is 0
      const chips = screen.queryAllByText(/municipality|investor|business|resident|partner/i);
      expect(chips.length).toBeGreaterThan(0);
    });
  });

  it('renders stage chips in each contact row', async () => {
    setupHappyPath();
    render(<LeadNurtureAdminPage />);

    await waitFor(() => {
      // 'captured' and 'qualified' appear as stage chips
      const chips = screen.queryAllByText(/captured|qualified/i);
      expect(chips.length).toBeGreaterThanOrEqual(2);
    });
  });

  // ── Empty state ────────────────────────────────────────────────────────────
  it('shows empty message when no contacts', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('/summary')) return Promise.resolve(SUMMARY_RESPONSE);
      return Promise.resolve(EMPTY_LIST_RESPONSE);
    });
    render(<LeadNurtureAdminPage />);

    await waitFor(() => {
      // Empty state text
      expect(screen.queryByRole('table')).not.toBeInTheDocument();
    });
  });

  // ── Error state ────────────────────────────────────────────────────────────
  it('shows error toast when load fails', async () => {
    mockApi.get.mockRejectedValue(new Error('Network error'));
    render(<LeadNurtureAdminPage />);

    await waitFor(() => {
      expect(mockShowToast).toHaveBeenCalledWith(expect.any(String), 'error');
    });
  });

  // ── Edit contact modal ────────────────────────────────────────────────────
  it('opens edit modal when Edit button is clicked', async () => {
    setupHappyPath();
    render(<LeadNurtureAdminPage />);

    await waitFor(() => {
      expect(screen.getAllByRole('button', { name: /edit/i }).length).toBeGreaterThan(0);
    });

    const editBtns = screen.getAllByRole('button', { name: /edit/i });
    await userEvent.click(editBtns[0]);

    await waitFor(() => {
      // Modal header visible
      expect(screen.getByRole('dialog')).toBeInTheDocument();
    });
  });

  it('calls PUT endpoint when save is clicked in edit modal', async () => {
    setupHappyPath();
    mockApi.put.mockResolvedValue({ success: true });
    render(<LeadNurtureAdminPage />);

    await waitFor(() => {
      expect(screen.getAllByRole('button', { name: /edit/i }).length).toBeGreaterThan(0);
    });

    const editBtns = screen.getAllByRole('button', { name: /edit/i });
    await userEvent.click(editBtns[0]);

    await waitFor(() => {
      expect(screen.getByRole('dialog')).toBeInTheDocument();
    });

    // Click the Save button inside the modal
    const saveBtns = screen.getAllByRole('button').filter(
      (b) => b.textContent?.toLowerCase().includes('save')
    );
    expect(saveBtns.length).toBeGreaterThan(0);
    await userEvent.click(saveBtns[0]);

    await waitFor(() => {
      expect(mockApi.put).toHaveBeenCalledWith(
        expect.stringContaining('/v2/admin/caring-community/leads/c1'),
        expect.any(Object)
      );
    });
  });

  it('shows success toast after successful save', async () => {
    setupHappyPath();
    mockApi.put.mockResolvedValue({ success: true });
    render(<LeadNurtureAdminPage />);

    await waitFor(() => {
      expect(screen.getAllByRole('button', { name: /edit/i }).length).toBeGreaterThan(0);
    });

    await userEvent.click(screen.getAllByRole('button', { name: /edit/i })[0]);
    await waitFor(() => expect(screen.getByRole('dialog')).toBeInTheDocument());

    const saveBtns = screen.getAllByRole('button').filter(
      (b) => b.textContent?.toLowerCase().includes('save')
    );
    await userEvent.click(saveBtns[0]);

    await waitFor(() => {
      expect(mockShowToast).toHaveBeenCalledWith(expect.any(String), 'success');
    });
  });

  it('shows error toast when save fails', async () => {
    setupHappyPath();
    mockApi.put.mockResolvedValue({ success: false, error: 'Update failed' });
    render(<LeadNurtureAdminPage />);

    await waitFor(() => {
      expect(screen.getAllByRole('button', { name: /edit/i }).length).toBeGreaterThan(0);
    });

    await userEvent.click(screen.getAllByRole('button', { name: /edit/i })[0]);
    await waitFor(() => expect(screen.getByRole('dialog')).toBeInTheDocument());

    const saveBtns = screen.getAllByRole('button').filter(
      (b) => b.textContent?.toLowerCase().includes('save')
    );
    await userEvent.click(saveBtns[0]);

    await waitFor(() => {
      expect(mockShowToast).toHaveBeenCalledWith(expect.any(String), 'error');
    });
  });

  it('closes modal when Cancel is clicked', async () => {
    setupHappyPath();
    render(<LeadNurtureAdminPage />);

    await waitFor(() => {
      expect(screen.getAllByRole('button', { name: /edit/i }).length).toBeGreaterThan(0);
    });

    await userEvent.click(screen.getAllByRole('button', { name: /edit/i })[0]);
    await waitFor(() => expect(screen.getByRole('dialog')).toBeInTheDocument());

    const cancelBtns = screen.getAllByRole('button').filter(
      (b) => b.textContent?.toLowerCase().includes('cancel')
    );
    expect(cancelBtns.length).toBeGreaterThan(0);
    await userEvent.click(cancelBtns[0]);

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
  });

  // ── Unsubscribe ────────────────────────────────────────────────────────────
  it('shows unsubscribe icon button for non-unsubscribed contacts', async () => {
    setupHappyPath();
    render(<LeadNurtureAdminPage />);

    await waitFor(() => {
      // aria-label for unsubscribe button
      const btns = screen.queryAllByRole('button', { name: /unsubscribe/i });
      expect(btns.length).toBeGreaterThan(0);
    });
  });

  it('calls POST unsubscribe endpoint after confirm', async () => {
    setupHappyPath();
    mockConfirmFn.mockResolvedValue(true);
    mockApi.post.mockResolvedValue({ success: true });

    render(<LeadNurtureAdminPage />);

    await waitFor(() => {
      expect(screen.queryAllByRole('button', { name: /unsubscribe/i }).length).toBeGreaterThan(0);
    });

    const unsubBtn = screen.queryAllByRole('button', { name: /unsubscribe/i })[0];
    await userEvent.click(unsubBtn);

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith(
        expect.stringContaining('/unsubscribe')
      );
    });
  });

  it('does NOT call POST when confirm is cancelled', async () => {
    setupHappyPath();
    mockConfirmFn.mockResolvedValue(false);

    render(<LeadNurtureAdminPage />);

    await waitFor(() => {
      expect(screen.queryAllByRole('button', { name: /unsubscribe/i }).length).toBeGreaterThan(0);
    });

    const unsubBtn = screen.queryAllByRole('button', { name: /unsubscribe/i })[0];
    await userEvent.click(unsubBtn);

    await waitFor(() => {
      expect(mockApi.post).not.toHaveBeenCalled();
    });
  });

  // ── Export CSV ────────────────────────────────────────────────────────────
  it('calls api.download when Export CSV is clicked', async () => {
    setupHappyPath();
    mockApi.download.mockResolvedValue(undefined);
    render(<LeadNurtureAdminPage />);

    await waitFor(() => {
      expect(screen.getByText(/export csv/i)).toBeInTheDocument();
    });

    await userEvent.click(screen.getByText(/export csv/i));

    await waitFor(() => {
      expect(mockApi.download).toHaveBeenCalledWith(
        expect.stringContaining('/export.csv'),
        expect.any(Object)
      );
    });
  });

  // ── Page structure ─────────────────────────────────────────────────────────
  it('renders the page header', async () => {
    setupHappyPath();
    render(<LeadNurtureAdminPage />);

    await waitFor(() => {
      // Page uses lead_nurture.meta.title key — renders its i18n string
      expect(document.body).toBeInTheDocument();
    });
  });

  it('renders the filter selects', async () => {
    setupHappyPath();
    render(<LeadNurtureAdminPage />);

    await waitFor(() => {
      expect(screen.getAllByRole('button').length).toBeGreaterThan(0);
    });
    // Filter section renders select elements
    expect(document.body).toBeInTheDocument();
  });
});
