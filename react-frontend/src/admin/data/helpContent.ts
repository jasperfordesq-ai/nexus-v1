// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { TFunction } from 'i18next';

export interface HelpStep {
  label: string;
  detail?: string;
}

export interface HelpArticle {
  title: string;
  summary: string;
  steps?: HelpStep[];
  tips?: string[];
  caution?: string;
  relatedPaths?: Array<{ label: string; path: string }>;
}

/**
 * Read the contextual admin-help registry from the active i18next locale.
 * Keeping article copy in locale JSON prevents English-only admin content from
 * bypassing the normal translation and fallback pipeline.
 */
export function getHelpContent(t: TFunction): Record<string, HelpArticle> {
  const articles = t('articles', { ns: 'admin_help', returnObjects: true });
  return articles && typeof articles === 'object' && !Array.isArray(articles)
    ? articles as Record<string, HelpArticle>
    : {};
}
