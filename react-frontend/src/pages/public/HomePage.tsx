// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Home Page - Per-tenant customizable landing page
 *
 * Delegates to LandingPageRenderer which reads the tenant's landing page
 * configuration (sections, content, ordering) and renders accordingly.
 * Falls back to sensible defaults when no custom config is set.
 */

import { useTranslation } from 'react-i18next';
import { PageMeta } from '@/components/seo';
import { LandingPageRenderer } from '@/components/landing';

export function HomePage() {
  const { t } = useTranslation('public');

  return (
    <>
      {/* No usePageTitle here: it sets document.title via useEffect and races
          Helmet, which left prerendered snapshots with a bare "Home" title.
          PageMeta (Helmet) handles <title> as part of the React tree, which
          the prerenderer captures correctly. */}
      <PageMeta
        title={t('home.meta_title', 'Community Timebanking Platform')}
        description={t('home.meta_description')}
        keywords={t('home.meta_keywords')}
      />
      <div className="-mx-3 sm:-mx-4 md:-mx-6 lg:-mx-8 -my-4 sm:-my-6 md:-my-8 overflow-x-hidden">
        <LandingPageRenderer />
      </div>
    </>
  );
}

export default HomePage;
