// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Community Guidelines Page
 *
 * Displays the tenant's custom community guidelines document if one exists.
 * Otherwise falls back to a generic v1.0 default — the same pattern used by
 * TermsPage / PrivacyPage. Trust & Safety links to this page as if it
 * exists, so a real policy is shipped here rather than a "being prepared"
 * placeholder.
 */

import { motion } from 'framer-motion';
import { Spinner } from '@heroui/react';
import Users from 'lucide-react/icons/users';
import Heart from 'lucide-react/icons/heart';
import Shield from 'lucide-react/icons/shield';
import MessageCircle from 'lucide-react/icons/message-circle';
import EyeOff from 'lucide-react/icons/eye-off';
import Flag from 'lucide-react/icons/flag';
import Gavel from 'lucide-react/icons/gavel';
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

export function CommunityGuidelinesPage() {
  const { t } = useTranslation('legal');
  usePageTitle(t('community_guidelines.page_title', 'Community Guidelines'));
  const { branding } = useTenant();
  const { document: customDoc, loading } = useLegalDocument('community_guidelines');

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
          title={t('page_meta.community_guidelines.title')}
          description={t('page_meta.community_guidelines.description')}
        />
        <CustomLegalDocument document={customDoc} />
      </>
    );
  }

  const communityName = branding?.name || 'this community';

  return (
    <>
      <PageMeta
        title={t('page_meta.community_guidelines.title')}
        description={t('page_meta.community_guidelines.description')}
      />
      <motion.div
        variants={containerVariants}
        initial="hidden"
        animate="visible"
        className="max-w-4xl mx-auto space-y-6"
      >
        <motion.div variants={itemVariants} className="text-center">
          <div className="inline-flex p-4 rounded-2xl bg-gradient-to-br from-blue-500/20 to-purple-500/20 mb-4">
            <Users className="w-10 h-10 text-[var(--color-info)]" aria-hidden="true" />
          </div>
          <h1 className="text-3xl sm:text-4xl font-bold text-theme-primary mb-3">
            Community Guidelines
          </h1>
          <p className="text-theme-muted text-base sm:text-lg max-w-2xl mx-auto">
            How we expect everyone in {communityName} to treat each other. Short, plain-English,
            and applies to every exchange, message, post, and event.
          </p>
          <p className="text-xs text-theme-subtle mt-3">Version 1.0 · Last updated 2026-05-13</p>
        </motion.div>

        <motion.div variants={itemVariants}>
          <GlassCard className="p-6 sm:p-8 space-y-6 legal-content">
            <Section
              icon={<Heart className="w-5 h-5" aria-hidden="true" />}
              title="1. Be kind and respectful"
            >
              <p>
                Treat other members the way you would treat a neighbour. Disagreement is fine —
                contempt, name-calling, mockery, and personal attacks are not.
              </p>
              <ul>
                <li>No harassment, bullying, stalking, or intimidation.</li>
                <li>No hate speech, slurs, or content that demeans people because of who they are
                  — including race, ethnicity, nationality, religion, gender, sexuality,
                  disability, age, or background.</li>
                <li>No threats of violence, real or implied.</li>
              </ul>
            </Section>

            <Section
              icon={<Shield className="w-5 h-5" aria-hidden="true" />}
              title="2. Be honest"
            >
              <p>
                Trust is what makes a timebank work. Misrepresenting yourself or what you're
                offering damages the whole community.
              </p>
              <ul>
                <li>Use your real name and a real photo on your profile.</li>
                <li>Describe skills, listings, and requests accurately. Don't exaggerate what
                  you can do or how long something takes.</li>
                <li>Log hours honestly. Don't claim time you didn't give.</li>
                <li>One person, one account.</li>
              </ul>
            </Section>

            <Section
              icon={<MessageCircle className="w-5 h-5" aria-hidden="true" />}
              title="3. Keep it relevant"
            >
              <p>
                This is a space for community exchange, not a marketplace, billboard, or
                political platform.
              </p>
              <ul>
                <li>No spam, repeated promotion, or off-topic blasts.</li>
                <li>No commercial selling, recruiting, or fundraising outside the features
                  designed for it (e.g. jobs, organisations, marketplace where enabled).</li>
                <li>No campaign material, partisan organising, or content designed to inflame
                  political conflict. Civic engagement is welcome — partisan combat is not.</li>
              </ul>
            </Section>

            <Section
              icon={<EyeOff className="w-5 h-5" aria-hidden="true" />}
              title="4. Protect privacy"
            >
              <p>
                What members share inside the community stays inside the community.
              </p>
              <ul>
                <li>Don't share another member's personal information (address, phone, photos,
                  health details, family situation) without their clear permission.</li>
                <li>Don't post screenshots of private messages.</li>
                <li>Don't take photos at community events without asking the people in them.</li>
                <li>Use the platform's messaging for exchange-related communication; don't push
                  people onto outside channels before they're comfortable.</li>
              </ul>
            </Section>

            <Section
              icon={<Users className="w-5 h-5" aria-hidden="true" />}
              title="5. Look after each other in person"
            >
              <p>
                Exchanges often involve meeting in person, sometimes in someone's home. Use
                ordinary common sense.
              </p>
              <ul>
                <li>Read the member's profile and reviews before a first exchange.</li>
                <li>Meet in a public place for the first exchange when you can.</li>
                <li>Tell someone you trust where you're going and when you expect to be back.</li>
                <li>Stop the exchange and leave if something feels wrong. You don't owe anyone
                  an explanation in the moment.</li>
                <li>If someone is unsafe — themselves or with others — see Trust &amp; Safety
                  for how to report. In an emergency, contact emergency services first (112/999
                  in Ireland).</li>
              </ul>
            </Section>

            <Section
              icon={<Flag className="w-5 h-5" aria-hidden="true" />}
              title="6. Report concerns"
            >
              <p>
                If you see something that breaks these guidelines — or that worries you — tell
                us. You can:
              </p>
              <ul>
                <li>Use the report button on any profile, listing, message, post, or event.</li>
                <li>Email the community team via the Contact page.</li>
                <li>For safeguarding concerns (a child or vulnerable adult at risk), see the
                  Trust &amp; Safety page for the dedicated reporting route.</li>
              </ul>
              <p>
                Reports are reviewed by moderators and treated confidentially. We don't share
                your identity with the person you reported unless you've asked us to or the law
                requires it.
              </p>
            </Section>

            <Section
              icon={<Gavel className="w-5 h-5" aria-hidden="true" />}
              title="7. What happens when guidelines are broken"
            >
              <p>
                Most issues are small misunderstandings and a quiet word fixes them. For
                clearer breaches we use proportionate steps:
              </p>
              <ul>
                <li><strong>A reminder</strong> — for first-time, minor issues.</li>
                <li><strong>Removal of content</strong> — a post, listing, or message taken
                  down.</li>
                <li><strong>A warning</strong> — recorded on your account.</li>
                <li><strong>Temporary suspension</strong> — for repeated or serious issues.</li>
                <li><strong>Permanent removal</strong> — for harassment, safeguarding breaches,
                  fraud, threats, hate speech, or repeated suspensions.</li>
              </ul>
              <p>
                Serious matters — abuse, threats, fraud, exploitation — may be reported to An
                Garda Síochána or the appropriate authority regardless of the platform action.
              </p>
              <p>
                If you think a decision was wrong, you can appeal via the Contact page. We'll
                review it with a moderator who wasn't involved in the original decision.
              </p>
            </Section>

            <Section title="Changes to these guidelines">
              <p>
                We may update these guidelines as the community grows. Material changes will be
                announced in the platform and recorded in the legal version history.
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
        {icon ? <span className="text-[var(--color-info)]">{icon}</span> : null}
        {title}
      </h2>
      <div className="text-theme-muted text-sm sm:text-base leading-relaxed space-y-2 [&_ul]:list-disc [&_ul]:ms-6 [&_ul]:space-y-1 [&_strong]:text-theme-primary">
        {children}
      </div>
    </section>
  );
}

export default CommunityGuidelinesPage;
