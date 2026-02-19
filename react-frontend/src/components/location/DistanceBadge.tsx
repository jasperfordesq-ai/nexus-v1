// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Navigation } from 'lucide-react';

export interface DistanceBadgeProps {
  distanceKm: number;
  className?: string;
}

export function DistanceBadge({ distanceKm, className = '' }: DistanceBadgeProps) {
  const formatted =
    distanceKm < 1
      ? `${Math.round(distanceKm * 1000)}m`
      : distanceKm < 10
        ? `${distanceKm.toFixed(1)}km`
        : `${Math.round(distanceKm)}km`;

  return (
    <span className={`inline-flex items-center gap-1 text-xs text-theme-subtle ${className}`}>
      <Navigation className="w-3 h-3" />
      {formatted}
    </span>
  );
}
