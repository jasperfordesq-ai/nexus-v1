// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Group Exchanges Page - List view showing user's group exchanges
 *
 * Features:
 * - Tabs: All, Active, Completed, Cancelled
 * - Exchange cards with title, status chip, participant count, total hours
 * - Click to navigate to detail page
 * - Loading skeleton, empty state
 * - "New Exchange" button links to creation wizard
 *
 * Route: /group-exchanges
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { motion } from 'framer-motion';
import {
  Button,
  Chip,
  Tabs,
  Tab,
  Avatar,
} from '@heroui/react';
import {
  Plus,
  Users,
  Clock,
  ArrowLeftRight,
  Calendar,
  RefreshCw,
  AlertTriangle,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { usePageTitle } from '@/hooks';
import { useAuth, useTenant, useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl } from '@/lib/helpers';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface GroupExchange {
  id: number;
  title: string;
  description: string | null;
  organizer_id: number;
  organizer_name: string;
  organizer_avatar: string | null;
  status: GroupExchangeStatus;
  split_type: 'equal' | 'custom' | 'weighted';
  total_hours: number;
  participant_count: number;
  created_at: string;
  updated_at: string;
  completed_at: string | null;
}

type GroupExchangeStatus =
  | 'draft'
  | 'pending_participants'
  | 'pending_broker'
  | 'active'
  | 'pending_confirmation'
  | 'completed'
  | 'cancelled'
  | 'disputed';

interface StatusConfig {
  label: string;
  color: 'default' | 'warning' | 'secondary' | 'primary' | 'success' | 'danger';
}

const STATUS_CONFIGS: Record<GroupExchangeStatus, StatusConfig> = {
  draft: { label: 'Draft', color: 'default' },
  pending_participants: { label: 'Pending Participants', color: 'warning' },
  pending_broker: { label: 'Pending Broker', color: 'secondary' },
  active: { label: 'Active', color: 'primary' },
  pending_confirmation: { label: 'Pending Confirmation', color: 'warning' },
  completed: { label: 'Completed', color: 'success' },
  cancelled: { label: 'Cancelled', color: 'danger' },
  disputed: { label: 'Disputed', color: 'danger' },
};

const ITEMS_PER_PAGE = 20;

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function GroupExchangesPage() {
  usePageTitle('Group Exchanges');
  const { user } = useAuth();
  const { tenantPath } = useTenant();
  const toast = useToast();
  const [searchParams, setSearchParams] = useSearchParams();

  const [exchanges, setExchanges] = useState<GroupExchange[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [hasMore, setHasMore] = useState(true);
  const [selectedTab, setSelectedTab] = useState(searchParams.get('status') || 'all');

  const abortControllerRef = useRef<AbortController | null>(null);

  // Load group exchanges
  const loadExchanges = useCallback(async (append = false) => {
    if (abortControllerRef.current) {
      abortControllerRef.current.abort();
    }
    abortControllerRef.current = new AbortController();

    try {
      if (!append) {
        setIsLoading(true);
        setError(null);
      } else {
        setIsLoadingMore(true);
      }

      const offset = append ? exchanges.length : 0;
      const statusFilter = selectedTab !== 'all' ? `&status=${selectedTab}` : '';
      const url = `/v2/group-exchanges?limit=${ITEMS_PER_PAGE}&offset=${offset}${statusFilter}`;

      const response = await api.get<{ data: GroupExchange[]; has_more: boolean }>(url);

      if (abortControllerRef.current?.signal.aborted) return;

      if (response.success && response.data) {
        const items = Array.isArray(response.data) ? response.data : [];
        const more = response.meta?.has_more ?? items.length >= ITEMS_PER_PAGE;

        if (append) {
          setExchanges((prev) => [...prev, ...items]);
        } else {
          setExchanges(items);
        }
        setHasMore(more);
      } else {
        if (!append) {
          setError('Failed to load group exchanges. Please try again.');
        } else {
          toast.error('Failed to load more exchanges');
        }
      }
    } catch (err) {
      if (err instanceof Error && err.name === 'AbortError') return;
      logError('Failed to load group exchanges', err);
      if (!append) {
        setError('Failed to load group exchanges. Please try again.');
      } else {
        toast.error('Failed to load more exchanges');
      }
    } finally {
      setIsLoading(false);
      setIsLoadingMore(false);
    }
  }, [selectedTab, exchanges.length, toast]);

  const loadMore = useCallback(() => {
    if (isLoadingMore || !hasMore) return;
    loadExchanges(true);
  }, [isLoadingMore, hasMore, loadExchanges]);

  // Reload on tab change
  useEffect(() => {
    loadExchanges();
    return () => {
      if (abortControllerRef.current) {
        abortControllerRef.current.abort();
      }
    };
  }, [selectedTab]); // eslint-disable-line react-hooks/exhaustive-deps

  function handleTabChange(key: string | number) {
    setSelectedTab(key.toString());
    setSearchParams({ status: key.toString() });
    setHasMore(true);
  }

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      className="space-y-6"
    >
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl sm:text-3xl font-bold text-theme-primary">
            Group Exchanges
          </h1>
          <p className="text-theme-muted mt-1">
            Multi-participant exchanges with split types and confirmations
          </p>
        </div>
        <Link to={tenantPath('/group-exchanges/create')}>
          <Button
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
            startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
          >
            New Exchange
          </Button>
        </Link>
      </div>

      {/* Tabs */}
      <GlassCard className="p-2">
        <Tabs
          selectedKey={selectedTab}
          onSelectionChange={handleTabChange}
          variant="light"
          aria-label="Group exchange status filter"
          classNames={{
            tabList: 'gap-2',
            tab: 'px-4 py-2',
          }}
        >
          <Tab key="all" title="All" aria-label="Show all group exchanges" />
          <Tab key="active" title="Active" aria-label="Show active group exchanges" />
          <Tab key="pending_confirmation" title="Needs Confirmation" aria-label="Show exchanges needing confirmation" />
          <Tab key="completed" title="Completed" aria-label="Show completed group exchanges" />
          <Tab key="cancelled" title="Cancelled" aria-label="Show cancelled group exchanges" />
        </Tabs>
      </GlassCard>

      {/* Error State */}
      {error && !isLoading && (
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <h3 className="text-lg font-semibold text-theme-primary mb-2">Unable to Load Exchanges</h3>
          <p className="text-theme-muted mb-4">{error}</p>
          <Button
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
            startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
            onPress={() => loadExchanges()}
          >
            Try Again
          </Button>
        </GlassCard>
      )}

      {/* Exchange List */}
      {!error && (
        <>
          {isLoading ? (
            <div className="space-y-4">
              {[1, 2, 3, 4].map((i) => (
                <GlassCard key={i} className="p-6 animate-pulse">
                  <div className="flex items-center gap-4">
                    <div className="w-12 h-12 rounded-full bg-theme-elevated" />
                    <div className="flex-1 space-y-2">
                      <div className="h-5 w-48 bg-theme-elevated rounded" />
                      <div className="h-4 w-32 bg-theme-elevated rounded" />
                    </div>
                    <div className="h-6 w-20 bg-theme-elevated rounded-full" />
                  </div>
                </GlassCard>
              ))}
            </div>
          ) : exchanges.length === 0 ? (
            <EmptyState
              icon={<ArrowLeftRight className="w-12 h-12" />}
              title="No Group Exchanges Found"
              description={
                selectedTab === 'all'
                  ? "You haven't created or joined any group exchanges yet."
                  : 'No group exchanges match this filter.'
              }
              action={
                <Link to={tenantPath('/group-exchanges/create')}>
                  <Button className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white">
                    Create Group Exchange
                  </Button>
                </Link>
              }
            />
          ) : (
            <div className="space-y-4">
              {exchanges.map((exchange) => {
                const statusConfig = STATUS_CONFIGS[exchange.status] || STATUS_CONFIGS.draft;
                const isOrganizer = exchange.organizer_id === user?.id;

                return (
                  <Link key={exchange.id} to={tenantPath(`/group-exchanges/${exchange.id}`)}>
                    <article
                      className="block"
                      aria-label={`Group exchange: ${exchange.title} - ${statusConfig.label}`}
                    >
                      <GlassCard className="p-4 sm:p-6 hover:border-indigo-500/30 transition-colors cursor-pointer">
                        <div className="flex flex-col sm:flex-row sm:items-center gap-4">
                          {/* Organizer Avatar */}
                          <Avatar
                            src={resolveAvatarUrl(exchange.organizer_avatar)}
                            name={exchange.organizer_name || 'Unknown'}
                            size="lg"
                            className="flex-shrink-0"
                          />

                          {/* Exchange Info */}
                          <div className="flex-1 min-w-0">
                            <div className="flex flex-wrap items-center gap-2 mb-1">
                              <h3 className="font-semibold text-theme-primary truncate">
                                {exchange.title}
                              </h3>
                              <Chip
                                size="sm"
                                color={statusConfig.color}
                                variant="flat"
                              >
                                {statusConfig.label}
                              </Chip>
                            </div>

                            <div className="flex flex-wrap items-center gap-4 text-sm text-theme-muted">
                              <span className="flex items-center gap-1">
                                <Users className="w-4 h-4" aria-hidden="true" />
                                {exchange.participant_count} participant{exchange.participant_count !== 1 ? 's' : ''}
                              </span>
                              <span className="flex items-center gap-1">
                                <Clock className="w-4 h-4" aria-hidden="true" />
                                {Number(exchange.total_hours)} hour{Number(exchange.total_hours) !== 1 ? 's' : ''}
                              </span>
                              <span className="flex items-center gap-1">
                                <Calendar className="w-4 h-4" aria-hidden="true" />
                                <time dateTime={exchange.created_at}>
                                  {new Date(exchange.created_at).toLocaleDateString()}
                                </time>
                              </span>
                            </div>

                            {/* Role indicator */}
                            <div className="mt-2">
                              <span className={`
                                text-xs px-2 py-1 rounded-full
                                ${isOrganizer
                                  ? 'bg-indigo-500/20 text-indigo-400'
                                  : 'bg-emerald-500/20 text-emerald-400'}
                              `}>
                                {isOrganizer ? 'You organized' : 'Participant'}
                              </span>
                            </div>
                          </div>

                          {/* Split type badge */}
                          <div className="flex-shrink-0">
                            <Chip size="sm" variant="flat" className="bg-theme-elevated text-theme-muted capitalize">
                              {exchange.split_type} split
                            </Chip>
                          </div>
                        </div>
                      </GlassCard>
                    </article>
                  </Link>
                );
              })}

              {/* Load More Button */}
              {hasMore && (
                <div className="pt-4 text-center">
                  <Button
                    variant="flat"
                    className="bg-theme-elevated text-theme-muted"
                    onPress={loadMore}
                    isLoading={isLoadingMore}
                  >
                    Load More
                  </Button>
                </div>
              )}
            </div>
          )}
        </>
      )}
    </motion.div>
  );
}

export default GroupExchangesPage;
