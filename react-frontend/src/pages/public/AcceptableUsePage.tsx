// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Acceptable Use Policy Page
 *
 * Displays the tenant's acceptable use policy if one exists.
 * Unlike terms/privacy/cookies/accessibility, there is no hardcoded
 * fallback — if no custom document is configured, a friendly "not
 * available" message is shown instead.
 */

import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Spinner } from '@heroui/react';
import { ShieldCheck, ArrowLeft, FileText, Send } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { CustomLegalDocument } from '@/components/legal/CustomLegalDocument';
import { useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { useLegalDocument } from '@/hooks/useLegalDocument';

const containerVariants = {
  hidden: { opacity: 0 },
  visible: { opacity: 1, transition: { staggerChildren: 0.08 } },
};

const itemVariants = {
  hidden: { opacity: 0, y: 20 },
  visible: { opacity: 1, y: 0 },
};

export function AcceptableUsePage() {
  const { t } = useTranslation('legal');
  usePageTitle(t('acceptable_use.page_title', 'Acceptable Use Policy'));
  const { tenantPath, branding } = useTenant();
  const { document: customDoc, loading } = useLegalDocument('acceptable_use');

  if (loading) {
    return (
      <div className="flex justify-center items-center min-h-[50vh]">
        <Spinner size="lg" />
      </div>
    );
  }

  // Custom document exists — render it
  if (customDoc) {
    return <CustomLegalDocument document={customDoc} />;
  }

  // No custom document — show placeholder
  return (
    <motion.div
      variants={containerVariants}
      initial="hidden"
      animate="visible"
      className="max-w-4xl mx-auto space-y-6"
    >
      <motion.div variants={itemVariants} className="text-center">
        <div className="inline-flex p-4 rounded-2xl bg-gradient-to-br from-emerald-500/20 to-teal-500/20 mb-4">
          <ShieldCheck className="w-10 h-10 text-emerald-500 dark:text-emerald-400" aria-hidden="true" />
        </div>
        <h1 className="text-3xl sm:text-4xl font-bold text-theme-primary mb-3">
          {t('acceptable_use.heading', 'Acceptable Use Policy')}
        </h1>
        <p className="text-theme-muted text-lg max-w-2xl mx-auto">
          {branding?.name
            ? t('acceptable_use.subtitle_with_name', {
                name: branding.name,
                defaultValue: `${branding.name}'s acceptable use policy is being prepared.`,
              })
            : t('acceptable_use.subtitle_generic', 'The acceptable use policy is being prepared.')}
        </p>
      </motion.div>

      <motion.div variants={itemVariants}>
        <GlassCard className="p-8 text-center">
          <FileText className="w-12 h-12 text-theme-subtle mx-auto mb-3" aria-hidden="true" />
          <p className="text-theme-muted mb-4">
            {t(
              'acceptable_use.not_available',
              'The acceptable use policy has not been published yet. Please check back later or contact us for more information.',
            )}
          </p>
          <div className="flex justify-center gap-3">
            <Link to={tenantPath('/contact')}>
              <Button
                variant="flat"
                className="bg-theme-elevated text-theme-primary"
                startContent={<Send className="w-4 h-4" aria-hidden="true" />}
              >
                {t('common.contact_us', 'Contact Us')}
              </Button>
            </Link>
            <Link to={tenantPath('/legal')}>
              <Button
                variant="light"
                className="text-theme-muted"
                startContent={<ArrowLeft className="w-4 h-4" aria-hidden="true" />}
              >
                {t('common.back_to_legal', 'All Legal Documents')}
              </Button>
            </Link>
          </div>
        </GlassCard>
      </motion.div>
    </motion.div>
  );
}

export default AcceptableUsePage;
