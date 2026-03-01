// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Ideation Challenges Page - Browse and discover community challenges
 *
 * Features:
 * - Grid of challenge cards with status chips
 * - Filter tabs: All, Open, Voting, Closed
 * - Create Challenge button (admin only)
 * - Cursor-based pagination
 * - Empty state
 */

import { useState, useEffect, useCallback } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import {
  Button,
  Chip,
  Tabs,
  Tab,
  Spinner,
} from '@heroui/react';
import {
  Lightbulb,
  Plus,
  RefreshCw,
  AlertTriangle,
  Calendar,
  MessageSquarePlus,
  Trophy,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useAuth, useToast, useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

/* ───────────────────────── Types ───────────────────────── */

interface Challenge {
  id: number;
  tenant_id: number;
  user_id: number;
  title: string;
  description: string;
  category: string | null;
  status: 'draft' | 'open' | 'voting' | 'closed';
  ideas_count: number;
  submission_deadline: string | null;
  voting_deadline: string | null;
  prize_description: string | null;
  max_ideas_per_user: number | null;
  created_at: string;
  creator: {
    id: number;
    name: string;
    avatar_url: string | null;
  };
}

type FilterTab = 'all' | 'open' | 'voting' | 'closed';

const STATUS_COLOR_MAP: Record<string, 'default' | 'success' | 'warning' | 'danger'> = {
  draft: 'default',
  open: 'success',
  voting: 'warning',
  closed: 'danger',
};

/* ───────────────────────── Main Component ───────────────────────── */

export function IdeationPage() {
  const { t } = useTranslation('ideation');
  usePageTitle(t('page_title'));
  const { user } = useAuth();
  const { tenantPath } = useTenant();
  const toast = useToast();
  const navigate = useNavigate();

  const [challenges, setChallenges] = useState<Challenge[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [activeTab, setActiveTab] = useState<FilterTab>('all');
  const [hasMore, setHasMore] = useState(false);
  const [cursor, setCursor] = useState<string | undefined>();
  const [isLoadingMore, setIsLoadingMore] = useState(false);

  const isAdmin = user?.role && ['admin', 'tenant_admin', 'tenant_super_admin', 'super_admin'].includes(user.role);

  const fetchChallenges = useCallback(async (tab: FilterTab, loadMore = false) => {
    try {
      if (loadMore) {
        setIsLoadingMore(true);
      } else {
        setIsLoading(true);
        setError(null);
      }

      const params = new URLSearchParams();
      params.set('per_page', '20');
      if (tab !== 'all') {
        params.set('status', tab);
      }
      if (loadMore && cursor) {
        params.set('cursor', cursor);
      }

      const response = await api.get<Challenge[]>(`/v2/ideation-challenges?${params}`);

      if (response.success && response.data) {
        const items = Array.isArray(response.data) ? response.data : [];
        if (loadMore) {
          setChallenges(prev => [...prev, ...items]);
        } else {
          setChallenges(items);
        }
        setHasMore(response.meta?.has_more ?? false);
        setCursor(response.meta?.cursor ?? undefined);
      } else {
        if (!loadMore) setError(t('challenges.load_error'));
      }
    } catch (err) {
      logError('Failed to fetch challenges', err);
      if (!loadMore) {
        setError(t('challenges.load_error'));
      } else {
        toast.error(t('challenges.load_error'));
      }
    } finally {
      setIsLoading(false);
      setIsLoadingMore(false);
    }
  }, [cursor, t, toast]);

  useEffect(() => {
    setCursor(undefined);
    fetchChallenges(activeTab);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [activeTab]);

  const handleTabChange = (key: React.Key) => {
    setActiveTab(key as FilterTab);
  };

  const truncate = (text: string, maxLength: number) => {
    if (text.length <= maxLength) return text;
    return text.slice(0, maxLength).trimEnd() + '...';
  };

  const formatDate = (dateStr: string | null) => {
    if (!dateStr) return null;
    try {
      return new Date(dateStr).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
      });
    } catch {
      return dateStr;
    }
  };

  return (
    <div className="max-w-6xl mx-auto px-4 py-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
          <h1 className="text-2xl font-bold text-[var(--color-text)]">
            {t('title')}
          </h1>
          <p className="text-sm text-[var(--color-text-secondary)] mt-1">
            {t('subtitle')}
          </p>
        </div>

        {isAdmin && (
          <Button
            color="primary"
            startContent={<Plus className="w-4 h-4" />}
            onPress={() => navigate(tenantPath('/ideation/create'))}
          >
            {t('challenges.create')}
          </Button>
        )}
      </div>

      {/* Filter Tabs */}
      <div className="mb-6">
        <Tabs
          selectedKey={activeTab}
          onSelectionChange={handleTabChange}
          variant="underlined"
          color="primary"
          aria-label={t('tabs.all')}
        >
          <Tab key="all" title={t('tabs.all')} />
          <Tab key="open" title={t('tabs.open')} />
          <Tab key="voting" title={t('tabs.voting')} />
          <Tab key="closed" title={t('tabs.closed')} />
        </Tabs>
      </div>

      {/* Loading */}
      {isLoading && (
        <div className="flex justify-center py-12">
          <Spinner size="lg" />
        </div>
      )}

      {/* Error */}
      {error && !isLoading && (
        <EmptyState
          icon={<AlertTriangle className="w-10 h-10 text-theme-subtle" />}
          title={t('challenges.load_error')}
          description={error}
          action={
            <Button
              color="primary"
              variant="flat"
              startContent={<RefreshCw className="w-4 h-4" />}
              onPress={() => fetchChallenges(activeTab)}
            >
              {t('ideas.load_more')}
            </Button>
          }
        />
      )}

      {/* Empty State */}
      {!isLoading && !error && challenges.length === 0 && (
        <EmptyState
          icon={<Lightbulb className="w-10 h-10 text-theme-subtle" />}
          title={t('challenges.empty_title')}
          description={activeTab === 'all' ? t('challenges.empty_description') : t('challenges.empty_filtered')}
        />
      )}

      {/* Challenge Grid */}
      {!isLoading && !error && challenges.length > 0 && (
        <>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {challenges.map((challenge) => (
              <Link
                key={challenge.id}
                to={tenantPath(`/ideation/${challenge.id}`)}
                className="block"
              >
                <GlassCard className="p-5 h-full hover:shadow-lg transition-shadow cursor-pointer">
                  <div className="flex items-start justify-between gap-3 mb-3">
                    <h3 className="text-lg font-semibold text-[var(--color-text)] line-clamp-2">
                      {challenge.title}
                    </h3>
                    <Chip
                      size="sm"
                      color={STATUS_COLOR_MAP[challenge.status] ?? 'default'}
                      variant="flat"
                    >
                      {t(`status.${challenge.status}`)}
                    </Chip>
                  </div>

                  <p className="text-sm text-[var(--color-text-secondary)] mb-4 line-clamp-3">
                    {truncate(challenge.description, 150)}
                  </p>

                  <div className="flex flex-wrap items-center gap-3 text-xs text-[var(--color-text-tertiary)]">
                    {/* Ideas count */}
                    <span className="flex items-center gap-1">
                      <MessageSquarePlus className="w-3.5 h-3.5" />
                      {t('challenge.ideas_count', { count: challenge.ideas_count })}
                    </span>

                    {/* Submission deadline */}
                    {challenge.submission_deadline && (
                      <span className="flex items-center gap-1">
                        <Calendar className="w-3.5 h-3.5" />
                        {t('challenge.submission_deadline', { date: formatDate(challenge.submission_deadline) })}
                      </span>
                    )}

                    {/* Prize indicator */}
                    {challenge.prize_description && (
                      <span className="flex items-center gap-1">
                        <Trophy className="w-3.5 h-3.5 text-amber-500" />
                        {t('challenge.prize')}
                      </span>
                    )}

                    {/* Category */}
                    {challenge.category && (
                      <Chip size="sm" variant="flat" className="text-xs">
                        {challenge.category}
                      </Chip>
                    )}
                  </div>
                </GlassCard>
              </Link>
            ))}
          </div>

          {/* Load More */}
          {hasMore && (
            <div className="flex justify-center mt-6">
              <Button
                variant="flat"
                isLoading={isLoadingMore}
                onPress={() => fetchChallenges(activeTab, true)}
              >
                {t('challenges.load_more')}
              </Button>
            </div>
          )}
        </>
      )}
    </div>
  );
}

export default IdeationPage;
