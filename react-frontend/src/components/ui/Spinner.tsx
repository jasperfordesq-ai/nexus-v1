// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { type HTMLAttributes, type ReactNode } from 'react';
import {
  Spinner as HeroUISpinner,
  type SpinnerProps as HeroUISpinnerProps,
} from '@heroui/react';

type V2SpinnerColor =
  | 'default'
  | 'primary'
  | 'secondary'
  | 'success'
  | 'warning'
  | 'danger'
  | 'white'
  | 'current';

type V3SpinnerColor = NonNullable<HeroUISpinnerProps['color']>;

export type SpinnerProps = Omit<
  HeroUISpinnerProps,
  'color' | 'className'
> &
  Omit<HTMLAttributes<HTMLSpanElement>, 'color'> & {
    color?: V2SpinnerColor | V3SpinnerColor;
    label?: ReactNode;
    labelColor?: V2SpinnerColor | V3SpinnerColor;
    variant?: 'default' | 'simple' | 'gradient' | 'wave' | 'dots' | 'spinner';
    className?: string;
    classNames?: {
      base?: string;
      wrapper?: string;
      circle1?: string;
      circle2?: string;
      label?: string;
    };
  };

function mapColor(color?: SpinnerProps['color']): V3SpinnerColor {
  switch (color) {
    case 'primary':
      return 'accent';
    case 'success':
    case 'warning':
    case 'danger':
    case 'accent':
    case 'current':
      return color;
    default:
      return 'current';
  }
}

function combineClasses(...classes: Array<string | undefined>): string | undefined {
  const className = classes.filter(Boolean).join(' ');

  return className || undefined;
}

export function Spinner({
  'aria-label': ariaLabel,
  className,
  classNames,
  color,
  label,
  labelColor,
  variant: _variant,
  ...props
}: SpinnerProps) {
  const spinner = (
    <HeroUISpinner
      aria-label={ariaLabel ?? (label ? String(label) : undefined)}
      className={combineClasses(classNames?.wrapper, classNames?.circle1, classNames?.circle2, className)}
      color={mapColor(color)}
      {...props}
    />
  );

  if (!label) {
    return spinner;
  }

  return (
    <span className={combineClasses('inline-flex flex-col items-center gap-2', classNames?.base)}>
      {spinner}
      <span className={combineClasses('text-sm text-current', classNames?.label)} style={{ color: labelColor ? undefined : undefined }}>
        {label}
      </span>
    </span>
  );
}
