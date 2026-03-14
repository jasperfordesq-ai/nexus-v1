// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * SuggestedListingsWidget - Shows recommended listings in the sidebar
 */

import { Link } from 'react-router-dom';
import { Chip } from '@heroui/react';
import { Sparkles, Heart, HandHelping } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { useTenant } from '@/contexts';

export interface SuggestedListing {
  id: number;
  title: string;
  type: 'offer' | 'request';
  owner_name: string;
}

interface SuggestedListingsWidgetProps {
  listings: SuggestedListing[];
}

export function SuggestedListingsWidget({ listings }: SuggestedListingsWidgetProps) {
  const { tenantPath } = useTenant();
  const { t } = useTranslation('feed');

  if (listings.length === 0) return null;

  return (
    <GlassCard className="p-4">
      <div className="flex items-center justify-between mb-3">
        <div className="flex items-center gap-2">
          <Sparkles className="w-4 h-4 text-purple-500" aria-hidden="true" />
          <h3 className="font-semibold text-sm text-[var(--text-primary)]">
            {t('sidebar.suggested.title', 'Suggested For You')}
          </h3>
        </div>
        <Link
          to={tenantPath('/listings')}
          className="text-xs text-indigo-500 hover:text-indigo-600 transition-colors"
        >
          {t('sidebar.suggested.see_all', 'See All')}
        </Link>
      </div>

      <div className="space-y-2">
        {listings.map((listing) => (
          <Link
            key={listing.id}
            to={tenantPath(`/listings/${listing.id}`)}
            className="flex items-start gap-3 p-2 rounded-lg hover:bg-[var(--surface-elevated)] transition-colors group"
          >
            <div className={`w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5 ${
              listing.type === 'offer' ? 'bg-emerald-500/10' : 'bg-orange-500/10'
            }`}>
              {listing.type === 'offer' ? (
                <Heart className="w-3.5 h-3.5 text-emerald-500" aria-hidden="true" />
              ) : (
                <HandHelping className="w-3.5 h-3.5 text-orange-500" aria-hidden="true" />
              )}
            </div>
            <div className="flex-1 min-w-0">
              <p className="text-sm font-medium text-[var(--text-primary)] truncate group-hover:text-indigo-500 transition-colors">
                {listing.title}
              </p>
              <p className="text-xs text-[var(--text-muted)] truncate">
                {t('sidebar.by_owner', 'by {{name}}', { name: listing.owner_name })}
              </p>
            </div>
            <Chip
              size="sm"
              variant="flat"
              className={listing.type === 'offer'
                ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 text-[10px]'
                : 'bg-orange-500/10 text-orange-600 dark:text-orange-400 text-[10px]'
              }
            >
              {listing.type === 'offer'
                ? t('sidebar.offer', 'Offer')
                : t('sidebar.suggested.request', 'Request')
              }
            </Chip>
          </Link>
        ))}
      </div>
    </GlassCard>
  );
}

export default SuggestedListingsWidget;
