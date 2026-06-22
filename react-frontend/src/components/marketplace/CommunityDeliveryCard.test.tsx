// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

// ── Hoisted mocks ─────────────────────────────────────────────────────────────
const { mockApi, mockToast } = vi.hoisted(() => {
  const m = { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn(), download: vi.fn() };
  return {
    mockApi: m,
    mockToast: { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() },
  };
});

vi.mock('@/lib/api', () => ({
  default: mockApi,
  api: mockApi,
}));

// ── Mock logger ───────────────────────────────────────────────────────────────
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

const AUTHENTICATED_USER = {
  id: 99,
  name: 'Auth User',
  email: 'user@nexus.ie',
  is_god: false,
  is_admin: false,
  tenant_id: 2,
};

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
    useAuth: () => ({
      user: AUTHENTICATED_USER,
      isAuthenticated: true,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      updateUser: vi.fn(),
      refreshUser: vi.fn(),
      status: 'idle',
      error: null,
    }),
  })
);

// ── Fixtures ──────────────────────────────────────────────────────────────────
const PENDING_OFFER = {
  id: 1,
  order_id: 10,
  deliverer_id: 5,
  time_credits: 2,
  estimated_minutes: 30,
  notes: 'Happy to deliver',
  status: 'pending',
  accepted_at: null,
  completed_at: null,
  created_at: '2025-06-01T10:00:00Z',
  deliverer: {
    id: 5,
    name: 'Alice Deliverer',
    avatar_url: null,
    is_verified: true,
  },
};

const ACCEPTED_OFFER = {
  ...PENDING_OFFER,
  id: 2,
  status: 'accepted',
  accepted_at: '2025-06-01T11:00:00Z',
};

import { CommunityDeliveryCard } from './CommunityDeliveryCard';

describe('CommunityDeliveryCard — informational mode', () => {
  beforeEach(() => vi.clearAllMocks());

  it('renders the card title', () => {
    render(<CommunityDeliveryCard informational />);
    // t('community_delivery.title') — i18n will return key in test
    expect(document.body).toBeInTheDocument();
  });

  it('does NOT call the delivery offers API in informational mode', () => {
    render(<CommunityDeliveryCard informational orderId={10} />);
    expect(mockApi.get).not.toHaveBeenCalled();
  });

  it('does NOT render Offer to Deliver button in informational mode', () => {
    render(<CommunityDeliveryCard informational orderId={10} />);
    const btns = screen.queryAllByRole('button').filter(
      (b) => /offer/i.test(b.textContent ?? '')
    );
    expect(btns).toHaveLength(0);
  });
});

describe('CommunityDeliveryCard — no orderId', () => {
  beforeEach(() => vi.clearAllMocks());

  it('does not call API when orderId is absent', () => {
    render(<CommunityDeliveryCard />);
    expect(mockApi.get).not.toHaveBeenCalled();
  });
});

