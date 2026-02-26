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
  { id: 'data-collection', key: 'privacy.nav_data_collection', icon: Database },
  { id: 'data-usage', key: 'privacy.nav_data_usage', icon: PieChart },
  { id: 'your-rights', key: 'privacy.nav_your_rights', icon: UserCheck },
  { id: 'cookies', key: 'privacy.nav_cookies', icon: Cookie },
];

const dataCollectionKeys = [
  {
    typeKey: 'privacy.data_account_type',
    collectedKey: 'privacy.data_account_collected',
    whyKey: 'privacy.data_account_why',
    basisKey: 'privacy.data_account_basis',
  },
  {
    typeKey: 'privacy.data_profile_type',
    collectedKey: 'privacy.data_profile_collected',
    whyKey: 'privacy.data_profile_why',
    basisKey: 'privacy.data_profile_basis',
  },
  {
    typeKey: 'privacy.data_activity_type',
    collectedKey: 'privacy.data_activity_collected',
    whyKey: 'privacy.data_activity_why',
    basisKey: 'privacy.data_activity_basis',
  },
  {
    typeKey: 'privacy.data_device_type',
    collectedKey: 'privacy.data_device_collected',
    whyKey: 'privacy.data_device_why',
    basisKey: 'privacy.data_device_basis',
  },
  {
    typeKey: 'privacy.data_analytics_type',
    collectedKey: 'privacy.data_analytics_collected',
    whyKey: 'privacy.data_analytics_why',
    basisKey: 'privacy.data_analytics_basis',
  },
];

const gdprRightKeys = [
  {
    icon: Eye,
    titleKey: 'privacy.right_access_title',
    descKey: 'privacy.right_access_desc',
    color: 'text-blue-500',
    bg: 'bg-blue-500/20',
  },
  {
    icon: Pencil,
    titleKey: 'privacy.right_rectification_title',
    descKey: 'privacy.right_rectification_desc',
    color: 'text-emerald-500',
    bg: 'bg-emerald-500/20',
  },
  {
    icon: Trash2,
    titleKey: 'privacy.right_erasure_title',
    descKey: 'privacy.right_erasure_desc',
    color: 'text-red-500',
    bg: 'bg-red-500/20',
  },
  {
    icon: Download,
    titleKey: 'privacy.right_portability_title',
    descKey: 'privacy.right_portability_desc',
    color: 'text-purple-500',
    bg: 'bg-purple-500/20',
  },
  {
    icon: Ban,
    titleKey: 'privacy.right_restrict_title',
    descKey: 'privacy.right_restrict_desc',
    color: 'text-amber-500',
    bg: 'bg-amber-500/20',
  },
  {
    icon: UserCheck,
    titleKey: 'privacy.right_withdraw_title',
    descKey: 'privacy.right_withdraw_desc',
    color: 'text-indigo-500',
    bg: 'bg-indigo-500/20',
  },
];

