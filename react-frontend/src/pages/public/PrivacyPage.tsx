// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Privacy Policy Page
 *
 * Comprehensive GDPR-compliant privacy policy with data collection tables,
 * rights sections, cookie information, and data controller details.
 */

import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Chip, Divider, Spinner } from '@heroui/react';
import {
  Shield,
  Database,
  PieChart,
  UserCheck,
  Cookie,
  Eye,
  Lock,
  Clock,
  Handshake,
  Pencil,
  Trash2,
  Download,
  Ban,
  MessageSquare,
  Send,
  CalendarDays,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { CustomLegalDocument } from '@/components/legal/CustomLegalDocument';
import { useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { useLegalDocument } from '@/hooks/useLegalDocument';

const containerVariants = {
  hidden: { opacity: 0 },
  visible: {
    opacity: 1,
    transition: { staggerChildren: 0.08 },
  },
};

const itemVariants = {
  hidden: { opacity: 0, y: 20 },
  visible: { opacity: 1, y: 0 },
};

const quickNavItems = [
  { id: 'data-collection', label: 'Data Collection', icon: Database },
  { id: 'data-usage', label: 'How We Use Data', icon: PieChart },
  { id: 'your-rights', label: 'Your Rights', icon: UserCheck },
  { id: 'cookies', label: 'Cookies', icon: Cookie },
];

const dataCollectionRows = [
  {
    type: 'Account Information',
    collected: 'Name, email address, password (encrypted)',
    why: 'Required to create and manage your account',
    basis: 'Contract',
  },
  {
    type: 'Profile Details',
    collected: 'Bio, skills, location, profile photo',
    why: 'Helps connect you with community members',
    basis: 'Consent',
  },
  {
    type: 'Activity Data',
    collected: 'Exchanges, messages, time credit transactions',
    why: 'Essential for platform functionality',
    basis: 'Contract',
  },
  {
    type: 'Device Information',
    collected: 'Browser type, IP address, device type',
    why: 'Security monitoring and troubleshooting',
    basis: 'Legitimate Interest',
  },
  {
    type: 'Usage Analytics',
    collected: 'Pages visited, features used, session duration',
    why: 'Improve platform experience',
    basis: 'Legitimate Interest',
  },
];

const gdprRights = [
  {
    icon: Eye,
    title: 'Right to Access',
    description: 'Request a copy of all personal data we hold about you. We will respond within 30 days.',
    color: 'text-blue-500',
    bg: 'bg-blue-500/20',
  },
  {
    icon: Pencil,
    title: 'Right to Rectification',
    description: 'Correct any inaccurate or incomplete personal information we hold about you.',
    color: 'text-emerald-500',
    bg: 'bg-emerald-500/20',
  },
  {
    icon: Trash2,
    title: 'Right to Erasure',
    description: 'Request deletion of your account and all associated personal data.',
    color: 'text-red-500',
    bg: 'bg-red-500/20',
  },
  {
    icon: Download,
    title: 'Right to Portability',
    description: 'Export your data in a structured, machine-readable format (JSON or CSV).',
    color: 'text-purple-500',
    bg: 'bg-purple-500/20',
  },
  {
    icon: Ban,
    title: 'Right to Restrict Processing',
    description: 'Request that we limit how we process your data while a concern is being resolved.',
    color: 'text-amber-500',
    bg: 'bg-amber-500/20',
  },
  {
    icon: UserCheck,
    title: 'Right to Withdraw Consent',
    description: 'Withdraw consent for optional data processing at any time without affecting prior processing.',
    color: 'text-indigo-500',
    bg: 'bg-indigo-500/20',
  },
];

const cookieCategories = [
  {
    name: 'Essential Cookies',
    description: 'Required for login, security, and basic platform functionality. Cannot be disabled.',
    required: true,
  },
  {
    name: 'Preference Cookies',
    description: 'Remember your settings such as theme preference, language, and display options.',
    required: false,
  },
  {
    name: 'Analytics Cookies',
    description: 'Help us understand how people use the platform so we can improve features. All data is anonymised.',
    required: false,
  },
];

function scrollToSection(id: string) {
  const el = document.getElementById(id);
  if (el) {
    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}

export function PrivacyPage() {
  usePageTitle('Privacy Policy');
  const { branding, tenantPath } = useTenant();
  const { document: customDoc, loading } = useLegalDocument('privacy');

  if (loading) {
    return (
      <div className="flex justify-center items-center min-h-[50vh]">
        <Spinner size="lg" />
      </div>
    );
  }

  if (customDoc) {
    return <CustomLegalDocument document={customDoc} accentColor="indigo" />;
  }

  return (
    <motion.div
      variants={containerVariants}
      initial="hidden"
      animate="visible"
      className="max-w-4xl mx-auto space-y-8"
    >
      {/* Hero Header */}
      <motion.div variants={itemVariants} className="text-center">
        <div className="inline-flex p-4 rounded-2xl bg-gradient-to-br from-indigo-500/20 to-purple-500/20 mb-4">
          <Shield className="w-10 h-10 text-indigo-500 dark:text-indigo-400" aria-hidden="true" />
        </div>
        <h1 className="text-3xl sm:text-4xl font-bold text-theme-primary mb-3">
          Privacy Policy
        </h1>
        <p className="text-theme-muted text-lg max-w-2xl mx-auto">
          How we collect, use, and protect your personal information
        </p>
        <div className="flex items-center justify-center gap-2 mt-3 text-sm text-theme-subtle">
          <CalendarDays className="w-4 h-4" aria-hidden="true" />
          <span>Last updated: February 2026</span>
        </div>
      </motion.div>

      {/* Quick Navigation */}
      <motion.div variants={itemVariants}>
        <div className="flex flex-wrap justify-center gap-3">
          {quickNavItems.map((item) => (
            <Button
              key={item.id}
              variant="light"
              onPress={() => scrollToSection(item.id)}
              className="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-theme-elevated hover:bg-indigo-500/10 text-theme-primary text-sm font-medium transition-colors h-auto min-w-0"
            >
              <item.icon className="w-4 h-4 text-indigo-500" aria-hidden="true" />
              {item.label}
            </Button>
          ))}
        </div>
      </motion.div>

      {/* Our Commitment */}
      <motion.div variants={itemVariants}>
        <GlassCard className="p-6 sm:p-8">
          <div className="p-4 rounded-xl bg-indigo-500/10 border border-indigo-500/20">
            <h2 className="text-xl font-semibold text-theme-primary mb-3 flex items-center gap-2">
              <Handshake className="w-5 h-5 text-indigo-500" aria-hidden="true" />
              Our Commitment to Your Privacy
            </h2>
            <div className="space-y-3 text-theme-muted">
              <p>
                {branding.name} is committed to protecting your privacy and ensuring your
                personal data is handled responsibly. This policy explains what information
                we collect, why we collect it, and how you can manage your data.
              </p>
              <p>
                We believe in <strong className="text-theme-primary">transparency</strong> and{' '}
                <strong className="text-theme-primary">user control</strong>. You have the right
                to understand and manage how your information is used.
              </p>
            </div>
          </div>
        </GlassCard>
      </motion.div>

      {/* Data Collection */}
      <motion.div variants={itemVariants} id="data-collection">
        <GlassCard className="p-6 sm:p-8">
          <h2 className="text-xl font-semibold text-theme-primary mb-4 flex items-center gap-2">
            <Database className="w-5 h-5 text-indigo-500" aria-hidden="true" />
            Information We Collect
          </h2>
          <p className="text-theme-muted mb-6">
            We collect only the information necessary to provide and improve our services:
          </p>

          {/* Data Table - responsive card layout */}
          <div className="space-y-3">
            {dataCollectionRows.map((row) => (
              <div
                key={row.type}
                className="p-4 rounded-xl bg-theme-elevated border border-default-200 dark:border-default-100"
              >
                <div className="flex flex-wrap items-start justify-between gap-2 mb-2">
                  <h3 className="font-semibold text-theme-primary">{row.type}</h3>
                  <Chip size="sm" variant="flat" color="primary" className="text-xs">
                    {row.basis}
                  </Chip>
                </div>
                <p className="text-sm text-theme-muted mb-1">{row.collected}</p>
                <p className="text-xs text-theme-subtle">{row.why}</p>
              </div>
            ))}
          </div>
        </GlassCard>
      </motion.div>

      {/* How We Use Your Data */}
      <motion.div variants={itemVariants} id="data-usage">
        <GlassCard className="p-6 sm:p-8">
          <h2 className="text-xl font-semibold text-theme-primary mb-4 flex items-center gap-2">
            <PieChart className="w-5 h-5 text-indigo-500" aria-hidden="true" />
            How We Use Your Data
          </h2>
          <p className="text-theme-muted mb-4">
            Your data is used exclusively for the following purposes:
          </p>
          <ul className="space-y-3 text-theme-muted">
            {[
              { label: 'Service Delivery', desc: 'Facilitating time exchanges and community connections' },
              { label: 'Communication', desc: 'Sending important updates, notifications, and messages from other members' },
              { label: 'Security', desc: 'Protecting your account and preventing fraud or abuse' },
              { label: 'Improvement', desc: 'Analysing usage patterns to enhance platform features' },
              { label: 'Legal Compliance', desc: 'Meeting regulatory requirements when necessary' },
            ].map((item) => (
              <li key={item.label} className="flex items-start gap-3">
                <div className="mt-1 w-1.5 h-1.5 rounded-full bg-indigo-500 flex-shrink-0" />
                <span>
                  <strong className="text-theme-primary">{item.label}:</strong> {item.desc}
                </span>
              </li>
            ))}
          </ul>

          <div className="mt-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20">
            <p className="text-sm font-medium text-emerald-600 dark:text-emerald-400">
              We do not sell your personal data to third parties. Your information is never
              shared with advertisers or data brokers.
            </p>
          </div>
        </GlassCard>
      </motion.div>

      {/* Profile Visibility */}
      <motion.div variants={itemVariants}>
        <GlassCard className="p-6 sm:p-8">
          <h2 className="text-xl font-semibold text-theme-primary mb-4 flex items-center gap-2">
            <Eye className="w-5 h-5 text-indigo-500" aria-hidden="true" />
            Profile Visibility
          </h2>
          <div className="space-y-3 text-theme-muted">
            <p>
              Your profile is visible to other verified members of the timebank community.
              This visibility is essential for facilitating exchanges and building trust
              within the community.
            </p>
            <p>
              You can control what information appears on your profile through your{' '}
              <strong className="text-theme-primary">account settings</strong>. Some information,
              like your name and general location, is required for meaningful community participation.
            </p>
          </div>
        </GlassCard>
      </motion.div>

      {/* Data Protection */}
      <motion.div variants={itemVariants}>
        <GlassCard className="p-6 sm:p-8">
          <h2 className="text-xl font-semibold text-theme-primary mb-4 flex items-center gap-2">
            <Lock className="w-5 h-5 text-indigo-500" aria-hidden="true" />
            How We Protect Your Data
          </h2>
          <p className="text-theme-muted mb-4">
            We implement robust security measures to safeguard your information:
          </p>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            {[
              { label: 'Encryption', desc: 'All data encrypted in transit (HTTPS) and at rest' },
              { label: 'Secure Passwords', desc: 'Passwords hashed using industry-standard algorithms' },
              { label: 'Access Controls', desc: 'Strict internal policies limit data access' },
              { label: 'Regular Audits', desc: 'Security reviews and practice updates' },
              { label: 'Secure Infrastructure', desc: 'Hosted in certified data centres' },
            ].map((item) => (
              <div
                key={item.label}
                className="flex items-start gap-3 p-3 rounded-xl bg-theme-elevated"
              >
                <Lock className="w-4 h-4 text-indigo-500 mt-0.5 flex-shrink-0" aria-hidden="true" />
                <div>
                  <p className="font-medium text-theme-primary text-sm">{item.label}</p>
                  <p className="text-xs text-theme-subtle mt-0.5">{item.desc}</p>
                </div>
              </div>
            ))}
          </div>
        </GlassCard>
      </motion.div>

      {/* Your Rights (GDPR) */}
      <motion.div variants={itemVariants} id="your-rights">
        <GlassCard className="p-6 sm:p-8">
          <div className="p-4 rounded-xl bg-indigo-500/10 border border-indigo-500/20 mb-6">
            <h2 className="text-xl font-semibold text-theme-primary mb-2 flex items-center gap-2">
              <UserCheck className="w-5 h-5 text-indigo-500" aria-hidden="true" />
              Your Privacy Rights
            </h2>
            <p className="text-theme-muted text-sm">
              Under the General Data Protection Regulation (GDPR), you have full control
              over your personal data. Here are your rights:
            </p>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {gdprRights.map((right) => (
              <div
                key={right.title}
                className="flex items-start gap-3 p-4 rounded-xl bg-theme-elevated"
              >
                <div className={`p-2 rounded-lg ${right.bg} flex-shrink-0`}>
                  <right.icon className={`w-4 h-4 ${right.color}`} aria-hidden="true" />
                </div>
                <div>
                  <h3 className="font-medium text-theme-primary text-sm">{right.title}</h3>
                  <p className="text-xs text-theme-subtle mt-1">{right.description}</p>
                </div>
              </div>
            ))}
          </div>

          <p className="text-sm text-theme-muted mt-4">
            To exercise any of these rights, please contact us through our contact page.
            We will respond to all requests within 30 days.
          </p>
        </GlassCard>
      </motion.div>

      {/* Cookies */}
      <motion.div variants={itemVariants} id="cookies">
        <GlassCard className="p-6 sm:p-8">
          <h2 className="text-xl font-semibold text-theme-primary mb-4 flex items-center gap-2">
            <Cookie className="w-5 h-5 text-indigo-500" aria-hidden="true" />
            Cookies &amp; Tracking
          </h2>
          <p className="text-theme-muted mb-4">
            We use cookies to enhance your experience on our platform:
          </p>

          <div className="space-y-3">
            {cookieCategories.map((cat) => (
              <div
                key={cat.name}
                className="flex items-start gap-3 p-4 rounded-xl bg-theme-elevated border border-default-200 dark:border-default-100"
              >
                <div className="flex-1">
                  <div className="flex items-center gap-2 mb-1">
                    <h3 className="font-medium text-theme-primary text-sm">{cat.name}</h3>
                    {cat.required && (
                      <Chip size="sm" variant="flat" color="warning" className="text-xs">
                        Required
                      </Chip>
                    )}
                  </div>
                  <p className="text-xs text-theme-subtle">{cat.description}</p>
                </div>
              </div>
            ))}
          </div>

          <p className="text-sm text-theme-muted mt-4">
            We do <strong className="text-theme-primary">not</strong> use advertising cookies or
            share data with ad networks. You can manage cookie preferences in your browser settings.
            For more details, see our{' '}
            <Link to={tenantPath('/cookies')} className="text-indigo-500 hover:underline">
              Cookie Policy
            </Link>.
          </p>
        </GlassCard>
      </motion.div>

      {/* Data Retention */}
      <motion.div variants={itemVariants}>
        <GlassCard className="p-6 sm:p-8">
          <h2 className="text-xl font-semibold text-theme-primary mb-4 flex items-center gap-2">
            <Clock className="w-5 h-5 text-indigo-500" aria-hidden="true" />
            Data Retention
          </h2>
          <p className="text-theme-muted mb-4">
            We retain your data only as long as necessary:
          </p>
          <ul className="space-y-3 text-theme-muted">
            {[
              { label: 'Active Accounts', desc: 'Data is kept while your account remains active' },
              { label: 'Deleted Accounts', desc: 'Personal data is removed within 30 days of account deletion' },
              { label: 'Transaction Records', desc: 'May be retained for up to 7 years for legal and audit purposes' },
              { label: 'Security Logs', desc: 'IP addresses and access logs retained for 12 months' },
            ].map((item) => (
              <li key={item.label} className="flex items-start gap-3">
                <div className="mt-1 w-1.5 h-1.5 rounded-full bg-indigo-500 flex-shrink-0" />
                <span>
                  <strong className="text-theme-primary">{item.label}:</strong> {item.desc}
                </span>
              </li>
            ))}
          </ul>
        </GlassCard>
      </motion.div>

      {/* Contact CTA */}
      <motion.div variants={itemVariants}>
        <GlassCard className="p-6 sm:p-8">
          <div className="text-center">
            <div className="inline-flex p-3 rounded-2xl bg-gradient-to-br from-indigo-500/20 to-purple-500/20 mb-4">
              <MessageSquare className="w-8 h-8 text-indigo-500 dark:text-indigo-400" aria-hidden="true" />
            </div>
            <h2 className="text-xl font-semibold text-theme-primary mb-2">
              Questions About Your Privacy?
            </h2>
            <p className="text-theme-muted text-sm mb-6 max-w-lg mx-auto">
              We are here to help. If you have any questions about this policy or want to
              exercise your data rights, please do not hesitate to reach out.
            </p>
            <Divider className="my-4" />
            <div className="flex flex-wrap justify-center gap-3 mt-4">
              <Link to={tenantPath('/contact')}>
                <Button
                  className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                  startContent={<Send className="w-4 h-4" aria-hidden="true" />}
                >
                  Contact Our Privacy Team
                </Button>
              </Link>
              <Link to={tenantPath('/cookies')}>
                <Button
                  variant="flat"
                  className="bg-theme-elevated text-theme-primary"
                  startContent={<Cookie className="w-4 h-4" aria-hidden="true" />}
                >
                  Cookie Policy
                </Button>
              </Link>
            </div>
          </div>
        </GlassCard>
      </motion.div>
    </motion.div>
  );
}

export default PrivacyPage;
