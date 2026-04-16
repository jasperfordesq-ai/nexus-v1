// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { motion } from 'framer-motion';
import { useTranslation } from 'react-i18next';
import type { CoreValuesContent } from '@/types';

const FALLBACK_GRADIENT = 'from-indigo-500 to-blue-500';
const defaultGradients = [
  FALLBACK_GRADIENT,
  'from-purple-500 to-pink-500',
  'from-cyan-500 to-teal-500',
];

interface ResolvedValue {
  title: string;
  description: string;
  gradient: string;
}

interface CoreValuesSectionProps {
  content?: CoreValuesContent;
}

export function CoreValuesSection({ content }: CoreValuesSectionProps) {
  const { t } = useTranslation('public');

  const title = content?.title || t('home.why_timebanking.title');
  const subtitle = content?.subtitle || t('home.why_timebanking.subtitle');

  const values: ResolvedValue[] =
    content?.values && content.values.length > 0
      ? content.values.map((value, index) => ({
          title: value.title,
          description: value.description,
          gradient: defaultGradients[index % defaultGradients.length] ?? FALLBACK_GRADIENT,
        }))
      : [
          {
            title: t('home.why_timebanking.values.0.title', 'Equal Value'),
            description: t(
              'home.why_timebanking.values.0.description',
              "Every hour is worth the same. Whether you're teaching piano or mowing lawns, your time has equal value.",
            ),
            gradient: 'from-indigo-500 to-blue-500',
          },
          {
            title: t('home.why_timebanking.values.1.title', 'Build Trust'),
            description: t(
              'home.why_timebanking.values.1.description',
              'Reviews and ratings help you find reliable service providers and build your reputation in the community.',
            ),
            gradient: 'from-purple-500 to-pink-500',
          },
          {
            title: t('home.why_timebanking.values.2.title', 'Stay Local'),
            description: t(
              'home.why_timebanking.values.2.description',
              'Connect with neighbors and strengthen your local community through meaningful skill exchanges.',
            ),
            gradient: 'from-cyan-500 to-teal-500',
          },
        ];

  return (
    <section aria-labelledby="core-values-heading" className="py-20 px-4 sm:px-6 lg:px-8 bg-gradient-to-b from-transparent via-indigo-500/5 to-transparent">
      <div className="max-w-7xl mx-auto">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true }}
          transition={{ duration: 0.5 }}
          className="text-center mb-12"
        >
          <h2 id="core-values-heading" className="text-2xl sm:text-3xl font-bold text-theme-primary mb-3">
            {title}
          </h2>
          <p className="text-theme-muted max-w-lg mx-auto">
            {subtitle}
          </p>
        </motion.div>

        <motion.div
          initial={{ opacity: 0, y: 20 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true }}
          transition={{ duration: 0.6 }}
          className="grid md:grid-cols-3 gap-4 sm:gap-6 md:gap-8"
        >
          {values.map((value, index) => (
            <motion.div
              key={`value-${index}`}
              initial={{ opacity: 0, y: 30 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
              transition={{ delay: index * 0.1 }}
              className="relative group"
            >
              <div className="p-5 sm:p-8 rounded-2xl glass-card-hover backdrop-blur-lg">
                <div
                  className={`w-12 h-12 rounded-xl bg-gradient-to-r ${value.gradient} flex items-center justify-center mb-6`}
                  aria-hidden="true"
                >
                  <span className="text-2xl font-bold text-white">
                    {index + 1}
                  </span>
                </div>
                <h3 className="text-xl font-semibold text-theme-primary mb-3">
                  {value.title}
                </h3>
                <p className="text-theme-muted">{value.description}</p>
              </div>
            </motion.div>
          ))}
        </motion.div>
      </div>
    </section>
  );
}
