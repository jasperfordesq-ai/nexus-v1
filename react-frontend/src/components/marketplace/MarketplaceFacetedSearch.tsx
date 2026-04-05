// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * MarketplaceFacetedSearch - Filter sidebar for marketplace browse
 *
 * Provides category, price range, condition, seller type, delivery method,
 * sort, and time-posted filters. Collapsible on mobile via HeroUI Accordion.
 */

import { useState, useCallback } from 'react';
import {
  Accordion,
  AccordionItem,
  Button,
  CheckboxGroup,
  Checkbox,
  Input,
  RadioGroup,
  Radio,
  Select,
  SelectItem,
} from '@heroui/react';
import { SlidersHorizontal } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { MarketplaceFilters, MarketplaceCategory } from '@/types/marketplace';

interface MarketplaceFacetedSearchProps {
  filters: MarketplaceFilters;
  onChange: (filters: MarketplaceFilters) => void;
  categories: MarketplaceCategory[];
}

const CONDITION_OPTIONS = [
  { value: 'new', label: 'New' },
  { value: 'like_new', label: 'Like New' },
  { value: 'good', label: 'Good' },
  { value: 'fair', label: 'Fair' },
  { value: 'poor', label: 'Poor' },
] as const;

const SORT_OPTIONS = [
  { value: 'newest', label: 'Newest First' },
  { value: 'price_asc', label: 'Price: Low to High' },
  { value: 'price_desc', label: 'Price: High to Low' },
  { value: 'popular', label: 'Most Popular' },
] as const;

const POSTED_WITHIN_OPTIONS = [
  { value: '1', label: 'Today' },
  { value: '3', label: 'Last 3 Days' },
  { value: '7', label: 'Last 7 Days' },
  { value: '30', label: 'Last 30 Days' },
  { value: '', label: 'Any Time' },
] as const;

