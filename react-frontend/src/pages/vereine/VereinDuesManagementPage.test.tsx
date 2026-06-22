// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent, act } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── Mock api ─────────────────────────────────────────────────────────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}));

vi.mock('@/lib/api', () => ({
  api: mockApi,
  default: mockApi,
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ─── Stub @/components/ui with lightweight div-based stubs ───────────────────
// HeroUI Table in jsdom renders row content into <template> (never in DOM).
// The uiMock proxy provides plain div/button/input stubs that render children,
// so TableCell content (e.g. first_name) is actually queryable.
vi.mock('@/components/ui', async () => (await import('@/test/uiMock')).uiMock);

// ─── Router — provide id param ─────────────────────────────────────────────────
vi.mock('react-router-dom', async (importOriginal) => {
  const orig = await importOriginal<typeof import('react-router-dom')>();
  return {
    ...orig,
    useParams: () => ({ id: '7' }),
  };
});

// ─── Toast / Contexts ─────────────────────────────────────────────────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };

vi.mock('@/contexts', () =>
  createMockContexts({
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
vi.mock('@/components/seo/PageMeta', () => ({ PageMeta: () => null }));

// ─── Fixtures ─────────────────────────────────────────────────────────────────
const makeFeeConfig = (overrides = {}) => ({
  success: true,
  data: {
    fee_config: {
      id: 1,
      fee_amount_cents: 5000,
      currency: 'CHF',
      billing_cycle: 'annual',
      grace_period_days: 30,
      late_fee_cents: null,
      is_active: true,
      ...overrides,
    },
  },
});

const makeDuesRow = (overrides = {}) => ({
  id: 100,
  user_id: 10,
  membership_year: 2025,
  amount_cents: 5000,
  currency: 'CHF',
  status: 'pending',
  due_date: '2025-03-31',
  paid_at: null,
  reminder_count: 0,
  last_reminder_at: null,
  generated_email_sent_at: null,
  generated_email_failed_at: null,
  paid_email_sent_at: null,
  paid_email_failed_at: null,
  reminder_email_failed_at: null,
  reminder_email_last_error: null,
  waived_reason: null,
  first_name: 'Hans',
  last_name: 'Müller',
  email: 'hans@example.ch',
  ...overrides,
});

const makeDuesList = (rows = [] as object[]) => ({
  success: true,
  data: {
    items: rows,
    total: rows.length,
    page: 1,
    per_page: 50,
    year: 2025,
  },
});

const makeOverdueList = (rows = [] as object[]) => ({
  success: true,
  data: { items: rows },
});

// ─────────────────────────────────────────────────────────────────────────────
describe('VereinDuesManagementPage', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    // Default: fee config present, empty dues list, no overdue
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('/fee-config')) return Promise.resolve(makeFeeConfig());
      if (url.includes('/overdue')) return Promise.resolve(makeOverdueList());
      return Promise.resolve(makeDuesList());
    });
  });

  it('shows loading spinner while fetching dues', async () => {
    // Delay the dues call so spinner is visible
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('/fee-config')) return Promise.resolve(makeFeeConfig());
      if (url.includes('/overdue')) return Promise.resolve(makeOverdueList());
      return new Promise(() => {}); // hang — dues never resolves
    });

    const { VereinDuesManagementPage } = await import('./VereinDuesManagementPage');
    render(<VereinDuesManagementPage />);

    // Spinner has aria-busy=true or role=status; uiMock Spinner gets role=status
    const statuses = screen.getAllByRole('status');
    const busy = statuses.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  it('renders fee configuration heading', async () => {
    const { VereinDuesManagementPage } = await import('./VereinDuesManagementPage');
    render(<VereinDuesManagementPage />);

    await waitFor(() => {
      // Page h1 heading
      const heading = screen.getAllByRole('heading').find((h) =>
        h.textContent?.toLowerCase().includes('due') ||
        h.textContent?.toLowerCase().includes('beitrag') ||
        h.textContent?.toLowerCase().includes('fee') ||
        h.textContent?.toLowerCase().includes('mitglied')
      );
      expect(heading).toBeDefined();
    });
  });

  it('populates fee amount from loaded config', async () => {
    const { VereinDuesManagementPage } = await import('./VereinDuesManagementPage');
    render(<VereinDuesManagementPage />);

    await waitFor(() => {
      // Fee config: 5000 cents = 50 CHF; the uiMock Input renders an <input type="number">
      const inputs = document.querySelectorAll('input[type="number"]');
      const feeInput = Array.from(inputs).find((i) => (i as HTMLInputElement).value === '50');
      expect(feeInput).toBeTruthy();
    });
  });

  it('shows member rows in the table when dues are loaded', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('/fee-config')) return Promise.resolve(makeFeeConfig());
      if (url.includes('/overdue')) return Promise.resolve(makeOverdueList());
      return Promise.resolve(makeDuesList([makeDuesRow()]));
    });

    const { VereinDuesManagementPage } = await import('./VereinDuesManagementPage');
    render(<VereinDuesManagementPage />);

    // uiMock renders TableCell content as plain divs.
    // {row.first_name} {row.last_name} renders as "Hans Müller" in one div —
    // use regex or exact:false because the full div text is "Hans Müller".
    await waitFor(() => {
      expect(screen.getByText(/Hans/)).toBeInTheDocument();
      expect(screen.getByText(/Müller/)).toBeInTheDocument();
    });
  });

  it('shows overdue alert banner when overdue members exist', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('/fee-config')) return Promise.resolve(makeFeeConfig());
      if (url.includes('/overdue')) return Promise.resolve(makeOverdueList([makeDuesRow({ status: 'overdue', id: 200 })]));
      return Promise.resolve(makeDuesList([makeDuesRow({ status: 'overdue' })]));
    });

    const { VereinDuesManagementPage } = await import('./VereinDuesManagementPage');
    render(<VereinDuesManagementPage />);

    await waitFor(() => {
      // Overdue banner has bulk remind button (text includes 'remind' or 'bulk')
      const btns = screen.getAllByRole('button');
      const bulkBtn = btns.find((b) =>
        b.textContent?.toLowerCase().includes('remind') || b.textContent?.toLowerCase().includes('bulk')
      );
      expect(bulkBtn).toBeInTheDocument();
    });
  });

  it('does NOT show overdue banner when no overdue members', async () => {
    const { VereinDuesManagementPage } = await import('./VereinDuesManagementPage');
    render(<VereinDuesManagementPage />);

    // Wait for loading to finish (spinner gone)
    await waitFor(() => {
      const statuses = screen.queryAllByRole('status');
      const busy = statuses.find((el) => el.getAttribute('aria-busy') === 'true');
      expect(busy).toBeUndefined();
    });

    // No overdue heading should appear
    const dangerHeadings = screen.queryAllByRole('heading').filter((h) =>
      h.textContent?.toLowerCase().includes('overdue')
    );
    expect(dangerHeadings.length).toBe(0);
  });

  it('calls save config endpoint and shows success toast', async () => {
    mockApi.put.mockResolvedValue({ success: true, data: { fee_config: {} } });

    const { VereinDuesManagementPage } = await import('./VereinDuesManagementPage');
    render(<VereinDuesManagementPage />);

    await waitFor(() => {
      // Wait for config to load (fee input populated with value 50)
      const inputs = document.querySelectorAll('input[type="number"]');
      expect(inputs.length).toBeGreaterThan(0);
    });

    // uiMock Button: onPress → onClick, so fireEvent.click works
    const saveBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('save')
    );
    if (saveBtn) fireEvent.click(saveBtn);

    await waitFor(() => {
      expect(mockApi.put).toHaveBeenCalledWith(
        '/v2/vereine/7/dues/fee-config',
        expect.objectContaining({ currency: 'CHF', billing_cycle: 'annual' })
      );
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('calls generate dues endpoint and shows success toast', async () => {
    mockApi.post.mockResolvedValue({ success: true, data: { generated: 10, skipped: 2, year: 2025 } });

    const { VereinDuesManagementPage } = await import('./VereinDuesManagementPage');
    render(<VereinDuesManagementPage />);

    await waitFor(() => {
      // Wait for spinner to clear
      const statuses = screen.queryAllByRole('status');
      const busy = statuses.find((el) => el.getAttribute('aria-busy') === 'true');
      expect(busy).toBeUndefined();
    });

    const genBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('generate') || b.textContent?.toLowerCase().includes('gen')
    );
    if (genBtn) fireEvent.click(genBtn);

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith(
        '/v2/vereine/7/dues/generate',
        expect.objectContaining({ year: expect.any(Number) })
      );
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('shows remind and waive buttons for pending rows', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('/fee-config')) return Promise.resolve(makeFeeConfig());
      if (url.includes('/overdue')) return Promise.resolve(makeOverdueList());
      return Promise.resolve(makeDuesList([makeDuesRow({ status: 'pending' })]));
    });

    const { VereinDuesManagementPage } = await import('./VereinDuesManagementPage');
    render(<VereinDuesManagementPage />);

    // Wait for table row to appear (uiMock renders cell content).
    // first_name and last_name are siblings in one div so use regex.
    await waitFor(() => screen.getByText(/Hans/));

    const btns = screen.getAllByRole('button');
    const remindBtn = btns.find((b) => b.textContent?.toLowerCase().includes('remind'));
    const waiveBtn = btns.find((b) => b.textContent?.toLowerCase().includes('waive'));
    expect(remindBtn).toBeInTheDocument();
    expect(waiveBtn).toBeInTheDocument();
  });

  it('calls remind endpoint on remind button click', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('/fee-config')) return Promise.resolve(makeFeeConfig());
      if (url.includes('/overdue')) return Promise.resolve(makeOverdueList());
      return Promise.resolve(makeDuesList([makeDuesRow({ id: 100, status: 'pending' })]));
    });
    mockApi.post.mockResolvedValue({ success: true, data: { sent: true } });

    const { VereinDuesManagementPage } = await import('./VereinDuesManagementPage');
    render(<VereinDuesManagementPage />);

    await waitFor(() => screen.getByText(/Hans/));

    const remindBtn = screen.getAllByRole('button').find((b) => b.textContent?.toLowerCase().includes('remind'));
    if (remindBtn) fireEvent.click(remindBtn);

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith('/v2/vereine/7/dues/100/remind', {});
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('opens waive modal when waive button is clicked', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('/fee-config')) return Promise.resolve(makeFeeConfig());
      if (url.includes('/overdue')) return Promise.resolve(makeOverdueList());
      return Promise.resolve(makeDuesList([makeDuesRow({ status: 'pending' })]));
    });

    const { VereinDuesManagementPage } = await import('./VereinDuesManagementPage');
    render(<VereinDuesManagementPage />);

    await waitFor(() => screen.getByText(/Hans/));

    // The modal is closed (isOpen=false) → uiMock Modal returns null.
    // There is no "Cancel" button in the DOM yet (that's in the modal footer).
    const cancelBefore = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('cancel')
    );
    expect(cancelBefore).toBeUndefined();

    const waiveBtn = screen.getAllByRole('button').find((b) => b.textContent?.toLowerCase().includes('waive'));
    if (waiveBtn) fireEvent.click(waiveBtn);

    // Modal opens — ModalFooter Cancel button now visible
    await waitFor(() => {
      const cancelBtn = screen.getAllByRole('button').find((b) =>
        b.textContent?.toLowerCase().includes('cancel')
      );
      expect(cancelBtn).toBeInTheDocument();
    });
  });

  it('calls waive endpoint with reason on modal confirm', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('/fee-config')) return Promise.resolve(makeFeeConfig());
      if (url.includes('/overdue')) return Promise.resolve(makeOverdueList());
      return Promise.resolve(makeDuesList([makeDuesRow({ id: 100, status: 'pending' })]));
    });
    mockApi.post.mockResolvedValue({ success: true, data: { status: 'waived' } });

    const { VereinDuesManagementPage } = await import('./VereinDuesManagementPage');
    render(<VereinDuesManagementPage />);

    await waitFor(() => screen.getByText(/Hans/));

    // Capture existing text inputs before opening modal
    const inputsBefore = new Set(Array.from(document.querySelectorAll('input[type="text"], input:not([type])')));

    // Open waive modal
    const waiveBtn = screen.getAllByRole('button').find((b) => b.textContent?.toLowerCase().includes('waive'));
    if (waiveBtn) fireEvent.click(waiveBtn);

    // Wait for modal Cancel button to appear (uiMock Textarea renders as <input>, not <textarea>)
    await waitFor(() => {
      const cancelBtn = screen.getAllByRole('button').find((b) =>
        b.textContent?.toLowerCase().includes('cancel')
      );
      expect(cancelBtn).toBeInTheDocument();
    });

    // uiMock renders Textarea as <input> (isInputLike regex matches 'textarea').
    // The modal adds a new untyped input for the reason — find the new one added by modal.
    const allInputsAfter = Array.from(document.querySelectorAll('input[type="text"], input:not([type])'));
    const reasonInput = allInputsAfter.find((i) => !inputsBefore.has(i as HTMLInputElement));

    // Wrap in act() to flush the React state update from fireEvent.change
    // so waiveReason is set before the confirm button click.
    await act(async () => {
      if (reasonInput) {
        fireEvent.change(reasonInput, { target: { value: 'Financial hardship' } });
      }
    });

    // t('verein_dues.admin_confirm_waive') = "Waive" (same as row action).
    // The modal Cancel button is unique, and the modal Confirm "Waive" comes after it.
    // Use the LAST button with text "Waive" since the modal footer button is after the table row button.
    const allBtns = screen.getAllByRole('button');
    const waiveBtns = allBtns.filter((b) => b.textContent?.toLowerCase() === 'waive');
    const modalConfirmBtn = waiveBtns[waiveBtns.length - 1]; // last "Waive" = modal confirm
    if (modalConfirmBtn) fireEvent.click(modalConfirmBtn);

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith(
        '/v2/vereine/7/dues/100/waive',
        expect.objectContaining({ reason: 'Financial hardship' })
      );
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('filters rows by search term in the member name', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('/fee-config')) return Promise.resolve(makeFeeConfig());
      if (url.includes('/overdue')) return Promise.resolve(makeOverdueList());
      return Promise.resolve(makeDuesList([
        makeDuesRow({ id: 1, first_name: 'Hans', last_name: 'Müller' }),
        makeDuesRow({ id: 2, first_name: 'Anna', last_name: 'Schneider' }),
      ]));
    });

    const { VereinDuesManagementPage } = await import('./VereinDuesManagementPage');
    render(<VereinDuesManagementPage />);

    // first_name/last_name are in same div → use regex
    await waitFor(() => {
      expect(screen.getByText(/Hans/)).toBeInTheDocument();
      expect(screen.getByText(/Anna/)).toBeInTheDocument();
    });

    // Find the search text input (uiMock renders Input without a type attribute;
    // Switch renders as type=checkbox; number inputs are type=number).
    const searchInput = Array.from(document.querySelectorAll('input')).find((i) => {
      const t = i.getAttribute('type');
      return t !== 'number' && t !== 'date' && t !== 'checkbox';
    });
    if (searchInput) {
      fireEvent.change(searchInput, { target: { value: 'Anna' } });
      await waitFor(() => {
        expect(screen.queryByText(/Hans/)).not.toBeInTheDocument();
        expect(screen.getByText(/Anna/)).toBeInTheDocument();
      });
    }
  });
});
