// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * FriendsWidget - Shows accepted connections in the sidebar
 */

import { Link } from 'react-router-dom';
import { Avatar } from '@heroui/react';
import { Users } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { useTenant } from '@/contexts';
import { resolveAvatarUrl } from '@/lib/helpers';

export interface Friend {
  id: number;
  name: string;
  avatar_url?: string;
  location?: string;
  is_online?: boolean;
  is_recent?: boolean;
}

interface FriendsWidgetProps {
  friends: Friend[];
}

export function FriendsWidget({ friends }: FriendsWidgetProps) {
  const { tenantPath } = useTenant();
  const { t } = useTranslation('feed');

  if (friends.length === 0) return null;

  return (
    <GlassCard className="p-4">
      <div className="flex items-center justify-between mb-3">
        <div className="flex items-center gap-2">
          <Users className="w-4 h-4 text-indigo-500" aria-hidden="true" />
          <h3 className="font-semibold text-sm text-[var(--text-primary)]">
            {t('sidebar.friends.title', 'Friends')}
          </h3>
        </div>
        <Link
          to={tenantPath('/connections')}
          className="text-xs text-indigo-500 hover:text-indigo-600 transition-colors"
        >
          {t('sidebar.friends.see_all', 'See All')}
        </Link>
      </div>

      <div className="space-y-2">
        {friends.map((friend) => (
          <Link
            key={friend.id}
            to={tenantPath(`/profile/${friend.id}`)}
            className="flex items-center gap-3 p-2 rounded-lg hover:bg-[var(--surface-elevated)] transition-colors"
          >
            <div className="relative flex-shrink-0">
              <Avatar
                src={resolveAvatarUrl(friend.avatar_url)}
                name={friend.name}
                size="sm"
              />
              {(friend.is_online || friend.is_recent) && (
                <span
                  className={`absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full border-2 border-[var(--glass-bg)] ${
                    friend.is_online ? 'bg-emerald-500' : 'bg-amber-500'
                  }`}
                  aria-label={friend.is_online ? 'Online' : 'Recently active'}
                />
              )}
            </div>
            <div className="flex-1 min-w-0">
              <p className="text-sm font-medium text-[var(--text-primary)] truncate">
                {friend.name}
              </p>
              {friend.location && (
                <p className="text-xs text-[var(--text-muted)] truncate">
                  {friend.location}
                </p>
              )}
            </div>
          </Link>
        ))}
      </div>
    </GlassCard>
  );
}

export default FriendsWidget;
