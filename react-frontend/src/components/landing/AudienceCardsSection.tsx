// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import ArrowRight from 'lucide-react/icons/arrow-right';
import UserPlus from 'lucide-react/icons/user-plus';
import { useTranslation } from 'react-i18next';
import { useTenant } from '@/contexts';
import { getIcon } from './iconMap';
import type { AudienceCardsContent } from '@/types';
import { DEFAULT_AUDIENCE_CARDS } from '@/types/landing-page';

interface AudienceCardsSectionProps {
  content?: AudienceCardsContent;
}

function isExternal(url: string): boolean {
  return /^https?:\/\//i.test(url);
}

export function AudienceCardsSection({ content }: AudienceCardsSectionProps) {
  const { t } = useTranslation('public');
  const { tenantPath } = useTenant();

  const cards = content?.cards && content.cards.length > 0
    ? content.cards
    : DEFAULT_AUDIENCE_CARDS;

  if (cards.length === 0) return null;

  const title = content?.title ?? t('home.audience_cards.title', 'Where would you like to start?');
  const subtitle = content?.subtitle;

  const colCount = Math.min(cards.length, 4);
  const gridCols =
    colCount === 1 ? 'sm:grid-cols-1' :
    colCount === 2 ? 'sm:grid-cols-2' :
    colCount === 3 ? 'sm:grid-cols-2 lg:grid-cols-3' :
    'sm:grid-cols-2 lg:grid-cols-4';

  return (
    <section
      aria-labelledby="audience-cards-heading"
      className="py-12 sm:py-16 px-4 sm:px-6 lg:px-8"
    >
      <div className="max-w-6xl mx-auto">
        <div className="text-center mb-8 sm:mb-10">
          <h2
            id="audience-cards-heading"
            className="text-2xl sm:text-3xl font-bold text-theme-primary"
          >
            {title}
          </h2>
          {subtitle && (
            <p className="mt-2 text-theme-muted max-w-2xl mx-auto">
              {subtitle}
            </p>
          )}
        </div>
        <div className={`grid grid-cols-1 ${gridCols} gap-4`}>
          {cards.map((card, index) => {
            const Icon = getIcon(card.icon, UserPlus);
            const href = isExternal(card.target_url)
              ? card.target_url
              : tenantPath(card.target_url || '/');
            const linkProps = isExternal(card.target_url)
              ? { href, rel: 'noopener noreferrer', target: '_blank' as const }
              : null;
            const cardInner = (
              <>
                <div className="flex-shrink-0 w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500/15 to-purple-500/15 flex items-center justify-center mb-4">
                  <Icon className="w-6 h-6 text-indigo-500 dark:text-indigo-400" aria-hidden="true" />
                </div>
                <h3 className="text-lg font-semibold text-theme-primary mb-2">
                  {card.title}
                </h3>
                <p className="text-sm text-theme-muted mb-4 flex-1">
                  {card.description}
                </p>
                <span className="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 dark:text-indigo-400">
                  {card.cta_label}
                  <ArrowRight className="w-4 h-4" aria-hidden="true" />
                </span>
              </>
            );
            const className =
              'group flex flex-col h-full p-5 sm:p-6 rounded-2xl border border-theme-default bg-theme-elevated transition-colors hover:border-theme-accent hover:bg-theme-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primary)] focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--color-background)]';
            return (
              <motion.div
                key={`audience-card-${index}`}
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true }}
                transition={{ delay: index * 0.05 }}
              >
                {linkProps ? (
                  <a {...linkProps} className={className}>
                    {cardInner}
                  </a>
                ) : (
                  <Link to={href} className={className}>
                    {cardInner}
                  </Link>
                )}
              </motion.div>
            );
          })}
        </div>
      </div>
    </section>
  );
}
