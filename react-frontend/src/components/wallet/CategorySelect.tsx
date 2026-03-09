// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * CategorySelect - Dropdown for selecting a transaction category (W8)
 */

import { useState, useEffect, type CSSProperties } from 'react';
import { Select, SelectItem } from '@heroui/react';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import type { TransactionCategory } from '@/types/api';

interface CategorySelectProps {
  value?: number | null;
  onChange: (categoryId: number | null) => void;
  label?: string;
  placeholder?: string;
  className?: string;
}

export function CategorySelect({
  value,
  onChange,
  label = 'Category',
  placeholder = 'Select a category',
  className,
}: CategorySelectProps) {
  const [categories, setCategories] = useState<TransactionCategory[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    async function loadCategories() {
      try {
        const response = await api.get<TransactionCategory[]>('/v2/wallet/categories');
        if (response.success && response.data) {
          setCategories(response.data);
        }
      } catch (err) {
        logError('Failed to load categories', err);
      } finally {
        setIsLoading(false);
      }
    }
    loadCategories();
  }, []);

  if (isLoading || categories.length === 0) {
    return null;
  }

  return (
    <Select
      label={label}
      placeholder={placeholder}
      selectedKeys={value ? [String(value)] : []}
      onSelectionChange={(keys) => {
        const selected = Array.from(keys)[0] as string | undefined;
        onChange(selected ? parseInt(selected, 10) : null);
      }}
      className={className}
      classNames={{
        trigger: 'bg-theme-elevated border-theme-default',
        value: 'text-theme-primary',
        label: 'text-theme-muted',
      }}
    >
      {categories.map((cat) => (
        <SelectItem key={String(cat.id)} textValue={cat.name}>
          <div className="flex items-center gap-2">
            {cat.color && (
              <div
                className="w-3 h-3 rounded-full flex-shrink-0"
                style={{ '--category-color': cat.color, backgroundColor: 'var(--category-color)' } as CSSProperties}
              />
            )}
            <span>{cat.name}</span>
          </div>
        </SelectItem>
      ))}
    </Select>
  );
}
