// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import ShieldCheck from 'lucide-react/icons/shield-check';
import { useTranslation } from 'react-i18next';
import { Chip } from '@/components/ui';

interface MarketplacePartnerBadgeProps {
  grantedAt?: string | null;
  size?: 'sm' | 'md' | 'lg';
}

export function MarketplacePartnerBadge({ grantedAt, size = 'sm' }: MarketplacePartnerBadgeProps) {
  const { t } = useTranslation('common');

  if (!grantedAt) return null;

  return (
    <Chip
      color="accent"
      variant="tertiary"
      size={size}
    >
      <ShieldCheck className="w-3.5 h-3.5" aria-hidden="true" />
      <Chip.Label>{t('marketplace.onboarding.partner_badge')}</Chip.Label>
    </Chip>
  );
}

export default MarketplacePartnerBadge;
