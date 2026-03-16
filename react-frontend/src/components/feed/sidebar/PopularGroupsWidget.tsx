// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * PopularGroupsWidget - Shows top groups in the sidebar
 */

import { Link } from 'react-router-dom';
import { Users } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { useTenant } from '@/contexts';
import { resolveAssetUrl } from '@/lib/helpers';

export interface PopularGroup {
  id: number;
  name: string;
  image_url?: string;
  member_count: number;
}

interface PopularGroupsWidgetProps {
  groups: PopularGroup[];
}

export function PopularGroupsWidget({ groups }: PopularGroupsWidgetProps) {
  const { tenantPath } = useTenant();
  const { t } = useTranslation('feed');

  if (groups.length === 0) return null;

  return (
    <GlassCard className="p-4">
      <div className="flex items-center justify-between mb-3">
        <div className="flex items-center gap-2">
          <Users className="w-4 h-4 text-violet-500" aria-hidden="true" />
          <h3 className="font-semibold text-sm text-[var(--text-primary)]">
            {t('sidebar.groups.title', 'Popular Groups')}
          </h3>
        </div>
        <Link
          to={tenantPath('/groups')}
          className="text-xs text-indigo-500 hover:text-indigo-600 transition-colors"
        >
          {t('sidebar.groups.see_all', 'See All')}
        </Link>
      </div>

      <div className="space-y-2">
        {groups.map((group) => (
          <Link
            key={group.id}
            to={tenantPath(`/groups/${group.id}`)}
            className="flex items-center gap-3 p-2 rounded-lg hover:bg-[var(--surface-elevated)] transition-colors group"
          >
            {/* Group icon/image */}
            <div className="w-9 h-9 rounded-lg bg-[var(--surface-elevated)] border border-[var(--border-default)] flex items-center justify-center flex-shrink-0 overflow-hidden">
              {group.image_url ? (
                <img
                  src={resolveAssetUrl(group.image_url)}
                  alt={group.name}
                  className="w-full h-full object-cover"
                  width={36}
                  height={36}
                  loading="lazy"
                  onError={(e) => { (e.target as HTMLImageElement).style.display = 'none'; }}
                />
              ) : (
                <Users className="w-4 h-4 text-[var(--text-muted)]" aria-hidden="true" />
              )}
            </div>
            <div className="flex-1 min-w-0">
              <p className="text-sm font-medium text-[var(--text-primary)] truncate group-hover:text-indigo-500 transition-colors">
                {group.name}
              </p>
              <p className="text-xs text-[var(--text-muted)]">
                {t('sidebar.groups.members', '{{count}} members', { count: group.member_count })}
              </p>
            </div>
          </Link>
        ))}
      </div>
    </GlassCard>
  );
}

export default PopularGroupsWidget;
