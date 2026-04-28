// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useTranslation } from 'react-i18next';
import { Button, Spinner } from '@heroui/react';
import MapPin from 'lucide-react/icons/map-pin';
import { useProximity } from '@/hooks/useProximity';

export interface ProximityFilterProps {
  radiusKm: number | null;
  onRadiusChange: (km: number | null) => void;
  className?: string;
}

const RADIUS_OPTIONS: Array<{ value: number | null; labelKey: string }> = [
  { value: null, labelKey: 'proximity.off' },
  { value: 1, labelKey: 'proximity.radius_option' },
  { value: 2, labelKey: 'proximity.radius_option' },
  { value: 5, labelKey: 'proximity.radius_option' },
  { value: 10, labelKey: 'proximity.radius_option' },
];

export function ProximityFilter({ radiusKm, onRadiusChange, className }: ProximityFilterProps) {
  const { t } = useTranslation('common');
  const { position, isLoading, error, requestLocation } = useProximity();

  function handleSelect(value: number | null) {
    onRadiusChange(value);
    if (value !== null && position === null) {
      requestLocation();
    }
  }

  const isActive = radiusKm !== null;

  return (
    <div className={['flex flex-col gap-2', className].filter(Boolean).join(' ')}>
      {/* Row: icon + label + pill options */}
      <div className="flex flex-wrap items-center gap-2">
        <div className="flex items-center gap-1.5 text-theme-muted">
          <MapPin className="w-4 h-4 shrink-0" aria-hidden="true" />
          <span className="text-sm font-medium text-theme-secondary">{t('proximity.label')}</span>
        </div>

        <div
          className="flex flex-wrap gap-1"
          role="radiogroup"
          aria-label={t('proximity.label')}
        >
          {RADIUS_OPTIONS.map(({ value, labelKey }) => {
            const isSelected = value === radiusKm;
            const label =
              value === null
                ? t('proximity.off')
                : t('proximity.radius_option', { km: value });

            return (
              <Button
                key={String(value)}
                size="sm"
                variant={isSelected ? 'solid' : 'flat'}
                color={isSelected ? 'primary' : 'default'}
                className={[
                  'text-xs px-3 py-1 min-w-0 h-auto rounded-full',
                  isSelected
                    ? 'text-white'
                    : 'text-theme-muted hover:text-theme-primary bg-theme-elevated hover:bg-theme-hover',
                ].join(' ')}
                onPress={() => handleSelect(value)}
                role="radio"
                aria-checked={isSelected}
              >
                {label}
              </Button>
            );
          })}
        </div>
      </div>

      {/* Status line — only shown when a radius is active */}
      {isActive && (
        <div className="flex items-center gap-2 text-xs text-theme-muted pl-0.5">
          {isLoading ? (
            <>
              <Spinner size="sm" color="current" className="w-3.5 h-3.5" />
              <span>{t('proximity.requesting')}</span>
            </>
          ) : error ? (
            <span className="text-warning">{error}</span>
          ) : position !== null ? (
            <span className="text-teal-600 dark:text-teal-400 font-medium">
              {t('proximity.active_label', { km: radiusKm })}
            </span>
          ) : null}
        </div>
      )}
    </div>
  );
}

export default ProximityFilter;
