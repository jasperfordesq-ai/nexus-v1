// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

'use client';

/**
 * Seam-proof probe. The smallest REAL shared component: it pulls the translator
 * and Link from the PublicRuntime port and imports a HeroUI primitive DIRECTLY
 * (never via the @/components/ui barrel). Used only to prove the cross-app
 * shared-component mechanism resolves and builds in both the Vite SPA and the
 * Next.js Turbopack standalone build. It will be superseded by real shared
 * views (FaqView, PublicPageHero, entity cards) and removed.
 */

import type { ReactNode } from 'react';
import { Chip } from '@heroui/react';

import { usePublicRuntime } from './runtime';

export function PublicSharedProbe(): ReactNode {
  const { t, Link, hrefFor, locale } = usePublicRuntime();

  return (
    <div className="flex items-center gap-2" data-nexus-shared-probe={locale}>
      <Chip color="accent" size="sm" variant="soft">
        {t('faq.title')}
      </Chip>
      <Link href={hrefFor('/about')} className="text-sm underline">
        {t('faq.help_center_link')}
      </Link>
    </div>
  );
}
