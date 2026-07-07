// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useTranslation } from 'react-i18next';
import MapPin from 'lucide-react/icons/map-pin';
import Globe from 'lucide-react/icons/globe';
import { Chip } from '@/components/ui/Chip';

export interface DistanceChipProps {
  distanceKm?: number | null;
  isRemote?: boolean;
  className?: string;
}

/**
 * Shows a "X km" chip when a distance is known, a "Remote" chip when the
 * match is explicitly remote, or nothing when neither applies.
 */
export function DistanceChip({ distanceKm, isRemote, className }: DistanceChipProps) {
  const { t } = useTranslation('matches');

  if (distanceKm != null) {
    return (
      <Chip size="sm" variant="flat" className={className} startContent={<MapPin className="w-3 h-3" aria-hidden="true" />}>
        {t('card.distance_km', { value: distanceKm.toFixed(1) })}
      </Chip>
    );
  }

  if (isRemote) {
    return (
      <Chip size="sm" variant="flat" className={className} startContent={<Globe className="w-3 h-3" aria-hidden="true" />}>
        {t('card.remote')}
      </Chip>
    );
  }

  return null;
}

export default DistanceChip;
