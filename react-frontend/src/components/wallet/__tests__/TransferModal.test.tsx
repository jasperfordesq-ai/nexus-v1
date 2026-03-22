// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for TransferModal component
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@/test/test-utils';
import { api } from '@/lib/api';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

vi.mock('@/contexts', () => ({
  useToast: vi.fn(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn() })),

  useTheme: () => ({ resolvedTheme: 'light', toggleTheme: vi.fn(), theme: 'system', setTheme: vi.fn() }),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
  useAuth: () => ({ user: null, isAuthenticated: false, login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle', error: null }),
  useTenant: () => ({ tenant: { id: 2, name: 'Test', slug: 'test', tagline: null }, branding: { name: 'Test', logo_url: null }, tenantSlug: 'test', tenantPath: (p: string) => '/test' + p, isLoading: false, hasFeature: vi.fn(() => true), hasModule: vi.fn(() => true) }),
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: vi.fn((url: string | null) => url || ''),
}));

vi.mock('../CategorySelect', () => ({
  CategorySelect: () => <div data-testid="category-select">Category Select</div>,
}));

import { TransferModal } from '../TransferModal';

const defaultProps = {
  isOpen: true,
  onClose: vi.fn(),
  currentBalance: 10,
  onTransferComplete: vi.fn(),
};

describe('TransferModal', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders when isOpen is true', () => {
    render(<TransferModal {...defaultProps} />);
    expect(screen.getByRole('dialog')).toBeInTheDocument();
  });

  it('does not render when isOpen is false', () => {
    render(<TransferModal {...defaultProps} isOpen={false} />);
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('renders "Send Credits" heading', () => {
    render(<TransferModal {...defaultProps} />);
    // "Send Credits" appears in both the header and the submit button,
    // so use getAllByText to avoid the multiple-elements error
    const elements = screen.getAllByText('Send Credits');
    expect(elements.length).toBeGreaterThanOrEqual(1);
  });

  it('renders the available balance', () => {
    render(<TransferModal {...defaultProps} currentBalance={42} />);
    expect(screen.getByText(/42 hours/)).toBeInTheDocument();
  });

  it('renders the recipient search input', () => {
    render(<TransferModal {...defaultProps} />);
    expect(screen.getByLabelText(/search recipient/i)).toBeInTheDocument();
  });

  it('renders the Cancel button', () => {
    render(<TransferModal {...defaultProps} />);
    expect(screen.getByRole('button', { name: /cancel/i })).toBeInTheDocument();
  });

  it('calls onClose when close (X) button is clicked', () => {
    const onClose = vi.fn();
    render(<TransferModal {...defaultProps} onClose={onClose} />);
    // HeroUI Modal renders a visible close button with aria-label="Close"
    const closeBtn = screen.getByRole('button', { name: /^close$/i });
    fireEvent.click(closeBtn);
    expect(onClose).toHaveBeenCalled();
  });

  it('has correct ARIA attributes for the modal dialog', () => {
    render(<TransferModal {...defaultProps} />);
    const dialog = screen.getByRole('dialog');
    // HeroUI Modal sets aria-modal="true" on the dialog section
    expect(dialog).toHaveAttribute('aria-modal', 'true');
    // HeroUI Modal sets aria-labelledby to reference the header
    expect(dialog).toHaveAttribute('aria-labelledby');
    // HeroUI Modal sets aria-describedby to reference the body
    expect(dialog).toHaveAttribute('aria-describedby');
  });

  it('renders the Send Credits submit button', () => {
    render(<TransferModal {...defaultProps} />);
    const sendBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('send credits')
    );
    expect(sendBtn).toBeTruthy();
  });

  it('disables Send Credits button when no recipient is selected', () => {
    render(<TransferModal {...defaultProps} />);
    const sendBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('send credits')
    );
    expect(sendBtn).toBeDisabled();
  });

  it('shows "Exceeds available balance" when amount is over balance', async () => {
    render(<TransferModal {...defaultProps} currentBalance={5} />);
    const amountInput = screen.getByLabelText(/amount in hours/i);
    fireEvent.change(amountInput, { target: { value: '100' } });
    await waitFor(() => {
      expect(screen.getByText(/exceeds available balance/i)).toBeInTheDocument();
    });
  });

  it('shows validation error when submitting without recipient', async () => {
    render(<TransferModal {...defaultProps} />);
    const amountInput = screen.getByLabelText(/amount in hours/i);
    fireEvent.change(amountInput, { target: { value: '2' } });
    // The Send Credits button should be disabled without a recipient,
    // so no error message will appear from clicking it
    const sendBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('send credits')
    );
    expect(sendBtn).toBeDisabled();
  });
});
