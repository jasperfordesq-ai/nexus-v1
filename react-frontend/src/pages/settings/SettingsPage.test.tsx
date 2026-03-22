// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for SettingsPage
 *
 * Note: SettingsPage imports 15+ HeroUI components and 15+ Lucide icons.
 * We mock @heroui/react and lucide-react to keep compilation fast.
 */

import React from 'react';
import type { ReactNode } from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';

// ── Mock @heroui/react before any imports ────────────────────────────────────
vi.mock('@heroui/react', async () => {
  const R = await import('react');
  const noop = () => R.createElement(R.Fragment, null);
  const useDisclosureMock = () => ({ isOpen: false, onOpen: vi.fn(), onClose: vi.fn(), onOpenChange: vi.fn() });
  return {
    HeroUIProvider: ({ children }: { children: ReactNode }) => R.createElement(R.Fragment, null, children),
    Button: ({ children, onPress, isDisabled, isLoading, type, ...rest }: Record<string, unknown>) =>
      R.createElement('button', {
        onClick: onPress as () => void,
        disabled: Boolean(isDisabled || isLoading),
        type: (type as string) || 'button',
        'aria-label': rest['aria-label'] as string | undefined,
        'data-testid': rest['data-testid'] as string | undefined,
      }, (isLoading ? 'Loading...' : children) as ReactNode),
    Input: ({ label, value, onChange, placeholder, type, name }: Record<string, unknown>) =>
      R.createElement('input', {
        'aria-label': label as string,
        value: value as string ?? '',
        onChange: onChange as React.ChangeEventHandler<HTMLInputElement>,
        placeholder: placeholder as string,
        type: (type as string) || 'text',
        name: name as string,
      }),
    Textarea: ({ label, value, onChange, placeholder }: Record<string, unknown>) =>
      R.createElement('textarea', {
        'aria-label': label as string,
        value: value as string ?? '',
        onChange: onChange as React.ChangeEventHandler<HTMLTextAreaElement>,
        placeholder: placeholder as string,
      }),
    Switch: ({ children, isSelected, onValueChange }: Record<string, unknown>) =>
      R.createElement('label', null,
        R.createElement('input', {
          type: 'checkbox',
          checked: Boolean(isSelected),
          onChange: (e: React.ChangeEvent<HTMLInputElement>) =>
            (onValueChange as ((v: boolean) => void) | undefined)?.(e.target.checked),
        }),
        children as ReactNode,
      ),
    Avatar: noop,
    Tabs: ({ children }: { children: ReactNode }) => R.createElement('div', { role: 'tablist' }, children),
    Tab: ({ children, title }: Record<string, unknown>) =>
      R.createElement('div', { role: 'tab' },
        R.createElement('span', null, title as ReactNode),
        children as ReactNode,
      ),
    Select: ({ children, label }: Record<string, unknown>) =>
      R.createElement('div', { 'aria-label': label as string }, children as ReactNode),
    SelectItem: ({ children }: { children: ReactNode }) => R.createElement('div', null, children),
    Modal: ({ children, isOpen }: Record<string, unknown>) =>
      isOpen ? R.createElement('div', { role: 'dialog' }, children as ReactNode) : null,
    ModalContent: ({ children }: { children: ((onClose: () => void) => ReactNode) | ReactNode }) =>
      R.createElement('div', null, typeof children === 'function' ? (children as (c: () => void) => ReactNode)(vi.fn()) : children as ReactNode),
    ModalHeader: ({ children }: { children: ReactNode }) => R.createElement('div', null, children),
    ModalBody: ({ children }: { children: ReactNode }) => R.createElement('div', null, children),
    ModalFooter: ({ children }: { children: ReactNode }) => R.createElement('div', null, children),
    Chip: ({ children }: { children: ReactNode }) => R.createElement('span', null, children),
    Spinner: noop,
    useDisclosure: useDisclosureMock,
  };
});

// ── Mock lucide-react icons ───────────────────────────────────────────────────
vi.mock('lucide-react', () => {
  const Icon = () => null;
  return {
    User: Icon, Bell: Icon, Shield: Icon, Save: Icon, Camera: Icon,
    Mail: Icon, Lock: Icon, Smartphone: Icon, Key: Icon, LogOut: Icon,
    Trash2: Icon, Settings: Icon, AlertTriangle: Icon, Eye: Icon, EyeOff: Icon,
    Phone: Icon, Building2: Icon, Search: Icon, MessageSquare: Icon, Trophy: Icon,
    CreditCard: Icon, Download: Icon, FileText: Icon, RefreshCw: Icon, Monitor: Icon,
    QrCode: Icon, ShieldCheck: Icon, ShieldOff: Icon, Copy: Icon, CheckCircle: Icon,
    Info: Icon, FileCheck: Icon, Upload: Icon, PenLine: Icon, Ban: Icon, Scale: Icon,
    Sparkles: Icon, Calendar: Icon, Users: Icon, Globe: Icon, ChevronRight: Icon,
  };
});

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn().mockResolvedValue({ success: true, data: {} }),
    post: vi.fn().mockResolvedValue({ success: true }),
    put: vi.fn().mockResolvedValue({ success: true }),
    delete: vi.fn().mockResolvedValue({ success: true }),
    upload: vi.fn().mockResolvedValue({ success: true, data: { avatar_url: '/new-avatar.png' } }),
  },
  tokenManager: { getTenantId: vi.fn() },
}));


