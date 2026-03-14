// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * QuickActionsWidget - Shows quick action buttons in the sidebar
 */

import { Link } from 'react-router-dom';
import { Button } from '@heroui/react';
import { Plus, CalendarDays, BarChart3, Target, UsersRound } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { useAuth, useTenant } from '@/contexts';

export function QuickActionsWidget() {
  const { isAuthenticated } = useAuth();
  const { tenantPath, hasFeature } = useTenant();
  const { t } = useTranslation('feed');

  if (!isAuthenticated) return null;

  const secondaryActions = [
    {
      label: t('sidebar.actions.host_event', 'Host Event'),
      icon: CalendarDays,
      path: '/events/create',
      color: 'text-pink-500',
      bg: 'bg-pink-500/10 hover:bg-pink-500/20',
      feature: 'events' as const,
    },
    {
      label: t('sidebar.actions.create_poll', 'Create Poll'),
      icon: BarChart3,
      path: '/polls',
      color: 'text-indigo-500',
      bg: 'bg-indigo-500/10 hover:bg-indigo-500/20',
      feature: 'polls' as const,
    },
    {
      label: t('sidebar.actions.set_goal', 'Set Goal'),
      icon: Target,
      path: '/goals',
      color: 'text-amber-500',
      bg: 'bg-amber-500/10 hover:bg-amber-500/20',
      feature: 'goals' as const,
    },
    {
      label: t('sidebar.actions.groups', 'Groups'),
      icon: UsersRound,
      path: '/groups',
      color: 'text-emerald-500',
      bg: 'bg-emerald-500/10 hover:bg-emerald-500/20',
      feature: 'groups' as const,
    },
  ];

  const enabledActions = secondaryActions.filter((action) => hasFeature(action.feature));

  return (
    <GlassCard className="p-4">
      {/* Primary CTA */}
      <Button
        as={Link}
        to={tenantPath('/listings/create')}
        className="w-full bg-gradient-to-r from-indigo-500 to-purple-600 text-white shadow-lg shadow-indigo-500/25 hover:shadow-indigo-500/40 transition-shadow font-medium"
        startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
      >
        {t('sidebar.actions.create_listing', 'Create New Listing')}
      </Button>

      {/* Secondary actions grid */}
      {enabledActions.length > 0 && (
        <div className="grid grid-cols-2 gap-2 mt-3">
          {enabledActions.map((action) => (
            <Link
              key={action.path}
              to={tenantPath(action.path)}
              className={`flex flex-col items-center gap-1.5 p-3 rounded-lg ${action.bg} transition-colors`}
            >
              <action.icon className={`w-4 h-4 ${action.color}`} aria-hidden="true" />
              <span className={`text-[11px] font-medium ${action.color}`}>
                {action.label}
              </span>
            </Link>
          ))}
        </div>
      )}
    </GlassCard>
  );
}

export default QuickActionsWidget;
