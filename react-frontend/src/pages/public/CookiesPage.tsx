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
import { useTranslation } from 'react-i18next';
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

const quickNavIcons = [
  { id: 'what-are-cookies', key: 'cookies.nav_what_are', icon: Cookie },
  { id: 'cookie-categories', key: 'cookies.nav_categories', icon: Settings },
  { id: 'cookie-list', key: 'cookies.nav_cookie_list', icon: BarChart3 },
  { id: 'manage-cookies', key: 'cookies.nav_manage', icon: Monitor },
];

const cookieCategoryDefs = [
  {
    nameKey: 'cookies.cat_essential_name',
    icon: Lock,
    color: 'text-emerald-500',
    bg: 'bg-emerald-500/20',
    borderColor: 'border-emerald-500/20',
    required: true,
    descKey: 'cookies.cat_essential_desc',
    exampleKeys: [
      'cookies.cat_essential_ex1',
      'cookies.cat_essential_ex2',
      'cookies.cat_essential_ex3',
      'cookies.cat_essential_ex4',
    ],
  },
  {
    nameKey: 'cookies.cat_analytics_name',
    icon: BarChart3,
    color: 'text-blue-500',
    bg: 'bg-blue-500/20',
    borderColor: 'border-blue-500/20',
    required: false,
    descKey: 'cookies.cat_analytics_desc',
    exampleKeys: [
      'cookies.cat_analytics_ex1',
      'cookies.cat_analytics_ex2',
      'cookies.cat_analytics_ex3',
      'cookies.cat_analytics_ex4',
    ],
  },
  {
    nameKey: 'cookies.cat_preference_name',
    icon: Settings,
    color: 'text-purple-500',
    bg: 'bg-purple-500/20',
    borderColor: 'border-purple-500/20',
    required: false,
    descKey: 'cookies.cat_preference_desc',
    exampleKeys: [
      'cookies.cat_preference_ex1',
      'cookies.cat_preference_ex2',
      'cookies.cat_preference_ex3',
      'cookies.cat_preference_ex4',
    ],
  },
];

const cookieTableDefs = [
  { name: 'nexus_session', providerKey: 'cookies.provider_platform', purposeKey: 'cookies.cookie_session_purpose', expiryKey: 'cookies.expiry_session', typeKey: 'cookies.type_essential', rawType: 'Essential' as const },
  { name: 'nexus_csrf', providerKey: 'cookies.provider_platform', purposeKey: 'cookies.cookie_csrf_purpose', expiryKey: 'cookies.expiry_session', typeKey: 'cookies.type_essential', rawType: 'Essential' as const },
  { name: 'nexus_token', providerKey: 'cookies.provider_platform', purposeKey: 'cookies.cookie_token_purpose', expiryKey: 'cookies.expiry_7_days', typeKey: 'cookies.type_essential', rawType: 'Essential' as const },
  { name: 'nexus_refresh', providerKey: 'cookies.provider_platform', purposeKey: 'cookies.cookie_refresh_purpose', expiryKey: 'cookies.expiry_30_days', typeKey: 'cookies.type_essential', rawType: 'Essential' as const },
  { name: 'nexus_theme', providerKey: 'cookies.provider_platform', purposeKey: 'cookies.cookie_theme_purpose', expiryKey: 'cookies.expiry_1_year', typeKey: 'cookies.type_preference', rawType: 'Preference' as const },
  { name: 'nexus_tenant', providerKey: 'cookies.provider_platform', purposeKey: 'cookies.cookie_tenant_purpose', expiryKey: 'cookies.expiry_30_days', typeKey: 'cookies.type_preference', rawType: 'Preference' as const },
  { name: 'nexus_locale', providerKey: 'cookies.provider_platform', purposeKey: 'cookies.cookie_locale_purpose', expiryKey: 'cookies.expiry_1_year', typeKey: 'cookies.type_preference', rawType: 'Preference' as const },
  { name: 'nexus_cookie_consent', providerKey: 'cookies.provider_platform', purposeKey: 'cookies.cookie_consent_purpose', expiryKey: 'cookies.expiry_6_months', typeKey: 'cookies.type_essential', rawType: 'Essential' as const },
  { name: 'sentry-*', providerKey: 'cookies.provider_sentry', purposeKey: 'cookies.cookie_sentry_purpose', expiryKey: 'cookies.expiry_session', typeKey: 'cookies.type_analytics', rawType: 'Analytics' as const },
];

