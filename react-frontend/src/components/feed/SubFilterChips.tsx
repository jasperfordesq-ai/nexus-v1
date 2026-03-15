// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * SubFilterChips — contextual sub-filters that appear based on the active filter type.
 */

import { Button } from '@heroui/react';
import { useTranslation } from 'react-i18next';

interface SubFilterChipsProps {
  filter: string;
  subFilter: string | null;
  onSubFilterChange: (subFilter: string | null) => void;
}

interface SubFilterOption {
  key: string | null;
  labelKey: string;
  fallback: string;
}

const LISTING_SUB_FILTERS: SubFilterOption[] = [
  { key: null, labelKey: 'subfilter.all', fallback: 'All' },
  { key: 'offer', labelKey: 'subfilter.offers', fallback: 'Offers' },
  { key: 'request', labelKey: 'subfilter.requests', fallback: 'Requests' },
];

const SUB_FILTERS_MAP: Record<string, SubFilterOption[]> = {
  listings: LISTING_SUB_FILTERS,
};

export function SubFilterChips({ filter, subFilter, onSubFilterChange }: SubFilterChipsProps) {
  const { t } = useTranslation('feed');

  const options = SUB_FILTERS_MAP[filter];
  if (!options) return null;

  return (
    <div className="flex items-center gap-1.5 flex-wrap">
      {options.map((option) => {
        const isActive = subFilter === option.key;

        return (
          <Button
            key={option.key ?? 'all'}
            size="sm"
            radius="full"
            variant={isActive ? 'flat' : 'light'}
            className={
              isActive
                ? 'bg-[var(--color-primary)]/10 text-[var(--color-primary)] border border-[var(--color-primary)]/20 min-w-fit'
                : 'text-[var(--text-muted)] min-w-fit'
            }
            onPress={() => onSubFilterChange(option.key)}
          >
            {t(option.labelKey, option.fallback)}
          </Button>
        );
      })}
    </div>
  );
}

export default SubFilterChips;
