// Copyright (C) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { type ComponentPropsWithoutRef, type ReactNode } from 'react';
import { Skeleton as HeroUISkeleton } from '@heroui-v3/react';

type HeroUISkeletonProps = ComponentPropsWithoutRef<typeof HeroUISkeleton>;

export type SkeletonProps = Omit<HeroUISkeletonProps, 'children'> & {
  children?: ReactNode;
  classNames?: {
    base?: string;
    content?: string;
  };
  disableAnimation?: boolean;
  isLoaded?: boolean;
};

function combineClasses(...classes: Array<string | false | undefined>): string | undefined {
  const className = classes.filter(Boolean).join(' ');

  return className || undefined;
}

export function Skeleton({
  animationType,
  children,
  className,
  classNames,
  disableAnimation,
  isLoaded,
  ...props
}: SkeletonProps) {
  if (isLoaded) {
    return <>{children}</>;
  }

  return (
    <HeroUISkeleton
      animationType={disableAnimation ? 'none' : animationType}
      className={combineClasses(classNames?.base, classNames?.content, className)}
      {...props}
    />
  );
}
