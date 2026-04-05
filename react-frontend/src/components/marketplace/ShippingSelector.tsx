// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * ShippingSelector — Buyer-facing shipping option selector.
 *
 * Shows a radio group of available shipping options from the seller
 * plus a "Local Pickup - Free" option when enabled. Selected option is
 * passed up to the parent via onSelect callback.
 */

import { useState, useEffect, useCallback } from 'react';
import {
  RadioGroup,
  Radio,
  Spinner,
  Chip,
} from '@heroui/react';
import { Truck, MapPin, Package, Clock } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import type { MarketplaceShippingOption } from '@/types/marketplace';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

export interface ShippingSelectorProps {
  sellerId: number;
  onSelect: (option: MarketplaceShippingOption | null) => void;
  /** Whether local pickup is available for this listing. */
  localPickup: boolean;
}

// The special "local pickup" pseudo-option ID
const LOCAL_PICKUP_ID = 'local_pickup';

// ─────────────────────────────────────────────────────────────────────────────
// Main Component
// ─────────────────────────────────────────────────────────────────────────────

export function ShippingSelector({ sellerId, onSelect, localPickup }: ShippingSelectorProps) {
  const { t } = useTranslation('marketplace');

  const [options, setOptions] = useState<MarketplaceShippingOption[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [selectedValue, setSelectedValue] = useState<string>('');
  const [error, setError] = useState<string | null>(null);

  // Load shipping options for this seller
  useEffect(() => {
    let cancelled = false;

    const load = async () => {
      setIsLoading(true);
      setError(null);
      try {
        const response = await api.get<MarketplaceShippingOption[]>(
          `/v2/marketplace/sellers/${sellerId}/shipping-options`
        );
        if (cancelled) return;
        if (response.success && response.data) {
          const activeOptions = response.data.filter((o) => o.is_active);
          setOptions(activeOptions);

          // Auto-select: local pickup first if available, else default option, else first option
          if (localPickup) {
            setSelectedValue(LOCAL_PICKUP_ID);
            // onSelect(null) means local pickup — parent interprets this
          } else {
            const defaultOpt = activeOptions.find((o) => o.is_default) || activeOptions[0];
            if (defaultOpt) {
              setSelectedValue(String(defaultOpt.id));
            }
          }
        }
      } catch (err) {
        if (!cancelled) {
          logError('Failed to load seller shipping options', err);
          setError(t('shipping.load_error', 'Failed to load shipping options'));
        }
      } finally {
        if (!cancelled) setIsLoading(false);
      }
    };

    load();
    return () => { cancelled = true; };
  }, [sellerId, localPickup, t]);

  // When selectedValue changes, fire onSelect
  useEffect(() => {
    if (!selectedValue) {
      onSelect(null);
      return;
    }
    if (selectedValue === LOCAL_PICKUP_ID) {
      onSelect(null);
      return;
    }
    const opt = options.find((o) => String(o.id) === selectedValue);
    onSelect(opt || null);
  }, [selectedValue, options]); // eslint-disable-line react-hooks/exhaustive-deps

  const handleSelectionChange = useCallback((value: string) => {
    setSelectedValue(value);
  }, []);

  // ─── Loading ───────────────────────────────────────────────────────────────
  if (isLoading) {
    return (
      <div className="flex items-center gap-2 py-4">
        <Spinner size="sm" color="primary" />
        <span className="text-sm text-default-500">
          {t('shipping.loading', 'Loading shipping options...')}
        </span>
      </div>
    );
  }

  // ─── Error ─────────────────────────────────────────────────────────────────
  if (error) {
    return (
      <p className="text-sm text-danger py-2">{error}</p>
    );
  }

  // No shipping options and no local pickup
  if (options.length === 0 && !localPickup) {
    return (
      <div className="py-3">
        <p className="text-sm text-default-500">
          {t('shipping.no_options', 'No shipping options available for this seller.')}
        </p>
      </div>
    );
  }

  // ─── Radio group ───────────────────────────────────────────────────────────
  return (
    <div className="space-y-3">
      <h4 className="text-sm font-semibold text-foreground flex items-center gap-2">
        <Truck className="w-4 h-4 text-primary" />
        {t('shipping.select_title', 'Delivery Method')}
      </h4>

      <RadioGroup
        value={selectedValue}
        onValueChange={handleSelectionChange}
        classNames={{ wrapper: 'gap-2' }}
      >
        {/* Local Pickup — always first if enabled */}
        {localPickup && (
          <Radio
            value={LOCAL_PICKUP_ID}
            classNames={{
              base: 'max-w-full m-0 border border-default-200 rounded-lg p-3 data-[selected=true]:border-primary',
            }}
          >
            <div className="flex items-center gap-3">
              <MapPin className="w-4 h-4 text-success shrink-0" />
              <div className="flex-1">
                <span className="text-sm font-medium text-foreground">
                  {t('shipping.local_pickup', 'Local Pickup')}
                </span>
                <span className="text-xs text-default-500 ml-2">
                  {t('shipping.local_pickup_subtitle', 'Collect in person')}
                </span>
              </div>
              <Chip size="sm" color="success" variant="flat">
                {t('price.free', 'Free')}
              </Chip>
            </div>
          </Radio>
        )}

        {/* Shipping options */}
        {options.map((option) => (
          <Radio
            key={option.id}
            value={String(option.id)}
            classNames={{
              base: 'max-w-full m-0 border border-default-200 rounded-lg p-3 data-[selected=true]:border-primary',
            }}
          >
            <div className="flex items-center gap-3">
              <Package className="w-4 h-4 text-primary shrink-0" />
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2">
                  <span className="text-sm font-medium text-foreground">
                    {option.courier_name}
                  </span>
                  {option.is_default && (
                    <Chip size="sm" variant="flat" color="secondary" className="text-[10px]">
                      {t('shipping.recommended', 'Recommended')}
                    </Chip>
                  )}
                </div>
                {option.estimated_days != null && (
                  <span className="text-xs text-default-500 flex items-center gap-1 mt-0.5">
                    <Clock className="w-3 h-3" />
                    {t('shipping.estimated_delivery', '~{{days}} days', { days: option.estimated_days })}
                  </span>
                )}
              </div>
              <span className="text-sm font-semibold text-foreground shrink-0">
                {new Intl.NumberFormat(undefined, {
                  style: 'currency',
                  currency: option.currency || 'EUR',
                  minimumFractionDigits: 0,
                  maximumFractionDigits: 2,
                }).format(option.price)}
              </span>
            </div>
          </Radio>
        ))}
      </RadioGroup>
    </div>
  );
}

export default ShippingSelector;
