// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for Layout and AuthLayout components
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import React from 'react';

// Mock child components to isolate Layout logic
vi.mock('./Navbar', () => ({
  Navbar: ({ onMobileMenuOpen }: any) => (
    <nav data-testid="navbar">Navbar Mock</nav>
  ),
}));
vi.mock('./MobileDrawer', () => ({
  MobileDrawer: ({ isOpen }: any) => (
    isOpen ? <div data-testid="mobile-drawer">MobileDrawer Mock</div> : null
  ),
}));
vi.mock('./MobileTabBar', () => ({
  MobileTabBar: () => <div data-testid="mobile-tab-bar">MobileTabBar Mock</div>,
}));
vi.mock('./Footer', () => ({
  Footer: () => <footer data-testid="footer">Footer Mock</footer>,
}));
vi.mock('@/components/ui/BackToTop', () => ({
  BackToTop: () => <div data-testid="back-to-top">BackToTop Mock</div>,
}));
vi.mock('@/components/feedback/OfflineIndicator', () => ({
  OfflineIndicator: () => <div data-testid="offline-indicator">Offline Mock</div>,
}));
vi.mock('@/components/feedback', () => ({
  SessionExpiredModal: () => <div data-testid="session-modal">SessionExpired Mock</div>,
}));
vi.mock('@/components/feedback/AppUpdateModal', () => ({
  AppUpdateModal: () => null,
}));
vi.mock('@/hooks', () => ({
  useApiErrorHandler: vi.fn(),
}));
vi.mock('@/hooks/useAppUpdate', () => ({
  useAppUpdate: vi.fn(() => ({ updateInfo: null, dismiss: vi.fn() })),
}));

// Mock react-router-dom Outlet to render test content
vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return {
    ...actual,
    Outlet: () => <div data-testid="outlet">Page Content</div>,
  };
});

import { Layout, AuthLayout } from './Layout';

describe('Layout', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders children via Outlet', () => {
    render(<Layout />);
    expect(screen.getByTestId('outlet')).toBeInTheDocument();
    expect(screen.getByText('Page Content')).toBeInTheDocument();
  });

  it('renders the Navbar by default', () => {
    render(<Layout />);
    expect(screen.getByTestId('navbar')).toBeInTheDocument();
  });

  it('renders the Footer by default', () => {
    render(<Layout />);
    expect(screen.getByTestId('footer')).toBeInTheDocument();
  });

  it('renders MobileTabBar by default', () => {
    render(<Layout />);
    expect(screen.getByTestId('mobile-tab-bar')).toBeInTheDocument();
  });

  it('renders BackToTop', () => {
    render(<Layout />);
    expect(screen.getByTestId('back-to-top')).toBeInTheDocument();
  });

  it('renders OfflineIndicator', () => {
    render(<Layout />);
    expect(screen.getByTestId('offline-indicator')).toBeInTheDocument();
  });

  it('renders SessionExpiredModal', () => {
    render(<Layout />);
    expect(screen.getByTestId('session-modal')).toBeInTheDocument();
  });

  it('hides Navbar when showNavbar is false', () => {
    render(<Layout showNavbar={false} />);
    expect(screen.queryByTestId('navbar')).not.toBeInTheDocument();
  });

  it('hides Footer when showFooter is false', () => {
    render(<Layout showFooter={false} />);
    expect(screen.queryByTestId('footer')).not.toBeInTheDocument();
  });

  it('hides MobileTabBar when showNavbar is false', () => {
    render(<Layout showNavbar={false} />);
    expect(screen.queryByTestId('mobile-tab-bar')).not.toBeInTheDocument();
  });

  it('adds padding class when showNavbar and withNavbarPadding are true', () => {
    const { container } = render(<Layout showNavbar={true} withNavbarPadding={true} />);
    const main = container.querySelector('main');
    expect(main?.className).toContain('pt-16');
  });

  it('does not add padding class when withNavbarPadding is false', () => {
    const { container } = render(<Layout showNavbar={true} withNavbarPadding={false} />);
    const main = container.querySelector('main');
    expect(main?.className).not.toContain('pt-16');
  });
});

describe('AuthLayout', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders children via Outlet', () => {
    render(<AuthLayout />);
    expect(screen.getByTestId('outlet')).toBeInTheDocument();
    expect(screen.getByText('Page Content')).toBeInTheDocument();
  });

  it('does NOT render Navbar', () => {
    render(<AuthLayout />);
    expect(screen.queryByTestId('navbar')).not.toBeInTheDocument();
  });

  it('does NOT render Footer component', () => {
    render(<AuthLayout />);
    expect(screen.queryByTestId('footer')).not.toBeInTheDocument();
  });

  it('does NOT render MobileTabBar', () => {
    render(<AuthLayout />);
    expect(screen.queryByTestId('mobile-tab-bar')).not.toBeInTheDocument();
  });

  it('renders AGPL attribution link', () => {
    render(<AuthLayout />);
    const link = screen.getByText('Built on Project NEXUS by Jasper Ford');
    expect(link).toBeInTheDocument();
    expect(link.closest('a')).toHaveAttribute(
      'href',
      'https://github.com/jasperfordesq-ai/nexus-v1',
    );
  });

  it('attribution link opens in new tab', () => {
    render(<AuthLayout />);
    const link = screen.getByText('Built on Project NEXUS by Jasper Ford').closest('a');
    expect(link).toHaveAttribute('target', '_blank');
    expect(link).toHaveAttribute('rel', 'noopener noreferrer');
  });
});
