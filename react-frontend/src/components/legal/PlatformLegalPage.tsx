// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * PlatformLegalPage — shared wrapper for platform-level legal documents.
 *
 * Renders a page with Project NEXUS branding (not tenant branding),
 * a "Platform Provider Notice" banner, optional table of contents,
 * sections in GlassCards, and a footer CTA linking to project-nexus.ie.
 *
 * Used by: PlatformTermsPage, PlatformPrivacyPage, PlatformDisclaimerPage
 */

import { useMemo } from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { motion } from 'framer-motion';
import { Button, Chip } from '@heroui/react';
import Hexagon from 'lucide-react/icons/hexagon';
import CalendarDays from 'lucide-react/icons/calendar-days';
import ExternalLink from 'lucide-react/icons/external-link';
import ArrowRight from 'lucide-react/icons/arrow-right';
import Info from 'lucide-react/icons/info';
import type { LucideIcon } from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';

/* ── Animation variants (consistent with tenant legal pages) ── */

const containerVariants = {
  hidden: { opacity: 0 },
  visible: { opacity: 1, transition: { staggerChildren: 0.07 } },
};

const itemVariants = {
  hidden: { opacity: 0, y: 20 },
  visible: { opacity: 1, y: 0 },
};

/* ── Types ── */

export interface PlatformLegalSection {
  id: string;
  title: string;
  content: React.ReactNode;
}

interface PlatformLegalPageProps {
  /** Document title (e.g., "Platform Terms of Service") */
  title: string;
  /** Short subtitle below the title */
  subtitle: string;
  /** Lucide icon for the header */
  icon: LucideIcon;
  /** Effective date string (e.g., "1 March 2026") */
  effectiveDate: string;
  /** Ordered sections to render */
  sections: PlatformLegalSection[];
  /** Cross-links to other platform legal pages */
  crossLinks?: { label: string; to: string }[];
}

