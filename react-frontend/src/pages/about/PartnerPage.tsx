// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Partner With Us Page - Partnership opportunities, SROI impact, and strategic growth
 *
 * Tenant-specific "About" page for the hOUR Timebank community.
 * Visual sections:
 * 1. Hero with SROI headline
 * 2. Addressing the Funding Gap
 * 3. Impact cards (3-card grid)
 * 4. Partnership Opportunities
 * 5. Learn More sidebar-style links
 * 6. CTA card
 */

import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button } from '@heroui/react';
import {
  Handshake,
  ArrowRight,
  TrendingUp,
  ShieldCheck,
  Rocket,
  Building2,
  HeartHandshake,
  Laptop,
  GraduationCap,
  BookOpen,
  FileText,
  Users,
  Target,
  Lightbulb,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { PageMeta } from '@/components/seo';
import { Breadcrumbs } from '@/components/navigation/Breadcrumbs';
import { useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { RelatedPages } from './RelatedPages';

/* ───────────────────────── Data ───────────────────────── */

const impactCards = [
  {
    icon: TrendingUp,
    title: 'Exceptional Social Value',
    highlight: '\u20AC16 for every \u20AC1',
    description:
      'Independent research confirms a 16:1 Social Return on Investment. Your support directly translates into measurable community wellbeing.',
    color: 'from-emerald-500 to-teal-500',
    bgAccent: 'bg-emerald-500/10',
    textAccent: 'text-emerald-600 dark:text-emerald-400',
    borderAccent: 'border-emerald-500/30',
  },
  {
    icon: ShieldCheck,
    title: 'Proof and Transparency',
    highlight: 'Independent 2023 Study',
    description:
      'Our impact figures come from a rigorous, independent evaluation. We provide full transparency on outcomes, spend, and social metrics.',
    color: 'from-blue-500 to-indigo-500',
    bgAccent: 'bg-blue-500/10',
    textAccent: 'text-blue-600 dark:text-blue-400',
    borderAccent: 'border-blue-500/30',
  },
  {
    icon: Rocket,
    title: 'Strategic Growth',
    highlight: '2,500+ Members by 2029',
    description:
      'A 5-year roadmap to scale nationally with federated communities, corporate partnerships, and digital-first engagement.',
    color: 'from-purple-500 to-pink-500',
    bgAccent: 'bg-purple-500/10',
    textAccent: 'text-purple-600 dark:text-purple-400',
    borderAccent: 'border-purple-500/30',
  },
];

const partnershipTypes = [
  {
    icon: Building2,
    title: 'Corporate Partnership',
    description:
      'Align your CSR goals with tangible social outcomes. Employee volunteering, matched funding, and branded community projects.',
  },
  {
    icon: HeartHandshake,
    title: 'Sponsorship',
    description:
      'Fund a hub coordinator, sponsor community events, or support our digital platform. Every contribution amplifies social return.',
  },
  {
    icon: Laptop,
    title: 'Technology Partnership',
    description:
      'Collaborate on our open-source platform, provide technical expertise, or support our digital inclusion programmes.',
  },
  {
    icon: GraduationCap,
    title: 'Research Partnership',
    description:
      'Access a living lab for social innovation research. Study community resilience, wellbeing metrics, and mutual aid dynamics.',
  },
];

const learnMoreLinks = [
  {
    icon: FileText,
    title: 'Impact Summary',
    description: 'See our full SROI report and outcomes data',
    to: '/impact-summary',
  },
  {
    icon: Target,
    title: 'Strategic Plan',
    description: 'Our 5-year vision for growth and federation',
    to: '/strategic-plan',
  },
  {
    icon: BookOpen,
    title: 'Timebanking Guide',
    description: 'Learn how timebanking works',
    to: '/timebanking-guide',
  },
];

/* ───────────────────────── Animations ───────────────────────── */

const fadeInUp = {
  initial: { opacity: 0, y: 30 },
  animate: { opacity: 1, y: 0 },
};

const stagger = {
  animate: { transition: { staggerChildren: 0.12 } },
};

/* ───────────────────────── Component ───────────────────────── */

export function PartnerPage() {
  usePageTitle('Partner With Us');
  const { tenantPath } = useTenant();

  return (
    <>
    <PageMeta
      title="Partner With Us"
      description="Partner with hOUR Timebank to create measurable social value. Our 16:1 SROI means every euro invested generates sixteen in community impact."
    />
    <div className="-mx-3 sm:-mx-4 md:-mx-6 lg:-mx-8 -my-4 sm:-my-6 md:-my-8 overflow-x-hidden">
      {/* ─── Breadcrumbs ─── */}
      <div className="px-4 sm:px-6 lg:px-8 pt-6">
        <Breadcrumbs items={[
          { label: 'About', href: '/about' },
          { label: 'Partner With Us' },
        ]} />
      </div>

      {/* ─── Hero Section ─── */}
      <section className="relative py-16 sm:py-24 px-4 sm:px-6 lg:px-8 overflow-hidden">
        {/* Background decoration */}
        <div className="absolute inset-0 pointer-events-none opacity-20" aria-hidden="true">
          <div className="absolute top-10 left-1/3 w-72 h-72 bg-emerald-500 rounded-full blur-3xl" />
          <div className="absolute bottom-10 right-1/3 w-72 h-72 bg-purple-500 rounded-full blur-3xl" />
        </div>

        <div className="max-w-4xl mx-auto text-center relative z-10">
          <motion.div {...fadeInUp} transition={{ duration: 0.6 }}>
            <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-emerald-500/20 to-purple-500/20 mb-6">
              <Handshake className="w-8 h-8 text-emerald-500 dark:text-emerald-400" aria-hidden="true" />
            </div>
          </motion.div>

          <motion.h1
            {...fadeInUp}
            transition={{ duration: 0.6, delay: 0.1 }}
            className="text-3xl sm:text-4xl md:text-5xl font-bold text-theme-primary mb-6"
          >
            Partner With Us
          </motion.h1>

          <motion.p
            {...fadeInUp}
            transition={{ duration: 0.6, delay: 0.2 }}
            className="text-lg sm:text-xl text-theme-muted max-w-2xl mx-auto"
          >
            Invest in your community with a proven 1:16 social return. Together, we build
            stronger, more connected neighbourhoods.
          </motion.p>
        </div>
      </section>

      {/* ─── Addressing the Funding Gap ─── */}
      <section className="py-12 sm:py-16 px-4 sm:px-6 lg:px-8">
        <div className="max-w-4xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
          >
            <GlassCard className="p-8 sm:p-10 relative overflow-hidden">
              {/* Accent bar */}
              <div
                className="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-amber-500 to-orange-500"
                aria-hidden="true"
              />

              <div className="flex items-start gap-4 mb-4">
                <div className="flex-shrink-0 p-3 rounded-xl bg-amber-500/15">
                  <Lightbulb className="w-6 h-6 text-amber-500 dark:text-amber-400" aria-hidden="true" />
                </div>
                <div>
                  <h2 className="text-xl sm:text-2xl font-bold text-theme-primary mb-3">
                    Addressing the Funding Gap
                  </h2>
                  <p className="text-theme-muted leading-relaxed mb-4">
                    Timebanks thrive when they have a dedicated <strong className="text-theme-primary">Hub Coordinator</strong> \u2014
                    someone who onboards new members, facilitates connections, and ensures the community stays
                    vibrant and inclusive.
                  </p>
                  <p className="text-theme-muted leading-relaxed">
                    The single biggest challenge facing timebanking in Ireland is securing sustainable
                    funding for these coordinator roles. With your partnership, we can bridge this gap and
                    unlock extraordinary social value for communities that need it most.
                  </p>
                </div>
              </div>
            </GlassCard>
          </motion.div>
        </div>
      </section>

      {/* ─── Impact Cards ─── */}
      <section className="py-12 sm:py-16 px-4 sm:px-6 lg:px-8 bg-gradient-to-b from-transparent via-emerald-500/5 to-transparent">
        <div className="max-w-5xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            className="text-center mb-10"
          >
            <h2 className="text-2xl sm:text-3xl font-bold text-theme-primary mb-3">
              Why Partner With Us?
            </h2>
            <p className="text-theme-muted max-w-lg mx-auto">
              Evidence-based impact you can stand behind.
            </p>
          </motion.div>

          <motion.div
            initial="initial"
            whileInView="animate"
            viewport={{ once: true }}
            variants={stagger}
            className="grid sm:grid-cols-3 gap-6"
          >
            {impactCards.map((card) => (
              <motion.div key={card.title} variants={fadeInUp}>
                <GlassCard className="p-6 h-full text-center relative overflow-hidden group hover:scale-[1.02] transition-transform">
                  {/* Gradient top bar */}
                  <div
                    className={`absolute top-0 left-0 right-0 h-1 bg-gradient-to-r ${card.color}`}
                    aria-hidden="true"
                  />

                  <div className={`inline-flex items-center justify-center w-12 h-12 rounded-xl ${card.bgAccent} mb-4`}>
                    <card.icon className={`w-6 h-6 ${card.textAccent}`} aria-hidden="true" />
                  </div>

                  <p className={`text-lg font-extrabold ${card.textAccent} mb-1`}>
                    {card.highlight}
                  </p>
                  <h3 className="font-semibold text-theme-primary mb-2">{card.title}</h3>
                  <p className="text-sm text-theme-muted leading-relaxed">{card.description}</p>
                </GlassCard>
              </motion.div>
            ))}
          </motion.div>
        </div>
      </section>

      {/* ─── Partnership Opportunities ─── */}
      <section className="py-16 sm:py-20 px-4 sm:px-6 lg:px-8">
        <div className="max-w-5xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            className="text-center mb-12"
          >
            <h2 className="text-2xl sm:text-3xl font-bold text-theme-primary mb-3">
              Partnership Opportunities
            </h2>
            <p className="text-theme-muted max-w-lg mx-auto">
              Multiple ways to create impact alongside our community.
            </p>
          </motion.div>

          <div className="grid sm:grid-cols-2 gap-6">
            {partnershipTypes.map((type, index) => (
              <motion.div
                key={type.title}
                initial={{ opacity: 0, x: index % 2 === 0 ? -20 : 20 }}
                whileInView={{ opacity: 1, x: 0 }}
                viewport={{ once: true }}
                transition={{ delay: index * 0.1 }}
              >
                <GlassCard className="p-6 h-full group hover:scale-[1.01] transition-transform">
                  <div className="flex items-start gap-4">
                    <div className="flex-shrink-0 p-3 rounded-xl bg-gradient-to-br from-indigo-500/15 to-purple-500/15">
                      <type.icon className="w-6 h-6 text-indigo-500 dark:text-indigo-400" aria-hidden="true" />
                    </div>
                    <div>
                      <h3 className="font-semibold text-theme-primary text-lg mb-1">{type.title}</h3>
                      <p className="text-theme-muted text-sm leading-relaxed">{type.description}</p>
                    </div>
                  </div>
                </GlassCard>
              </motion.div>
            ))}
          </div>
        </div>
      </section>

      {/* ─── Learn More Links ─── */}
      <section className="py-12 sm:py-16 px-4 sm:px-6 lg:px-8 bg-gradient-to-b from-transparent via-indigo-500/5 to-transparent">
        <div className="max-w-3xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            className="text-center mb-8"
          >
            <h2 className="text-xl sm:text-2xl font-bold text-theme-primary mb-2">
              Learn More
            </h2>
          </motion.div>

          <div className="grid gap-4">
            {learnMoreLinks.map((link, index) => (
              <motion.div
                key={link.title}
                initial={{ opacity: 0, y: 15 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true }}
                transition={{ delay: index * 0.08 }}
              >
                <Link to={tenantPath(link.to)}>
                  <GlassCard className="p-5 flex items-center gap-4 group hover:scale-[1.01] transition-transform">
                    <div className="flex-shrink-0 p-2.5 rounded-xl bg-indigo-500/15">
                      <link.icon className="w-5 h-5 text-indigo-500 dark:text-indigo-400" aria-hidden="true" />
                    </div>
                    <div className="flex-1 min-w-0">
                      <h3 className="font-semibold text-theme-primary">{link.title}</h3>
                      <p className="text-sm text-theme-muted">{link.description}</p>
                    </div>
                    <ArrowRight className="w-5 h-5 text-theme-subtle group-hover:text-indigo-500 transition-colors flex-shrink-0" aria-hidden="true" />
                  </GlassCard>
                </Link>
              </motion.div>
            ))}
          </div>
        </div>
      </section>

      {/* ─── Related Pages ─── */}
      <RelatedPages current="/partner" />

      {/* ─── CTA Section ─── */}
      <section className="py-16 sm:py-20 px-4 sm:px-6 lg:px-8">
        <div className="max-w-4xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
          >
            <GlassCard className="p-10 sm:p-14 text-center relative overflow-hidden">
              {/* Background gradient */}
              <div className="absolute inset-0 opacity-10 pointer-events-none" aria-hidden="true">
                <div className="absolute top-0 right-0 w-64 h-64 bg-gradient-to-br from-emerald-500 to-teal-500 rounded-full blur-3xl" />
                <div className="absolute bottom-0 left-0 w-64 h-64 bg-gradient-to-tr from-purple-500 to-indigo-500 rounded-full blur-3xl" />
              </div>

              <div className="relative z-10">
                <div className="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-br from-emerald-500/20 to-purple-500/20 mb-6">
                  <Users className="w-7 h-7 text-emerald-500 dark:text-emerald-400" aria-hidden="true" />
                </div>

                <h2 className="text-2xl sm:text-3xl font-bold text-theme-primary mb-4">
                  Let&apos;s Build Something Together
                </h2>
                <p className="text-theme-muted max-w-lg mx-auto mb-8">
                  Whether you represent a business, foundation, or public body \u2014 we&apos;d love to
                  explore how we can create social value together.
                </p>

                <div className="flex flex-col sm:flex-row gap-4 justify-center">
                  <Link to={tenantPath('/contact')}>
                    <Button
                      size="lg"
                      className="w-full sm:w-auto bg-gradient-to-r from-emerald-500 to-teal-600 text-white font-semibold px-8"
                      endContent={<ArrowRight className="w-5 h-5" aria-hidden="true" />}
                    >
                      Get in Touch
                    </Button>
                  </Link>

                  <Link to={tenantPath('/strategic-plan')}>
                    <Button
                      size="lg"
                      variant="bordered"
                      className="w-full sm:w-auto border-theme-default text-theme-primary hover:bg-theme-hover"
                      startContent={<Target className="w-5 h-5" aria-hidden="true" />}
                    >
                      View Strategic Plan
                    </Button>
                  </Link>
                </div>
              </div>
            </GlassCard>
          </motion.div>
        </div>
      </section>
    </div>
    </>
  );
}

export default PartnerPage;
