// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * TransferModal — thorough Vitest render tests (money-critical UI).
 *
 * api.get is URL-routed (not call-ordered) because the open effect re-fires on
 * re-render and would otherwise consume a call-ordered mock queue out of order.
 *
 * SKIPPED / notes:
 *   - validateForm's "select recipient" / "valid amount" / "insufficient balance"
 *     branches are unreachable through the UI: the Send button is disabled unless
 *     (recipient && amount > 0 && amount <= balance), so handleSubmit never runs
 *     for those inputs. The disabled-state itself is asserted instead.
 *   - The max_transfer branch IS reachable (amount <= balance but > maxTransfer)
 *     and is covered.
 *   - TransferModal does not toast on success — it delegates to the parent via
 *     onTransferComplete; asserting that callback is the correct proxy.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent, within } from '@/test/test-utils';

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

vi.mock(import('@/lib/helpers'), async (importOriginal) => ({
  ...(await importOriginal()),
  resolveAvatarUrl: vi.fn((url: string | null) => url ?? ''),
  formatNumber: vi.fn((n: number, opts?: Intl.NumberFormatOptions) => n.toLocaleString('en-US', opts)),
  cn: (...classes: unknown[]) => classes.filter(Boolean).join(' '),
}));

// Mock CategorySelect so it never fires its own api.get on mount.
vi.mock('./CategorySelect', () => ({
  CategorySelect: () => <div data-testid="category-select" />,
}));

const mockToast = vi.hoisted(() => ({
  success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn(),
}));

