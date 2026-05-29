// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api/client';
import { getConnections } from './connections';

jest.mock('@/lib/api/client', () => ({
  api: {
    get: jest.fn(),
  },
}));

describe('connections api', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('requests accepted connections with pagination params', async () => {
    (api.get as jest.Mock).mockResolvedValueOnce({ data: [] });

    await getConnections('accepted', 'cursor-1');

    expect(api.get).toHaveBeenCalledWith('/api/v2/connections', {
      status: 'accepted',
      per_page: '20',
      cursor: 'cursor-1',
    });
  });

  it('omits cursor when loading the first page', async () => {
    (api.get as jest.Mock).mockResolvedValueOnce({ data: [] });

    await getConnections('pending_received');

    expect(api.get).toHaveBeenCalledWith('/api/v2/connections', {
      status: 'pending_received',
      per_page: '20',
    });
  });
});
