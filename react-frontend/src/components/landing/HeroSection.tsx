// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button } from '@heroui/react';
import ArrowRight from 'lucide-react/icons/arrow-right';
import ChevronDown from 'lucide-react/icons/chevron-down';
import { useTranslation } from 'react-i18next';
import { useTenant, useAuth } from '@/contexts';
import type { HeroContent } from '@/types';

const fadeInUp = {
  initial: { opacity: 0, y: 30 },
  animate: { opacity: 1, y: 0, transition: { duration: 0.5 } },
};

const staggerContainer = {
  animate: {
    transition: {
      staggerChildren: 0.15,
    },
  },
};

interface HeroSectionProps {
  content?: HeroContent;
}

export function HeroSection({ content }: HeroSectionProps) {
  const { t } = useTranslation('public');
  const { branding, tenantPath } = useTenant();
  const { isAuthenticated } = useAuth();

  const badgeText = content?.badge_text || t('home.badge');
  const headline1 = content?.headline_1 || t('home.headline_1');
  const headline2 = content?.headline_2 || t('home.headline_2');
  const subheadline = content?.subheadline || t('home.subheadline', { name: branding.name });
  const ctaPrimaryText = content?.cta_primary_text || t('home.cta_get_started');
  const ctaPrimaryLink = content?.cta_primary_link || '/register';
  const ctaSecondaryText = content?.cta_secondary_text || t('home.cta_learn_more');
  const ctaSecondaryLink = content?.cta_secondary_link || '/about';
  const ctaFeedText = t('home.cta_feed');

  const scrollToSection = () => {
    document.getElementById('features')?.scrollIntoView({ behavior: 'smooth' });
  };

  return (
    <section aria-labelledby="hero-heading" className="relative py-20 sm:py-32 px-4 sm:px-6 lg:px-8">
      <div className="max-w-7xl mx-auto">
        <motion.div
          className="text-center"
          initial="initial"
          animate="animate"
          variants={staggerContainer}
        >
          {/* Badge */}
          <motion.div variants={fadeInUp} className="mb-6">
            <span className="inline-flex items-center gap-2 px-4 py-2 rounded-full glass-card backdrop-blur-lg text-sm text-theme-muted">
              <span className="text-indigo-500 dark:text-indigo-400">✨</span>
              <span>{badgeText}</span>
            </span>
          </motion.div>

          {/* Headline */}
          <motion.h1
            id="hero-heading"
            variants={fadeInUp}
            className="text-3xl sm:text-4xl md:text-5xl lg:text-6xl xl:text-7xl font-bold tracking-tight"
          >
            <span className="text-theme-primary">{headline1}</span>
            <br />
            <span className="text-gradient">{headline2}</span>
          </motion.h1>

          {/* Subheadline */}
          <motion.p
            variants={fadeInUp}
            className="mt-6 text-lg sm:text-xl text-theme-muted max-w-2xl mx-auto"
          >
            {subheadline}
          </motion.p>

          {/* CTAs */}
          <motion.div
            variants={fadeInUp}
            className="mt-10 flex flex-col sm:flex-row gap-4 justify-center"
          >
            {isAuthenticated ? (
              <Button
                as={Link}
                to={tenantPath('/feed')}
                size="lg"
                className="w-full sm:w-auto bg-gradient-to-r from-indigo-500 via-purple-500 to-indigo-600 text-white font-semibold px-8 shadow-lg shadow-indigo-500/25 hover:shadow-indigo-500/40 transition-shadow"
                endContent={<ArrowRight className="w-5 h-5" aria-hidden="true" />}
              >
                {ctaFeedText}
              </Button>
            ) : (
              <>
                <Button
                  as={Link}
                  to={tenantPath(ctaPrimaryLink)}
                  size="lg"
                  className="w-full sm:w-auto bg-gradient-to-r from-indigo-500 via-purple-500 to-indigo-600 text-white font-semibold px-8 shadow-lg shadow-indigo-500/25 hover:shadow-indigo-500/40 transition-shadow"
                  endContent={<ArrowRight className="w-5 h-5" aria-hidden="true" />}
                >
                  {ctaPrimaryText}
                </Button>
                <Button
                  as={Link}
                  to={tenantPath(ctaSecondaryLink)}
                  size="lg"
                  variant="bordered"
                  className="w-full sm:w-auto border-theme-default text-theme-primary hover:bg-theme-hover"
                >
                  {ctaSecondaryText}
                </Button>
              </>
            )}
          </motion.div>
        </motion.div>
      </div>

      {/* Scroll Indicator */}
      <motion.div
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        transition={{ delay: 1.2 }}
        className="flex justify-center mt-12"
      >
        <Button
          variant="light"
          className="text-theme-subtle hover:text-theme-primary motion-safe:animate-bounce"
          onPress={scrollToSection}
          isIconOnly
          aria-label={t('home.scroll_down')}
        >
          <ChevronDown className="w-6 h-6" aria-hidden="true" />
        </Button>
      </motion.div>
    </section>
  );
}