const browserInstructionKeys = [
  { nameKey: 'cookies.browser_chrome', pathKey: 'cookies.browser_chrome_path' },
  { nameKey: 'cookies.browser_firefox', pathKey: 'cookies.browser_firefox_path' },
  { nameKey: 'cookies.browser_safari', pathKey: 'cookies.browser_safari_path' },
  { nameKey: 'cookies.browser_edge', pathKey: 'cookies.browser_edge_path' },
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
  const { t } = useTranslation('legal');
  usePageTitle(t('cookies.page_title'));
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
          {t('cookies.heading')}
        </h1>
        <p className="text-theme-muted text-lg max-w-2xl mx-auto">
          {t('cookies.subtitle')}
        </p>
        <div className="flex items-center justify-center gap-2 mt-3 text-sm text-theme-subtle">
          <CalendarDays className="w-4 h-4" aria-hidden="true" />
          <span>{t('cookies.last_updated')}</span>
        </div>
      </motion.div>

      {/* Quick Navigation */}
      <motion.div variants={itemVariants}>
        <div className="flex flex-wrap justify-center gap-3">
          {quickNavIcons.map((item) => (
            <Button
              key={item.id}
              variant="light"
              onPress={() => scrollToSection(item.id)}
              className="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-theme-elevated hover:bg-amber-500/10 text-theme-primary text-sm font-medium transition-colors h-auto min-w-0"
            >
              <item.icon className="w-4 h-4 text-amber-500" aria-hidden="true" />
              {t(item.key)}
            </Button>
          ))}
        </div>
      </motion.div>

      {/* What Are Cookies */}
      <motion.div variants={itemVariants} id="what-are-cookies">
        <GlassCard className="p-6 sm:p-8">
          <div className="p-4 rounded-xl bg-amber-500/10 border border-amber-500/20">
            <h2 className="text-xl font-semibold text-theme-primary mb-3 flex items-center gap-2">
              <Cookie className="w-5 h-5 text-amber-500" aria-hidden="true" />
              {t('cookies.what_are_title')}
            </h2>
            <div className="space-y-3 text-theme-muted">
              <p>
                {t('cookies.what_are_body_1')}
              </p>
              <p>
                {t('cookies.what_are_body_2', { name: branding.name })}
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
            {t('cookies.categories_title')}
          </h2>

          <div className="space-y-6">
            {cookieCategoryDefs.map((cat) => (
              <div
                key={cat.nameKey}
                className={`p-5 rounded-xl bg-theme-elevated border ${cat.borderColor}`}
              >
                <div className="flex flex-wrap items-start justify-between gap-2 mb-3">
                  <h3 className="text-lg font-semibold text-theme-primary flex items-center gap-2">
                    <div className={`p-1.5 rounded-lg ${cat.bg}`}>
                      <cat.icon className={`w-4 h-4 ${cat.color}`} aria-hidden="true" />
                    </div>
                    {t(cat.nameKey)}
                  </h3>
                  <Chip
                    size="sm"
                    variant="flat"
                    color={cat.required ? 'warning' : 'default'}
                    className="text-xs"
                  >
                    {cat.required ? t('cookies.always_active') : t('cookies.optional')}
                  </Chip>
                </div>

                <p className="text-sm text-theme-muted mb-3">{t(cat.descKey)}</p>

                <div className="space-y-1.5">
                  {cat.exampleKeys.map((exKey) => (
                    <div key={exKey} className="flex items-center gap-2 text-sm text-theme-subtle">
                      <CheckCircle className={`w-3.5 h-3.5 ${cat.color} flex-shrink-0`} aria-hidden="true" />
                      <span>{t(exKey)}</span>
                    </div>
                  ))}
                </div>
              </div>
            ))}
          </div>

          <div className="mt-4 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20">
            <p className="text-sm font-medium text-emerald-600 dark:text-emerald-400">
              {t('cookies.no_marketing')}
            </p>
          </div>
        </GlassCard>
      </motion.div>

      {/* Cookie List */}
      <motion.div variants={itemVariants} id="cookie-list">
        <GlassCard className="p-6 sm:p-8">
          <h2 className="text-xl font-semibold text-theme-primary mb-6 flex items-center gap-2">
            <BarChart3 className="w-5 h-5 text-amber-500" aria-hidden="true" />
            {t('cookies.list_title')}
          </h2>

          {/* Responsive card-based table */}
          <div className="space-y-3">
            {cookieTableDefs.map((cookie) => (
              <div
                key={cookie.name}
                className="p-4 rounded-xl bg-theme-elevated border border-default-200 dark:border-default-100"
              >
                <div className="flex flex-wrap items-start justify-between gap-2 mb-2">
                  <code className="text-sm font-mono font-semibold text-theme-primary bg-default-100 dark:bg-default-50 px-2 py-0.5 rounded">
                    {cookie.name}
                  </code>
                  <Chip size="sm" variant="flat" color={getTypeColor(cookie.rawType)} className="text-xs">
                    {t(cookie.typeKey)}
                  </Chip>
                </div>
                <p className="text-sm text-theme-muted mb-2">{t(cookie.purposeKey)}</p>
                <div className="flex flex-wrap gap-4 text-xs text-theme-subtle">
                  <span>
                    <strong className="text-theme-primary">{t('cookies.provider_label')}:</strong> {t(cookie.providerKey)}
                  </span>
                  <span>
                    <strong className="text-theme-primary">{t('cookies.expiry_label')}:</strong> {t(cookie.expiryKey)}
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
            {t('cookies.third_party_title')}
          </h2>
          <div className="space-y-3 text-theme-muted">
            <p>
              {t('cookies.third_party_intro')}
            </p>
            <ul className="space-y-2">
              {([
                { labelKey: 'cookies.third_party_sentry_label', descKey: 'cookies.third_party_sentry_desc' },
                { labelKey: 'cookies.third_party_pusher_label', descKey: 'cookies.third_party_pusher_desc' },
              ] as const).map((item) => (
                <li key={item.labelKey} className="flex items-start gap-3">
                  <div className="mt-1 w-1.5 h-1.5 rounded-full bg-amber-500 flex-shrink-0" />
                  <span>
                    <strong className="text-theme-primary">{t(item.labelKey)}:</strong> {t(item.descKey)}
                  </span>
                </li>
              ))}
            </ul>
            <p>
              {t('cookies.third_party_compliance')}
            </p>
          </div>
        </GlassCard>
      </motion.div>

      {/* How to Manage Cookies */}
      <motion.div variants={itemVariants} id="manage-cookies">
        <GlassCard className="p-6 sm:p-8">
          <h2 className="text-xl font-semibold text-theme-primary mb-4 flex items-center gap-2">
            <Monitor className="w-5 h-5 text-amber-500" aria-hidden="true" />
            {t('cookies.manage_title')}
          </h2>
          <div className="space-y-4 text-theme-muted">
            <p>
              {t('cookies.manage_intro')}
            </p>

            <div className="p-4 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-start gap-3">
              <Info className="w-5 h-5 text-amber-500 mt-0.5 flex-shrink-0" aria-hidden="true" />
              <p className="text-sm text-theme-muted">
                {t('cookies.manage_warning')}
              </p>
            </div>

            <h3 className="font-semibold text-theme-primary mt-6 mb-3">{t('cookies.browser_settings_title')}</h3>
            <p className="text-sm">
              {t('cookies.browser_settings_intro')}
            </p>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3">
              {browserInstructionKeys.map((browser) => (
                <div
                  key={browser.nameKey}
                  className="flex items-start gap-3 p-3 rounded-xl bg-theme-elevated"
                >
                  <Monitor className="w-4 h-4 text-amber-500 mt-0.5 flex-shrink-0" aria-hidden="true" />
                  <div>
                    <p className="font-medium text-theme-primary text-sm">{t(browser.nameKey)}</p>
                    <p className="text-xs text-theme-subtle mt-0.5">{t(browser.pathKey)}</p>
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
              {t('cookies.cta_title')}
            </h2>
            <p className="text-theme-muted text-sm mb-6 max-w-lg mx-auto">
              {t('cookies.cta_body')}
            </p>
            <Divider className="my-4" />
            <div className="flex flex-wrap justify-center gap-3 mt-4">
              <Link to={tenantPath('/contact')}>
                <Button
                  className="bg-gradient-to-r from-amber-500 to-orange-600 text-white"
                  startContent={<Send className="w-4 h-4" aria-hidden="true" />}
                >
                  {t('cookies.contact_us')}
                </Button>
              </Link>
              <Link to={tenantPath('/privacy')}>
                <Button
                  variant="flat"
                  className="bg-theme-elevated text-theme-primary"
                  startContent={<Shield className="w-4 h-4" aria-hidden="true" />}
                >
                  {t('cookies.privacy_policy_link')}
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
