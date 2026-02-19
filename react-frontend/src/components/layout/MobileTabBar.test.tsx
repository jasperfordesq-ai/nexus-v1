// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for MobileTabBar component
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import React from 'react';

// --- Mocks ---

const mockNavigate = vi.fn();
let mockPathname = '/dashboard';

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return {
    ...actual,
    useNavigate: () => mockNavigate,
    useLocation: () => ({
      pathname: mockPathname,
      search: '',
      hash: '',
      state: null,
      key: 'default',
    }),
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

vi.mock('@/contexts', () => ({
  useAuth: (...args: any[]) => mockUseAuth(...args),
  useTenant: (...args: any[]) => mockUseTenant(...args),
  useNotifications: (...args: any[]) => mockUseNotifications(...args),
}));

vi.mock('./QuickCreateMenu', () => ({
  QuickCreateMenu: ({ isOpen }: any) => (
    isOpen ? <div data-testid="quick-create-menu">Quick Create</div> : null
  ),
}));

import { MobileTabBar } from './MobileTabBar';

function setupDefaultMocks(overrides: {
  auth?: Record<string, any>;
  tenant?: Record<string, any>;
  notifications?: Record<string, any>;
} = {}) {
  mockUseAuth.mockReturnValue({
    isAuthenticated: true,
    user: { id: 1, first_name: 'Test', last_name: 'User', role: 'member' },
    ...overrides.auth,
  });
  mockUseTenant.mockReturnValue({
    hasModule: vi.fn(() => true),
    tenantPath: (p: string) => p,
    ...overrides.tenant,
  });
  mockUseNotifications.mockReturnValue({
    counts: { messages: 0, notifications: 0 },
    ...overrides.notifications,
  });
}

describe('MobileTabBar', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockPathname = '/dashboard';
    setupDefaultMocks();
  });

  describe('Visibility', () => {
    it('renders when user is authenticated', () => {
      render(<MobileTabBar />);
      expect(screen.getByLabelText('Mobile navigation')).toBeInTheDocument();
    });

    it('does NOT render when user is not authenticated', () => {
      setupDefaultMocks({ auth: { isAuthenticated: false } });
      render(<MobileTabBar />);
      expect(screen.queryByLabelText('Mobile navigation')).not.toBeInTheDocument();
    });

    it('does NOT render on login page', () => {
      mockPathname = '/login';
      render(<MobileTabBar />);
      expect(screen.queryByLabelText('Mobile navigation')).not.toBeInTheDocument();
    });

    it('does NOT render on register page', () => {
      mockPathname = '/register';
      render(<MobileTabBar />);
      expect(screen.queryByLabelText('Mobile navigation')).not.toBeInTheDocument();
    });

    it('does NOT render on onboarding page', () => {
      mockPathname = '/onboarding';
      render(<MobileTabBar />);
      expect(screen.queryByLabelText('Mobile navigation')).not.toBeInTheDocument();
    });

    it('does NOT render on forgot-password page', () => {
      mockPathname = '/forgot-password';
      render(<MobileTabBar />);
      expect(screen.queryByLabelText('Mobile navigation')).not.toBeInTheDocument();
    });
  });

  describe('Tab icons', () => {
    it('renders Home tab', () => {
      render(<MobileTabBar />);
      expect(screen.getByLabelText('Home')).toBeInTheDocument();
    });

    it('renders Listings tab when module enabled', () => {
      render(<MobileTabBar />);
      expect(screen.getByLabelText('Listings')).toBeInTheDocument();
    });

    it('renders Create tab', () => {
      render(<MobileTabBar />);
      expect(screen.getByLabelText('Create new content')).toBeInTheDocument();
    });

    it('renders Messages tab when module enabled', () => {
      render(<MobileTabBar />);
      expect(screen.getByLabelText('Messages')).toBeInTheDocument();
    });

    it('renders Menu tab', () => {
      render(<MobileTabBar />);
      expect(screen.getByLabelText('Menu')).toBeInTheDocument();
    });
  });

  describe('Module gating', () => {
    it('hides Listings tab when listings module is disabled', () => {
      setupDefaultMocks({
        tenant: {
          hasModule: vi.fn((mod: string) => mod !== 'listings'),
        },
      });
      render(<MobileTabBar />);
      expect(screen.queryByLabelText('Listings')).not.toBeInTheDocument();
    });

    it('hides Messages tab when messages module is disabled', () => {
      setupDefaultMocks({
        tenant: {
          hasModule: vi.fn((mod: string) => mod !== 'messages'),
        },
      });
      render(<MobileTabBar />);
      expect(screen.queryByLabelText('Messages')).not.toBeInTheDocument();
    });

    it('hides Home tab when dashboard module is disabled', () => {
      setupDefaultMocks({
        tenant: {
          hasModule: vi.fn((mod: string) => mod !== 'dashboard'),
        },
      });
      render(<MobileTabBar />);
      expect(screen.queryByLabelText('Home')).not.toBeInTheDocument();
    });

    it('always shows Create and Menu tabs regardless of modules', () => {
      setupDefaultMocks({
        tenant: {
          hasModule: vi.fn(() => false),
        },
      });
      render(<MobileTabBar />);
      expect(screen.getByLabelText('Create new content')).toBeInTheDocument();
      expect(screen.getByLabelText('Menu')).toBeInTheDocument();
    });
  });

  describe('Active state', () => {
    it('marks Home tab as active on dashboard route', () => {
      mockPathname = '/dashboard';
      render(<MobileTabBar />);
      const homeButton = screen.getByLabelText('Home');
      expect(homeButton).toHaveAttribute('aria-current', 'page');
    });

    it('marks Listings tab as active on listings route', () => {
      mockPathname = '/listings';
      render(<MobileTabBar />);
      const listingsButton = screen.getByLabelText('Listings');
      expect(listingsButton).toHaveAttribute('aria-current', 'page');
    });

    it('marks Messages tab as active on messages route', () => {
      mockPathname = '/messages';
      render(<MobileTabBar />);
      const messagesButton = screen.getByLabelText('Messages');
      expect(messagesButton).toHaveAttribute('aria-current', 'page');
    });
  });

  describe('Menu callback', () => {
    it('calls onMenuOpen when Menu tab is pressed', () => {
      const onMenuOpen = vi.fn();
      render(<MobileTabBar onMenuOpen={onMenuOpen} />);
      const menuButton = screen.getByLabelText('Menu');
      menuButton.click();
      expect(onMenuOpen).toHaveBeenCalledTimes(1);
    });
  });

  describe('Message badge', () => {
    it('shows message count badge when there are unread messages', () => {
      setupDefaultMocks({
        notifications: { counts: { messages: 5, notifications: 0 } },
      });
      render(<MobileTabBar />);
      expect(screen.getByText('5')).toBeInTheDocument();
    });

    it('does NOT show badge when there are no unread messages', () => {
      setupDefaultMocks({
        notifications: { counts: { messages: 0, notifications: 0 } },
      });
      render(<MobileTabBar />);
      // No numeric badge should appear
      expect(screen.queryByText('0')).not.toBeInTheDocument();
    });

    it('shows 99+ when messages exceed 99', () => {
      setupDefaultMocks({
        notifications: { counts: { messages: 150, notifications: 0 } },
      });
      render(<MobileTabBar />);
      expect(screen.getByText('99+')).toBeInTheDocument();
    });
  });
});
