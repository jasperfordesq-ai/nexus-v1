// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { GlassCard } from '@/components/ui/GlassCard';
/**
 * Group Subgroups Tab
 * Lists sub-groups of a parent group with navigation links.
 */

import { Link } from 'react-router-dom';
import Users from 'lucide-react/icons/users';
import ChevronRight from 'lucide-react/icons/chevron-right';
import { useTranslation } from 'react-i18next';
import { useTenant } from '@/contexts';

interface SubGroup {
  id: number;
  name: string;
  member_count: number;
}

interface GroupSubgroupsTabProps {
  subGroups: SubGroup[];
}

export function GroupSubgroupsTab({ subGroups }: GroupSubgroupsTabProps) {
  const { t } = useTranslation('groups');
  const { tenantPath } = useTenant();

  return (
    <GlassCard className="p-4 sm:p-6">
      <div className="space-y-3">
        {subGroups.map((subGroup) => (
          <Link key={subGroup.id} className="block min-w-0" to={tenantPath(`/groups/${subGroup.id}`)}>
            <div className="flex min-w-0 items-center justify-between gap-3 rounded-lg bg-theme-elevated p-3 transition-colors hover:bg-theme-hover sm:p-4">
              <div className="flex min-w-0 flex-1 items-center gap-3 sm:gap-4">
                <div className="shrink-0 rounded-xl bg-gradient-to-br from-accent/20 to-accent-gradient-end/20 p-3">
                  <Users className="w-5 h-5 text-accent" aria-hidden="true" />
                </div>
                <div className="min-w-0 flex-1">
                  <p className="truncate font-medium text-theme-primary" title={subGroup.name}>{subGroup.name}</p>
                  <p className="text-sm text-theme-subtle">
                    {t('detail.members_count', { count: subGroup.member_count })}
                  </p>
                </div>
              </div>
              <ChevronRight className="h-5 w-5 shrink-0 text-theme-subtle rtl:rotate-180" aria-hidden="true" />
            </div>
          </Link>
        ))}
      </div>
    </GlassCard>
  );
}
