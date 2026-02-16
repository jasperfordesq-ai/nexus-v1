/**
 * Renders a custom legal document fetched from the API.
 * Used by TermsPage, PrivacyPage, AccessibilityPage, CookiesPage
 * when the tenant has a custom document configured.
 */

import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Chip } from '@heroui/react';
import { FileText, CalendarDays, Send } from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { useTenant } from '@/contexts';
import type { LegalDocument } from '@/hooks/useLegalDocument';

const containerVariants = {
  hidden: { opacity: 0 },
  visible: { opacity: 1, transition: { staggerChildren: 0.08 } },
};

const itemVariants = {
  hidden: { opacity: 0, y: 20 },
  visible: { opacity: 1, y: 0 },
};

interface Props {
  document: LegalDocument;
  accentColor?: string;
}

export function CustomLegalDocument({ document: doc, accentColor = 'blue' }: Props) {
  const { tenantPath } = useTenant();
  const gradientFrom = `from-${accentColor}-500/20`;
  const gradientTo = `to-cyan-500/20`;

  return (
    <motion.div
      variants={containerVariants}
      initial="hidden"
      animate="visible"
      className="max-w-4xl mx-auto space-y-8"
    >
      <motion.div variants={itemVariants} className="text-center">
        <div className={`inline-flex p-4 rounded-2xl bg-gradient-to-br ${gradientFrom} ${gradientTo} mb-4`}>
          <FileText className={`w-10 h-10 text-${accentColor}-500 dark:text-${accentColor}-400`} aria-hidden="true" />
        </div>
        <h1 className="text-3xl sm:text-4xl font-bold text-theme-primary mb-3">
          {doc.title}
        </h1>
        {doc.effective_date && (
          <div className="flex items-center justify-center gap-2 mt-3 text-sm text-theme-subtle">
            <CalendarDays className="w-4 h-4" aria-hidden="true" />
            <span>
              Effective: {new Date(doc.effective_date).toLocaleDateString('en-IE', {
                year: 'numeric', month: 'long', day: 'numeric'
              })}
            </span>
            {doc.version_number && (
              <Chip size="sm" variant="flat" color="default" className="ml-2">
                v{doc.version_number}
              </Chip>
            )}
          </div>
        )}
      </motion.div>

      <motion.div variants={itemVariants}>
        <GlassCard className="p-6 sm:p-8">
          <div
            className="prose prose-sm sm:prose dark:prose-invert max-w-none legal-content"
            dangerouslySetInnerHTML={{ __html: doc.content }}
          />
        </GlassCard>
      </motion.div>

      <motion.div variants={itemVariants}>
        <GlassCard className="p-6 sm:p-8">
          <div className="text-center">
            <h2 className="text-xl font-semibold text-theme-primary mb-2">
              Have Questions?
            </h2>
            <p className="text-theme-muted text-sm mb-4">
              If you have any questions about this document, please contact us.
            </p>
            <Link to={tenantPath('/contact')}>
              <Button
                className={`bg-gradient-to-r from-${accentColor}-500 to-cyan-600 text-white`}
                startContent={<Send className="w-4 h-4" aria-hidden="true" />}
              >
                Contact Us
              </Button>
            </Link>
          </div>
        </GlassCard>
      </motion.div>
    </motion.div>
  );
}
