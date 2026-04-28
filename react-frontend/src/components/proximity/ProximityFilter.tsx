// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

// Compatibility shim — adapts the canonical caring-community/ProximityFilter
// (radiusKm/onRadiusChange API) to the older value/onFilter API used by
// ListingsPage, EventsPage, and VolunteeringPage.

import { useEffect, useState } from 'react';
import { ProximityFilter as CanonicalProximityFilter } from '@/components/caring-community/ProximityFilter';
import { useProximity } from '@/hooks/useProximity';

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

export function ProximityFilter({ value, onFilter, className }: Props) {
  const { position } = useProximity();
  const [radiusKm, setRadiusKm] = useState<number | null>(value?.radius_km ?? null);

  useEffect(() => {
    if (radiusKm === null) {
      onFilter(null);
    } else if (position) {
      onFilter({ near_lat: position.lat, near_lng: position.lng, radius_km: radiusKm });
    }
    // When radius is set but position not yet available, wait — effect will re-run when position arrives
  }, [radiusKm, position]); // eslint-disable-line react-hooks/exhaustive-deps

  return (
    <CanonicalProximityFilter
      radiusKm={radiusKm}
      onRadiusChange={setRadiusKm}
      className={className}
    />
  );
}

export default ProximityFilter;
