// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Link, useLocation } from 'react-router-dom';
import { useTenant } from '@/contexts';
import ChevronRight from 'lucide-react/icons/chevron-right';
import LayoutDashboard from 'lucide-react/icons/layout-dashboard';

const SEGMENT_LABELS: Record<string, string> = {
  caring: 'Dashboard',
  workflow: 'Workflow',
  projects: 'Projects',
  loyalty: 'Loyalty Programme',
  'hour-transfers': 'Hour Transfers',
  'regional-points': 'Regional Points',
  'sub-regions': 'Sub-Regions',
  'federation-peers': 'Federation Peers',
  'sla-dashboard': 'SLA Dashboard',
  providers: 'Providers',
  'warmth-pass': 'Warmth Pass',
  'recipient-circle': 'Care Recipient Circle',
  nudges: 'Smart Nudges',
  'emergency-alerts': 'Emergency Alerts',
  surveys: 'Municipal Surveys',
  copilot: 'Communication Copilot',
  'civic-digest': 'Civic Digest',
  'lead-nurture': 'Lead Nurture',
  'success-stories': 'Success Stories',
  feedback: 'Feedback Inbox',
  verification: 'Municipal Verification',
  safeguarding: 'Safeguarding Reports',
  'trust-tier': 'Trust Tiers',
  'launch-readiness': 'Launch Readiness',
  'pilot-scoreboard': 'Pilot Scoreboard',
  'data-quality': 'Pilot Data Quality',
  'operating-policy': 'Operating Policy',
  'disclosure-pack': 'Disclosure Pack',
  'commercial-boundary': 'Commercial Boundary',
  'isolated-node': 'Isolated Node Gate',
  research: 'Research Partnerships',
  'external-integrations': 'External Integrations',
  'integration-showcase': 'Integration Showcase',
  'municipal-impact': 'Municipal Impact Reports',
  'kpi-baselines': 'KPI Baselines',
  'municipal-roi': 'Municipal ROI',
  'category-coefficients': 'Category Coefficients',
};

export function CaringPanelBreadcrumbs() {
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
    const label = SEGMENT_LABELS[segment]
      ?? segment.charAt(0).toUpperCase() + segment.slice(1).replace(/-/g, ' ');
    const isLast = i === segments.length - 1;

    crumbs.push({ label, href: isLast ? undefined : currentPath });
  }

  if (crumbs.length <= 1) return null;

  return (
    <nav aria-label="Breadcrumbs" className="mb-4">
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

export default CaringPanelBreadcrumbs;
