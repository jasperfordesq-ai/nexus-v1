// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Breadcrumbs
 * Auto-generates breadcrumbs from the current URL path
 */

import { Link, useLocation } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useTenant } from '@/contexts';
import { ChevronRight, LayoutDashboard } from 'lucide-react';

interface BreadcrumbItem {
  label: string;
  href?: string;
}

interface AdminBreadcrumbsProps {
  items?: BreadcrumbItem[];
}

// Map URL segments to i18n keys for breadcrumb labels
const SEGMENT_LABEL_KEYS: Record<string, string> = {
  // Core
  admin: 'breadcrumbs.admin',
  create: 'breadcrumbs.create',
  edit: 'breadcrumbs.edit',
  detail: 'breadcrumbs.detail',
  list: 'breadcrumbs.list',

  // Users
  users: 'breadcrumbs.users',

  // CRM
  crm: 'breadcrumbs.crm',
  notes: 'breadcrumbs.notes',
  tasks: 'breadcrumbs.tasks',
  tags: 'breadcrumbs.tags',
  timeline: 'breadcrumbs.timeline',
  funnel: 'breadcrumbs.funnel',

  // Content
  listings: 'breadcrumbs.listings',
  blog: 'breadcrumbs.blog',
  pages: 'breadcrumbs.pages',
  builder: 'breadcrumbs.builder',
  menus: 'breadcrumbs.menus',
  categories: 'breadcrumbs.categories',
  attributes: 'breadcrumbs.attributes',
  plans: 'breadcrumbs.plans',
  subscriptions: 'breadcrumbs.subscriptions',

  // Engagement / Gamification
  gamification: 'breadcrumbs.gamification',
  campaigns: 'breadcrumbs.campaigns',
  'custom-badges': 'breadcrumbs.custom_badges',

  // Matching & Broker
  'smart-matching': 'breadcrumbs.smart_matching',
  configuration: 'breadcrumbs.configuration',
  'match-approvals': 'breadcrumbs.match_approvals',
  'broker-controls': 'breadcrumbs.broker_controls',
  exchanges: 'breadcrumbs.exchanges',
  'risk-tags': 'breadcrumbs.risk_tags',
  messages: 'breadcrumbs.messages',
  vetting: 'breadcrumbs.vetting',
  insurance: 'breadcrumbs.insurance',
  archives: 'breadcrumbs.archives',
  'match-debug': 'breadcrumbs.match_debug',

  // Moderation
  moderation: 'breadcrumbs.moderation',
  feed: 'breadcrumbs.feed',
  comments: 'breadcrumbs.comments',
  reviews: 'breadcrumbs.reviews',
  reports: 'breadcrumbs.reports',
  queue: 'breadcrumbs.queue',

  // Newsletters / Marketing
  newsletters: 'breadcrumbs.newsletters',
  subscribers: 'breadcrumbs.subscribers',
  segments: 'breadcrumbs.segments',
  templates: 'breadcrumbs.templates',
  bounces: 'breadcrumbs.bounces',
  'send-time-optimizer': 'breadcrumbs.send_time_optimizer',
  diagnostics: 'breadcrumbs.diagnostics',
  stats: 'breadcrumbs.stats',
  activity: 'breadcrumbs.activity',

  // Advanced / SEO
  'ai-settings': 'breadcrumbs.ai_settings',
  'email-settings': 'breadcrumbs.email_settings',
  'algorithm-settings': 'breadcrumbs.algorithm_settings',
  seo: 'breadcrumbs.seo',
  audit: 'breadcrumbs.audit',
  redirects: 'breadcrumbs.redirects',
  '404-errors': 'breadcrumbs.errors_404',

  // Financial / Timebanking
  timebanking: 'breadcrumbs.timebanking',
  alerts: 'breadcrumbs.alerts',
  'user-report': 'breadcrumbs.user_report',
  'org-wallets': 'breadcrumbs.org_wallets',
  'create-org': 'breadcrumbs.create_org',
  'starting-balances': 'breadcrumbs.starting_balances',

  // Enterprise
  enterprise: 'breadcrumbs.enterprise',
  roles: 'breadcrumbs.roles',
  permissions: 'breadcrumbs.permissions',
  gdpr: 'breadcrumbs.gdpr',
  requests: 'breadcrumbs.requests',
  consents: 'breadcrumbs.consents',
  breaches: 'breadcrumbs.breaches',
  monitoring: 'breadcrumbs.monitoring',
  health: 'breadcrumbs.health',
  logs: 'breadcrumbs.logs',
  config: 'breadcrumbs.config',
  secrets: 'breadcrumbs.secrets',

  // Performance
  performance: 'breadcrumbs.performance',

  // Legal Documents
  'legal-documents': 'breadcrumbs.legal_documents',
  compliance: 'breadcrumbs.compliance',
  versions: 'breadcrumbs.versions',

  // Federation
  federation: 'breadcrumbs.federation',
  partnerships: 'breadcrumbs.partnerships',
  directory: 'breadcrumbs.directory',
  profile: 'breadcrumbs.profile',
  'api-keys': 'breadcrumbs.api_keys',
  data: 'breadcrumbs.data',
  'credit-agreements': 'breadcrumbs.credit_agreements',
  neighborhoods: 'breadcrumbs.neighborhoods',
  'system-controls': 'breadcrumbs.system_controls',
  whitelist: 'breadcrumbs.whitelist',
  features: 'breadcrumbs.features',

  // Safeguarding
  safeguarding: 'breadcrumbs.safeguarding',
  'safeguarding-options': 'breadcrumbs.safeguarding_options',

  // System
  settings: 'breadcrumbs.settings',
  'registration-policy': 'breadcrumbs.registration_policy',
  'onboarding-settings': 'breadcrumbs.onboarding_settings',
  'tenant-features': 'breadcrumbs.tenant_features',
  'module-configuration': 'breadcrumbs.module_configuration',
  'translation-config': 'breadcrumbs.translation_config',
  'cron-jobs': 'breadcrumbs.cron_jobs',
  setup: 'breadcrumbs.setup',
  'activity-log': 'breadcrumbs.activity_log',
  tests: 'breadcrumbs.tests',
  'seed-generator': 'breadcrumbs.seed_generator',
  'webp-converter': 'breadcrumbs.webp_converter',
  'image-settings': 'breadcrumbs.image_settings',
  'native-app': 'breadcrumbs.native_app',
  'blog-restore': 'breadcrumbs.blog_restore',

  // Community / Groups
  groups: 'breadcrumbs.groups',
  approvals: 'breadcrumbs.approvals',
  types: 'breadcrumbs.types',
  recommendations: 'breadcrumbs.recommendations',
  ranking: 'breadcrumbs.ranking',
  'smart-match-users': 'breadcrumbs.smart_match_users',
  'smart-match-monitoring': 'breadcrumbs.smart_match_monitoring',

  // Volunteering
  volunteering: 'breadcrumbs.volunteering',
  organizations: 'breadcrumbs.organizations',

  // Events, Polls, Goals, Resources, Jobs, Ideation
  events: 'breadcrumbs.events',
  polls: 'breadcrumbs.polls',
  goals: 'breadcrumbs.goals',
  resources: 'breadcrumbs.resources',
  jobs: 'breadcrumbs.jobs',
  ideation: 'breadcrumbs.ideation',

  // Deliverability
  deliverability: 'breadcrumbs.deliverability',

  // Diagnostics
  'matching-diagnostic': 'breadcrumbs.matching_diagnostic',
  'nexus-score': 'breadcrumbs.nexus_score',

  // Analytics & Reporting
  analytics: 'breadcrumbs.analytics',
  'community-analytics': 'breadcrumbs.community_analytics',
  'impact-report': 'breadcrumbs.impact_report',
  'social-value': 'breadcrumbs.social_value',
  members: 'breadcrumbs.members',
  hours: 'breadcrumbs.hours',
  'inactive-members': 'breadcrumbs.inactive_members',

  // Super Admin
  super: 'breadcrumbs.super',
  tenants: 'breadcrumbs.tenants',
  hierarchy: 'breadcrumbs.hierarchy',
  bulk: 'breadcrumbs.bulk',
  'audit-log': 'breadcrumbs.audit_log',
};

