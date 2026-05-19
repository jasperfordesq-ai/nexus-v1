// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Features Page
 *
 * Public marketing page documenting every module shipped in Project NEXUS
 * v1.5 (GA). Each module is honestly labelled with its maturity:
 *
 *   - (unmarked)  General Availability — stable, supported, used in production
 *   - Beta        Working in production, surface still hardening
 *   - Preview     Recently shipped, available to opt in, may change
 *
 * The page replaces the previous "Development Status" page; the old route
 * still redirects here so existing bookmarks survive.
 */

import { type ReactNode } from 'react';
import { Card, CardBody, CardHeader, Divider, Chip } from '@heroui/react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router-dom';
import CheckCircle from 'lucide-react/icons/circle-check-big';
import Sparkles from 'lucide-react/icons/sparkles';
import Globe from 'lucide-react/icons/globe';
import Shield from 'lucide-react/icons/shield';
import Github from 'lucide-react/icons/github';
import Bug from 'lucide-react/icons/bug';
import ExternalLink from 'lucide-react/icons/external-link';
import { PageMeta } from '@/components/seo';
import { usePageTitle } from '@/hooks/usePageTitle';
import { useTenant } from '@/contexts';
import { RELEASE_STATUS } from '@/config/releaseStatus';

// ---------------------------------------------------------------------------
// Maturity chip
// ---------------------------------------------------------------------------

type Maturity = 'ga' | 'beta' | 'preview';

function MaturityChip({ level }: { level: Maturity }) {
  const { t } = useTranslation('public');
  if (level === 'ga') return null;
  const config: Record<Exclude<Maturity, 'ga'>, { color: 'warning' | 'secondary'; label: string }> = {
    beta: { color: 'warning', label: t('features_page.chips.beta') },
    preview: { color: 'secondary', label: t('features_page.chips.preview') },
  };
  const { color, label } = config[level];
  return (
    <Chip color={color} variant="flat" size="sm" className="ms-2 align-middle">
      {label}
    </Chip>
  );
}

// ---------------------------------------------------------------------------
// Feature item
// ---------------------------------------------------------------------------

interface FeatureItem {
  title: string;
  description: string;
  maturity?: Maturity;
  note?: string;
}

function FeatureList({ items }: { items: FeatureItem[] }) {
  return (
    <ul className="space-y-3 list-none">
      {items.map((item, idx) => (
        <li key={idx} className="flex items-start gap-2">
          <CheckCircle className="w-4 h-4 text-success shrink-0 mt-1" aria-hidden="true" />
          <div className="text-sm">
            <span className="font-semibold text-foreground">{item.title}</span>
            <MaturityChip level={item.maturity ?? 'ga'} />
            <span className="text-foreground-600"> - {item.description}</span>
            {item.note && (
              <p className="text-xs text-foreground-500 mt-1 italic">{item.note}</p>
            )}
          </div>
        </li>
      ))}
    </ul>
  );
}

interface FeatureGroup {
  title: string;
  intro?: string;
  items: FeatureItem[];
}

function FeatureSection({
  group,
  icon,
}: {
  group: FeatureGroup;
  icon?: ReactNode;
}) {
  return (
    <Card>
      <CardHeader className="flex gap-2 items-center">
        {icon}
        <h2 className="text-lg font-semibold">{group.title}</h2>
      </CardHeader>
      <Divider />
      <CardBody className="space-y-3">
        {group.intro && <p className="text-sm text-foreground-600">{group.intro}</p>}
        <FeatureList items={group.items} />
      </CardBody>
    </Card>
  );
}

// ---------------------------------------------------------------------------
// Feature inventory
// ---------------------------------------------------------------------------

