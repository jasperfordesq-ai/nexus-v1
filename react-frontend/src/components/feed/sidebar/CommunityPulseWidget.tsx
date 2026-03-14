// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * CommunityPulseWidget - Shows community stats in a compact 2x2 grid
 */

import { Link } from 'react-router-dom';
import { HeartPulse, Users, Tag, CalendarDays, UsersRound } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { useTenant } from '@/contexts';

export interface CommunityStats {
  members: number;
  listings: number;
  events: number;
  groups: number;
}

interface CommunityPulseWidgetProps {
  stats: CommunityStats;
}

export function CommunityPulseWidget({ stats }: CommunityPulseWidgetProps) {
  const { tenantPath } = useTenant();
  const { t } = useTranslation('feed');

  const items = [
    {
      icon: Users,
      count: stats.members,
      label: t('sidebar.pulse.members', 'Members'),
      path: '/members',
      color: 'text-indigo-500',
      bg: 'bg-indigo-500/10',
    },
    {
      icon: Tag,
      count: stats.listings,
      label: t('sidebar.pulse.listings', 'Listings'),
      path: '/listings',
      color: 'text-emerald-500',
      bg: 'bg-emerald-500/10',
    },
    {
      icon: CalendarDays,
      count: stats.events,
      label: t('sidebar.pulse.events', 'Events'),
      path: '/events',
      color: 'text-pink-500',
      bg: 'bg-pink-500/10',
    },
    {
      icon: UsersRound,
      count: stats.groups,
      label: t('sidebar.pulse.groups', 'Groups'),
      path: '/groups',
      color: 'text-amber-500',
      bg: 'bg-amber-500/10',
    },
  ];

  return (
    <GlassCard className="p-4">
      <div className="flex items-center gap-2 mb-3">
        <HeartPulse className="w-4 h-4 text-pink-500" aria-hidden="true" />
        <h3 className="font-semibold text-sm text-[var(--text-primary)]">
          {t('sidebar.pulse.title', 'Community Pulse')}
        </h3>
      </div>

      <div className="grid grid-cols-2 gap-2">
        {items.map((item) => (
          <Link
            key={item.path}
            to={tenantPath(item.path)}
            className={`flex flex-col items-center gap-1 p-3 rounded-lg ${item.bg} hover:opacity-80 transition-opacity`}
          >
            <item.icon className={`w-4 h-4 ${item.color}`} aria-hidden="true" />
            <p className={`text-sm font-bold ${item.color}`}>
              {(item.count ?? 0).toLocaleString()}
            </p>
            <p className="text-[10px] text-[var(--text-muted)]">{item.label}</p>
          </Link>
        ))}
      </div>
    </GlassCard>
  );
}

export default CommunityPulseWidget;
