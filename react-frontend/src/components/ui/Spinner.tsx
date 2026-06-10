// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { type HTMLAttributes, type ReactNode } from 'react';
import {
  Spinner as HeroUISpinner,
  type SpinnerProps as HeroUISpinnerProps,
} from '@heroui/react';
import { useTranslation } from 'react-i18next';

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
  labelColor: _labelColor,
  variant: _variant,
  ...props
}: SpinnerProps) {
  const { t } = useTranslation('common');
  const resolvedAriaLabel = ariaLabel ?? (label ? String(label) : t('loading'));

  const spinner = (
    <HeroUISpinner
      aria-label={resolvedAriaLabel}
      className={combineClasses(classNames?.wrapper, classNames?.circle1, classNames?.circle2, className)}
      color={mapColor(color)}
      {...props}
    />
  );

  if (!label) {
    return (
      <span role="status" aria-label={resolvedAriaLabel}>
        {spinner}
      </span>
    );
  }

  return (
    <span role="status" aria-label={resolvedAriaLabel} className={combineClasses('inline-flex flex-col items-center gap-2', classNames?.base)}>
      {spinner}
      <span className={combineClasses('text-sm text-current', classNames?.label)}>
        {label}
      </span>
    </span>
  );
}
