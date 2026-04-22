// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * BusinessSellerBadge - Verified business seller indicator
 *
 * Displays a "Business" chip for business sellers, with an additional
 * verified checkmark when the business has been verified.
 * Returns null for private sellers.
 */

import { Chip } from '@heroui/react';
import CheckCircle from 'lucide-react/icons/circle-check-big';
import { useTranslation } from 'react-i18next';

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
        variant="flat"
        size="sm"
        startContent={<CheckCircle className="w-3.5 h-3.5" aria-hidden="true" />}
      >
        {t('seller.verified_business', 'Verified Business')}
      </Chip>
    );
  }

  return (
    <Chip color="secondary" variant="flat" size="sm">
      {t('seller.business', 'Business')}
    </Chip>
  );
}

export default BusinessSellerBadge;
