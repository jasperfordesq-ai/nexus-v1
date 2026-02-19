// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Cookie Policy Page
 *
 * Comprehensive cookie policy with cookie categories, detailed cookie table,
 * management instructions, and third-party cookie information.
 */

import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Chip, Divider, Spinner } from '@heroui/react';
import {
  Cookie,
  Shield,
  Settings,
  BarChart3,
  Lock,
  Globe,
  MessageSquare,
  Send,
  CalendarDays,
  CheckCircle,
  Info,
  Monitor,
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
  { id: 'what-are-cookies', label: 'What Are Cookies', icon: Cookie },
  { id: 'cookie-categories', label: 'Categories', icon: Settings },
  { id: 'cookie-list', label: 'Cookie List', icon: BarChart3 },
  { id: 'manage-cookies', label: 'Manage Cookies', icon: Monitor },
];

const cookieCategories = [
  {
    name: 'Essential Cookies',
    icon: Lock,
    color: 'text-emerald-500',
    bg: 'bg-emerald-500/20',
    borderColor: 'border-emerald-500/20',
    required: true,
    description:
      'These cookies are strictly necessary for the platform to function. They enable core functionality such as authentication, security, and session management. Without these cookies, the platform cannot operate properly.',
    examples: [
      'User authentication and session tokens',
      'CSRF protection tokens',
      'Security preferences',
      'Load balancing',
    ],
  },
  {
    name: 'Analytics Cookies',
    icon: BarChart3,
    color: 'text-blue-500',
    bg: 'bg-blue-500/20',
    borderColor: 'border-blue-500/20',
    required: false,
    description:
      'These cookies help us understand how visitors interact with the platform by collecting and reporting information anonymously. This helps us improve the platform experience for everyone.',
    examples: [
      'Pages visited and time spent',
      'Features used most frequently',
      'Error tracking and performance monitoring',
      'Aggregated usage statistics',
    ],
  },
  {
    name: 'Preference Cookies',
    icon: Settings,
    color: 'text-purple-500',
    bg: 'bg-purple-500/20',
    borderColor: 'border-purple-500/20',
    required: false,
    description:
      'These cookies remember your preferences and settings to provide a more personalised experience. They allow the platform to remember choices you make such as theme, language, and display options.',
    examples: [
      'Light/dark theme preference',
      'Language and locale settings',
      'Display density preferences',
      'Notification preferences',
    ],
  },
];

const cookieTable = [
  {
    name: 'nexus_session',
    provider: 'Platform',
    purpose: 'Maintains your authenticated session across page loads',
    expiry: 'Session',
    type: 'Essential',
  },
  {
    name: 'nexus_csrf',
    provider: 'Platform',
    purpose: 'Protects against cross-site request forgery attacks',
    expiry: 'Session',
    type: 'Essential',
  },
  {
    name: 'nexus_token',
    provider: 'Platform',
    purpose: 'JWT authentication token for API requests',
    expiry: '7 days',
    type: 'Essential',
  },
  {
    name: 'nexus_refresh',
    provider: 'Platform',
    purpose: 'Refresh token for seamless session renewal',
    expiry: '30 days',
    type: 'Essential',
  },
  {
    name: 'nexus_theme',
    provider: 'Platform',
    purpose: 'Stores your light/dark mode preference',
    expiry: '1 year',
    type: 'Preference',
  },
  {
    name: 'nexus_tenant',
    provider: 'Platform',
    purpose: 'Remembers your selected community/tenant',
    expiry: '30 days',
    type: 'Preference',
  },
  {
    name: 'nexus_locale',
    provider: 'Platform',
    purpose: 'Stores language and regional format preference',
    expiry: '1 year',
    type: 'Preference',
  },
  {
    name: '_ga / _gid',
    provider: 'Google Analytics',
    purpose: 'Distinguishes unique users and tracks page views (anonymised)',
    expiry: '2 years / 24 hours',
    type: 'Analytics',
  },
];

const browserInstructions = [
  { name: 'Chrome', path: 'Settings > Privacy and security > Cookies and other site data' },
  { name: 'Firefox', path: 'Settings > Privacy & Security > Cookies and Site Data' },
  { name: 'Safari', path: 'Preferences > Privacy > Manage Website Data' },
  { name: 'Edge', path: 'Settings > Cookies and site permissions > Cookies and site data' },
];

