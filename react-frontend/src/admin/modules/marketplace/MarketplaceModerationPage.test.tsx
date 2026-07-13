// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent, within } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── Mock api ────────────────────────────────────────────────────────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}));

vi.mock('@/lib/api', () => ({
  api: mockApi,
  default: mockApi,
  API_BASE: '/api',
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ─── Mock adminApi ────────────────────────────────────────────────────────────
const { mockAdminMarketplace } = vi.hoisted(() => ({
  mockAdminMarketplace: {
    bulkReject: vi.fn(),
  },
}));

vi.mock('../../api/adminApi', () => ({
  adminMarketplace: mockAdminMarketplace,
}));

// ─── Contexts ─────────────────────────────────────────────────────────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test', currency: 'EUR' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

vi.mock('@/components/seo/PageMeta', () => ({
  PageMeta: () => null,
}));

// ─── Stub HeroUI Modal family ─────────────────────────────────────────────────
vi.mock('@/components/ui', async (importOriginal) => {
  const actual = await importOriginal<Record<string, unknown>>();
  return {
    ...actual,
    Modal: ({ isOpen, children }: { isOpen: boolean; children: React.ReactNode; onClose?: () => void; size?: string }) =>
      isOpen ? <div role="dialog" aria-label="Dialog" data-testid="reject-modal">{children}</div> : null,
    ModalContent: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
    ModalHeader: ({ children, className }: { children: React.ReactNode; className?: string }) => <div className={className} data-testid="modal-header">{children}</div>,
    ModalBody: ({ children }: { children: React.ReactNode }) => <div data-testid="modal-body">{children}</div>,
    ModalFooter: ({ children }: { children: React.ReactNode }) => <div data-testid="modal-footer">{children}</div>,
    Button: ({ children, onPress, isLoading, isDisabled, onClick, as: Tag, href, target, rel, ...rest }: { children?: React.ReactNode; onPress?: () => void; isLoading?: boolean; isDisabled?: boolean; onClick?: React.MouseEventHandler; as?: string; href?: string; target?: string; rel?: string; [key: string]: unknown }) => {
      if (Tag === 'a') {
        return <a href={href} target={target} rel={rel} aria-label={rest['aria-label'] as string}>{children}</a>;
      }
      return (
        <button
          onClick={(e) => { onClick?.(e); onPress?.(); }}
          disabled={isLoading || isDisabled}
          data-loading={isLoading ? 'true' : undefined}
          aria-label={rest['aria-label'] as string}
        >
          {isLoading ? 'Loading…' : children}
        </button>
      );
    },
    Textarea: ({ label, value, onValueChange, placeholder }: { label?: string; value?: string; onValueChange?: (v: string) => void; placeholder?: string; variant?: string }) => (
      <div>
        {label && <label>{label}</label>}
        <textarea
          value={value}
          placeholder={placeholder}
          aria-label={label}
          onChange={(e) => onValueChange?.(e.target.value)}
        />
      </div>
    ),
    Chip: ({ children, color }: { children: React.ReactNode; color?: string; size?: string; variant?: string; className?: string }) => (
      <span data-color={color}>{children}</span>
    ),
    Tabs: ({ children }: { children: React.ReactNode; onSelectionChange?: (key: string | number) => void; selectedKey?: string; [key: string]: unknown }) => (
      <div role="tablist">{children}</div>
    ),
    Tab: ({ title }: { title: React.ReactNode }) => <button role="tab">{title}</button>,
    Tooltip: ({ children }: { children: React.ReactNode }) => <>{children}</>,
    Avatar: ({ name }: { name?: string; src?: string; size?: string; radius?: string; className?: string }) => (
      <div data-testid="avatar" aria-label={name}>{name?.[0]}</div>
    ),
  };
});

// ─────────────────────────────────────────────────────────────────────────────
// Fixtures
// ─────────────────────────────────────────────────────────────────────────────

const { makeListing } = vi.hoisted(() => ({
  makeListing: (overrides: Record<string, unknown> = {}) => ({
    id: 1,
    title: 'Handmade Candle',
    price: 15.00,
    price_currency: 'EUR',
    price_type: 'fixed',
    status: 'active',
    moderation_status: 'pending',
    moderation_notes: null,
    seller_type: 'individual',
    views_count: 12,
    image: null,
    category: 'Crafts',
    user: { id: 3, name: 'Alice Seller' },
    created_at: '2025-06-01T10:00:00Z',
    ...overrides,
  }),
}));

const makeApiResponse = (data: unknown[], total?: number) => ({
  success: true,
  data,
  meta: { total: total ?? (data as unknown[]).length },
});

// ─────────────────────────────────────────────────────────────────────────────

describe('MarketplaceModerationPage', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockApi.get.mockResolvedValue(makeApiResponse([]));
  });

  it('shows loading state initially', async () => {
    mockApi.get.mockImplementation(() => new Promise(() => {}));
    const { MarketplaceModerationPage } = await import('./MarketplaceModerationPage');
    render(<MarketplaceModerationPage />);
    expect(await screen.findByRole('status', { name: 'Loading' })).toHaveAttribute(
      'aria-busy',
      'true'
    );
  });

  it('renders empty state when no listings returned', async () => {
    const { MarketplaceModerationPage } = await import('./MarketplaceModerationPage');
    render(<MarketplaceModerationPage />);
    expect(
      await screen.findByRole('heading', { name: 'No listings found' })
    ).toBeInTheDocument();
  });

  it('renders listing rows when data returned', async () => {
    mockApi.get.mockResolvedValue(makeApiResponse([makeListing()]));
    const { MarketplaceModerationPage } = await import('./MarketplaceModerationPage');
    render(<MarketplaceModerationPage />);
    await waitFor(() => {
      expect(screen.getByText('Handmade Candle')).toBeInTheDocument();
    });
  });

  it('renders seller name column', async () => {
    mockApi.get.mockResolvedValue(makeApiResponse([makeListing()]));
    const { MarketplaceModerationPage } = await import('./MarketplaceModerationPage');
    render(<MarketplaceModerationPage />);
    await waitFor(() => {
      expect(screen.getByText('Alice Seller')).toBeInTheDocument();
    });
  });

  it('renders moderation status chip', async () => {
    mockApi.get.mockResolvedValue(makeApiResponse([makeListing({ moderation_status: 'pending' })]));
    const { MarketplaceModerationPage } = await import('./MarketplaceModerationPage');
    render(<MarketplaceModerationPage />);
    await waitFor(() => {
      expect(screen.getByText('pending')).toBeInTheDocument();
    });
  });

  it('shows approve and reject buttons for pending listings', async () => {
    mockApi.get.mockResolvedValue(makeApiResponse([makeListing({ moderation_status: 'pending' })]));
    const { MarketplaceModerationPage } = await import('./MarketplaceModerationPage');
    render(<MarketplaceModerationPage />);
    await waitFor(() => {
      expect(screen.getByRole('button', { name: /approve listing/i })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: /reject listing/i })).toBeInTheDocument();
    });
  });

  it('calls approve endpoint when approve button clicked', async () => {
    mockApi.get.mockResolvedValue(makeApiResponse([makeListing({ id: 7, moderation_status: 'pending' })]));
    mockApi.post.mockResolvedValue({ success: true });
    const { MarketplaceModerationPage } = await import('./MarketplaceModerationPage');
    render(<MarketplaceModerationPage />);
    await waitFor(() => screen.getByText('Handmade Candle'));

    fireEvent.click(screen.getByRole('button', { name: /approve listing/i }));
    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith('/v2/admin/marketplace/listings/7/approve');
    });
  });

  it('opens reject modal when reject button clicked', async () => {
    mockApi.get.mockResolvedValue(makeApiResponse([makeListing({ id: 8, moderation_status: 'pending' })]));
    const { MarketplaceModerationPage } = await import('./MarketplaceModerationPage');
    render(<MarketplaceModerationPage />);
    await waitFor(() => screen.getByText('Handmade Candle'));

    fireEvent.click(screen.getByRole('button', { name: /reject listing/i }));
    expect(await screen.findByRole('dialog')).toBeInTheDocument();
  });

  it('shows reject modal with notes textarea when reject clicked', async () => {
    mockApi.get.mockResolvedValue(makeApiResponse([makeListing({ id: 9, moderation_status: 'pending' })]));
    mockApi.post.mockResolvedValue({ success: true });
    const { MarketplaceModerationPage } = await import('./MarketplaceModerationPage');
    render(<MarketplaceModerationPage />);
    await waitFor(() => screen.getByText('Handmade Candle'));

    fireEvent.click(screen.getByRole('button', { name: /reject listing/i }));
    const rejectModal = await screen.findByRole('dialog');
    expect(
      within(rejectModal).getByRole('textbox', { name: /moderation notes/i })
    ).toBeInTheDocument();
  });

  it('shows delete confirm modal when delete button clicked', async () => {
    mockApi.get.mockResolvedValue(makeApiResponse([makeListing({ id: 10, moderation_status: 'approved' })]));
    const { MarketplaceModerationPage } = await import('./MarketplaceModerationPage');
    render(<MarketplaceModerationPage />);
    await waitFor(() => screen.getByText('Handmade Candle'));

    fireEvent.click(screen.getByRole('button', { name: /delete listing/i }));
    const confirmModal = await screen.findByRole('dialog');
    expect(
      within(confirmModal).getByText('Delete Listing')
    ).toBeInTheDocument();
  });

  it('calls DELETE endpoint when delete confirmed', async () => {
    mockApi.get.mockResolvedValue(makeApiResponse([makeListing({ id: 11, moderation_status: 'approved' })]));
    mockApi.delete.mockResolvedValue({ success: true });
    const { MarketplaceModerationPage } = await import('./MarketplaceModerationPage');
    render(<MarketplaceModerationPage />);
    await waitFor(() => screen.getByText('Handmade Candle'));

    fireEvent.click(screen.getByRole('button', { name: /delete listing/i }));
    const confirmModal = await screen.findByRole('dialog');
    fireEvent.click(within(confirmModal).getByRole('button', { name: /delete/i }));
    await waitFor(() => {
      expect(mockApi.delete).toHaveBeenCalledWith('/v2/admin/marketplace/listings/11');
    });
  });

  it('shows success toast when listing approved', async () => {
    mockApi.get.mockResolvedValue(makeApiResponse([makeListing({ id: 12, moderation_status: 'pending' })]));
    mockApi.post.mockResolvedValue({ success: true });
    const { MarketplaceModerationPage } = await import('./MarketplaceModerationPage');
    render(<MarketplaceModerationPage />);
    await waitFor(() => screen.getByText('Handmade Candle'));

    fireEvent.click(screen.getByRole('button', { name: /approve listing/i }));
    await waitFor(() => {
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('shows error toast when API fails on load', async () => {
    mockApi.get.mockRejectedValue(new Error('server error'));
    const { MarketplaceModerationPage } = await import('./MarketplaceModerationPage');
    render(<MarketplaceModerationPage />);
    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('renders page header', async () => {
    const { MarketplaceModerationPage } = await import('./MarketplaceModerationPage');
    render(<MarketplaceModerationPage />);
    expect(
      await screen.findByRole('heading', { level: 1, name: 'Moderation Queue' })
    ).toBeInTheDocument();
    expect(
      screen.getByText('Review and moderate marketplace listings pending approval')
    ).toBeInTheDocument();
  });

  it('renders moderation filter tabs', async () => {
    const { MarketplaceModerationPage } = await import('./MarketplaceModerationPage');
    render(<MarketplaceModerationPage />);
    await waitFor(() => {
      const tabs = screen.getAllByRole('tab');
      expect(tabs.length).toBeGreaterThanOrEqual(1);
    });
  });

  it('does not show approve/reject for approved listings', async () => {
    mockApi.get.mockResolvedValue(makeApiResponse([makeListing({ id: 20, moderation_status: 'approved' })]));
    const { MarketplaceModerationPage } = await import('./MarketplaceModerationPage');
    render(<MarketplaceModerationPage />);
    await waitFor(() => screen.getByText('Handmade Candle'));
    // No approve button for already-approved items
    expect(screen.queryByLabelText(/approve listing/i)).toBeNull();
  });

  it('renders listing price with currency', async () => {
    mockApi.get.mockResolvedValue(makeApiResponse([makeListing({ price: 25, price_currency: 'EUR' })]));
    const { MarketplaceModerationPage } = await import('./MarketplaceModerationPage');
    render(<MarketplaceModerationPage />);
    await waitFor(() => {
      expect(screen.getByText(/€25\.00/)).toBeInTheDocument();
    });
  });

  it('does not invent decimal places for zero-decimal listing currencies', async () => {
    mockApi.get.mockResolvedValue(makeApiResponse([makeListing({ price: 2500, price_currency: 'JPY' })]));
    const { MarketplaceModerationPage } = await import('./MarketplaceModerationPage');
    render(<MarketplaceModerationPage />);

    const price = await screen.findByText((content) => /2[,.]500/.test(content));
    expect(price.textContent).not.toMatch(/[,.]00(?:\D|$)/);
  });
});
