// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * MarketplaceListingCard - Grid card for marketplace listing display
 *
 * Shows an image with overlaid price and condition badges, listing title,
 * location, seller info, and a toggleable save/heart button.
 */

import { useState, useCallback } from 'react';
import { Button, Card, CardBody, Chip } from '@heroui/react';
import Heart from 'lucide-react/icons/heart';
import MapPin from 'lucide-react/icons/map-pin';
import Megaphone from 'lucide-react/icons/megaphone';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useTenant } from '@/contexts';
import { ImagePlaceholder } from '@/components/ui';
import { PriceBadge } from './PriceBadge';
import { ConditionBadge } from './ConditionBadge';
import type { MarketplaceListingItem } from '@/types/marketplace';

interface MarketplaceListingCardProps {
  listing: MarketplaceListingItem;
  onSave?: (id: number) => void;
  onUnsave?: (id: number) => void;
}

export function MarketplaceListingCard({ listing, onSave, onUnsave }: MarketplaceListingCardProps) {
  const { t } = useTranslation('marketplace');
  const { tenantPath } = useTenant();
  const [isSaved, setIsSaved] = useState(listing.is_saved);

  const handleToggleSave = useCallback(
    () => {
      if (isSaved) {
        onUnsave?.(listing.id);
      } else {
        onSave?.(listing.id);
      }
      setIsSaved((prev) => !prev);
    },
    [isSaved, listing.id, onSave, onUnsave],
  );

  const imageUrl = listing.image?.thumbnail_url || listing.image?.url;

  return (
    <Card
      className="group relative overflow-hidden bg-default-50 border border-default-200 transition-shadow duration-200 hover:shadow-lg focus-within:ring-2 focus-within:ring-primary/45"
    >
      <Link
        to={tenantPath(`/marketplace/${listing.id}`)}
        className="block h-full focus-visible:outline-none"
      >
        {/* Image container */}
        <div className="relative aspect-video overflow-hidden">
          {imageUrl ? (
            <img
              src={imageUrl}
              alt={listing.image?.alt_text || listing.title}
              className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
              loading="lazy"
            />
          ) : (
            <ImagePlaceholder className="w-full h-full" />
          )}

          <div className="absolute inset-x-0 bottom-0 h-24 bg-gradient-to-t from-black/55 via-black/20 to-transparent" />

          {/* Price badge - bottom-left */}
          <div className="absolute bottom-2 left-2 max-w-[calc(100%-1rem)]">
            <PriceBadge
              price={listing.price}
              currency={listing.price_currency}
              priceType={listing.price_type}
              timeCreditPrice={listing.time_credit_price}
              isOverlay
            />
          </div>

          {/* Condition badge - top-left */}
          {listing.condition && (
            <div className="absolute top-2 left-2">
              <ConditionBadge condition={listing.condition} />
            </div>
          )}

          {/* Promoted badge */}
          {listing.is_promoted && (
            <div className="absolute bottom-2 right-2">
              <Chip
                size="sm"
                variant="solid"
                color="secondary"
                startContent={<Megaphone className="w-3 h-3" aria-hidden="true" />}
              >
                {t('listing.promoted', 'Promoted')}
              </Chip>
            </div>
          )}
        </div>

        {/* Content */}
        <CardBody className="p-3 gap-1.5">
          <h3 className="text-sm font-semibold text-theme-primary line-clamp-2 leading-tight">
            {listing.title}
          </h3>

          {listing.location && (
            <div className="flex items-center gap-1 text-xs text-theme-muted">
              <MapPin className="w-3 h-3 shrink-0" aria-hidden="true" />
              <span className="truncate">{listing.location}</span>
            </div>
          )}

          {listing.user && (
            <p className="text-xs text-theme-subtle truncate">
              {listing.user.name}
            </p>
          )}
        </CardBody>
      </Link>

      {/* Save/Heart button - separate from the listing link */}
      <Button
        isIconOnly
        variant="flat"
        size="sm"
        onPress={handleToggleSave}
        className="absolute top-2 right-2 z-20 bg-background/90 text-foreground shadow-lg ring-1 ring-black/10 backdrop-blur-md transition-colors hover:bg-background"
        aria-label={
          isSaved
            ? t('listing.unsave', 'Remove from saved')
            : t('listing.save', 'Save listing')
        }
      >
        <Heart
          className={`w-4 h-4 ${isSaved ? 'fill-rose-500 text-rose-500' : 'text-foreground'}`}
          aria-hidden="true"
        />
      </Button>
    </Card>
  );
}

export default MarketplaceListingCard;
