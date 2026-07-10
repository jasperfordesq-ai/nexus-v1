// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Avatar } from '@/components/ui/Avatar';
import { Button } from '@/components/ui/Button';
import { Chip } from '@/components/ui/Chip';
import { GlassCard } from '@/components/ui/GlassCard';
/**
 * RecommendedGroups — authed-only horizontal strip of the top Smart
 * Matching group suggestions, shown above the main groups grid. Renders
 * null on loading error or when there's nothing to show, so it never
 * disrupts the page for tenants/backends without matching data.
 */

import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import Users from 'lucide-react/icons/users';
import Sparkles from 'lucide-react/icons/sparkles';
import { useAuth } from '@/contexts/AuthContext';
import { useToast } from '@/contexts/ToastContext';
import { useTenant } from '@/contexts/TenantContext';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveThumbnailUrl } from '@/lib/helpers';

interface GroupMatch {
  module: string;
  group_id?: number;
  title: string;
  match_score: number;
  match_reasons?: string[];
  image_url?: string | null;
}

export function RecommendedGroups() {
  const { t } = useTranslation('groups');
  const { isAuthenticated } = useAuth();
  const { tenantPath } = useTenant();
  const toast = useToast();

  const [groups, setGroups] = useState<GroupMatch[]>([]);
  const [loading, setLoading] = useState(true);
  const [hasError, setHasError] = useState(false);
  const [joinedIds, setJoinedIds] = useState<Set<number>>(new Set());
  const [joiningId, setJoiningId] = useState<number | null>(null);

  useEffect(() => {
    if (!isAuthenticated) {
      setLoading(false);
      return;
    }

    let cancelled = false;
    (async () => {
      try {
        const res = await api.get<{ matches?: GroupMatch[] }>('/v2/matches/all?modules=groups&limit=3&min_score=50');
        if (cancelled) return;
        if (res.success && Array.isArray(res.data?.matches)) {
          setGroups(res.data.matches.filter((m) => m.group_id != null).slice(0, 3));
        } else {
          setHasError(true);
        }
      } catch (err) {
        if (!cancelled) {
          logError('RecommendedGroups.load', err);
          setHasError(true);
        }
      }
      if (!cancelled) setLoading(false);
    })();

    return () => { cancelled = true; };
  }, [isAuthenticated]);

  const handleJoin = async (groupId: number) => {
    setJoiningId(groupId);
    try {
      const res = await api.post(`/v2/groups/${groupId}/join`, {});
      if (res.success) {
        setJoinedIds((prev) => new Set(prev).add(groupId));
        toast.success(t('recommended.joined'));
      } else {
        toast.error(res.error || t('unable_to_load'));
      }
    } catch (err) {
      logError('RecommendedGroups.join', err);
      toast.error(t('unable_to_load'));
    }
    setJoiningId(null);
  };

  if (!isAuthenticated || loading || hasError || groups.length === 0) return null;

  return (
    <div className="space-y-3">
      <h2 className="flex items-center gap-2 text-sm font-semibold text-theme-primary">
        <Sparkles className="w-4 h-4 text-accent" aria-hidden="true" />
        {t('recommended.title')}
      </h2>
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
        {groups.map((group) => {
          const hasJoined = group.group_id != null && joinedIds.has(group.group_id);
          const firstReason = Array.isArray(group.match_reasons) ? group.match_reasons[0] : undefined;
          return (
            <GlassCard key={group.group_id} className="p-3 flex flex-col gap-2">
              <div className="flex items-center gap-2 min-w-0">
                <Avatar
                  src={resolveThumbnailUrl(group.image_url, { width: 96, height: 96 })}
                  name={group.title}
                  size="sm"
                  className="w-8 h-8"
                  radius="md"
                />
                <div className="min-w-0 flex-1">
                  <p className="text-sm font-medium text-theme-primary truncate">{group.title}</p>
                  <Chip size="sm" variant="flat" className="h-4 text-[10px]" startContent={<Users className="w-2.5 h-2.5" aria-hidden="true" />}>
                    {t('recommended.score_chip', { score: group.match_score })}
                  </Chip>
                </div>
              </div>
              {firstReason && (
                <p className="text-xs text-theme-subtle line-clamp-2">{firstReason}</p>
              )}
              <div className="flex items-center gap-2 mt-auto pt-1">
                <Button
                  as={Link}
                  to={tenantPath(`/groups/${group.group_id}`)}
                  size="sm"
                  variant="tertiary"
                  className="bg-theme-elevated text-theme-primary flex-1"
                >
                  {t('recommended.view')}
                </Button>
                {!hasJoined && group.group_id != null && (
                  <Button
                    size="sm"
                    className="bg-gradient-to-r from-accent to-accent-gradient-end text-white flex-1"
                    isLoading={joiningId === group.group_id}
                    onPress={() => handleJoin(group.group_id!)}
                  >
                    {t('recommended.join')}
                  </Button>
                )}
              </div>
            </GlassCard>
          );
        })}
      </div>
    </div>
  );
}

export default RecommendedGroups;
