/**
 * Terms of Service Page
 *
 * Fetches custom tenant-specific terms from the API (managed via admin
 * Legal Documents). Falls back to a generic default if no custom document
 * exists for this tenant.
 */

import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Chip, Divider, Spinner } from '@heroui/react';
import {
  FileText,
  Clock,
  Users,
  Ban,
  Shield,
  Handshake,
  UserCog,
  AlertTriangle,
  MapPin,
  Scale,
  RefreshCw,
  Gem,
  MessageSquare,
  Send,
  CalendarDays,
  Info,
  CircleSlash,
} from 'lucide-react';
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

export function TermsPage() {
  usePageTitle('Terms of Service');
  const { branding, tenantPath } = useTenant();
  const { document: customDoc, loading } = useLegalDocument('terms');

  if (loading) {
    return (
      <div className="flex justify-center items-center min-h-[50vh]">
        <Spinner size="lg" />
      </div>
    );
  }

  if (customDoc) {
    return <CustomLegalDocument document={customDoc} accentColor="blue" />;
  }

  // Default fallback — generic terms content
  return <DefaultTermsContent branding={branding} tenantPath={tenantPath} />;
}

// ─────────────────────────────────────────────────────────────────────────────
// Default Terms Content (shown when no custom document exists)
// ─────────────────────────────────────────────────────────────────────────────

const quickNavItems = [
  { id: 'time-credits', label: 'Time Credits', icon: Clock },
  { id: 'community', label: 'Community Rules', icon: Users },
  { id: 'prohibited', label: 'Prohibited', icon: Ban },
  { id: 'liability', label: 'Liability', icon: Shield },
];

const prohibitedItems = [
  'Harassment or discrimination',
  'Fraudulent exchanges',
  'Illegal services or activities',
  'Spam or solicitation',
  'Impersonation',
  'Sharing others\' private information',
];

