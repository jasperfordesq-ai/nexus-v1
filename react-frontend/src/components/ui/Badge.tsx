// Copyright (C) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { ComponentProps, ReactNode } from 'react';
import { Badge as HeroUIBadge } from '@heroui-v3/react';

type HeroUIBadgeProps = ComponentProps<typeof HeroUIBadge>;
type HeroUIBadgeAnchorProps = ComponentProps<typeof HeroUIBadge.Anchor>;

type BadgeClassNames = {
  base?: string;
  badge?: string;
  label?: string;
};

export type BadgeProps = Omit<HeroUIBadgeProps, 'children' | 'color' | 'variant'> & {
  children?: ReactNode;
  classNames?: BadgeClassNames;
  color?: HeroUIBadgeProps['color'] | 'primary' | 'secondary';
  content?: ReactNode;
  disableAnimation?: boolean;
  disableOutline?: boolean;
  isDot?: boolean;
  isInvisible?: boolean;
  isOneChar?: boolean;
  shape?: 'circle' | 'rectangle' | string;
  showOutline?: boolean;
  variant?: HeroUIBadgeProps['variant'] | 'solid' | 'flat' | 'faded' | 'shadow';
};

function combineClasses(...classes: Array<string | false | undefined>): string | undefined {
  const className = classes.filter(Boolean).join(' ');

  return className || undefined;
}

function mapColor(color?: BadgeProps['color']): HeroUIBadgeProps['color'] {
  switch (color) {
    case 'primary':
      return 'accent';
    case 'secondary':
      return 'default';
    default:
      return color;
  }
}

function mapVariant(variant?: BadgeProps['variant']): HeroUIBadgeProps['variant'] {
  switch (variant) {
    case 'flat':
      return 'soft';
    case 'faded':
      return 'secondary';
    case 'shadow':
    case 'solid':
      return 'primary';
    default:
      return variant;
  }
}

export function Badge({
  children,
  className,
  classNames,
  color,
  content,
  disableAnimation: _disableAnimation,
  disableOutline: _disableOutline,
  isDot,
  isInvisible,
  isOneChar: _isOneChar,
  shape: _shape,
  showOutline,
  variant,
  ...props
}: BadgeProps) {
  const badgeContent = isDot ? undefined : content;
  const anchorProps: Partial<HeroUIBadgeAnchorProps> = {};

  return (
    <HeroUIBadge.Anchor className={classNames?.base} {...anchorProps}>
      {children}
      <HeroUIBadge
        {...props}
        className={combineClasses(showOutline && 'border-2 border-background', isInvisible && 'opacity-0 pointer-events-none', classNames?.badge, className)}
        color={mapColor(color)}
        data-invisible={isInvisible ? 'true' : undefined}
        variant={mapVariant(variant)}
      >
        {badgeContent == null || badgeContent === '' ? null : (
          <HeroUIBadge.Label className={classNames?.label}>{badgeContent}</HeroUIBadge.Label>
        )}
      </HeroUIBadge>
    </HeroUIBadge.Anchor>
  );
}
