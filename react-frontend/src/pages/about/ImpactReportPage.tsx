/**
 * Impact Report Page - Full Social Impact Study (2023) for hOUR Timebank
 *
 * A long-form document-style page covering the complete SROI analysis,
 * member outcomes, activity data, case studies, and recommendations.
 */

import { useState, useEffect, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button } from '@heroui/react';
import {
  FileText,
  BookOpen,
  BarChart3,
  Activity,
  Users,
  Calculator,
  MessageSquare,
  Lightbulb,
  Quote,
  Download,
  ExternalLink,
  ChevronRight,
  TrendingUp,
  Heart,
  ArrowUp,
  Clock,
  LogIn,
  ArrowRightLeft,
  Wallet,
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

const FULL_REPORT_PDF = resolveAssetUrl('/uploads/tenants/hour-timebank/TBI-Social-Impact-Study-Final-Full-Report-May-23.pdf');
const EXEC_SUMMARY_PDF = resolveAssetUrl('/uploads/tenants/hour-timebank/TBI-Social-Impact-Study-Executive-Summary-Design-Version-Final.pdf');

/* ───────────────────────── Animations ───────────────────────── */

const fadeInUp = {
  initial: { opacity: 0, y: 30 },
  animate: { opacity: 1, y: 0 },
};

/* ───────────────────────── Table of Contents ───────────────────────── */

const tocSections = [
  { id: 'introduction', label: 'Introduction & Context', icon: BookOpen },
  { id: 'literature', label: 'Literature Review', icon: FileText },
  { id: 'activity', label: 'TBI Activity 2021\u201322', icon: Activity },
  { id: 'impact', label: 'Impact & Demographics', icon: Users },
  { id: 'sroi', label: 'SROI Calculation', icon: Calculator },
  { id: 'discussion', label: 'Discussion & Learning', icon: MessageSquare },
  { id: 'recommendations', label: 'Recommendations', icon: Lightbulb },
];

/* ───────────────────────── Component ───────────────────────── */

export function ImpactReportPage() {
  const { tenantPath } = useTenant();
  usePageTitle('Impact Report');
  const [activeSection, setActiveSection] = useState('introduction');

  const handleScroll = useCallback(() => {
    const sections = tocSections.map((s) => ({
      id: s.id,
      el: document.getElementById(s.id),
    }));
    for (let i = sections.length - 1; i >= 0; i--) {
      const el = sections[i].el;
      if (el) {
        const rect = el.getBoundingClientRect();
        if (rect.top <= 120) {
          setActiveSection(sections[i].id);
          break;
        }
      }
    }
  }, []);

  useEffect(() => {
    window.addEventListener('scroll', handleScroll, { passive: true });
    return () => window.removeEventListener('scroll', handleScroll);
  }, [handleScroll]);

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
      title="Impact Report"
      description="Full 2023 Social Impact Study for hOUR Timebank: SROI analysis showing sixteen euros returned for every euro invested, with member outcomes and case studies."
    />
    <div className="-mx-4 sm:-mx-6 lg:-mx-8 -my-6 sm:-my-8">
      {/* ─── Breadcrumbs ─── */}
      <div className="px-4 sm:px-6 lg:px-8 pt-6">
        <Breadcrumbs items={[
          { label: 'About', href: '/about' },
          { label: 'Impact Report' },
        ]} />
      </div>

      {/* ─── Hero Section ─── */}
      <section className="relative py-16 sm:py-24 px-4 sm:px-6 lg:px-8 overflow-hidden">
        <div className="absolute inset-0 pointer-events-none opacity-20" aria-hidden="true">
          <div className="absolute top-10 left-1/4 w-72 h-72 bg-emerald-500 rounded-full blur-3xl" />
          <div className="absolute bottom-10 right-1/4 w-72 h-72 bg-cyan-500 rounded-full blur-3xl" />
        </div>

        <div className="max-w-4xl mx-auto text-center relative z-10">
          <motion.div {...fadeInUp} transition={{ duration: 0.6 }}>
            <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-emerald-500/20 to-cyan-500/20 mb-6">
              <BarChart3 className="w-8 h-8 text-emerald-500 dark:text-emerald-400" aria-hidden="true" />
            </div>
          </motion.div>

          <motion.h1
            {...fadeInUp}
            transition={{ duration: 0.6, delay: 0.1 }}
            className="text-3xl sm:text-4xl md:text-5xl font-bold text-theme-primary mb-4"
          >
            Social Impact Study
          </motion.h1>

          <motion.p
            {...fadeInUp}
            transition={{ duration: 0.6, delay: 0.2 }}
            className="text-xl text-theme-muted mb-2"
          >
            hOUR Timebank &mdash; 2023
          </motion.p>

          <motion.p
            {...fadeInUp}
            transition={{ duration: 0.6, delay: 0.3 }}
            className="text-sm text-theme-subtle max-w-xl mx-auto mb-8"
          >
            A comprehensive Social Return on Investment (SROI) analysis examining
            the social, economic, and wellbeing outcomes of timebanking in Ireland.
          </motion.p>

          <motion.div
            {...fadeInUp}
            transition={{ duration: 0.6, delay: 0.4 }}
            className="flex flex-col sm:flex-row gap-3 justify-center"
          >
            <Button
              as="a"
              href={FULL_REPORT_PDF}
              target="_blank"
              rel="noopener noreferrer"
              className="bg-gradient-to-r from-emerald-500 to-teal-600 text-white font-semibold"
              startContent={<Download className="w-4 h-4" aria-hidden="true" />}
            >
              Download Full Report (PDF)
            </Button>
            <Button
              as="a"
              href={EXEC_SUMMARY_PDF}
              target="_blank"
              rel="noopener noreferrer"
              variant="bordered"
              className="border-theme-default text-theme-primary hover:bg-theme-hover"
              startContent={<ExternalLink className="w-4 h-4" aria-hidden="true" />}
            >
              Executive Summary (PDF)
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
              <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-2">
                {tocSections.map((section, index) => (
                  <button
                    key={section.id}
                    type="button"
                    onClick={() => scrollTo(section.id)}
                    className={`flex items-center gap-2 px-3 py-2 rounded-lg text-left transition-colors text-sm ${
                      activeSection === section.id
                        ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 font-medium'
                        : 'text-theme-muted hover:bg-theme-hover/50 hover:text-theme-primary'
                    }`}
                  >
                    <span className="flex-shrink-0 w-6 h-6 rounded-md bg-gradient-to-br from-emerald-500/20 to-teal-500/20 flex items-center justify-center text-xs font-bold text-emerald-600 dark:text-emerald-400">
                      {index + 1}
                    </span>
                    <span className="truncate">{section.label}</span>
                  </button>
                ))}
              </div>
            </GlassCard>
          </motion.div>
        </div>
      </section>

      {/* ─── Section 1: Introduction & Context ─── */}
      <section id="introduction" className="py-12 px-4 sm:px-6 lg:px-8">
        <div className="max-w-4xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
          >
            <SectionHeading icon={BookOpen} number={1} title="Introduction & Context" />

            <div className="space-y-6 mt-6">
              <GlassCard className="p-6">
                <p className="text-theme-muted text-sm leading-relaxed mb-4">
                  hOUR Timebank Ireland (TBI) was established to address social isolation and
                  promote community resilience through a time-based exchange system. This study
                  was conducted to independently measure the social return on investment of timebanking
                  activities during the 2021&ndash;2022 period.
                </p>

                <h3 className="text-base font-semibold text-theme-primary mb-3">Study Objectives</h3>
                <ul className="space-y-2">
                  {[
                    'Measure the social value generated by hOUR Timebank activities',
                    'Quantify wellbeing and community connection improvements for members',
                    'Calculate Social Return on Investment (SROI) ratio',
                    'Identify key success factors and areas for improvement',
                    'Provide evidence to inform future funding and strategic decisions',
                  ].map((item) => (
                    <li key={item} className="flex items-start gap-2 text-sm text-theme-muted">
                      <ChevronRight className="w-4 h-4 text-emerald-500 dark:text-emerald-400 flex-shrink-0 mt-0.5" aria-hidden="true" />
                      <span>{item}</span>
                    </li>
                  ))}
                </ul>
              </GlassCard>

              {/* Case Study: Monica */}
              <CaseStudyCard
                name="Monica"
                quote="Before joining hOUR Timebank, I was barely leaving the house. Now I have a reason
                  to get up in the morning. I teach Italian to a group of members every week and in return
                  I've had help with my garden, lifts to appointments, and most importantly &mdash;
                  real friendships."
                accent="emerald"
              />
            </div>
          </motion.div>
        </div>
      </section>

      {/* ─── Section 2: Literature Review ─── */}
      <section id="literature" className="py-12 px-4 sm:px-6 lg:px-8 bg-gradient-to-b from-transparent via-emerald-500/5 to-transparent">
        <div className="max-w-4xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
          >
            <SectionHeading icon={FileText} number={2} title="Literature Review" />

            <div className="mt-6">
              <GlassCard className="p-6">
                <p className="text-theme-muted text-sm leading-relaxed mb-4">
                  International research consistently demonstrates that timebanking delivers
                  measurable benefits across multiple domains. The literature review for this study
                  examined evidence from the UK, US, Japan, and New Zealand.
                </p>

                <div className="grid sm:grid-cols-2 gap-4 mt-4">
                  {[
                    {
                      title: 'Social Isolation',
                      description: 'Timebanking reduces loneliness by creating structured opportunities for regular social interaction and reciprocal relationships.',
                      color: 'text-indigo-500 dark:text-indigo-400',
                      bg: 'bg-indigo-500/10',
                    },
                    {
                      title: 'Mental Health',
                      description: 'Members report improved self-esteem, sense of purpose, and reduced symptoms of depression and anxiety through meaningful contribution.',
                      color: 'text-rose-500 dark:text-rose-400',
                      bg: 'bg-rose-500/10',
                    },
                    {
                      title: 'Community Resilience',
                      description: 'Time-based exchange systems strengthen neighbourhood bonds and create informal support networks that persist beyond formal activities.',
                      color: 'text-amber-500 dark:text-amber-400',
                      bg: 'bg-amber-500/10',
                    },
                    {
                      title: 'Economic Value',
                      description: 'SROI analyses in the UK have found returns of between £3 and £8 per £1 invested, making timebanking a cost-effective social intervention.',
                      color: 'text-emerald-500 dark:text-emerald-400',
                      bg: 'bg-emerald-500/10',
                    },
                  ].map((item) => (
                    <div key={item.title} className={`p-4 rounded-xl ${item.bg}`}>
                      <h4 className={`text-sm font-semibold ${item.color} mb-1`}>{item.title}</h4>
                      <p className="text-xs text-theme-muted leading-relaxed">{item.description}</p>
                    </div>
                  ))}
                </div>
              </GlassCard>
            </div>
          </motion.div>
        </div>
      </section>

      {/* ─── Section 3: TBI Activity ─── */}
      <section id="activity" className="py-12 px-4 sm:px-6 lg:px-8">
        <div className="max-w-4xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
          >
            <SectionHeading icon={Activity} number={3} title="TBI Activity 2021\u201322" />

            <div className="space-y-6 mt-6">
              <p className="text-theme-muted text-sm leading-relaxed">
                The study period captured a comprehensive view of platform usage across the hOUR Timebank
                network during 2021&ndash;2022. The following metrics reflect the breadth of community engagement.
              </p>

              {/* Activity Stats Table */}
              <GlassCard className="overflow-hidden">
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead>
                      <tr className="bg-gradient-to-r from-emerald-500/10 to-teal-500/10">
                        <th className="px-6 py-4 text-left font-semibold text-theme-primary">Metric</th>
                        <th className="px-6 py-4 text-right font-semibold text-theme-primary">Value</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-theme-default">
                      {[
                        { icon: Wallet, metric: 'Gross Income', value: '1,941.10 credits', highlight: false },
                        { icon: ArrowRightLeft, metric: 'Transfers Completed', value: '559', highlight: false },
                        { icon: LogIn, metric: 'Member Logins', value: '1,560', highlight: false },
                        { icon: Clock, metric: 'Community Account Balance', value: '1,007,748.95 credits', highlight: true },
                      ].map((row) => (
                        <tr key={row.metric} className={row.highlight ? 'bg-emerald-500/5' : ''}>
                          <td className="px-6 py-4">
                            <div className="flex items-center gap-3">
                              <div className="p-1.5 rounded-lg bg-emerald-500/10">
                                <row.icon className="w-4 h-4 text-emerald-500 dark:text-emerald-400" aria-hidden="true" />
                              </div>
                              <span className="text-theme-muted">{row.metric}</span>
                            </div>
                          </td>
                          <td className={`px-6 py-4 text-right font-semibold ${row.highlight ? 'text-emerald-600 dark:text-emerald-400' : 'text-theme-primary'}`}>
                            {row.value}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </GlassCard>

              <p className="text-xs text-theme-subtle leading-relaxed">
                Note: The Community Account balance reflects cumulative time credits across the entire
                network, demonstrating the scale of social capital generated through the platform.
              </p>
            </div>
          </motion.div>
        </div>
      </section>

      {/* ─── Section 4: Impact & Demographics ─── */}
      <section id="impact" className="py-12 px-4 sm:px-6 lg:px-8 bg-gradient-to-b from-transparent via-indigo-500/5 to-transparent">
        <div className="max-w-4xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
          >
            <SectionHeading icon={Users} number={4} title="Impact & Demographics" />

            <div className="space-y-6 mt-6">
              <GlassCard className="p-6">
                <h3 className="text-base font-semibold text-theme-primary mb-4">Member Demographics</h3>
                <p className="text-theme-muted text-sm leading-relaxed mb-4">
                  The survey captured responses from members across a range of age groups, with the strongest
                  representation in the 45&ndash;64 and 65+ categories &mdash; demographics most at risk of
                  social isolation.
                </p>

                <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                  {[
                    { band: '18\u201334', pct: '12%', color: 'from-cyan-500/20 to-blue-500/20' },
                    { band: '35\u201344', pct: '18%', color: 'from-indigo-500/20 to-purple-500/20' },
                    { band: '45\u201364', pct: '42%', color: 'from-emerald-500/20 to-teal-500/20' },
                    { band: '65+', pct: '28%', color: 'from-amber-500/20 to-orange-500/20' },
                  ].map((age) => (
                    <div key={age.band} className={`p-3 rounded-xl bg-gradient-to-br ${age.color} text-center`}>
                      <p className="text-xs text-theme-subtle mb-1">Age {age.band}</p>
                      <p className="text-lg font-bold text-theme-primary">{age.pct}</p>
                    </div>
                  ))}
                </div>
              </GlassCard>

              {/* Outcomes */}
              <div className="grid sm:grid-cols-2 gap-4">
                <GlassCard className="p-6 relative overflow-hidden">
                  <div className="absolute top-0 right-0 w-24 h-24 bg-emerald-500/10 rounded-full blur-2xl" aria-hidden="true" />
                  <div className="relative">
                    <div className="flex items-center gap-3 mb-3">
                      <div className="p-2 rounded-lg bg-emerald-500/15">
                        <Users className="w-5 h-5 text-emerald-500 dark:text-emerald-400" aria-hidden="true" />
                      </div>
                      <h3 className="font-semibold text-theme-primary">Social Connection</h3>
                    </div>
                    <p className="text-4xl font-bold text-emerald-600 dark:text-emerald-400 mb-1">95%</p>
                    <p className="text-sm text-theme-muted">
                      of members reported feeling more socially connected as a result of participating
                      in hOUR Timebank.
                    </p>
                  </div>
                </GlassCard>

                <GlassCard className="p-6 relative overflow-hidden">
                  <div className="absolute top-0 right-0 w-24 h-24 bg-rose-500/10 rounded-full blur-2xl" aria-hidden="true" />
                  <div className="relative">
                    <div className="flex items-center gap-3 mb-3">
                      <div className="p-2 rounded-lg bg-rose-500/15">
                        <Heart className="w-5 h-5 text-rose-500 dark:text-rose-400" aria-hidden="true" />
                      </div>
                      <h3 className="font-semibold text-theme-primary">Wellbeing</h3>
                    </div>
                    <p className="text-4xl font-bold text-rose-600 dark:text-rose-400 mb-1">100%</p>
                    <p className="text-sm text-theme-muted">
                      of participants reported improved wellbeing, spanning mental health,
                      confidence, and sense of purpose.
                    </p>
                  </div>
                </GlassCard>
              </div>

              {/* Case Study: Elaine */}
              <CaseStudyCard
                name="Elaine"
                quote="I was going through a very difficult time after losing my partner. The timebank gave
                  me a lifeline. I started offering baking lessons and in return received company, conversation,
                  and a sense that I still had something valuable to give. It quite literally saved my life."
                accent="indigo"
              />
            </div>
          </motion.div>
        </div>
      </section>

      {/* ─── Section 5: SROI Calculation ─── */}
      <section id="sroi" className="py-12 px-4 sm:px-6 lg:px-8">
        <div className="max-w-4xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
          >
            <SectionHeading icon={Calculator} number={5} title="SROI Calculation" />

            <div className="space-y-6 mt-6">
              {/* Big SROI result */}
              <GlassCard className="p-8 sm:p-10 text-center relative overflow-hidden">
                <div className="absolute inset-0 opacity-10 pointer-events-none" aria-hidden="true">
                  <div className="absolute top-0 left-0 w-64 h-64 bg-gradient-to-br from-emerald-500 to-teal-500 rounded-full blur-3xl" />
                  <div className="absolute bottom-0 right-0 w-64 h-64 bg-gradient-to-tr from-amber-500 to-orange-500 rounded-full blur-3xl" />
                </div>

                <div className="relative z-10">
                  <div className="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-500 mb-4">
                    <TrendingUp className="w-7 h-7 text-white" aria-hidden="true" />
                  </div>
                  <h3 className="text-lg font-semibold text-theme-primary mb-2">Social Return on Investment</h3>
                  <p className="text-5xl sm:text-6xl font-extrabold text-gradient mb-3">
                    &euro;16 : &euro;1
                  </p>
                  <p className="text-theme-muted text-sm max-w-md mx-auto">
                    For every &euro;1 invested in hOUR Timebank, &euro;16 of social value
                    is generated for members and the wider community.
                  </p>
                </div>
              </GlassCard>

              {/* Breakdown table */}
              <GlassCard className="overflow-hidden">
                <div className="p-4 sm:p-6 border-b border-theme-default">
                  <h3 className="text-base font-semibold text-theme-primary">SROI Breakdown</h3>
                </div>
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead>
                      <tr className="bg-gradient-to-r from-emerald-500/10 to-teal-500/10">
                        <th className="px-6 py-3 text-left font-semibold text-theme-primary">Component</th>
                        <th className="px-6 py-3 text-right font-semibold text-theme-primary">Value</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-theme-default">
                      <tr>
                        <td className="px-6 py-3 text-theme-muted">Total Investment (Input)</td>
                        <td className="px-6 py-3 text-right font-semibold text-theme-primary">&euro;50,000</td>
                      </tr>
                      <tr>
                        <td className="px-6 py-3 text-theme-muted">Total Present Value (Social Outcomes)</td>
                        <td className="px-6 py-3 text-right font-semibold text-emerald-600 dark:text-emerald-400">&euro;803,184</td>
                      </tr>
                      <tr className="bg-emerald-500/5">
                        <td className="px-6 py-3 font-semibold text-theme-primary">Net Social Value</td>
                        <td className="px-6 py-3 text-right font-bold text-emerald-600 dark:text-emerald-400">&euro;753,184</td>
                      </tr>
                      <tr className="bg-gradient-to-r from-emerald-500/10 to-teal-500/10">
                        <td className="px-6 py-3 font-bold text-theme-primary">SROI Ratio</td>
                        <td className="px-6 py-3 text-right font-extrabold text-emerald-600 dark:text-emerald-400 text-lg">&euro;16.06 : &euro;1</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </GlassCard>

              <GlassCard className="p-5">
                <div className="flex items-start gap-3">
                  <div className="p-2 rounded-lg bg-amber-500/15 flex-shrink-0">
                    <ArrowUp className="w-4 h-4 text-amber-500 dark:text-amber-400" aria-hidden="true" />
                  </div>
                  <p className="text-sm text-theme-muted leading-relaxed">
                    This SROI ratio of <strong className="text-theme-primary">&euro;16 for every &euro;1</strong> significantly
                    exceeds international benchmarks for timebanking programmes and places hOUR Timebank
                    among the highest-performing social interventions documented in Ireland.
                  </p>
                </div>
              </GlassCard>
            </div>
          </motion.div>
        </div>
      </section>

      {/* ─── Section 6: Discussion & Learning ─── */}
      <section id="discussion" className="py-12 px-4 sm:px-6 lg:px-8 bg-gradient-to-b from-transparent via-emerald-500/5 to-transparent">
        <div className="max-w-4xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
          >
            <SectionHeading icon={MessageSquare} number={6} title="Discussion & Learning" />

            <div className="space-y-6 mt-6">
              <GlassCard className="p-6">
                <p className="text-theme-muted text-sm leading-relaxed mb-4">
                  The study identified several key factors that contribute to the exceptional impact of hOUR Timebank:
                </p>

                <div className="space-y-4">
                  {[
                    {
                      title: 'Reciprocity as empowerment',
                      description: 'Unlike one-directional volunteering, timebanking enables members to both give and receive, preserving dignity and fostering mutual respect.',
                    },
                    {
                      title: 'Low-barrier participation',
                      description: 'The simplicity of the time-credit model makes it accessible to people of all backgrounds, including those who may feel excluded from traditional economic systems.',
                    },
                    {
                      title: 'Broker-supported coordination',
                      description: 'The role of the timebank broker/coordinator was highlighted as critical to success, providing personal introductions, conflict resolution, and continuity.',
                    },
                    {
                      title: 'Digital and in-person hybrid',
                      description: 'The combination of a digital platform with real-world meetups and events creates both convenience and meaningful human connection.',
                    },
                  ].map((item) => (
                    <div key={item.title} className="flex items-start gap-3">
                      <ChevronRight className="w-4 h-4 text-emerald-500 dark:text-emerald-400 flex-shrink-0 mt-1" aria-hidden="true" />
                      <div>
                        <h4 className="text-sm font-semibold text-theme-primary">{item.title}</h4>
                        <p className="text-sm text-theme-muted leading-relaxed">{item.description}</p>
                      </div>
                    </div>
                  ))}
                </div>
              </GlassCard>
            </div>
          </motion.div>
        </div>
      </section>

      {/* ─── Section 7: Recommendations ─── */}
      <section id="recommendations" className="py-12 px-4 sm:px-6 lg:px-8">
        <div className="max-w-4xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
          >
            <SectionHeading icon={Lightbulb} number={7} title="Recommendations" />

            <div className="space-y-6 mt-6">
              <GlassCard className="overflow-hidden">
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead>
                      <tr className="bg-gradient-to-r from-amber-500/10 to-orange-500/10">
                        <th className="px-6 py-4 text-left font-semibold text-theme-primary w-8">#</th>
                        <th className="px-6 py-4 text-left font-semibold text-theme-primary">Recommendation</th>
                        <th className="px-6 py-4 text-left font-semibold text-theme-primary">Priority</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-theme-default">
                      {[
                        { rec: 'Secure sustainable, multi-year funding to enable long-term planning and scaling', priority: 'Critical' },
                        { rec: 'Increase membership through partnerships with HSE, local authorities, and community organisations', priority: 'High' },
                        { rec: 'Explore integration with social prescribing networks and GP referral pathways', priority: 'High' },
                        { rec: 'Invest in the digital platform to improve member experience and data collection', priority: 'Medium' },
                        { rec: 'Develop a national scaling plan with regional hubs and local coordinators', priority: 'Medium' },
                        { rec: 'Commission follow-up SROI studies at regular intervals to track ongoing impact', priority: 'Medium' },
                      ].map((row, idx) => (
                        <tr key={idx} className={idx % 2 === 1 ? 'bg-theme-hover/20' : ''}>
                          <td className="px-6 py-3 text-theme-subtle font-medium">{idx + 1}</td>
                          <td className="px-6 py-3 text-theme-muted">{row.rec}</td>
                          <td className="px-6 py-3">
                            <span className={`inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium ${
                              row.priority === 'Critical'
                                ? 'bg-rose-500/15 text-rose-600 dark:text-rose-400'
                                : row.priority === 'High'
                                ? 'bg-amber-500/15 text-amber-600 dark:text-amber-400'
                                : 'bg-indigo-500/15 text-indigo-600 dark:text-indigo-400'
                            }`}>
                              {row.priority}
                            </span>
                          </td>
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
      <RelatedPages current="/impact-report" />

      {/* ─── Downloads & CTA ─── */}
      <section className="py-16 sm:py-20 px-4 sm:px-6 lg:px-8">
        <div className="max-w-4xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
          >
            <GlassCard className="p-10 sm:p-14 text-center relative overflow-hidden">
              <div className="absolute inset-0 opacity-10 pointer-events-none" aria-hidden="true">
                <div className="absolute top-0 right-0 w-64 h-64 bg-gradient-to-br from-emerald-500 to-teal-500 rounded-full blur-3xl" />
                <div className="absolute bottom-0 left-0 w-64 h-64 bg-gradient-to-tr from-indigo-500 to-purple-500 rounded-full blur-3xl" />
              </div>

              <div className="relative z-10">
                <h2 className="text-2xl sm:text-3xl font-bold text-theme-primary mb-4">
                  Download the Full Report
                </h2>
                <p className="text-theme-muted max-w-lg mx-auto mb-8">
                  Access the complete Social Impact Study with full methodology, data tables, and appendices.
                </p>

                <div className="flex flex-col sm:flex-row gap-3 justify-center mb-6">
                  <Button
                    as="a"
                    href={FULL_REPORT_PDF}
                    target="_blank"
                    rel="noopener noreferrer"
                    size="lg"
                    className="bg-gradient-to-r from-emerald-500 to-teal-600 text-white font-semibold"
                    startContent={<Download className="w-5 h-5" aria-hidden="true" />}
                  >
                    Full Report (PDF)
                  </Button>
                  <Button
                    as="a"
                    href={EXEC_SUMMARY_PDF}
                    target="_blank"
                    rel="noopener noreferrer"
                    size="lg"
                    variant="bordered"
                    className="border-theme-default text-theme-primary hover:bg-theme-hover"
                    startContent={<ExternalLink className="w-5 h-5" aria-hidden="true" />}
                  >
                    Executive Summary (PDF)
                  </Button>
                </div>

                <div className="pt-4 border-t border-theme-default">
                  <p className="text-sm text-theme-subtle mb-3">Have questions about our impact?</p>
                  <Link to={tenantPath('/contact')}>
                    <Button
                      variant="light"
                      className="text-emerald-600 dark:text-emerald-400"
                      startContent={<Mail className="w-4 h-4" aria-hidden="true" />}
                    >
                      Contact Us
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
      <div className="flex-shrink-0 w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-500/20 to-teal-500/20 flex items-center justify-center">
        <Icon className="w-5 h-5 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
      </div>
      <div>
        <p className="text-xs text-theme-subtle uppercase tracking-wider font-medium">Section {number}</p>
        <h2 className="text-xl sm:text-2xl font-bold text-theme-primary">{title}</h2>
      </div>
    </div>
  );
}

interface CaseStudyCardProps {
  name: string;
  quote: string;
  accent: 'emerald' | 'indigo' | 'amber';
}

function CaseStudyCard({ name, quote, accent }: CaseStudyCardProps) {
  const colorMap = {
    emerald: {
      bg: 'bg-emerald-500/5',
      border: 'border-emerald-500/20',
      icon: 'text-emerald-500/60',
      badge: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
    },
    indigo: {
      bg: 'bg-indigo-500/5',
      border: 'border-indigo-500/20',
      icon: 'text-indigo-500/60',
      badge: 'bg-indigo-500/15 text-indigo-600 dark:text-indigo-400',
    },
    amber: {
      bg: 'bg-amber-500/5',
      border: 'border-amber-500/20',
      icon: 'text-amber-500/60',
      badge: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
    },
  };

  const colors = colorMap[accent];

  return (
    <GlassCard className={`p-6 ${colors.bg} border ${colors.border}`}>
      <div className="flex items-center gap-2 mb-3">
        <span className={`inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium ${colors.badge}`}>
          Case Study
        </span>
        <span className="text-sm font-medium text-theme-primary">{name}</span>
      </div>
      <div className="flex gap-3">
        <Quote className={`w-5 h-5 ${colors.icon} flex-shrink-0 mt-0.5`} aria-hidden="true" />
        <p className="text-sm italic text-theme-muted leading-relaxed">
          &ldquo;{quote}&rdquo;
        </p>
      </div>
    </GlassCard>
  );
}

export default ImpactReportPage;
