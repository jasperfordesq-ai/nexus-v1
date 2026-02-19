// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for MobileDrawer component
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';

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
      return <a href={to} className={cls}>{children}</a>;
    },
  };
});

const mockUseAuth = vi.fn();
const mockUseTenant = vi.fn();
const mockUseNotifications = vi.fn();

vi.mock('@/contexts', () => ({
  useAuth: (...args: any[]) => mockUseAuth(...args),
  useTenant: (...args: any[]) => mockUseTenant(...args),
  useNotifications: (...args: any[]) => mockUseNotifications(...args),
}));

vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: (url: string | undefined) => url || '/default-avatar.png',
}));

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn() },
  tokenManager: { getAccessToken: vi.fn(() => 'mock-token') },
  API_BASE: 'http://localhost:8090/api',
}));

import { MobileDrawer } from './MobileDrawer';

function setupDefaultMocks(overrides: {
  auth?: Record<string, any>;
  tenant?: Record<string, any>;
  notifications?: Record<string, any>;
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
}

describe('MobileDrawer', () => {
  const defaultProps = {
    isOpen: true,
    onClose: vi.fn(),
    onSearchOpen: vi.fn(),
  };

  beforeEach(() => {
    vi.clearAllMocks();
    setupDefaultMocks();
  });

  describe('Rendering when open', () => {
    it('renders the drawer when isOpen is true', () => {
      render(<MobileDrawer {...defaultProps} />);
      // The drawer renders a Close menu button
      expect(screen.getByLabelText('Close menu')).toBeInTheDocument();
    });

    it('renders the brand name', () => {
      render(<MobileDrawer {...defaultProps} />);
      expect(screen.getByText('Test Community')).toBeInTheDocument();
    });

    it('renders the search button', () => {
      render(<MobileDrawer {...defaultProps} />);
      expect(screen.getByLabelText('Open search')).toBeInTheDocument();
    });
  });

  describe('Navigation links', () => {
    it('renders Home navigation link', () => {
      render(<MobileDrawer {...defaultProps} />);
      expect(screen.getByText('Home')).toBeInTheDocument();
    });

    it('renders Listings navigation link when module is enabled', () => {
      setupDefaultMocks({
        tenant: {
          hasModule: vi.fn(() => true),
        },
      });
      render(<MobileDrawer {...defaultProps} />);
      expect(screen.getByText('Listings')).toBeInTheDocument();
    });

    it('renders About section with universal items', () => {
      render(<MobileDrawer {...defaultProps} />);
      // "About" appears as both a section heading and a nav link; use getAllByText
      const aboutElements = screen.getAllByText('About');
      expect(aboutElements.length).toBeGreaterThanOrEqual(1);
      expect(screen.getByText('FAQ')).toBeInTheDocument();
      expect(screen.getByText('Timebanking Guide')).toBeInTheDocument();
    });

    it('renders Support section', () => {
      render(<MobileDrawer {...defaultProps} />);
      expect(screen.getByText('Help Center')).toBeInTheDocument();
      expect(screen.getByText('Contact')).toBeInTheDocument();
    });

    it('renders Legal section', () => {
      render(<MobileDrawer {...defaultProps} />);
      expect(screen.getByText('Terms of Service')).toBeInTheDocument();
      expect(screen.getByText('Privacy Policy')).toBeInTheDocument();
      expect(screen.getByText('Accessibility')).toBeInTheDocument();
    });
  });

  describe('Unauthenticated state', () => {
    it('shows Log In button when not authenticated', () => {
      render(<MobileDrawer {...defaultProps} />);
      expect(screen.getByText('Log In')).toBeInTheDocument();
    });

    it('shows Sign Up button when not authenticated', () => {
      render(<MobileDrawer {...defaultProps} />);
      expect(screen.getByText('Sign Up')).toBeInTheDocument();
    });

    it('does NOT show user info when not authenticated', () => {
      render(<MobileDrawer {...defaultProps} />);
      expect(screen.queryByText('Credits')).not.toBeInTheDocument();
    });

    it('does NOT show Settings when not authenticated', () => {
      render(<MobileDrawer {...defaultProps} />);
      expect(screen.queryByText('Settings')).not.toBeInTheDocument();
    });

    it('does NOT show Log Out when not authenticated', () => {
      render(<MobileDrawer {...defaultProps} />);
      expect(screen.queryByText('Log Out')).not.toBeInTheDocument();
    });
  });

  describe('Authenticated state', () => {
    beforeEach(() => {
      setupDefaultMocks({
        auth: {
          user: {
            id: 1,
            first_name: 'Jane',
            last_name: 'Smith',
            email: 'jane@example.com',
            avatar_url: '/jane.jpg',
            role: 'member',
            balance: 10,
          },
          isAuthenticated: true,
        },
      });
    });

    it('shows user name when authenticated', () => {
      render(<MobileDrawer {...defaultProps} />);
      expect(screen.getByText('Jane Smith')).toBeInTheDocument();
    });

    it('shows user email when authenticated', () => {
      render(<MobileDrawer {...defaultProps} />);
      expect(screen.getByText('jane@example.com')).toBeInTheDocument();
    });

    it('shows user balance in quick stats', () => {
      render(<MobileDrawer {...defaultProps} />);
      expect(screen.getByText('10')).toBeInTheDocument();
      expect(screen.getByText('Credits')).toBeInTheDocument();
    });

    it('does NOT show Log In / Sign Up when authenticated', () => {
      render(<MobileDrawer {...defaultProps} />);
      expect(screen.queryByText('Log In')).not.toBeInTheDocument();
      expect(screen.queryByText('Sign Up')).not.toBeInTheDocument();
    });

    it('shows Settings link when authenticated', () => {
      render(<MobileDrawer {...defaultProps} />);
      expect(screen.getByText('Settings')).toBeInTheDocument();
    });

    it('shows Log Out button when authenticated', () => {
      render(<MobileDrawer {...defaultProps} />);
      expect(screen.getByText('Log Out')).toBeInTheDocument();
    });

    it('shows Dashboard link when authenticated and module enabled', () => {
      render(<MobileDrawer {...defaultProps} />);
      expect(screen.getByText('Dashboard')).toBeInTheDocument();
    });
  });

  describe('Admin tools', () => {
    it('shows Admin Tools section for admin users', () => {
      setupDefaultMocks({
        auth: {
          user: {
            id: 1,
            first_name: 'Admin',
            last_name: 'User',
            email: 'admin@example.com',
            role: 'admin',
            is_admin: true,
          },
          isAuthenticated: true,
        },
      });
      render(<MobileDrawer {...defaultProps} />);
      expect(screen.getByText('Admin Panel')).toBeInTheDocument();
      expect(screen.getByText('Legacy Admin')).toBeInTheDocument();
    });

    it('does NOT show Admin Tools for regular members', () => {
      setupDefaultMocks({
        auth: {
          user: {
            id: 1,
            first_name: 'Regular',
            last_name: 'User',
            email: 'user@example.com',
            role: 'member',
          },
          isAuthenticated: true,
        },
      });
      render(<MobileDrawer {...defaultProps} />);
      expect(screen.queryByText('Admin Panel')).not.toBeInTheDocument();
      expect(screen.queryByText('Legacy Admin')).not.toBeInTheDocument();
    });
  });

  describe('Tenant-specific about items', () => {
    it('shows hOUR Timebank specific items when slug is hour-timebank', () => {
      setupDefaultMocks({
        tenant: {
          tenant: { id: 2, name: 'hOUR Timebank', slug: 'hour-timebank' },
          hasFeature: vi.fn(() => false),
          hasModule: vi.fn(() => true),
        },
      });
      render(<MobileDrawer {...defaultProps} />);
      expect(screen.getByText('Partner With Us')).toBeInTheDocument();
      expect(screen.getByText('Social Prescribing')).toBeInTheDocument();
      expect(screen.getByText('Our Impact')).toBeInTheDocument();
      expect(screen.getByText('Impact Report')).toBeInTheDocument();
      expect(screen.getByText('Strategic Plan')).toBeInTheDocument();
    });

    it('does NOT show hOUR Timebank items for other tenants', () => {
      setupDefaultMocks({
        tenant: {
          tenant: { id: 3, name: 'Other', slug: 'other-tenant' },
          hasFeature: vi.fn(() => false),
          hasModule: vi.fn(() => true),
        },
      });
      render(<MobileDrawer {...defaultProps} />);
      expect(screen.queryByText('Partner With Us')).not.toBeInTheDocument();
      expect(screen.queryByText('Social Prescribing')).not.toBeInTheDocument();
      expect(screen.queryByText('Our Impact')).not.toBeInTheDocument();
    });
  });

  describe('Feature-gated items', () => {
    it('shows Events link when events feature is enabled', () => {
      setupDefaultMocks({
        tenant: {
          hasFeature: vi.fn((f: string) => f === 'events'),
          hasModule: vi.fn(() => true),
        },
      });
      render(<MobileDrawer {...defaultProps} />);
      expect(screen.getByText('Events')).toBeInTheDocument();
    });

    it('does NOT show Events link when events feature is disabled', () => {
      setupDefaultMocks({
        tenant: {
          hasFeature: vi.fn(() => false),
          hasModule: vi.fn(() => true),
        },
      });
      render(<MobileDrawer {...defaultProps} />);
      expect(screen.queryByText('Events')).not.toBeInTheDocument();
    });

    it('shows Explore section when gamification feature is enabled', () => {
      setupDefaultMocks({
        tenant: {
          hasFeature: vi.fn((f: string) => f === 'gamification'),
          hasModule: vi.fn(() => true),
        },
      });
      render(<MobileDrawer {...defaultProps} />);
      expect(screen.getByText('Achievements')).toBeInTheDocument();
      expect(screen.getByText('Leaderboard')).toBeInTheDocument();
    });
  });

  describe('AGPL attribution', () => {
    it('renders Built on Project NEXUS attribution link', () => {
      render(<MobileDrawer {...defaultProps} />);
      const link = screen.getByText('Built on Project NEXUS by Jasper Ford');
      expect(link).toBeInTheDocument();
      expect(link.closest('a')).toHaveAttribute(
        'href',
        'https://github.com/jasperfordesq-ai/nexus-v1',
      );
    });
  });
});
