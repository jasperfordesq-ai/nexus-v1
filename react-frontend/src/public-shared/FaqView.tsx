// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

'use client';

/**
 * Shared FAQ view — the SINGLE source of truth for the public FAQ page, rendered
 * by BOTH the Vite SPA and the Next.js SSR public frontend. It is presentational:
 * it imports nothing runtime-specific (no react-router / @/contexts / @/lib/api /
 * react-i18next / react-helmet / the @/components/ui barrel). The translator and
 * the Link/hrefFor come from the injected PublicRuntime port, so the exact same
 * markup renders in either host. A dual-render parity test guards against drift.
 *
 * Differences from the original SPA FaqPage (all host-neutral, same end-state):
 * - GlassCard -> HeroUI <Card> + the shared .glass-card classes (identical DOM/CSS).
 * - useTranslation/useTenant -> the PublicRuntime port (t, Link, hrefFor).
 * - Framer-motion wrappers removed -> renders the settled (visible) state, which
 *   is what SSR/no-JS needs and is visually identical once the SPA fade completes.
 */

import { useMemo, useState } from 'react';
import { Card } from '@heroui/react';
import {
  CircleHelp,
  Handshake,
  Rocket,
  ShieldCheck,
  Trophy,
  Wallet,
  type LucideIcon,
} from 'lucide-react';

import { Accordion, AccordionItem } from './ui/Accordion';
import { usePublicRuntime } from './runtime';

interface FaqItem {
  question: string;
  answer: React.ReactNode;
}

interface FaqCategory {
  title: string;
  icon: LucideIcon;
  items: FaqItem[];
}

function GlassCard({ className = '', children }: { className?: string; children: React.ReactNode }) {
  return <Card className={`backdrop-blur-lg glass-card ${className}`}>{children}</Card>;
}

