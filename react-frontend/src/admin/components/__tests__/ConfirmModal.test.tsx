// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for ConfirmModal — confirmation dialog for destructive actions
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { HeroUIProvider } from '@heroui/react';

// ─── Stable mock references ─────────────────────────────────────────────────

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    user: { id: 1, name: 'Admin User', role: 'admin' },
    isAuthenticated: true,
    logout: vi.fn(),
  })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Community', slug: 'test', configuration: {} },
    tenantSlug: 'test',
    branding: { name: 'Test Community' },
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
    tenantPath: (p: string) => `/test${p}`,
  })),
  useToast: vi.fn(() => ({
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
    showToast: vi.fn(),
  })),
  useTheme: () => ({ resolvedTheme: 'light', toggleTheme: vi.fn(), theme: 'system', setTheme: vi.fn() }),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
}));

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

import { ConfirmModal } from '../ConfirmModal';

// ─── Wrapper ─────────────────────────────────────────────────────────────────

function W({ children }: { children: React.ReactNode }) {
  return (
    <HeroUIProvider>
      <MemoryRouter initialEntries={['/test/admin']}>
        {children}
      </MemoryRouter>
    </HeroUIProvider>
  );
}

// ─── Tests ───────────────────────────────────────────────────────────────────

describe('ConfirmModal', () => {
  const defaultProps = {
    isOpen: true,
    onClose: vi.fn(),
    onConfirm: vi.fn(),
    title: 'Delete User',
    message: 'Are you sure you want to delete this user?',
  };

  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders without crashing when open', () => {
    render(<W><ConfirmModal {...defaultProps} /></W>);
    expect(screen.getByText('Delete User')).toBeTruthy();
  });

  it('renders the title', () => {
    render(<W><ConfirmModal {...defaultProps} /></W>);
    expect(screen.getByText('Delete User')).toBeTruthy();
  });

  it('renders the message', () => {
    render(<W><ConfirmModal {...defaultProps} /></W>);
    expect(screen.getByText('Are you sure you want to delete this user?')).toBeTruthy();
  });

  it('renders the cancel button', () => {
    render(<W><ConfirmModal {...defaultProps} /></W>);
    expect(screen.getByText('Cancel')).toBeTruthy();
  });

  it('renders the confirm button with default label', () => {
    render(<W><ConfirmModal {...defaultProps} /></W>);
    expect(screen.getByText('Confirm')).toBeTruthy();
  });

  it('renders custom confirm label', () => {
    render(
      <W><ConfirmModal {...defaultProps} confirmLabel="Yes, Delete" /></W>
    );
    expect(screen.getByText('Yes, Delete')).toBeTruthy();
  });

  it('calls onConfirm when confirm button is clicked', () => {
    const onConfirm = vi.fn();
    render(<W><ConfirmModal {...defaultProps} onConfirm={onConfirm} /></W>);
    fireEvent.click(screen.getByText('Confirm'));
    expect(onConfirm).toHaveBeenCalledTimes(1);
  });

  it('calls onClose when cancel button is clicked', () => {
    const onClose = vi.fn();
    render(<W><ConfirmModal {...defaultProps} onClose={onClose} /></W>);
    fireEvent.click(screen.getByText('Cancel'));
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it('does not render content when closed', () => {
    const { container } = render(
      <W><ConfirmModal {...defaultProps} isOpen={false} /></W>
    );
    expect(container.textContent).not.toContain('Delete User');
  });

  it('renders children content', () => {
    render(
      <W>
        <ConfirmModal {...defaultProps}>
          <p data-testid="extra-content">Extra warning text</p>
        </ConfirmModal>
      </W>
    );
    expect(screen.getByTestId('extra-content')).toBeTruthy();
    expect(screen.getByText('Extra warning text')).toBeTruthy();
  });
});
