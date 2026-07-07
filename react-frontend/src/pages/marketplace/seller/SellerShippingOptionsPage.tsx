// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * SellerShippingOptionsPage — seller configuration for marketplace shipping.
 */

import { Link } from 'react-router-dom';
import ArrowLeft from 'lucide-react/icons/arrow-left';
import Truck from 'lucide-react/icons/truck';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { ShippingOptionsManager } from '@/components/marketplace/ShippingOptionsManager';
import { PageMeta } from '@/components/seo/PageMeta';
import { useAuth, useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';

export function SellerShippingOptionsPage() {
  const { t } = useTranslation('marketplace');
  const { user } = useAuth();
  const { tenantPath } = useTenant();

  usePageTitle(t('shipping.page_title'));

  return (
    <>
      <PageMeta title={t('shipping.page_title')} noIndex />
      <div className="mx-auto max-w-3xl px-4 py-6 space-y-6">
        <Button
          as={Link}
          to={tenantPath('/marketplace/my-listings')}
          variant="tertiary"
          size="sm"
          startContent={<ArrowLeft className="h-4 w-4" aria-hidden="true" />}
        >
          {t('shipping.back_to_listings')}
        </Button>

        <div className="space-y-1">
          <h1 className="flex items-center gap-2 text-2xl font-bold text-foreground">
            <Truck className="h-7 w-7 text-accent" aria-hidden="true" />
            {t('shipping.page_title')}
          </h1>
          <p className="text-sm text-muted">
            {t('shipping.page_subtitle')}
          </p>
        </div>

        <ShippingOptionsManager sellerId={user?.id ?? 0} />
      </div>
    </>
  );
}

export default SellerShippingOptionsPage;
