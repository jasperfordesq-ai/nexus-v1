// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { motion } from 'framer-motion';
import { UserPlus, Search, Handshake, Coins } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { getIcon } from './iconMap';
import type { LucideIcon } from 'lucide-react';
import type { HowItWorksContent } from '@/types';

interface ResolvedStep {
  Icon: LucideIcon;
  title: string;
  description: string;
  color: string;
}

const FALLBACK_META = { icon: UserPlus, color: 'from-indigo-500 to-blue-500' } as const;
const defaultStepMeta: { icon: LucideIcon; color: string }[] = [
  FALLBACK_META,
  { icon: Search, color: 'from-purple-500 to-pink-500' },
  { icon: Handshake, color: 'from-cyan-500 to-teal-500' },
  { icon: Coins, color: 'from-amber-500 to-orange-500' },
];

interface HowItWorksSectionProps {
  content?: HowItWorksContent;
}

export function HowItWorksSection({ content }: HowItWorksSectionProps) {
  const { t } = useTranslation('public');

  const title = content?.title || t('home.how_it_works.title');
  const subtitle = content?.subtitle || t('home.how_it_works.subtitle');

  const steps: ResolvedStep[] =
    content?.steps && content.steps.length > 0
      ? content.steps.map((step, index) => {
          const meta = defaultStepMeta[index] ?? FALLBACK_META;
          return {
            Icon: getIcon(step.icon, meta.icon),
            title: step.title,
            description: step.description,
            color: meta.color,
          };
        })
      : [
          {
            Icon: UserPlus,
            title: t('home.how_it_works.steps.0.title', 'Sign Up Free'),
            description: t('home.how_it_works.steps.0.description', 'Create your profile in minutes and list the skills you can offer.'),
            color: 'from-indigo-500 to-blue-500',
          },
          {
            Icon: Search,
            title: t('home.how_it_works.steps.1.title', 'Browse & Connect'),
            description: t('home.how_it_works.steps.1.description', 'Find services you need and connect with local community members.'),
            color: 'from-purple-500 to-pink-500',
          },
          {
            Icon: Handshake,
            title: t('home.how_it_works.steps.2.title', 'Exchange Services'),
            description: t('home.how_it_works.steps.2.description', 'Arrange skill exchanges and help each other out.'),
            color: 'from-cyan-500 to-teal-500',
          },
          {
            Icon: Coins,
            title: t('home.how_it_works.steps.3.title', 'Earn Credits'),
            description: t('home.how_it_works.steps.3.description', 'Get one time credit for every hour you give. Spend them freely.'),
            color: 'from-amber-500 to-orange-500',
          },
        ];

  return (
    <section id="features" aria-labelledby="how-it-works-heading" className="py-20 px-4 sm:px-6 lg:px-8">
      <div className="max-w-5xl mx-auto">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true }}
          transition={{ duration: 0.5 }}
          className="text-center mb-12"
        >
          <h2 id="how-it-works-heading" className="text-2xl sm:text-3xl font-bold text-theme-primary mb-3">
            {title}
          </h2>
          <p className="text-theme-muted max-w-lg mx-auto">
            {subtitle}
          </p>
        </motion.div>

        <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
          {steps.map((step, index) => (
            <motion.div
              key={`step-${index}`}
              initial={{ opacity: 0, y: 30 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
              transition={{ delay: index * 0.1 }}
            >
              <GlassCard className="p-6 h-full text-center relative group hover:scale-[1.02] transition-transform">
                <div className="absolute top-2 right-2 w-8 h-8 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white text-sm font-bold shadow-lg">
                  {index + 1}
                </div>
                <div className={`inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-br ${step.color} mb-4`}>
                  <step.Icon className="w-7 h-7 text-white" aria-hidden="true" />
                </div>
                <h3 className="font-semibold text-theme-primary mb-2">{step.title}</h3>
                <p className="text-sm text-theme-muted">{step.description}</p>
              </GlassCard>
            </motion.div>
          ))}
        </div>
      </div>
    </section>
  );
}
