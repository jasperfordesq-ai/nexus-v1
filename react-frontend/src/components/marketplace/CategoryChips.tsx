// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Chip, ScrollShadow } from '@heroui/react';
import { LayoutGrid } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { MarketplaceCategory } from '@/types/marketplace';

interface CategoryChipsProps {
  categories: MarketplaceCategory[];
  activeId?: number;
  onSelect: (id: number | null) => void;
}

export function CategoryChips({ categories, activeId, onSelect }: CategoryChipsProps) {
  const { t } = useTranslation('marketplace');

  return (
    <ScrollShadow
      orientation="horizontal"
      hideScrollBar
      className="w-full"
      role="listbox"
      aria-label={t('categories.label', 'Filter by category')}
    >
      <div className="flex items-center gap-2 pb-1 w-max">
        <Chip
          as="button"
          variant={activeId == null ? 'solid' : 'flat'}
          color={activeId == null ? 'primary' : 'default'}
          className="cursor-pointer shrink-0"
          startContent={<LayoutGrid className="w-3.5 h-3.5" aria-hidden="true" />}
          onClick={() => onSelect(null)}
          role="option"
          aria-selected={activeId == null}
        >
          {t('categories.all', 'All')}
        </Chip>

        {categories.map((category) => (
          <Chip
            key={category.id}
            as="button"
            variant={activeId === category.id ? 'solid' : 'flat'}
            color={activeId === category.id ? 'primary' : 'default'}
            className="cursor-pointer shrink-0"
            onClick={() => onSelect(category.id)}
            role="option"
            aria-selected={activeId === category.id}
          >
            {category.name}
          </Chip>
        ))}
      </div>
    </ScrollShadow>
  );
}

export default CategoryChips;