function scrollToSection(id: string) {
  const el = document.getElementById(id);
  if (el) {
    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}

function getTypeColor(type: string): 'success' | 'primary' | 'secondary' {
  switch (type) {
    case 'Essential':
      return 'success';
    case 'Analytics':
      return 'primary';
    case 'Preference':
      return 'secondary';
    default:
      return 'primary';
  }
}

export function CookiesPage() {
  usePageTitle('Cookie Policy');
  const { branding, tenantPath } = useTenant();
  const { document: customDoc, loading } = useLegalDocument('cookies');

  if (loading) {
    return (
      <div className="flex justify-center items-center min-h-[50vh]">
        <Spinner size="lg" />
      </div>
    );
  }

  if (customDoc) {
    return <CustomLegalDocument document={customDoc} accentColor="amber" />;
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
        <div className="inline-flex p-4 rounded-2xl bg-gradient-to-br from-amber-500/20 to-orange-500/20 mb-4">
          <Cookie className="w-10 h-10 text-amber-500 dark:text-amber-400" aria-hidden="true" />
        </div>
        <h1 className="text-3xl sm:text-4xl font-bold text-theme-primary mb-3">
          Cookie Policy
        </h1>
        <p className="text-theme-muted text-lg max-w-2xl mx-auto">
          How we use cookies and similar technologies on our platform
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
            <button
              key={item.id}
              onClick={() => scrollToSection(item.id)}
              className="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-theme-elevated hover:bg-amber-500/10 text-theme-primary text-sm font-medium transition-colors"
            >
              <item.icon className="w-4 h-4 text-amber-500" aria-hidden="true" />
              {item.label}
            </button>
          ))}
        </div>
      </motion.div>

      {/* What Are Cookies */}
      <motion.div variants={itemVariants} id="what-are-cookies">
        <GlassCard className="p-6 sm:p-8">
          <div className="p-4 rounded-xl bg-amber-500/10 border border-amber-500/20">
            <h2 className="text-xl font-semibold text-theme-primary mb-3 flex items-center gap-2">
              <Cookie className="w-5 h-5 text-amber-500" aria-hidden="true" />
              What Are Cookies?
            </h2>
            <div className="space-y-3 text-theme-muted">
              <p>
                Cookies are small text files that are placed on your device when you visit a
                website. They are widely used to make websites work more efficiently and to
                provide information to the owners of the site.
              </p>
              <p>
                {branding.name} uses cookies and similar technologies to ensure you get the
                best experience on our platform. This policy explains which cookies we use,
                why we use them, and how you can control them.
              </p>
            </div>
          </div>
        </GlassCard>
      </motion.div>

      {/* Cookie Categories */}
      <motion.div variants={itemVariants} id="cookie-categories">
        <GlassCard className="p-6 sm:p-8">
          <h2 className="text-xl font-semibold text-theme-primary mb-6 flex items-center gap-2">
            <Settings className="w-5 h-5 text-amber-500" aria-hidden="true" />
            Cookie Categories
          </h2>

          <div className="space-y-6">
            {cookieCategories.map((cat) => (
              <div
                key={cat.name}
                className={`p-5 rounded-xl bg-theme-elevated border ${cat.borderColor}`}
              >
                <div className="flex flex-wrap items-start justify-between gap-2 mb-3">
                  <h3 className="text-lg font-semibold text-theme-primary flex items-center gap-2">
                    <div className={`p-1.5 rounded-lg ${cat.bg}`}>
                      <cat.icon className={`w-4 h-4 ${cat.color}`} aria-hidden="true" />
                    </div>
                    {cat.name}
                  </h3>
                  <Chip
                    size="sm"
                    variant="flat"
                    color={cat.required ? 'warning' : 'default'}
                    className="text-xs"
                  >
                    {cat.required ? 'Always Active' : 'Optional'}
                  </Chip>
                </div>

                <p className="text-sm text-theme-muted mb-3">{cat.description}</p>

                <div className="space-y-1.5">
                  {cat.examples.map((example) => (
                    <div key={example} className="flex items-center gap-2 text-sm text-theme-subtle">
                      <CheckCircle className={`w-3.5 h-3.5 ${cat.color} flex-shrink-0`} aria-hidden="true" />
                      <span>{example}</span>
                    </div>
                  ))}
                </div>
              </div>
            ))}
          </div>

          <div className="mt-4 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20">
            <p className="text-sm font-medium text-emerald-600 dark:text-emerald-400">
              We do not use marketing or advertising cookies. We never share your data with
              advertisers or ad networks.
            </p>
          </div>
        </GlassCard>
      </motion.div>

      {/* Cookie List */}
      <motion.div variants={itemVariants} id="cookie-list">
        <GlassCard className="p-6 sm:p-8">
          <h2 className="text-xl font-semibold text-theme-primary mb-6 flex items-center gap-2">
            <BarChart3 className="w-5 h-5 text-amber-500" aria-hidden="true" />
            Cookies We Use
          </h2>

          {/* Responsive card-based table */}
          <div className="space-y-3">
            {cookieTable.map((cookie) => (
              <div
                key={cookie.name}
                className="p-4 rounded-xl bg-theme-elevated border border-default-200 dark:border-default-100"
              >
                <div className="flex flex-wrap items-start justify-between gap-2 mb-2">
                  <code className="text-sm font-mono font-semibold text-theme-primary bg-default-100 dark:bg-default-50 px-2 py-0.5 rounded">
                    {cookie.name}
                  </code>
                  <Chip size="sm" variant="flat" color={getTypeColor(cookie.type)} className="text-xs">
                    {cookie.type}
                  </Chip>
                </div>
                <p className="text-sm text-theme-muted mb-2">{cookie.purpose}</p>
                <div className="flex flex-wrap gap-4 text-xs text-theme-subtle">
                  <span>
                    <strong className="text-theme-primary">Provider:</strong> {cookie.provider}
                  </span>
                  <span>
                    <strong className="text-theme-primary">Expiry:</strong> {cookie.expiry}
                  </span>
                </div>
              </div>
            ))}
          </div>
        </GlassCard>
      </motion.div>

      {/* Third-Party Cookies */}
      <motion.div variants={itemVariants}>
        <GlassCard className="p-6 sm:p-8">
          <h2 className="text-xl font-semibold text-theme-primary mb-4 flex items-center gap-2">
            <Globe className="w-5 h-5 text-amber-500" aria-hidden="true" />
            Third-Party Cookies
          </h2>
          <div className="space-y-3 text-theme-muted">
            <p>
              In some cases, we use third-party services that may set their own cookies.
              These services include:
            </p>
            <ul className="space-y-2">
              {[
                { label: 'Google Analytics', desc: 'To understand how visitors use the platform (anonymised IP addresses)' },
                { label: 'Pusher', desc: 'For real-time notifications and messaging functionality' },
              ].map((item) => (
                <li key={item.label} className="flex items-start gap-3">
                  <div className="mt-1 w-1.5 h-1.5 rounded-full bg-amber-500 flex-shrink-0" />
                  <span>
                    <strong className="text-theme-primary">{item.label}:</strong> {item.desc}
                  </span>
                </li>
              ))}
            </ul>
            <p>
              We carefully select our third-party partners and ensure they comply with
              applicable data protection regulations. Third-party cookies are governed by
              the respective third party's privacy policy.
            </p>
          </div>
        </GlassCard>
      </motion.div>

      {/* How to Manage Cookies */}
      <motion.div variants={itemVariants} id="manage-cookies">
        <GlassCard className="p-6 sm:p-8">
          <h2 className="text-xl font-semibold text-theme-primary mb-4 flex items-center gap-2">
            <Monitor className="w-5 h-5 text-amber-500" aria-hidden="true" />
            How to Manage Cookies
          </h2>
          <div className="space-y-4 text-theme-muted">
            <p>
              You can control and manage cookies in several ways. Please note that removing
              or blocking cookies may impact your user experience and some functionality may
              no longer be available.
            </p>

            <div className="p-4 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-start gap-3">
              <Info className="w-5 h-5 text-amber-500 mt-0.5 flex-shrink-0" aria-hidden="true" />
              <p className="text-sm text-theme-muted">
                Disabling essential cookies will prevent you from logging in and using core
                platform features. We recommend keeping essential cookies enabled.
              </p>
            </div>

            <h3 className="font-semibold text-theme-primary mt-6 mb-3">Browser Cookie Settings</h3>
            <p className="text-sm">
              Most web browsers allow you to manage cookies through their settings. Here is
              where to find cookie settings in popular browsers:
            </p>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3">
              {browserInstructions.map((browser) => (
                <div
                  key={browser.name}
                  className="flex items-start gap-3 p-3 rounded-xl bg-theme-elevated"
                >
                  <Monitor className="w-4 h-4 text-amber-500 mt-0.5 flex-shrink-0" aria-hidden="true" />
                  <div>
                    <p className="font-medium text-theme-primary text-sm">{browser.name}</p>
                    <p className="text-xs text-theme-subtle mt-0.5">{browser.path}</p>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </GlassCard>
      </motion.div>

      {/* Contact CTA */}
      <motion.div variants={itemVariants}>
        <GlassCard className="p-6 sm:p-8">
          <div className="text-center">
            <div className="inline-flex p-3 rounded-2xl bg-gradient-to-br from-amber-500/20 to-orange-500/20 mb-4">
              <MessageSquare className="w-8 h-8 text-amber-500 dark:text-amber-400" aria-hidden="true" />
            </div>
            <h2 className="text-xl font-semibold text-theme-primary mb-2">
              Questions About Cookies?
            </h2>
            <p className="text-theme-muted text-sm mb-6 max-w-lg mx-auto">
              If you have any questions about our use of cookies or this policy, please
              do not hesitate to contact us.
            </p>
            <Divider className="my-4" />
            <div className="flex flex-wrap justify-center gap-3 mt-4">
              <Link to={tenantPath('/contact')}>
                <Button
                  className="bg-gradient-to-r from-amber-500 to-orange-600 text-white"
                  startContent={<Send className="w-4 h-4" aria-hidden="true" />}
                >
                  Contact Us
                </Button>
              </Link>
              <Link to={tenantPath('/privacy')}>
                <Button
                  variant="flat"
                  className="bg-theme-elevated text-theme-primary"
                  startContent={<Shield className="w-4 h-4" aria-hidden="true" />}
                >
                  Privacy Policy
                </Button>
              </Link>
            </div>
          </div>
        </GlassCard>
      </motion.div>
    </motion.div>
  );
}

export default CookiesPage;
