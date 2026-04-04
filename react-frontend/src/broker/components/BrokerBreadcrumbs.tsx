// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Broker Breadcrumbs
 * Auto-generates breadcrumbs from the current URL path.
 */

import { Link, useLocation } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useTenant } from '@/contexts';
import { ChevronRight, LayoutDashboard } from 'lucide-react';

const SEGMENT_LABELS: Record<string, string> = {
  broker: 'breadcrumbs.dashboard',
  members: 'breadcrumbs.members',
  onboarding: 'breadcrumbs.onboarding',
  safeguarding: 'breadcrumbs.safeguarding',
  vetting: 'breadcrumbs.vetting',
  exchanges: 'breadcrumbs.exchanges',
  messages: 'breadcrumbs.messages',
};

export function BrokerBreadcrumbs() {
  const { t } = useTranslation('broker');
  const location = useLocation();
  const { tenantSlug } = useTenant();

  let path = location.pathname;
  if (tenantSlug) {
    path = path.replace(`/${tenantSlug}`, '');
  }

  const segments = path.split('/').filter(Boolean);
  const crumbs: { label: string; href?: string }[] = [];

  let currentPath = tenantSlug ? `/${tenantSlug}` : '';

  for (let i = 0; i < segments.length; i++) {
    const segment = segments[i];
    if (!segment || /^\d+$/.test(segment)) continue;

    currentPath += `/${segment}`;
    const labelKey = SEGMENT_LABELS[segment];
    const label = labelKey
      ? t(labelKey)
      : segment.charAt(0).toUpperCase() + segment.slice(1).replace(/-/g, ' ');
    const isLast = i === segments.length - 1;

    crumbs.push({ label, href: isLast ? undefined : currentPath });
  }

  if (crumbs.length <= 1) return null;

  return (
    <nav aria-label={t('breadcrumbs.aria_label')} className="mb-4">
      <ol className="flex items-center gap-1.5 text-sm text-default-500">
        {crumbs.map((crumb, index) => (
          <li key={crumb.label} className="flex items-center gap-1.5">
            {index > 0 && <ChevronRight size={14} className="text-default-300" />}
            {index === 0 && <LayoutDashboard size={14} className="mr-1" />}
            {crumb.href ? (
              <Link to={crumb.href} className="hover:text-foreground transition-colors">
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

export default BrokerBreadcrumbs;
