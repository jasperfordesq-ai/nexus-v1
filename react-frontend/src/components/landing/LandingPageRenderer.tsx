// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * LandingPageRenderer
 *
 * Reads the tenant's landing page configuration and renders the enabled
 * sections in the configured order. Falls back to defaults when no
 * custom config is provided.
 *
 * Wraps all sections in <MotionConfig reducedMotion="user"> so that
 * Framer Motion automatically respects the user's prefers-reduced-motion
 * system preference — all animations become instant when set.
 */

import { useMemo } from 'react';
import { MotionConfig } from 'framer-motion';
import { useTenant } from '@/contexts';
import type {
  LandingSection,
  HeroContent,
  FeaturePillsContent,
  StatsContent,
  HowItWorksContent,
  CoreValuesContent,
  CtaContent,
} from '@/types';
import { HeroSection } from './HeroSection';
import { FeaturePillsSection } from './FeaturePillsSection';
import { StatsSection } from './StatsSection';
import { HowItWorksSection } from './HowItWorksSection';
import { CoreValuesSection } from './CoreValuesSection';
import { CtaSection } from './CtaSection';

/**
 * Renders a single landing section based on its type and content.
 */
function RenderSection({ section }: { section: LandingSection }) {
  switch (section.type) {
    case 'hero':
      return <HeroSection content={section.content as HeroContent | undefined} />;
    case 'feature_pills':
      return <FeaturePillsSection content={section.content as FeaturePillsContent | undefined} />;
    case 'stats':
      return <StatsSection content={section.content as StatsContent | undefined} />;
    case 'how_it_works':
      return <HowItWorksSection content={section.content as HowItWorksContent | undefined} />;
    case 'core_values':
      return <CoreValuesSection content={section.content as CoreValuesContent | undefined} />;
    case 'cta':
      return <CtaSection content={section.content as CtaContent | undefined} />;
    default:
      if (import.meta.env.DEV) {
        console.warn(`[LandingPageRenderer] Unknown section type: "${(section as { type: string }).type}"`);
      }
      return null;
  }
}

export function LandingPageRenderer() {
  const { landingPageConfig } = useTenant();

  const sortedSections = useMemo(() => {
    return [...landingPageConfig.sections]
      .filter((s) => s.enabled)
      .sort((a, b) => a.order - b.order);
  }, [landingPageConfig.sections]);

  return (
    <MotionConfig reducedMotion="user">
      {sortedSections.map((section) => (
        <RenderSection key={section.id} section={section} />
      ))}
    </MotionConfig>
  );
}