vi.mock('@/contexts', () => ({
  useToast: vi.fn(() => mockToast),
  useTheme: () => ({ resolvedTheme: 'light', theme: 'system', toggleTheme: vi.fn(), setTheme: vi.fn() }),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
  useAuth: () => ({ user: null, isAuthenticated: false, login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle', error: null }),
  useTenant: () => ({ tenant: { id: 2, name: 'Test Tenant', slug: 'test', tagline: null }, branding: { name: 'Test Tenant', logo_url: null }, tenantSlug: 'test', tenantPath: (p: string) => '/test' + p, isLoading: false, hasFeature: vi.fn(() => true), hasModule: vi.fn(() => true) }),
  usePresence: () => ({ status: 'offline', setStatus: vi.fn(), getPresence: vi.fn(), isOnline: vi.fn(() => false) }),
  usePresenceOptional: () => null,
}));

import { api } from '@/lib/api';
import { TransferModal } from './TransferModal';
import type { Transaction } from '@/types/api';

const MOCK_TRANSACTION = {
  id: 99, type: 'transfer', amount: 2, direction: 'sent', status: 'completed',
  description: 'Test payment', created_at: '2026-06-21T12:00:00Z', updated_at: '2026-06-21T12:00:00Z',
} as unknown as Transaction;

const MOCK_RECIPIENT = { id: 42, first_name: 'Alice', last_name: 'Timebank', avatar: null, username: 'alice' };

// Mutable per-test api state, read by the URL router below.
let apiState: { maxTransfer: number; users: typeof MOCK_RECIPIENT[]; userById: Record<string, unknown> | null };

beforeEach(() => {
  vi.clearAllMocks();
  apiState = { maxTransfer: 1000, users: [], userById: null };
  vi.mocked(api.get).mockImplementation((url: string) => {
    if (url.includes('/v2/wallet/user-search')) {
      return Promise.resolve({ success: true, data: { users: apiState.users } } as never);
    }
    if (url.includes('/v2/users/')) {
      return Promise.resolve(
        (apiState.userById ? { success: true, data: apiState.userById } : { success: false }) as never,
      );
    }
    if (url.includes('/v2/wallet/config')) {
      return Promise.resolve({ success: true, data: { max_transfer: apiState.maxTransfer } } as never);
    }
    return Promise.resolve({ success: true, data: {} } as never);
  });
  vi.mocked(api.post).mockResolvedValue({ success: true, data: MOCK_TRANSACTION } as never);
});

const defaultProps = { isOpen: true, onClose: vi.fn(), currentBalance: 10, onTransferComplete: vi.fn() };

function renderModal(overrides: Partial<React.ComponentProps<typeof TransferModal>> = {}) {
  const onClose = vi.fn();
  const onTransferComplete = vi.fn();
  render(<TransferModal {...defaultProps} onClose={onClose} onTransferComplete={onTransferComplete} {...overrides} />);
  return { onClose, onTransferComplete };
}

function getSendButton(): HTMLButtonElement {
  const btns = screen.getAllByRole('button') as HTMLButtonElement[];
  return btns.find((b) => b.textContent?.toLowerCase().includes('send credits'))!;
}

function setAmount(value: string) {
  fireEvent.change(screen.getByRole('spinbutton'), { target: { value } });
}

/** Type into the search box, wait for the results group, click the first result. */
async function selectRecipientViaSearch(recipient = MOCK_RECIPIENT) {
  apiState.users = [recipient];
  fireEvent.change(screen.getByLabelText(/search recipient/i), { target: { value: 'Ali' } });
  const results = await screen.findByRole('group', { name: /search results/i });
  const optionBtn = within(results).getAllByRole('button')[0];
  fireEvent.click(optionBtn);
  // recipient panel now shows the name
  await screen.findByText(`${recipient.first_name} ${recipient.last_name}`);
}

describe('TransferModal — rendering', () => {
  it('renders a dialog when isOpen=true', () => {
    renderModal();
    expect(screen.getByRole('dialog')).toBeInTheDocument();
  });

  it('does not render when isOpen=false', () => {
    renderModal({ isOpen: false });
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('shows the available balance', () => {
    renderModal({ currentBalance: 7 });
    expect(screen.getByText('7', { exact: false })).toBeInTheDocument();
  });

  it('fetches wallet config on open', async () => {
    renderModal();
    await waitFor(() => expect(api.get).toHaveBeenCalledWith('/v2/wallet/config'));
  });
});

describe('TransferModal — submit gating (disabled Send button)', () => {
  it('disables Send with no recipient selected', () => {
    renderModal();
    expect(getSendButton()).toBeDisabled();
  });

  it('disables Send when the amount exceeds the balance', async () => {
    renderModal({ currentBalance: 10 });
    await selectRecipientViaSearch();
    setAmount('25');
    expect(getSendButton()).toBeDisabled();
    expect(screen.getByText(/exceed/i)).toBeInTheDocument();
  });

  it('enables Send once a recipient and a valid amount are set', async () => {
    renderModal({ currentBalance: 10 });
    await selectRecipientViaSearch();
    setAmount('2');
    await waitFor(() => expect(getSendButton()).toBeEnabled());
  });
});

describe('TransferModal — recipient search', () => {
  it('queries the user-search endpoint with the typed term', async () => {
    renderModal();
    fireEvent.change(screen.getByLabelText(/search recipient/i), { target: { value: 'Ali' } });
    await waitFor(() =>
      expect(api.get).toHaveBeenCalledWith(expect.stringContaining('/v2/wallet/user-search?q=Ali')),
    );
  });

  it('shows and selects a search result, then allows removing it', async () => {
    renderModal();
    await selectRecipientViaSearch();
    expect(screen.getByText('Alice Timebank')).toBeInTheDocument();

    // Remove the selected recipient → search input returns
    fireEvent.click(screen.getByLabelText(/remove recipient/i));
    await waitFor(() => expect(screen.getByLabelText(/search recipient/i)).toBeInTheDocument());
  });

  it('renders results in a labelled group of buttons, not an invalid empty listbox', async () => {
    // Regression: results were a role="listbox" wrapping HeroUI <Button>s. React Aria
    // does not forward role="option" to the DOM, so the listbox had zero options —
    // invalid ARIA that screen readers announce as an empty list. They are now a
    // role="group" (aria-label "Search results") of real, focusable result buttons.
    apiState.users = [MOCK_RECIPIENT];
    renderModal();
    fireEvent.change(screen.getByLabelText(/search recipient/i), { target: { value: 'Ali' } });

    const results = await screen.findByRole('group', { name: /search results/i });
    expect(within(results).getAllByRole('button').length).toBeGreaterThan(0);
    // No invalid listbox, and no orphan options outside a real listbox.
    expect(screen.queryByRole('listbox')).toBeNull();
    expect(screen.queryByRole('option')).toBeNull();
  });
});

describe('TransferModal — successful transfer', () => {
  it('posts the exact payload with an Idempotency-Key and calls the success callbacks', async () => {
    const { onClose, onTransferComplete } = renderModal({ currentBalance: 10 });
    await selectRecipientViaSearch();
    setAmount('2');
    fireEvent.click(getSendButton());

    await waitFor(() => expect(api.post).toHaveBeenCalledTimes(1));
    const [url, body, opts] = vi.mocked(api.post).mock.calls[0];
    expect(url).toBe('/v2/wallet/transfer');
    expect(body).toMatchObject({ recipient: 42, amount: 2 });
    expect((body as Record<string, unknown>).description).toBeUndefined();
    expect((opts as { headers: Record<string, string> }).headers['Idempotency-Key']).toEqual(expect.any(String));

    await waitFor(() => expect(onTransferComplete).toHaveBeenCalledWith(MOCK_TRANSACTION));
    expect(onClose).toHaveBeenCalled();
  });

  it('includes a trimmed description in the payload when provided', async () => {
    renderModal({ currentBalance: 10 });
    await selectRecipientViaSearch();
    setAmount('2');
    const textarea = screen.getByRole('textbox', { name: /description/i });
    fireEvent.change(textarea, { target: { value: '  thanks!  ' } });
    fireEvent.click(getSendButton());

    await waitFor(() => expect(api.post).toHaveBeenCalledTimes(1));
    expect(vi.mocked(api.post).mock.calls[0][1]).toMatchObject({ description: 'thanks!' });
  });
});

describe('TransferModal — failure handling', () => {
  it('shows the server error and keeps the modal open', async () => {
    vi.mocked(api.post).mockResolvedValue({ success: false, error: 'Recipient not found' } as never);
    const { onClose, onTransferComplete } = renderModal({ currentBalance: 10 });
    await selectRecipientViaSearch();
    setAmount('2');
    fireEvent.click(getSendButton());

    expect(await screen.findByText('Recipient not found')).toBeInTheDocument();
    expect(onTransferComplete).not.toHaveBeenCalled();
    expect(onClose).not.toHaveBeenCalled();
  });

  it('shows a fallback error when the server returns no error message', async () => {
    vi.mocked(api.post).mockResolvedValue({ success: false } as never);
    const { onClose } = renderModal({ currentBalance: 10 });
    await selectRecipientViaSearch();
    setAmount('2');
    fireEvent.click(getSendButton());

    await waitFor(() => expect(screen.getByRole('alert').textContent?.length ?? 0).toBeGreaterThan(0));
    expect(onClose).not.toHaveBeenCalled();
  });

  it('shows an unexpected-error message when the request throws', async () => {
    vi.mocked(api.post).mockRejectedValue(new Error('network'));
    const { onClose } = renderModal({ currentBalance: 10 });
    await selectRecipientViaSearch();
    setAmount('2');
    fireEvent.click(getSendButton());

    await waitFor(() => expect(screen.getByRole('alert').textContent?.length ?? 0).toBeGreaterThan(0));
    expect(onClose).not.toHaveBeenCalled();
  });

  it('blocks a transfer above the configured max_transfer limit', async () => {
    apiState.maxTransfer = 5;
    renderModal({ currentBalance: 100 }); // balance high so the amount is not over-balance
    await waitFor(() => expect(api.get).toHaveBeenCalledWith('/v2/wallet/config'));
    await selectRecipientViaSearch();
    setAmount('6'); // <= balance (100) but > maxTransfer (5)
    fireEvent.click(getSendButton());

    await waitFor(() => expect(screen.getByRole('alert').textContent?.length ?? 0).toBeGreaterThan(0));
    expect(api.post).not.toHaveBeenCalled();
  });
});

describe('TransferModal — initialRecipientId', () => {
  it('auto-fills the recipient fetched by id on open', async () => {
    apiState.userById = { id: 7, first_name: 'Bob', last_name: 'Helper', username: 'bob' };
    renderModal({ initialRecipientId: 7 });
    expect(await screen.findByText('Bob Helper')).toBeInTheDocument();
    expect(api.get).toHaveBeenCalledWith('/v2/users/7');
  });
});

describe('TransferModal — cancel', () => {
  it('calls onClose when Cancel is pressed', () => {
    const { onClose } = renderModal();
    fireEvent.click(screen.getByRole('button', { name: /^cancel$/i }));
    expect(onClose).toHaveBeenCalled();
  });
});
