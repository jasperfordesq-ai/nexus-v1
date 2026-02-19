// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Social Prescribing Partner Page - Evidence-based referral pathway and outcomes
 *
 * Tenant-specific "About" page for the hOUR Timebank community.
 * Visual sections:
 * 1. Hero with evidence headline
 * 2. Validated Outcomes stats
 * 3. Member testimonial blockquote
 * 4. The Managed Referral Pathway (4 steps)
 * 5. CTA for public sector partnership
 */

import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button } from '@heroui/react';
import {
  HeartPulse,
  ArrowRight,
  CheckCircle2,
  Users,
  ClipboardList,
  UserPlus,
  Link2,
  CalendarCheck,
  Quote,
  ShieldCheck,
  TrendingUp,
  Stethoscope,
  Mail,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { PageMeta } from '@/components/seo';
import { Breadcrumbs } from '@/components/navigation/Breadcrumbs';
import { useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { RelatedPages } from './RelatedPages';

/* ───────────────────────── Data ───────────────────────── */

const outcomeStats = [
  {
    value: '100%',
    label: 'Improved Wellbeing',
    description: 'Every participant reports improved mental health and personal wellbeing',
    icon: HeartPulse,
    color: 'from-emerald-500 to-teal-500',
    bgAccent: 'bg-emerald-500/10',
    textAccent: 'text-emerald-600 dark:text-emerald-400',
  },
  {
    value: '95%',
    label: 'Increased Connection',
    description: 'Participants feel significantly more socially connected after engagement',
    icon: Users,
    color: 'from-blue-500 to-indigo-500',
    bgAccent: 'bg-blue-500/10',
    textAccent: 'text-blue-600 dark:text-blue-400',
  },
  {
    value: '16:1',
    label: 'Social Return',
    description: 'Independent SROI analysis validates exceptional value for public investment',
    icon: TrendingUp,
    color: 'from-amber-500 to-orange-500',
    bgAccent: 'bg-amber-500/10',
    textAccent: 'text-amber-600 dark:text-amber-400',
  },
];

const referralSteps = [
  {
    icon: ClipboardList,
    title: 'Formal Referral',
    description:
      'A GP, social prescriber, or community health worker submits a structured referral through our secure pathway. No self-referral barriers \u2014 the link worker initiates contact.',
    color: 'from-blue-500 to-indigo-500',
    bgAccent: 'bg-blue-500/10',
    textAccent: 'text-blue-600 dark:text-blue-400',
  },
  {
    icon: UserPlus,
    title: 'Onboarding',
    description:
      'Our Hub Coordinator personally welcomes the participant, explains how timebanking works, and creates a profile highlighting their strengths and interests.',
    color: 'from-emerald-500 to-teal-500',
    bgAccent: 'bg-emerald-500/10',
    textAccent: 'text-emerald-600 dark:text-emerald-400',
  },
  {
    icon: Link2,
    title: 'Connection',
    description:
      'The coordinator matches the participant with suitable exchanges, group activities, or community events \u2014 building confidence through meaningful, supported engagement.',
    color: 'from-purple-500 to-pink-500',
    bgAccent: 'bg-purple-500/10',
    textAccent: 'text-purple-600 dark:text-purple-400',
  },
  {
    icon: CalendarCheck,
    title: 'Follow-Up',
    description:
      'Regular check-ins track progress against wellbeing indicators. Outcomes data is shared with the referrer, closing the feedback loop and demonstrating impact.',
    color: 'from-amber-500 to-orange-500',
    bgAccent: 'bg-amber-500/10',
    textAccent: 'text-amber-600 dark:text-amber-400',
  },
];

const strategicFitPoints = [
  'Aligned with Sl\u00e1intecare community-based care objectives',
  'Supports HSE Social Prescribing Framework delivery',
  'Addresses social isolation and loneliness at community level',
  'Provides structured outcomes data for public health reporting',
  'Scalable model through federated community network',
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

export function SocialPrescribingPage() {
  usePageTitle('Social Prescribing');
  const { tenantPath } = useTenant();

  return (
    <>
    <PageMeta
      title="Social Prescribing"
      description="Evidence-based social prescribing through timebanking: 100% improved wellbeing, 95% increased connection, and a structured referral pathway."
    />
    <div className="-mx-4 sm:-mx-6 lg:-mx-8 -my-6 sm:-my-8">
      {/* ─── Breadcrumbs ─── */}
      <div className="px-4 sm:px-6 lg:px-8 pt-6">
        <Breadcrumbs items={[
          { label: 'About', href: '/about' },
          { label: 'Social Prescribing' },
        ]} />
      </div>

      {/* ─── Hero Section ─── */}
      <section className="relative py-16 sm:py-24 px-4 sm:px-6 lg:px-8 overflow-hidden">
        {/* Background decoration */}
        <div className="absolute inset-0 pointer-events-none opacity-20" aria-hidden="true">
          <div className="absolute top-10 left-1/4 w-72 h-72 bg-emerald-500 rounded-full blur-3xl" />
          <div className="absolute bottom-10 right-1/4 w-72 h-72 bg-blue-500 rounded-full blur-3xl" />
        </div>

        <div className="max-w-4xl mx-auto text-center relative z-10">
          <motion.div {...fadeInUp} transition={{ duration: 0.6 }}>
            <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-emerald-500/20 to-blue-500/20 mb-6">
              <Stethoscope className="w-8 h-8 text-emerald-500 dark:text-emerald-400" aria-hidden="true" />
            </div>
          </motion.div>

          <motion.h1
            {...fadeInUp}
            transition={{ duration: 0.6, delay: 0.1 }}
            className="text-3xl sm:text-4xl md:text-5xl font-bold text-theme-primary mb-6"
          >
            Social Prescribing Partner
          </motion.h1>

          <motion.p
            {...fadeInUp}
            transition={{ duration: 0.6, delay: 0.2 }}
            className="text-lg sm:text-xl text-theme-muted max-w-2xl mx-auto"
          >
            Evidence-based, community-led, and 100% effective for wellbeing.
          </motion.p>
        </div>
      </section>

      {/* ─── Validated Outcomes ─── */}
      <section className="py-12 sm:py-16 px-4 sm:px-6 lg:px-8 bg-gradient-to-b from-transparent via-emerald-500/5 to-transparent">
        <div className="max-w-5xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            className="text-center mb-10"
          >
            <h2 className="text-2xl sm:text-3xl font-bold text-theme-primary mb-3">
              Validated Outcomes
            </h2>
            <p className="text-theme-muted max-w-lg mx-auto">
              Our wellbeing impact is independently verified and consistently exceptional.
            </p>
          </motion.div>

          <motion.div
            initial="initial"
            whileInView="animate"
            viewport={{ once: true }}
            variants={stagger}
            className="grid sm:grid-cols-3 gap-6"
          >
            {outcomeStats.map((stat) => (
              <motion.div key={stat.label} variants={fadeInUp}>
                <GlassCard className="p-6 text-center h-full relative overflow-hidden group hover:scale-[1.02] transition-transform">
                  {/* Gradient top bar */}
                  <div
                    className={`absolute top-0 left-0 right-0 h-1 bg-gradient-to-r ${stat.color}`}
                    aria-hidden="true"
                  />

                  <div className={`inline-flex items-center justify-center w-12 h-12 rounded-xl ${stat.bgAccent} mb-4`}>
                    <stat.icon className={`w-6 h-6 ${stat.textAccent}`} aria-hidden="true" />
                  </div>

                  <p className={`text-4xl sm:text-5xl font-extrabold ${stat.textAccent} mb-2`}>
                    {stat.value}
                  </p>
                  <p className="font-semibold text-theme-primary mb-1">{stat.label}</p>
                  <p className="text-sm text-theme-muted">{stat.description}</p>
                </GlassCard>
              </motion.div>
            ))}
          </motion.div>

          {/* Strategic Fit Points */}
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            className="mt-8"
          >
            <GlassCard className="p-6 sm:p-8">
              <div className="flex items-start gap-4 mb-4">
                <div className="flex-shrink-0 p-3 rounded-xl bg-indigo-500/15">
                  <ShieldCheck className="w-6 h-6 text-indigo-500 dark:text-indigo-400" aria-hidden="true" />
                </div>
                <h3 className="text-lg font-semibold text-theme-primary pt-2">
                  Strategic Fit
                </h3>
              </div>
              <ul className="space-y-3 ml-1">
                {strategicFitPoints.map((point) => (
                  <li key={point} className="flex items-start gap-3">
                    <CheckCircle2 className="w-5 h-5 text-emerald-500 dark:text-emerald-400 flex-shrink-0 mt-0.5" aria-hidden="true" />
                    <span className="text-theme-muted text-sm leading-relaxed">{point}</span>
                  </li>
                ))}
              </ul>
            </GlassCard>
          </motion.div>
        </div>
      </section>

      {/* ─── Member Testimonial ─── */}
      <section className="py-12 sm:py-16 px-4 sm:px-6 lg:px-8">
        <div className="max-w-3xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
          >
            <GlassCard className="p-8 sm:p-10 relative overflow-hidden">
              {/* Decorative gradient */}
              <div className="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-emerald-500 via-teal-500 to-blue-500" aria-hidden="true" />

              {/* Quote icon */}
              <div className="flex justify-center mb-6">
                <div className="p-3 rounded-full bg-emerald-500/15">
                  <Quote className="w-6 h-6 text-emerald-500 dark:text-emerald-400" aria-hidden="true" />
                </div>
              </div>

              <blockquote className="text-center">
                <p className="text-lg sm:text-xl text-theme-primary leading-relaxed italic mb-6">
                  &ldquo;Before I joined the timebank, I barely left the house. Now I help with gardening for
                  two neighbours, and in return someone helps me with my shopping. I&apos;ve made real
                  friends. It&apos;s given me a reason to get up in the morning.&rdquo;
                </p>
                <footer>
                  <div className="inline-flex items-center gap-3">
                    <div className="w-10 h-10 rounded-full bg-gradient-to-br from-emerald-500 to-teal-500 flex items-center justify-center text-white font-bold text-sm" aria-hidden="true">
                      M
                    </div>
                    <div className="text-left">
                      <cite className="not-italic font-semibold text-theme-primary">Monica</cite>
                      <p className="text-sm text-theme-muted">hOUR Timebank Member</p>
                    </div>
                  </div>
                </footer>
              </blockquote>
            </GlassCard>
          </motion.div>
        </div>
      </section>

      {/* ─── The Managed Referral Pathway ─── */}
      <section className="py-16 sm:py-20 px-4 sm:px-6 lg:px-8 bg-gradient-to-b from-transparent via-blue-500/5 to-transparent">
        <div className="max-w-5xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            className="text-center mb-12"
          >
            <h2 className="text-2xl sm:text-3xl font-bold text-theme-primary mb-3">
              The Managed Referral Pathway
            </h2>
            <p className="text-theme-muted max-w-lg mx-auto">
              A structured, supported journey from referral to measurable wellbeing outcomes.
            </p>
          </motion.div>

          <div className="grid sm:grid-cols-2 gap-6">
            {referralSteps.map((step, index) => (
              <motion.div
                key={step.title}
                initial={{ opacity: 0, y: 30 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true }}
                transition={{ delay: index * 0.12 }}
              >
                <GlassCard className="p-6 h-full relative group hover:scale-[1.01] transition-transform">
                  {/* Step number badge */}
                  <div className={`absolute -top-3 -right-3 w-9 h-9 rounded-full bg-gradient-to-br ${step.color} flex items-center justify-center text-white text-sm font-bold shadow-lg`}>
                    {index + 1}
                  </div>

                  <div className="flex items-start gap-4">
                    <div className={`flex-shrink-0 p-3 rounded-xl ${step.bgAccent}`}>
                      <step.icon className={`w-6 h-6 ${step.textAccent}`} aria-hidden="true" />
                    </div>
                    <div>
                      <h3 className="font-semibold text-theme-primary text-lg mb-2">
                        {step.title}
                      </h3>
                      <p className="text-theme-muted text-sm leading-relaxed">
                        {step.description}
                      </p>
                    </div>
                  </div>
                </GlassCard>
              </motion.div>
            ))}
          </div>
        </div>
      </section>

      {/* ─── Related Pages ─── */}
      <RelatedPages current="/social-prescribing" />

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
                <div className="absolute bottom-0 left-0 w-64 h-64 bg-gradient-to-tr from-blue-500 to-indigo-500 rounded-full blur-3xl" />
              </div>

              <div className="relative z-10">
                <div className="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-br from-emerald-500/20 to-blue-500/20 mb-6">
                  <HeartPulse className="w-7 h-7 text-emerald-500 dark:text-emerald-400" aria-hidden="true" />
                </div>

                <h2 className="text-2xl sm:text-3xl font-bold text-theme-primary mb-4">
                  Seeking a Public Sector Contract Partner
                </h2>
                <p className="text-theme-muted max-w-lg mx-auto mb-4">
                  We&apos;re actively seeking partnerships with HSE Community Healthcare
                  Organisations, local authorities, and social prescribing link workers to deliver
                  this evidence-based model at scale.
                </p>
                <p className="text-theme-muted max-w-lg mx-auto mb-8">
                  If you commission community health services or operate a social prescribing
                  programme, we&apos;d welcome the opportunity to discuss how hOUR Timebank can
                  support your outcomes.
                </p>

                <div className="flex flex-col sm:flex-row gap-4 justify-center">
                  <Link to={tenantPath('/contact')}>
                    <Button
                      size="lg"
                      className="w-full sm:w-auto bg-gradient-to-r from-emerald-500 to-teal-600 text-white font-semibold px-8"
                      endContent={<ArrowRight className="w-5 h-5" aria-hidden="true" />}
                    >
                      Contact Us
                    </Button>
                  </Link>

                  <Link to={tenantPath('/partner')}>
                    <Button
                      size="lg"
                      variant="bordered"
                      className="w-full sm:w-auto border-theme-default text-theme-primary hover:bg-theme-hover"
                      startContent={<Mail className="w-5 h-5" aria-hidden="true" />}
                    >
                      Partnership Details
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

export default SocialPrescribingPage;
