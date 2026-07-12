// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api';
import { normalizeGroupApiError, unwrapGroupResponse } from './core';

export type GroupNotificationFrequency = 'instant' | 'digest' | 'muted';

export interface GroupNotificationPreferences {
  frequency: GroupNotificationFrequency;
  email_enabled: boolean;
  push_enabled: boolean;
  updated_at: string | null;
}

export interface GroupNotificationPreferenceUpdateResult {
  message: string;
  preferences: GroupNotificationPreferences;
}

export interface GroupWelcomeConfig {
  enabled: boolean;
  message: string;
}

export interface GroupSettingsReadOptions {
  signal?: AbortSignal;
}

type UnknownRecord = Record<string, unknown>;

function isRecord(value: unknown): value is UnknownRecord {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function invalidSettingsResponse(): never {
  throw normalizeGroupApiError({ code: 'INVALID_RESPONSE' });
}

function normalizeBoolean(value: unknown, fallback: boolean): boolean {
  if (typeof value === 'boolean') return value;
  if (value === 1 || value === '1') return true;
  if (value === 0 || value === '0') return false;
  return fallback;
}

function normalizeRequiredBoolean(value: unknown): boolean {
  if (typeof value === 'boolean') return value;
  if (value === 1 || value === '1') return true;
  if (value === 0 || value === '0') return false;
  return invalidSettingsResponse();
}

function normalizeIsoTimestamp(value: unknown): string | null {
  if (value === null) return null;
  if (typeof value !== 'string'
    || !/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?Z$/.test(value)
    || !Number.isFinite(Date.parse(value))) {
    return invalidSettingsResponse();
  }
  return value;
}

function normalizeNotificationPreferences(value: unknown): GroupNotificationPreferences {
  if (!isRecord(value)) return invalidSettingsResponse();

  const frequency = value.frequency ?? 'instant';
  if (frequency !== 'instant' && frequency !== 'digest' && frequency !== 'muted') {
    return invalidSettingsResponse();
  }

  return {
    frequency,
    email_enabled: normalizeRequiredBoolean(value.email_enabled),
    push_enabled: normalizeRequiredBoolean(value.push_enabled),
    updated_at: normalizeIsoTimestamp(value.updated_at),
  };
}

function normalizeNotificationUpdateResult(value: unknown): GroupNotificationPreferenceUpdateResult {
  if (!isRecord(value) || typeof value.message !== 'string' || value.message.trim() === '') {
    return invalidSettingsResponse();
  }
  const preferences = normalizeNotificationPreferences(value.preferences);
  if (preferences.updated_at === null) return invalidSettingsResponse();
  return {
    message: value.message,
    preferences,
  };
}

function normalizeWelcomeConfig(value: unknown): GroupWelcomeConfig {
  if (!isRecord(value)) return invalidSettingsResponse();

  return {
    enabled: normalizeBoolean(value.enabled, false),
    message: typeof value.message === 'string' ? value.message : '',
  };
}

export async function getGroupNotificationPreferences(
  groupId: number,
  options: GroupSettingsReadOptions = {},
): Promise<GroupNotificationPreferences> {
  try {
    const response = await api.get<unknown>(
      `/v2/groups/${groupId}/notification-prefs`,
      { signal: options.signal },
    );
    return normalizeNotificationPreferences(unwrapGroupResponse(response));
  } catch (error) {
    throw normalizeGroupApiError(error);
  }
}

export async function updateGroupNotificationPreferences(
  groupId: number,
  preferences: GroupNotificationPreferences,
): Promise<GroupNotificationPreferenceUpdateResult> {
  try {
    const payload = {
      frequency: preferences.frequency,
      email_enabled: preferences.email_enabled,
      push_enabled: preferences.push_enabled,
    };
    const response = await api.put<unknown>(
      `/v2/groups/${groupId}/notification-prefs`,
      payload,
    );
    return normalizeNotificationUpdateResult(unwrapGroupResponse(response));
  } catch (error) {
    throw normalizeGroupApiError(error);
  }
}

export async function getGroupWelcomeConfig(
  groupId: number,
  options: GroupSettingsReadOptions = {},
): Promise<GroupWelcomeConfig> {
  try {
    const response = await api.get<unknown>(
      `/v2/groups/${groupId}/welcome`,
      { signal: options.signal },
    );
    return normalizeWelcomeConfig(unwrapGroupResponse(response));
  } catch (error) {
    throw normalizeGroupApiError(error);
  }
}

export async function updateGroupWelcomeConfig(
  groupId: number,
  config: GroupWelcomeConfig,
): Promise<GroupWelcomeConfig> {
  try {
    const response = await api.put<unknown>(`/v2/groups/${groupId}/welcome`, config);
    return normalizeWelcomeConfig(unwrapGroupResponse(response));
  } catch (error) {
    throw normalizeGroupApiError(error);
  }
}
