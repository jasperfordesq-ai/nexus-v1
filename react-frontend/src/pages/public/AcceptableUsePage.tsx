// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Acceptable Use Policy Page
 *
 * Displays the tenant's custom AUP document if one exists. Otherwise falls
 * back to a generic v1.0 default — the same pattern used by TermsPage /
 * PrivacyPage. Trust & Safety and the Terms link here as if a real policy
 * exists, so we ship one rather than a "being prepared" placeholder.
 */

import { motion } from 'framer-motion';
import { Spinner } from '@heroui/react';
import ShieldCheck from 'lucide-react/icons/shield-check';
import Ban from 'lucide-react/icons/ban';
import UserX from 'lucide-react/icons/user-x';
import Bot from 'lucide-react/icons/bot';
import Bug from 'lucide-react/icons/bug';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import Lock from 'lucide-react/icons/lock';
import Scale from 'lucide-react/icons/scale';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { CustomLegalDocument } from '@/components/legal/CustomLegalDocument';
import { PageMeta } from '@/components/seo';
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
  const { branding } = useTenant();
  const { document: customDoc, loading } = useLegalDocument('acceptable_use');

  if (loading) {
    return (
      <div className="flex justify-center items-center min-h-[50vh]">
        <Spinner size="lg" />
      </div>
    );
  }

  if (customDoc) {
    return (
      <>
        <PageMeta
          title={t('page_meta.acceptable_use.title')}
          description={t('page_meta.acceptable_use.description')}
        />
        <CustomLegalDocument document={customDoc} />
      </>
    );
  }

  const platformName = branding?.name || 'the platform';

  return (
    <>
      <PageMeta
        title={t('page_meta.acceptable_use.title')}
        description={t('page_meta.acceptable_use.description')}
      />
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
            Acceptable Use Policy
          </h1>
          <p className="text-theme-muted text-base sm:text-lg max-w-2xl mx-auto">
            The specific behaviours that are not allowed on {platformName}. This sits alongside
            the Terms of Service and the Community Guidelines and applies to every account.
          </p>
          <p className="text-xs text-theme-subtle mt-3">Version 1.0 · Last updated 2026-05-13</p>
        </motion.div>

        <motion.div variants={itemVariants}>
          <GlassCard className="p-6 sm:p-8 space-y-6 legal-content">
            <Section title="1. Scope">
              <p>
                This policy applies to anyone using {platformName} — members, organisations,
                visitors, API users, and integrators — and to all activity on the platform,
                including profiles, listings, messages, events, posts, polls, jobs, files, and
                outbound communications sent through the platform.
              </p>
            </Section>

            <Section
              icon={<Scale className="w-5 h-5" aria-hidden="true" />}
              title="2. No illegal activity"
            >
              <p>You must not use the platform to:</p>
              <ul>
                <li>Carry out, plan, or facilitate anything illegal in Ireland or in the
                  jurisdiction where you or the other person is located.</li>
                <li>Offer or solicit services that require professional licensing or regulation
                  you don't hold (e.g. unlicensed medical, legal, financial, electrical,
                  gas, or childcare work).</li>
                <li>Trade in regulated or restricted goods (alcohol, tobacco, prescription
                  medicines, firearms, controlled drugs, etc.).</li>
                <li>Launder money or evade tax obligations.</li>
              </ul>
            </Section>

            <Section
              icon={<UserX className="w-5 h-5" aria-hidden="true" />}
              title="3. No fraud or impersonation"
            >
              <ul>
                <li>Don't impersonate another person, organisation, or official body.</li>
                <li>Don't create fake accounts, sock-puppets, or duplicate accounts to manipulate
                  ratings, reviews, polls, or matching.</li>
                <li>Don't misrepresent your identity, credentials, qualifications, or vetting
                  status.</li>
                <li>Don't manipulate time-credit balances through fake exchanges, collusion, or
                  staged transactions.</li>
              </ul>
            </Section>

            <Section
              icon={<AlertTriangle className="w-5 h-5" aria-hidden="true" />}
              title="4. No harm to people"
            >
              <ul>
                <li>No harassment, bullying, stalking, doxxing, or coordinated targeting.</li>
                <li>No hate speech, slurs, or content that demeans people on the basis of
                  protected characteristics.</li>
                <li>No threats, incitement, or glorification of violence.</li>
                <li>No content that sexualises children, in any form.</li>
                <li>No non-consensual sexual content, intimate-image abuse, or content posted to
                  shame or extort.</li>
                <li>No promotion of self-harm or suicide. If you or someone you know is in
                  crisis, contact emergency services (112/999 in Ireland) or a recognised
                  helpline.</li>
              </ul>
            </Section>

            <Section
              icon={<Ban className="w-5 h-5" aria-hidden="true" />}
              title="5. No prohibited content"
            >
              <ul>
                <li>Sexually explicit content, gratuitous violence, or shock content.</li>
                <li>Content that infringes someone else's copyright, trademark, or other
                  intellectual property.</li>
                <li>Confidential information you're not entitled to share (employer secrets,
                  protected health information, etc.).</li>
                <li>Personal data about others posted without their consent.</li>
              </ul>
            </Section>

            <Section
              icon={<Bot className="w-5 h-5" aria-hidden="true" />}
              title="6. No spam, abuse, or platform misuse"
            >
              <ul>
                <li>No unsolicited bulk messaging, repeated identical posts, or promotional
                  blasts.</li>
                <li>No scraping, mass-downloading, or automated harvesting of profiles, listings,
                  or other data, except via documented APIs and within their rate limits.</li>
                <li>No artificial inflation of engagement (fake likes, reviews, endorsements,
                  matches, badges, or leaderboard positions).</li>
                <li>No referral, MLM, pyramid, or "get-paid-to-click" schemes.</li>
                <li>No using the platform primarily to drive traffic to an external site or
                  service unrelated to community exchange.</li>
              </ul>
            </Section>

            <Section
              icon={<Bug className="w-5 h-5" aria-hidden="true" />}
              title="7. No interference with the platform"
            >
              <ul>
                <li>No probing, scanning, or testing for vulnerabilities without prior written
                  permission. Coordinated disclosure is welcomed via the Contact page.</li>
                <li>No malware, viruses, worms, or other malicious code.</li>
                <li>No attempts to bypass authentication, rate limits, paywalls, feature gates,
                  or moderation tools.</li>
                <li>No denial-of-service attempts or activity that materially degrades service
                  for others.</li>
                <li>No reverse engineering, decompiling, or extracting source code, except where
                  the AGPL-3.0 licence expressly permits it.</li>
              </ul>
            </Section>

            <Section
              icon={<Lock className="w-5 h-5" aria-hidden="true" />}
              title="8. Account hygiene"
            >
              <ul>
                <li>Don't share your account credentials. You're responsible for activity under
                  your account.</li>
                <li>Notify us promptly if you believe your account has been compromised.</li>
                <li>Don't transfer or sell your account to anyone else.</li>
                <li>Use the platform's tools to delete or export your data — don't try to
                  extract it through automation.</li>
              </ul>
            </Section>

            <Section title="9. Enforcement">
              <p>
                Breaches may result in content removal, warnings, temporary suspension, or
                permanent removal of access — proportionate to severity and history. Serious
                breaches may be reported to An Garda Síochána, the Data Protection Commission,
                or other competent authorities, and we may be legally obliged to do so.
              </p>
              <p>
                You can appeal an enforcement decision via the Contact page. Appeals are
                reviewed by someone not involved in the original decision.
              </p>
            </Section>

            <Section title="10. Reporting violations">
              <p>
                If you spot something that breaches this policy, use the in-product report tool
                on the relevant profile, listing, message, post, or event, or email the team via
                the Contact page. For safeguarding concerns about a child or vulnerable adult,
                follow the dedicated route on the Trust &amp; Safety page.
              </p>
            </Section>

            <Section title="11. Changes">
              <p>
                We may update this policy as the platform and the regulatory environment evolve.
                Material changes will be announced and recorded in the legal version history.
              </p>
            </Section>
          </GlassCard>
        </motion.div>
      </motion.div>
    </>
  );
}

interface SectionProps {
  title: string;
  icon?: React.ReactNode;
  children: React.ReactNode;
}

function Section({ title, icon, children }: SectionProps) {
  return (
    <section className="space-y-2">
      <h2 className="text-xl sm:text-2xl font-semibold text-theme-primary flex items-center gap-2">
        {icon ? <span className="text-emerald-500 dark:text-emerald-400">{icon}</span> : null}
        {title}
      </h2>
      <div className="text-theme-muted text-sm sm:text-base leading-relaxed space-y-2 [&_ul]:list-disc [&_ul]:ms-6 [&_ul]:space-y-1 [&_strong]:text-theme-primary">
        {children}
      </div>
    </section>
  );
}

export default AcceptableUsePage;