export function MarketplaceFacetedSearch({
  filters,
  onChange,
  categories,
}: MarketplaceFacetedSearchProps) {
  const { t } = useTranslation('marketplace');
  const [localFilters, setLocalFilters] = useState<MarketplaceFilters>(filters);

  const updateFilter = useCallback(
    <K extends keyof MarketplaceFilters>(key: K, value: MarketplaceFilters[K]) => {
      setLocalFilters((prev) => ({ ...prev, [key]: value }));
    },
    [],
  );

  const handleApply = useCallback(() => {
    onChange(localFilters);
  }, [localFilters, onChange]);

  const handleClearAll = useCallback(() => {
    const cleared: MarketplaceFilters = {};
    setLocalFilters(cleared);
    onChange(cleared);
  }, [onChange]);

  const filterContent = (
    <div className="space-y-5">
      {/* Category */}
      <div>
        <Select
          label={t('filters.category', 'Category')}
          variant="bordered"
          size="sm"
          selectedKeys={localFilters.category_id != null ? [String(localFilters.category_id)] : []}
          onSelectionChange={(keys) => {
            const selected = Array.from(keys)[0] as string | undefined;
            updateFilter('category_id', selected ? Number(selected) : undefined);
          }}
        >
          {categories.map((cat) => (
            <SelectItem key={String(cat.id)}>
              {cat.name}
            </SelectItem>
          ))}
        </Select>
      </div>

      {/* Price range */}
      <div>
        <p className="text-xs font-medium text-theme-muted mb-2">
          {t('filters.price_range', 'Price Range')}
        </p>
        <div className="flex gap-2">
          <Input
            type="number"
            placeholder={t('filters.min', 'Min')}
            size="sm"
            variant="bordered"
            min={0}
            value={localFilters.price_min != null ? String(localFilters.price_min) : ''}
            onValueChange={(v) => updateFilter('price_min', v ? Number(v) : undefined)}
          />
          <Input
            type="number"
            placeholder={t('filters.max', 'Max')}
            size="sm"
            variant="bordered"
            min={0}
            value={localFilters.price_max != null ? String(localFilters.price_max) : ''}
            onValueChange={(v) => updateFilter('price_max', v ? Number(v) : undefined)}
          />
        </div>
      </div>

      {/* Condition */}
      <div>
        <CheckboxGroup
          label={t('filters.condition', 'Condition')}
          size="sm"
          value={localFilters.condition ?? []}
          onValueChange={(value) => updateFilter('condition', value as string[])}
        >
          {CONDITION_OPTIONS.map((opt) => (
            <Checkbox key={opt.value} value={opt.value}>
              {t(`condition.${opt.value}`, opt.label)}
            </Checkbox>
          ))}
        </CheckboxGroup>
      </div>

      {/* Seller type */}
      <div>
        <RadioGroup
          label={t('filters.seller_type', 'Seller Type')}
          size="sm"
          value={localFilters.seller_type ?? 'all'}
          onValueChange={(value) =>
            updateFilter('seller_type', value === 'all' ? undefined : value)
          }
        >
          <Radio value="all">{t('filters.seller_all', 'All')}</Radio>
          <Radio value="private">{t('filters.seller_private', 'Private')}</Radio>
          <Radio value="business">{t('filters.seller_business', 'Business')}</Radio>
        </RadioGroup>
      </div>

      {/* Delivery method */}
      <div>
        <RadioGroup
          label={t('filters.delivery_method', 'Delivery Method')}
          size="sm"
          value={localFilters.delivery_method ?? 'all'}
          onValueChange={(value) =>
            updateFilter('delivery_method', value === 'all' ? undefined : value)
          }
        >
          <Radio value="all">{t('filters.delivery_all', 'All')}</Radio>
          <Radio value="shipping">{t('filters.delivery_shipping', 'Shipping')}</Radio>
          <Radio value="pickup">{t('filters.delivery_pickup', 'Local Pickup')}</Radio>
          <Radio value="both">{t('filters.delivery_both', 'Both')}</Radio>
        </RadioGroup>
      </div>

      {/* Sort */}
      <div>
        <Select
          label={t('filters.sort', 'Sort By')}
          variant="bordered"
          size="sm"
          selectedKeys={localFilters.sort ? [localFilters.sort] : ['newest']}
          onSelectionChange={(keys) => {
            const selected = Array.from(keys)[0] as string | undefined;
            updateFilter('sort', selected || undefined);
          }}
        >
          {SORT_OPTIONS.map((opt) => (
            <SelectItem key={opt.value}>
              {t(`filters.sort_${opt.value}`, opt.label)}
            </SelectItem>
          ))}
        </Select>
      </div>

      {/* Posted within */}
      <div>
        <Select
          label={t('filters.posted_within', 'Posted Within')}
          variant="bordered"
          size="sm"
          selectedKeys={
            localFilters.posted_within != null
              ? [String(localFilters.posted_within)]
              : ['']
          }
          onSelectionChange={(keys) => {
            const selected = Array.from(keys)[0] as string | undefined;
            updateFilter('posted_within', selected ? Number(selected) : undefined);
          }}
        >
          {POSTED_WITHIN_OPTIONS.map((opt) => (
            <SelectItem key={opt.value}>
              {t(`filters.posted_${opt.value || 'any'}`, opt.label)}
            </SelectItem>
          ))}
        </Select>
      </div>

      {/* Actions */}
      <div className="flex gap-2 pt-2">
        <Button color="primary" className="flex-1" onPress={handleApply}>
          {t('filters.apply', 'Apply Filters')}
        </Button>
        <Button variant="flat" onPress={handleClearAll}>
          {t('filters.clear', 'Clear All')}
        </Button>
      </div>
    </div>
  );

  return (
    <>
      {/* Desktop: always visible sidebar */}
      <div className="hidden lg:block">{filterContent}</div>

      {/* Mobile/Tablet: collapsible accordion */}
      <div className="lg:hidden">
        <Accordion variant="bordered">
          <AccordionItem
            key="filters"
            aria-label={t('filters.title', 'Filters')}
            title={t('filters.title', 'Filters')}
            startContent={<SlidersHorizontal className="w-4 h-4" aria-hidden="true" />}
          >
            {filterContent}
          </AccordionItem>
        </Accordion>
      </div>
    </>
  );
}

export default MarketplaceFacetedSearch;
