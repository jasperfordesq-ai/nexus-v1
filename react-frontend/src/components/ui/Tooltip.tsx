// Copyright (C) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import {
  type ComponentPropsWithoutRef,
  type ReactNode,
} from 'react';
import { Tooltip as HeroUITooltip } from '@heroui/react';

type HeroUITooltipProps = ComponentPropsWithoutRef<typeof HeroUITooltip>;
type HeroUITooltipContentProps = ComponentPropsWithoutRef<typeof HeroUITooltip.Content>;
type LegacyPlacement = HeroUITooltipContentProps['placement'] | string;

export type TooltipProps = Omit<HeroUITooltipProps, 'children' | 'className'> & {
  children?: ReactNode;
  className?: string;
  classNames?: {
    base?: string;
    trigger?: string;
    content?: string;
    arrow?: string;
  };
  color?: 'default' | 'primary' | 'secondary' | 'success' | 'warning' | 'danger' | string;
  content?: ReactNode;
  offset?: HeroUITooltipContentProps['offset'];
  placement?: LegacyPlacement;
  radius?: string;
  shadow?: string;
  showArrow?: boolean;
  size?: string;
};

function combineClasses(...classes: Array<string | false | undefined>): string | undefined {
  const className = classes.filter(Boolean).join(' ');

  return className || undefined;
}

function mapColor(color?: TooltipProps['color']): string | undefined {
  switch (color) {
    case 'danger':
      return 'bg-danger text-danger-foreground';
    case 'success':
      return 'bg-success text-success-foreground';
    case 'warning':
      return 'bg-warning text-warning-foreground';
    case 'primary':
      return 'bg-accent text-accent-foreground';
    case 'secondary':
      return 'bg-default text-foreground';
    default:
      return undefined;
  }
}

function normalizePlacement(placement?: LegacyPlacement): HeroUITooltipContentProps['placement'] | undefined {
  return placement?.replace('-', ' ') as HeroUITooltipContentProps['placement'] | undefined;
}

export function Tooltip({
  children,
  className,
  classNames,
  color,
  content,
  delay,
  offset,
  placement,
  radius: _radius,
  shadow: _shadow,
  showArrow,
  size: _size,
  ...props
}: TooltipProps) {
  return (
    <HeroUITooltip
      delay={delay ?? 0}
      {...props}
    >
      <HeroUITooltip.Trigger>
        {children}
      </HeroUITooltip.Trigger>
      <HeroUITooltip.Content
        className={combineClasses(mapColor(color), classNames?.base, classNames?.content, className)}
        offset={offset}
        placement={normalizePlacement(placement)}
        showArrow={showArrow}
      >
        {showArrow ? <HeroUITooltip.Arrow className={classNames?.arrow} /> : null}
        {content}
      </HeroUITooltip.Content>
    </HeroUITooltip>
  );
}
