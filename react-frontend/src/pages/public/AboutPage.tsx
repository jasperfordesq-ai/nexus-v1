// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * About Page - Platform showcase with mission, how it works, values, and stats
 *
 * Visual sections:
 * 1. Hero banner with mission statement
 * 2. How It Works — 4-step visual guide
 * 3. Core Values — icon cards
 * 4. Platform Stats — live from API
 * 5. CTA section
 */

import { useState, useEffect, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button } from '@heroui/react';
import {
  Hexagon,
  UserPlus,
  Search,
  Handshake,
  Coins,
  Scale,
  Heart,
  Shield,
  Sprout,
  Users,
  Clock,
  ListTodo,
  Globe,
  ArrowRight,
  HelpCircle,
  Mail,
  Code,
  Crown,
  Star,
  Award,
  BookOpen,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { PageMeta } from '@/components/seo';
import { useTenant, useAuth } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import contributors from '@/data/contributors.json';

/* ─────────────── Types ─────────────── */

interface PlatformStats {
  members: number;
  hours_exchanged: number;
  listings: number;
  skills: number;
  communities: number;
}

/* ─────────────── Data ─────────────── */

const steps = [
  {
    icon: UserPlus,
    title: 'Create Your Profile',
    description: 'Sign up for free and list the skills you can offer to your community.',
    color: 'from-indigo-500 to-blue-500',
  },
  {
    icon: Search,
    title: 'Find What You Need',
    description: 'Browse listings to discover services offered by members near you.',
    color: 'from-purple-500 to-pink-500',
  },
  {
    icon: Handshake,
    title: 'Exchange Services',
    description: 'Connect with members and arrange skill exchanges that work for both of you.',
    color: 'from-cyan-500 to-teal-500',
  },
  {
    icon: Coins,
    title: 'Earn & Spend Credits',
    description: 'Earn one time credit for every hour you give, and spend them on services you need.',
    color: 'from-amber-500 to-orange-500',
  },
];

const values = [
  {
    icon: Scale,
    title: 'Equality',
    description: "Every person's time is valued equally. One hour of gardening is worth the same as one hour of tutoring.",
    color: 'text-indigo-500 dark:text-indigo-400',
    bg: 'bg-indigo-500/15',
  },
  {
    icon: Heart,
    title: 'Community',
    description: 'We believe in building strong local connections. Every exchange strengthens the fabric of your neighbourhood.',
    color: 'text-rose-500 dark:text-rose-400',
    bg: 'bg-rose-500/15',
  },
  {
    icon: Shield,
    title: 'Trust & Safety',
    description: 'Reviews, ratings, and broker oversight ensure a safe environment for all members to participate.',
    color: 'text-emerald-500 dark:text-emerald-400',
    bg: 'bg-emerald-500/15',
  },
  {
    icon: Sprout,
    title: 'Sustainability',
    description: 'By sharing skills locally, we reduce waste, support circular economies, and strengthen local resilience.',
    color: 'text-teal-500 dark:text-teal-400',
    bg: 'bg-teal-500/15',
  },
];

/* ─────────────── Helpers ─────────────── */

function formatStatNumber(num: number): string {
  if (num >= 1000000) return (num / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
  if (num >= 1000) return (num / 1000).toFixed(1).replace(/\.0$/, '') + 'K';
  return num.toLocaleString();
}

/* ─────────────── Animations ─────────────── */

const fadeInUp = {
  initial: { opacity: 0, y: 30 },
  animate: { opacity: 1, y: 0 },
};

const stagger = {
  animate: { transition: { staggerChildren: 0.12 } },
};

/* ─────────────── Component ─────────────── */

export function AboutPage() {
  usePageTitle('About');
  const { branding, tenantPath } = useTenant();
  const { isAuthenticated } = useAuth();
  const [stats, setStats] = useState<PlatformStats | null>(null);

  const loadStats = useCallback(async () => {
    try {
      const response = await api.get<PlatformStats>('/v2/platform/stats');
      if (response.success && response.data) {
        setStats(response.data);
      }
    } catch (err) {
      logError('Failed to load platform stats on about page', err);
    }
  }, []);

  useEffect(() => {
    loadStats();
  }, [loadStats]);

  const statItems = stats
    ? [
        { icon: Users, value: formatStatNumber(stats.members), label: 'Members' },
        { icon: Clock, value: formatStatNumber(stats.hours_exchanged), label: 'Hours Exchanged' },
        { icon: ListTodo, value: formatStatNumber(stats.listings), label: 'Active Listings' },
        { icon: Globe, value: formatStatNumber(stats.communities), label: 'Communities' },
      ]
    : null;

  return (
    <>
      <PageMeta
        title={`About ${branding.name}`}
        description={`Learn about ${branding.name} — a time banking platform where communities exchange skills using time as currency.`}
      />

      <div className="-mx-4 sm:-mx-6 lg:-mx-8 -my-6 sm:-my-8">
        {/* ─── Hero Section ─── */}
        <section className="relative py-20 sm:py-28 px-4 sm:px-6 lg:px-8 overflow-hidden">
          {/* Background blurs */}
          <div className="absolute inset-0 pointer-events-none opacity-20" aria-hidden="true">
            <div className="absolute top-10 left-1/4 w-72 h-72 bg-indigo-500 rounded-full blur-3xl" />
            <div className="absolute bottom-10 right-1/4 w-72 h-72 bg-purple-500 rounded-full blur-3xl" />
          </div>

          <div className="max-w-4xl mx-auto text-center relative z-10">
            <motion.div {...fadeInUp} transition={{ duration: 0.6 }}>
              <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-500/20 to-purple-500/20 mb-6">
                <Hexagon className="w-8 h-8 text-indigo-500 dark:text-indigo-400" aria-hidden="true" />
              </div>
            </motion.div>

            <motion.h1
              {...fadeInUp}
              transition={{ duration: 0.6, delay: 0.1 }}
              className="text-3xl sm:text-4xl md:text-5xl font-bold text-theme-primary mb-6"
            >
              About {branding.name}
            </motion.h1>

            <motion.p
              {...fadeInUp}
              transition={{ duration: 0.6, delay: 0.2 }}
              className="text-lg sm:text-xl text-theme-muted max-w-2xl mx-auto"
            >
              {branding.name} is a modern time banking platform where every hour of service is
              valued equally. We help communities exchange skills, build trust, and create a fairer
              local economy — one hour at a time.
            </motion.p>
          </div>
        </section>

        {/* ─── How It Works ─── */}
        <section className="py-16 sm:py-20 px-4 sm:px-6 lg:px-8">
          <div className="max-w-5xl mx-auto">
            <motion.div
              initial={{ opacity: 0, y: 20 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
              className="text-center mb-12"
            >
              <h2 className="text-2xl sm:text-3xl font-bold text-theme-primary mb-3">
                How It Works
              </h2>
              <p className="text-theme-muted max-w-lg mx-auto">
                Getting started is simple. Four steps to join and begin exchanging skills in your community.
              </p>
            </motion.div>

            <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
              {steps.map((step, index) => (
                <motion.div
                  key={step.title}
                  initial={{ opacity: 0, y: 30 }}
                  whileInView={{ opacity: 1, y: 0 }}
                  viewport={{ once: true }}
                  transition={{ delay: index * 0.1 }}
                >
                  <GlassCard className="p-6 h-full text-center relative group hover:scale-[1.02] transition-transform">
                    {/* Step number */}
                    <div className="absolute -top-3 -right-3 w-8 h-8 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white text-sm font-bold shadow-lg">
                      {index + 1}
                    </div>

                    <div className={`inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-br ${step.color} mb-4`}>
                      <step.icon className="w-7 h-7 text-white" aria-hidden="true" />
                    </div>

                    <h3 className="font-semibold text-theme-primary mb-2">{step.title}</h3>
                    <p className="text-sm text-theme-muted">{step.description}</p>
                  </GlassCard>
                </motion.div>
              ))}
            </div>
          </div>
        </section>

        {/* ─── Our Values ─── */}
        <section className="py-16 sm:py-20 px-4 sm:px-6 lg:px-8 bg-gradient-to-b from-transparent via-indigo-500/5 to-transparent">
          <div className="max-w-5xl mx-auto">
            <motion.div
              initial={{ opacity: 0, y: 20 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
              className="text-center mb-12"
            >
              <h2 className="text-2xl sm:text-3xl font-bold text-theme-primary mb-3">
                Our Values
              </h2>
              <p className="text-theme-muted max-w-lg mx-auto">
                The principles that guide everything we do at {branding.name}.
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
                        <h3 className="font-semibold text-theme-primary text-lg mb-1">{value.title}</h3>
                        <p className="text-theme-muted text-sm leading-relaxed">{value.description}</p>
                      </div>
                    </div>
                  </GlassCard>
                </motion.div>
              ))}
            </div>
          </div>
        </section>

        {/* ─── Platform Stats ─── */}
        {statItems && (
          <section className="py-16 sm:py-20 px-4 sm:px-6 lg:px-8">
            <div className="max-w-4xl mx-auto">
              <motion.div
                initial="initial"
                whileInView="animate"
                viewport={{ once: true }}
                variants={stagger}
                className="grid grid-cols-2 sm:grid-cols-4 gap-6"
              >
                {statItems.map((stat) => (
                  <motion.div key={stat.label} variants={fadeInUp}>
                    <GlassCard className="p-6 text-center">
                      <div className="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-indigo-500/15 mb-3">
                        <stat.icon className="w-5 h-5 text-indigo-500 dark:text-indigo-400" aria-hidden="true" />
                      </div>
                      <p className="text-2xl sm:text-3xl font-bold text-gradient">{stat.value}</p>
                      <p className="text-sm text-theme-subtle mt-1">{stat.label}</p>
                    </GlassCard>
                  </motion.div>
                ))}
              </motion.div>
            </div>
          </section>
        )}

        {/* ─── Credits & Open Source ─── */}
        <section className="py-16 sm:py-20 px-4 sm:px-6 lg:px-8 bg-gradient-to-b from-transparent via-indigo-500/5 to-transparent">
          <div className="max-w-4xl mx-auto">
            <motion.div
              initial={{ opacity: 0, y: 20 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
              className="text-center mb-12"
            >
              <h2 className="text-2xl sm:text-3xl font-bold text-theme-primary mb-3">
                Powered by Project NEXUS
              </h2>
              <p className="text-theme-muted max-w-lg mx-auto">
                Open-source community platform created by Jasper Ford. Licensed under AGPL v3.
              </p>
            </motion.div>

            <div className="grid sm:grid-cols-2 gap-6 mb-8">
              {/* Creator & Founders (from contributors.json) */}
              <motion.div
                initial={{ opacity: 0, x: -20 }}
                whileInView={{ opacity: 1, x: 0 }}
                viewport={{ once: true }}
              >
                <GlassCard className="p-6 h-full">
                  <div className="flex items-center gap-3 mb-4">
                    <div className="p-2.5 rounded-xl bg-indigo-500/15">
                      <Crown className="w-5 h-5 text-indigo-500 dark:text-indigo-400" aria-hidden="true" />
                    </div>
                    <h3 className="font-semibold text-theme-primary text-lg">Creator</h3>
                  </div>
                  {contributors.filter(c => c.type === 'creator').map(c => (
                    <p key={c.name} className="text-theme-primary font-medium mb-4">{c.name}</p>
                  ))}

                  <h4 className="text-sm font-semibold text-theme-muted mb-2">Founders of hOUR Timebank CLC</h4>
                  <ul className="space-y-1 text-sm text-theme-muted">
                    {contributors.filter(c => c.type === 'creator' || c.type === 'founder').map(c => (
                      <li key={c.name}>{c.name}</li>
                    ))}
                  </ul>
                </GlassCard>
              </motion.div>

              {/* Contributors (from contributors.json) */}
              <motion.div
                initial={{ opacity: 0, x: 20 }}
                whileInView={{ opacity: 1, x: 0 }}
                viewport={{ once: true }}
              >
                <GlassCard className="p-6 h-full">
                  <div className="flex items-center gap-3 mb-4">
                    <div className="p-2.5 rounded-xl bg-purple-500/15">
                      <Star className="w-5 h-5 text-purple-500 dark:text-purple-400" aria-hidden="true" />
                    </div>
                    <h3 className="font-semibold text-theme-primary text-lg">Contributors</h3>
                  </div>
                  <ul className="space-y-2 text-sm text-theme-muted mb-4">
                    {contributors.filter(c => c.type === 'contributor').map(c => (
                      <li key={c.name}><span className="text-theme-primary font-medium">{c.name}</span> — {c.role}</li>
                    ))}
                  </ul>

                  {contributors.some(c => c.note?.includes('social impact study')) && (
                    <>
                      <h4 className="text-sm font-semibold text-theme-muted mb-2">Research Foundation</h4>
                      <p className="text-sm text-theme-muted">
                        Informed by a social impact study commissioned by the{' '}
                        <span className="text-theme-primary font-medium">
                          {contributors.find(c => c.note?.includes('social impact study'))?.name}
                        </span>.
                      </p>
                    </>
                  )}
                </GlassCard>
              </motion.div>
            </div>

            {/* Acknowledgements & License (from contributors.json) */}
            <div className="grid sm:grid-cols-2 gap-6">
              <motion.div
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true }}
              >
                <GlassCard className="p-6 h-full">
                  <div className="flex items-center gap-3 mb-4">
                    <div className="p-2.5 rounded-xl bg-amber-500/15">
                      <Award className="w-5 h-5 text-amber-500 dark:text-amber-400" aria-hidden="true" />
                    </div>
                    <h3 className="font-semibold text-theme-primary text-lg">Acknowledgements</h3>
                  </div>
                  <ul className="space-y-1 text-sm text-theme-muted">
                    {contributors.filter(c => c.type === 'acknowledgement').map(c => (
                      <li key={c.name}>{c.name}{c.role !== 'Research Foundation' ? `, ${c.role}` : ''}</li>
                    ))}
                  </ul>
                </GlassCard>
              </motion.div>

              <motion.div
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true }}
                transition={{ delay: 0.1 }}
              >
                <GlassCard className="p-6 h-full">
                  <div className="flex items-center gap-3 mb-4">
                    <div className="p-2.5 rounded-xl bg-emerald-500/15">
                      <Code className="w-5 h-5 text-emerald-500 dark:text-emerald-400" aria-hidden="true" />
                    </div>
                    <h3 className="font-semibold text-theme-primary text-lg">Open Source</h3>
                  </div>
                  <p className="text-sm text-theme-muted mb-3">
                    Licensed under the GNU Affero General Public License v3 (AGPL-3.0).
                  </p>
                  <div className="flex flex-col gap-2">
                    <a
                      href="https://github.com/jasperfordesq-ai/nexus-v1"
                      target="_blank"
                      rel="noopener noreferrer"
                      className="inline-flex items-center gap-2 text-sm font-medium text-indigo-500 dark:text-indigo-400 hover:text-indigo-600 dark:hover:text-indigo-300 transition-colors"
                    >
                      <BookOpen className="w-4 h-4" aria-hidden="true" />
                      V1 Source Code (PHP + React)
                    </a>
                    <a
                      href="https://github.com/jasperfordesq-ai/api.project-nexus.net"
                      target="_blank"
                      rel="noopener noreferrer"
                      className="inline-flex items-center gap-2 text-sm font-medium text-indigo-500 dark:text-indigo-400 hover:text-indigo-600 dark:hover:text-indigo-300 transition-colors"
                    >
                      <BookOpen className="w-4 h-4" aria-hidden="true" />
                      V2 Source Code (ASP.NET Core)
                    </a>
                  </div>
                </GlassCard>
              </motion.div>
            </div>
          </div>
        </section>

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
                  <h2 className="text-2xl sm:text-3xl font-bold text-theme-primary mb-4">
                    Ready to Get Involved?
                  </h2>
                  <p className="text-theme-muted max-w-lg mx-auto mb-8">
                    Whether you want to share your skills or find help with something, our community is here for you.
                  </p>

                  <div className="flex flex-col sm:flex-row gap-4 justify-center">
                    {isAuthenticated ? (
                      <Link to={tenantPath("/dashboard")}>
                        <Button
                          size="lg"
                          className="w-full sm:w-auto bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-semibold px-8"
                          endContent={<ArrowRight className="w-5 h-5" aria-hidden="true" />}
                        >
                          Go to Dashboard
                        </Button>
                      </Link>
                    ) : (
                      <Link to={tenantPath("/register")}>
                        <Button
                          size="lg"
                          className="w-full sm:w-auto bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-semibold px-8"
                          endContent={<ArrowRight className="w-5 h-5" aria-hidden="true" />}
                        >
                          Join for Free
                        </Button>
                      </Link>
                    )}

                    <Link to={tenantPath("/help")}>
                      <Button
                        size="lg"
                        variant="bordered"
                        className="w-full sm:w-auto border-theme-default text-theme-primary hover:bg-theme-hover"
                        startContent={<HelpCircle className="w-5 h-5" aria-hidden="true" />}
                      >
                        Help Center
                      </Button>
                    </Link>

                    <Link to={tenantPath("/contact")}>
                      <Button
                        size="lg"
                        variant="bordered"
                        className="w-full sm:w-auto border-theme-default text-theme-primary hover:bg-theme-hover"
                        startContent={<Mail className="w-5 h-5" aria-hidden="true" />}
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

export default AboutPage;
