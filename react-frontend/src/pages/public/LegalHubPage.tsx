// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Legal Hub Page
 *
 * Central hub linking to all legal and compliance documents.
 * GDPR transparency commitment with cards for each legal document.
 */

import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Divider } from '@heroui/react';
import {
  Scale,
  Shield,
  FileText,
  Cookie,
  Accessibility,
  CalendarDays,
  ArrowRight,
  Handshake,
  Send,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';

const containerVariants = {
  hidden: { opacity: 0 },
  visible: {
    opacity: 1,
    transition: { staggerChildren: 0.1 },
  },
};

const itemVariants = {
  hidden: { opacity: 0, y: 20 },
  visible: { opacity: 1, y: 0 },
};

const legalDocumentDefs = [
  {
    titleKey: 'hub.doc_privacy_title',
    descKey: 'hub.doc_privacy_desc',
    icon: Shield,
    path: '/privacy',
    color: 'text-indigo-500',
    bg: 'bg-indigo-500/20',
    gradient: 'from-indigo-500/20 to-purple-500/20',
    updatedKey: 'hub.doc_updated_feb_2026',
  },
  {
    titleKey: 'hub.doc_terms_title',
    descKey: 'hub.doc_terms_desc',
    icon: FileText,
    path: '/terms',
    color: 'text-blue-500',
    bg: 'bg-blue-500/20',
    gradient: 'from-blue-500/20 to-cyan-500/20',
    updatedKey: 'hub.doc_updated_feb_2026',
  },
  {
    titleKey: 'hub.doc_cookies_title',
    descKey: 'hub.doc_cookies_desc',
    icon: Cookie,
    path: '/cookies',
    color: 'text-amber-500',
    bg: 'bg-amber-500/20',
    gradient: 'from-amber-500/20 to-orange-500/20',
    updatedKey: 'hub.doc_updated_feb_2026',
  },
  {
    titleKey: 'hub.doc_accessibility_title',
    descKey: 'hub.doc_accessibility_desc',
    icon: Accessibility,
    path: '/accessibility',
    color: 'text-emerald-500',
    bg: 'bg-emerald-500/20',
    gradient: 'from-emerald-500/20 to-green-500/20',
    updatedKey: 'hub.doc_updated_feb_2026',
  },
];

export function LegalHubPage() {
  const { t } = useTranslation('legal');
  usePageTitle(t('hub.page_title'));
  const { branding, tenantPath } = useTenant();

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
          <Scale className="w-10 h-10 text-indigo-500 dark:text-indigo-400" aria-hidden="true" />
        </div>
        <h1 className="text-3xl sm:text-4xl font-bold text-theme-primary mb-3">
          {t('hub.heading')}
        </h1>
        <p className="text-theme-muted text-lg max-w-2xl mx-auto">
          {t('hub.subtitle')}
        </p>
      </motion.div>

      {/* GDPR Commitment Banner */}
      <motion.div variants={itemVariants}>
        <GlassCard className="p-6 sm:p-8">
          <div className="p-4 rounded-xl bg-indigo-500/10 border border-indigo-500/20">
            <h2 className="text-xl font-semibold text-theme-primary mb-3 flex items-center gap-2">
              <Handshake className="w-5 h-5 text-indigo-500" aria-hidden="true" />
              {t('hub.commitment_title')}
            </h2>
            <div className="space-y-3 text-theme-muted">
              <p>
                {t('hub.commitment_body_1', { name: branding.name })}
              </p>
              <p>
                {t('hub.commitment_body_2_before')}{' '}
                <strong className="text-theme-primary">{t('hub.commitment_body_2_emphasis')}</strong>{' '}
                {t('hub.commitment_body_2_after')}
              </p>
            </div>
          </div>
        </GlassCard>
      </motion.div>

      {/* Legal Document Cards */}
      <motion.div variants={itemVariants}>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          {legalDocumentDefs.map((doc) => (
            <GlassCard key={doc.titleKey} hoverable className="p-6 flex flex-col">
              <div className="flex items-start gap-4 mb-4">
                <div className={`p-3 rounded-xl bg-gradient-to-br ${doc.gradient} flex-shrink-0`}>
                  <doc.icon className={`w-6 h-6 ${doc.color}`} aria-hidden="true" />
                </div>
                <div className="flex-1 min-w-0">
                  <h3 className="text-lg font-semibold text-theme-primary">{t(doc.titleKey)}</h3>
                  <div className="flex items-center gap-1.5 mt-1 text-xs text-theme-subtle">
                    <CalendarDays className="w-3.5 h-3.5" aria-hidden="true" />
                    <span>{t(doc.updatedKey)}</span>
                  </div>
                </div>
              </div>
              <p className="text-sm text-theme-muted flex-1 mb-4">
                {t(doc.descKey)}
              </p>
              <Link to={tenantPath(doc.path)}>
                <Button
                  variant="flat"
                  className="w-full bg-theme-elevated text-theme-primary"
                  endContent={<ArrowRight className="w-4 h-4" aria-hidden="true" />}
                >
                  {t('hub.read_document')}
                </Button>
              </Link>
            </GlassCard>
          ))}
        </div>
      </motion.div>

      {/* Contact CTA */}
      <motion.div variants={itemVariants}>
        <GlassCard className="p-6 sm:p-8">
          <div className="text-center">
            <div className="inline-flex p-3 rounded-2xl bg-gradient-to-br from-indigo-500/20 to-purple-500/20 mb-4">
              <Scale className="w-8 h-8 text-indigo-500 dark:text-indigo-400" aria-hidden="true" />
            </div>
            <h2 className="text-xl font-semibold text-theme-primary mb-2">
              {t('hub.cta_title')}
            </h2>
            <p className="text-theme-muted text-sm mb-6 max-w-lg mx-auto">
              {t('hub.cta_body')}
            </p>
            <Divider className="my-4" />
            <div className="flex flex-wrap justify-center gap-3 mt-4">
              <Link to={tenantPath('/contact')}>
                <Button
                  className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                  startContent={<Send className="w-4 h-4" aria-hidden="true" />}
                >
                  {t('hub.contact_team')}
                </Button>
              </Link>
              <Link to={tenantPath('/privacy')}>
                <Button
                  variant="flat"
                  className="bg-theme-elevated text-theme-primary"
                  startContent={<Shield className="w-4 h-4" aria-hidden="true" />}
                >
                  {t('hub.privacy_policy_link')}
                </Button>
              </Link>
            </div>
          </div>
        </GlassCard>
      </motion.div>
    </motion.div>
  );
}

export default LegalHubPage;
