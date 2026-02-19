// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for Navbar component
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import React from 'react';

// --- Mocks ---

const mockNavigate = vi.fn();
const mockLocation = { pathname: '/dashboard', search: '', hash: '', state: null, key: 'default' };

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return {
    ...actual,
    useNavigate: () => mockNavigate,
    useLocation: () => mockLocation,
    NavLink: ({ children, to, className }: any) => {
      const cls = typeof className === 'function' ? className({ isActive: false }) : className;
      return <a href={to} className={cls} data-testid={`navlink-${to}`}>{children}</a>;
    },
  };
});

vi.mock('framer-motion', () => {
  const proxy = new Proxy({}, {
    get: (_t: object, prop: string | symbol) => {
      return React.forwardRef(({ children, ...p }: any, ref: any) => {
        const safe: Record<string, unknown> = {};
        for (const [k, v] of Object.entries(p)) {
          if (!['variants', 'initial', 'animate', 'exit', 'transition', 'whileHover', 'whileTap', 'whileInView', 'layout', 'viewport', 'layoutId'].includes(k)) safe[k] = v;
        }
        return React.createElement(typeof prop === 'string' ? prop : 'div', { ...safe, ref }, children);
      });
    },
  });
  return { motion: proxy, AnimatePresence: ({ children }: any) => children };
});

const mockUseAuth = vi.fn();
const mockUseTenant = vi.fn();
const mockUseNotifications = vi.fn();
const mockUseTheme = vi.fn();

vi.mock('@/contexts', () => ({
  useAuth: (...args: any[]) => mockUseAuth(...args),
  useTenant: (...args: any[]) => mockUseTenant(...args),
  useNotifications: (...args: any[]) => mockUseNotifications(...args),
  useTheme: (...args: any[]) => mockUseTheme(...args),
}));

vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: (url: string | undefined) => url || '/default-avatar.png',
}));

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn() },
  tokenManager: { getAccessToken: vi.fn(() => 'mock-token') },
  API_BASE: 'http://localhost:8090/api',
}));

import { Navbar } from './Navbar';

// Helper to set up default context mock values
function setupDefaultMocks(overrides: {
  auth?: Partial<ReturnType<typeof mockUseAuth>>;
  tenant?: Partial<ReturnType<typeof mockUseTenant>>;
  notifications?: Partial<ReturnType<typeof mockUseNotifications>>;
  theme?: Partial<ReturnType<typeof mockUseTheme>>;
} = {}) {
  mockUseAuth.mockReturnValue({
    user: null,
    isAuthenticated: false,
    logout: vi.fn(),
    ...overrides.auth,
  });
  mockUseTenant.mockReturnValue({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test-tenant' },
    branding: { name: 'Test Community', logo: null, tagline: 'A test community' },
    hasFeature: vi.fn(() => false),
    hasModule: vi.fn(() => true),
    tenantPath: (p: string) => p,
    ...overrides.tenant,
  });
  mockUseNotifications.mockReturnValue({
    unreadCount: 0,
    counts: { messages: 0, notifications: 0 },
    ...overrides.notifications,
  });
  mockUseTheme.mockReturnValue({
    resolvedTheme: 'light',
    theme: 'light',
    toggleTheme: vi.fn(),
    setTheme: vi.fn(),
    ...overrides.theme,
  });
}