// Stable references — CRITICAL: useAuth/useTenant/useToast must return the SAME object
// reference on every call to prevent useEffect dependency loops.
// SettingsPage's main useEffect has [user, ...loadFns] deps — if user changes reference
// on each render, the effect fires infinitely.
vi.mock('@/contexts', () => {
  const mockUser = {
    id: 1,
    first_name: 'Test',
    last_name: 'User',
    name: 'Test User',
    phone: '123456789',
    tagline: 'Hello world',
    bio: 'A test bio',
    location: 'Dublin',
    avatar: null,
    profile_type: 'individual',
    organization_name: '',
    has_2fa_enabled: false,
  };
  const mockLogout = vi.fn();
  const mockRefreshUser = vi.fn();
  const mockAuthResult = { user: mockUser, isAuthenticated: true, logout: mockLogout, refreshUser: mockRefreshUser };

  const mockTenantResult = {
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    tenantPath: (p: string) => `/test${p}`,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
  };

  const mockToastSuccess = vi.fn();
  const mockToastError = vi.fn();
  const mockToastInfo = vi.fn();
  const mockToastWarning = vi.fn();
  const mockToastResult = { success: mockToastSuccess, error: mockToastError, info: mockToastInfo, warning: mockToastWarning };

  return {
    useAuth: vi.fn(() => mockAuthResult),
    useTenant: vi.fn(() => mockTenantResult),
    useToast: vi.fn(() => mockToastResult),
    useTheme: () => ({ resolvedTheme: 'light', toggleTheme: vi.fn(), theme: 'system', setTheme: vi.fn() }),
    useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
    usePusher: () => ({ channel: null, isConnected: false }),
    usePusherOptional: () => null,
    useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
    readStoredConsent: () => null,
    useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
    useFeature: vi.fn(() => true),
    useModule: vi.fn(() => true),
  };
});

vi.mock('@/hooks', () => ({
  usePageTitle: vi.fn(),
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: vi.fn((url: string) => url || '/default-avatar.png'),
  formatRelativeTime: vi.fn(() => '2 hours ago'),
}));

vi.mock('@/components/ui', () => ({
  GlassCard: ({ children, className }: { children: ReactNode; className?: string }) => (
    <div data-testid="glass-card" className={className}>{children}</div>
  ),
  GlassButton: ({ children }: Record<string, unknown>) => children as never,
  GlassInput: () => null,
  BackToTop: () => null,
  AlgorithmLabel: () => null,
  ImagePlaceholder: () => null,
  DynamicIcon: () => null,
  ICON_MAP: {},
  ICON_NAMES: [],
  ListingSkeleton: () => null,
  MemberCardSkeleton: () => null,
  StatCardSkeleton: () => null,
  EventCardSkeleton: () => null,
  GroupCardSkeleton: () => null,
  ConversationSkeleton: () => null,
  ExchangeCardSkeleton: () => null,
  NotificationSkeleton: () => null,
  ProfileHeaderSkeleton: () => null,
  SkeletonList: () => null,
}));

vi.mock('@/components/location', () => ({
  PlaceAutocompleteInput: ({ label, placeholder, value, onChange }: {
    label: string;
    placeholder: string;
    value: string;
    onChange: (val: string) => void;
  }) => (
    <input
      data-testid="place-autocomplete"
      aria-label={label}
      placeholder={placeholder}
      value={value}
      onChange={(e) => onChange(e.target.value)}
    />
  ),
}));

// Stable t function — defined once in factory scope to prevent identity change on re-render.
// SettingsPage has loadSessions with [t] as useCallback dep → unstable t causes infinite loop.
vi.mock('react-i18next', () => {
  const settingsTranslations: Record<string, string> = {
    'header.title': 'Settings',
    'header.subtitle': 'Manage your account preferences',
    'tabs.profile': 'Profile',
    'tabs.notifications': 'Notifications',
    'tabs.privacy': 'Privacy',
    'tabs.security': 'Security',
    'profile.section_title': 'Profile Information',
    'profile.first_name': 'First Name',
    'profile.last_name': 'Last Name',
    'save_changes': 'Save Changes',
  };
  const stableTFn = (key: string, opts?: Record<string, unknown> | string): string => {
    if (typeof opts === 'object' && opts !== null && 'defaultValue' in opts) {
      return opts.defaultValue as string;
    }
    return settingsTranslations[key] ?? key;
  };
  const stableI18n = { language: 'en', changeLanguage: () => Promise.resolve() };
  return {
    useTranslation: () => ({ t: stableTFn, i18n: stableI18n }),
  };
});

