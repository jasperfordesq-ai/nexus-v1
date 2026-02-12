/**
 * Exchanges Page - View and manage exchange requests
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Avatar, Chip, Tabs, Tab } from '@heroui/react';
import {
  ArrowRightLeft,
  Clock,
  Calendar,
  User,
  Plus,
  RefreshCw,
  AlertTriangle,
} from 'lucide-react';
import { GlassCard, ExchangeCardSkeleton } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useAuth, useToast, useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl } from '@/lib/helpers';
import { EXCHANGE_STATUS_CONFIG } from '@/lib/exchange-status';
import type { Exchange, ExchangeConfig } from '@/types/api';

const ITEMS_PER_PAGE = 20;

export function ExchangesPage() {
  usePageTitle('Exchanges');
  const { user } = useAuth();
  const { tenantPath } = useTenant();
  const toast = useToast();
  const [searchParams, setSearchParams] = useSearchParams();

  const [exchanges, setExchanges] = useState<Exchange[]>([]);
  const [config, setConfig] = useState<ExchangeConfig | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [hasMore, setHasMore] = useState(true);
  const [selectedTab, setSelectedTab] = useState(searchParams.get('status') || 'active');

  // Refs for race condition prevention
  const abortControllerRef = useRef<AbortController | null>(null);
  const configLoadedRef = useRef(false);

  // Load config once on mount
  const loadConfig = useCallback(async () => {
    if (configLoadedRef.current) return;

    try {
      const response = await api.get<ExchangeConfig>('/v2/exchanges/config');
      if (response.success && response.data) {
        setConfig(response.data);
        configLoadedRef.current = true;
      }
    } catch (err) {
      logError('Failed to load exchange config', err);
    }
  }, []);

  // Load exchanges with abort controller for race condition prevention
  const loadExchanges = useCallback(async (append = false) => {
    // Cancel previous request
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
      const queryString = selectedTab !== 'all'
        ? `?status=${selectedTab}&limit=${ITEMS_PER_PAGE}&offset=${offset}`
        : `?limit=${ITEMS_PER_PAGE}&offset=${offset}`;

      const response = await api.get<Exchange[]>(`/v2/exchanges${queryString}`);

      // Check if request was aborted
      if (abortControllerRef.current?.signal.aborted) return;

      if (response.success && response.data) {
        if (append) {
          setExchanges((prev) => [...prev, ...response.data!]);
        } else {
          setExchanges(response.data);
        }
        setHasMore(response.data.length >= ITEMS_PER_PAGE);
      } else {
        if (!append) {
          setError('Failed to load exchanges. Please try again.');
        } else {
          toast.error('Failed to load more exchanges');
        }
      }
    } catch (err) {
      // Ignore abort errors
      if (err instanceof Error && err.name === 'AbortError') return;

      logError('Failed to load exchanges', err);
      if (!append) {
        setError('Failed to load exchanges. Please try again.');
      } else {
        toast.error('Failed to load more exchanges');
      }
    } finally {
      setIsLoading(false);
      setIsLoadingMore(false);
    }
  }, [selectedTab, exchanges.length, toast]);

  // Load more exchanges
  const loadMoreExchanges = useCallback(() => {
    if (isLoadingMore || !hasMore) return;
    loadExchanges(true);
  }, [isLoadingMore, hasMore, loadExchanges]);

  // Load config on mount
  useEffect(() => {
    loadConfig();
  }, [loadConfig]);

  // Load exchanges on tab change
  useEffect(() => {
    loadExchanges();
    // Cleanup on unmount
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

  const isRequester = (exchange: Exchange) => exchange.requester_id === user?.id;
  const isProvider = (exchange: Exchange) => exchange.provider_id === user?.id;
  const otherParty = (exchange: Exchange) =>
    isRequester(exchange) ? exchange.provider : exchange.requester;

  // Show empty state if exchange workflow is not enabled
  if (configLoadedRef.current && !config?.exchange_workflow_enabled) {
    return (
      <EmptyState
        icon={<ArrowRightLeft className="w-12 h-12" />}
        title="Exchange Workflow Not Enabled"
        description="The exchange workflow system is not enabled for this community. Contact your coordinator for more information."
        action={
          <Link to={tenantPath("/listings")}>
            <Button className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white">
              Browse Listings
            </Button>
          </Link>
        }
      />
    );
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
            My Exchanges
          </h1>
          <p className="text-theme-muted mt-1">
            Track your service exchange requests and confirmations
          </p>
        </div>
        <Link to={tenantPath("/listings")}>
          <Button
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
            startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
          >
            Browse Listings
          </Button>
        </Link>
      </div>

      {/* Tabs */}
      <GlassCard className="p-2">
        <Tabs
          selectedKey={selectedTab}
          onSelectionChange={handleTabChange}
          variant="light"
          aria-label="Exchange status filter"
          classNames={{
            tabList: 'gap-2',
            tab: 'px-4 py-2',
          }}
        >
          <Tab key="active" title="Active" aria-label="Show active exchanges" />
          <Tab key="pending_confirmation" title="Needs Confirmation" aria-label="Show exchanges needing confirmation" />
          <Tab key="completed" title="Completed" aria-label="Show completed exchanges" />
          <Tab key="all" title="All" aria-label="Show all exchanges" />
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

      {/* Exchanges List */}
      {!error && (
        <>
          {isLoading ? (
            <div className="space-y-4">
              {[1, 2, 3, 4].map((i) => (
                <ExchangeCardSkeleton key={i} />
              ))}
            </div>
          ) : exchanges.length === 0 ? (
            <EmptyState
              icon={<ArrowRightLeft className="w-12 h-12" />}
              title="No Exchanges Found"
              description={
                selectedTab === 'active'
                  ? "You don't have any active exchanges. Browse listings to request a service."
                  : "No exchanges match this filter."
              }
              action={
                <Link to={tenantPath("/listings")}>
                  <Button className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white">
                    Browse Listings
                  </Button>
                </Link>
              }
            />
          ) : (
            <div className="space-y-4">
              {exchanges.map((exchange) => {
                const statusConfig = EXCHANGE_STATUS_CONFIG[exchange.status];
                const StatusIcon = statusConfig.icon;
                const other = otherParty(exchange);

                return (
                  <Link key={exchange.id} to={tenantPath(`/exchanges/${exchange.id}`)}>
                    <article
                      className="block"
                      aria-label={`Exchange: ${exchange.listing?.title || 'Service Exchange'} - ${statusConfig.label}`}
                    >
                      <GlassCard className="p-4 sm:p-6 hover:border-indigo-500/30 transition-colors cursor-pointer">
                        <div className="flex flex-col sm:flex-row sm:items-center gap-4">
                          {/* Other party avatar */}
                          <Avatar
                            src={resolveAvatarUrl(other?.avatar)}
                            name={other?.name || 'Unknown'}
                            size="lg"
                            className="flex-shrink-0"
                          />

                          {/* Exchange info */}
                          <div className="flex-1 min-w-0">
                            <div className="flex flex-wrap items-center gap-2 mb-1">
                              <h3 className="font-semibold text-theme-primary truncate">
                                {exchange.listing?.title || 'Service Exchange'}
                              </h3>
                              <Chip
                                size="sm"
                                color={statusConfig.color}
                                variant="flat"
                                startContent={<StatusIcon className="w-3 h-3" aria-hidden="true" />}
                              >
                                {statusConfig.label}
                              </Chip>
                            </div>

                            <div className="flex flex-wrap items-center gap-4 text-sm text-theme-muted">
                              <span className="flex items-center gap-1">
                                <User className="w-4 h-4" aria-hidden="true" />
                                {isRequester(exchange) ? 'With ' : 'From '}
                                {other?.name || 'Unknown'}
                              </span>
                              <span className="flex items-center gap-1">
                                <Clock className="w-4 h-4" aria-hidden="true" />
                                {exchange.proposed_hours} hour{exchange.proposed_hours !== 1 ? 's' : ''}
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
                                ${isRequester(exchange)
                                  ? 'bg-amber-500/20 text-amber-400'
                                  : 'bg-emerald-500/20 text-emerald-400'}
                              `}>
                                {isRequester(exchange) ? 'You requested' : 'You are providing'}
                              </span>
                            </div>
                          </div>

                          {/* Action needed indicator */}
                          {exchange.status === 'pending_confirmation' && (
                            <div className="flex-shrink-0">
                              {(isRequester(exchange) && !exchange.requester_confirmed_at) ||
                               (isProvider(exchange) && !exchange.provider_confirmed_at) ? (
                                <Chip color="warning" variant="flat">
                                  Confirm Hours
                                </Chip>
                              ) : (
                                <Chip color="default" variant="flat">
                                  Waiting for other
                                </Chip>
                              )}
                            </div>
                          )}

                          {exchange.status === 'pending_provider' && isProvider(exchange) && (
                            <Chip color="warning" variant="flat">
                              Respond
                            </Chip>
                          )}
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
                    onPress={loadMoreExchanges}
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

export default ExchangesPage;
