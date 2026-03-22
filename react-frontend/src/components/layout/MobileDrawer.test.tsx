// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for MobileDrawer component
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@/test/test-utils';

// --- Mocks ---

const mockNavigate = vi.fn();
const mockLocation = { pathname: '/dashboard', search: '', hash: '', state: null, key: 'default' };

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return {
    ...actual,
    useNavigate: () => mockNavigate,
    useLocation: () => mockLocation,
    NavLink: ({ children, to, className }: { children: React.ReactNode; to: string; className?: string | ((opts: { isActive: boolean }) => string) }) => {
      const cls = typeof className === 'function' ? className({ isActive: false }) : className;
      return <a href={to} className={cls}>{children}</a>;
    },
  };
});

const mockUseAuth = vi.fn();
const mockUseTenant = vi.fn();
const mockUseNotifications = vi.fn();

vi.mock('@/contexts', () => ({
  useAuth: (...args: unknown[]) => mockUseAuth(...args),
  useTenant: (...args: unknown[]) => mockUseTenant(...args),
  useNotifications: (...args: unknown[]) => mockUseNotifications(...args),
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useCookieConsent: () => ({ showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn() }),

  useTheme: () => ({ resolvedTheme: 'light', toggleTheme: vi.fn(), theme: 'system', setTheme: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  readStoredConsent: () => null,
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
  useToast: () => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }),
}));

const i18nMap: Record<string, string> = {
  'nav.home': 'Home', 'nav.dashboard': 'Dashboard', 'nav.feed': 'Feed',
  'nav.listings': 'Listings', 'nav.messages': 'Messages', 'nav.groups': 'Groups',
  'nav.events': 'Events', 'nav.connections': 'Connections', 'nav.exchanges': 'Exchanges',
  'nav.wallet': 'Wallet', 'nav.volunteering': 'Volunteering', 'nav.blog': 'Blog',
  'nav.resources': 'Resources', 'nav.members': 'Members', 'nav.about': 'About',
  'nav.achievements': 'Achievements', 'nav.leaderboard': 'Leaderboard', 'nav.goals': 'Goals',
  'nav.ai_chat': 'AI Chat', 'nav.our_impact': 'Our Impact',
  'nav.timebanking_guide': 'Timebanking Guide', 'nav.faq': 'FAQ',
  'nav.strategic_plan': 'Strategic Plan', 'nav.social_prescribing': 'Social Prescribing',
  'nav.partner_with_us': 'Partner With Us', 'nav.impact_report': 'Impact Report',
  'nav.organisations': 'Organisations', 'nav.partner_communities': 'Partner Communities',
  'nav.group_exchanges': 'Group Exchanges',
  'nav.federation_hub': 'Federation Hub', 'nav.federated_members': 'Federated Members',
  'nav.federated_listings': 'Federated Listings', 'nav.federated_messages': 'Federated Messages',
  'nav.federated_events': 'Federated Events',
  'auth.log_in': 'Log In', 'auth.sign_up': 'Sign Up',
  'account.settings': 'Settings', 'account.log_out': 'Log Out',
  // Admin tool keys used by MobileDrawer (user_menu namespace)
  'user_menu.admin_panel': 'Admin Panel',
  'user_menu.legacy_admin': 'Legacy Admin',
  'user_menu.help_center': 'Help Center',
  'sections.main': 'Main',
  'sections.about': 'About', 'sections.support': 'Support', 'sections.legal': 'Legal',
  'sections.community': 'Community', 'sections.explore': 'Explore',
  'sections.federation': 'Federation', 'sections.account': 'Account',
  'sections.language': 'Language',
  'nav.timebanking': 'Timebanking',
  'support.help_center': 'Help Center', 'support.contact': 'Contact',
  'legal.terms_of_service': 'Terms of Service', 'legal.privacy_policy': 'Privacy Policy',
  'legal.cookie_policy': 'Cookie Policy', 'legal.accessibility': 'Accessibility',
  'legal.legal_hub': 'Legal Hub',
  'cookie_consent.manage': 'Manage Cookies',
  'stats.credits': 'Credits', 'stats.messages': 'Messages', 'stats.alerts': 'Alerts',
  'search.placeholder': 'Search...',
};
vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key: string) => i18nMap[key] ?? key, i18n: { language: 'en', changeLanguage: vi.fn() } }),
  initReactI18next: { type: '3rdParty', init: () => {} },
}));

