// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

jest.mock('@/lib/api/client', () => ({
  api: { get: jest.fn(), post: jest.fn(), put: jest.fn(), delete: jest.fn() },
}));
jest.mock('@/lib/constants', () => ({
  API_V2: '/api/v2',
}));

import { api } from '@/lib/api/client';
import {
  approveSubAccount,
  getManagedSubAccounts,
  getManagerSubAccounts,
  requestSubAccount,
  revokeSubAccount,
  updateSubAccountPermissions,
} from './settings';

describe('settings sub-account API', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('loads managed and manager account relationships', async () => {
    (api.get as jest.Mock).mockResolvedValue({ data: [] });

    await getManagedSubAccounts();
    await getManagerSubAccounts();

    expect(api.get).toHaveBeenCalledWith('/api/v2/users/me/sub-accounts');
    expect(api.get).toHaveBeenCalledWith('/api/v2/users/me/parent-accounts');
  });

  it('requests a linked account by email', async () => {
    (api.post as jest.Mock).mockResolvedValue({ data: [] });

    await requestSubAccount('member@example.com');

    expect(api.post).toHaveBeenCalledWith('/api/v2/users/me/sub-accounts', { email: 'member@example.com' });
  });

  it('approves, updates permissions, and revokes relationships', async () => {
    (api.put as jest.Mock).mockResolvedValue({ data: [] });
    (api.delete as jest.Mock).mockResolvedValue({ data: { deleted: true } });

    await approveSubAccount(12);
    await updateSubAccountPermissions(12, { can_transact: true });
    await revokeSubAccount(12);

    expect(api.put).toHaveBeenCalledWith('/api/v2/users/me/sub-accounts/12/approve');
    expect(api.put).toHaveBeenCalledWith('/api/v2/users/me/sub-accounts/12/permissions', {
      permissions: { can_transact: true },
    });
    expect(api.delete).toHaveBeenCalledWith('/api/v2/users/me/sub-accounts/12');
  });
});
