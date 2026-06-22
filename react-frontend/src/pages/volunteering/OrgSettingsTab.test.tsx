// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@/test/test-utils';
import { api } from '@/lib/api';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

const mockToast = {
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
};

vi.mock('@/contexts', () => ({
  useAuth: () => ({ user: null, isAuthenticated: false, login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle', error: null }),
  useTenant: () => ({ tenant: { id: 2, name: 'Test Tenant', slug: 'test' }, tenantPath: (p: string) => `/test${p}`, hasFeature: vi.fn(() => true), hasModule: vi.fn(() => true) }),
  useToast: () => mockToast,
  useTheme: () => ({ resolvedTheme: 'light', theme: 'system', toggleTheme: vi.fn(), setTheme: vi.fn() }),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
  usePresence: () => ({ status: 'offline', setStatus: vi.fn(), getPresence: vi.fn(), isOnline: vi.fn(() => false) }),
  usePresenceOptional: () => null,
}));

import OrgSettingsTab from './OrgSettingsTab';

const defaultOrgData = {
  name: 'Community Helpers',
  description: 'We help the community.',
  contact_email: 'info@helpers.ie',
  website: 'https://helpers.ie',
};

const onOrgUpdate = vi.fn();

describe('OrgSettingsTab — form fields render', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the heading', () => {
    render(<OrgSettingsTab orgId={7} orgData={defaultOrgData} onOrgUpdate={onOrgUpdate} />);
    // i18n key org_settings.heading
    expect(screen.getByRole('heading', { level: 2 })).toBeInTheDocument();
  });

  it('renders input fields pre-filled from orgData', () => {
    render(<OrgSettingsTab orgId={7} orgData={defaultOrgData} onOrgUpdate={onOrgUpdate} />);
    // All four controlled inputs should exist and display their initial values
    expect(screen.getByDisplayValue('Community Helpers')).toBeInTheDocument();
    expect(screen.getByDisplayValue('We help the community.')).toBeInTheDocument();
    expect(screen.getByDisplayValue('info@helpers.ie')).toBeInTheDocument();
    expect(screen.getByDisplayValue('https://helpers.ie')).toBeInTheDocument();
  });

  it('renders with empty optional fields when orgData has nulls', () => {
    render(
      <OrgSettingsTab
        orgId={7}
        orgData={{ name: 'Solo Org', description: null, contact_email: null, website: null }}
        onOrgUpdate={onOrgUpdate}
      />
    );
    expect(screen.getByDisplayValue('Solo Org')).toBeInTheDocument();
  });

  it('renders a save button', () => {
    render(<OrgSettingsTab orgId={7} orgData={defaultOrgData} onOrgUpdate={onOrgUpdate} />);
    expect(screen.getByRole('button')).toBeInTheDocument();
  });
});

describe('OrgSettingsTab — save action', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('calls api.put with the correct payload on successful save', async () => {
    vi.mocked(api.put).mockResolvedValueOnce({ success: true });

    render(<OrgSettingsTab orgId={7} orgData={defaultOrgData} onOrgUpdate={onOrgUpdate} />);

    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => {
      expect(api.put).toHaveBeenCalledWith('/v2/volunteering/organisations/7', {
        name: 'Community Helpers',
        description: 'We help the community.',
        contact_email: 'info@helpers.ie',
        website: 'https://helpers.ie',
      });
    });
  });

  it('calls onOrgUpdate and shows success toast on success', async () => {
    vi.mocked(api.put).mockResolvedValueOnce({ success: true });

    render(<OrgSettingsTab orgId={7} orgData={defaultOrgData} onOrgUpdate={onOrgUpdate} />);
    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => {
      expect(onOrgUpdate).toHaveBeenCalled();
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('shows error toast and does not call onOrgUpdate when api returns success:false', async () => {
    vi.mocked(api.put).mockResolvedValueOnce({ success: false, error: 'Save failed' });

    render(<OrgSettingsTab orgId={7} orgData={defaultOrgData} onOrgUpdate={onOrgUpdate} />);
    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
    expect(onOrgUpdate).not.toHaveBeenCalled();
  });

  it('shows error toast and does not call onOrgUpdate when api.put throws', async () => {
    vi.mocked(api.put).mockRejectedValueOnce(new Error('Network error'));

    render(<OrgSettingsTab orgId={7} orgData={defaultOrgData} onOrgUpdate={onOrgUpdate} />);
    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
    expect(onOrgUpdate).not.toHaveBeenCalled();
  });

  it('shows an error toast and does not call api.put when name is blank', async () => {
    render(
      <OrgSettingsTab
        orgId={7}
        orgData={{ name: '   ', description: null, contact_email: null, website: null }}
        onOrgUpdate={onOrgUpdate}
      />
    );

    fireEvent.click(screen.getByRole('button'));

    // toast.error called synchronously (no await needed but give it a tick)
    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
    expect(api.put).not.toHaveBeenCalled();
  });
});
