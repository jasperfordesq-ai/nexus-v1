/**
 * Renders a custom legal document fetched from the API.
 * Used by TermsPage, PrivacyPage, AccessibilityPage, CookiesPage
 * when the tenant has a custom document configured.
 *
 * Parses the HTML content to extract section headings for a table of
 * contents, then renders each section inside its own GlassCard with
 * staggered entrance animations — matching the visual quality of the
 * handcrafted default legal pages.
 */

import { useMemo } from 'react';
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Chip } from '@heroui/react';
import {
  FileText,
  CalendarDays,
  Send,
  List,
  ChevronRight,
  History,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { useTenant } from '@/contexts';
import type { LegalDocument } from '@/hooks/useLegalDocument';

// ─────────────────────────────────────────────────────────────────────────────
// Animation variants
// ─────────────────────────────────────────────────────────────────────────────

const containerVariants = {
  hidden: { opacity: 0 },
  visible: { opacity: 1, transition: { staggerChildren: 0.06 } },
};

const itemVariants = {
  hidden: { opacity: 0, y: 20 },
  visible: { opacity: 1, y: 0 },
};

// ─────────────────────────────────────────────────────────────────────────────
// Section parsing
// ─────────────────────────────────────────────────────────────────────────────

interface Section {
  id: string;
  title: string;
  html: string;
}

/**
 * Splits HTML content on <h2> boundaries into individual sections.
 * Each section gets an id derived from the h2's existing id attribute
 * (or slugified from the heading text) and the HTML between that h2
 * and the next one.
 */
function parseSections(html: string): Section[] {
  // Split on <h2 ...>...</h2> while keeping the matched heading
  const parts = html.split(/(<h2[^>]*>[\s\S]*?<\/h2>)/i);
  const sections: Section[] = [];
  let currentTitle = '';
  let currentId = '';
  let currentHtml = '';

  // Check if there's meaningful content before the first h2
  const introHtml = parts[0]?.trim();
  const hasIntro = introHtml && !introHtml.match(/^<h2/i) && introHtml.length > 10;

  for (const part of parts) {
    const h2Match = part.match(/<h2[^>]*?(?:id="([^"]*)")?[^>]*>([\s\S]*?)<\/h2>/i);

    if (h2Match) {
      // Flush previous section
      if (currentTitle) {
        sections.push({ id: currentId, title: currentTitle, html: currentHtml.trim() });
      }
      const rawTitle = h2Match[2].replace(/<[^>]+>/g, '').trim();
      currentTitle = rawTitle;
      currentId = h2Match[1] || slugify(rawTitle);
      currentHtml = '';
    } else {
      currentHtml += part;
    }
  }

  // Flush last section
  if (currentTitle) {
    sections.push({ id: currentId, title: currentTitle, html: currentHtml.trim() });
  }

  // If there was content before the first h2, prepend as an intro section
  if (hasIntro) {
    sections.unshift({ id: 'introduction', title: 'Introduction', html: introHtml });
  }

  return sections;
}

function slugify(text: string): string {
  return text
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

function scrollToSection(id: string) {
  const el = document.getElementById(id);
  if (el) {
    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Accent colour palettes — static classes so Tailwind won't purge them
// ─────────────────────────────────────────────────────────────────────────────

const ACCENT_STYLES = {
  blue: {
    gradientFrom: 'from-blue-500/20',
    gradientTo: 'to-cyan-500/20',
    icon: 'text-blue-500 dark:text-blue-400',
    tocHover: 'hover:bg-blue-500/10',
    chipBg: 'bg-blue-500/10 text-blue-600 dark:text-blue-400',
    btnGradient: 'bg-gradient-to-r from-blue-500 to-cyan-600 text-white',
  },
  indigo: {
    gradientFrom: 'from-indigo-500/20',
    gradientTo: 'to-purple-500/20',
    icon: 'text-indigo-500 dark:text-indigo-400',
    tocHover: 'hover:bg-indigo-500/10',
    chipBg: 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400',
    btnGradient: 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white',
  },
  purple: {
    gradientFrom: 'from-purple-500/20',
    gradientTo: 'to-pink-500/20',
    icon: 'text-purple-500 dark:text-purple-400',
    tocHover: 'hover:bg-purple-500/10',
    chipBg: 'bg-purple-500/10 text-purple-600 dark:text-purple-400',
    btnGradient: 'bg-gradient-to-r from-purple-500 to-pink-600 text-white',
  },
  emerald: {
    gradientFrom: 'from-emerald-500/20',
    gradientTo: 'to-teal-500/20',
    icon: 'text-emerald-500 dark:text-emerald-400',
    tocHover: 'hover:bg-emerald-500/10',
    chipBg: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    btnGradient: 'bg-gradient-to-r from-emerald-500 to-teal-600 text-white',
  },
  amber: {
    gradientFrom: 'from-amber-500/20',
    gradientTo: 'to-orange-500/20',
    icon: 'text-amber-500 dark:text-amber-400',
    tocHover: 'hover:bg-amber-500/10',
    chipBg: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
    btnGradient: 'bg-gradient-to-r from-amber-500 to-orange-600 text-white',
  },
};

/** Map document type → route slug for the versions page */
const TYPE_TO_SLUG: Record<string, string> = {
  terms: 'terms',
  privacy: 'privacy',
  cookies: 'cookies',
  accessibility: 'accessibility',
  community_guidelines: 'community-guidelines',
  acceptable_use: 'acceptable-use',
};

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

interface Props {
  document: LegalDocument;
  accentColor?: keyof typeof ACCENT_STYLES;
}

export function CustomLegalDocument({ document: doc, accentColor = 'blue' }: Props) {
  const { tenantPath } = useTenant();
  const styles = ACCENT_STYLES[accentColor] ?? ACCENT_STYLES.blue;
  const sections = useMemo(() => parseSections(doc.content), [doc.content]);

  // Show TOC only when there are enough sections to justify it
  const showToc = sections.length >= 4;

  return (
    <motion.div
      variants={containerVariants}
      initial="hidden"
      animate="visible"
      className="max-w-4xl mx-auto space-y-6"
    >
      {/* ── Hero Header ── */}
      <motion.div variants={itemVariants} className="text-center">
        <div
          className={`inline-flex p-4 rounded-2xl bg-gradient-to-br ${styles.gradientFrom} ${styles.gradientTo} mb-4`}
        >
          <FileText className={`w-10 h-10 ${styles.icon}`} aria-hidden="true" />
        </div>
        <h1 className="text-3xl sm:text-4xl font-bold text-theme-primary mb-3">
          {doc.title}
        </h1>
        {(doc.effective_date || doc.version_number) && (
          <div className="flex items-center justify-center gap-2 mt-3 text-sm text-theme-subtle">
            {doc.effective_date && (
              <>
                <CalendarDays className="w-4 h-4" aria-hidden="true" />
                <span>
                  Effective:{' '}
                  {new Date(doc.effective_date).toLocaleDateString('en-IE', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                  })}
                </span>
              </>
            )}
            {doc.version_number && (
              <Chip size="sm" variant="flat" color="default" className="ml-2">
                v{doc.version_number}
              </Chip>
            )}
          </div>
        )}
        {doc.summary_of_changes && (
          <p className="mt-3 text-sm text-theme-muted max-w-xl mx-auto italic">
            {doc.summary_of_changes}
          </p>
        )}
      </motion.div>

      {/* ── Table of Contents ── */}
      {showToc && (
        <motion.div variants={itemVariants}>
          <GlassCard className="p-5 sm:p-6">
            <h2 className="text-sm font-semibold text-theme-primary mb-3 flex items-center gap-2 uppercase tracking-wider">
              <List className="w-4 h-4" aria-hidden="true" />
              Contents
            </h2>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-1.5">
              {sections.map((section, idx) => (
                <button
                  key={section.id}
                  onClick={() => scrollToSection(section.id)}
                  className={`flex items-center gap-2.5 px-3 py-2 rounded-lg text-left text-sm text-theme-muted transition-colors ${styles.tocHover} group`}
                >
                  <span
                    className={`inline-flex items-center justify-center w-5 h-5 rounded text-[0.65rem] font-bold ${styles.chipBg} flex-shrink-0`}
                  >
                    {idx + 1}
                  </span>
                  <span className="truncate group-hover:text-theme-primary transition-colors">
                    {section.title}
                  </span>
                  <ChevronRight
                    className="w-3 h-3 ml-auto opacity-0 group-hover:opacity-60 transition-opacity flex-shrink-0"
                    aria-hidden="true"
                  />
                </button>
              ))}
            </div>
          </GlassCard>
        </motion.div>
      )}

      {/* ── Content Sections ── */}
      {sections.map((section, idx) => (
        <motion.div key={section.id} variants={itemVariants} id={section.id}>
          <GlassCard className="p-6 sm:p-8">
            <h2 className="text-xl font-semibold text-theme-primary mb-4 flex items-center gap-2.5 pb-3 border-b border-[var(--border-default)]">
              <Chip
                size="sm"
                variant="flat"
                classNames={{ base: styles.chipBg }}
                className="text-xs font-bold min-w-[1.75rem]"
              >
                {idx + 1}
              </Chip>
              {section.title}
            </h2>
            <div
              className="legal-content"
              dangerouslySetInnerHTML={{ __html: section.html }}
            />
          </GlassCard>
        </motion.div>
      ))}

      {/* ── Version History + Contact CTA ── */}
      <motion.div variants={itemVariants}>
        <GlassCard className="p-6 sm:p-8">
          <div className="text-center space-y-4">
            {doc.has_previous_versions && (
              <div>
                <Link
                  to={tenantPath(`/${TYPE_TO_SLUG[doc.type] ?? doc.type}/versions`)}
                  className="inline-flex items-center gap-2 text-sm text-theme-muted hover:text-theme-primary transition-colors"
                >
                  <History className="w-4 h-4" aria-hidden="true" />
                  View previous versions of this document
                </Link>
              </div>
            )}
            <div>
              <h2 className="text-xl font-semibold text-theme-primary mb-2">
                Have Questions?
              </h2>
              <p className="text-theme-muted text-sm mb-4">
                If you have any questions about this document, please contact us.
              </p>
              <Link to={tenantPath('/contact')}>
                <Button
                  className={styles.btnGradient}
                  startContent={<Send className="w-4 h-4" aria-hidden="true" />}
                >
                  Contact Us
                </Button>
              </Link>
            </div>
          </div>
        </GlassCard>
      </motion.div>
    </motion.div>
  );
}