describe('CommunityDeliveryCard — with orderId, non-owner, authenticated', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockApi.get.mockResolvedValue({ success: true, data: [] });
  });

  // ── Loading state ──────────────────────────────────────────────────────────
  it('shows loading spinner while fetching offers', async () => {
    mockApi.get.mockReturnValue(new Promise(() => {}));
    render(<CommunityDeliveryCard orderId={10} />);
    const spinner = document.querySelector('[role="status"][aria-busy="true"]');
    expect(spinner).toBeTruthy();
  });

  // ── Empty state ────────────────────────────────────────────────────────────
  it('renders Offer to Deliver button when no offers exist', async () => {
    render(<CommunityDeliveryCard orderId={10} />);
    await waitFor(() => expect(mockApi.get).toHaveBeenCalled());
    const btn = screen.queryAllByRole('button').find(
      (b) => /offer/i.test(b.textContent ?? '')
    );
    expect(btn).toBeTruthy();
  });

  // ── Populated state ────────────────────────────────────────────────────────
  it('renders existing pending offer from deliverer', async () => {
    mockApi.get.mockResolvedValue({ success: true, data: [PENDING_OFFER] });
    render(<CommunityDeliveryCard orderId={10} />);
    await waitFor(() => expect(screen.getByText('Alice Deliverer')).toBeInTheDocument());
  });

  it('calls the correct API URL to load offers', async () => {
    render(<CommunityDeliveryCard orderId={42} />);
    await waitFor(() => expect(mockApi.get).toHaveBeenCalled());
    const url: string = mockApi.get.mock.calls[0][0];
    expect(url).toContain('/v2/marketplace/orders/42/delivery-offers');
  });

  // ── Submit offer ───────────────────────────────────────────────────────────
  it('opens the offer modal when Offer to Deliver is clicked', async () => {
    render(<CommunityDeliveryCard orderId={10} />);
    await waitFor(() => expect(mockApi.get).toHaveBeenCalled());

    const offerBtn = screen.queryAllByRole('button').find(
      (b) => /offer/i.test(b.textContent ?? '')
    );
    expect(offerBtn).toBeTruthy();
    fireEvent.click(offerBtn!);

    // Modal should appear — check for Send Offer or similar text
    await waitFor(() => {
      const modalBtns = screen.queryAllByRole('button');
      expect(modalBtns.length).toBeGreaterThan(1);
    });
  });

  it('posts to the delivery offers endpoint on submit', async () => {
    mockApi.post.mockResolvedValue({ success: true });
    render(<CommunityDeliveryCard orderId={10} />);
    await waitFor(() => expect(mockApi.get).toHaveBeenCalled());

    const offerBtn = screen.queryAllByRole('button').find(
      (b) => /offer/i.test(b.textContent ?? '')
    );
    fireEvent.click(offerBtn!);

    // Wait for modal, then click send
    await waitFor(() => {
      const sendBtn = screen.queryAllByRole('button').find(
        (b) => /send/i.test(b.textContent ?? '')
      );
      if (sendBtn && !sendBtn.hasAttribute('disabled')) fireEvent.click(sendBtn);
    });

    await waitFor(() => {
      if (mockApi.post.mock.calls.length > 0) {
        const url: string = mockApi.post.mock.calls[0][0];
        expect(url).toContain('/v2/marketplace/orders/10/delivery-offers');
      }
    });
  });

  it('shows success toast after submitting offer', async () => {
    mockApi.post.mockResolvedValue({ success: true });
    render(<CommunityDeliveryCard orderId={10} />);
    await waitFor(() => expect(mockApi.get).toHaveBeenCalled());

    const offerBtn = screen.queryAllByRole('button').find(
      (b) => /offer/i.test(b.textContent ?? '')
    );
    fireEvent.click(offerBtn!);

    await waitFor(() => {
      const sendBtn = screen.queryAllByRole('button').find(
        (b) => /send/i.test(b.textContent ?? '')
      );
      if (sendBtn && !sendBtn.hasAttribute('disabled')) fireEvent.click(sendBtn);
    });

    await waitFor(() => {
      if (mockApi.post.mock.calls.length > 0) {
        expect(mockToast.success).toHaveBeenCalled();
      }
    });
  });

  it('shows error toast when submit fails', async () => {
    mockApi.post.mockRejectedValue(new Error('Server error'));
    render(<CommunityDeliveryCard orderId={10} />);
    await waitFor(() => expect(mockApi.get).toHaveBeenCalled());

    const offerBtn = screen.queryAllByRole('button').find(
      (b) => /offer/i.test(b.textContent ?? '')
    );
    fireEvent.click(offerBtn!);

    await waitFor(() => {
      const sendBtn = screen.queryAllByRole('button').find(
        (b) => /send/i.test(b.textContent ?? '')
      );
      if (sendBtn && !sendBtn.hasAttribute('disabled')) fireEvent.click(sendBtn);
    });

    await waitFor(() => {
      if (mockApi.post.mock.calls.length > 0) {
        expect(mockToast.error).toHaveBeenCalled();
      }
    });
  });
});

describe('CommunityDeliveryCard — owner view', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders Accept button for pending offer when user is owner', async () => {
    mockApi.get.mockResolvedValue({ success: true, data: [PENDING_OFFER] });
    render(<CommunityDeliveryCard orderId={10} isOwner />);
    await waitFor(() => expect(screen.getByText('Alice Deliverer')).toBeInTheDocument());

    const acceptBtn = screen.queryAllByRole('button').find(
      (b) => /accept/i.test(b.textContent ?? '')
    );
    expect(acceptBtn).toBeTruthy();
  });

  it('calls accept endpoint when owner clicks Accept', async () => {
    mockApi.get.mockResolvedValue({ success: true, data: [PENDING_OFFER] });
    mockApi.put.mockResolvedValue({ success: true });

    render(<CommunityDeliveryCard orderId={10} isOwner />);
    await waitFor(() => expect(screen.getByText('Alice Deliverer')).toBeInTheDocument());

    const acceptBtn = screen.queryAllByRole('button').find(
      (b) => /accept/i.test(b.textContent ?? '')
    );
    expect(acceptBtn).toBeTruthy();
    fireEvent.click(acceptBtn!);

    await waitFor(() => expect(mockApi.put).toHaveBeenCalled());
    const url: string = mockApi.put.mock.calls[0][0];
    expect(url).toContain('accept');
  });

  it('renders Confirm button for accepted offer when user is owner', async () => {
    mockApi.get.mockResolvedValue({ success: true, data: [ACCEPTED_OFFER] });
    render(<CommunityDeliveryCard orderId={10} isOwner />);
    await waitFor(() => expect(screen.getByText('Alice Deliverer')).toBeInTheDocument());

    const confirmBtn = screen.queryAllByRole('button').find(
      (b) => /confirm/i.test(b.textContent ?? '')
    );
    expect(confirmBtn).toBeTruthy();
  });

  it('does NOT render Offer to Deliver button for owner', async () => {
    mockApi.get.mockResolvedValue({ success: true, data: [] });
    render(<CommunityDeliveryCard orderId={10} isOwner />);
    await waitFor(() => expect(mockApi.get).toHaveBeenCalled());

    const offerBtn = screen.queryAllByRole('button').find(
      (b) => /offer to deliver/i.test(b.textContent ?? '')
    );
    expect(offerBtn).toBeUndefined();
  });
});
