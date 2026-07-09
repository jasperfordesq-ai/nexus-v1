// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { ComponentProps, ElementType } from 'react';
import { ScrollShadow as HeroUIScrollShadow } from '@heroui/react/scroll-shadow';

type HeroUIScrollShadowProps = ComponentProps<typeof HeroUIScrollShadow>;

export type ScrollShadowProps = HeroUIScrollShadowProps & {
  as?: ElementType;
};

export function ScrollShadow({ as, role, ...props }: ScrollShadowProps) {
  return (
    <HeroUIScrollShadow
      {...props}
      role={role ?? (as === 'nav' ? 'navigation' : undefined)}
    />
  );
}
