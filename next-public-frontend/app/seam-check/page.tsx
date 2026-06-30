// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * TEMPORARY seam-proof route. Server Component → passes serializable props to a
 * client shell that builds the PublicRuntime and renders the shared
 * PublicSharedProbe (which physically lives in react-frontend/src/public-shared
 * and resolves via the @nexus/public-shared alias). Proves the cross-app
 * shared-component mechanism end to end: alias resolution, Turbopack standalone
 * bundling, the RSC server→client boundary, and a single React instance at
 * runtime. Remove once a real shared view (FaqView) is wired the same way. The
 * app is inert/unserved, so this route reaches no traffic.
 */

import type { ReactNode } from 'react';

import { SeamCheckClient } from './SeamCheckClient';

export default function SeamCheckPage(): ReactNode {
  return (
    <main className="p-8">
      <SeamCheckClient locale="en" />
    </main>
  );
}