const GROUPS: FeatureGroup[] = [
  {
    title: 'Core Platform',
    items: [
      { title: 'Timebanking Engine', description: 'Full credit exchange system with wallet, transactions, and broker controls.' },
      { title: 'Multi-Tenancy', description: 'Host unlimited communities on a single platform, each with their own branding, configuration, and feature set.' },
      { title: 'Tenant Hierarchy', description: 'Parent–child tenant relationships with feature toggling and federation scoping.' },
      { title: 'Smart Matching', description: 'AI-powered matching with semantic embeddings, collaborative filtering, availability scheduling, and learned preferences.' },
      { title: 'Real-Time Messaging', description: 'Private conversations with Pusher WebSocket integration and real-time presence.' },
      { title: 'Progressive Web App', description: 'Install as a PWA on any device — offline shell, push notifications, NetworkFirst update flow.' },
      { title: 'Native Mobile App', description: 'iOS and Android builds from the same React codebase via Capacitor.', maturity: 'beta', note: 'Web is the primary distribution channel; native builds are tested but not yet under continuous release.' },
    ],
  },
  {
    title: 'Federation',
    intro: 'A four-protocol federation layer for cross-community exchange — Nexus (native), Komunitin (15 endpoints), Credit Commons Protocol (11 endpoints, CEN-compatible), and TimeOverflow.',
    items: [
      { title: 'Federation Network', description: 'Connect multiple NEXUS communities into a network for cross-community discovery, listings, events, and messaging.' },
      { title: 'External Partner Federation', description: 'Live with external timebanking platforms — partnerships established, messages flowing, credit exchange under active testing.', maturity: 'beta', note: 'Real partnerships exist and exchange data daily. The wire protocols are still being hardened against edge cases — treat external credit transfers as supervised, not automated.' },
      { title: 'Multi-Protocol Adapters', description: 'Two-way sync across 9 entity types: members, listings, events, groups, connections, volunteering, reviews, transfers, messages.', maturity: 'beta' },
      { title: 'Federation Neighborhoods', description: 'Geographically grouped clusters of federated communities for regional coordination.', maturity: 'beta' },
      { title: 'Credit Agreements', description: 'Negotiated exchange-rate terms between federated communities.', maturity: 'beta' },
      { title: 'Federation Analytics', description: 'Live admin dashboards for partner activity, sync health, and federated transaction volume.' },
    ],
  },
  {
    title: 'Member Experience',
    items: [
      { title: 'Service Listings', description: 'Post offers and requests, browse and smart-match listings.' },
      { title: 'Marketplace', description: 'Standalone classifieds module with Stripe Connect payouts, orders, seller profiles, and AI-powered reply suggestions.', maturity: 'beta' },
      { title: 'Donations', description: 'One-off and recurring donations via Stripe with full dashboards and receipts for organisations.' },
      { title: 'Identity Verification', description: 'Optional Stripe Identity flow (document + selfie + name/DOB matching) with a verified-member badge.', maturity: 'beta' },
      { title: 'Exchange Workflow', description: 'Structured service-exchange lifecycle with broker approval and dispute handling.' },
      { title: 'Group Exchanges', description: 'Bulk community service exchanges across multiple members.' },
      { title: 'Social Feed', description: 'Posts, comments, likes, polls, hashtags, voice messages, media attachments, link previews, and @mentions.' },
      { title: 'Stories', description: 'Ephemeral 24-hour photo and video stories with reactions, polls, and highlights.', maturity: 'beta' },
      { title: 'Presence System', description: 'Real-time online/offline status with privacy controls and custom status messages.' },
      { title: 'Events & Groups', description: 'Community gatherings, interest-based groups, and event reminders.' },
      { title: 'Connections', description: 'Follow and connect with other community members.' },
      { title: 'Members Directory', description: 'Browse, filter, and discover people in your community.' },
      { title: 'Gamification', description: 'Verification badges, journeys, XP, leaderboards, achievements, challenges, streaks, XP shop rewards, community dashboard, and seasonal competitions.' },
      { title: 'Goals & Impact', description: 'Track personal goals and community impact with mentoring and deliverables tracking.' },
      { title: 'Ideation Challenges', description: 'Innovation hub with campaigns, ideas, voting, and outcomes tracking.' },
      { title: 'Volunteering', description: 'Volunteer opportunities, hours tracking, check-ins, expenses, certificates, wellbeing monitoring, emergency alerts, and a volunteer-organisation wallet that pays out time credits on approved hours.' },
      { title: 'Job Vacancies', description: 'Full recruitment module with alerts, analytics, and a public RSS/JSON job feed for aggregators.' },
      { title: 'Organisations', description: 'Company and employer profiles with sub-accounts and a dedicated organisation wallet.' },
      { title: 'Sub-Accounts / Family Accounts', description: 'Parent–child account relationships for household and family management.' },
      { title: 'Reviews & Ratings', description: 'Build trust through structured member feedback.' },
      { title: 'Endorsements', description: 'Peer skill and experience endorsements.' },
      { title: 'Polls', description: 'Community voting and surveys with multiple question types.' },
      { title: 'Skills Browse', description: 'Explore the skill taxonomy and discover expertise.' },
      { title: 'Availability Scheduling', description: 'Timezone-aware time-slot scheduling for smart matching and bookings.' },
    ],
  },
  {
    title: 'Content & Communication',
    items: [
      { title: 'Blog', description: 'Tenant-managed content management and community news.' },
      { title: 'Resources & Knowledge Base', description: 'Structured articles and a shared resource library.' },
      { title: 'Help Center', description: 'Documentation hub and FAQ.' },
      { title: 'Custom Pages', description: 'Tenant-managed CMS pages for community content.' },
      { title: 'Newsletter System', description: 'Email campaign manager with A/B testing, smart segments, geo targeting, recurring sends, templates, send-time optimisation, and full open/click analytics.' },
      { title: 'AI Chat', description: 'OpenAI-powered assistant for platform guidance.', maturity: 'beta' },
      { title: 'Legal Hub', description: 'Versioned legal documents with acceptance gates and audit trail.' },
      { title: 'Impact Reports', description: 'SROI analysis, member outcome reports, and social impact case studies.' },
      { title: 'Social Prescribing', description: 'Information and tooling for community health integration workflows.', maturity: 'preview' },
    ],
  },
  {
    title: 'Trust, Reputation & Safety',
    items: [
      { title: 'Member Verification Badges', description: 'Verified status indicators on member profiles.' },
      { title: 'NexusScore', description: 'Proprietary reputation and trustworthiness scoring.' },
      { title: 'Streaks', description: 'Consecutive activity tracking to reward consistent engagement.' },
      { title: 'Personal Insights Dashboard', description: 'Individual engagement metrics, hours given/received, skills breakdown, monthly charts, and personalised recommendations.' },
      { title: 'Safeguarding Module', description: 'Flagged content review workflow, incident reporting, safeguarding assignment tracking, and a community safety dashboard.' },
      { title: 'CRM', description: 'Admin contact management with notes, tasks, tags, activity timelines, and export.' },
    ],
  },
  {
    title: 'AI & Recommendation Engine',
    items: [
      { title: 'Semantic Search', description: 'Meilisearch-powered full-text search across listings, members, events, and groups with typo tolerance, synonyms, and per-tenant isolation.' },
      { title: 'Collaborative Filtering', description: 'Item-based recommendations from real community interaction data.' },
      { title: 'Semantic Embeddings', description: 'OpenAI-powered content matching for listings, members, and requests.' },
      { title: 'EdgeRank Feed', description: 'Time-decay, affinity, and engagement-weighted feed ranking.' },
      { title: 'MatchRank & CommunityRank', description: 'Bayesian quality scoring with Wilson confidence intervals.' },
      { title: 'Group Recommendations', description: 'Trending and affinity-based group discovery.' },
      { title: 'Match Learning', description: 'Feedback loop that improves recommendations from user interactions.' },
      { title: 'Algorithm Health Dashboard', description: 'Live admin monitoring and tuning of all ranking systems.' },
    ],
  },
  {
    title: 'Caring Community Layer',
    intro: 'A pilot-readiness governance layer for civic and caring-community deployments — most modules shipped April–May 2026.',
    items: [
      { title: 'Civic Digest', description: 'Periodic summary of community activity and outcomes for stakeholders.', maturity: 'preview' },
      { title: 'Success Stories', description: 'Curated case studies of member-led outcomes.', maturity: 'preview' },
      { title: 'Feedback Inbox', description: 'Centralised pilot-feedback capture with admin triage.', maturity: 'preview' },
      { title: 'Integration Showcase', description: 'Public surface for connected partners and integrations.', maturity: 'preview' },
      { title: 'Lead Nurture', description: 'Workflow tooling for pilot-stakeholder follow-up.', maturity: 'preview' },
      { title: 'Copilot', description: 'In-context AI helper for community administrators.', maturity: 'preview' },
    ],
  },
  {
    title: 'Built for Production',
    items: [
      { title: 'Enterprise Security', description: 'CSRF, rate limiting, TOTP 2FA, WebAuthn passkeys, CSP nonces, a CORS allowlist, Form Request validation, email verification gates, and invite-code registration.' },
      { title: 'Stripe Payments Layer', description: 'Subscriptions, donations, marketplace (Connect), and identity verification, with idempotent webhook handling and deep money-flow test coverage.' },
      { title: 'GDPR Compliance Suite', description: 'Data requests, consent management, cookie consent, breach tracking, and a full audit log.' },
      { title: 'Fraud & Abuse Detection', description: 'Automated suspicious-activity alerts and content moderation.' },
      { title: 'Insurance Certificate Tracking', description: 'Volunteer insurance management and verification.' },
      { title: 'Enterprise RBAC', description: 'Role-based access control across 13+ modules with a full permission matrix.' },
      { title: 'WCAG 2.1 AA Accessibility', description: 'Audited accessibility compliance across the user-facing surface.' },
      { title: 'Multi-Language Support', description: '11 languages: English, Irish, German, French, Italian, Portuguese, Spanish, Dutch, Polish, Japanese, and Arabic (full RTL).' },
      { title: 'Self-Hosted Prerendering', description: 'Bot-only Playwright snapshots served to SEO crawlers; users always get the live SPA.' },
      { title: 'Guided Onboarding', description: 'Wizard for new members with smart defaults.' },
      { title: 'Admin Panel', description: 'Algorithm controls, diagnostics, cron-job monitoring, and email-deliverability monitoring.' },
      { title: 'Email Webhook Processing', description: 'SendGrid bounce, complaint, and delivery event handling.' },
      { title: '500+ PHPUnit Tests', description: 'Money flow, webhooks, federation, groups, marketplace — plus Vitest frontend suites.' },
      { title: 'OpenAPI 3.0 Specification', description: 'Full API spec with Swagger UI docs.' },
      { title: 'Fully Dockerized', description: 'Production and development run from the same Docker Compose foundations.' },
    ],
  },
];

