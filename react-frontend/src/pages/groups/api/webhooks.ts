// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api';
import { normalizeGroupApiError, unwrapGroupResponse } from './core';

export interface GroupWebhook {
  id: number;
  url: string;
  events: string[];
  is_active: boolean;
  last_fired_at: string | null;
  failure_count: number;
}

export interface CreateGroupWebhookInput {
  url: string;
  events: string[];
  secret?: string;
}

export interface ListGroupWebhooksOptions {
  signal?: AbortSignal;
}

export async function listGroupWebhooks(
  groupId: number,
  options: ListGroupWebhooksOptions = {},
): Promise<GroupWebhook[]> {
  try {
    const response = await api.get<GroupWebhook[]>(`/v2/groups/${groupId}/webhooks`, {
      signal: options.signal,
    });
    const webhooks = unwrapGroupResponse(response);
    if (!Array.isArray(webhooks)) {
      throw normalizeGroupApiError({ code: 'INVALID_RESPONSE' });
    }
    return webhooks;
  } catch (error) {
    throw normalizeGroupApiError(error);
  }
}

export async function createGroupWebhook(
  groupId: number,
  input: CreateGroupWebhookInput,
): Promise<GroupWebhook | undefined> {
  try {
    return unwrapGroupResponse(await api.post<GroupWebhook>(`/v2/groups/${groupId}/webhooks`, input));
  } catch (error) {
    throw normalizeGroupApiError(error);
  }
}

export async function setGroupWebhookActive(
  groupId: number,
  webhookId: number,
  isActive: boolean,
): Promise<void> {
  try {
    unwrapGroupResponse<void>(await api.put<void>(
      `/v2/groups/${groupId}/webhooks/${webhookId}/toggle`,
      { is_active: isActive },
    ));
  } catch (error) {
    throw normalizeGroupApiError(error);
  }
}

export async function deleteGroupWebhook(groupId: number, webhookId: number): Promise<void> {
  try {
    unwrapGroupResponse<void>(await api.delete<void>(`/v2/groups/${groupId}/webhooks/${webhookId}`));
  } catch (error) {
    throw normalizeGroupApiError(error);
  }
}
