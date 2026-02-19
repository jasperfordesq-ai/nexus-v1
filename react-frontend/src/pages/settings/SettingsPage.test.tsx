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
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { HeroUIProvider } from '@heroui/react';

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

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    user: {
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
    },
    isAuthenticated: true,
    logout: vi.fn(),
    refreshUser: vi.fn(),
  })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    tenantPath: (p: string) => `/test${p}`,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
  })),
  useToast: vi.fn(() => ({
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
    warning: vi.fn(),
  })),
}));

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
  GlassCard: ({ children, className }: { children: React.ReactNode; className?: string }) => (
    <div data-testid="glass-card" className={className}>{children}</div>
  ),
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

vi.mock('dompurify', () => ({
  default: {
    sanitize: vi.fn((html: string) => html),
  },
}));

// Mock framer-motion to avoid heavy animation bundle
vi.mock('framer-motion', () => ({
  motion: new Proxy({}, {
    get: () => React.forwardRef(({ children, ...props }: any, ref: any) => {
      const safe = Object.fromEntries(
        Object.entries(props).filter(([k]) => !['variants', 'initial', 'animate', 'exit', 'layout', 'whileHover', 'whileTap', 'transition', 'whileInView', 'viewport'].includes(k))
      );
      return <div ref={ref} {...safe}>{children}</div>;
    }),
  }),
  AnimatePresence: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

import { SettingsPage } from './SettingsPage';
import { api } from '@/lib/api';

function Wrapper({ children }: { children: React.ReactNode }) {
  return (
    <HeroUIProvider>
      <MemoryRouter>
        {children}
      </MemoryRouter>
    </HeroUIProvider>
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
