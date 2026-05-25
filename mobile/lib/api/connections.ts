// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api/client';
import { API_V2 } from '@/lib/constants';

export type ConnectionStatusType = 'none' | 'connected' | 'pending_sent' | 'pending_received';

export interface ConnectionStatus {
  status: ConnectionStatusType;
  connection_id: number | null;
  direction: 'sent' | 'received' | null;
}

/** GET /api/v2/connections/status/{userId} */
export function getConnectionStatus(userId: number): Promise<{ data: ConnectionStatus }> {
  return api.get<{ data: ConnectionStatus }>(`${API_V2}/connections/status/${userId}`);
}

/** POST /api/v2/connections/request — send connection request */
export function sendConnectionRequest(userId: number): Promise<{ data: { status: string; connection_id: number; message: string } }> {
  return api.post<{ data: { status: string; connection_id: number; message: string } }>(`${API_V2}/connections/request`, { user_id: userId });
}

/** POST /api/v2/connections/{id}/accept */
export function acceptConnection(connectionId: number): Promise<{ data: { connection_id: number; status: string } }> {
  return api.post<{ data: { connection_id: number; status: string } }>(`${API_V2}/connections/${connectionId}/accept`, {});
}

/** DELETE /api/v2/connections/{id} */
export function removeConnection(connectionId: number): Promise<void> {
  return api.delete<void>(`${API_V2}/connections/${connectionId}`);
}
