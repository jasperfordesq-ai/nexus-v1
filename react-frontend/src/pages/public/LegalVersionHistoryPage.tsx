/**
 * Legal Document Version History Page
 *
 * Shows all published versions of a legal document (terms, privacy, etc.)
 * with a timeline UI. Clicking a version expands it to show the full content
 * with a "Summary of Changes" callout.
 *
 * Routes:
 *   /terms/versions
 *   /privacy/versions
 *   /cookies/versions
 *   /accessibility/versions
 */

import { useEffect, useState } from 'react';
import { useLocation, Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Chip, Spinner } from '@heroui/react';
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
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
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
};

/** Map type → parent page route for "back" link */
const TYPE_TO_ROUTE: Record<string, string> = {
  terms: '/terms',
  privacy: '/privacy',
  cookies: '/cookies',
  accessibility: '/accessibility',
};

function formatDate(dateStr: string | null): string {
  if (!dateStr) return 'Unknown';
  return new Date(dateStr).toLocaleDateString('en-IE', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
}

export function LegalVersionHistoryPage() {
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

  usePageTitle(title ? `${title} - Version History` : 'Version History');

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
      .catch(() => {})
      .finally(() => setLoading(false));
  }, [docType, tenantLoading, tenant]);

  const handleToggle = async (version: LegalVersionSummary) => {
    if (expandedId === version.id) {
      setExpandedId(null);
      setExpandedContent(null);
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

  if (!docType) {
    return (
      <div className="max-w-4xl mx-auto text-center py-16">
        <p className="text-theme-muted">Document type not found.</p>
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
          Back to {title || 'document'}
        </Link>
      </motion.div>

      {/* Hero Header */}
      <motion.div variants={itemVariants} className="text-center">
        <div className="inline-flex p-4 rounded-2xl bg-gradient-to-br from-slate-500/20 to-blue-500/20 mb-4">
          <History className="w-10 h-10 text-slate-500 dark:text-slate-400" aria-hidden="true" />
        </div>
        <h1 className="text-3xl sm:text-4xl font-bold text-theme-primary mb-3">
          Version History
        </h1>
        <p className="text-theme-muted text-lg max-w-2xl mx-auto">
          {title ? `Previous versions of "${title}"` : 'Previous versions of this document'}
        </p>
      </motion.div>

      {/* No versions */}
      {versions.length === 0 && (
        <motion.div variants={itemVariants}>
          <GlassCard className="p-8 text-center">
            <FileText className="w-12 h-12 text-theme-subtle mx-auto mb-3" aria-hidden="true" />
            <p className="text-theme-muted">No published versions found for this document.</p>
            <Link to={tenantPath(backRoute)} className="mt-4 inline-block">
              <Button size="sm" variant="flat" className="bg-theme-elevated text-theme-primary">
                View Current Document
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

          {versions.map((version, idx) => (
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
                <button
                  onClick={() => handleToggle(version)}
                  className="w-full flex items-center gap-3 p-5 text-left hover:bg-[var(--surface-hover)] transition-colors"
                >
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 flex-wrap mb-1">
                      <span className="font-semibold text-theme-primary text-base">
                        Version {version.version_number}
                      </span>
                      {version.is_current && (
                        <Chip
                          size="sm"
                          variant="flat"
                          startContent={<CheckCircle className="w-3 h-3" />}
                          classNames={{ base: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' }}
                        >
                          Current
                        </Chip>
                      )}
                      {!version.is_current && idx === versions.length - 1 && (
                        <Chip
                          size="sm"
                          variant="flat"
                          startContent={<Clock className="w-3 h-3" />}
                          classNames={{ base: 'bg-[var(--surface-elevated)] text-theme-subtle' }}
                        >
                          Original
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
                        Effective {formatDate(version.effective_date)}
                      </span>
                    </div>
                    {version.summary_of_changes && (
                      <p className="mt-2 text-sm text-theme-muted line-clamp-2">
                        {version.summary_of_changes}
                      </p>
                    )}
                  </div>
                  {expandedId === version.id ? (
                    <ChevronUp className="w-5 h-5 text-theme-subtle flex-shrink-0" />
                  ) : (
                    <ChevronDown className="w-5 h-5 text-theme-subtle flex-shrink-0" />
                  )}
                </button>

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
                        {expandedContent.summary_of_changes && (
                          <div className="flex items-start gap-3 p-4 rounded-xl bg-blue-500/10 border border-blue-500/20">
                            <Info className="w-5 h-5 text-blue-500 mt-0.5 flex-shrink-0" aria-hidden="true" />
                            <div>
                              <h4 className="font-medium text-sm text-blue-600 dark:text-blue-400 mb-1">
                                Summary of Changes
                              </h4>
                              <p className="text-sm text-theme-muted">
                                {expandedContent.summary_of_changes}
                              </p>
                            </div>
                          </div>
                        )}
                        <div
                          className="legal-content"
                          dangerouslySetInnerHTML={{ __html: expandedContent.content }}
                        />
                      </div>
                    )}
                  </div>
                )}
              </GlassCard>
            </motion.div>
          ))}
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
            Back to Current {title}
          </Button>
        </Link>
      </motion.div>
    </motion.div>
  );
}

export default LegalVersionHistoryPage;