vi.mock('dompurify', () => ({
  default: {
    sanitize: vi.fn((html: string) => html),
  },
}));

vi.mock('@/components/security/BiometricSettings', () => ({
  BiometricSettings: () => null,
}));

vi.mock('@/components/skills/SkillSelector', () => ({
  SkillSelector: () => null,
}));

vi.mock('@/components/availability/AvailabilityGrid', () => ({
  AvailabilityGrid: () => null,
}));

vi.mock('@/components/subaccounts/SubAccountsManager', () => ({
  SubAccountsManager: () => null,
}));

vi.mock('@/components/LanguageSwitcher', () => ({
  LanguageSwitcher: () => null,
}));

// Mock framer-motion — IMPORTANT: use a stable MotionDiv component, NOT a Proxy that
// creates a new component class on each property access. React tracks component identity;
// a new class per render causes unmount/remount cycles → infinite effect loops.
vi.mock('framer-motion', async () => {
  const R = await import('react');
  const MOTION_PROPS = new Set(['variants', 'initial', 'animate', 'exit', 'layout', 'whileHover', 'whileTap', 'transition', 'whileInView', 'viewport', 'layoutId']);
  const MotionDiv = R.forwardRef(({ children, ...props }: Record<string, unknown>, ref: R.Ref<HTMLDivElement>) => {
    const safe: Record<string, unknown> = {};
    for (const [k, v] of Object.entries(props)) {
      if (!MOTION_PROPS.has(k)) safe[k] = v;
    }
    return R.createElement('div', { ...safe, ref }, children as R.ReactNode);
  });
  MotionDiv.displayName = 'MotionDiv';
  return {
    motion: { div: MotionDiv, section: MotionDiv, span: MotionDiv, p: MotionDiv, ul: MotionDiv, li: MotionDiv },
    AnimatePresence: ({ children }: { children: R.ReactNode }) => R.createElement(R.Fragment, null, children),
  };
});

import { SettingsPage } from './SettingsPage';
import { api } from '@/lib/api';

function Wrapper({ children }: { children: ReactNode }) {
  return (
    <MemoryRouter>
      {children}
    </MemoryRouter>
  );
}

describe('SettingsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url.includes('/v2/users/me/notifications')) {
        return Promise.resolve({
          success: true,
          data: {
            email_messages: true,
            email_listings: true,
            email_digest: false,
            email_connections: true,
            email_transactions: true,
            email_reviews: true,
            email_gamification: false,
            push_enabled: true,
          },
        });
      }
      if (url.includes('/v2/users/me/preferences')) {
        return Promise.resolve({
          success: true,
          data: {
            privacy: {
              profile_visibility: 'members',
              search_indexing: true,
              contact_permission: true,
            },
          },
        });
      }
      if (url.includes('/v2/auth/2fa/status')) {
        return Promise.resolve({
          success: true,
          data: { enabled: false, backup_codes_remaining: 0 },
        });
      }
      if (url.includes('/v2/users/me/sessions')) {
        return Promise.resolve({ success: true, data: [] });
      }
      return Promise.resolve({ success: true, data: {} });
    });
  });

  it('renders the page heading and description', () => {
    render(<SettingsPage />, { wrapper: Wrapper });
    expect(screen.getByText('Settings')).toBeInTheDocument();
    expect(screen.getByText('Manage your account preferences')).toBeInTheDocument();
  });

  it('shows tab navigation with Profile, Notifications, Privacy, Security', () => {
    render(<SettingsPage />, { wrapper: Wrapper });
    expect(screen.getByText('Profile')).toBeInTheDocument();
    expect(screen.getByText('Notifications')).toBeInTheDocument();
    expect(screen.getByText('Privacy')).toBeInTheDocument();
    expect(screen.getByText('Security')).toBeInTheDocument();
  });

  it('shows Profile Information section by default', () => {
    render(<SettingsPage />, { wrapper: Wrapper });
    expect(screen.getByText('Profile Information')).toBeInTheDocument();
  });

  it('shows profile form fields', () => {
    render(<SettingsPage />, { wrapper: Wrapper });
    expect(screen.getByLabelText('First Name')).toBeInTheDocument();
    expect(screen.getByLabelText('Last Name')).toBeInTheDocument();
  });

  it('shows Save Changes button on profile tab', () => {
    render(<SettingsPage />, { wrapper: Wrapper });
    expect(screen.getByText('Save Changes')).toBeInTheDocument();
  });

  it('populates form with user data', () => {
    render(<SettingsPage />, { wrapper: Wrapper });
    const firstNameInput = screen.getByLabelText('First Name') as HTMLInputElement;
    expect(firstNameInput.value).toBe('Test');
    const lastNameInput = screen.getByLabelText('Last Name') as HTMLInputElement;
    expect(lastNameInput.value).toBe('User');
  });
});
