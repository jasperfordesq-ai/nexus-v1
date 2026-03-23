// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for MenuContext (MenuProvider + useMenuContext)
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { ReactNode } from 'react';

// Mock dependencies used by MenuContext
const mockRefresh = vi.fn().mockResolvedValue(undefined);

vi.mock('@/hooks/useMenus', () => ({
  useMenus: vi.fn(() => ({
    menus: {
      'header-main': [
        { id: 1, location: 'header-main', label: 'Home', items: [] },
      ],
      mobile: [
        { id: 2, location: 'mobile', label: 'Mobile Home', items: [] },
      ],
      footer: [
        { id: 3, location: 'footer', label: 'Footer Link', items: [] },
      ],
    },
    isLoading: false,
    hasCustomMenus: true,
    refresh: mockRefresh,
  })),
}));

// MenuContext imports useAuth from './AuthContext' and useTenant from './TenantContext'
// These resolve to @/contexts/AuthContext and @/contexts/TenantContext
vi.mock('@/contexts/AuthContext', () => ({
  useAuth: vi.fn(() => ({
    isAuthenticated: true,
    user: { id: 1, name: 'Test User', role: 'user' },
  })),
}));

vi.mock('@/contexts/TenantContext', () => ({
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
  })),
}));

// Import after mocks
import { MenuProvider, useMenuContext } from '../MenuContext';

function TestConsumer() {
  const ctx = useMenuContext();
  return (
    <div>
      <div data-testid="header-count">{ctx.headerMenus.length}</div>
      <div data-testid="mobile-count">{ctx.mobileMenus.length}</div>
      <div data-testid="footer-count">{ctx.footerMenus.length}</div>
      <div data-testid="is-loading">{ctx.isLoading ? 'true' : 'false'}</div>
      <div data-testid="has-custom">{ctx.hasCustomMenus ? 'true' : 'false'}</div>
      <button data-testid="refresh-btn" onClick={() => ctx.refreshMenus()}>Refresh</button>
    </div>
  );
}

function renderWithProvider(ui: ReactNode) {
  return render(<MenuProvider>{ui}</MenuProvider>);
}

describe('MenuContext', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('provides header menus', () => {
    renderWithProvider(<TestConsumer />);
    expect(screen.getByTestId('header-count').textContent).toBe('1');
  });

  it('provides mobile menus', () => {
    renderWithProvider(<TestConsumer />);
    expect(screen.getByTestId('mobile-count').textContent).toBe('1');
  });

  it('provides footer menus', () => {
    renderWithProvider(<TestConsumer />);
    expect(screen.getByTestId('footer-count').textContent).toBe('1');
  });

  it('provides isLoading state', () => {
    renderWithProvider(<TestConsumer />);
    expect(screen.getByTestId('is-loading').textContent).toBe('false');
  });

  it('provides hasCustomMenus flag', () => {
    renderWithProvider(<TestConsumer />);
    expect(screen.getByTestId('has-custom').textContent).toBe('true');
  });

  it('provides refreshMenus function', () => {
    renderWithProvider(<TestConsumer />);
    expect(screen.getByTestId('refresh-btn')).toBeInTheDocument();
  });

  it('throws when useMenuContext is used outside MenuProvider', () => {
    const consoleSpy = vi.spyOn(console, 'error').mockImplementation(() => {});

    expect(() => {
      render(<TestConsumer />);
    }).toThrow('useMenuContext must be used within a MenuProvider');

    consoleSpy.mockRestore();
  });
});
