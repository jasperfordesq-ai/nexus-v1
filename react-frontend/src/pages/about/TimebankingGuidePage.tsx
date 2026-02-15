/**
 * Timebanking Guide Page - How timebanking works, impact stats, values, and CTAs
 *
 * Tenant-specific "About" page for the hOUR Timebank community.
 * Visual sections:
 * 1. Hero with subtitle
 * 2. Impact stats row (3 stat cards)
 * 3. How It Works - 3 simple steps
 * 4. Fundamental Values
 * 5. CTA card
 */

import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button } from '@heroui/react';
import {
  Handshake,
  Clock,
  Users,
  Heart,
  ArrowRight,
  TrendingUp,
  Sparkles,
  BookOpen,
  Gem,
  RefreshCw,
  Network,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { PageMeta } from '@/components/seo';
import { Breadcrumbs } from '@/components/navigation/Breadcrumbs';
import { useTenant, useAuth } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { RelatedPages } from './RelatedPages';

/* ───────────────────────── Data ───────────────────────── */

const impactStats = [
  {
    value: '16:1',
    label: 'Social Return on Investment',
    description: 'For every \u20AC1 invested, \u20AC16 of social value is created',
    color: 'from-indigo-500 to-blue-500',
    bgAccent: 'bg-indigo-500/10',
    textAccent: 'text-indigo-600 dark:text-indigo-400',
    icon: TrendingUp,
  },
  {
    value: '100%',
    label: 'Improved Wellbeing',
    description: 'Members report feeling better connected and supported',
    color: 'from-emerald-500 to-teal-500',
    bgAccent: 'bg-emerald-500/10',
    textAccent: 'text-emerald-600 dark:text-emerald-400',
    icon: Heart,
  },
  {
    value: '95%',
    label: 'Socially Connected',
    description: 'Members feel more integrated in their community',
    color: 'from-amber-500 to-orange-500',
    bgAccent: 'bg-amber-500/10',
    textAccent: 'text-amber-600 dark:text-amber-400',
    icon: Users,
  },
];

const steps = [
  {
    icon: Handshake,
    title: 'Give an Hour',
    description:
      'Share a skill you love \u2014 from practical help to a friendly chat or a lift to the shops.',
    color: 'from-indigo-500 to-purple-500',
  },
  {
    icon: Clock,
    title: 'Earn a Credit',
    description:
      'You automatically earn one Time Credit for every hour you spend helping another member.',
    color: 'from-emerald-500 to-teal-500',
  },
  {
    icon: Users,
    title: 'Get Help',
    description:
      'Spend your credit to get support, learn a new skill, or join a community work day.',
    color: 'from-amber-500 to-orange-500',
  },
];

const values = [
  {
    icon: Gem,
    title: 'We Are All Assets',
    description:
      'Everyone has something valuable to offer. Timebanking recognises the unique contributions of every member, regardless of background or circumstance.',
    color: 'text-indigo-500 dark:text-indigo-400',
    bg: 'bg-indigo-500/15',
  },
  {
    icon: RefreshCw,
    title: 'Redefining Work',
    description:
      'Raising children, caring for elders, volunteering, and community building \u2014 timebanking values the essential work that keeps communities strong.',
    color: 'text-emerald-500 dark:text-emerald-400',
    bg: 'bg-emerald-500/15',
  },
  {
    icon: Sparkles,
    title: 'Reciprocity',
    description:
      'Giving and receiving go hand in hand. When you help someone, you create a ripple of goodwill that comes back to strengthen the whole community.',
    color: 'text-amber-500 dark:text-amber-400',
    bg: 'bg-amber-500/15',
  },
  {
    icon: Network,
    title: 'Social Networks',
    description:
      'Timebanking weaves a web of trust and mutual support. Every exchange builds social capital and creates lasting connections between neighbours.',
    color: 'text-rose-500 dark:text-rose-400',
    bg: 'bg-rose-500/15',
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

export function TimebankingGuidePage() {
  usePageTitle('Timebanking Guide');
  const { tenantPath } = useTenant();
  const { isAuthenticated } = useAuth();

  return (
    <>
    <PageMeta
      title="Timebanking Guide"
      description="Learn how timebanking works: give an hour, earn a credit, get help. Discover the values and proven impact behind hOUR Timebank."
    />
    <div className="-mx-4 sm:-mx-6 lg:-mx-8 -my-6 sm:-my-8">
      {/* ─── Breadcrumbs ─── */}
      <div className="px-4 sm:px-6 lg:px-8 pt-6">
        <Breadcrumbs items={[
          { label: 'About', href: '/about' },
          { label: 'Timebanking Guide' },
        ]} />
      </div>

      {/* ─── Hero Section ─── */}
      <section className="relative py-16 sm:py-24 px-4 sm:px-6 lg:px-8 overflow-hidden">
        {/* Background decoration */}
        <div className="absolute inset-0 pointer-events-none opacity-20" aria-hidden="true">
          <div className="absolute top-10 left-1/4 w-72 h-72 bg-indigo-500 rounded-full blur-3xl" />
          <div className="absolute bottom-10 right-1/4 w-72 h-72 bg-emerald-500 rounded-full blur-3xl" />
        </div>

        <div className="max-w-4xl mx-auto text-center relative z-10">
          <motion.div {...fadeInUp} transition={{ duration: 0.6 }}>
            <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-500/20 to-emerald-500/20 mb-6">
              <BookOpen className="w-8 h-8 text-indigo-500 dark:text-indigo-400" aria-hidden="true" />
            </div>
          </motion.div>

          <motion.h1
            {...fadeInUp}
            transition={{ duration: 0.6, delay: 0.1 }}
            className="text-3xl sm:text-4xl md:text-5xl font-bold text-theme-primary mb-6"
          >
            Timebanking Guide
          </motion.h1>

          <motion.p
            {...fadeInUp}
            transition={{ duration: 0.6, delay: 0.2 }}
            className="text-lg sm:text-xl text-theme-muted max-w-2xl mx-auto"
          >
            Give an hour, get an hour. It&apos;s that simple.
          </motion.p>
        </div>
      </section>

      {/* ─── Impact Stats ─── */}
      <section className="py-12 sm:py-16 px-4 sm:px-6 lg:px-8 bg-gradient-to-b from-transparent via-indigo-500/5 to-transparent">
        <div className="max-w-5xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            className="text-center mb-10"
          >
            <h2 className="text-2xl sm:text-3xl font-bold text-theme-primary mb-3">
              Proven Impact
            </h2>
            <p className="text-theme-muted max-w-lg mx-auto">
              Independent research confirms the transformative power of timebanking.
            </p>
          </motion.div>

          <motion.div
            initial="initial"
            whileInView="animate"
            viewport={{ once: true }}
            variants={stagger}
            className="grid sm:grid-cols-3 gap-6"
          >
            {impactStats.map((stat) => (
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
        </div>
      </section>

      {/* ─── How It Works: 3 Simple Steps ─── */}
      <section className="py-16 sm:py-20 px-4 sm:px-6 lg:px-8">
        <div className="max-w-5xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            className="text-center mb-12"
          >
            <h2 className="text-2xl sm:text-3xl font-bold text-theme-primary mb-3">
              How It Works: 3 Simple Steps
            </h2>
            <p className="text-theme-muted max-w-lg mx-auto">
              Timebanking is easy. Everyone&apos;s hour is worth the same, no matter what skill you share.
            </p>
          </motion.div>

          <div className="grid sm:grid-cols-3 gap-8">
            {steps.map((step, index) => (
              <motion.div
                key={step.title}
                initial={{ opacity: 0, y: 30 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true }}
                transition={{ delay: index * 0.15 }}
              >
                <GlassCard className="p-8 h-full text-center relative group hover:scale-[1.02] transition-transform">
                  {/* Step number badge */}
                  <div className="absolute -top-3 -right-3 w-9 h-9 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white text-sm font-bold shadow-lg">
                    {index + 1}
                  </div>

                  <div
                    className={`inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br ${step.color} mb-5`}
                  >
                    <step.icon className="w-8 h-8 text-white" aria-hidden="true" />
                  </div>

                  <h3 className="text-lg font-semibold text-theme-primary mb-3">{step.title}</h3>
                  <p className="text-theme-muted leading-relaxed">{step.description}</p>
                </GlassCard>
              </motion.div>
            ))}
          </div>
        </div>
      </section>

      {/* ─── Our Fundamental Values ─── */}
      <section className="py-16 sm:py-20 px-4 sm:px-6 lg:px-8 bg-gradient-to-b from-transparent via-purple-500/5 to-transparent">
        <div className="max-w-5xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            className="text-center mb-12"
          >
            <h2 className="text-2xl sm:text-3xl font-bold text-theme-primary mb-3">
              Our Fundamental Values
            </h2>
            <p className="text-theme-muted max-w-lg mx-auto">
              The principles that guide every hour shared in our community.
            </p>
          </motion.div>

          <div className="grid sm:grid-cols-2 gap-6">
            {values.map((value, index) => (
              <motion.div
                key={value.title}
                initial={{ opacity: 0, x: index % 2 === 0 ? -20 : 20 }}
                whileInView={{ opacity: 1, x: 0 }}
                viewport={{ once: true }}
                transition={{ delay: index * 0.1 }}
              >
                <GlassCard className="p-6 h-full">
                  <div className="flex items-start gap-4">
                    <div className={`flex-shrink-0 p-3 rounded-xl ${value.bg}`}>
                      <value.icon className={`w-6 h-6 ${value.color}`} aria-hidden="true" />
                    </div>
                    <div>
                      <h3 className="font-semibold text-theme-primary text-lg mb-1">
                        {value.title}
                      </h3>
                      <p className="text-theme-muted text-sm leading-relaxed">
                        {value.description}
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
      <RelatedPages current="/timebanking-guide" />

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
                <div className="absolute top-0 right-0 w-64 h-64 bg-gradient-to-br from-indigo-500 to-purple-500 rounded-full blur-3xl" />
                <div className="absolute bottom-0 left-0 w-64 h-64 bg-gradient-to-tr from-emerald-500 to-teal-500 rounded-full blur-3xl" />
              </div>

              <div className="relative z-10">
                <div className="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-br from-indigo-500/20 to-emerald-500/20 mb-6">
                  <Sparkles className="w-7 h-7 text-indigo-500 dark:text-indigo-400" aria-hidden="true" />
                </div>

                <h2 className="text-2xl sm:text-3xl font-bold text-theme-primary mb-4">
                  Ready to Start Sharing?
                </h2>
                <p className="text-theme-muted max-w-lg mx-auto mb-8">
                  Join a community where your time truly matters. Every hour you give creates a
                  ripple of positive change.
                </p>

                <div className="flex flex-col sm:flex-row gap-4 justify-center">
                  {isAuthenticated ? (
                    <Link to={tenantPath('/listings')}>
                      <Button
                        size="lg"
                        className="w-full sm:w-auto bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-semibold px-8"
                        endContent={<ArrowRight className="w-5 h-5" aria-hidden="true" />}
                      >
                        Browse Listings
                      </Button>
                    </Link>
                  ) : (
                    <Link to={tenantPath('/register')}>
                      <Button
                        size="lg"
                        className="w-full sm:w-auto bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-semibold px-8"
                        endContent={<ArrowRight className="w-5 h-5" aria-hidden="true" />}
                      >
                        Join for Free
                      </Button>
                    </Link>
                  )}

                  <Link to={tenantPath('/partner')}>
                    <Button
                      size="lg"
                      variant="bordered"
                      className="w-full sm:w-auto border-theme-default text-theme-primary hover:bg-theme-hover"
                      startContent={<Handshake className="w-5 h-5" aria-hidden="true" />}
                    >
                      Partner With Us
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

export default TimebankingGuidePage;
