// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * MarketplaceEmptyState - Empty state placeholder for marketplace views
 *
 * Shows a muted icon, message, and optional call-to-action button
 * when no marketplace listings are available.
 */

import { Button } from '@heroui/react';
import ShoppingBag from 'lucide-react/icons/shopping-bag';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useTenant } from '@/contexts';

interface MarketplaceEmptyStateProps {
  message?: string;
  showCta?: boolean;
}

export function MarketplaceEmptyState({ message, showCta = false }: MarketplaceEmptyStateProps) {
  const { t } = useTranslation('marketplace');
  const { tenantPath } = useTenant();

  return (
    <div className="flex flex-col items-center justify-center py-16 text-center">
      <ShoppingBag
        className="w-16 h-16 text-theme-subtle mb-4"
        aria-hidden="true"
        strokeWidth={1.5}
      />
      <p className="text-lg text-theme-muted mb-2">
        {message || t('empty.no_listings', 'No listings yet')}
      </p>
      <p className="text-sm text-theme-subtle mb-6">
        {t('empty.subtitle', 'Check back later or be the first to list something.')}
      </p>
      {showCta && (
        <Button
          as={Link}
          to={tenantPath('/marketplace/sell')}
          color="primary"
          variant="solid"
        >
          {t('empty.start_selling', 'Start Selling')}
        </Button>
      )}
    </div>
  );
}

export default MarketplaceEmptyState;
