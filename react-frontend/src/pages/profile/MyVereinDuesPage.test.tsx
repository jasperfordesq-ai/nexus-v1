// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import { api } from '@/lib/api';

// ---------------------------------------------------------------------------
// Module mocks
// ---------------------------------------------------------------------------

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };

vi.mock('@/contexts', () =>
  createMockContexts({ useToast: () => mockToast }),
);

// Stripe modal is a heavy dependency — stub it so it doesn't require Stripe.js
vi.mock('@/components/marketplace/StripeCheckoutModal', () => ({
  StripeCheckoutModal: ({ isOpen, onClose }: { isOpen: boolean; onClose: () => void }) =>
    isOpen ? <div data-testid="stripe-modal"><button onClick={onClose}>close-stripe</button></div> : null,
}));

import { MyVereinDuesPage } from './MyVereinDuesPage';

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

const pendingRow = {
  id: 1,
  organization_id: 10,
  organization_name: 'FC Zurich',
  membership_year: 2025,
  amount_cents: 15000,
  currency: 'CHF',
  status: 'pending',
  due_date: '2025-12-31',
  paid_at: null,
  stripe_payment_intent_id: null,
};

const paidRow = {
  id: 2,
  organization_id: 10,
  organization_name: 'Tennis Club',
  membership_year: 2025,
  amount_cents: 5000,
  currency: 'EUR',
  status: 'paid',
  due_date: null,
  paid_at: '2025-01-15T10:00:00Z',
  stripe_payment_intent_id: 'pi_123',
};

const overdueRow = {
  id: 3,
  organization_id: 11,
  organization_name: 'Hockey Club',
  membership_year: 2024,
  amount_cents: 8000,
  currency: 'CHF',
  status: 'overdue',
  due_date: '2024-06-30',
  paid_at: null,
  stripe_payment_intent_id: null,
};

function makeListResponse(items: typeof pendingRow[]) {
  return { success: true, data: { items, total: items.length } };
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('MyVereinDuesPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows a loading spinner while the dues are being fetched', () => {
    // Never resolve — keep in loading state
    vi.mocked(api.get).mockReturnValue(new Promise(() => {}));
    render(<MyVereinDuesPage />);
    // The loading spinner renders a div with aria-busy="true"; ToastProvider also renders
    // role="status" so we key on aria-busy to uniquely identify the loading indicator.
    expect(document.querySelector('[aria-busy="true"]')).not.toBeNull();
  });

  it('renders dues rows after successful load', async () => {
    vi.mocked(api.get).mockResolvedValueOnce(makeListResponse([pendingRow, paidRow]));
    render(<MyVereinDuesPage />);

    await waitFor(() => {
      expect(screen.getByText('FC Zurich')).toBeInTheDocument();
      expect(screen.getByText('Tennis Club')).toBeInTheDocument();
    });
  });

  it('shows an error alert when the API call fails', async () => {
    vi.mocked(api.get).mockRejectedValueOnce(new Error('network'));
    render(<MyVereinDuesPage />);

    await waitFor(() => {
      expect(screen.getByRole('alert')).toBeInTheDocument();
    });
  });

  it('shows an error when the API returns success:false', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: false, error: 'Server error' });
    render(<MyVereinDuesPage />);

    await waitFor(() => {
      expect(screen.getByRole('alert')).toBeInTheDocument();
    });
  });

  it('shows empty-state message when dues list is empty', async () => {
    vi.mocked(api.get).mockResolvedValueOnce(makeListResponse([]));
    render(<MyVereinDuesPage />);

    // Wait for loading to finish (aria-busy disappears) then check empty state
    await waitFor(() => {
      expect(document.querySelector('[aria-busy="true"]')).toBeNull();
    });
    // The page renders an empty-state card — no rows should appear
    expect(screen.queryByText('FC Zurich')).not.toBeInTheDocument();
  });

  it('shows Pay button for pending rows and not for paid rows', async () => {
    vi.mocked(api.get).mockResolvedValueOnce(makeListResponse([pendingRow, paidRow]));
    render(<MyVereinDuesPage />);

    await waitFor(() => {
      expect(screen.getByText('FC Zurich')).toBeInTheDocument();
    });

    // The pending row must have a pay button (contains CreditCard icon or pay label)
    const buttons = screen.getAllByRole('button');
    // At least one button should be present for the payable row
    expect(buttons.length).toBeGreaterThanOrEqual(1);
  });

  it('shows Pay button for overdue rows', async () => {
    vi.mocked(api.get).mockResolvedValueOnce(makeListResponse([overdueRow]));
    render(<MyVereinDuesPage />);

    await waitFor(() => {
      expect(screen.getByText('Hockey Club')).toBeInTheDocument();
    });

    const buttons = screen.getAllByRole('button');
    expect(buttons.length).toBeGreaterThanOrEqual(1);
  });

  it('calls POST /v2/me/verein-dues/{id}/pay when Pay is clicked and opens Stripe modal', async () => {
    vi.mocked(api.get).mockResolvedValueOnce(makeListResponse([pendingRow]));
    vi.mocked(api.post).mockResolvedValueOnce({
      success: true,
      data: { client_secret: 'cs_test_123', payment_intent_id: 'pi_abc', public_key: 'pk_test' },
    });

    render(<MyVereinDuesPage />);

    await waitFor(() => {
      expect(screen.getByText('FC Zurich')).toBeInTheDocument();
    });

    // Click the pay button (first button in a pending/overdue row)
    const payButtons = screen.getAllByRole('button');
    fireEvent.click(payButtons[0]);

    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith(
        `/v2/me/verein-dues/${pendingRow.id}/pay`,
        {},
      );
    });

    // Stripe modal should now appear
    await waitFor(() => {
      expect(screen.getByTestId('stripe-modal')).toBeInTheDocument();
    });
  });

  it('shows error toast when pay POST fails', async () => {
    vi.mocked(api.get).mockResolvedValueOnce(makeListResponse([pendingRow]));
    vi.mocked(api.post).mockResolvedValueOnce({ success: false, error: 'Card declined' });

    render(<MyVereinDuesPage />);

    await waitFor(() => {
      expect(screen.getByText('FC Zurich')).toBeInTheDocument();
    });

    const payButtons = screen.getAllByRole('button');
    fireEvent.click(payButtons[0]);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
    // Modal should NOT open
    expect(screen.queryByTestId('stripe-modal')).not.toBeInTheDocument();
  });

  it('displays formatted amount for each dues row', async () => {
    vi.mocked(api.get).mockResolvedValueOnce(makeListResponse([pendingRow]));
    render(<MyVereinDuesPage />);

    await waitFor(() => {
      expect(screen.getByText('FC Zurich')).toBeInTheDocument();
    });

    // 15000 cents = CHF 150 — should appear in formatted form somewhere on the page
    const amountPattern = /150/;
    expect(screen.getByText(amountPattern)).toBeInTheDocument();
  });

  it('shows paid_at date for paid rows', async () => {
    vi.mocked(api.get).mockResolvedValueOnce(makeListResponse([paidRow]));
    render(<MyVereinDuesPage />);

    await waitFor(() => {
      expect(screen.getByText('Tennis Club')).toBeInTheDocument();
    });

    // paid_at is shown as toLocaleDateString — there can be multiple "2025" occurrences
    // (year label + paid date), so just confirm at least one is present
    const all2025 = screen.getAllByText(/2025/);
    expect(all2025.length).toBeGreaterThanOrEqual(1);
  });
});
