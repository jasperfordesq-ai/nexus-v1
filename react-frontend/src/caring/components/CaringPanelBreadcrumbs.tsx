// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Link, useLocation } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useTenant } from '@/contexts';
import ChevronRight from 'lucide-react/icons/chevron-right';
import LayoutDashboard from 'lucide-react/icons/layout-dashboard';

export function CaringPanelBreadcrumbs() {
  const location = useLocation();
  const { tenantSlug } = useTenant();
  const { t } = useTranslation('caring_community');

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
    const label = t(
      `panel.breadcrumbs.segments.${segment}`,
      { defaultValue: segment.charAt(0).toUpperCase() + segment.slice(1).replace(/-/g, ' ') },
    );
    const isLast = i === segments.length - 1;

    crumbs.push({ label, href: isLast ? undefined : currentPath });
  }

  if (crumbs.length <= 1) return null;

  return (
    <nav aria-label={t('panel.breadcrumbs.aria')} className="mb-4">
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
