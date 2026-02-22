// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Strategic Plan Page - hOUR Timebank Strategic Plan 2026-2030
 *
 * Covers vision, mission, SWOT analysis, strategic pillars with KPIs,
 * Year 1 roadmap, and risk mitigation.
 */

import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button } from '@heroui/react';
import {
  Compass,
  Download,
  Eye,
  Rocket,
  Target,
  ShieldAlert,
  TrendingUp,
  Lightbulb,
  AlertTriangle,
  CheckCircle2,
  Sprout,
  DollarSign,
  Calendar,
  ChevronRight,
  Mail,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { PageMeta } from '@/components/seo';
import { Breadcrumbs } from '@/components/navigation/Breadcrumbs';
import { useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { resolveAssetUrl } from '@/lib/helpers';
import { RelatedPages } from './RelatedPages';

/* ───────────────────────── Constants ───────────────────────── */

const STRATEGIC_PLAN_PDF = resolveAssetUrl('/uploads/tenants/hour-timebank/Timebank-Ireland-Strategic-Plan-2026-2030.pdf');

/* ───────────────────────── Animations ───────────────────────── */

const fadeInUp = {
  initial: { opacity: 0, y: 30 },
  animate: { opacity: 1, y: 0 },
};

/* ───────────────────────── Table of Contents ───────────────────────── */

const tocSections = [
  { id: 'executive-summary', label: 'Executive Summary' },
  { id: 'vision-mission', label: 'Vision & Mission' },
  { id: 'swot', label: 'SWOT Analysis' },
  { id: 'pillars', label: 'Strategic Pillars' },
  { id: 'roadmap', label: 'Year 1 Roadmap' },
  { id: 'risks', label: 'Risk & Mitigation' },
];

/* ───────────────────────── Component ───────────────────────── */

export function StrategicPlanPage() {
  const { tenantPath } = useTenant();
  usePageTitle('Strategic Plan 2026\u20132030');

  const scrollTo = (id: string) => {
    const el = document.getElementById(id);
    if (el) {
      const top = el.getBoundingClientRect().top + window.scrollY - 100;
      window.scrollTo({ top, behavior: 'smooth' });
    }
  };

  return (
    <>
    <PageMeta
      title="Strategic Plan 2026–2030"
      description="The Power of an Hour: hOUR Timebank's five-year strategic plan for sustainable growth, national scaling, and building a connected Ireland."
    />
    <div className="-mx-3 sm:-mx-4 md:-mx-6 lg:-mx-8 -my-4 sm:-my-6 md:-my-8 overflow-x-hidden">
      {/* ─── Breadcrumbs ─── */}
      <div className="px-4 sm:px-6 lg:px-8 pt-6">
        <Breadcrumbs items={[
          { label: 'About', href: '/about' },
          { label: 'Strategic Plan' },
        ]} />
      </div>

      {/* ─── Hero Section ─── */}
      <section className="relative py-16 sm:py-24 px-4 sm:px-6 lg:px-8 overflow-hidden">
        <div className="absolute inset-0 pointer-events-none opacity-20" aria-hidden="true">
          <div className="absolute top-10 left-1/4 w-72 h-72 bg-indigo-500 rounded-full blur-3xl" />
          <div className="absolute bottom-10 right-1/4 w-72 h-72 bg-purple-500 rounded-full blur-3xl" />
        </div>

        <div className="max-w-4xl mx-auto text-center relative z-10">
          <motion.div {...fadeInUp} transition={{ duration: 0.6 }}>
            <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-500/20 to-purple-500/20 mb-6">
              <Compass className="w-8 h-8 text-indigo-500 dark:text-indigo-400" aria-hidden="true" />
            </div>
          </motion.div>

          <motion.h1
            {...fadeInUp}
            transition={{ duration: 0.6, delay: 0.1 }}
            className="text-3xl sm:text-4xl md:text-5xl font-bold text-theme-primary mb-4"
          >
            Strategic Plan 2026&ndash;2030
          </motion.h1>

          <motion.p
            {...fadeInUp}
            transition={{ duration: 0.6, delay: 0.2 }}
            className="text-lg sm:text-xl text-theme-muted max-w-2xl mx-auto mb-3"
          >
            The Power of an Hour: Building a Resilient, Connected Ireland
          </motion.p>

          <motion.p
            {...fadeInUp}
            transition={{ duration: 0.6, delay: 0.3 }}
            className="text-sm text-theme-subtle max-w-xl mx-auto mb-8"
          >
            A five-year roadmap for sustainable growth, national scaling, and deepening our social impact.
          </motion.p>

          <motion.div
            {...fadeInUp}
            transition={{ duration: 0.6, delay: 0.4 }}
          >
            <Button
              as="a"
              href={STRATEGIC_PLAN_PDF}
              target="_blank"
              rel="noopener noreferrer"
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-semibold"
              startContent={<Download className="w-4 h-4" aria-hidden="true" />}
            >
              Download Full Plan (PDF)
            </Button>
          </motion.div>
        </div>
      </section>

      {/* ─── Table of Contents ─── */}
      <section className="py-8 px-4 sm:px-6 lg:px-8">
        <div className="max-w-5xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
          >
            <GlassCard className="p-4 sm:p-6">
              <h2 className="text-sm font-semibold text-theme-subtle uppercase tracking-wider mb-4">
                Contents
              </h2>
              <div className="flex flex-wrap gap-2">
                {tocSections.map((section, index) => (
                  <Button
                    key={section.id}
                    type="button"
                    variant="light"
                    onPress={() => scrollTo(section.id)}
                    className="flex items-center gap-2 px-3 py-2 rounded-lg text-sm text-theme-muted hover:bg-indigo-500/10 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors h-auto min-w-0"
                  >
                    <span className="flex-shrink-0 w-6 h-6 rounded-md bg-gradient-to-br from-indigo-500/20 to-purple-500/20 flex items-center justify-center text-xs font-bold text-indigo-600 dark:text-indigo-400">
                      {index + 1}
                    </span>
                    <span>{section.label}</span>
                  </Button>
                ))}
              </div>
            </GlassCard>
          </motion.div>
        </div>
      </section>

      {/* ─── Section 1: Executive Summary ─── */}
      <section id="executive-summary" className="py-12 px-4 sm:px-6 lg:px-8">
        <div className="max-w-4xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
          >
            <SectionHeading icon={Target} number={1} title="Executive Summary" />

            <div className="space-y-6 mt-6">
              <GlassCard className="p-6">
                <p className="text-theme-muted text-sm leading-relaxed mb-6">
                  This strategic plan sets out how hOUR Timebank will grow from a proven community
                  initiative into a sustainable, nationally recognised social infrastructure over the next
                  five years. It is built on two primary goals:
                </p>

                <div className="grid sm:grid-cols-2 gap-4">
                  <div className="p-5 rounded-xl bg-gradient-to-br from-emerald-500/10 to-teal-500/10 border border-emerald-500/20">
                    <div className="flex items-center gap-3 mb-3">
                      <div className="p-2 rounded-lg bg-emerald-500/15">
                        <Sprout className="w-5 h-5 text-emerald-500 dark:text-emerald-400" aria-hidden="true" />
                      </div>
                      <h3 className="font-semibold text-theme-primary">Goal 1</h3>
                    </div>
                    <p className="text-sm text-theme-muted leading-relaxed">
                      <strong className="text-theme-primary">Scale reach and impact</strong> by expanding
                      to new communities, increasing active membership, and deepening the social value
                      delivered per member.
                    </p>
                  </div>

                  <div className="p-5 rounded-xl bg-gradient-to-br from-indigo-500/10 to-blue-500/10 border border-indigo-500/20">
                    <div className="flex items-center gap-3 mb-3">
                      <div className="p-2 rounded-lg bg-indigo-500/15">
                        <DollarSign className="w-5 h-5 text-indigo-500 dark:text-indigo-400" aria-hidden="true" />
                      </div>
                      <h3 className="font-semibold text-theme-primary">Goal 2</h3>
                    </div>
                    <p className="text-sm text-theme-muted leading-relaxed">
                      <strong className="text-theme-primary">Build financial resilience</strong> by diversifying
                      income, securing multi-year funding, and developing earned revenue streams to reduce
                      dependency on grants.
                    </p>
                  </div>
                </div>
              </GlassCard>
            </div>
          </motion.div>
        </div>
      </section>

      {/* ─── Section 2: Vision & Mission ─── */}
      <section id="vision-mission" className="py-12 px-4 sm:px-6 lg:px-8 bg-gradient-to-b from-transparent via-indigo-500/5 to-transparent">
        <div className="max-w-4xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
          >
            <SectionHeading icon={Eye} number={2} title="Vision & Mission" />

            <div className="grid sm:grid-cols-2 gap-6 mt-6">
              {/* Mission */}
              <GlassCard className="p-6 relative overflow-hidden">
                <div className="absolute top-0 left-0 w-1 h-full bg-gradient-to-b from-indigo-500 to-blue-500 rounded-l" aria-hidden="true" />
                <div className="pl-4">
                  <div className="flex items-center gap-2 mb-3">
                    <div className="p-2 rounded-lg bg-indigo-500/15">
                      <Rocket className="w-5 h-5 text-indigo-500 dark:text-indigo-400" aria-hidden="true" />
                    </div>
                    <h3 className="text-lg font-bold text-indigo-600 dark:text-indigo-400">Our Mission</h3>
                  </div>
                  <p className="text-sm text-theme-muted leading-relaxed">
                    To create thriving, connected communities across Ireland through time-based exchange,
                    where every person&apos;s contribution is valued equally and no one is left isolated.
                  </p>
                </div>
              </GlassCard>

              {/* Vision */}
              <GlassCard className="p-6 relative overflow-hidden">
                <div className="absolute top-0 left-0 w-1 h-full bg-gradient-to-b from-emerald-500 to-teal-500 rounded-l" aria-hidden="true" />
                <div className="pl-4">
                  <div className="flex items-center gap-2 mb-3">
                    <div className="p-2 rounded-lg bg-emerald-500/15">
                      <Eye className="w-5 h-5 text-emerald-500 dark:text-emerald-400" aria-hidden="true" />
                    </div>
                    <h3 className="text-lg font-bold text-emerald-600 dark:text-emerald-400">Our Vision</h3>
                  </div>
                  <p className="text-sm text-theme-muted leading-relaxed">
                    An Ireland where timebanking is a recognised pillar of community wellbeing &mdash;
                    accessible in every county, integrated into public health systems, and empowering
                    tens of thousands of people to live more connected, purposeful lives.
                  </p>
                </div>
              </GlassCard>
            </div>
          </motion.div>
        </div>
      </section>

      {/* ─── Section 3: SWOT Analysis ─── */}
      <section id="swot" className="py-12 px-4 sm:px-6 lg:px-8">
        <div className="max-w-5xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
          >
            <SectionHeading icon={ShieldAlert} number={3} title="SWOT Analysis" />

            <div className="grid sm:grid-cols-2 gap-4 mt-6">
              {/* Strengths */}
              <SwotCard
                title="Strengths"
                accent="emerald"
                icon={CheckCircle2}
                items={[
                  'Proven social impact: €16 SROI per €1 invested',
                  'Strong partnerships with HSE, local authorities, and community groups',
                  'Lean operations with low overheads and volunteer commitment',
                  'Technology-enabled platform for scalable community management',
                  'Passionate, dedicated founding team with deep sector knowledge',
                ]}
              />

              {/* Weaknesses */}
              <SwotCard
                title="Weaknesses"
                accent="rose"
                icon={AlertTriangle}
                items={[
                  'Financial instability due to reliance on short-term grants',
                  'Limited human resources — core team stretched across multiple roles',
                  'No permanent physical hub or dedicated community space',
                  'Brand awareness low outside existing community networks',
                  'Volunteer coordinator capacity is a single point of failure',
                ]}
              />

              {/* Opportunities */}
              <SwotCard
                title="Opportunities"
                accent="indigo"
                icon={Lightbulb}
                items={[
                  'Public sector interest in social prescribing and community health models',
                  'Hybrid digital/in-person models for post-pandemic community building',
                  'National loneliness narrative creating political and media support',
                  'EU funding programmes for social innovation and cohesion',
                  'Corporate social responsibility partnerships for sustainable income',
                ]}
              />

              {/* Threats */}
              <SwotCard
                title="Threats"
                accent="amber"
                icon={ShieldAlert}
                items={[
                  'Funding cliff risk if current grants are not renewed',
                  'Volunteer and coordinator burnout without adequate resources',
                  'Competition from other wellbeing and community platforms',
                  'Changing government priorities and policy direction',
                  'Technology costs rising without corresponding income growth',
                ]}
              />
            </div>
          </motion.div>
        </div>
      </section>

      {/* ─── Section 4: Strategic Pillars ─── */}
      <section id="pillars" className="py-12 px-4 sm:px-6 lg:px-8 bg-gradient-to-b from-transparent via-indigo-500/5 to-transparent">
        <div className="max-w-5xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
          >
            <SectionHeading icon={TrendingUp} number={4} title="Strategic Pillars" />

            <div className="space-y-8 mt-6">
              {/* Pillar 1 */}
              <GlassCard className="overflow-hidden">
                <div className="p-5 sm:p-6 border-b border-theme-default bg-gradient-to-r from-emerald-500/5 to-teal-500/5">
                  <div className="flex items-center gap-3">
                    <div className="p-2.5 rounded-xl bg-emerald-500/15">
                      <Sprout className="w-5 h-5 text-emerald-500 dark:text-emerald-400" aria-hidden="true" />
                    </div>
                    <div>
                      <p className="text-xs text-theme-subtle uppercase tracking-wider font-medium">Pillar 1</p>
                      <h3 className="text-lg font-bold text-theme-primary">Roots & Reach (Growth)</h3>
                    </div>
                  </div>
                </div>
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead>
                      <tr className="bg-emerald-500/5">
                        <th className="px-5 py-3 text-left font-semibold text-theme-primary">Initiative</th>
                        <th className="px-5 py-3 text-left font-semibold text-theme-primary">Priority</th>
                        <th className="px-5 py-3 text-left font-semibold text-theme-primary">KPI / Target</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-theme-default">
                      {[
                        { initiative: 'Expand to 3 new communities', priority: 'Year 1', kpi: '3 new timebank chapters launched' },
                        { initiative: 'Active member growth programme', priority: 'Ongoing', kpi: '500 active members by 2028' },
                        { initiative: 'HSE social prescribing pilot', priority: 'Year 1\u20132', kpi: '1 pilot GP referral partnership live' },
                        { initiative: 'University and youth outreach', priority: 'Year 2', kpi: '50 members under 30' },
                        { initiative: 'Regional hub model development', priority: 'Year 2\u20133', kpi: '2 regional hubs with local coordinators' },
                        { initiative: 'National awareness campaign', priority: 'Year 1', kpi: '50% increase in website traffic' },
                      ].map((row, idx) => (
                        <tr key={idx} className={idx % 2 === 1 ? 'bg-theme-hover/20' : ''}>
                          <td className="px-5 py-3 text-theme-muted">{row.initiative}</td>
                          <td className="px-5 py-3">
                            <span className="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-500/15 text-emerald-600 dark:text-emerald-400">
                              {row.priority}
                            </span>
                          </td>
                          <td className="px-5 py-3 text-theme-muted">{row.kpi}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </GlassCard>

              {/* Pillar 2 */}
              <GlassCard className="overflow-hidden">
                <div className="p-5 sm:p-6 border-b border-theme-default bg-gradient-to-r from-indigo-500/5 to-blue-500/5">
                  <div className="flex items-center gap-3">
                    <div className="p-2.5 rounded-xl bg-indigo-500/15">
                      <DollarSign className="w-5 h-5 text-indigo-500 dark:text-indigo-400" aria-hidden="true" />
                    </div>
                    <div>
                      <p className="text-xs text-theme-subtle uppercase tracking-wider font-medium">Pillar 2</p>
                      <h3 className="text-lg font-bold text-theme-primary">Financial Resilience</h3>
                    </div>
                  </div>
                </div>
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead>
                      <tr className="bg-indigo-500/5">
                        <th className="px-5 py-3 text-left font-semibold text-theme-primary">Initiative</th>
                        <th className="px-5 py-3 text-left font-semibold text-theme-primary">Priority</th>
                        <th className="px-5 py-3 text-left font-semibold text-theme-primary">KPI / Target</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-theme-default">
                      {[
                        { initiative: 'Multi-year funding applications (Pobal, SSE Airtricity)', priority: 'Year 1', kpi: '2+ multi-year grants secured' },
                        { initiative: 'Corporate partnership programme', priority: 'Year 1\u20132', kpi: '3 corporate sponsors at €5K+' },
                        { initiative: 'Platform licensing (SaaS model)', priority: 'Year 2\u20133', kpi: '€15K annual earned revenue' },
                        { initiative: 'Membership fee model exploration', priority: 'Year 2', kpi: 'Feasibility study complete' },
                        { initiative: 'Fundraising events and campaigns', priority: 'Ongoing', kpi: '€10K annual fundraising income' },
                        { initiative: 'Diversified income to 40% earned revenue', priority: 'Year 5', kpi: '40% non-grant income ratio' },
                      ].map((row, idx) => (
                        <tr key={idx} className={idx % 2 === 1 ? 'bg-theme-hover/20' : ''}>
                          <td className="px-5 py-3 text-theme-muted">{row.initiative}</td>
                          <td className="px-5 py-3">
                            <span className="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-500/15 text-indigo-600 dark:text-indigo-400">
                              {row.priority}
                            </span>
                          </td>
                          <td className="px-5 py-3 text-theme-muted">{row.kpi}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </GlassCard>
            </div>
          </motion.div>
        </div>
      </section>

      {/* ─── Section 5: Year 1 Roadmap ─── */}
      <section id="roadmap" className="py-12 px-4 sm:px-6 lg:px-8">
        <div className="max-w-5xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
          >
            <SectionHeading icon={Calendar} number={5} title="Year 1 Roadmap (2026)" />

            <div className="mt-6">
              <GlassCard className="overflow-hidden">
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead>
                      <tr className="bg-gradient-to-r from-indigo-500/10 to-purple-500/10">
                        <th className="px-4 py-3 text-left font-semibold text-theme-primary min-w-[160px]">Activity</th>
                        <th className="px-4 py-3 text-center font-semibold text-theme-primary min-w-[90px]">Q1</th>
                        <th className="px-4 py-3 text-center font-semibold text-theme-primary min-w-[90px]">Q2</th>
                        <th className="px-4 py-3 text-center font-semibold text-theme-primary min-w-[90px]">Q3</th>
                        <th className="px-4 py-3 text-center font-semibold text-theme-primary min-w-[90px]">Q4</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-theme-default">
                      {[
                        {
                          activity: 'Multi-year funding applications',
                          q1: { label: 'Submit', type: 'submit' as const },
                          q2: { label: 'Secure', type: 'secure' as const },
                          q3: null,
                          q4: null,
                        },
                        {
                          activity: 'Launch 3 new community chapters',
                          q1: { label: 'Pitch', type: 'pitch' as const },
                          q2: { label: 'Launch', type: 'launch' as const },
                          q3: { label: 'Launch', type: 'launch' as const },
                          q4: { label: 'Launch', type: 'launch' as const },
                        },
                        {
                          activity: 'Social prescribing pilot outreach',
                          q1: null,
                          q2: { label: 'Pitch', type: 'pitch' as const },
                          q3: { label: 'Ongoing', type: 'ongoing' as const },
                          q4: { label: 'Ongoing', type: 'ongoing' as const },
                        },
                        {
                          activity: 'National awareness campaign',
                          q1: null,
                          q2: { label: 'Launch', type: 'launch' as const },
                          q3: { label: 'Ongoing', type: 'ongoing' as const },
                          q4: { label: 'Ongoing', type: 'ongoing' as const },
                        },
                        {
                          activity: 'Corporate sponsor outreach',
                          q1: { label: 'Pitch', type: 'pitch' as const },
                          q2: { label: 'Pitch', type: 'pitch' as const },
                          q3: { label: 'Secure', type: 'secure' as const },
                          q4: null,
                        },
                        {
                          activity: 'Platform UX improvements',
                          q1: { label: 'Ongoing', type: 'ongoing' as const },
                          q2: { label: 'Ongoing', type: 'ongoing' as const },
                          q3: { label: 'Ongoing', type: 'ongoing' as const },
                          q4: { label: 'Ongoing', type: 'ongoing' as const },
                        },
                        {
                          activity: 'Board recruitment & governance',
                          q1: { label: 'Submit', type: 'submit' as const },
                          q2: { label: 'Secure', type: 'secure' as const },
                          q3: null,
                          q4: null,
                        },
                        {
                          activity: 'Annual impact report',
                          q1: null,
                          q2: null,
                          q3: null,
                          q4: { label: 'Launch', type: 'launch' as const },
                        },
                      ].map((row, idx) => (
                        <tr key={idx} className={idx % 2 === 1 ? 'bg-theme-hover/20' : ''}>
                          <td className="px-4 py-3 text-theme-muted font-medium">{row.activity}</td>
                          {[row.q1, row.q2, row.q3, row.q4].map((cell, cellIdx) => (
                            <td key={cellIdx} className="px-4 py-3 text-center">
                              {cell ? <RoadmapBadge label={cell.label} type={cell.type} /> : (
                                <span className="text-theme-subtle">&mdash;</span>
                              )}
                            </td>
                          ))}
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </GlassCard>

              {/* Badge Legend */}
              <div className="flex flex-wrap gap-3 mt-4 justify-center">
                {[
                  { label: 'Submit', type: 'submit' as const },
                  { label: 'Secure', type: 'secure' as const },
                  { label: 'Launch', type: 'launch' as const },
                  { label: 'Ongoing', type: 'ongoing' as const },
                  { label: 'Pitch', type: 'pitch' as const },
                ].map((item) => (
                  <div key={item.label} className="flex items-center gap-1.5">
                    <RoadmapBadge label={item.label} type={item.type} />
                    <span className="text-xs text-theme-subtle">{item.label}</span>
                  </div>
                ))}
              </div>
            </div>
          </motion.div>
        </div>
      </section>

      {/* ─── Section 6: Risk & Mitigation ─── */}
      <section id="risks" className="py-12 px-4 sm:px-6 lg:px-8 bg-gradient-to-b from-transparent via-indigo-500/5 to-transparent">
        <div className="max-w-5xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
          >
            <SectionHeading icon={ShieldAlert} number={6} title="Risk & Mitigation" />

            <div className="mt-6">
              <GlassCard className="overflow-hidden">
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead>
                      <tr className="bg-gradient-to-r from-rose-500/10 to-amber-500/10">
                        <th className="px-5 py-3 text-left font-semibold text-theme-primary">Risk</th>
                        <th className="px-5 py-3 text-center font-semibold text-theme-primary w-24">Likelihood</th>
                        <th className="px-5 py-3 text-center font-semibold text-theme-primary w-24">Impact</th>
                        <th className="px-5 py-3 text-left font-semibold text-theme-primary">Mitigation Strategy</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-theme-default">
                      {[
                        {
                          risk: 'Core funding not renewed',
                          likelihood: 'High',
                          impact: 'Critical',
                          mitigation: 'Diversify income streams, build reserves, pursue multi-year grants, and develop earned revenue through platform licensing.',
                        },
                        {
                          risk: 'Key coordinator burnout or departure',
                          likelihood: 'Medium',
                          impact: 'High',
                          mitigation: 'Document processes, cross-train volunteers, establish succession plan, invest in coordinator wellbeing support.',
                        },
                        {
                          risk: 'Low adoption in new communities',
                          likelihood: 'Medium',
                          impact: 'Medium',
                          mitigation: 'Start with established community partners, pilot small before scaling, use local champions and peer ambassadors.',
                        },
                        {
                          risk: 'Technology platform costs exceed budget',
                          likelihood: 'Low',
                          impact: 'Medium',
                          mitigation: 'Open-source infrastructure, negotiate hosting rates, explore technology partnerships with socially-minded tech firms.',
                        },
                        {
                          risk: 'Volunteer fatigue across the network',
                          likelihood: 'Medium',
                          impact: 'High',
                          mitigation: 'Recognition programmes, regular appreciation events, manageable time commitments, clear boundaries and expectations.',
                        },
                      ].map((row, idx) => (
                        <tr key={idx} className={idx % 2 === 1 ? 'bg-theme-hover/20' : ''}>
                          <td className="px-5 py-3 text-theme-muted font-medium">{row.risk}</td>
                          <td className="px-5 py-3 text-center">
                            <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${
                              row.likelihood === 'High'
                                ? 'bg-rose-500/15 text-rose-600 dark:text-rose-400'
                                : row.likelihood === 'Medium'
                                ? 'bg-amber-500/15 text-amber-600 dark:text-amber-400'
                                : 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
                            }`}>
                              {row.likelihood}
                            </span>
                          </td>
                          <td className="px-5 py-3 text-center">
                            <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${
                              row.impact === 'Critical'
                                ? 'bg-rose-500/15 text-rose-600 dark:text-rose-400'
                                : row.impact === 'High'
                                ? 'bg-amber-500/15 text-amber-600 dark:text-amber-400'
                                : 'bg-indigo-500/15 text-indigo-600 dark:text-indigo-400'
                            }`}>
                              {row.impact}
                            </span>
                          </td>
                          <td className="px-5 py-3 text-theme-muted text-xs leading-relaxed">{row.mitigation}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </GlassCard>
            </div>
          </motion.div>
        </div>
      </section>

      {/* ─── Related Pages ─── */}
      <RelatedPages current="/strategic-plan" />

      {/* ─── CTA Section ─── */}
      <section className="py-16 sm:py-20 px-4 sm:px-6 lg:px-8">
        <div className="max-w-4xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
          >
            <GlassCard className="p-10 sm:p-14 text-center relative overflow-hidden">
              <div className="absolute inset-0 opacity-10 pointer-events-none" aria-hidden="true">
                <div className="absolute top-0 right-0 w-64 h-64 bg-gradient-to-br from-indigo-500 to-purple-500 rounded-full blur-3xl" />
                <div className="absolute bottom-0 left-0 w-64 h-64 bg-gradient-to-tr from-emerald-500 to-teal-500 rounded-full blur-3xl" />
              </div>

              <div className="relative z-10">
                <h2 className="text-2xl sm:text-3xl font-bold text-theme-primary mb-4">
                  Be Part of the Plan
                </h2>
                <p className="text-theme-muted max-w-lg mx-auto mb-8">
                  Whether you want to partner, fund, volunteer, or join as a member &mdash;
                  there&apos;s a role for you in building a more connected Ireland.
                </p>

                <div className="flex flex-col sm:flex-row gap-3 justify-center">
                  <Button
                    as="a"
                    href={STRATEGIC_PLAN_PDF}
                    target="_blank"
                    rel="noopener noreferrer"
                    size="lg"
                    className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-semibold px-8"
                    startContent={<Download className="w-5 h-5" aria-hidden="true" />}
                  >
                    Download Full Plan
                  </Button>
                  <Link to={tenantPath('/contact')}>
                    <Button
                      size="lg"
                      variant="bordered"
                      className="w-full sm:w-auto border-theme-default text-theme-primary hover:bg-theme-hover"
                      startContent={<Mail className="w-5 h-5" aria-hidden="true" />}
                    >
                      Get in Touch
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

/* ───────────────────────── Sub-Components ───────────────────────── */

interface SectionHeadingProps {
  icon: React.ComponentType<{ className?: string }>;
  number: number;
  title: string;
}

function SectionHeading({ icon: Icon, number, title }: SectionHeadingProps) {
  return (
    <div className="flex items-center gap-3">
      <div className="flex-shrink-0 w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500/20 to-purple-500/20 flex items-center justify-center">
        <Icon className="w-5 h-5 text-indigo-600 dark:text-indigo-400" aria-hidden="true" />
      </div>
      <div>
        <p className="text-xs text-theme-subtle uppercase tracking-wider font-medium">Section {number}</p>
        <h2 className="text-xl sm:text-2xl font-bold text-theme-primary">{title}</h2>
      </div>
    </div>
  );
}

/* ─── SWOT Card ─── */

interface SwotCardProps {
  title: string;
  accent: 'emerald' | 'rose' | 'indigo' | 'amber';
  icon: React.ComponentType<{ className?: string }>;
  items: string[];
}

function SwotCard({ title, accent, icon: Icon, items }: SwotCardProps) {
  const colorMap = {
    emerald: {
      gradient: 'from-emerald-500/10 to-teal-500/10',
      border: 'border-emerald-500/20',
      iconBg: 'bg-emerald-500/15',
      iconColor: 'text-emerald-500 dark:text-emerald-400',
      titleColor: 'text-emerald-600 dark:text-emerald-400',
      bullet: 'text-emerald-500 dark:text-emerald-400',
    },
    rose: {
      gradient: 'from-rose-500/10 to-pink-500/10',
      border: 'border-rose-500/20',
      iconBg: 'bg-rose-500/15',
      iconColor: 'text-rose-500 dark:text-rose-400',
      titleColor: 'text-rose-600 dark:text-rose-400',
      bullet: 'text-rose-500 dark:text-rose-400',
    },
    indigo: {
      gradient: 'from-indigo-500/10 to-blue-500/10',
      border: 'border-indigo-500/20',
      iconBg: 'bg-indigo-500/15',
      iconColor: 'text-indigo-500 dark:text-indigo-400',
      titleColor: 'text-indigo-600 dark:text-indigo-400',
      bullet: 'text-indigo-500 dark:text-indigo-400',
    },
    amber: {
      gradient: 'from-amber-500/10 to-orange-500/10',
      border: 'border-amber-500/20',
      iconBg: 'bg-amber-500/15',
      iconColor: 'text-amber-500 dark:text-amber-400',
      titleColor: 'text-amber-600 dark:text-amber-400',
      bullet: 'text-amber-500 dark:text-amber-400',
    },
  };

  const colors = colorMap[accent];

  return (
    <GlassCard className={`p-5 border ${colors.border} bg-gradient-to-br ${colors.gradient}`}>
      <div className="flex items-center gap-2 mb-4">
        <div className={`p-2 rounded-lg ${colors.iconBg}`}>
          <Icon className={`w-5 h-5 ${colors.iconColor}`} aria-hidden="true" />
        </div>
        <h3 className={`text-base font-bold ${colors.titleColor}`}>{title}</h3>
      </div>
      <ul className="space-y-2">
        {items.map((item) => (
          <li key={item} className="flex items-start gap-2 text-sm text-theme-muted">
            <ChevronRight className={`w-3.5 h-3.5 ${colors.bullet} flex-shrink-0 mt-0.5`} aria-hidden="true" />
            <span>{item}</span>
          </li>
        ))}
      </ul>
    </GlassCard>
  );
}

/* ─── Roadmap Badge ─── */

interface RoadmapBadgeProps {
  label: string;
  type: 'submit' | 'secure' | 'launch' | 'ongoing' | 'pitch';
}

function RoadmapBadge({ label, type }: RoadmapBadgeProps) {
  const styles = {
    submit: 'bg-indigo-500/15 text-indigo-600 dark:text-indigo-400',
    secure: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
    launch: 'bg-purple-500/15 text-purple-600 dark:text-purple-400',
    ongoing: 'bg-cyan-500/15 text-cyan-600 dark:text-cyan-400',
    pitch: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
  };

  return (
    <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${styles[type]}`}>
      {label}
    </span>
  );
}

export default StrategicPlanPage;
