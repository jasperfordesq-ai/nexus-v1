// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { Button, Popover, PopoverTrigger, PopoverContent, Spinner } from '@heroui/react';
import MapPin from 'lucide-react/icons/map-pin';
import X from 'lucide-react/icons/x';
import { useGeolocation } from '@/hooks/useGeolocation';

export interface ProximityFilterParams {
  near_lat: number;
  near_lng: number;
  radius_km: number;
}

interface Props {
  onFilter: (params: ProximityFilterParams | null) => void;
  /** Currently active filter params, if any */
  value?: ProximityFilterParams | null;
}

const RADIUS_OPTIONS: { labelKey: string; value: number }[] = [
  { labelKey: 'proximity.radii.500m', value: 0.5 },
  { labelKey: 'proximity.radii.1km',  value: 1 },
  { labelKey: 'proximity.radii.2km',  value: 2 },
  { labelKey: 'proximity.radii.5km',  value: 5 },
  { labelKey: 'proximity.radii.10km', value: 10 },
];

/**
 * ProximityFilter — "Near Me" button with radius popover.
 *
 * Calls onFilter({ near_lat, near_lng, radius_km }) when active.
 * Calls onFilter(null) when cleared.
 */
export function ProximityFilter({ onFilter, value }: Props) {
  const { t } = useTranslation('common');
  const [isOpen, setIsOpen] = useState(false);
  const [pendingRadius, setPendingRadius] = useState<number>(2);
  const geo = useGeolocation();

  const isActive = value !== null && value !== undefined;

  const handleNearMePress = useCallback(() => {
    if (isActive) {
      // Already active — toggle off
      onFilter(null);
      return;
    }
    // If we already have a cached location, open the popover immediately
    if (geo.latitude !== null && geo.longitude !== null) {
      setIsOpen(true);
      return;
    }
    // Request location — popover opens automatically once we have coords
    geo.requestLocation();
    setIsOpen(true);
  }, [isActive, onFilter, geo]);

  const handleRadiusSelect = useCallback((radiusKm: number) => {
    if (geo.latitude !== null && geo.longitude !== null) {
      onFilter({ near_lat: geo.latitude, near_lng: geo.longitude, radius_km: radiusKm });
      setPendingRadius(radiusKm);
      setIsOpen(false);
    }
  }, [geo.latitude, geo.longitude, onFilter]);

  const handleClear = useCallback((e: React.MouseEvent) => {
    e.stopPropagation();
    onFilter(null);
  }, [onFilter]);

  // Button label
  let buttonLabel: string;
  if (isActive && value) {
    const match = RADIUS_OPTIONS.find((o) => o.value === value.radius_km);
    const radiusStr = match ? t(match.labelKey) : `${value.radius_km} km`;
    buttonLabel = t('proximity.radius', { radius: radiusStr });
  } else {
    buttonLabel = t('proximity.near_me');
  }

  return (
    <Popover
      isOpen={isOpen}
      onOpenChange={setIsOpen}
      placement="bottom-start"
    >
      <PopoverTrigger>
        <Button
          variant={isActive ? 'solid' : 'flat'}
          className={
            isActive
              ? 'bg-primary text-white min-h-[40px]'
              : 'bg-theme-elevated text-theme-primary min-h-[40px]'
          }
          startContent={
            geo.loading
              ? <Spinner size="sm" color={isActive ? 'white' : 'current'} />
              : <MapPin className="w-4 h-4" aria-hidden="true" />
          }
          endContent={
            isActive ? (
              <span
                role="button"
                aria-label={t('proximity.clear')}
                onClick={handleClear}
                className="ml-1 hover:opacity-70"
              >
                <X className="w-3.5 h-3.5" aria-hidden="true" />
              </span>
            ) : undefined
          }
          onPress={handleNearMePress}
          aria-pressed={isActive}
          aria-label={isActive ? t('proximity.clear') : t('proximity.near_me')}
          isDisabled={geo.loading}
        >
          {buttonLabel}
        </Button>
      </PopoverTrigger>

      <PopoverContent className="p-3 min-w-[180px] bg-content1 border border-theme-default shadow-lg">
        <div className="space-y-2">
          {geo.loading && (
            <div className="flex items-center gap-2 text-sm text-theme-muted py-1">
              <Spinner size="sm" />
              <span>{t('proximity.getting_location')}</span>
            </div>
          )}

          {geo.error && (
            <p className="text-sm text-danger py-1">
              {geo.error.includes('denied')
                ? t('proximity.location_denied')
                : t('proximity.location_error')}
            </p>
          )}

          {!geo.loading && !geo.error && geo.latitude !== null && (
            <>
              <p className="text-xs font-medium text-theme-muted pb-1">
                {t('proximity.near_me')}
              </p>
              {RADIUS_OPTIONS.map((opt) => (
                <button
                  key={opt.value}
                  type="button"
                  onClick={() => handleRadiusSelect(opt.value)}
                  className={`w-full text-left px-3 py-2 text-sm rounded-lg transition-colors ${
                    pendingRadius === opt.value && isActive
                      ? 'bg-primary/10 text-primary font-medium'
                      : 'text-theme-primary hover:bg-theme-hover'
                  }`}
                >
                  {t('proximity.radius', { radius: t(opt.labelKey) })}
                </button>
              ))}
            </>
          )}
        </div>
      </PopoverContent>
    </Popover>
  );
}
