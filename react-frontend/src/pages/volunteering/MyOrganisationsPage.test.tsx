// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import type { ReactNode } from 'react';

vi.mock('@/lib/motion', () => ({
  motion: {
    div: ({ children, ...props }: { children?: ReactNode; [key: string]: unknown }) => {
      const { initial: _initial, animate: _animate, transition: _transition, ...rest } = props;
      return <div {...rest}>{children}</div>;
    },
  },
  AnimatePresence: ({ children }: { children?: ReactNode }) => <>{children}</>,
}));

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
  },
}));

vi.mock('@/contexts', () => ({
  useTenant: () => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    tenantPath: (path: string) => `/test${path}`,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
  }),
  useTheme: () => ({ resolvedTheme: 'light', toggleTheme: vi.fn(), theme: 'system', setTheme: vi.fn() }),
  useAuth: () => ({ user: { id: 1 }, isAuthenticated: true }),
  useNotifications: () => ({ unreadCount: 0, counts: {} }),
  useToast: () => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }),
}));

vi.mock('@/components/navigation', () => ({
  Breadcrumbs: ({ items }: { items: { label: string }[] }) => (
    <nav data-testid="breadcrumbs">
      {items.map((item) => <span key={item.label}>{item.label}</span>)}
    </nav>
  ),
}));

vi.mock('@/components/ui', async () => (await import('@/test/uiMock')).uiMock);
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/hooks/usePageTitle', () => ({ usePageTitle: vi.fn() }));

const translations: Record<string, string> = {
  breadcrumb_volunteering: 'Volunteering',
  my_organisations: 'My Organisations',
  my_organisations_title: 'My Organisations',
  my_organisations_subtitle: 'Manage your volunteer organisations.',
  register_organisation: 'Register Organisation',
  loading: 'Loading',
  status_active: 'Active',
  member_roles: 'Member Roles',
  'member_roles.owner': 'Owner',
  'member_roles.admin': 'Admin',
  'member_roles.member': 'Member',
  hours_abbrev: '{{hours}}h',
  wallet: 'Wallet',
  manage: 'Manage',
};

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, options?: Record<string, unknown>) => {
      let value = translations[key] ?? key;
      Object.entries(options ?? {}).forEach(([optionKey, optionValue]) => {
        value = value.replace(`{{${optionKey}}}`, String(optionValue));
      });
      return value;
    },
  }),
  initReactI18next: { type: '3rdParty', init: () => {} },
}));

import MyOrganisationsPage from './MyOrganisationsPage';
import { api } from '@/lib/api';

describe('MyOrganisationsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: [
        {
          id: 7,
          name: 'Community Helpers',
          description: 'Local volunteering team',
          status: 'active',
          member_role: 'owner',
          contact_email: 'helpers@example.com',
          website: null,
          balance: 12,
        },
      ],
      meta: { cursor: null, has_more: false },
    });
  });

  it('renders translated manager roles instead of raw backend role values', async () => {
    render(<MyOrganisationsPage />);

    await waitFor(() => expect(screen.getByText('Community Helpers')).toBeInTheDocument());

    expect(screen.getByText('Owner')).toBeInTheDocument();
    expect(screen.queryByText('owner')).not.toBeInTheDocument();
  });
});