export function FaqView() {
  const { t, Link, hrefFor } = usePublicRuntime();
  const [searchQuery, setSearchQuery] = useState('');

  const categories: FaqCategory[] = useMemo(
    () => [
      {
        title: t('faq.categories.getting_started.title'),
        icon: Rocket,
        items: [
          {
            question: t('faq.categories.getting_started.q1.question'),
            answer: (
              <>
                <p>{t('faq.categories.getting_started.q1.answer_p1')}</p>
                <p>{t('faq.categories.getting_started.q1.answer_p2')}</p>
              </>
            ),
          },
          {
            question: t('faq.categories.getting_started.q2.question'),
            answer: (
              <>
                <p>{t('faq.categories.getting_started.q2.answer_p1')}</p>
                <p>{t('faq.categories.getting_started.q2.answer_p2')}</p>
              </>
            ),
          },
          {
            question: t('faq.categories.getting_started.q3.question'),
            answer: (
              <>
                <p>{t('faq.categories.getting_started.q3.answer_intro')}</p>
                <ul>
                  <li><strong>{t('faq.categories.getting_started.q3.step1_bold')}</strong> &mdash; {t('faq.categories.getting_started.q3.step1_text')}</li>
                  <li><strong>{t('faq.categories.getting_started.q3.step2_bold')}</strong> &mdash; {t('faq.categories.getting_started.q3.step2_text')}</li>
                  <li><strong>{t('faq.categories.getting_started.q3.step3_bold')}</strong> &mdash; {t('faq.categories.getting_started.q3.step3_text')}</li>
                  <li><strong>{t('faq.categories.getting_started.q3.step4_bold')}</strong> &mdash; {t('faq.categories.getting_started.q3.step4_text')}</li>
                  <li><strong>{t('faq.categories.getting_started.q3.step5_bold')}</strong> &mdash; {t('faq.categories.getting_started.q3.step5_text')}</li>
                </ul>
              </>
            ),
          },
          {
            question: t('faq.categories.getting_started.q4.question'),
            answer: (
              <>
                <p>{t('faq.categories.getting_started.q4.answer_intro')}</p>
                <ul>
                  <li>{t('faq.categories.getting_started.q4.skill1')}</li>
                  <li>{t('faq.categories.getting_started.q4.skill2')}</li>
                  <li>{t('faq.categories.getting_started.q4.skill3')}</li>
                  <li>{t('faq.categories.getting_started.q4.skill4')}</li>
                  <li>{t('faq.categories.getting_started.q4.skill5')}</li>
                  <li>{t('faq.categories.getting_started.q4.skill6')}</li>
                  <li>{t('faq.categories.getting_started.q4.skill7')}</li>
                </ul>
                <p>{t('faq.categories.getting_started.q4.answer_outro')}</p>
              </>
            ),
          },
        ],
      },
      {
        title: t('faq.categories.time_credits.title'),
        icon: Wallet,
        items: [
          {
            question: t('faq.categories.time_credits.q1.question'),
            answer: (
              <>
                <p>{t('faq.categories.time_credits.q1.answer_p1')}</p>
                <p>{t('faq.categories.time_credits.q1.answer_p2')}</p>
              </>
            ),
          },
          {
            question: t('faq.categories.time_credits.q2.question'),
            answer: (
              <>
                <p>{t('faq.categories.time_credits.q2.answer_intro')}</p>
                <ul>
                  <li><strong>{t('faq.categories.time_credits.q2.way1_bold')}</strong> {t('faq.categories.time_credits.q2.way1_text')}</li>
                  <li><strong>{t('faq.categories.time_credits.q2.way2_bold')}</strong> {t('faq.categories.time_credits.q2.way2_text')}</li>
                  <li><strong>{t('faq.categories.time_credits.q2.way3_bold')}</strong> {t('faq.categories.time_credits.q2.way3_text')}</li>
                  <li><strong>{t('faq.categories.time_credits.q2.way4_bold')}</strong> {t('faq.categories.time_credits.q2.way4_text')}</li>
                </ul>
              </>
            ),
          },
          {
            question: t('faq.categories.time_credits.q3.question'),
            answer: (
              <>
                <p>{t('faq.categories.time_credits.q3.answer_intro')}</p>
                <ul>
                  <li>{t('faq.categories.time_credits.q3.option1')}</li>
                  <li>{t('faq.categories.time_credits.q3.option2')}</li>
                </ul>
                <p>{t('faq.categories.time_credits.q3.answer_link_before')}<Link href={hrefFor('/wallet')} className="text-indigo-500 dark:text-indigo-400 hover:underline">{t('faq.categories.time_credits.q3.wallet_link')}</Link>{t('faq.categories.time_credits.q3.answer_link_after')}</p>
              </>
            ),
          },
          {
            question: t('faq.categories.time_credits.q4.question'),
            answer: <p>{t('faq.categories.time_credits.q4.answer')}</p>,
          },
          {
            question: t('faq.categories.time_credits.q5.question'),
            answer: (
              <>
                <p>{t('faq.categories.time_credits.q5.answer_intro')}</p>
                <ul>
                  <li>{t('faq.categories.time_credits.q5.step1')}</li>
                  <li>{t('faq.categories.time_credits.q5.step2')}</li>
                </ul>
                <p>{t('faq.categories.time_credits.q5.answer_outro')}</p>
              </>
            ),
          },
        ],
      },
      {
        title: t('faq.categories.exchanges_safety.title'),
        icon: Handshake,
        items: [
          {
            question: t('faq.categories.exchanges_safety.q1.question'),
            answer: (
              <>
                <p>{t('faq.categories.exchanges_safety.q1.answer_intro')}</p>
                <ul>
                  <li>{t('faq.categories.exchanges_safety.q1.step1')}</li>
                  <li>{t('faq.categories.exchanges_safety.q1.step2')}</li>
                  <li>{t('faq.categories.exchanges_safety.q1.step3')}</li>
                  <li>{t('faq.categories.exchanges_safety.q1.step4')}</li>
                  <li>{t('faq.categories.exchanges_safety.q1.step5')}</li>
                  <li>{t('faq.categories.exchanges_safety.q1.step6')}</li>
                </ul>
              </>
            ),
          },
          {
            question: t('faq.categories.exchanges_safety.q2.question'),
            answer: (
              <>
                <p>{t('faq.categories.exchanges_safety.q2.answer_intro')}</p>
                <ul>
                  <li>{t('faq.categories.exchanges_safety.q2.tip1')}</li>
                  <li>{t('faq.categories.exchanges_safety.q2.tip2')}</li>
                  <li>{t('faq.categories.exchanges_safety.q2.tip3')}</li>
                  <li>{t('faq.categories.exchanges_safety.q2.tip4')}</li>
                  <li>{t('faq.categories.exchanges_safety.q2.tip5')}</li>
                </ul>
                <p>{t('faq.categories.exchanges_safety.q2.answer_outro')}</p>
              </>
            ),
          },
          {
            question: t('faq.categories.exchanges_safety.q3.question'),
            answer: (
              <>
                <p>{t('faq.categories.exchanges_safety.q3.answer_intro')}</p>
                <ul>
                  <li>{t('faq.categories.exchanges_safety.q3.step1')}</li>
                  <li>{t('faq.categories.exchanges_safety.q3.step2')}</li>
                  <li>{t('faq.categories.exchanges_safety.q3.step3')}</li>
                </ul>
                <p>{t('faq.categories.exchanges_safety.q3.answer_outro')}</p>
              </>
            ),
          },
          {
            question: t('faq.categories.exchanges_safety.q4.question'),
            answer: <p>{t('faq.categories.exchanges_safety.q4.answer')}</p>,
          },
        ],
      },
      {
        title: t('faq.categories.badges_rewards.title'),
        icon: Trophy,
        items: [
          {
            question: t('faq.categories.badges_rewards.q1.question'),
            answer: (
              <>
                <p>{t('faq.categories.badges_rewards.q1.answer_intro')}</p>
                <ul>
                  <li><strong>{t('faq.categories.badges_rewards.q1.item1_bold')}</strong> &mdash; {t('faq.categories.badges_rewards.q1.item1_text')}</li>
                  <li><strong>{t('faq.categories.badges_rewards.q1.item2_bold')}</strong> &mdash; {t('faq.categories.badges_rewards.q1.item2_text')}</li>
                  <li><strong>{t('faq.categories.badges_rewards.q1.item3_bold')}</strong> &mdash; {t('faq.categories.badges_rewards.q1.item3_text')}</li>
                  <li><strong>{t('faq.categories.badges_rewards.q1.item4_bold')}</strong> &mdash; {t('faq.categories.badges_rewards.q1.item4_text')}</li>
                </ul>
              </>
            ),
          },
          {
            question: t('faq.categories.badges_rewards.q2.question'),
            answer: (
              <>
                <p>{t('faq.categories.badges_rewards.q2.answer_intro')}</p>
                <ul>
                  <li>{t('faq.categories.badges_rewards.q2.activity1')}</li>
                  <li>{t('faq.categories.badges_rewards.q2.activity2')}</li>
                  <li>{t('faq.categories.badges_rewards.q2.activity3')}</li>
                  <li>{t('faq.categories.badges_rewards.q2.activity4')}</li>
                  <li>{t('faq.categories.badges_rewards.q2.activity5')}</li>
                  <li>{t('faq.categories.badges_rewards.q2.activity6')}</li>
                  <li>{t('faq.categories.badges_rewards.q2.activity7')}</li>
                </ul>
              </>
            ),
          },
          {
            question: t('faq.categories.badges_rewards.q3.question'),
            answer: <p>{t('faq.categories.badges_rewards.q3.answer_before_link')}<Link href={hrefFor('/settings')} className="text-indigo-500 dark:text-indigo-400 hover:underline">{t('faq.categories.badges_rewards.q3.settings_link')}</Link>{t('faq.categories.badges_rewards.q3.answer_after_link')}</p>,
          },
        ],
      },
      {
        title: t('faq.categories.account_privacy.title'),
        icon: ShieldCheck,
        items: [
          {
            question: t('faq.categories.account_privacy.q1.question'),
            answer: <p>{t('faq.categories.account_privacy.q1.answer_before_link')}<Link href={hrefFor('/settings')} className="text-indigo-500 dark:text-indigo-400 hover:underline">{t('faq.categories.account_privacy.q1.settings_link')}</Link>{t('faq.categories.account_privacy.q1.answer_after_link')}</p>,
          },
          {
            question: t('faq.categories.account_privacy.q2.question'),
            answer: (
              <>
                <p>{t('faq.categories.account_privacy.q2.answer_intro')}</p>
                <ul>
                  <li>{t('faq.categories.account_privacy.q2.item1')}</li>
                  <li>{t('faq.categories.account_privacy.q2.item2')}</li>
                  <li>{t('faq.categories.account_privacy.q2.item3')}</li>
                </ul>
                <p>{t('faq.categories.account_privacy.q2.answer_link_before')}<Link href={hrefFor('/settings')} className="text-indigo-500 dark:text-indigo-400 hover:underline">{t('faq.categories.account_privacy.q2.settings_link')}</Link>{t('faq.categories.account_privacy.q2.answer_link_after')}</p>
              </>
            ),
          },
          {
            question: t('faq.categories.account_privacy.q3.question'),
            answer: (
              <>
                <p>{t('faq.categories.account_privacy.q3.answer_intro')}</p>
                <ul>
                  <li>{t('faq.categories.account_privacy.q3.step1')}</li>
                  <li>{t('faq.categories.account_privacy.q3.step2')}</li>
                  <li>{t('faq.categories.account_privacy.q3.step3')}</li>
                </ul>
                <p>{t('faq.categories.account_privacy.q3.answer_outro')}</p>
              </>
            ),
          },
          {
            question: t('faq.categories.account_privacy.q4.question'),
            answer: (
              <>
                <p>{t('faq.categories.account_privacy.q4.answer_intro')}</p>
                <ul>
                  <li>{t('faq.categories.account_privacy.q4.item1')}</li>
                  <li>{t('faq.categories.account_privacy.q4.item2')}</li>
                  <li>{t('faq.categories.account_privacy.q4.item3')}</li>
                  <li>{t('faq.categories.account_privacy.q4.item4')}</li>
                </ul>
              </>
            ),
          },
        ],
      },
    ],
    [t, hrefFor, Link],
  );

  const faqSchema = useMemo(() => {
    const answerKeys: Record<string, string[]> = {
      getting_started: ['answer_p1', 'answer_p1', 'answer_intro', 'answer_intro'],
      time_credits: ['answer_p1', 'answer_intro', 'answer_intro', 'answer', 'answer_intro'],
      exchanges_safety: ['answer_intro', 'answer_intro', 'answer_intro', 'answer'],
      badges_rewards: ['answer_intro', 'answer_intro', 'answer_before_link'],
      account_privacy: ['answer_before_link', 'answer_intro', 'answer_intro', 'answer_intro'],
    };
    const catKeys = ['getting_started', 'time_credits', 'exchanges_safety', 'badges_rewards', 'account_privacy'];

    const mainEntity = catKeys.flatMap((catKey, ci) =>
      (categories[ci]?.items || []).map((item, qi) => ({
        '@type': 'Question' as const,
        name: item.question,
        acceptedAnswer: {
          '@type': 'Answer' as const,
          text: t(`faq.categories.${catKey}.q${qi + 1}.${answerKeys[catKey]?.[qi] || 'answer_p1'}`),
        },
      })),
    );

    return { '@context': 'https://schema.org', '@type': 'FAQPage', mainEntity };
  }, [categories, t]);

  const filteredCategories = useMemo(() => {
    if (!searchQuery.trim()) return categories;
    const q = searchQuery.toLowerCase();
    return categories
      .map((cat) => ({ ...cat, items: cat.items.filter((item) => item.question.toLowerCase().includes(q)) }))
      .filter((cat) => cat.items.length > 0);
  }, [categories, searchQuery]);

  return (
    <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(faqSchema).replace(/</g, '\\u003c') }} />
      <div className="space-y-6">
        <GlassCard className="p-8 sm:p-10">
          <div className="flex items-center gap-3 mb-4">
            <div className="p-2 rounded-xl bg-indigo-500/10">
              <CircleHelp aria-hidden="true" className="w-8 h-8 text-indigo-500 dark:text-indigo-400" />
            </div>
            <h1 className="text-3xl font-bold text-theme-primary">{t('faq.title')}</h1>
          </div>
          <p className="text-theme-muted mb-6">
            {t('faq.subtitle_before_link')}{' '}
            <Link href={hrefFor('/help')} className="text-indigo-500 dark:text-indigo-400 hover:underline">
              {t('faq.help_center_link')}
            </Link>{' '}
            {t('faq.subtitle_after_link')}
          </p>
          <input
            type="search"
            placeholder={t('faq.search_placeholder')}
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            aria-label={t('faq.search_placeholder')}
            className="glass-input w-full rounded-xl px-4 py-2.5 bg-theme-subtle/10 border border-theme-default text-theme-primary"
          />
        </GlassCard>

        {filteredCategories.length === 0 ? (
          <GlassCard className="p-8 text-center">
            <p className="text-theme-muted">{t('faq.no_results')}</p>
          </GlassCard>
        ) : (
          filteredCategories.map((cat) => {
            const Icon = cat.icon;
            return (
              <GlassCard className="p-6" key={cat.title}>
                <div className="flex items-center gap-3 mb-4">
                  <div className="p-1.5 rounded-lg bg-indigo-500/10">
                    <Icon aria-hidden="true" className="w-5 h-5 text-indigo-500 dark:text-indigo-400" />
                  </div>
                  <h2 className="text-lg font-semibold text-theme-primary">{cat.title}</h2>
                </div>
                <Accordion
                  selectionMode="multiple"
                  variant="surface"
                  itemClasses={{
                    base: 'border-theme-default/50',
                    title: 'text-theme-primary font-medium text-sm',
                    trigger: 'px-4 py-3 hover:bg-theme-subtle/5 data-[hover=true]:bg-theme-subtle/5',
                    content: 'px-4 pb-4 text-theme-muted text-sm leading-relaxed [&_p]:mb-3 [&_p:last-child]:mb-0 [&_ul]:ml-5 [&_ul]:list-disc [&_ul]:mb-3 [&_li]:mb-1.5',
                    indicator: 'text-theme-subtle',
                  }}
                >
                  {cat.items.map((item) => (
                    <AccordionItem key={`${cat.title}-${item.question}`} id={`${cat.title}-${item.question}`} aria-label={item.question} title={item.question}>
                      {item.answer}
                    </AccordionItem>
                  ))}
                </Accordion>
              </GlassCard>
            );
          })
        )}

        <GlassCard className="p-8 text-center">
          <h3 className="text-xl font-semibold text-theme-primary mb-2">{t('faq.cta_title')}</h3>
          <p className="text-theme-muted mb-4">{t('faq.cta_description')}</p>
          <Link
            href={hrefFor('/contact')}
            className="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-indigo-500 text-white font-medium hover:bg-indigo-600 transition-colors"
          >
            {t('faq.cta_button')}
          </Link>
        </GlassCard>
      </div>
    </div>
  );
}