export function AdminBreadcrumbs({ items }: AdminBreadcrumbsProps) {
  const { t } = useTranslation('admin');
  const location = useLocation();
  const { tenantSlug } = useTenant();

  // Auto-generate breadcrumbs from URL if not provided
  const breadcrumbs: BreadcrumbItem[] = items || (() => {
    let path = location.pathname;

    // Strip tenant slug prefix if present
    if (tenantSlug) {
      path = path.replace(`/${tenantSlug}`, '');
    }

    const segments = path.split('/').filter(Boolean);
    const crumbs: BreadcrumbItem[] = [];

    let currentPath = tenantSlug ? `/${tenantSlug}` : '';

    for (let i = 0; i < segments.length; i++) {
      const segment = segments[i];
      if (!segment) continue;
      currentPath += `/${segment}`;

      // Skip numeric IDs
      if (/^\d+$/.test(segment)) continue;

      const labelKey = SEGMENT_LABEL_KEYS[segment];
      const label = labelKey ? t(labelKey) : segment.charAt(0).toUpperCase() + segment.slice(1).replace(/-/g, ' ');
      const isLast = i === segments.length - 1;

      crumbs.push({
        label,
        href: isLast ? undefined : currentPath,
      });
    }

    return crumbs;
  })();

  if (breadcrumbs.length <= 1) return null;

  return (
    <nav aria-label={t('aria.breadcrumbs')} className="mb-4">
      <ol className="flex items-center gap-1.5 text-sm text-default-500">
        {breadcrumbs.map((crumb, index) => (
          <li key={crumb.label} className="flex items-center gap-1.5">
            {index > 0 && <ChevronRight size={14} className="text-default-300" />}
            {index === 0 && <LayoutDashboard size={14} className="mr-1" />}
            {crumb.href ? (
              <Link
                to={crumb.href}
                className="hover:text-foreground transition-colors"
              >
                {crumb.label}
              </Link>
            ) : (
              <span className="font-medium text-foreground">{crumb.label}</span>
            )}
          </li>
        ))}
      </ol>
    </nav>
  );
}

export default AdminBreadcrumbs;
