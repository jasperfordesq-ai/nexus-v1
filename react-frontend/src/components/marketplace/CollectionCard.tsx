// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * CollectionCard — Displays a marketplace collection with name, item count,
 * thumbnail grid (first 4 item images), and public/private badge.
 */

import { Card, CardBody, Chip } from '@heroui/react';
import { FolderHeart, Lock, Globe, Package } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { MarketplaceCollection } from '@/types/marketplace';

interface CollectionCardProps {
  collection: MarketplaceCollection;
  /** First 4 thumbnail URLs for the image grid preview */
  thumbnails?: string[];
  onClick?: (collection: MarketplaceCollection) => void;
}

export function CollectionCard({ collection, thumbnails = [], onClick }: CollectionCardProps) {
  const { t } = useTranslation('marketplace');

  return (
    <Card
      isPressable={!!onClick}
      onPress={() => onClick?.(collection)}
      className="bg-background/60 border border-divider hover:border-primary/40 transition-colors"
    >
      <CardBody className="p-0">
        {/* Thumbnail grid */}
        <div className="aspect-[4/3] bg-default-100 relative overflow-hidden rounded-t-lg">
          {thumbnails.length > 0 ? (
            <div className="grid grid-cols-2 grid-rows-2 w-full h-full gap-0.5">
              {[0, 1, 2, 3].map((i) => (
                <div key={i} className="bg-default-200 overflow-hidden">
                  {thumbnails[i] ? (
                    <img
                      src={thumbnails[i]}
                      alt=""
                      className="w-full h-full object-cover"
                      loading="lazy"
                    />
                  ) : (
                    <div className="w-full h-full flex items-center justify-center">
                      <Package className="w-6 h-6 text-default-300" />
                    </div>
                  )}
                </div>
              ))}
            </div>
          ) : (
            <div className="w-full h-full flex items-center justify-center">
              <FolderHeart className="w-12 h-12 text-default-300" />
            </div>
          )}
        </div>

        {/* Info */}
        <div className="p-3 space-y-1.5">
          <div className="flex items-center justify-between gap-2">
            <h3 className="font-semibold text-foreground text-sm truncate">{collection.name}</h3>
            <Chip
              size="sm"
              variant="flat"
              color={collection.is_public ? 'success' : 'default'}
              startContent={collection.is_public
                ? <Globe className="w-3 h-3" />
                : <Lock className="w-3 h-3" />}
            >
              {collection.is_public
                ? t('collections.public', 'Public')
                : t('collections.private', 'Private')}
            </Chip>
          </div>

          {collection.description && (
            <p className="text-xs text-default-500 line-clamp-2">{collection.description}</p>
          )}

          <p className="text-xs text-default-400">
            {t('collections.item_count', '{{count}} items', { count: collection.item_count })}
          </p>
        </div>
      </CardBody>
    </Card>
  );
}

export default CollectionCard;
