// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

// Proximity filter for Listings, Events, and Volunteering pages.
// Coordinates come from the user's profile — no browser geolocation popup.

import { useTranslation } from 'react-i18next';
import { Button, Select, SelectItem } from '@heroui/react';
import MapPin from 'lucide-react/icons/map-pin';
import { useAuth, useToast } from '@/contexts';

export interface ProximityFilterParams {
  near_lat: number;
  near_lng: number;
  radius_km: number;
}

interface Props {
  value: ProximityFilterParams | null;
  onFilter: (params: ProximityFilterParams | null) => void;
  className?: string;
}

const RADIUS_OPTIONS = [5, 10, 25, 50, 100] as const;
const DEFAULT_RADIUS = 25;

export function ProximityFilter({ value, onFilter, className }: Props) {
  const { t } = useTranslation('common');
  const { user } = useAuth();
  const toast = useToast();

  const isActive = value !== null;
  const radiusKm = value?.radius_km ?? DEFAULT_RADIUS;

  function handleToggle() {
    if (isActive) {
      onFilter(null);
      return;
    }
    if (user?.latitude == null || user?.longitude == null) {
      toast.error(t('members.near_me_no_location'));
      return;
    }
    onFilter({ near_lat: user.latitude, near_lng: user.longitude, radius_km: DEFAULT_RADIUS });
  }

  function handleRadiusChange(km: number) {
    if (user?.latitude == null || user?.longitude == null) return;
    onFilter({ near_lat: user.latitude, near_lng: user.longitude, radius_km: km });
  }

  return (
    <div className={['flex flex-wrap items-center gap-2', className].filter(Boolean).join(' ')}>
      <Button
        size="sm"
        variant={isActive ? 'solid' : 'flat'}
        className={isActive
          ? 'bg-emerald-600 text-white shadow-sm'
          : 'bg-theme-elevated text-theme-primary hover:bg-emerald-500/10 hover:text-emerald-600'}
        startContent={<MapPin className="w-4 h-4" aria-hidden="true" />}
        onPress={handleToggle}
        aria-pressed={isActive}
      >
        {t('members.near_me')}
      </Button>

      {isActive && (
        <Select
          aria-label={t('members.radius_label')}
          selectedKeys={[String(radiusKm)]}
          disallowEmptySelection
          onSelectionChange={(keys) => {
            const val = keys instanceof Set ? ([...keys][0] as string) : String(DEFAULT_RADIUS);
            handleRadiusChange(Number(val) || DEFAULT_RADIUS);
          }}
          className="w-28"
          classNames={{
            trigger: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
            value: 'text-theme-primary',
          }}
        >
          {RADIUS_OPTIONS.map((km) => (
            <SelectItem key={String(km)}>{t(`radius_${km}`)}</SelectItem>
          ))}
        </Select>
      )}
    </div>
  );
}

export default ProximityFilter;
