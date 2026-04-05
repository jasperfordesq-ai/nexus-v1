// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Landing Page Configuration Types
 *
 * Defines the structure for per-tenant customizable landing pages.
 * Supports three levels:
 *   1. Content-only: Override text, titles, descriptions
 *   2. Section toggle: Enable/disable/reorder sections
 *   3. Full builder: Admin UI for visual editing
 */

// ─────────────────────────────────────────────────────────────────────────────
// Section Types
// ─────────────────────────────────────────────────────────────────────────────

export type LandingSectionType =
  | 'hero'
  | 'feature_pills'
  | 'stats'
  | 'how_it_works'
  | 'core_values'
  | 'cta';

/** Icon identifiers supported in the landing page builder (map to Lucide icons) */
export type LandingIconId =
  | 'clock'
  | 'users'
  | 'zap'
  | 'user-plus'
  | 'search'
  | 'handshake'
  | 'coins'
  | 'heart'
  | 'shield'
  | 'star'
  | 'globe'
  | 'book-open'
  | 'message-circle'
  | 'award'
  | 'target'
  | 'thumbs-up';

// ─────────────────────────��───────────────────────────────────────────────────
// Section Content Interfaces
// ���──────────────────��─────────────────────────────────────────────────────────

export interface HeroContent {
  badge_text?: string;
  headline_1?: string;
  headline_2?: string;
  subheadline?: string;
  cta_primary_text?: string;
  cta_primary_link?: string;
  cta_secondary_text?: string;
  cta_secondary_link?: string;
}

export interface FeaturePillItem {
  icon?: LandingIconId;
  title: string;
  description: string;
}

export interface FeaturePillsContent {
  items?: FeaturePillItem[];
}

export interface StatsContent {
  /** If true, show live platform stats from API. If false, section is hidden. */
  show_live_stats?: boolean;
}

export interface HowItWorksStep {
  icon?: LandingIconId;
  title: string;
  description: string;
}

export interface HowItWorksContent {
  title?: string;
  subtitle?: string;
  steps?: HowItWorksStep[];
}

export interface CoreValue {
  title: string;
  description: string;
}

export interface CoreValuesContent {
  title?: string;
  subtitle?: string;
  values?: CoreValue[];
}

export interface CtaContent {
  title?: string;
  description?: string;
  button_text?: string;
  button_link?: string;
}

// ────────────────────────────────────────────────────────────��────────────────
// Section Definition
// ────────────────────────���──────────────────────────────��─────────────────────

/** Map section types to their content shapes */
export interface SectionContentMap {
  hero: HeroContent;
  feature_pills: FeaturePillsContent;
  stats: StatsContent;
  how_it_works: HowItWorksContent;
  core_values: CoreValuesContent;
  cta: CtaContent;
}

export interface LandingSection<T extends LandingSectionType = LandingSectionType> {
  id: string;
  type: T;
  enabled: boolean;
  order: number;
  content?: SectionContentMap[T];
}

// ────────────────────────────────────────────────────────────��────────────────
// Landing Page Config (top-level)
// ──��───────────────────────────────────────────────���──────────────────────────

export interface LandingPageConfig {
  sections: LandingSection[];
}

// ──────────────────────────────────���──────────────────────────────────────────
// Defaults
// ───────────────────────────────────────────────────���─────────────────────────

export const DEFAULT_LANDING_PAGE_CONFIG: LandingPageConfig = {
  sections: [
    {
      id: 'hero',
      type: 'hero',
      enabled: true,
      order: 0,
    },
    {
      id: 'feature_pills',
      type: 'feature_pills',
      enabled: true,
      order: 1,
    },
    {
      id: 'stats',
      type: 'stats',
      enabled: true,
      order: 2,
    },
    {
      id: 'how_it_works',
      type: 'how_it_works',
      enabled: true,
      order: 3,
    },
    {
      id: 'core_values',
      type: 'core_values',
      enabled: true,
      order: 4,
    },
    {
      id: 'cta',
      type: 'cta',
      enabled: true,
      order: 5,
    },
  ],
};
