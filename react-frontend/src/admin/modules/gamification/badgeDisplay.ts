// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { TFunction } from 'i18next';

interface LocalizableBadge {
  name: string;
  description?: string;
  name_code?: string | null;
  description_code?: string | null;
}

export function badgeDisplayName(t: TFunction, badge: LocalizableBadge): string {
  return badge.name_code
    ? t(`gamification.${badge.name_code}`, { defaultValue: badge.name })
    : badge.name;
}

export function badgeDisplayDescription(t: TFunction, badge: LocalizableBadge): string {
  return badge.description_code
    ? t(`gamification.${badge.description_code}`, { defaultValue: badge.description ?? '' })
    : (badge.description ?? '');
}