function scrollToSection(id: string) {
  const el = document.getElementById(id);
  if (el) {
    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}

function DefaultTermsContent({ branding, tenantPath }: { branding: { name: string }; tenantPath: (path: string) => string }) {
  return (
    <motion.div
      variants={containerVariants}
      initial="hidden"
      animate="visible"
      className="max-w-4xl mx-auto space-y-8"
    >
      {/* Hero Header */}
      <motion.div variants={itemVariants} className="text-center">
        <div className="inline-flex p-4 rounded-2xl bg-gradient-to-br from-blue-500/20 to-cyan-500/20 mb-4">
          <FileText className="w-10 h-10 text-blue-500 dark:text-blue-400" aria-hidden="true" />
        </div>
        <h1 className="text-3xl sm:text-4xl font-bold text-theme-primary mb-3">
          Terms of Service
        </h1>
        <p className="text-theme-muted text-lg max-w-2xl mx-auto">
          The rules and guidelines for using our platform
        </p>
        <div className="flex items-center justify-center gap-2 mt-3 text-sm text-theme-subtle">
          <CalendarDays className="w-4 h-4" aria-hidden="true" />
          <span>Last updated: February 2026</span>
        </div>
      </motion.div>

      {/* Quick Navigation */}
      <motion.div variants={itemVariants}>
        <div className="flex flex-wrap justify-center gap-3">
          {quickNavItems.map((item) => (
            <button
              key={item.id}
              onClick={() => scrollToSection(item.id)}
              className="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-theme-elevated hover:bg-blue-500/10 text-theme-primary text-sm font-medium transition-colors"
            >
              <item.icon className="w-4 h-4 text-blue-500" aria-hidden="true" />
              {item.label}
            </button>
          ))}
        </div>
      </motion.div>

      {/* Introduction */}
      <motion.div variants={itemVariants}>
        <GlassCard className="p-6 sm:p-8">
          <div className="p-4 rounded-xl bg-blue-500/10 border border-blue-500/20">
            <h2 className="text-xl font-semibold text-theme-primary mb-3 flex items-center gap-2">
              <Handshake className="w-5 h-5 text-blue-500" aria-hidden="true" />
              Welcome to {branding.name}
            </h2>
            <div className="space-y-3 text-theme-muted">
              <p>
                By accessing or using our platform, you agree to be bound by these Terms of
                Service. Please read them carefully before participating in our community.
              </p>
              <p>
                These terms establish a framework for{' '}
                <strong className="text-theme-primary">fair, respectful, and meaningful exchanges</strong>{' '}
                between community members. Our goal is to create a trusted environment where
                everyone's time is valued equally.
              </p>
            </div>
          </div>
        </GlassCard>
      </motion.div>

      {/* 1. Time Credit System */}
      <motion.div variants={itemVariants} id="time-credits">
        <GlassCard className="p-6 sm:p-8">
          <h2 className="text-xl font-semibold text-theme-primary mb-4 flex items-center gap-2">
            <Chip size="sm" variant="flat" color="primary" className="text-xs font-bold">1</Chip>
            <Clock className="w-5 h-5 text-blue-500" aria-hidden="true" />
            Time Credit System
          </h2>
          <p className="text-theme-muted mb-4">
            Our platform operates on a simple but powerful principle:{' '}
            <strong className="text-theme-primary">everyone's time is equal</strong>.
          </p>

          {/* Visual equality display */}
          <div className="flex flex-wrap items-center justify-center gap-4 my-6">
            <div className="flex items-center gap-3 p-4 rounded-xl bg-theme-elevated">
              <div className="p-2 rounded-lg bg-blue-500/20">
                <Clock className="w-6 h-6 text-blue-500" aria-hidden="true" />
              </div>
              <span className="font-medium text-theme-primary">1 Hour of Service</span>
            </div>
            <span className="text-2xl font-bold text-blue-500">=</span>
            <div className="flex items-center gap-3 p-4 rounded-xl bg-theme-elevated">
              <div className="p-2 rounded-lg bg-blue-500/20">
                <Gem className="w-6 h-6 text-blue-500" aria-hidden="true" />
              </div>
              <span className="font-medium text-theme-primary">1 Time Credit</span>
            </div>
          </div>

          {/* Important notice */}
          <div className="p-4 rounded-xl bg-amber-500/10 border border-amber-500/20 mb-4 flex items-start gap-3">
            <Info className="w-5 h-5 text-amber-500 mt-0.5 flex-shrink-0" aria-hidden="true" />
            <div>
              <h4 className="font-medium text-amber-600 dark:text-amber-400 text-sm mb-1">Important</h4>
              <p className="text-sm text-theme-muted">
                Time Credits have no monetary value and cannot be exchanged for cash.
                They exist solely to facilitate community exchanges.
              </p>
            </div>
          </div>

          <ul className="space-y-2 text-theme-muted">
            {[
              'One hour of service provided equals one Time Credit earned',
              'Credits can be used to receive services from other members',
              'The type of service does not affect the credit value',
              'Credits are tracked automatically through the platform',
            ].map((item) => (
              <li key={item} className="flex items-start gap-3">
                <div className="mt-1.5 w-1.5 h-1.5 rounded-full bg-blue-500 flex-shrink-0" />
                <span>{item}</span>
              </li>
            ))}
          </ul>
        </GlassCard>
      </motion.div>

      {/* 2. Account Responsibilities */}
      <motion.div variants={itemVariants}>
        <GlassCard className="p-6 sm:p-8">
          <h2 className="text-xl font-semibold text-theme-primary mb-4 flex items-center gap-2">
            <Chip size="sm" variant="flat" color="primary" className="text-xs font-bold">2</Chip>
            <UserCog className="w-5 h-5 text-blue-500" aria-hidden="true" />
            Account Responsibilities
          </h2>
          <p className="text-theme-muted mb-4">
            When you create an account, you agree to:
          </p>
          <ul className="space-y-3 text-theme-muted">
            {[
              { label: 'Provide accurate information', desc: 'Your profile must reflect your true identity and skills' },
              { label: 'Maintain security', desc: 'Keep your login credentials confidential and secure' },
              { label: 'Use one account', desc: 'Each person may only maintain one active account' },
              { label: 'Stay current', desc: 'Update your profile when your skills or availability change' },
              { label: 'Be reachable', desc: 'Respond to messages and requests in a timely manner' },
            ].map((item) => (
              <li key={item.label} className="flex items-start gap-3">
                <div className="mt-1 w-1.5 h-1.5 rounded-full bg-blue-500 flex-shrink-0" />
                <span>
                  <strong className="text-theme-primary">{item.label}:</strong> {item.desc}
                </span>
              </li>
            ))}
          </ul>
        </GlassCard>
      </motion.div>

      {/* 3. Community Guidelines */}
      <motion.div variants={itemVariants} id="community">
        <GlassCard className="p-6 sm:p-8">
          <div className="p-4 rounded-xl bg-blue-500/10 border border-blue-500/20 mb-4">
            <h2 className="text-xl font-semibold text-theme-primary mb-2 flex items-center gap-2">
              <Chip size="sm" variant="flat" color="primary" className="text-xs font-bold">3</Chip>
              <Users className="w-5 h-5 text-blue-500" aria-hidden="true" />
              Community Guidelines
            </h2>
            <p className="text-theme-muted text-sm">
              Our community is built on{' '}
              <strong className="text-theme-primary">trust, respect, and mutual support</strong>.
              All members must:
            </p>
          </div>

          <ol className="space-y-3 text-theme-muted">
            {[
              { label: 'Treat everyone with respect', desc: 'Be kind and courteous in all interactions' },
              { label: 'Honour your commitments', desc: 'If you agree to an exchange, follow through' },
              { label: 'Communicate clearly', desc: 'Keep other members informed about your availability' },
              { label: 'Be inclusive', desc: 'Welcome members of all backgrounds and abilities' },
              { label: 'Give honest feedback', desc: 'Help the community by providing fair reviews' },
            ].map((item, index) => (
              <li key={item.label} className="flex items-start gap-3">
                <div className="flex-shrink-0 w-6 h-6 rounded-full bg-blue-500/20 flex items-center justify-center">
                  <span className="text-xs font-bold text-blue-500">{index + 1}</span>
                </div>
                <span>
                  <strong className="text-theme-primary">{item.label}</strong> — {item.desc}
                </span>
              </li>
            ))}
          </ol>
        </GlassCard>
      </motion.div>

      {/* 4. Prohibited Activities */}
      <motion.div variants={itemVariants} id="prohibited">
        <GlassCard className="p-6 sm:p-8">
          <h2 className="text-xl font-semibold text-theme-primary mb-4 flex items-center gap-2">
            <Chip size="sm" variant="flat" color="primary" className="text-xs font-bold">4</Chip>
            <Ban className="w-5 h-5 text-red-500" aria-hidden="true" />
            Prohibited Activities
          </h2>
          <p className="text-theme-muted mb-4">
            The following activities are strictly prohibited and may result in account termination:
          </p>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            {prohibitedItems.map((item) => (
              <div
                key={item}
                className="flex items-center gap-3 p-3 rounded-xl bg-red-500/5 border border-red-500/10"
              >
                <CircleSlash className="w-4 h-4 text-red-500 flex-shrink-0" aria-hidden="true" />
                <span className="text-sm text-theme-muted">{item}</span>
              </div>
            ))}
          </div>
        </GlassCard>
      </motion.div>

      {/* 5. Safety & Meetings */}
      <motion.div variants={itemVariants}>
        <GlassCard className="p-6 sm:p-8">
          <h2 className="text-xl font-semibold text-theme-primary mb-4 flex items-center gap-2">
            <Chip size="sm" variant="flat" color="primary" className="text-xs font-bold">5</Chip>
            <MapPin className="w-5 h-5 text-blue-500" aria-hidden="true" />
            Safety &amp; Meetings
          </h2>
          <p className="text-theme-muted mb-4">
            Your safety is important. We recommend following these guidelines:
          </p>
          <ul className="space-y-3 text-theme-muted">
            {[
              { label: 'First meetings', desc: 'Meet in public places for initial exchanges' },
              { label: 'Verify identity', desc: 'Confirm the member\'s profile before meeting' },
              { label: 'Trust your instincts', desc: 'If something feels wrong, do not proceed' },
              { label: 'Report concerns', desc: 'Let us know about any suspicious behaviour' },
              { label: 'Keep records', desc: 'Document exchanges through the platform' },
            ].map((item) => (
              <li key={item.label} className="flex items-start gap-3">
                <div className="mt-1 w-1.5 h-1.5 rounded-full bg-blue-500 flex-shrink-0" />
                <span>
                  <strong className="text-theme-primary">{item.label}:</strong> {item.desc}
                </span>
              </li>
            ))}
          </ul>
        </GlassCard>
      </motion.div>

      {/* 6. Limitation of Liability */}
      <motion.div variants={itemVariants} id="liability">
        <GlassCard className="p-6 sm:p-8">
          <h2 className="text-xl font-semibold text-theme-primary mb-4 flex items-center gap-2">
            <Chip size="sm" variant="flat" color="primary" className="text-xs font-bold">6</Chip>
            <Scale className="w-5 h-5 text-blue-500" aria-hidden="true" />
            Limitation of Liability
          </h2>
          <p className="text-theme-muted mb-4">
            {branding.name} provides a platform for community members to connect and exchange
            services. However:
          </p>
          <ul className="space-y-3 text-theme-muted">
            {[
              'We do not guarantee the quality or safety of any services exchanged',
              'We are not responsible for disputes between members',
              'Members exchange services at their own risk',
              'We recommend obtaining appropriate insurance for professional services',
            ].map((item) => (
              <li key={item} className="flex items-start gap-3">
                <div className="mt-1 w-1.5 h-1.5 rounded-full bg-blue-500 flex-shrink-0" />
                <span>{item}</span>
              </li>
            ))}
          </ul>

          <div className="mt-4 p-4 rounded-xl bg-blue-500/10 border border-blue-500/20">
            <p className="text-sm text-theme-muted">
              By using the platform, you agree to hold {branding.name} harmless from any
              claims arising from your participation in service exchanges.
            </p>
          </div>
        </GlassCard>
      </motion.div>

      {/* 7. Account Termination */}
      <motion.div variants={itemVariants}>
        <GlassCard className="p-6 sm:p-8">
          <h2 className="text-xl font-semibold text-theme-primary mb-4 flex items-center gap-2">
            <Chip size="sm" variant="flat" color="primary" className="text-xs font-bold">7</Chip>
            <AlertTriangle className="w-5 h-5 text-amber-500" aria-hidden="true" />
            Account Termination
          </h2>
          <p className="text-theme-muted mb-4">
            We reserve the right to suspend or terminate accounts that violate these terms.
            Reasons for termination include:
          </p>
          <ul className="space-y-2 text-theme-muted">
            {[
              'Repeated violation of community guidelines',
              'Fraudulent or deceptive behaviour',
              'Harassment of other members',
              'Extended inactivity (over 12 months)',
              'Providing false information',
            ].map((item) => (
              <li key={item} className="flex items-start gap-3">
                <div className="mt-1 w-1.5 h-1.5 rounded-full bg-amber-500 flex-shrink-0" />
                <span>{item}</span>
              </li>
            ))}
          </ul>
          <p className="text-sm text-theme-muted mt-4">
            You may also close your account at any time through your account settings.
          </p>
        </GlassCard>
      </motion.div>

      {/* 8. Changes to These Terms */}
      <motion.div variants={itemVariants}>
        <GlassCard className="p-6 sm:p-8">
          <h2 className="text-xl font-semibold text-theme-primary mb-4 flex items-center gap-2">
            <Chip size="sm" variant="flat" color="primary" className="text-xs font-bold">8</Chip>
            <RefreshCw className="w-5 h-5 text-blue-500" aria-hidden="true" />
            Changes to These Terms
          </h2>
          <p className="text-theme-muted mb-4">
            We may update these terms from time to time to reflect changes in our practices
            or for legal reasons. When we make significant changes:
          </p>
          <ul className="space-y-2 text-theme-muted">
            {[
              'We will notify you via email or platform notification',
              'The updated date will be shown at the top of this page',
              'Continued use of the platform constitutes acceptance of the new terms',
            ].map((item) => (
              <li key={item} className="flex items-start gap-3">
                <div className="mt-1 w-1.5 h-1.5 rounded-full bg-blue-500 flex-shrink-0" />
                <span>{item}</span>
              </li>
            ))}
          </ul>
        </GlassCard>
      </motion.div>

      {/* Contact CTA */}
      <motion.div variants={itemVariants}>
        <GlassCard className="p-6 sm:p-8">
          <div className="text-center">
            <div className="inline-flex p-3 rounded-2xl bg-gradient-to-br from-blue-500/20 to-cyan-500/20 mb-4">
              <MessageSquare className="w-8 h-8 text-blue-500 dark:text-blue-400" aria-hidden="true" />
            </div>
            <h2 className="text-xl font-semibold text-theme-primary mb-2">
              Have Questions?
            </h2>
            <p className="text-theme-muted text-sm mb-6 max-w-lg mx-auto">
              If you have any questions about these Terms of Service or need clarification
              on any points, our team is here to help.
            </p>
            <Divider className="my-4" />
            <div className="flex flex-wrap justify-center gap-3 mt-4">
              <Link to={tenantPath('/contact')}>
                <Button
                  className="bg-gradient-to-r from-blue-500 to-cyan-600 text-white"
                  startContent={<Send className="w-4 h-4" aria-hidden="true" />}
                >
                  Contact Us
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

export default TermsPage;
