// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * TopEndorsedWidget - Leaderboard of most endorsed members
 *
 * Displays a compact list of top endorsed community members.
 * Can be used as a sidebar widget or standalone section.
 */

import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { Avatar, Chip, Spinner } from '@heroui/react';
import { Trophy, ThumbsUp, Medal } from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl } from '@/lib/helpers';

interface TopEndorsedMember {
  id: number;
  name: string;
  avatar_url?: string;
  total_endorsements: number;
  top_skills: string[];
}

export function TopEndorsedWidget({ limit = 5 }: { limit?: number }) {
  const { tenantPath } = useTenant();
  const [members, setMembers] = useState<TopEndorsedMember[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const loadTopEndorsed = async () => {
      try {
        setIsLoading(true);
        const response = await api.get<TopEndorsedMember[]>(`/v2/members/top-endorsed?limit=${limit}`);
        if (response.success && response.data) {
          setMembers(response.data);
        }
      } catch (err) {
        logError('Failed to load top endorsed members', err);
      } finally {
        setIsLoading(false);
      }
    };
    loadTopEndorsed();
  }, [limit]);

  if (isLoading) {
    return (
      <GlassCard className="p-4">
        <div className="flex justify-center py-4">
          <Spinner size="sm" />
        </div>
      </GlassCard>
    );
  }

  if (members.length === 0) return null;

  const rankIcons = [
    <Medal key="gold" className="w-5 h-5 text-amber-500" aria-hidden="true" />,
    <Medal key="silver" className="w-5 h-5 text-gray-400" aria-hidden="true" />,
    <Medal key="bronze" className="w-5 h-5 text-amber-700" aria-hidden="true" />,
  ];

  return (
    <GlassCard className="p-4">
      <div className="flex items-center gap-2 mb-4">
        <Trophy className="w-5 h-5 text-amber-500" aria-hidden="true" />
        <h3 className="font-semibold text-theme-primary text-sm">Most Endorsed</h3>
      </div>

      <div className="space-y-3">
        {members.map((member, index) => (
          <Link
            key={member.id}
            to={tenantPath(`/profile/${member.id}`)}
            className="flex items-center gap-3 p-2 rounded-lg hover:bg-theme-hover transition-colors"
          >
            {/* Rank */}
            <div className="w-6 flex-shrink-0 flex items-center justify-center">
              {index < 3 ? (
                rankIcons[index]
              ) : (
                <span className="text-xs font-bold text-theme-subtle">#{index + 1}</span>
              )}
            </div>

            {/* Avatar */}
            <Avatar
              src={resolveAvatarUrl(member.avatar_url)}
              name={member.name}
              size="sm"
              className="ring-2 ring-theme-muted/20"
            />

            {/* Info */}
            <div className="flex-1 min-w-0">
              <p className="text-sm font-medium text-theme-primary truncate">{member.name}</p>
              {member.top_skills.length > 0 && (
                <p className="text-xs text-theme-subtle truncate">
                  {member.top_skills.slice(0, 2).join(', ')}
                </p>
              )}
            </div>

            {/* Count */}
            <Chip
              size="sm"
              variant="flat"
              className="bg-indigo-500/10 text-indigo-600 dark:text-indigo-400"
              startContent={<ThumbsUp className="w-3 h-3" aria-hidden="true" />}
            >
              {member.total_endorsements}
            </Chip>
          </Link>
        ))}
      </div>
    </GlassCard>
  );
}

export default TopEndorsedWidget;
