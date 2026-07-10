// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api';

/**
 * Shape of GET /v2/federation/status (FederationV2Controller::status).
 * These are the ONLY fields the endpoint returns — earlier callers also read
 * `user_opted_in` / `status.user_optin`, which never existed and never matched.
 */
export interface FederationStatus {
  enabled?: boolean;
  tenant_federation_enabled?: boolean;
  federation_optin?: boolean;
  partnerships_count?: number;
  messages_count?: number;
  transactions_count?: number;
}

/**
 * True when the CURRENT USER has opted into federation.
 *
 * `federation_optin` is the user's own opt-in flag. `enabled` is the combined
 * "tenant federation on AND user opted in" flag, so it implies opt-in — but a
 * user can be opted in while their tenant has federation switched off, which
 * is why `federation_optin` must be consulted first.
 */
export function isUserOptedIntoFederation(status: FederationStatus | null | undefined): boolean {
  return status?.federation_optin === true || status?.enabled === true;
}

/**
 * Fetch /v2/federation/status and report the user's opt-in state.
 * Resolves false on any failure (api.get resolves with a failure envelope).
 */
export async function fetchUserFederationOptIn(): Promise<boolean> {
  const res = await api.get<FederationStatus>('/v2/federation/status');
  return res.success && res.data ? isUserOptedIntoFederation(res.data) : false;
}
