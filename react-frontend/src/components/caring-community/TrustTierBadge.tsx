// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Chip } from '@heroui/react';
import Award from 'lucide-react/icons/award';
import ShieldCheck from 'lucide-react/icons/shield-check';
import Star from 'lucide-react/icons/star';
import UserCheck from 'lucide-react/icons/user-check';
import Users from 'lucide-react/icons/users';
import { useTranslation } from 'react-i18next';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

export interface TrustTierBadgeProps {
  tier: 0 | 1 | 2 | 3 | 4;
  size?: 'sm' | 'md';
  showLabel?: boolean;
}

// ---------------------------------------------------------------------------
// Config
// ---------------------------------------------------------------------------

type ChipColor = 'default' | 'primary' | 'success' | 'secondary' | 'warning';

interface TierConfig {
  color: ChipColor;
  /** i18n key within the caring_community namespace */
  labelKey: string;
  Icon: React.ComponentType<{ className?: string; 'aria-hidden'?: boolean | 'true' | 'false' }>;
}

const TIER_CONFIG: Record<0 | 1 | 2 | 3 | 4, TierConfig> = {
  0: { color: 'default',   labelKey: 'trust_tier.tier_newcomer',    Icon: Users },
  1: { color: 'primary',   labelKey: 'trust_tier.tier_member',      Icon: UserCheck },
  2: { color: 'success',   labelKey: 'trust_tier.tier_trusted',     Icon: Star },
  3: { color: 'secondary', labelKey: 'trust_tier.tier_verified',    Icon: ShieldCheck },
  4: { color: 'warning',   labelKey: 'trust_tier.tier_coordinator', Icon: Award },
};

// ---------------------------------------------------------------------------
// Component
// ---------------------------------------------------------------------------

export function TrustTierBadge({ tier, size = 'sm', showLabel = true }: TrustTierBadgeProps) {
  const { t } = useTranslation('caring_community');
  const config = TIER_CONFIG[tier] ?? TIER_CONFIG[0];
  const { color, labelKey, Icon } = config;

  const iconClass = size === 'sm' ? 'h-3 w-3' : 'h-3.5 w-3.5';

  return (
    <Chip
      size={size}
      color={color}
      variant="flat"
      startContent={<Icon className={iconClass} aria-hidden="true" />}
    >
      {showLabel ? t(labelKey) : null}
    </Chip>
  );
}

export default TrustTierBadge;
