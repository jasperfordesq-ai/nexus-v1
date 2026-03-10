// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Legal Document Version History Page
 *
 * Shows all published versions of a legal document (terms, privacy, etc.)
 * with a timeline UI. Clicking a version expands it to show the full content
 * with a "Summary of Changes" callout. Each non-original version has a
 * "What changed" button that loads a unified diff from the previous version.
 *
 * Routes:
 *   /terms/versions
 *   /privacy/versions
 *   /cookies/versions
 *   /accessibility/versions
 *   /community-guidelines/versions
 *   /acceptable-use/versions
 */

import { useEffect, useState, useCallback } from 'react';
import { useLocation, Link } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import { Button, Chip, Spinner } from '@heroui/react';
import DOMPurify from 'dompurify';
import {
  History,
  CalendarDays,
  ChevronDown,
  ChevronUp,
  FileText,
  ArrowLeft,
  CheckCircle,
  Clock,
  Info,
  GitCompareArrows,
  X,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import type {
  LegalDocumentType,
  LegalVersionSummary,
  LegalVersionDetail,
} from '@/hooks/useLegalDocument';

const containerVariants = {
  hidden: { opacity: 0 },
  visible: { opacity: 1, transition: { staggerChildren: 0.08 } },
};

const itemVariants = {
  hidden: { opacity: 0, y: 20 },
  visible: { opacity: 1, y: 0 },
};

/** Map route slug → API document type */
const SLUG_TO_TYPE: Record<string, LegalDocumentType> = {
  terms: 'terms',
  privacy: 'privacy',
  cookies: 'cookies',
  accessibility: 'accessibility',
  'community-guidelines': 'community_guidelines',
  'acceptable-use': 'acceptable_use',
};

/** Map type → parent page route for "back" link */
const TYPE_TO_ROUTE: Record<string, string> = {
  terms: '/terms',
  privacy: '/privacy',
  cookies: '/cookies',
  accessibility: '/accessibility',
  community_guidelines: '/community-guidelines',
  acceptable_use: '/acceptable-use',
};

interface VersionComparison {
  version1: { version_number: string; effective_date: string };
  version2: { version_number: string; effective_date: string };
  diff_html: string;
  changes_count: number;
}

function formatDate(dateStr: string | null): string {
  if (!dateStr) return 'Unknown';
  return new Date(dateStr).toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
}

export function LegalVersionHistoryPage() {
  const { t } = useTranslation('legal');
  const location = useLocation();
  const { tenantPath, isLoading: tenantLoading, tenant } = useTenant();

  // Extract document slug from URL path: /terms/versions → "terms"
  const pathSegments = location.pathname.split('/').filter(Boolean);
  const versionsIdx = pathSegments.indexOf('versions');
  const slug = versionsIdx > 0 ? pathSegments[versionsIdx - 1] : '';
  const docType = SLUG_TO_TYPE[slug];

  const [title, setTitle] = useState('');
  const [versions, setVersions] = useState<LegalVersionSummary[]>([]);
  const [loading, setLoading] = useState(true);
  const [expandedId, setExpandedId] = useState<number | null>(null);
  const [expandedContent, setExpandedContent] = useState<LegalVersionDetail | null>(null);
  const [loadingContent, setLoadingContent] = useState(false);

  // Diff state
  const [diffForVersionId, setDiffForVersionId] = useState<number | null>(null);
  const [diffData, setDiffData] = useState<VersionComparison | null>(null);
  const [loadingDiff, setLoadingDiff] = useState(false);

  usePageTitle(title ? `${title} - ${t('version_history.page_title')}` : t('version_history.page_title'));

  useEffect(() => {
    if (!docType) return;
    // Wait for tenant context so X-Tenant-ID header is available
    if (tenantLoading || !tenant) return;

    api.get<{ title: string; type: string; versions: LegalVersionSummary[] }>(
      `/v2/legal/${docType}/versions`
    )
      .then((res) => {
        if (res.success && res.data) {
          setTitle(res.data.title);
          setVersions(res.data.versions ?? []);
        }
      })
      .catch((err: unknown) => {
        logError('LegalVersionHistoryPage: failed to load version list', err);
      })
      .finally(() => setLoading(false));
  }, [docType, tenantLoading, tenant]);

  const handleToggle = async (version: LegalVersionSummary) => {
    if (expandedId === version.id) {
      setExpandedId(null);
      setExpandedContent(null);
      // Also close diff if it belongs to this version
      if (diffForVersionId === version.id) {
        setDiffForVersionId(null);
        setDiffData(null);
      }
      return;
    }

    setExpandedId(version.id);
    setExpandedContent(null);
    setLoadingContent(true);

    try {
      const res = await api.get<LegalVersionDetail>(`/v2/legal/version/${version.id}`);
      if (res.success && res.data) {
        setExpandedContent(res.data);
      }
    } catch {
      // Silently handle
    } finally {
      setLoadingContent(false);
    }
  };

  /**
   * Load a diff between the given version and its predecessor.
   * Versions are sorted newest-first, so the "previous" version is at idx + 1.
   */
  const handleViewDiff = useCallback(async (version: LegalVersionSummary, previousVersion: LegalVersionSummary) => {
    // Toggle off if already showing
    if (diffForVersionId === version.id) {
      setDiffForVersionId(null);
      setDiffData(null);
      return;
    }

    setDiffForVersionId(version.id);
    setDiffData(null);
    setLoadingDiff(true);

    try {
      const res = await api.get<VersionComparison>(
        `/v2/legal/versions/compare?v1=${previousVersion.id}&v2=${version.id}`
      );
      if (res.success && res.data) {
        setDiffData(res.data);
      }
    } catch {
      // Silently handle
    } finally {
      setLoadingDiff(false);
    }
  }, [diffForVersionId]);

  if (!docType) {
    return (
      <div className="max-w-4xl mx-auto text-center py-16">
        <p className="text-theme-muted">{t('version_history.not_found')}</p>
      </div>
    );
  }

  if (loading) {
    return (
      <div className="flex justify-center items-center min-h-[50vh]">
        <Spinner size="lg" />
      </div>
    );
  }

  const backRoute = TYPE_TO_ROUTE[docType] ?? '/terms';

  return (
    <motion.div
      variants={containerVariants}
      initial="hidden"
      animate="visible"
      className="max-w-4xl mx-auto space-y-6"
    >
      {/* Back link */}
      <motion.div variants={itemVariants}>
        <Link
          to={tenantPath(backRoute)}
          className="inline-flex items-center gap-1.5 text-sm text-theme-muted hover:text-theme-primary transition-colors"
        >
          <ArrowLeft className="w-4 h-4" />
          {t('version_history.back_to', { title: title || t('version_history.document') })}
        </Link>
      </motion.div>

      {/* Hero Header */}
      <motion.div variants={itemVariants} className="text-center">
        <div className="inline-flex p-4 rounded-2xl bg-gradient-to-br from-slate-500/20 to-blue-500/20 mb-4">
          <History className="w-10 h-10 text-slate-500 dark:text-slate-400" aria-hidden="true" />
        </div>
        <h1 className="text-3xl sm:text-4xl font-bold text-theme-primary mb-3">
          {t('version_history.heading')}
        </h1>
        <p className="text-theme-muted text-lg max-w-2xl mx-auto">
          {title ? t('version_history.subtitle_with_title', { title }) : t('version_history.subtitle_generic')}
        </p>
      </motion.div>

      {/* No versions */}
      {versions.length === 0 && (
        <motion.div variants={itemVariants}>
          <GlassCard className="p-8 text-center">
            <FileText className="w-12 h-12 text-theme-subtle mx-auto mb-3" aria-hidden="true" />
            <p className="text-theme-muted">{t('version_history.no_versions')}</p>
            <Link to={tenantPath(backRoute)} className="mt-4 inline-block">
              <Button size="sm" variant="flat" className="bg-theme-elevated text-theme-primary">
                {t('version_history.view_current')}
              </Button>
            </Link>
          </GlassCard>
        </motion.div>
      )}

      {/* Timeline */}
      {versions.length > 0 && (
        <div className="relative">
          {/* Vertical timeline line */}
          <div className="absolute left-6 top-0 bottom-0 w-px bg-[var(--border-default)] hidden sm:block" />

          {versions.map((version, idx) => {
            const isLastVersion = idx === versions.length - 1;
            const previousVersion = !isLastVersion ? versions[idx + 1] : null;
            const isDiffOpen = diffForVersionId === version.id;

            return (
              <motion.div
                key={version.id}
                variants={itemVariants}
                className="relative sm:pl-16 mb-4"
              >
                {/* Timeline dot */}
                <div className="absolute left-4 top-6 hidden sm:flex items-center justify-center w-5 h-5 rounded-full border-2 border-[var(--border-default)] bg-[var(--surface-base)] z-10">
                  <div
                    className={`w-2.5 h-2.5 rounded-full ${
                      version.is_current ? 'bg-emerald-500' : 'bg-[var(--text-subtle)]'
                    }`}
                  />
                </div>

                <GlassCard className="overflow-hidden">
                  {/* Version header (clickable) */}
                  <Button
                    variant="light"
                    onPress={() => handleToggle(version)}
                    className="w-full flex items-center gap-3 p-5 text-left hover:bg-[var(--surface-hover)] transition-colors h-auto min-w-0 justify-start rounded-none"
                  >
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center gap-2 flex-wrap mb-1">
                        <span className="font-semibold text-theme-primary text-base">
                          {t('version_history.version_number', { number: version.version_number })}
                        </span>
                        {version.is_current && (
                          <Chip
                            size="sm"
                            variant="flat"
                            startContent={<CheckCircle className="w-3 h-3" />}
                            classNames={{ base: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' }}
                          >
                            {t('version_history.current')}
                          </Chip>
                        )}
                        {!version.is_current && isLastVersion && (
                          <Chip
                            size="sm"
                            variant="flat"
                            startContent={<Clock className="w-3 h-3" />}
                            classNames={{ base: 'bg-[var(--surface-elevated)] text-theme-subtle' }}
                          >
                            {t('version_history.original')}
                          </Chip>
                        )}
                        {version.version_label && (
                          <span className="text-sm text-theme-subtle italic">
                            {version.version_label}
                          </span>
                        )}
                      </div>
                      <div className="flex items-center gap-3 text-sm text-theme-muted">
                        <span className="inline-flex items-center gap-1">
                          <CalendarDays className="w-3.5 h-3.5" aria-hidden="true" />
                          {t('version_history.effective', { date: formatDate(version.effective_date) })}
                        </span>
                      </div>
                      {version.summary_of_changes && (
                        <p className="mt-2 text-sm text-theme-muted line-clamp-2">
                          {version.summary_of_changes}
                        </p>
                      )}
                    </div>
                    {expandedId === version.id ? (
                      <ChevronUp className="w-5 h-5 text-theme-subtle shrink-0" />
                    ) : (
                      <ChevronDown className="w-5 h-5 text-theme-subtle shrink-0" />
                    )}
                  </Button>

                  {/* Expanded content */}
                  {expandedId === version.id && (
                    <div className="border-t border-[var(--border-default)]">
                      {loadingContent && (
                        <div className="flex justify-center py-8">
                          <Spinner size="md" />
                        </div>
                      )}
                      {expandedContent && (
                        <div className="p-5 sm:p-6 space-y-4">
                          {/* Summary of changes callout */}
                          {expandedContent.summary_of_changes && (
                            <div className="flex items-start gap-3 p-4 rounded-xl bg-blue-500/10 border border-blue-500/20">
                              <Info className="w-5 h-5 text-blue-500 mt-0.5 shrink-0" aria-hidden="true" />
                              <div>
                                <h4 className="font-medium text-sm text-blue-600 dark:text-blue-400 mb-1">
                                  {t('version_history.summary_of_changes')}
                                </h4>
                                <p className="text-sm text-theme-muted">
                                  {expandedContent.summary_of_changes}
                                </p>
                              </div>
                            </div>
                          )}

                          {/* "What changed" diff button — only for non-original versions */}
                          {previousVersion && (
                            <div>
                              <Button
                                size="sm"
                                variant={isDiffOpen ? 'flat' : 'bordered'}
                                className={
                                  isDiffOpen
                                    ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20'
                                    : 'text-theme-muted border-[var(--border-default)]'
                                }
                                startContent={isDiffOpen ? <X className="w-3.5 h-3.5" /> : <GitCompareArrows className="w-3.5 h-3.5" />}
                                onPress={() => handleViewDiff(version, previousVersion)}
                              >
                                {isDiffOpen
                                  ? t('version_history.hide_changes', 'Hide changes')
                                  : t('version_history.view_changes', {
                                      defaultValue: 'What changed from v{{prev}}',
                                      prev: previousVersion.version_number,
                                    })}
                              </Button>
                            </div>
                          )}

                          {/* Diff panel */}
                          <AnimatePresence>
                            {isDiffOpen && (
                              <motion.div
                                initial={{ opacity: 0, height: 0 }}
                                animate={{ opacity: 1, height: 'auto' }}
                                exit={{ opacity: 0, height: 0 }}
                                transition={{ duration: 0.2 }}
                                className="overflow-hidden"
                              >
                                {loadingDiff && (
                                  <div className="flex justify-center py-6">
                                    <Spinner size="md" />
                                  </div>
                                )}
                                {diffData && (
                                  <div className="rounded-xl border border-amber-500/20 overflow-hidden">
                                    {/* Diff header */}
                                    <div className="flex items-center justify-between gap-3 px-4 py-3 bg-amber-500/5 border-b border-amber-500/20">
                                      <div className="flex items-center gap-2 text-sm">
                                        <GitCompareArrows className="w-4 h-4 text-amber-500" aria-hidden="true" />
                                        <span className="font-medium text-theme-primary">
                                          {t('version_history.changes_between', {
                                            defaultValue: 'Changes: v{{old}} → v{{new}}',
                                            old: diffData.version1.version_number,
                                            new: diffData.version2.version_number,
                                          })}
                                        </span>
                                        <Chip size="sm" variant="flat" classNames={{ base: 'bg-amber-500/10 text-amber-600 dark:text-amber-400' }}>
                                          {t('version_history.changes_count', {
                                            defaultValue: '{{count}} changes',
                                            count: diffData.changes_count,
                                          })}
                                        </Chip>
                                      </div>
                                    </div>

                                    {/* Legend */}
                                    <div className="flex items-center gap-4 px-4 py-2 bg-[var(--surface-elevated)] border-b border-[var(--border-default)] text-xs text-theme-muted">
                                      <span className="inline-flex items-center gap-1">
                                        <span className="inline-block w-3 h-3 rounded-sm bg-red-500/20 border border-red-500/30" />
                                        {t('version_history.legend_removed', 'Removed')}
                                      </span>
                                      <span className="inline-flex items-center gap-1">
                                        <span className="inline-block w-3 h-3 rounded-sm bg-emerald-500/20 border border-emerald-500/30" />
                                        {t('version_history.legend_added', 'Added')}
                                      </span>
                                    </div>

                                    {/* Diff content */}
                                    <div
                                      className="version-diff-content max-h-[500px] overflow-y-auto p-4 text-sm font-mono leading-relaxed"
                                      dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(diffData.diff_html) }}
                                    />
                                  </div>
                                )}
                              </motion.div>
                            )}
                          </AnimatePresence>

                          {/* Full document content */}
                          <div
                            className="legal-content"
                            dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(expandedContent.content) }}
                          />
                        </div>
                      )}
                    </div>
                  )}
                </GlassCard>
              </motion.div>
            );
          })}
        </div>
      )}

      {/* Back CTA */}
      <motion.div variants={itemVariants} className="text-center pt-2">
        <Link to={tenantPath(backRoute)}>
          <Button
            variant="flat"
            className="bg-theme-elevated text-theme-primary"
            startContent={<ArrowLeft className="w-4 h-4" />}
          >
            {t('version_history.back_to_current', { title })}
          </Button>
        </Link>
      </motion.div>
    </motion.div>
  );
}

export default LegalVersionHistoryPage;
