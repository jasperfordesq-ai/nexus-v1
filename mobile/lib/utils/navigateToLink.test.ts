// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

const mockPush = jest.fn();
const mockReplace = jest.fn();
const mockCaptureMessage = jest.fn();

jest.mock('expo-router', () => ({
  router: {
    push: (...args: unknown[]) => mockPush(...args),
    replace: (...args: unknown[]) => mockReplace(...args),
  },
}));

jest.mock('@sentry/react-native', () => ({
  captureMessage: (...args: unknown[]) => mockCaptureMessage(...args),
}));

import { navigateToLink } from './navigateToLink';

describe('navigateToLink', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('maps federation directory deep links to their native screens', () => {
    navigateToLink('/federation/partners');
    navigateToLink('/federation/members?partner_id=2');
    navigateToLink('/federation/messages?compose=true&to_user=272&to_tenant=5&name=Alice');
    navigateToLink('/federation/listings?partner_id=2');
    navigateToLink('/federation/events');
    navigateToLink('/federation/settings');

    expect(mockPush).toHaveBeenNthCalledWith(1, '/(modals)/federation-partners');
    expect(mockPush).toHaveBeenNthCalledWith(2, { pathname: '/(modals)/federation-members', params: { partner_id: '2' } });
    expect(mockPush).toHaveBeenNthCalledWith(3, {
      pathname: '/(modals)/federation-messages',
      params: { compose: 'true', to_user: '272', to_tenant: '5', name: 'Alice' },
    });
    expect(mockPush).toHaveBeenNthCalledWith(4, { pathname: '/(modals)/federation-listings', params: { partner_id: '2' } });
    expect(mockPush).toHaveBeenNthCalledWith(5, '/(modals)/federation-events');
    expect(mockPush).toHaveBeenNthCalledWith(6, '/(modals)/federation-settings');
  });

  it('maps federation detail deep links with tenant context', () => {
    navigateToLink('/federation/partners/7');
    navigateToLink('/federation/members/272?tenant_id=5');

    expect(mockPush).toHaveBeenNthCalledWith(1, { pathname: '/(modals)/federation-partner', params: { id: '7' } });
    expect(mockPush).toHaveBeenNthCalledWith(2, { pathname: '/(modals)/member-profile', params: { id: '272', tenant_id: '5' } });
  });
});
