// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import {
  Surface as HeroUISurface,
  type SurfaceProps as HeroUISurfaceProps,
} from '@heroui/react';

type ProjectSurfaceVariant = 'elevated' | 'outlined' | 'filled' | 'ghost';
type V3SurfaceVariant = NonNullable<HeroUISurfaceProps['variant']>;

export type SurfaceProps = Omit<HeroUISurfaceProps, 'variant'> & {
  variant?: HeroUISurfaceProps['variant'] | ProjectSurfaceVariant;
};

function mapVariant(variant?: SurfaceProps['variant']): V3SurfaceVariant {
  switch (variant) {
    case 'elevated':
    case 'filled':
      return 'secondary';
    case 'outlined':
    case 'ghost':
      return 'transparent';
    default:
      return variant ?? 'default';
  }
}

export function Surface({ variant, ...props }: SurfaceProps) {
  return <HeroUISurface variant={mapVariant(variant)} {...props} />;
}
