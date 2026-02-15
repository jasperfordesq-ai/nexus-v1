/**
 * Impact Summary Page - hOUR Timebank social impact overview
 *
 * Highlights the key "1 to 16" social return on investment finding
 * with wellbeing outcomes, public health potential, and strategic document links.
 */

import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button } from '@heroui/react';
import {
  TrendingUp,
  Heart,
  Stethoscope,
  FileText,
  Compass,
  ArrowRight,
  Quote,
  Sparkles,
  Users,
  ShieldCheck,
  Mail,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { PageMeta } from '@/components/seo';
import { Breadcrumbs } from '@/components/navigation/Breadcrumbs';
import { useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { RelatedPages } from './RelatedPages';

/* ───────────────────────── Animations ───────────────────────── */

const fadeInUp = {
  initial: { opacity: 0, y: 30 },
  animate: { opacity: 1, y: 0 },
};

const stagger = {
  animate: { transition: { staggerChildren: 0.12 } },
};

/* ───────────────────────── Component ───────────────────────── */

export function ImpactSummaryPage() {
  const { tenantPath } = useTenant();
  usePageTitle('Our Impact');

  return (
    <>
    <PageMeta
      title="Our Impact"
      description="For every euro invested in hOUR Timebank, sixteen euros of social value is generated. 100% improved wellbeing, 95% socially connected."
    />
    <div className="-mx-4 sm:-mx-6 lg:-mx-8 -my-6 sm:-my-8">
      {/* ─── Breadcrumbs ─── */}
      <div className="px-4 sm:px-6 lg:px-8 pt-6">
        <Breadcrumbs items={[
          { label: 'About', href: '/about' },
          { label: 'Our Impact' },
        ]} />
      </div>

      {/* ─── Hero Section ─── */}
      <section className="relative py-16 sm:py-24 px-4 sm:px-6 lg:px-8 overflow-hidden">
        {/* Background blurs */}
        <div className="absolute inset-0 pointer-events-none opacity-20" aria-hidden="true">
          <div className="absolute top-10 left-1/4 w-72 h-72 bg-emerald-500 rounded-full blur-3xl" />
          <div className="absolute bottom-10 right-1/4 w-72 h-72 bg-indigo-500 rounded-full blur-3xl" />
        </div>

        <div className="max-w-4xl mx-auto text-center relative z-10">
          <motion.div {...fadeInUp} transition={{ duration: 0.6 }}>
            <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-emerald-500/20 to-teal-500/20 mb-6">
              <TrendingUp className="w-8 h-8 text-emerald-500 dark:text-emerald-400" aria-hidden="true" />
            </div>
          </motion.div>

          <motion.h1
            {...fadeInUp}
            transition={{ duration: 0.6, delay: 0.1 }}
            className="text-3xl sm:text-4xl md:text-5xl font-bold text-theme-primary mb-4"
          >
            Our Impact
          </motion.h1>

          {/* Hero stat */}
          <motion.div
            {...fadeInUp}
            transition={{ duration: 0.6, delay: 0.2 }}
            className="mb-6"
          >
            <GlassCard className="inline-block px-8 py-5 mx-auto">
              <h2 className="flex items-center gap-3 justify-center text-2xl sm:text-3xl md:text-4xl font-extrabold text-gradient">
                <Sparkles className="w-6 h-6 text-amber-500 dark:text-amber-400 flex-shrink-0" aria-hidden="true" />
                <span>
                  For Every &euro;1 Invested, We Generate &euro;16 in Social Value
                </span>
              </h2>
            </GlassCard>
          </motion.div>

          <motion.p
            {...fadeInUp}
            transition={{ duration: 0.6, delay: 0.3 }}
            className="text-lg text-theme-muted max-w-2xl mx-auto"
          >
            Independently validated by our 2023 Social Impact Study
          </motion.p>
        </div>
      </section>

      {/* ─── Key Stats Banner ─── */}
      <section className="py-10 px-4 sm:px-6 lg:px-8">
        <div className="max-w-5xl mx-auto">
          <motion.div
            initial="initial"
            whileInView="animate"
            viewport={{ once: true }}
            variants={stagger}
            className="grid grid-cols-2 sm:grid-cols-4 gap-4"
          >
            {[
              { value: '€16', label: 'Return per €1', icon: TrendingUp, color: 'text-emerald-500 dark:text-emerald-400', bg: 'bg-emerald-500/15' },
              { value: '100%', label: 'Improved Wellbeing', icon: Heart, color: 'text-rose-500 dark:text-rose-400', bg: 'bg-rose-500/15' },
              { value: '95%', label: 'Socially Connected', icon: Users, color: 'text-indigo-500 dark:text-indigo-400', bg: 'bg-indigo-500/15' },
              { value: '€803K', label: 'Social Value Created', icon: ShieldCheck, color: 'text-amber-500 dark:text-amber-400', bg: 'bg-amber-500/15' },
            ].map((stat) => (
              <motion.div key={stat.label} variants={fadeInUp}>
                <GlassCard className="p-5 text-center">
                  <div className={`inline-flex items-center justify-center w-10 h-10 rounded-xl ${stat.bg} mb-3`}>
                    <stat.icon className={`w-5 h-5 ${stat.color}`} aria-hidden="true" />
                  </div>
                  <p className="text-2xl sm:text-3xl font-bold text-gradient">{stat.value}</p>
                  <p className="text-sm text-theme-subtle mt-1">{stat.label}</p>
                </GlassCard>
              </motion.div>
            ))}
          </motion.div>
        </div>
      </section>

      {/* ─── Two-Column Impact Cards ─── */}
      <section className="py-12 sm:py-16 px-4 sm:px-6 lg:px-8">
        <div className="max-w-5xl mx-auto">
          <div className="grid md:grid-cols-2 gap-6">
            {/* Wellbeing Card */}
            <motion.div
              initial={{ opacity: 0, x: -20 }}
              whileInView={{ opacity: 1, x: 0 }}
              viewport={{ once: true }}
              transition={{ duration: 0.5 }}
            >
              <GlassCard className="p-6 sm:p-8 h-full relative overflow-hidden">
                {/* Green accent line */}
                <div className="absolute top-0 left-0 w-1 h-full bg-gradient-to-b from-emerald-500 to-teal-500 rounded-l" aria-hidden="true" />

                <div className="pl-4">
                  <div className="flex items-center gap-3 mb-4">
                    <div className="p-2.5 rounded-xl bg-emerald-500/15">
                      <Heart className="w-6 h-6 text-emerald-500 dark:text-emerald-400" aria-hidden="true" />
                    </div>
                    <h2 className="text-xl font-bold text-theme-primary">Profound Impact on Wellbeing</h2>
                  </div>

                  <div className="space-y-4">
                    <div className="flex items-start gap-3">
                      <div className="flex-shrink-0 mt-1 w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center">
                        <span className="text-sm font-bold text-emerald-600 dark:text-emerald-400">100%</span>
                      </div>
                      <p className="text-theme-muted text-sm leading-relaxed">
                        of participants reported <strong className="text-theme-primary">improved wellbeing</strong> as
                        a direct result of their involvement in hOUR Timebank.
                      </p>
                    </div>

                    <div className="flex items-start gap-3">
                      <div className="flex-shrink-0 mt-1 w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center">
                        <span className="text-sm font-bold text-emerald-600 dark:text-emerald-400">95%</span>
                      </div>
                      <p className="text-theme-muted text-sm leading-relaxed">
                        of members felt more <strong className="text-theme-primary">socially connected</strong> and
                        less isolated within their community.
                      </p>
                    </div>

                    {/* Blockquote */}
                    <div className="mt-6 p-4 rounded-xl bg-emerald-500/5 border border-emerald-500/20">
                      <div className="flex gap-3">
                        <Quote className="w-5 h-5 text-emerald-500/60 flex-shrink-0 mt-0.5" aria-hidden="true" />
                        <div>
                          <p className="text-sm italic text-theme-muted leading-relaxed">
                            &ldquo;The impact of hOUR Timebank has been <strong className="text-theme-primary">transformational
                            and lifesaving</strong> for its members.&rdquo;
                          </p>
                          <p className="text-xs text-theme-subtle mt-2">
                            &mdash; 2023 Social Impact Study
                          </p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </GlassCard>
            </motion.div>

            {/* Public Health Card */}
            <motion.div
              initial={{ opacity: 0, x: 20 }}
              whileInView={{ opacity: 1, x: 0 }}
              viewport={{ once: true }}
              transition={{ duration: 0.5 }}
            >
              <GlassCard className="p-6 sm:p-8 h-full relative overflow-hidden">
                {/* Blue accent line */}
                <div className="absolute top-0 left-0 w-1 h-full bg-gradient-to-b from-indigo-500 to-blue-500 rounded-l" aria-hidden="true" />

                <div className="pl-4">
                  <div className="flex items-center gap-3 mb-4">
                    <div className="p-2.5 rounded-xl bg-indigo-500/15">
                      <Stethoscope className="w-6 h-6 text-indigo-500 dark:text-indigo-400" aria-hidden="true" />
                    </div>
                    <h2 className="text-xl font-bold text-theme-primary">A Public Health Solution</h2>
                  </div>

                  <div className="space-y-4">
                    <p className="text-theme-muted text-sm leading-relaxed">
                      Timebanking has been identified as an <strong className="text-theme-primary">efficient and scalable
                      intervention</strong> to combat loneliness and social isolation &mdash; one of the most pressing
                      public health challenges of our time.
                    </p>

                    <p className="text-theme-muted text-sm leading-relaxed">
                      The study found that hOUR Timebank delivers measurable improvements across multiple wellbeing
                      indicators, making it a cost-effective complement to traditional health services.
                    </p>

                    {/* Blockquote */}
                    <div className="mt-6 p-4 rounded-xl bg-indigo-500/5 border border-indigo-500/20">
                      <div className="flex gap-3">
                        <Quote className="w-5 h-5 text-indigo-500/60 flex-shrink-0 mt-0.5" aria-hidden="true" />
                        <div>
                          <p className="text-sm italic text-theme-muted leading-relaxed">
                            &ldquo;Timebanking <strong className="text-theme-primary">could become part of a social
                            prescribing offering</strong>, providing a structured community-based alternative to
                            clinical interventions for loneliness and mental health.&rdquo;
                          </p>
                          <p className="text-xs text-theme-subtle mt-2">
                            &mdash; Social Impact Study Recommendation
                          </p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </GlassCard>
            </motion.div>
          </div>
        </div>
      </section>

      {/* ─── Strategic Documents ─── */}
      <section className="py-12 sm:py-16 px-4 sm:px-6 lg:px-8 bg-gradient-to-b from-transparent via-indigo-500/5 to-transparent">
        <div className="max-w-5xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            className="text-center mb-10"
          >
            <h2 className="text-2xl sm:text-3xl font-bold text-theme-primary mb-3">
              Our Strategic Documents
            </h2>
            <p className="text-theme-muted max-w-lg mx-auto">
              Explore our full impact study and strategic plan to learn more about our vision and results.
            </p>
          </motion.div>

          <div className="grid sm:grid-cols-2 gap-6 max-w-3xl mx-auto">
            {/* Impact Report Card */}
            <motion.div
              initial={{ opacity: 0, y: 20 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
              transition={{ delay: 0.1 }}
            >
              <Link to={tenantPath('/impact-report')}>
                <GlassCard hoverable className="p-6 h-full text-center group hover:scale-[1.02] transition-transform">
                  <div className="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-500 mb-4">
                    <FileText className="w-7 h-7 text-white" aria-hidden="true" />
                  </div>
                  <h3 className="text-lg font-semibold text-theme-primary mb-2">Impact Report</h3>
                  <p className="text-sm text-theme-muted mb-4">
                    Our full 2023 Social Impact Study with SROI analysis, member outcomes, and case studies.
                  </p>
                  <div className="flex items-center justify-center gap-2 text-sm font-medium text-emerald-600 dark:text-emerald-400 group-hover:gap-3 transition-all">
                    Read Report <ArrowRight className="w-4 h-4" aria-hidden="true" />
                  </div>
                </GlassCard>
              </Link>
            </motion.div>

            {/* Strategic Plan Card */}
            <motion.div
              initial={{ opacity: 0, y: 20 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
              transition={{ delay: 0.2 }}
            >
              <Link to={tenantPath('/strategic-plan')}>
                <GlassCard hoverable className="p-6 h-full text-center group hover:scale-[1.02] transition-transform">
                  <div className="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-500 mb-4">
                    <Compass className="w-7 h-7 text-white" aria-hidden="true" />
                  </div>
                  <h3 className="text-lg font-semibold text-theme-primary mb-2">Strategic Plan</h3>
                  <p className="text-sm text-theme-muted mb-4">
                    Our 2026&ndash;2030 strategy for growth, financial resilience, and building a connected Ireland.
                  </p>
                  <div className="flex items-center justify-center gap-2 text-sm font-medium text-indigo-600 dark:text-indigo-400 group-hover:gap-3 transition-all">
                    Read Plan <ArrowRight className="w-4 h-4" aria-hidden="true" />
                  </div>
                </GlassCard>
              </Link>
            </motion.div>
          </div>
        </div>
      </section>

      {/* ─── Related Pages ─── */}
      <RelatedPages current="/impact-summary" />

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
                <div className="absolute bottom-0 left-0 w-64 h-64 bg-gradient-to-tr from-indigo-500 to-purple-500 rounded-full blur-3xl" />
              </div>

              <div className="relative z-10">
                <h2 className="text-2xl sm:text-3xl font-bold text-theme-primary mb-4">
                  Want to Learn More?
                </h2>
                <p className="text-theme-muted max-w-lg mx-auto mb-8">
                  Get in touch to discuss our impact, partnership opportunities, or how timebanking
                  can benefit your community.
                </p>

                <Link to={tenantPath('/contact')}>
                  <Button
                    size="lg"
                    className="bg-gradient-to-r from-emerald-500 to-teal-600 text-white font-semibold px-8"
                    startContent={<Mail className="w-5 h-5" aria-hidden="true" />}
                  >
                    Get in Touch
                  </Button>
                </Link>
              </div>
            </GlassCard>
          </motion.div>
        </div>
      </section>
    </div>
    </>
  );
}

export default ImpactSummaryPage;
