// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { TFunction } from 'i18next';

const PRESET_KEYS = [
  'national_admin',
  'canton_admin',
  'municipality_admin',
  'cooperative_coordinator',
  'organisation_coordinator',
  'trusted_reviewer',
] as const;

export type CaringRolePresetKey = (typeof PRESET_KEYS)[number];

export function caringRolePresetKey(roleName: string): CaringRolePresetKey | null {
  const match = roleName.match(/^kiss_\d+_(.+)$/);
  const candidate = match?.[1];

  return PRESET_KEYS.includes(candidate as CaringRolePresetKey)
    ? (candidate as CaringRolePresetKey)
    : null;
}

export function caringRolePresetName(t: TFunction, key: CaringRolePresetKey): string {
  return t(`enterprise.caring_role_presets.${key}.name`);
}

export function caringRolePresetDescription(t: TFunction, key: CaringRolePresetKey): string {
  return t(`enterprise.caring_role_presets.${key}.description`);
}
