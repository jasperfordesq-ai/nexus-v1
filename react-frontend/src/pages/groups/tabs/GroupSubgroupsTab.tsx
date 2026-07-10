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
    <GlassCard className="p-6">
      <div className="space-y-3">
        {subGroups.map((subGroup) => (
          <Link key={subGroup.id} to={tenantPath(`/groups/${subGroup.id}`)}>
            <div className="flex items-center justify-between p-4 rounded-lg bg-theme-elevated hover:bg-theme-hover transition-colors">
              <div className="flex items-center gap-4">
                <div className="p-3 rounded-xl bg-gradient-to-br from-accent/20 to-accent-gradient-end/20">
                  <Users className="w-5 h-5 text-accent" aria-hidden="true" />
                </div>
                <div>
                  <p className="font-medium text-theme-primary">{subGroup.name}</p>
                  <p className="text-sm text-theme-subtle">
                    {t('detail.members_count', { count: subGroup.member_count })}
                  </p>
                </div>
              </div>
              <ChevronRight className="w-5 h-5 text-theme-subtle" aria-hidden="true" />
            </div>
          </Link>
        ))}
      </div>
    </GlassCard>
  );
}
