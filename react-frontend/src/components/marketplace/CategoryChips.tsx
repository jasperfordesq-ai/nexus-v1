// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * CategoryChips - Horizontal scrollable category pills
 *
 * Renders a scrollable row of category chips with an "All" option.
 * The active category is highlighted with the primary color.
 */

import { Chip } from '@heroui/react';
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
    <div
      className="flex items-center gap-2 overflow-x-auto pb-2 scrollbar-hide"
      role="listbox"
      aria-label={t('categories.label', 'Filter by category')}
    >
      {/* "All" chip */}
      <Chip
        as="button"
        variant={activeId == null ? 'solid' : 'flat'}
        color={activeId == null ? 'primary' : 'default'}
        className="shrink-0 cursor-pointer"
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
          className="shrink-0 cursor-pointer"
          onClick={() => onSelect(category.id)}
          role="option"
          aria-selected={activeId === category.id}
        >
          {category.name}
        </Chip>
      ))}
    </div>
  );
}

export default CategoryChips;
