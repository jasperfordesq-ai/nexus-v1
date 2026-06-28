// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { ReactNode } from 'react';

import { createTranslator } from '../src/lib/i18n';

export default function NotFound(): ReactNode {
  const t = createTranslator('en');

  return (
    <main className="hero-band">
      <p className="eyebrow">{t('brand.platformName')}</p>
      <h1>{t('pages.notFound.title')}</h1>
      <p>{t('pages.notFound.lead')}</p>
    </main>
  );
}
