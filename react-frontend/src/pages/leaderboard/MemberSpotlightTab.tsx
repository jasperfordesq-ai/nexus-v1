// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * MemberSpotlightTab — daily rotating spotlight of active community members.
 *
 * Randomly features active members rather than always surfacing top earners.
 * Uses date-seeded rotation so the same members show all day.
 */

import { useState, useEffect, useRef } from 'react';
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Avatar, Skeleton } from '@heroui/react';
import { Sparkles, ChevronRight } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl } from '@/lib/helpers';

interface SpotlightMember {
  id: number;
  first_name: string;
  last_name: string;
  avatar_url: string | null;
  bio: string | null;
  member_since: string | null;
  recent_activity: string;
}

export default function MemberSpotlightTab() {
  const { t } = useTranslation('gamification');
  const { tenantPath } = useTenant();
  const [members, setMembers] = useState<SpotlightMember[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const abortRef = useRef<AbortController | null>(null);

  useEffect(() => {
    const controller = new AbortController();
    abortRef.current = controller;

    const load = async () => {
      try {
        setIsLoading(true);
        const res = await api.get<SpotlightMember[]>('/v2/gamification/member-spotlight?limit=6', {
          signal: controller.signal,
          timeout: 60000,
        });
        if (controller.signal.aborted) return;
        if (res.success && res.data) {
          setMembers(Array.isArray(res.data) ? res.data : []);
        } else if (!res.success) {
          logError('MemberSpotlightTab', res.error || 'Failed to load spotlight');
        }
      } catch (err: unknown) {
        if (err instanceof Error && err.name !== 'AbortError') {
          logError('MemberSpotlightTab', err);
        }
      } finally {
        setIsLoading(false);
      }
    };

    load();
    return () => controller.abort();
  }, []);

  if (isLoading) {
    return (
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {[...Array(3)].map((_, i) => (
          <GlassCard key={i} className="p-6">
            <div className="flex items-center gap-3 mb-3">
              <Skeleton className="w-12 h-12 rounded-full" />
              <div>
                <Skeleton className="h-4 w-24 mb-1 rounded" />
                <Skeleton className="h-3 w-16 rounded" />
              </div>
            </div>
            <Skeleton className="h-12 w-full rounded" />
          </GlassCard>
        ))}
      </div>
    );
  }

  if (members.length === 0) {
    return (
      <EmptyState
        icon={<Sparkles className="w-12 h-12" />}
        title={t('spotlight.empty_title', 'No Spotlight Yet')}
        description={t(
          'spotlight.empty_description',
          'Active members will be featured here once the community starts exchanging.'
        )}
      />
    );
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-2 mb-2">
        <Sparkles className="w-5 h-5 text-amber-500" />
        <p className="text-sm text-default-500">
          {t('spotlight.description', "Today's featured active community members")}
        </p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {members.map((member, i) => (
          <motion.div
            key={member.id}
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: i * 0.1 }}
          >
            <Link to={tenantPath(`/members/${member.id}`)}>
              <GlassCard className="p-6 hover:ring-1 hover:ring-primary-500/30 transition-all cursor-pointer">
                <div className="flex items-center gap-3 mb-3">
                  <Avatar
                    src={resolveAvatarUrl(member.avatar_url)}
                    name={`${member.first_name} ${member.last_name}`}
                    size="lg"
                    className="ring-2 ring-amber-500/30"
                  />
                  <div className="flex-1 min-w-0">
                    <p className="font-semibold truncate">
                      {member.first_name} {member.last_name}
                    </p>
                    {member.member_since && (
                      <p className="text-xs text-default-400">
                        {t('spotlight.member_since', 'Member since')} {member.member_since}
                      </p>
                    )}
                  </div>
                  <ChevronRight className="w-4 h-4 text-default-300 flex-shrink-0" />
                </div>

                <p className="text-sm text-primary-500 font-medium">
                  {member.recent_activity}
                </p>

                {member.bio && (
                  <p className="text-xs text-default-400 mt-2 line-clamp-2">
                    {member.bio}
                  </p>
                )}
              </GlassCard>
            </Link>
          </motion.div>
        ))}
      </div>
    </div>
  );
}
