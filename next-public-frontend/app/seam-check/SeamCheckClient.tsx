// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

'use client';

/**
 * Client shell for the seam-proof probe. This is the TEMPLATE pattern for every
 * Next public page: the Server Component fetches/serializes data and passes only
 * serializable props (here: locale); this client boundary constructs the
 * PublicRuntime (which holds functions + the Link component — not RSC-
 * serializable) and provides it to the shared view. The tree still SSRs to HTML.
 */

import type { ReactNode } from 'react';

import { PublicSharedProbe } from '@nexus/public-shared/PublicSharedProbe';
import {
  PublicRuntimeProvider,
  type PublicRuntime,
  type PublicRuntimeLinkProps,
} from '@nexus/public-shared/runtime';

function ProbeLink({ href, className, children }: PublicRuntimeLinkProps): ReactNode {
  return (
    <a className={className} href={href}>
      {children}
    </a>
  );
}

export function SeamCheckClient({ locale }: { locale: string }): ReactNode {
  const runtime: PublicRuntime = {
    t: (key) => key,
    Link: ProbeLink,
    hrefFor: (path) => path,
    isAuthenticated: false,
    locale,
    branding: null,
    hasFeature: () => false,
    hasModule: () => false,
  };

  return (
    <PublicRuntimeProvider runtime={runtime}>
      <PublicSharedProbe />
    </PublicRuntimeProvider>
  );
}