vi.mock('@/components/LanguageSwitcher', () => ({
  LanguageSwitcher: () => null,
}));

vi.mock('@/components/navigation', () => ({
  DesktopMenuItems: () => null,
  MobileMenuItems: () => null,
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
  auth?: Record<string, unknown>;
  tenant?: Record<string, unknown>;
  notifications?: Record<string, unknown>;
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
      // Listings is in the 'Timebanking' accordion section (collapsed by default).
      // Open the section by clicking the accordion trigger, then verify the item.
      const timebankingTrigger = screen.getByRole('button', { name: /timebanking/i });
      fireEvent.click(timebankingTrigger);
      expect(screen.getByText('Listings')).toBeInTheDocument();
    });

    it('renders About section with universal items', () => {
      render(<MobileDrawer {...defaultProps} />);
      // The 'About' accordion section is collapsed by default — expand the trigger first.
      // The trigger button is accessible by name "About" (from the AccordionItem title).
      const aboutButtons = screen.getAllByRole('button', { name: /^about$/i });
      // Pick the trigger button (data-slot="trigger") to expand the section
      const aboutTrigger = aboutButtons.find(
        (btn) => btn.getAttribute('data-slot') === 'trigger'
      );
      expect(aboutTrigger).toBeDefined();
      fireEvent.click(aboutTrigger!);
      expect(screen.getByText('FAQ')).toBeInTheDocument();
      expect(screen.getByText('Timebanking Guide')).toBeInTheDocument();
    });

    it('renders Contact in utility row for unauthenticated users', () => {
      // For unauthenticated users the utility row shows a Contact button (not Help Center)
      // Help Center is shown only for authenticated users
      render(<MobileDrawer {...defaultProps} />);
      expect(screen.getByText('Contact')).toBeInTheDocument();
    });

    it('renders Legal section', () => {
      render(<MobileDrawer {...defaultProps} />);
      // Legal section is collapsed by default — expand the trigger first.
      const legalButtons = screen.getAllByRole('button', { name: /^legal$/i });
      const legalTrigger = legalButtons.find(
        (btn) => btn.getAttribute('data-slot') === 'trigger'
      );
      expect(legalTrigger).toBeDefined();
      fireEvent.click(legalTrigger!);
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
    it('shows Admin Panel button for admin users', () => {
      setupDefaultMocks({
        auth: {
          user: {
            id: 1,
            first_name: 'Admin',
            last_name: 'User',
            // Legacy Admin button only renders for jasper.ford.esq@gmail.com;
            // use that email so both buttons appear.
            email: 'jasper.ford.esq@gmail.com',
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
      // hOUR Timebank items are in the 'About' accordion section (collapsed by default).
      const aboutButtons = screen.getAllByRole('button', { name: /^about$/i });
      const aboutTrigger = aboutButtons.find(
        (btn) => btn.getAttribute('data-slot') === 'trigger'
      );
      expect(aboutTrigger).toBeDefined();
      fireEvent.click(aboutTrigger!);
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
      // Open the About accordion to confirm the items are truly absent
      const aboutButtons = screen.getAllByRole('button', { name: /^about$/i });
      const aboutTrigger = aboutButtons.find(
        (btn) => btn.getAttribute('data-slot') === 'trigger'
      );
      expect(aboutTrigger).toBeDefined();
      fireEvent.click(aboutTrigger!);
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
      // Events is in the 'Community' accordion section (collapsed by default) — open it first.
      const communityButtons = screen.getAllByRole('button', { name: /^community$/i });
      const communityTrigger = communityButtons.find(
        (btn) => btn.getAttribute('data-slot') === 'trigger'
      );
      expect(communityTrigger).toBeDefined();
      fireEvent.click(communityTrigger!);
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
      // Community section is hidden when no community features are enabled,
      // so Events will not appear regardless.
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
      // Achievements/Leaderboard are in the 'Explore' accordion section — open it first.
      const exploreButtons = screen.getAllByRole('button', { name: /^explore$/i });
      const exploreTrigger = exploreButtons.find(
        (btn) => btn.getAttribute('data-slot') === 'trigger'
      );
      expect(exploreTrigger).toBeDefined();
      fireEvent.click(exploreTrigger!);
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