// ---------------------------------------------------------------------------
// Page
// ---------------------------------------------------------------------------

export function FeaturesPage() {
  const { t } = useTranslation('public');
  const { tenantPath } = useTenant();
  usePageTitle(t('features_page.title', { defaultValue: 'Features' }));

  return (
    <div className="max-w-4xl mx-auto space-y-6 py-4 px-4 sm:px-0">
      <PageMeta
        title={t('features_page.meta_title', { defaultValue: 'Features — Project NEXUS v1.5' })}
        description={t('features_page.meta_description', {
          defaultValue:
            'Every module shipped in Project NEXUS v1.5 (Generally Available). Honest maturity labels per module — including federation, which is live with external partners while protocols continue to harden.',
        })}
      />

      {/* Hero */}
      <div className="flex flex-col gap-3">
        <div className="flex items-center gap-3 flex-wrap">
          <Sparkles className="w-7 h-7 text-primary shrink-0" aria-hidden="true" />
          <h1 className="text-2xl sm:text-3xl font-bold text-foreground">
            {t('features_page.heading', { defaultValue: 'What Project NEXUS does' })}
          </h1>
          <Chip color="success" variant="flat" size="sm">
            {RELEASE_STATUS.stageLabel}
          </Chip>
        </div>
        <p className="text-sm sm:text-base text-foreground-600">
          {t('features_page.subheading', {
            defaultValue:
              'Project NEXUS is an enterprise-grade, multi-tenant community platform. Every module below ships in v1.5 today. We label modules honestly: unmarked items are Generally Available; newer or actively-hardening surfaces carry a Beta or Preview chip.',
          })}
        </p>
      </div>

      {/* Maturity key */}
      <Card>
        <CardBody className="text-sm space-y-2">
          <p className="font-semibold text-foreground">
            {t('features_page.maturity_key_title', { defaultValue: 'How we label maturity' })}
          </p>
          <ul className="space-y-1.5 list-none">
            <li className="flex items-start gap-2">
              <Chip color="success" variant="flat" size="sm" className="shrink-0">GA</Chip>
              <span className="text-foreground-600">
                {t('features_page.maturity_ga', {
                  defaultValue: 'Generally Available — stable, supported, used in production across pilot tenants.',
                })}
              </span>
            </li>
            <li className="flex items-start gap-2">
              <Chip color="warning" variant="flat" size="sm" className="shrink-0">
                {t('features_page.chips.beta')}
              </Chip>
              <span className="text-foreground-600">
                {t('features_page.maturity_beta', {
                  defaultValue: 'Working in production today, but the public surface or wire protocol is still being hardened.',
                })}
              </span>
            </li>
            <li className="flex items-start gap-2">
              <Chip color="secondary" variant="flat" size="sm" className="shrink-0">
                {t('features_page.chips.preview')}
              </Chip>
              <span className="text-foreground-600">
                {t('features_page.maturity_preview', {
                  defaultValue: 'Recently shipped and available to opt in. Expect rapid iteration — the API and UX may change.',
                })}
              </span>
            </li>
          </ul>
        </CardBody>
      </Card>

      {/* Feature groups */}
      {GROUPS.map((group, index) => {
        const icons = [
          <Sparkles className="w-5 h-5 text-primary" aria-hidden="true" />,
          <Globe className="w-5 h-5 text-primary" aria-hidden="true" />,
          <CheckCircle className="w-5 h-5 text-success" aria-hidden="true" />,
          <CheckCircle className="w-5 h-5 text-success" aria-hidden="true" />,
          <Shield className="w-5 h-5 text-warning" aria-hidden="true" />,
          <Sparkles className="w-5 h-5 text-secondary" aria-hidden="true" />,
          <Sparkles className="w-5 h-5 text-secondary" aria-hidden="true" />,
          <Shield className="w-5 h-5 text-primary" aria-hidden="true" />,
        ];
        return <FeatureSection key={group.title} group={group} icon={icons[index]} />;
      })}

      {/* Modern Tech Stack */}
      <Card>
        <CardHeader>
          <h2 className="text-lg font-semibold">
            {t('features_page.tech_stack_title', { defaultValue: 'Modern Tech Stack' })}
          </h2>
        </CardHeader>
        <Divider />
        <CardBody className="text-sm text-foreground-600">
          <ul className="grid sm:grid-cols-2 gap-y-1.5 gap-x-6 list-none">
            <li><strong>{t('features_page.tech_stack.frontend_label')}:</strong> {t('features_page.tech_stack.frontend_value')}</li>
            <li><strong>{t('features_page.tech_stack.backend_label')}:</strong> {t('features_page.tech_stack.backend_value')}</li>
            <li><strong>{t('features_page.tech_stack.database_label')}:</strong> {t('features_page.tech_stack.database_value')}</li>
            <li><strong>{t('features_page.tech_stack.search_label')}:</strong> {t('features_page.tech_stack.search_value')}</li>
            <li><strong>{t('features_page.tech_stack.ai_label')}:</strong> {t('features_page.tech_stack.ai_value')}</li>
            <li><strong>{t('features_page.tech_stack.realtime_label')}:</strong> {t('features_page.tech_stack.realtime_value')}</li>
            <li><strong>{t('features_page.tech_stack.mobile_label')}:</strong> {t('features_page.tech_stack.mobile_value')}</li>
            <li><strong>{t('features_page.tech_stack.infrastructure_label')}:</strong> {t('features_page.tech_stack.infrastructure_value')}</li>
          </ul>
        </CardBody>
      </Card>

      {/* Open source + how to help */}
      <Card className="border border-primary-200 dark:border-primary-800">
        <CardHeader className="flex gap-2 items-center">
          <Github className="w-5 h-5 text-primary" aria-hidden="true" />
          <h2 className="text-lg font-semibold">
            {t('features_page.open_source_title', { defaultValue: 'Open Source — AGPL-3.0' })}
          </h2>
        </CardHeader>
        <Divider />
        <CardBody className="text-sm text-foreground-600 space-y-3">
          <p>
            {t('features_page.open_source_body', {
              defaultValue:
                'Project NEXUS is fully open source under AGPL-3.0. Every line of code is auditable, forkable, and self-hostable. Federation protocols are documented as open standards so no single platform controls the global timebanking network.',
            })}
          </p>
          <div className="flex flex-wrap gap-3">
            <a
              href="https://github.com/jasperfordesq-ai/nexus-v1"
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex items-center gap-1.5 text-primary underline font-medium focus:outline-none focus:ring-2 focus:ring-primary rounded"
            >
              <Github className="w-3.5 h-3.5" aria-hidden="true" />
              {t('features_page.link_repo', { defaultValue: 'Source repository' })}
              <ExternalLink className="w-3 h-3" aria-hidden="true" />
            </a>
            <Link
              to={tenantPath('/changelog')}
              className="inline-flex items-center gap-1.5 text-primary underline font-medium focus:outline-none focus:ring-2 focus:ring-primary rounded"
            >
              {t('features_page.link_changelog', { defaultValue: 'Changelog' })}
            </Link>
            <a
              href="https://project-nexus.canny.io/"
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex items-center gap-1.5 text-primary underline font-medium focus:outline-none focus:ring-2 focus:ring-primary rounded"
            >
              <Bug className="w-3.5 h-3.5" aria-hidden="true" />
              {t('features_page.link_report_bug', { defaultValue: 'Report a bug' })}
              <ExternalLink className="w-3 h-3" aria-hidden="true" />
            </a>
            <Link
              to={tenantPath('/about')}
              className="inline-flex items-center gap-1.5 text-primary underline font-medium focus:outline-none focus:ring-2 focus:ring-primary rounded"
            >
              {t('features_page.link_about', { defaultValue: 'About this tenant' })}
            </Link>
          </div>
        </CardBody>
      </Card>

      {/* Security disclosure */}
      <Card className="border border-danger-200 dark:border-danger-800">
        <CardHeader className="flex gap-2 items-center">
          <Shield className="w-5 h-5 text-danger" aria-hidden="true" />
          <h2 className="text-lg font-semibold">
            {t('features_page.security_title', { defaultValue: 'Security disclosure' })}
          </h2>
        </CardHeader>
        <Divider />
        <CardBody className="text-sm text-foreground-600">
          <p>
            {t('features_page.security_body_before', {
              defaultValue: 'Found a security issue? Please report it privately to ',
            })}
            <a
              href="mailto:jasper@hour-timebank.ie"
              className="text-primary underline font-medium focus:outline-none focus:ring-2 focus:ring-primary rounded"
            >
              {t('features_page.security_email')}
            </a>
            {t('features_page.security_body_after', {
              defaultValue:
                ' rather than filing a public issue. Full vulnerability-disclosure policy in SECURITY.md on the source repository.',
            })}
          </p>
        </CardBody>
      </Card>
    </div>
  );
}

export default FeaturesPage;
