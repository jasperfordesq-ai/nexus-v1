// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api/client';
import { API_V2 } from '@/lib/constants';

export interface CreatePollPayload {
  question: string;
  description?: string;
  options: string[];
  poll_type?: 'standard' | 'ranked';
  is_anonymous?: boolean;
  category?: string;
  expires_at?: string;
}

export function createPoll(payload: CreatePollPayload): Promise<{ data?: unknown }> {
  return api.post<{ data?: unknown }>(`${API_V2}/polls`, {
    poll_type: 'standard',
    is_anonymous: false,
    ...payload,
  });
}
