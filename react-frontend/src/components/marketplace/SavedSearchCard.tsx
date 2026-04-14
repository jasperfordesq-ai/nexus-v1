// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * SavedSearchCard — Displays a saved marketplace search with filters summary,
 * alert frequency, active toggle, and delete button.
 */

import { Card, CardBody, Chip, Switch, Button } from '@heroui/react';
import { Search, Bell, Trash2, MapPin, Tag } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { MarketplaceSavedSearch } from '@/types/marketplace';

interface SavedSearchCardProps {
  search: MarketplaceSavedSearch;
  onToggle?: (id: number, isActive: boolean) => void;
  onDelete?: (id: number) => void;
  onRun?: (search: MarketplaceSavedSearch) => void;
}

const FREQUENCY_COLORS: Record<string, 'primary' | 'warning' | 'secondary'> = {
  instant: 'primary',
  daily: 'warning',
  weekly: 'secondary',
};

export function SavedSearchCard({ search, onToggle, onDelete, onRun }: SavedSearchCardProps) {
  const { t } = useTranslation('marketplace');

  const filterSummary: string[] = [];
  if (search.search_query) {
    filterSummary.push(`"${search.search_query}"`);
  }
  if (search.filters?.location) {
    filterSummary.push(search.filters.location);
  }
  if (search.filters?.price_min != null || search.filters?.price_max != null) {
    const min = search.filters?.price_min ?? 0;
    const max = search.filters?.price_max;
    filterSummary.push(max ? `${min}–${max}` : `${min}+`);
  }
  if (search.filters?.condition) {
    filterSummary.push(search.filters.condition);
  }

  return (
    <Card className="bg-background/60 border border-divider">
      <CardBody className="p-4">
        <div className="flex items-start justify-between gap-3">
          <div className="flex-1 min-w-0">
            {/* Name + run */}
            <Button
              variant="light"
              onPress={() => onRun?.(search)}
              className="flex items-center gap-2 text-left hover:text-primary transition-colors h-auto p-0 min-w-0 justify-start"
            >
              <Search className="w-4 h-4 text-primary shrink-0" />
              <span className="font-semibold text-foreground truncate">{search.name}</span>
            </Button>

            {/* Filter chips */}
            {filterSummary.length > 0 && (
              <div className="flex flex-wrap gap-1.5 mt-2">
                {search.search_query && (
                  <Chip size="sm" variant="flat" startContent={<Search className="w-3 h-3" />}>
                    {search.search_query}
                  </Chip>
                )}
                {search.filters?.location && (
                  <Chip size="sm" variant="flat" startContent={<MapPin className="w-3 h-3" />}>
                    {search.filters.location}
                    {search.filters.radius ? ` (${search.filters.radius}km)` : ''}
                  </Chip>
                )}
                {search.filters?.category_id && (
                  <Chip size="sm" variant="flat" startContent={<Tag className="w-3 h-3" />}>
                    {t('collections.category', 'Category')}: {search.filters.category_id}
                  </Chip>
                )}
              </div>
            )}

            {/* Alert frequency */}
            <div className="flex items-center gap-2 mt-2">
              <Bell className="w-3.5 h-3.5 text-default-400" />
              <Chip
                size="sm"
                variant="flat"
                color={FREQUENCY_COLORS[search.alert_frequency] ?? 'default'}
              >
                {t(`saved_searches.frequency_${search.alert_frequency}`, search.alert_frequency)}
              </Chip>
              <span className="text-xs text-default-400">
                {search.alert_channel === 'both'
                  ? t('saved_searches.channel_both', 'Email & Push')
                  : search.alert_channel === 'push'
                    ? t('saved_searches.channel_push', 'Push')
                    : t('saved_searches.channel_email', 'Email')}
              </span>
            </div>
          </div>

          {/* Actions */}
          <div className="flex flex-col items-end gap-2 shrink-0">
            <Switch
              size="sm"
              isSelected={search.is_active}
              onValueChange={(val) => onToggle?.(search.id, val)}
              aria-label={t('saved_searches.toggle_active', 'Toggle active')}
            />
            <Button
              isIconOnly
              size="sm"
              variant="light"
              color="danger"
              onPress={() => onDelete?.(search.id)}
              aria-label={t('saved_searches.delete', 'Delete saved search')}
            >
              <Trash2 className="w-4 h-4" />
            </Button>
          </div>
        </div>
      </CardBody>
    </Card>
  );
}

export default SavedSearchCard;
