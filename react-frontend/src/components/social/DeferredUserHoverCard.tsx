// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { lazy, Suspense, useCallback, useState, type ReactNode } from 'react';

const LazyUserHoverCard = lazy(() => import('./UserHoverCard').then((module) => ({
  default: module.UserHoverCard,
})));

interface DeferredUserHoverCardProps {
  userId: number;
  children: ReactNode;
}

function supportsHover(): boolean {
  if (typeof window === 'undefined') return false;
  return !('ontouchstart' in window) && window.matchMedia('(hover: hover)').matches;
}

export function DeferredUserHoverCard({ userId, children }: DeferredUserHoverCardProps) {
  const [shouldLoad, setShouldLoad] = useState(false);
  const [openOnMount, setOpenOnMount] = useState(false);

  const handleMouseEnter = useCallback(() => {
    if (!supportsHover()) return;
    setOpenOnMount(true);
    setShouldLoad(true);
  }, []);

  if (!shouldLoad) {
    return (
      <span onMouseEnter={handleMouseEnter} className="inline-flex">
        {children}
      </span>
    );
  }

  return (
    <Suspense
      fallback={(
        <span onMouseEnter={handleMouseEnter} className="inline-flex">
          {children}
        </span>
      )}
    >
      <LazyUserHoverCard userId={userId} openOnMount={openOnMount}>
        {children}
      </LazyUserHoverCard>
    </Suspense>
  );
}

export default DeferredUserHoverCard;
