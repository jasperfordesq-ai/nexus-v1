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

const legalDocuments = [
  {
    title: 'Privacy Policy',
    description:
      'How we collect, use, and protect your personal information. Includes your GDPR rights and data retention policies.',
    icon: Shield,
    path: '/privacy',
    color: 'text-indigo-500',
    bg: 'bg-indigo-500/20',
    gradient: 'from-indigo-500/20 to-purple-500/20',
    updated: 'February 2026',
  },
  {
    title: 'Terms of Service',
    description:
      'The rules and guidelines governing your use of the platform, including member responsibilities and community standards.',
    icon: FileText,
    path: '/terms',
    color: 'text-blue-500',
    bg: 'bg-blue-500/20',
    gradient: 'from-blue-500/20 to-cyan-500/20',
    updated: 'February 2026',
  },
  {
    title: 'Cookie Policy',
    description:
      'Details about the cookies we use, why we use them, and how you can manage your cookie preferences.',
    icon: Cookie,
    path: '/cookies',
    color: 'text-amber-500',
    bg: 'bg-amber-500/20',
    gradient: 'from-amber-500/20 to-orange-500/20',
    updated: 'February 2026',
  },
  {
    title: 'Accessibility Statement',
    description:
      'Our commitment to making the platform accessible to everyone, including WCAG 2.1 AA compliance details.',
    icon: Accessibility,
    path: '/accessibility',
    color: 'text-emerald-500',
    bg: 'bg-emerald-500/20',
    gradient: 'from-emerald-500/20 to-green-500/20',
    updated: 'February 2026',
  },
];

export function LegalHubPage() {
  usePageTitle('Legal');
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
          Legal &amp; Compliance
        </h1>
        <p className="text-theme-muted text-lg max-w-2xl mx-auto">
          Transparency and trust are at the heart of everything we do.
          Here you will find all of our legal documents and policies.
        </p>
      </motion.div>

      {/* GDPR Commitment Banner */}
      <motion.div variants={itemVariants}>
        <GlassCard className="p-6 sm:p-8">
          <div className="p-4 rounded-xl bg-indigo-500/10 border border-indigo-500/20">
            <h2 className="text-xl font-semibold text-theme-primary mb-3 flex items-center gap-2">
              <Handshake className="w-5 h-5 text-indigo-500" aria-hidden="true" />
              Our Commitment to Transparency
            </h2>
            <div className="space-y-3 text-theme-muted">
              <p>
                {branding.name} is fully committed to GDPR compliance and data protection
                best practices. We believe you have the right to understand exactly how your
                data is collected, processed, and stored.
              </p>
              <p>
                Every policy below has been written in{' '}
                <strong className="text-theme-primary">plain language</strong> so that it is
                genuinely useful, not just legally required. If anything is unclear, our team
                is always happy to help.
              </p>
            </div>
          </div>
        </GlassCard>
      </motion.div>

      {/* Legal Document Cards */}
      <motion.div variants={itemVariants}>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          {legalDocuments.map((doc) => (
            <GlassCard key={doc.title} hoverable className="p-6 flex flex-col">
              <div className="flex items-start gap-4 mb-4">
                <div className={`p-3 rounded-xl bg-gradient-to-br ${doc.gradient} flex-shrink-0`}>
                  <doc.icon className={`w-6 h-6 ${doc.color}`} aria-hidden="true" />
                </div>
                <div className="flex-1 min-w-0">
                  <h3 className="text-lg font-semibold text-theme-primary">{doc.title}</h3>
                  <div className="flex items-center gap-1.5 mt-1 text-xs text-theme-subtle">
                    <CalendarDays className="w-3.5 h-3.5" aria-hidden="true" />
                    <span>Updated {doc.updated}</span>
                  </div>
                </div>
              </div>
              <p className="text-sm text-theme-muted flex-1 mb-4">
                {doc.description}
              </p>
              <Link to={tenantPath(doc.path)}>
                <Button
                  variant="flat"
                  className="w-full bg-theme-elevated text-theme-primary"
                  endContent={<ArrowRight className="w-4 h-4" aria-hidden="true" />}
                >
                  Read Document
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
              Questions About Our Policies?
            </h2>
            <p className="text-theme-muted text-sm mb-6 max-w-lg mx-auto">
              If you have any questions about our legal documents, data practices, or want
              to exercise your GDPR rights, please get in touch.
            </p>
            <Divider className="my-4" />
            <div className="flex flex-wrap justify-center gap-3 mt-4">
              <Link to={tenantPath('/contact')}>
                <Button
                  className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                  startContent={<Send className="w-4 h-4" aria-hidden="true" />}
                >
                  Contact Our Team
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

export default LegalHubPage;
