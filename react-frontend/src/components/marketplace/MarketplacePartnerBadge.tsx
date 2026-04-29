// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * MarketplacePartnerBadge — AG48 verified marketplace partner indicator.
 *
 * Shown on seller profile pages when `marketplace_partner_badge_at` is set
 * (granted on first approved listing after onboarding completion).
 */

import { Chip } from '@heroui/react';
import ShieldCheck from 'lucide-react/icons/shield-check';
import { useTranslation } from 'react-i18next';

interface MarketplacePartnerBadgeProps {
  grantedAt?: string | null;
  size?: 'sm' | 'md' | 'lg';
}

export function MarketplacePartnerBadge({ grantedAt, size = 'sm' }: MarketplacePartnerBadgeProps) {
  const { t } = useTranslation('common');

  if (!grantedAt) return null;

  return (
    <Chip
      color="primary"
      variant="flat"
      size={size}
      startContent={<ShieldCheck className="w-3.5 h-3.5" aria-hidden="true" />}
    >
      {t('marketplace.onboarding.partner_badge', 'Marketplace Partner')}
    </Chip>
  );
}

export default MarketplacePartnerBadge;
