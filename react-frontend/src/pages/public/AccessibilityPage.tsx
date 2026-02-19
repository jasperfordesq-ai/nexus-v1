// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Accessibility Statement Page
 *
 * Static page with the platform's accessibility commitment,
 * conformance status, feedback options, and technical specifications.
 */

import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Spinner } from '@heroui/react';
import {
  Accessibility,
  CheckCircle,
  MessageSquare,
  Monitor,
  Eye,
  Keyboard,
  Volume2,
  Globe,
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
    transition: { staggerChildren: 0.1 },
  },
};

const itemVariants = {
  hidden: { opacity: 0, y: 20 },
  visible: { opacity: 1, y: 0 },
};

export function AccessibilityPage() {
  usePageTitle('Accessibility');
  const { branding, tenantPath } = useTenant();
  const { document: customDoc, loading } = useLegalDocument('accessibility');

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
      {/* Hero */}
      <motion.div variants={itemVariants} className="text-center">
        <div className="inline-flex p-4 rounded-2xl bg-gradient-to-br from-indigo-500/20 to-purple-500/20 mb-4">
          <Accessibility className="w-10 h-10 text-indigo-500 dark:text-indigo-400" aria-hidden="true" />
        </div>
        <h1 className="text-3xl sm:text-4xl font-bold text-theme-primary mb-3">
          Accessibility Statement
        </h1>
        <p className="text-theme-muted text-lg max-w-2xl mx-auto">
          {branding.name} is committed to ensuring digital accessibility for people
          of all abilities.
        </p>
      </motion.div>

      {/* Our Commitment */}
      <motion.div variants={itemVariants}>
        <GlassCard className="p-6 sm:p-8">
          <h2 className="text-xl font-semibold text-theme-primary mb-4 flex items-center gap-2">
            <Eye className="w-5 h-5 text-indigo-500" aria-hidden="true" />
            Our Commitment
          </h2>
          <div className="space-y-4 text-theme-muted">
            <p>
              We believe that the internet should be accessible to everyone, regardless of
              ability or disability. {branding.name} strives to ensure that our platform
              meets or exceeds the requirements of the Web Content Accessibility Guidelines
              (WCAG) 2.1 Level AA.
            </p>
            <p>
              We are continually improving the user experience for everyone and applying
              the relevant accessibility standards to ensure we provide equal access to all
              of our users.
            </p>
          </div>

          {/* Accessibility Features */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-6">
            {[
              {
                icon: Keyboard,
                title: 'Keyboard Navigation',
                description: 'Full keyboard support including skip links, focus indicators, and logical tab order',
              },
              {
                icon: Eye,
                title: 'Visual Accessibility',
                description: 'Minimum 4.5:1 contrast ratio, resizable text, and no colour-only information',
              },
              {
                icon: Volume2,
                title: 'Screen Reader Support',
                description: 'Semantic HTML, ARIA labels, and meaningful alt text for all images',
              },
              {
                icon: Monitor,
                title: 'Responsive Design',
                description: 'Content adapts to all screen sizes and supports up to 200% zoom without loss of functionality',
              },
            ].map((feature) => (
              <div
                key={feature.title}
                className="flex items-start gap-3 p-4 rounded-xl bg-theme-elevated"
              >
                <div className="p-2 rounded-lg bg-indigo-500/20 flex-shrink-0">
                  <feature.icon className="w-4 h-4 text-indigo-500 dark:text-indigo-400" aria-hidden="true" />
                </div>
                <div>
                  <p className="font-medium text-theme-primary text-sm">{feature.title}</p>
                  <p className="text-xs text-theme-subtle mt-1">{feature.description}</p>
                </div>
              </div>
            ))}
          </div>
        </GlassCard>
      </motion.div>

      {/* Conformance Status */}
      <motion.div variants={itemVariants}>
        <GlassCard className="p-6 sm:p-8">
          <h2 className="text-xl font-semibold text-theme-primary mb-4 flex items-center gap-2">
            <CheckCircle className="w-5 h-5 text-emerald-500" aria-hidden="true" />
            Conformance Status
          </h2>
          <div className="space-y-4 text-theme-muted">
            <p>
              The Web Content Accessibility Guidelines (WCAG) defines requirements for
              designers and developers to improve accessibility for people with disabilities.
              It defines three levels of conformance: Level A, Level AA, and Level AAA.
            </p>
            <p>
              {branding.name} is <strong className="text-theme-primary">partially conformant</strong> with
              WCAG 2.1 Level AA. Partially conformant means that some parts of the content
              do not fully conform to the accessibility standard.
            </p>

            <div className="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20">
              <p className="text-sm font-medium text-emerald-600 dark:text-emerald-400 mb-2">
                Standards we follow:
              </p>
              <ul className="space-y-2 text-sm text-theme-muted">
                <li className="flex items-center gap-2">
                  <CheckCircle className="w-4 h-4 text-emerald-500 flex-shrink-0" aria-hidden="true" />
                  WCAG 2.1 Level AA
                </li>
                <li className="flex items-center gap-2">
                  <CheckCircle className="w-4 h-4 text-emerald-500 flex-shrink-0" aria-hidden="true" />
                  WAI-ARIA 1.2 for interactive components
                </li>
                <li className="flex items-center gap-2">
                  <CheckCircle className="w-4 h-4 text-emerald-500 flex-shrink-0" aria-hidden="true" />
                  Section 508 compliance
                </li>
                <li className="flex items-center gap-2">
                  <CheckCircle className="w-4 h-4 text-emerald-500 flex-shrink-0" aria-hidden="true" />
                  EN 301 549 (European accessibility standard)
                </li>
              </ul>
            </div>
          </div>
        </GlassCard>
      </motion.div>

      {/* Feedback */}
      <motion.div variants={itemVariants}>
        <GlassCard className="p-6 sm:p-8">
          <h2 className="text-xl font-semibold text-theme-primary mb-4 flex items-center gap-2">
            <MessageSquare className="w-5 h-5 text-amber-500" aria-hidden="true" />
            Feedback
          </h2>
          <div className="space-y-4 text-theme-muted">
            <p>
              We welcome your feedback on the accessibility of {branding.name}. Please
              let us know if you encounter accessibility barriers:
            </p>

            <ul className="space-y-2 text-sm">
              <li className="flex items-start gap-2">
                <span className="text-theme-primary font-medium min-w-[80px]">Email:</span>
                <span>Use our contact form to report issues</span>
              </li>
              <li className="flex items-start gap-2">
                <span className="text-theme-primary font-medium min-w-[80px]">Response:</span>
                <span>We aim to respond to accessibility feedback within 5 business days</span>
              </li>
              <li className="flex items-start gap-2">
                <span className="text-theme-primary font-medium min-w-[80px]">Updates:</span>
                <span>We will notify you of progress towards resolving the issue</span>
              </li>
            </ul>

            <div className="flex flex-wrap gap-3 mt-4">
              <Link to={tenantPath("/contact")}>
                <Button
                  className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                  startContent={<MessageSquare className="w-4 h-4" aria-hidden="true" />}
                >
                  Report an Issue
                </Button>
              </Link>
              <Link to={tenantPath("/help")}>
                <Button
                  variant="flat"
                  className="bg-theme-elevated text-theme-primary"
                >
                  Help Center
                </Button>
              </Link>
            </div>
          </div>
        </GlassCard>
      </motion.div>

      {/* Technical Specifications */}
      <motion.div variants={itemVariants}>
        <GlassCard className="p-6 sm:p-8">
          <h2 className="text-xl font-semibold text-theme-primary mb-4 flex items-center gap-2">
            <Globe className="w-5 h-5 text-blue-500" aria-hidden="true" />
            Technical Specifications
          </h2>
          <div className="space-y-4 text-theme-muted">
            <p>
              Accessibility of {branding.name} relies on the following technologies
              to work with the particular combination of web browser and any assistive
              technologies or plugins installed on your computer:
            </p>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              {[
                'HTML5 with semantic markup',
                'WAI-ARIA for dynamic content',
                'CSS for visual presentation',
                'JavaScript for enhanced interactivity',
                'SVG with appropriate alternatives',
                'React with accessible component library',
              ].map((tech) => (
                <div
                  key={tech}
                  className="flex items-center gap-2 p-3 rounded-lg bg-theme-elevated text-sm"
                >
                  <CheckCircle className="w-4 h-4 text-blue-500 flex-shrink-0" aria-hidden="true" />
                  <span className="text-theme-primary">{tech}</span>
                </div>
              ))}
            </div>

            <div className="p-4 rounded-xl bg-blue-500/10 border border-blue-500/20 mt-4">
              <p className="text-sm text-theme-muted">
                These technologies are relied upon for conformance with the accessibility
                standards used. We recommend using the latest version of your browser with
                up-to-date assistive technology for the best experience.
              </p>
            </div>

            <p className="text-sm text-theme-subtle mt-4">
              This statement was last updated on February 2026.
            </p>
          </div>
        </GlassCard>
      </motion.div>
    </motion.div>
  );
}

export default AccessibilityPage;
