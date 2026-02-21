// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Home Page - Landing page with hero section
 * Theme-aware styling for light and dark modes
 */

import { useState, useEffect, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button } from '@heroui/react';
import {
  ArrowRight,
  Clock,
  Users,
  Zap,
  ChevronDown,
  UserPlus,
  Search,
  Handshake,
  Coins,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { useTenant, useAuth } from '@/contexts';
import { PageMeta } from '@/components/seo';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

interface PlatformStats {
  members: number;
  hours_exchanged: number;
  listings: number;
  skills: number;
  communities: number;
}

const fadeInUp = {
  initial: { opacity: 0, y: 30 },
  animate: { opacity: 1, y: 0 },
};

const staggerContainer = {
  animate: {
    transition: {
      staggerChildren: 0.15,
    },
  },
};

const features = [
  {
    icon: Clock,
    title: 'Time Credits',
    description: 'Exchange skills using time as currency',
  },
  {
    icon: Users,
    title: 'Community',
    description: 'Connect with local service providers',
  },
  {
    icon: Zap,
    title: 'Instant',
    description: 'Quick and seamless transactions',
  },
];

// Format number with appropriate suffix (K, M, etc.)
function formatStatNumber(num: number): string {
  if (num >= 1000000) {
    return (num / 1000000).toFixed(1).replace(/\.0$/, '') + 'M+';
  }
  if (num >= 1000) {
    return (num / 1000).toFixed(1).replace(/\.0$/, '') + 'K+';
  }
  return num.toString();
}

const coreValues = [
  {
    title: 'Equal Value',
    description:
      "Every hour is worth the same. Whether you're teaching piano or mowing lawns, your time has equal value.",
    gradient: 'from-indigo-500 to-blue-500',
  },
  {
    title: 'Build Trust',
    description:
      'Reviews and ratings help you find reliable service providers and build your reputation in the community.',
    gradient: 'from-purple-500 to-pink-500',
  },
  {
    title: 'Stay Local',
    description:
      'Connect with neighbors and strengthen your local community through meaningful skill exchanges.',
    gradient: 'from-cyan-500 to-teal-500',
  },
];

const howItWorks = [
  {
    icon: UserPlus,
    title: 'Sign Up Free',
    description: 'Create your profile in minutes and list the skills you can offer.',
    color: 'from-indigo-500 to-blue-500',
  },
  {
    icon: Search,
    title: 'Browse & Connect',
    description: 'Find services you need and connect with local community members.',
    color: 'from-purple-500 to-pink-500',
  },
  {
    icon: Handshake,
    title: 'Exchange Services',
    description: 'Arrange skill exchanges and help each other out.',
    color: 'from-cyan-500 to-teal-500',
  },
  {
    icon: Coins,
    title: 'Earn Credits',
    description: 'Get one time credit for every hour you give. Spend them freely.',
    color: 'from-amber-500 to-orange-500',
  },
];

export function HomePage() {
  usePageTitle('Home');
  const { branding, tenantPath } = useTenant();
  const { isAuthenticated } = useAuth();
  const [platformStats, setPlatformStats] = useState<PlatformStats | null>(null);

  const loadStats = useCallback(async () => {
    try {
      const response = await api.get<PlatformStats>('/v2/platform/stats');
      if (response.success && response.data) {
        setPlatformStats(response.data);
      }
    } catch (error) {
      logError('Failed to load platform stats', error);
      // Stats will show defaults on error
    }
  }, []);

  useEffect(() => {
    loadStats();
  }, [loadStats]);

  const stats = platformStats
    ? [
        { value: formatStatNumber(platformStats.members), label: 'Active Members' },
        { value: formatStatNumber(platformStats.hours_exchanged), label: 'Hours Exchanged' },
        { value: formatStatNumber(platformStats.listings), label: 'Active Listings' },
        { value: formatStatNumber(platformStats.communities), label: 'Communities' },
      ]
    : [
        { value: '—', label: 'Active Members' },
        { value: '—', label: 'Hours Exchanged' },
        { value: '—', label: 'Active Listings' },
        { value: '—', label: 'Communities' },
      ];

  const scrollToSection = () => {
    document.getElementById('features')?.scrollIntoView({ behavior: 'smooth' });
  };

  return (
    <>
      <PageMeta
        description="Exchange skills and services using time credits. Join our community-driven time banking platform."
        keywords="time banking, skill exchange, community, volunteer, time credits"
      />
      <div className="-mx-3 sm:-mx-4 md:-mx-6 lg:-mx-8 -my-4 sm:-my-6 md:-my-8 overflow-x-hidden">
        {/* Hero Section */}
      <section className="relative py-20 sm:py-32 px-4 sm:px-6 lg:px-8">
        <div className="max-w-7xl mx-auto">
          <motion.div
            className="text-center"
            initial="initial"
            animate="animate"
            variants={staggerContainer}
          >
            {/* Badge */}
            <motion.div variants={fadeInUp} className="mb-6">
              <span className="inline-flex items-center gap-2 px-4 py-2 rounded-full glass-card text-sm text-theme-muted">
                <span className="text-indigo-500 dark:text-indigo-400">✨</span>
                <span>The Future of Time Banking</span>
              </span>
            </motion.div>

            {/* Headline */}
            <motion.h1
              variants={fadeInUp}
              className="text-3xl sm:text-4xl md:text-5xl lg:text-6xl xl:text-7xl font-bold tracking-tight"
            >
              <span className="text-theme-primary">Exchange Skills,</span>
              <br />
              <span className="text-gradient">Build Community</span>
            </motion.h1>

            {/* Subheadline */}
            <motion.p
              variants={fadeInUp}
              className="mt-6 text-lg sm:text-xl text-theme-muted max-w-2xl mx-auto"
            >
              {branding.name} is a modern time banking platform where every hour of service
              is valued equally. Trade your skills, earn time credits, and connect
              with your community.
            </motion.p>

            {/* CTAs */}
            <motion.div
              variants={fadeInUp}
              className="mt-10 flex flex-col sm:flex-row gap-4 justify-center"
            >
              {isAuthenticated ? (
                <Link to={tenantPath("/dashboard")}>
                  <Button
                    size="lg"
                    className="w-full sm:w-auto bg-gradient-to-r from-indigo-500 via-purple-500 to-indigo-600 text-white font-semibold px-8 shadow-lg shadow-indigo-500/25 hover:shadow-indigo-500/40 transition-shadow"
                    endContent={<ArrowRight className="w-5 h-5" aria-hidden="true" />}
                  >
                    Go to Dashboard
                  </Button>
                </Link>
              ) : (
                <>
                  <Link to={tenantPath("/register")}>
                    <Button
                      size="lg"
                      className="w-full sm:w-auto bg-gradient-to-r from-indigo-500 via-purple-500 to-indigo-600 text-white font-semibold px-8 shadow-lg shadow-indigo-500/25 hover:shadow-indigo-500/40 transition-shadow"
                      endContent={<ArrowRight className="w-5 h-5" aria-hidden="true" />}
                    >
                      Get Started Free
                    </Button>
                  </Link>
                  <Link to={tenantPath("/about")}>
                    <Button
                      size="lg"
                      variant="bordered"
                      className="w-full sm:w-auto border-theme-default text-theme-primary hover:bg-theme-hover"
                    >
                      Learn More
                    </Button>
                  </Link>
                </>
              )}
            </motion.div>

            {/* Feature Pills */}
            <motion.div
              variants={fadeInUp}
              className="mt-16 grid grid-cols-1 sm:grid-cols-3 gap-4 max-w-3xl mx-auto"
            >
              {features.map((feature, index) => (
                <motion.div
                  key={feature.title}
                  initial={{ opacity: 0, y: 20 }}
                  animate={{ opacity: 1, y: 0 }}
                  transition={{ delay: 0.5 + index * 0.1 }}
                  className="flex items-center gap-3 p-4 rounded-2xl glass-card"
                >
                  <div className="flex-shrink-0 w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500/20 to-purple-500/20 flex items-center justify-center">
                    <feature.icon className="w-5 h-5 text-indigo-500 dark:text-indigo-400" aria-hidden="true" />
                  </div>
                  <div className="text-left">
                    <p className="font-medium text-theme-primary">{feature.title}</p>
                    <p className="text-sm text-theme-subtle">{feature.description}</p>
                  </div>
                </motion.div>
              ))}
            </motion.div>

            {/* Stats */}
            <motion.div
              initial={{ opacity: 0, y: 40 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.8 }}
              className="mt-16 sm:mt-24 grid grid-cols-2 sm:grid-cols-4 gap-4 sm:gap-8"
            >
              {stats.map((stat, index) => (
                <div key={stat.label} className="text-center">
                  <motion.p
                    initial={{ opacity: 0, scale: 0.5 }}
                    animate={{ opacity: 1, scale: 1 }}
                    transition={{ delay: 0.9 + index * 0.1 }}
                    className="text-3xl sm:text-4xl font-bold text-gradient"
                  >
                    {stat.value}
                  </motion.p>
                  <p className="mt-1 text-sm text-theme-subtle">{stat.label}</p>
                </div>
              ))}
            </motion.div>
          </motion.div>
        </div>

        {/* Scroll Indicator */}
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          transition={{ delay: 1.2 }}
          className="flex justify-center mt-12"
        >
          <Button
            variant="light"
            className="text-theme-subtle hover:text-theme-primary animate-bounce"
            onPress={scrollToSection}
            isIconOnly
            aria-label="Scroll down"
          >
            <ChevronDown className="w-6 h-6" aria-hidden="true" />
          </Button>
        </motion.div>
      </section>

      {/* How It Works Section */}
      <section id="features" className="py-20 px-4 sm:px-6 lg:px-8">
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
              Get started in four simple steps
            </p>
          </motion.div>

          <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
            {howItWorks.map((step, index) => (
              <motion.div
                key={step.title}
                initial={{ opacity: 0, y: 30 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true }}
                transition={{ delay: index * 0.1 }}
              >
                <GlassCard className="p-6 h-full text-center relative group hover:scale-[1.02] transition-transform">
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

      {/* Core Values Section */}
      <section className="py-20 px-4 sm:px-6 lg:px-8 bg-gradient-to-b from-transparent via-indigo-500/5 to-transparent">
        <div className="max-w-7xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            className="text-center mb-12"
          >
            <h2 className="text-2xl sm:text-3xl font-bold text-theme-primary mb-3">
              Why Time Banking?
            </h2>
            <p className="text-theme-muted max-w-lg mx-auto">
              The core principles that make our community work
            </p>
          </motion.div>

          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            className="grid md:grid-cols-3 gap-4 sm:gap-6 md:gap-8"
          >
            {coreValues.map((feature, index) => (
              <motion.article
                key={feature.title}
                initial={{ opacity: 0, y: 30 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true }}
                transition={{ delay: index * 0.1 }}
                className="relative group"
              >
                <div className="p-5 sm:p-8 rounded-2xl glass-card-hover">
                  <div
                    className={`w-12 h-12 rounded-xl bg-gradient-to-r ${feature.gradient} flex items-center justify-center mb-6`}
                    aria-hidden="true"
                  >
                    <span className="text-2xl font-bold text-white">
                      {index + 1}
                    </span>
                  </div>
                  <h3 className="text-xl font-semibold text-theme-primary mb-3">
                    {feature.title}
                  </h3>
                  <p className="text-theme-muted">{feature.description}</p>
                </div>
              </motion.article>
            ))}
          </motion.div>
        </div>
      </section>

      {/* CTA Section */}
      {!isAuthenticated && (
        <section className="py-20 px-4 sm:px-6 lg:px-8">
          <div className="max-w-4xl mx-auto text-center">
            <motion.div
              initial={{ opacity: 0, y: 20 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
              className="p-6 sm:p-8 lg:p-12 rounded-3xl bg-gradient-to-br from-indigo-500/10 to-purple-500/10 border border-theme-default"
            >
              <h2 className="text-3xl sm:text-4xl font-bold text-theme-primary mb-4">
                Ready to get started?
              </h2>
              <p className="text-theme-muted mb-8 max-w-xl mx-auto">
                Join thousands of community members who are already exchanging
                skills and building meaningful connections.
              </p>
              <Link to={tenantPath("/register")}>
                <Button
                  size="lg"
                  className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-semibold px-10"
                  endContent={<ArrowRight className="w-5 h-5" aria-hidden="true" />}
                >
                  Create Your Free Account
                </Button>
              </Link>
            </motion.div>
          </div>
        </section>
      )}
      </div>
    </>
  );
}

export default HomePage;
