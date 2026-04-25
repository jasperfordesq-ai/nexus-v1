// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * SellerCard - Mini seller profile card
 *
 * Compact horizontal layout showing seller avatar, name, verification
 * status, and seller type. Used on listing detail pages.
 */

import { Avatar, Chip } from '@heroui/react';
import CheckCircle from 'lucide-react/icons/circle-check-big';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useTenant } from '@/contexts';
import { resolveAvatarUrl } from '@/lib/helpers';

interface SellerCardProps {
  seller: {
    id: number;
    name: string;
    avatar_url?: string;
    is_verified?: boolean;
    seller_type?: string;
  };
}

export function SellerCard({ seller }: SellerCardProps) {
  const { t } = useTranslation('marketplace');
  const { tenantPath } = useTenant();

  return (
    <div className="flex items-center gap-3 p-3 rounded-lg bg-theme-elevated">
      <Avatar
        src={resolveAvatarUrl(seller.avatar_url)}
        name={seller.name}
        size="md"
        className="shrink-0"
      />
      <div className="flex-1 min-w-0">
        <div className="flex items-center gap-1.5">
          <span className="text-sm font-semibold text-theme-primary truncate">
            {seller.name}
          </span>
          {seller.is_verified && (
            <CheckCircle
              className="w-4 h-4 text-[var(--color-info)] shrink-0"
              aria-label={t('seller.verified', 'Verified')}
            />
          )}
        </div>
        {seller.seller_type && (
          <Chip
            size="sm"
            variant="flat"
            color={seller.seller_type === 'business' ? 'secondary' : 'default'}
            className="mt-1"
          >
            {seller.seller_type === 'business'
              ? t('seller.business', 'Business')
              : t('seller.private', 'Private')}
          </Chip>
        )}
      </div>
      <Link
        to={tenantPath(`/marketplace/seller/${seller.id}`)}
        className="text-xs text-primary hover:underline shrink-0"
      >
        {t('seller.view_profile', 'View Profile')}
      </Link>
    </div>
  );
}

export default SellerCard;
