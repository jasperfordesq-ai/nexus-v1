// Copyright (C) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { ComponentProps } from 'react';
import { ButtonGroup as HeroUIButtonGroup } from '@heroui-v3/react';

type HeroUIButtonGroupProps = ComponentProps<typeof HeroUIButtonGroup>;

export type ButtonGroupProps = Omit<HeroUIButtonGroupProps, 'variant'> & {
  color?: string;
  disableAnimation?: boolean;
  disableRipple?: boolean;
  radius?: string;
  variant?: HeroUIButtonGroupProps['variant'] | 'flat' | 'solid' | 'bordered' | 'light' | 'faded' | 'shadow';
};

function mapVariant(variant: ButtonGroupProps['variant']): HeroUIButtonGroupProps['variant'] {
  switch (variant) {
    case 'flat':
    case 'faded':
      return 'secondary';
    case 'bordered':
      return 'outline';
    case 'light':
      return 'tertiary';
    case 'shadow':
    case 'solid':
      return 'primary';
    default:
      return variant;
  }
}

export function ButtonGroup({
  color: _color,
  disableAnimation: _disableAnimation,
  disableRipple: _disableRipple,
  radius: _radius,
  variant,
  ...props
}: ButtonGroupProps) {
  return <HeroUIButtonGroup {...props} variant={mapVariant(variant)} />;
}

ButtonGroup.Separator = HeroUIButtonGroup.Separator;