export function PlatformLegalPage({
  title,
  subtitle,
  icon: Icon,
  effectiveDate,
  sections,
  crossLinks,
}: PlatformLegalPageProps) {
  const { t } = useTranslation('legal');
  usePageTitle(`${title} — Project NEXUS`);
  const { tenantPath, tenant, branding } = useTenant();

  const showToc = sections.length >= 4;

  const tenantName = useMemo(
    () => branding?.name || tenant?.name || 'your community',
    [branding, tenant],
  );

  return (
    <motion.div
      variants={containerVariants}
      initial="hidden"
      animate="visible"
      className="max-w-4xl mx-auto space-y-6"
    >
      {/* ── Hero header with NEXUS branding ── */}
      <motion.div variants={itemVariants} className="text-center">
        <div className="inline-flex p-4 rounded-2xl bg-gradient-to-br from-slate-500/20 to-blue-500/20 mb-4">
          <Hexagon className="w-10 h-10 text-slate-500 dark:text-slate-400" aria-hidden="true" />
        </div>
        <h1 className="text-3xl sm:text-4xl font-bold text-theme-primary mb-2">
          {title}
        </h1>
        <p className="text-theme-muted text-lg max-w-2xl mx-auto mb-3">
          {subtitle}
        </p>
        <div className="flex items-center justify-center gap-3 flex-wrap">
          <Chip
            size="sm"
            variant="flat"
            startContent={<CalendarDays className="w-3 h-3" />}
            classNames={{ base: 'bg-slate-500/10 text-slate-600 dark:text-slate-400' }}
          >
            {t('platform.effective_date', { date: effectiveDate })}
          </Chip>
          <Chip
            size="sm"
            variant="flat"
            startContent={<Hexagon className="w-3 h-3" />}
            classNames={{ base: 'bg-blue-500/10 text-blue-600 dark:text-blue-400' }}
          >
            {t('platform.platform_chip')}
          </Chip>
        </div>
      </motion.div>

      {/* ── Platform Provider Notice ── */}
      <motion.div variants={itemVariants}>
        <GlassCard className="p-5 sm:p-6">
          <div className="flex items-start gap-3 p-4 rounded-xl bg-blue-500/10 border border-blue-500/20">
            <Info className="w-5 h-5 text-[var(--color-info)] mt-0.5 shrink-0" aria-hidden="true" />
            <div>
              <h2 className="font-semibold text-sm text-blue-600 dark:text-blue-400 mb-1">
                {t('platform.provider_notice_title')}
              </h2>
              <p className="text-sm text-theme-muted">
                {t('platform.provider_notice_body', { tenant: tenantName })}
              </p>
              <Link
                to={tenantPath('/legal')}
                className="inline-flex items-center gap-1 mt-2 text-sm text-blue-600 dark:text-blue-400 hover:underline"
              >
                {t('platform.view_tenant_legal', { tenant: tenantName })}
                <ArrowRight className="w-3.5 h-3.5" />
              </Link>
            </div>
          </div>
        </GlassCard>
      </motion.div>

      {/* ── Table of Contents ── */}
      {showToc && (
        <motion.div variants={itemVariants}>
          <GlassCard className="p-5 sm:p-6">
            <h2 className="font-semibold text-theme-primary mb-3">{t('platform.contents')}</h2>
            <nav aria-label={t('platform.contents')}>
              <ol className="grid grid-cols-1 sm:grid-cols-2 gap-1.5">
                {sections.map((section, idx) => (
                  <li key={section.id}>
                    <Button
                      variant="light"
                      onPress={() =>
                        document.getElementById(section.id)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
                      }
                      className="text-left text-sm text-theme-muted hover:text-theme-primary transition-colors w-full px-2 py-1.5 rounded-lg hover:bg-slate-500/10 justify-start h-auto"
                    >
                      <span className="font-medium text-theme-subtle mr-2">{idx + 1}.</span>
                      {section.title}
                    </Button>
                  </li>
                ))}
              </ol>
            </nav>
          </GlassCard>
        </motion.div>
      )}

      {/* ── Sections ── */}
      {sections.map((section, idx) => (
        <motion.div key={section.id} variants={itemVariants}>
          <GlassCard className="p-5 sm:p-6" data-section-id={section.id}>
            <h2 className="text-lg font-semibold text-theme-primary mb-3 flex items-center gap-2">
              <span className="inline-flex items-center justify-center w-7 h-7 rounded-full bg-slate-500/10 text-sm font-bold text-slate-500 dark:text-slate-400">
                {idx + 1}
              </span>
              {section.title}
            </h2>
            <div className="text-sm text-theme-muted leading-relaxed space-y-3">
              {section.content}
            </div>
          </GlassCard>
        </motion.div>
      ))}

      {/* ── Cross-links to other platform docs ── */}
      {crossLinks && crossLinks.length > 0 && (
        <motion.div variants={itemVariants}>
          <GlassCard className="p-5 sm:p-6">
            <h2 className="font-semibold text-theme-primary mb-3">{t('platform.related_documents')}</h2>
            <div className="flex flex-wrap gap-2">
              {crossLinks.map((link) => (
                <Link key={link.to} to={tenantPath(link.to)}>
                  <Button
                    size="sm"
                    variant="flat"
                    className="bg-slate-500/10 text-theme-primary"
                    endContent={<ArrowRight className="w-3.5 h-3.5" />}
                  >
                    {link.label}
                  </Button>
                </Link>
              ))}
            </div>
          </GlassCard>
        </motion.div>
      )}

      {/* ── Footer CTA ── */}
      <motion.div variants={itemVariants}>
        <GlassCard className="p-6 sm:p-8">
          <div className="text-center space-y-4">
            <div className="inline-flex p-3 rounded-2xl bg-gradient-to-br from-slate-500/20 to-blue-500/20">
              <Icon className="w-8 h-8 text-slate-500 dark:text-slate-400" aria-hidden="true" />
            </div>
            <h2 className="text-xl font-semibold text-theme-primary">
              {t('platform.cta_title')}
            </h2>
            <p className="text-theme-muted text-sm max-w-lg mx-auto">
              {t('platform.cta_body', { tenant: tenantName })}
            </p>
            <div className="flex justify-center gap-3 flex-wrap">
              <a
                href="https://project-nexus.ie"
                target="_blank"
                rel="noopener noreferrer"
              >
                <Button
                  className="bg-gradient-to-r from-slate-600 to-blue-600 text-white"
                  startContent={<ExternalLink className="w-4 h-4" aria-hidden="true" />}
                >
                  {t('platform.nexus_website')}
                </Button>
              </a>
              <Link to={tenantPath('/contact')}>
                <Button
                  variant="flat"
                  className="bg-theme-elevated text-theme-primary"
                >
                  {t('platform.contact_tenant', { tenant: tenantName })}
                </Button>
              </Link>
            </div>
          </div>
        </GlassCard>
      </motion.div>
    </motion.div>
  );
}

export default PlatformLegalPage;
