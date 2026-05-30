// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { fireEvent, render, waitFor } from '@testing-library/react-native';

const mockUseApi = jest.fn();
const mockRefresh = jest.fn();
const mockRequestSubAccount = jest.fn();
const mockApproveSubAccount = jest.fn();
const mockRevokeSubAccount = jest.fn();
const mockUpdateSubAccountPermissions = jest.fn();

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, unknown>) => {
      const map: Record<string, string> = {
        'common:buttons.back': 'Back',
        'common:errors.alertTitle': 'Error',
        'linkedAccounts.title': 'Linked accounts',
        'linkedAccounts.eyebrow': 'Delegated access',
        'linkedAccounts.subtitle': 'Request and manage account relationships.',
        'linkedAccounts.addTitle': 'Request linked account access',
        'linkedAccounts.addDescription': 'Send a request by email.',
        'linkedAccounts.emailLabel': 'Member email',
        'linkedAccounts.emailPlaceholder': 'member@example.com',
        'linkedAccounts.sendRequest': 'Send request',
        'linkedAccounts.sending': 'Sending...',
        'linkedAccounts.emailRequired': 'Enter the member email address.',
        'linkedAccounts.requestFailed': 'Could not send request.',
        'linkedAccounts.loadFailed': 'Could not load linked accounts.',
        'linkedAccounts.managedTitle': 'Accounts you manage',
        'linkedAccounts.managedDescription': 'People who granted access.',
        'linkedAccounts.managedEmpty': 'You are not managing any accounts yet.',
        'linkedAccounts.managersTitle': 'People who manage you',
        'linkedAccounts.managersDescription': 'Members who can help manage your account.',
        'linkedAccounts.managersEmpty': 'No one is managing your account.',
        'linkedAccounts.unknownMember': 'Community member',
        'linkedAccounts.permissionsTitle': 'Permissions',
        'linkedAccounts.approve': 'Approve',
        'linkedAccounts.decline': 'Decline',
        'linkedAccounts.remove': 'Remove',
        'linkedAccounts.approveFailed': 'Could not approve this request.',
        'linkedAccounts.revokeFailed': 'Could not remove this linked account.',
        'linkedAccounts.permissionFailed': 'Could not update permission.',
        'linkedAccounts.permissionToggle': `Toggle ${String(opts?.permission ?? '')} for ${String(opts?.name ?? '')}`,
        'linkedAccounts.status.active': 'Active',
        'linkedAccounts.status.pending': 'Pending',
        'linkedAccounts.permissions.can_view_activity': 'View activity',
        'linkedAccounts.permissions.can_manage_listings': 'Manage listings',
        'linkedAccounts.permissions.can_transact': 'Transfer credits',
        'linkedAccounts.permissions.can_view_messages': 'View messages',
      };
      return map[key] ?? String(opts?.defaultValue ?? key);
    },
  }),
}));

jest.mock('@/lib/hooks/useApi', () => ({
  useApi: (...args: unknown[]) => mockUseApi(...args),
}));
jest.mock('@/lib/hooks/useTenant', () => ({ usePrimaryColor: () => '#6366f1' }));
jest.mock('@/lib/hooks/useTheme', () => ({
  useTheme: () => ({
    text: '#000',
    textSecondary: '#666',
    textMuted: '#999',
  }),
}));
jest.mock('@expo/vector-icons', () => ({ Ionicons: 'View' }));
jest.mock('@/components/ui/AppTopBar', () => 'View');
jest.mock('@/components/ModalErrorBoundary', () => ({ children }: { children: React.ReactNode }) => children);
jest.mock('@/lib/api/settings', () => ({
  approveSubAccount: (...args: unknown[]) => mockApproveSubAccount(...args),
  getManagedSubAccounts: jest.fn(),
  getManagerSubAccounts: jest.fn(),
  requestSubAccount: (...args: unknown[]) => mockRequestSubAccount(...args),
  revokeSubAccount: (...args: unknown[]) => mockRevokeSubAccount(...args),
  updateSubAccountPermissions: (...args: unknown[]) => mockUpdateSubAccountPermissions(...args),
}));

import SettingsLinkedAccountsRoute from './settings-linked-accounts';

beforeEach(() => {
  mockRefresh.mockReset();
  mockRequestSubAccount.mockReset().mockResolvedValue({});
  mockApproveSubAccount.mockReset().mockResolvedValue({});
  mockRevokeSubAccount.mockReset().mockResolvedValue({});
  mockUpdateSubAccountPermissions.mockReset().mockResolvedValue({});
  mockUseApi.mockReset().mockReturnValue({
    data: {
      managed: [{
        relationship_id: 11,
        relationship_type: 'family',
        permissions: { can_view_activity: true, can_transact: false },
        status: 'active',
        created_at: '2026-01-01T00:00:00Z',
        user_id: 5,
        first_name: 'Alex',
        last_name: 'Managed',
        avatar_url: null,
        email: 'alex@example.com',
      }],
      managers: [{
        relationship_id: 12,
        relationship_type: 'family',
        permissions: {},
        status: 'pending',
        created_at: '2026-01-01T00:00:00Z',
        user_id: 6,
        first_name: 'Morgan',
        last_name: 'Manager',
        avatar_url: null,
        email: 'morgan@example.com',
      }],
    },
    isLoading: false,
    error: null,
    refresh: mockRefresh,
  });
});

describe('SettingsLinkedAccountsRoute', () => {
  it('renders managed and manager linked account relationships', () => {
    const { getByText } = render(<SettingsLinkedAccountsRoute />);

    expect(getByText('Linked accounts')).toBeTruthy();
    expect(getByText('Alex Managed')).toBeTruthy();
    expect(getByText('Morgan Manager')).toBeTruthy();
    expect(getByText('View activity')).toBeTruthy();
  });

  it('requests linked account access by email', async () => {
    const { getByPlaceholderText, getByText } = render(<SettingsLinkedAccountsRoute />);

    fireEvent.changeText(getByPlaceholderText('member@example.com'), 'child@example.com');
    fireEvent.press(getByText('Send request'));

    await waitFor(() => expect(mockRequestSubAccount).toHaveBeenCalledWith('child@example.com'));
    expect(mockRefresh).toHaveBeenCalled();
  });

  it('approves pending requests, revokes relationships, and updates permissions', async () => {
    const { getAllByText, getByLabelText, getByText } = render(<SettingsLinkedAccountsRoute />);

    fireEvent(getByLabelText('Toggle Transfer credits for Alex Managed'), 'selectedChange', true);
    await waitFor(() => expect(mockUpdateSubAccountPermissions).toHaveBeenCalledWith(11, { can_transact: true }));

    fireEvent.press(getByText('Approve'));
    await waitFor(() => expect(mockApproveSubAccount).toHaveBeenCalledWith(12));

    fireEvent.press(getAllByText('Remove')[0]);
    await waitFor(() => expect(mockRevokeSubAccount).toHaveBeenCalledWith(11));
  });
});