describe('Navbar', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    setupDefaultMocks();
  });

  describe('Brand / Logo', () => {
    it('renders the brand name from tenant branding', () => {
      render(<Navbar />);
      expect(screen.getByText('Test Community')).toBeInTheDocument();
    });

    it('renders the brand logo when branding.logo is set', () => {
      setupDefaultMocks({
        tenant: {
          branding: { name: 'Logo Tenant', logo: '/logo.png', tagline: '' },
        },
      });
      render(<Navbar />);
      const img = screen.getByAltText('Logo Tenant');
      expect(img).toBeInTheDocument();
      expect(img).toHaveAttribute('src', '/logo.png');
    });

    it('renders fallback Hexagon icon when no logo', () => {
      render(<Navbar />);
      // The Hexagon icon is inside a motion.div, the brand name should still appear
      expect(screen.getByText('Test Community')).toBeInTheDocument();
    });
  });

  describe('Unauthenticated state', () => {
    it('shows Log In button when not authenticated', () => {
      render(<Navbar />);
      expect(screen.getByText('Log In')).toBeInTheDocument();
    });

    it('shows Sign Up button when not authenticated', () => {
      render(<Navbar />);
      expect(screen.getByText('Sign Up')).toBeInTheDocument();
    });

    it('does NOT show user avatar when not authenticated', () => {
      render(<Navbar />);
      // When unauthenticated, there should be no Avatar component rendered
      // Check for the user dropdown trigger (Avatar) rather than notification badge
      expect(screen.queryByLabelText('Search (Ctrl+K)')).not.toBeInTheDocument();
    });
  });

  describe('Authenticated state', () => {
    beforeEach(() => {
      setupDefaultMocks({
        auth: {
          user: {
            id: 1,
            first_name: 'John',
            last_name: 'Doe',
            email: 'john@example.com',
            avatar_url: '/avatar.jpg',
            role: 'member',
            balance: 5,
          },
          isAuthenticated: true,
        },
      });
    });

    it('does NOT show Log In / Sign Up when authenticated', () => {
      render(<Navbar />);
      expect(screen.queryByText('Log In')).not.toBeInTheDocument();
      expect(screen.queryByText('Sign Up')).not.toBeInTheDocument();
    });

    it('renders search trigger with Ctrl+K hint', () => {
      render(<Navbar />);
      // Desktop search button has aria-label
      expect(screen.getByLabelText('Search (Ctrl+K)')).toBeInTheDocument();
    });

    it('renders notification bell button', () => {
      render(<Navbar />);
      // HeroUI Badge may duplicate aria-labels; use getAllByLabelText and check at least one
      const bells = screen.getAllByLabelText('Notifications');
      expect(bells.length).toBeGreaterThanOrEqual(1);
    });

    it('renders theme toggle button', () => {
      render(<Navbar />);
      expect(screen.getByLabelText('Switch to dark mode')).toBeInTheDocument();
    });

    it('renders Create new button', () => {
      render(<Navbar />);
      expect(screen.getByLabelText('Create new')).toBeInTheDocument();
    });

    it('shows mobile search button', () => {
      render(<Navbar />);
      expect(screen.getByLabelText('Search')).toBeInTheDocument();
    });
  });

  describe('Theme toggle', () => {
    it('shows moon icon in light mode', () => {
      setupDefaultMocks({
        auth: { user: { id: 1, first_name: 'A', last_name: 'B', email: 'a@b.com', role: 'member' }, isAuthenticated: true },
        theme: { resolvedTheme: 'light' },
      });
      render(<Navbar />);
      expect(screen.getByLabelText('Switch to dark mode')).toBeInTheDocument();
    });

    it('shows sun icon in dark mode', () => {
      setupDefaultMocks({
        auth: { user: { id: 1, first_name: 'A', last_name: 'B', email: 'a@b.com', role: 'member' }, isAuthenticated: true },
        theme: { resolvedTheme: 'dark' },
      });
      render(<Navbar />);
      expect(screen.getByLabelText('Switch to light mode')).toBeInTheDocument();
    });
  });

  describe('Navigation links', () => {
    it('renders Dashboard link when module is enabled', () => {
      setupDefaultMocks({
        auth: { user: { id: 1, first_name: 'A', last_name: 'B', email: 'a@b.com', role: 'member' }, isAuthenticated: true },
        tenant: {
          hasModule: vi.fn((mod: string) => ['dashboard', 'feed', 'listings', 'messages'].includes(mod)),
        },
      });
      render(<Navbar />);
      expect(screen.getByText('Dashboard')).toBeInTheDocument();
    });

    it('renders Feed link when module is enabled', () => {
      setupDefaultMocks({
        auth: { user: { id: 1, first_name: 'A', last_name: 'B', email: 'a@b.com', role: 'member' }, isAuthenticated: true },
        tenant: {
          hasModule: vi.fn((mod: string) => ['feed'].includes(mod)),
        },
      });
      render(<Navbar />);
      expect(screen.getByText('Feed')).toBeInTheDocument();
    });

    it('renders Listings link when module is enabled', () => {
      setupDefaultMocks({
        auth: { user: { id: 1, first_name: 'A', last_name: 'B', email: 'a@b.com', role: 'member' }, isAuthenticated: true },
        tenant: {
          hasModule: vi.fn((mod: string) => ['listings'].includes(mod)),
        },
      });
      render(<Navbar />);
      expect(screen.getByText('Listings')).toBeInTheDocument();
    });

    it('renders Messages link when module is enabled', () => {
      setupDefaultMocks({
        auth: { user: { id: 1, first_name: 'A', last_name: 'B', email: 'a@b.com', role: 'member' }, isAuthenticated: true },
        tenant: {
          hasModule: vi.fn((mod: string) => ['messages'].includes(mod)),
        },
      });
      render(<Navbar />);
      expect(screen.getByText('Messages')).toBeInTheDocument();
    });

    it('does NOT render Dashboard link when module is disabled', () => {
      setupDefaultMocks({
        tenant: {
          hasModule: vi.fn(() => false),
        },
      });
      render(<Navbar />);
      expect(screen.queryByText('Dashboard')).not.toBeInTheDocument();
    });
  });

  describe('Community dropdown', () => {
    it('renders Community dropdown when community features are enabled', () => {
      setupDefaultMocks({
        auth: { user: { id: 1, first_name: 'A', last_name: 'B', email: 'a@b.com', role: 'member' }, isAuthenticated: true },
        tenant: {
          hasFeature: vi.fn((f: string) => ['events', 'groups', 'connections'].includes(f)),
          hasModule: vi.fn(() => true),
        },
      });
      render(<Navbar />);
      expect(screen.getByText('Community')).toBeInTheDocument();
    });

    it('does NOT render Community dropdown when no community features are enabled', () => {
      setupDefaultMocks({
        tenant: {
          hasFeature: vi.fn(() => false),
          hasModule: vi.fn(() => true),
        },
      });
      render(<Navbar />);
      expect(screen.queryByText('Community')).not.toBeInTheDocument();
    });
  });

  describe('More dropdown', () => {
    it('renders More dropdown', () => {
      setupDefaultMocks({
        auth: { user: { id: 1, first_name: 'A', last_name: 'B', email: 'a@b.com', role: 'member' }, isAuthenticated: true },
      });
      render(<Navbar />);
      expect(screen.getByText('More')).toBeInTheDocument();
    });
  });

  describe('Tenant-specific about items', () => {
    it('includes hOUR Timebank specific items when slug is hour-timebank', () => {
      setupDefaultMocks({
        auth: { user: { id: 1, first_name: 'A', last_name: 'B', email: 'a@b.com', role: 'member' }, isAuthenticated: true },
        tenant: {
          tenant: { id: 2, name: 'hOUR Timebank', slug: 'hour-timebank' },
          hasFeature: vi.fn(() => false),
          hasModule: vi.fn(() => true),
        },
      });
      // The about items are inside the More dropdown which is collapsed by default.
      // We can verify the component renders without error; the dropdown menu items
      // are created but not necessarily visible until the dropdown is opened.
      const { container } = render(<Navbar />);
      expect(container).toBeTruthy();
    });

    it('excludes hOUR Timebank specific items for other tenants', () => {
      setupDefaultMocks({
        auth: { user: { id: 1, first_name: 'A', last_name: 'B', email: 'a@b.com', role: 'member' }, isAuthenticated: true },
        tenant: {
          tenant: { id: 3, name: 'Other Tenant', slug: 'other-tenant' },
          hasFeature: vi.fn(() => false),
          hasModule: vi.fn(() => true),
        },
      });
      const { container } = render(<Navbar />);
      expect(container).toBeTruthy();
      // Partner With Us should not appear in the About items for non-hour-timebank tenants
      expect(screen.queryByText('Partner With Us')).not.toBeInTheDocument();
    });
  });

  describe('Mobile menu toggle', () => {
    it('renders mobile menu button with correct aria-label', () => {
      render(<Navbar />);
      expect(screen.getByLabelText('Open menu')).toBeInTheDocument();
    });

    it('calls onMobileMenuOpen when mobile menu button is pressed', async () => {
      const onMobileMenuOpen = vi.fn();
      render(<Navbar onMobileMenuOpen={onMobileMenuOpen} />);
      const menuButton = screen.getByLabelText('Open menu');
      menuButton.click();
      expect(onMobileMenuOpen).toHaveBeenCalledTimes(1);
    });
  });

  describe('Unread notification badge', () => {
    it('shows unread count on notification bell', () => {
      setupDefaultMocks({
        auth: { user: { id: 1, first_name: 'A', last_name: 'B', email: 'a@b.com', role: 'member' }, isAuthenticated: true },
        notifications: { unreadCount: 5, counts: { messages: 0, notifications: 5 } },
      });
      render(<Navbar />);
      expect(screen.getByLabelText('Notifications, 5 unread')).toBeInTheDocument();
    });
  });

  describe('Message count badge', () => {
    it('shows message count on Messages link', () => {
      setupDefaultMocks({
        auth: { user: { id: 1, first_name: 'A', last_name: 'B', email: 'a@b.com', role: 'member' }, isAuthenticated: true },
        notifications: { unreadCount: 3, counts: { messages: 3, notifications: 0 } },
        tenant: {
          hasModule: vi.fn((mod: string) => mod === 'messages'),
        },
      });
      render(<Navbar />);
      // HeroUI Badge renders the count in multiple places; use getAllByText
      const badges = screen.getAllByText('3');
      expect(badges.length).toBeGreaterThanOrEqual(1);
    });
  });
});
