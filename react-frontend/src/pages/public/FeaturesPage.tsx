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
  key: string;
  maturity?: Maturity;
}

function FeatureList({ groupKey, items }: { groupKey: string; items: FeatureItem[] }) {
  const { t } = useTranslation('public');

  return (
    <ul className="space-y-3 list-none">
      {items.map((item) => {
        const copyKey = `features_page.groups.${groupKey}.items.${item.key}`;
        const note = t(`${copyKey}.note`, { defaultValue: '' });

        return (
        <li key={item.key} className="flex items-start gap-2">
          <CheckCircle className="w-4 h-4 text-success shrink-0 mt-1" aria-hidden="true" />
          <div className="text-sm">
            <span className="font-semibold text-foreground">{t(`${copyKey}.title`)}</span>
            <MaturityChip level={item.maturity ?? 'ga'} />
            <span className="text-foreground-600"> {t(`${copyKey}.description`)}</span>
            {note && (
              <p className="text-xs text-foreground-500 mt-1 italic">{note}</p>
            )}
          </div>
        </li>
        );
      })}
    </ul>
  );
}

interface FeatureGroup {
  key: string;
  items: FeatureItem[];
}

function FeatureSection({
  group,
  icon,
}: {
  group: FeatureGroup;
  icon?: ReactNode;
}) {
  const { t } = useTranslation('public');
  const groupKey = `features_page.groups.${group.key}`;
  const intro = t(`${groupKey}.intro`, { defaultValue: '' });

  return (
    <Card>
      <CardHeader className="flex gap-2 items-center">
        {icon}
        <h2 className="text-lg font-semibold">{t(`${groupKey}.title`)}</h2>
      </CardHeader>
      <Divider />
      <CardBody className="space-y-3">
        {intro && <p className="text-sm text-foreground-600">{intro}</p>}
        <FeatureList groupKey={group.key} items={group.items} />
      </CardBody>
    </Card>
  );
}

// ---------------------------------------------------------------------------
// Feature inventory
// ---------------------------------------------------------------------------

const GROUPS: FeatureGroup[] = [
  {
    key: 'core_platform',
    items: [
      {
        key: 'timebanking_engine'
      },
      {
        key: 'multi_tenancy'
      },
      {
        key: 'tenant_hierarchy'
      },
      {
        key: 'smart_matching'
      },
      {
        key: 'real_time_messaging'
      },
      {
        key: 'progressive_web_app'
      },
      {
        key: 'native_mobile_app',
        maturity: 'beta'
      }
    ]
  },
  {
    key: 'federation',
    items: [
      {
        key: 'federation_network'
      },
      {
        key: 'external_partner_federation',
        maturity: 'beta'
      },
      {
        key: 'multi_protocol_adapters',
        maturity: 'beta'
      },
      {
        key: 'federation_neighborhoods',
        maturity: 'beta'
      },
      {
        key: 'credit_agreements',
        maturity: 'beta'
      },
      {
        key: 'federation_analytics'
      }
    ]
  },
  {
    key: 'member_experience',
    items: [
      {
        key: 'service_listings'
      },
      {
        key: 'marketplace',
        maturity: 'beta'
      },
      {
        key: 'donations'
      },
      {
        key: 'identity_verification',
        maturity: 'beta'
      },
      {
        key: 'exchange_workflow'
      },
      {
        key: 'group_exchanges'
      },
      {
        key: 'social_feed'
      },
      {
        key: 'stories',
        maturity: 'beta'
      },
      {
        key: 'presence_system'
      },
      {
        key: 'events_and_groups'
      },
      {
        key: 'connections'
      },
      {
        key: 'members_directory'
      },
      {
        key: 'gamification'
      },
      {
        key: 'goals_and_impact'
      },
      {
        key: 'ideation_challenges'
      },
      {
        key: 'volunteering'
      },
      {
        key: 'job_vacancies'
      },
      {
        key: 'organisations'
      },
      {
        key: 'sub_accounts_family_accounts'
      },
      {
        key: 'reviews_and_ratings'
      },
      {
        key: 'endorsements'
      },
      {
        key: 'polls'
      },
      {
        key: 'skills_browse'
      },
      {
        key: 'availability_scheduling'
      }
    ]
  },
  {
    key: 'content_and_communication',
    items: [
      {
        key: 'blog'
      },
      {
        key: 'resources_and_knowledge_base'
      },
      {
        key: 'help_center'
      },
      {
        key: 'custom_pages'
      },
      {
        key: 'newsletter_system'
      },
      {
        key: 'ai_chat',
        maturity: 'beta'
      },
      {
        key: 'legal_hub'
      },
      {
        key: 'impact_reports'
      },
      {
        key: 'social_prescribing',
        maturity: 'preview'
      }
    ]
  },
  {
    key: 'trust_reputation_and_safety',
    items: [
      {
        key: 'member_verification_badges'
      },
      {
        key: 'nexusscore'
      },
      {
        key: 'streaks'
      },
      {
        key: 'personal_insights_dashboard'
      },
      {
        key: 'safeguarding_module'
      },
      {
        key: 'crm'
      }
    ]
  },
  {
    key: 'ai_and_recommendation_engine',
    items: [
      {
        key: 'semantic_search'
      },
      {
        key: 'collaborative_filtering'
      },
      {
        key: 'semantic_embeddings'
      },
      {
        key: 'edgerank_feed'
      },
      {
        key: 'matchrank_and_communityrank'
      },
      {
        key: 'group_recommendations'
      },
      {
        key: 'match_learning'
      },
      {
        key: 'algorithm_health_dashboard'
      }
    ]
  },
  {
    key: 'caring_community_layer',
    items: [
      {
        key: 'civic_digest',
        maturity: 'preview'
      },
      {
        key: 'success_stories',
        maturity: 'preview'
      },
      {
        key: 'feedback_inbox',
        maturity: 'preview'
      },
      {
        key: 'integration_showcase',
        maturity: 'preview'
      },
      {
        key: 'lead_nurture',
        maturity: 'preview'
      },
      {
        key: 'copilot',
        maturity: 'preview'
      }
    ]
  },
  {
    key: 'built_for_production',
    items: [
      {
        key: 'enterprise_security'
      },
      {
        key: 'stripe_payments_layer'
      },
      {
        key: 'gdpr_compliance_suite'
      },
      {
        key: 'fraud_and_abuse_detection'
      },
      {
        key: 'insurance_certificate_tracking'
      },
      {
        key: 'enterprise_rbac'
      },
      {
        key: 'wcag_2_1_aa_accessibility'
      },
      {
        key: 'multi_language_support'
      },
      {
        key: 'self_hosted_prerendering'
      },
      {
        key: 'guided_onboarding'
      },
      {
        key: 'admin_panel'
      },
      {
        key: 'email_webhook_processing'
      },
      {
        key: '500plus_phpunit_tests'
      },
      {
        key: 'openapi_3_0_specification'
      },
      {
        key: 'fully_dockerized'
      }
    ]
  }
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
        return <FeatureSection key={group.key} group={group} icon={icons[index]} />;
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
