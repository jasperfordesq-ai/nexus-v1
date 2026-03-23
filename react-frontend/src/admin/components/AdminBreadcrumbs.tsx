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
  admin: 'breadcrumbs.admin',
  users: 'breadcrumbs.users',
  listings: 'breadcrumbs.listings',
  blog: 'breadcrumbs.blog',
  pages: 'breadcrumbs.pages',
  menus: 'breadcrumbs.menus',
  categories: 'breadcrumbs.categories',
  attributes: 'breadcrumbs.attributes',
  gamification: 'breadcrumbs.gamification',
  campaigns: 'breadcrumbs.campaigns',
  'custom-badges': 'breadcrumbs.custom_badges',
  'smart-matching': 'breadcrumbs.smart_matching',
  'match-approvals': 'breadcrumbs.match_approvals',
  'broker-controls': 'breadcrumbs.broker_controls',
  newsletters: 'breadcrumbs.newsletters',
  subscribers: 'breadcrumbs.subscribers',
  segments: 'breadcrumbs.segments',
  templates: 'breadcrumbs.templates',
  federation: 'breadcrumbs.federation',
  partnerships: 'breadcrumbs.partnerships',
  directory: 'breadcrumbs.directory',
  'api-keys': 'breadcrumbs.api_keys',
  enterprise: 'breadcrumbs.enterprise',
  roles: 'breadcrumbs.roles',
  permissions: 'breadcrumbs.permissions',
  gdpr: 'breadcrumbs.gdpr',
  monitoring: 'breadcrumbs.monitoring',
  config: 'breadcrumbs.config',
  secrets: 'breadcrumbs.secrets',
  'legal-documents': 'breadcrumbs.legal_documents',
  seo: 'breadcrumbs.seo',
  '404-errors': 'breadcrumbs.errors_404',
  timebanking: 'breadcrumbs.timebanking',
  alerts: 'breadcrumbs.alerts',
  'org-wallets': 'breadcrumbs.org_wallets',
  plans: 'breadcrumbs.plans',
  settings: 'breadcrumbs.settings',
  'tenant-features': 'breadcrumbs.tenant_features',
  'cron-jobs': 'breadcrumbs.cron_jobs',
  'activity-log': 'breadcrumbs.activity_log',
  analytics: 'breadcrumbs.analytics',
  create: 'breadcrumbs.create',
  edit: 'breadcrumbs.edit',
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
    <nav aria-label="Breadcrumbs" className="mb-4">
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
