// Copyright (C) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { ComponentProps, ReactNode } from 'react';
import { Link as HeroUILink } from '@heroui/react';

type HeroUILinkProps = ComponentProps<typeof HeroUILink>;

export type LinkProps = Omit<HeroUILinkProps, 'children' | 'className'> & {
  anchorIcon?: ReactNode;
  children?: ReactNode;
  className?: string;
  color?: string;
  disableAnimation?: boolean;
  isBlock?: boolean;
  isExternal?: boolean;
  showAnchorIcon?: boolean;
  size?: 'sm' | 'md' | 'lg';
  underline?: string;
};

function combineClasses(...classes: Array<string | false | undefined>): string | undefined {
  const className = classes.filter(Boolean).join(' ');

  return className || undefined;
}

export function Link({
  anchorIcon,
  children,
  className,
  color: _color,
  disableAnimation: _disableAnimation,
  isBlock,
  isExternal,
  rel,
  showAnchorIcon,
  size: _size,
  target,
  underline,
  ...props
}: LinkProps) {
  return (
    <HeroUILink
      {...props}
      className={combineClasses(isBlock && 'block', underline === 'hover' && 'hover:underline', underline === 'always' && 'underline', className)}
      rel={rel ?? (isExternal ? 'noopener noreferrer' : undefined)}
      target={target ?? (isExternal ? '_blank' : undefined)}
    >
      {children}
      {showAnchorIcon ? <HeroUILink.Icon>{anchorIcon}</HeroUILink.Icon> : null}
    </HeroUILink>
  );
}

Link.Icon = HeroUILink.Icon;
