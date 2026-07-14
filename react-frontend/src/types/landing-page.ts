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
  | 'audience_cards'
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

export interface AudienceCard {
  /** Client-only stable React key; injected on load, stripped before save by cleanConfig(). */
  _key?: string;
  icon?: LandingIconId;
  title: string;
  description: string;
  cta_label: string;
  target_url: string;
}

export interface AudienceCardsContent {
  title?: string;
  subtitle?: string;
  cards?: AudienceCard[];
}

export interface FeaturePillItem {
  /** Client-only stable React key; injected on load, stripped before save by cleanConfig(). */
  _key?: string;
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
  /** Client-only stable React key; injected on load, stripped before save by cleanConfig(). */
  _key?: string;
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
  /** Client-only stable React key; injected on load, stripped before save by cleanConfig(). */
  _key?: string;
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
  audience_cards: AudienceCardsContent;
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

const DEFAULT_AUDIENCE_CARD_BLUEPRINTS = [
  {
    icon: 'user-plus',
    titleKey: 'home.audience_cards.defaults.new_here.title',
    descriptionKey: 'home.audience_cards.defaults.new_here.description',
    ctaKey: 'home.audience_cards.defaults.new_here.cta',
    target_url: '/about',
  },
  {
    icon: 'handshake',
    titleKey: 'home.audience_cards.defaults.exchange.title',
    descriptionKey: 'home.audience_cards.defaults.exchange.description',
    ctaKey: 'home.audience_cards.defaults.exchange.cta',
    target_url: '/listings',
  },
  {
    icon: 'shield',
    titleKey: 'home.audience_cards.defaults.partner.title',
    descriptionKey: 'home.audience_cards.defaults.partner.description',
    ctaKey: 'home.audience_cards.defaults.partner.cta',
    target_url: '/contact',
  },
] as const;

type DefaultAudienceCardKey = (typeof DEFAULT_AUDIENCE_CARD_BLUEPRINTS)[number][
  'titleKey' | 'descriptionKey' | 'ctaKey'
];

/** Build locale-aware fallback content without freezing copy at module load. */
export function createDefaultAudienceCards(
  translate: (key: DefaultAudienceCardKey) => string,
): AudienceCard[] {
  return DEFAULT_AUDIENCE_CARD_BLUEPRINTS.map((card) => ({
    icon: card.icon,
    title: translate(card.titleKey),
    description: translate(card.descriptionKey),
    cta_label: translate(card.ctaKey),
    target_url: card.target_url,
  }));
}

export const DEFAULT_LANDING_PAGE_CONFIG: LandingPageConfig = {
  sections: [
    {
      id: 'hero',
      type: 'hero',
      enabled: true,
      order: 0,
    },
    {
      id: 'audience_cards',
      type: 'audience_cards',
      enabled: true,
      order: 1,
      // Empty means "use locale-aware defaults" in the renderer/editor.
      content: { cards: [] },
    },
    {
      id: 'feature_pills',
      type: 'feature_pills',
      enabled: true,
      order: 2,
    },
    {
      id: 'stats',
      type: 'stats',
      enabled: true,
      order: 3,
    },
    {
      id: 'how_it_works',
      type: 'how_it_works',
      enabled: true,
      order: 4,
    },
    {
      id: 'core_values',
      type: 'core_values',
      enabled: true,
      order: 5,
    },
    {
      id: 'cta',
      type: 'cta',
      enabled: true,
      order: 6,
    },
  ],
};
