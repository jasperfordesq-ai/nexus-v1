// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import CheckCircle from 'lucide-react/icons/circle-check-big';
import { useTranslation } from 'react-i18next';
import { Chip } from '@/components/ui';

interface BusinessSellerBadgeProps {
  sellerType: string;
  businessVerified?: boolean;
}

export function BusinessSellerBadge({ sellerType, businessVerified }: BusinessSellerBadgeProps) {
  const { t } = useTranslation('marketplace');

  if (sellerType !== 'business') {
    return null;
  }

  if (businessVerified) {
    return (
      <Chip
        color="success"
        variant="tertiary"
        size="sm"
      >
        <CheckCircle className="w-3.5 h-3.5" aria-hidden="true" />
        <Chip.Label>{t('seller.verified_business')}</Chip.Label>
      </Chip>
    );
  }

  return (
    <Chip color="default" variant="tertiary" size="sm">
      {t('seller.business')}
    </Chip>
  );
}

export default BusinessSellerBadge;