const cookieCategoryKeys = [
  {
    nameKey: 'privacy.cookie_essential_name',
    descKey: 'privacy.cookie_essential_desc',
    required: true,
  },
  {
    nameKey: 'privacy.cookie_preference_name',
    descKey: 'privacy.cookie_preference_desc',
    required: false,
  },
  {
    nameKey: 'privacy.cookie_analytics_name',
    descKey: 'privacy.cookie_analytics_desc',
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
  const { t } = useTranslation('legal');
  usePageTitle(t('privacy.page_title'));
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
          {t('privacy.heading')}
        </h1>
        <p className="text-theme-muted text-lg max-w-2xl mx-auto">
          {t('privacy.subtitle')}
        </p>
        <div className="flex items-center justify-center gap-2 mt-3 text-sm text-theme-subtle">
          <CalendarDays className="w-4 h-4" aria-hidden="true" />
          <span>{t('privacy.last_updated')}</span>
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
              className="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-theme-elevated hover:bg-indigo-500/10 text-theme-primary text-sm font-medium transition-colors h-auto min-w-0"
            >
              <item.icon className="w-4 h-4 text-indigo-500" aria-hidden="true" />
              {t(item.key)}
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
              {t('privacy.commitment_title')}
            </h2>
            <div className="space-y-3 text-theme-muted">
              <p>
                {t('privacy.commitment_body_1', { name: branding.name })}
              </p>
              <p>
                {t('privacy.commitment_body_2_before')}{' '}
                <strong className="text-theme-primary">{t('privacy.commitment_body_2_transparency')}</strong>{' '}
                {t('privacy.commitment_body_2_and')}{' '}
                <strong className="text-theme-primary">{t('privacy.commitment_body_2_control')}</strong>.{' '}
                {t('privacy.commitment_body_2_after')}
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
            {t('privacy.data_collection_title')}
          </h2>
          <p className="text-theme-muted mb-6">
            {t('privacy.data_collection_intro')}
          </p>

          {/* Data Table - responsive card layout */}
          <div className="space-y-3">
            {dataCollectionKeys.map((row) => (
              <div
                key={row.typeKey}
                className="p-4 rounded-xl bg-theme-elevated border border-default-200 dark:border-default-100"
              >
                <div className="flex flex-wrap items-start justify-between gap-2 mb-2">
                  <h3 className="font-semibold text-theme-primary">{t(row.typeKey)}</h3>
                  <Chip size="sm" variant="flat" color="primary" className="text-xs">
                    {t(row.basisKey)}
                  </Chip>
                </div>
                <p className="text-sm text-theme-muted mb-1">{t(row.collectedKey)}</p>
                <p className="text-xs text-theme-subtle">{t(row.whyKey)}</p>
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
            {t('privacy.data_usage_title')}
          </h2>
          <p className="text-theme-muted mb-4">
            {t('privacy.data_usage_intro')}
          </p>
          <ul className="space-y-3 text-theme-muted">
            {([
              { labelKey: 'privacy.usage_delivery_label', descKey: 'privacy.usage_delivery_desc' },
              { labelKey: 'privacy.usage_communication_label', descKey: 'privacy.usage_communication_desc' },
              { labelKey: 'privacy.usage_security_label', descKey: 'privacy.usage_security_desc' },
              { labelKey: 'privacy.usage_improvement_label', descKey: 'privacy.usage_improvement_desc' },
              { labelKey: 'privacy.usage_legal_label', descKey: 'privacy.usage_legal_desc' },
            ] as const).map((item) => (
              <li key={item.labelKey} className="flex items-start gap-3">
                <div className="mt-1 w-1.5 h-1.5 rounded-full bg-indigo-500 flex-shrink-0" />
                <span>
                  <strong className="text-theme-primary">{t(item.labelKey)}:</strong> {t(item.descKey)}
                </span>
              </li>
            ))}
          </ul>

          <div className="mt-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20">
            <p className="text-sm font-medium text-emerald-600 dark:text-emerald-400">
              {t('privacy.no_sell_data')}
            </p>
          </div>
        </GlassCard>
      </motion.div>

      {/* Profile Visibility */}
      <motion.div variants={itemVariants}>
        <GlassCard className="p-6 sm:p-8">
          <h2 className="text-xl font-semibold text-theme-primary mb-4 flex items-center gap-2">
            <Eye className="w-5 h-5 text-indigo-500" aria-hidden="true" />
            {t('privacy.visibility_title')}
          </h2>
          <div className="space-y-3 text-theme-muted">
            <p>
              {t('privacy.visibility_body_1')}
            </p>
            <p>
              {t('privacy.visibility_body_2_before')}{' '}
              <strong className="text-theme-primary">{t('privacy.visibility_body_2_emphasis')}</strong>.{' '}
              {t('privacy.visibility_body_2_after')}
            </p>
          </div>
        </GlassCard>
      </motion.div>

      {/* Data Protection */}
      <motion.div variants={itemVariants}>
        <GlassCard className="p-6 sm:p-8">
          <h2 className="text-xl font-semibold text-theme-primary mb-4 flex items-center gap-2">
            <Lock className="w-5 h-5 text-indigo-500" aria-hidden="true" />
            {t('privacy.protection_title')}
          </h2>
          <p className="text-theme-muted mb-4">
            {t('privacy.protection_intro')}
          </p>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            {([
              { labelKey: 'privacy.protect_encryption_label', descKey: 'privacy.protect_encryption_desc' },
              { labelKey: 'privacy.protect_passwords_label', descKey: 'privacy.protect_passwords_desc' },
              { labelKey: 'privacy.protect_access_label', descKey: 'privacy.protect_access_desc' },
              { labelKey: 'privacy.protect_audits_label', descKey: 'privacy.protect_audits_desc' },
              { labelKey: 'privacy.protect_infrastructure_label', descKey: 'privacy.protect_infrastructure_desc' },
            ] as const).map((item) => (
              <div
                key={item.labelKey}
                className="flex items-start gap-3 p-3 rounded-xl bg-theme-elevated"
              >
                <Lock className="w-4 h-4 text-indigo-500 mt-0.5 flex-shrink-0" aria-hidden="true" />
                <div>
                  <p className="font-medium text-theme-primary text-sm">{t(item.labelKey)}</p>
                  <p className="text-xs text-theme-subtle mt-0.5">{t(item.descKey)}</p>
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
              {t('privacy.rights_title')}
            </h2>
            <p className="text-theme-muted text-sm">
              {t('privacy.rights_intro')}
            </p>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {gdprRightKeys.map((right) => (
              <div
                key={right.titleKey}
                className="flex items-start gap-3 p-4 rounded-xl bg-theme-elevated"
              >
                <div className={`p-2 rounded-lg ${right.bg} flex-shrink-0`}>
                  <right.icon className={`w-4 h-4 ${right.color}`} aria-hidden="true" />
                </div>
                <div>
                  <h3 className="font-medium text-theme-primary text-sm">{t(right.titleKey)}</h3>
                  <p className="text-xs text-theme-subtle mt-1">{t(right.descKey)}</p>
                </div>
              </div>
            ))}
          </div>

          <p className="text-sm text-theme-muted mt-4">
            {t('privacy.rights_contact')}
          </p>
        </GlassCard>
      </motion.div>

      {/* Cookies */}
      <motion.div variants={itemVariants} id="cookies">
        <GlassCard className="p-6 sm:p-8">
          <h2 className="text-xl font-semibold text-theme-primary mb-4 flex items-center gap-2">
            <Cookie className="w-5 h-5 text-indigo-500" aria-hidden="true" />
            {t('privacy.cookies_title')}
          </h2>
          <p className="text-theme-muted mb-4">
            {t('privacy.cookies_intro')}
          </p>

          <div className="space-y-3">
            {cookieCategoryKeys.map((cat) => (
              <div
                key={cat.nameKey}
                className="flex items-start gap-3 p-4 rounded-xl bg-theme-elevated border border-default-200 dark:border-default-100"
              >
                <div className="flex-1">
                  <div className="flex items-center gap-2 mb-1">
                    <h3 className="font-medium text-theme-primary text-sm">{t(cat.nameKey)}</h3>
                    {cat.required && (
                      <Chip size="sm" variant="flat" color="warning" className="text-xs">
                        {t('privacy.cookie_required')}
                      </Chip>
                    )}
                  </div>
                  <p className="text-xs text-theme-subtle">{t(cat.descKey)}</p>
                </div>
              </div>
            ))}
          </div>

          <p className="text-sm text-theme-muted mt-4">
            {t('privacy.cookies_no_ads_before')}{' '}
            <strong className="text-theme-primary">{t('privacy.cookies_no_ads_not')}</strong>{' '}
            {t('privacy.cookies_no_ads_after')}{' '}
            <Link to={tenantPath('/cookies')} className="text-indigo-500 hover:underline">
              {t('privacy.cookie_policy_link')}
            </Link>.
          </p>
        </GlassCard>
      </motion.div>

      {/* Data Retention */}
      <motion.div variants={itemVariants}>
        <GlassCard className="p-6 sm:p-8">
          <h2 className="text-xl font-semibold text-theme-primary mb-4 flex items-center gap-2">
            <Clock className="w-5 h-5 text-indigo-500" aria-hidden="true" />
            {t('privacy.retention_title')}
          </h2>
          <p className="text-theme-muted mb-4">
            {t('privacy.retention_intro')}
          </p>
          <ul className="space-y-3 text-theme-muted">
            {([
              { labelKey: 'privacy.retention_active_label', descKey: 'privacy.retention_active_desc' },
              { labelKey: 'privacy.retention_deleted_label', descKey: 'privacy.retention_deleted_desc' },
              { labelKey: 'privacy.retention_transactions_label', descKey: 'privacy.retention_transactions_desc' },
              { labelKey: 'privacy.retention_logs_label', descKey: 'privacy.retention_logs_desc' },
            ] as const).map((item) => (
              <li key={item.labelKey} className="flex items-start gap-3">
                <div className="mt-1 w-1.5 h-1.5 rounded-full bg-indigo-500 flex-shrink-0" />
                <span>
                  <strong className="text-theme-primary">{t(item.labelKey)}:</strong> {t(item.descKey)}
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
              {t('privacy.cta_title')}
            </h2>
            <p className="text-theme-muted text-sm mb-6 max-w-lg mx-auto">
              {t('privacy.cta_body')}
            </p>
            <Divider className="my-4" />
            <div className="flex flex-wrap justify-center gap-3 mt-4">
              <Link to={tenantPath('/contact')}>
                <Button
                  className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                  startContent={<Send className="w-4 h-4" aria-hidden="true" />}
                >
                  {t('privacy.contact_privacy_team')}
                </Button>
              </Link>
              <Link to={tenantPath('/cookies')}>
                <Button
                  variant="flat"
                  className="bg-theme-elevated text-theme-primary"
                  startContent={<Cookie className="w-4 h-4" aria-hidden="true" />}
                >
                  {t('privacy.cookie_policy_link')}
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
