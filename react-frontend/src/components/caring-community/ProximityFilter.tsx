// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useTranslation } from 'react-i18next';
import { Button, Select, SelectItem } from '@heroui/react';
import MapPin from 'lucide-react/icons/map-pin';
import { useAuth, useToast } from '@/contexts';

export interface ProximityFilterProps {
  radiusKm: number | null;
  onRadiusChange: (km: number | null) => void;
  className?: string;
}

const RADIUS_OPTIONS = [5, 10, 25, 50, 100] as const;
const DEFAULT_RADIUS = 25;

export function ProximityFilter({ radiusKm, onRadiusChange, className }: ProximityFilterProps) {
  const { t } = useTranslation('common');
  const { user } = useAuth();
  const toast = useToast();

  const isActive = radiusKm !== null;

  function handleToggle() {
    if (isActive) {
      onRadiusChange(null);
      return;
    }
    if (user?.latitude == null || user?.longitude == null) {
      toast.error(t('members.near_me_no_location'));
      return;
    }
    onRadiusChange(DEFAULT_RADIUS);
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
            onRadiusChange(Number(val) || DEFAULT_RADIUS);
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
