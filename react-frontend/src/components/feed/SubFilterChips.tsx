// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useTranslation } from 'react-i18next';
import type { Key } from '@heroui/react';
import { TagGroup, Tag } from '@/components/ui';

interface SubFilterChipsProps {
  filter: string;
  subFilter: string | null;
  onSubFilterChange: (subFilter: string | null) => void;
}

interface SubFilterOption {
  key: string | null;
  labelKey: string;
}

// Sentinel id for the "All" tag — TagGroup keys must be strings, so null maps to this.
const ALL_KEY = '__all__';

const LISTING_SUB_FILTERS: SubFilterOption[] = [
  { key: null, labelKey: 'subfilter.all' },
  { key: 'offer', labelKey: 'subfilter.offers' },
  { key: 'request', labelKey: 'subfilter.requests' },
];

const SUB_FILTERS_MAP: Record<string, SubFilterOption[]> = {
  listings: LISTING_SUB_FILTERS,
};

export function SubFilterChips({ filter, subFilter, onSubFilterChange }: SubFilterChipsProps) {
  const { t } = useTranslation('feed');

  const options = SUB_FILTERS_MAP[filter];
  if (!options) return null;

  const selectedKeys = new Set<Key>([subFilter ?? ALL_KEY]);

  return (
    <TagGroup
      aria-label={t('filter.select')}
      selectionMode="single"
      disallowEmptySelection
      selectedKeys={selectedKeys}
      onSelectionChange={(keys) => {
        const set = keys === 'all' ? null : keys;
        if (!set) return;
        const [key] = Array.from(set);
        onSubFilterChange(!key || key === ALL_KEY ? null : String(key));
      }}
    >
      <TagGroup.List className="flex flex-wrap items-center gap-1.5">
        {options.map((option) => (
          <Tag key={option.key ?? ALL_KEY} id={option.key ?? ALL_KEY}>
            {t(option.labelKey)}
          </Tag>
        ))}
      </TagGroup.List>
    </TagGroup>
  );
}

export default SubFilterChips;
