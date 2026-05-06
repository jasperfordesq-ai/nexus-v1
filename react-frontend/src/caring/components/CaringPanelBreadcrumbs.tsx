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
    const labelKey = `panel.breadcrumbs.segments.${segment}`;
    const translated = t(labelKey);
    const label = translated !== labelKey ? translated : `[missing: ${segment}]`;
    const isLast = i === segments.length - 1;

    crumbs.push({ label, href: isLast ? undefined : currentPath });
  }

  if (crumbs.length <= 1) return null;

  return (
    <nav aria-label={t('panel.breadcrumbs.aria')} className="mb-4 max-w-full overflow-x-auto pb-1">
      <ol className="flex w-max max-w-full items-center gap-1.5 text-sm text-default-500">
        {crumbs.map((crumb, index) => (
          <li key={crumb.label} className="flex min-w-0 items-center gap-1.5">
            {index > 0 && <ChevronRight size={14} className="shrink-0 text-default-300" />}
            {index === 0 && <LayoutDashboard size={14} className="mr-1 shrink-0" />}
            {crumb.href ? (
              <Link to={crumb.href} className="max-w-[9rem] truncate hover:text-foreground transition-colors sm:max-w-[14rem]">
                {crumb.label}
              </Link>
            ) : (
              <span className="max-w-[12rem] truncate font-medium text-foreground sm:max-w-[18rem]">{crumb.label}</span>
            )}
          </li>
        ))}
      </ol>
    </nav>
  );
}

export default CaringPanelBreadcrumbs;
