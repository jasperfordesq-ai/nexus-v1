/**
 * Federation Events Page - Browse events from partner communities
 *
 * Features:
 * - Search input with debounce
 * - Partner community Select dropdown
 * - Upcoming-only toggle via Chip
 * - Card list layout (not grid) with date box fallback
 * - Cursor-based pagination with Load More
 * - Loading skeletons and error states
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { useSearchParams } from 'react-router-dom';
import { motion } from 'framer-motion';
import {
  Button,
  Input,
  Select,
  SelectItem,
  Chip,
  Avatar,
} from '@heroui/react';
import {
  Search,
  Globe,
  Calendar,
  MapPin,
  Users,
  Clock,
  AlertTriangle,
  RefreshCw,
  Wifi,
  ChevronRight,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { Breadcrumbs } from '@/components/navigation';
import { EmptyState } from '@/components/feedback';
import { useToast } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl, resolveAssetUrl } from '@/lib/helpers';
import type { FederatedEvent, FederationPartner } from '@/types/api';

const SEARCH_DEBOUNCE_MS = 300;
const PER_PAGE = 20;

export function FederationEventsPage() {
  usePageTitle('Federated Events');
  const toast = useToast();
  const [searchParams, setSearchParams] = useSearchParams();

  // Data
  const [events, setEvents] = useState<FederatedEvent[]>([]);
  const [partners, setPartners] = useState<FederationPartner[]>([]);

  // State
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [cursor, setCursor] = useState<string | null>(null);
  const [hasMore, setHasMore] = useState(false);

  // Filters
  const [searchQuery, setSearchQuery] = useState(searchParams.get('q') || '');
  const [debouncedQuery, setDebouncedQuery] = useState(searchQuery);
  const [selectedPartner, setSelectedPartner] = useState(
    searchParams.get('partner_id') || ''
  );
  const [upcomingOnly, setUpcomingOnly] = useState(
    searchParams.get('upcoming') !== 'false'
  );

  const searchTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // ── Debounce search ──────────────────────────────────────────────────────
  useEffect(() => {
    if (searchTimeoutRef.current) clearTimeout(searchTimeoutRef.current);
    searchTimeoutRef.current = setTimeout(() => {
      setDebouncedQuery(searchQuery);
    }, SEARCH_DEBOUNCE_MS);

    return () => {
      if (searchTimeoutRef.current) clearTimeout(searchTimeoutRef.current);
    };
  }, [searchQuery]);

  // ── Load partners for dropdown ───────────────────────────────────────────
  const loadPartners = useCallback(async () => {
    try {
      const response = await api.get<FederationPartner[]>('/v2/federation/partners');
      if (response.success && response.data) {
        setPartners(response.data);
      }
    } catch (error) {
      logError('Failed to load federation partners for filter', error);
    }
  }, []);

  useEffect(() => {
    loadPartners();
  }, [loadPartners]);

  // ── Load events ──────────────────────────────────────────────────────────
  const loadEvents = useCallback(
    async (append = false) => {
      try {
        if (!append) {
          setIsLoading(true);
          setLoadError(null);
        } else {
          setIsLoadingMore(true);
        }

        const params = new URLSearchParams();
        if (debouncedQuery) params.set('q', debouncedQuery);
        if (selectedPartner) params.set('partner_id', selectedPartner);
        if (upcomingOnly) params.set('upcoming', 'true');
        if (append && cursor) params.set('cursor', cursor);
        params.set('per_page', String(PER_PAGE));

        const response = await api.get<FederatedEvent[]>(
          `/v2/federation/events?${params}`
        );

        if (response.success && response.data) {
          if (append) {
            setEvents((prev) => [...prev, ...response.data!]);
          } else {
            setEvents(response.data);
          }
          const nextCursor = response.meta?.cursor ?? response.meta?.next_cursor ?? null;
          setCursor(nextCursor);
          setHasMore(response.meta?.has_more ?? response.data.length >= PER_PAGE);
        } else {
          if (!append) setEvents([]);
          setHasMore(false);
        }
      } catch (error) {
        logError('Failed to load federated events', error);
        if (!append) {
          setLoadError('Failed to load federated events. Please try again.');
        } else {
          toast.error('Failed to load more events');
        }
      } finally {
        setIsLoading(false);
        setIsLoadingMore(false);
      }
    },
    [debouncedQuery, selectedPartner, upcomingOnly, cursor, toast]
  );

  // Reload on filter change
  useEffect(() => {
    setCursor(null);
    setHasMore(false);
    loadEvents(false);
  }, [debouncedQuery, selectedPartner, upcomingOnly]); // eslint-disable-line react-hooks/exhaustive-deps

  // Sync URL params
  useEffect(() => {
    const params = new URLSearchParams();
    if (searchQuery) params.set('q', searchQuery);
    if (selectedPartner) params.set('partner_id', selectedPartner);
    if (!upcomingOnly) params.set('upcoming', 'false');
    setSearchParams(params, { replace: true });
  }, [searchQuery, selectedPartner, upcomingOnly, setSearchParams]);

  function handleLoadMore() {
    if (!isLoadingMore && hasMore) {
      loadEvents(true);
    }
  }

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: {
      opacity: 1,
      transition: { staggerChildren: 0.05 },
    },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 20 },
    visible: { opacity: 1, y: 0 },
  };

  return (
    <div className="space-y-6">
      {/* Breadcrumbs */}
      <Breadcrumbs
        items={[
          { label: 'Federation', href: '/federation' },
          { label: 'Events' },
        ]}
      />

      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-3">
          <Calendar className="w-7 h-7 text-amber-400" aria-hidden="true" />
          Federated Events
        </h1>
        <p className="text-theme-muted mt-1">
          Events from communities across your network
        </p>
      </div>

      {/* Filter Bar */}
      <GlassCard className="p-4">
        <div className="flex flex-col lg:flex-row gap-4">
          {/* Search */}
          <div className="flex-1">
            <Input
              placeholder="Search federated events..."
              aria-label="Search federated events"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              startContent={<Search className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
              classNames={{
                input: 'bg-transparent text-theme-primary placeholder:text-theme-subtle',
                inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
              }}
            />
          </div>

          {/* Partner filter */}
          <Select
            placeholder="All Communities"
            aria-label="Filter by community"
            selectedKeys={selectedPartner ? [selectedPartner] : []}
            onChange={(e) => setSelectedPartner(e.target.value)}
            className="w-full lg:w-52"
            classNames={{
              trigger: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
              value: 'text-theme-primary',
            }}
            startContent={<Globe className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
          >
            {[
              { id: '', name: 'All Communities' },
              ...partners.map((p) => ({ id: String(p.id), name: p.name })),
            ].map((item) => (
              <SelectItem key={item.id}>{item.name}</SelectItem>
            ))}
          </Select>

          {/* Upcoming toggle */}
          <Chip
            variant={upcomingOnly ? 'solid' : 'flat'}
            className={
              upcomingOnly
                ? 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white cursor-pointer self-start'
                : 'bg-theme-elevated text-theme-muted cursor-pointer hover:bg-theme-hover self-start'
            }
            onClick={() => setUpcomingOnly(!upcomingOnly)}
            aria-pressed={upcomingOnly}
          >
            Upcoming Only
          </Chip>
        </div>
      </GlassCard>

      {/* Loading State */}
      {isLoading && events.length === 0 && (
        <div className="space-y-4">
          {[1, 2, 3].map((i) => (
            <GlassCard key={i} className="p-5 animate-pulse">
              <div className="flex gap-4">
                <div className="w-16 h-20 rounded-lg bg-theme-hover" />
                <div className="flex-1">
                  <div className="h-5 bg-theme-hover rounded w-1/2 mb-2" />
                  <div className="h-4 bg-theme-hover rounded w-3/4 mb-3" />
                  <div className="h-3 bg-theme-hover rounded w-1/4" />
                </div>
              </div>
            </GlassCard>
          ))}
        </div>
      )}

      {/* Error State */}
      {!isLoading && loadError && (
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary mb-2">
            Unable to Load Events
          </h2>
          <p className="text-theme-muted mb-4">{loadError}</p>
          <Button
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
            startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
            onPress={() => { setCursor(null); loadEvents(false); }}
          >
            Try Again
          </Button>
        </GlassCard>
      )}

      {/* Empty State */}
      {!isLoading && !loadError && events.length === 0 && (
        <EmptyState
          icon={<Calendar className="w-12 h-12" />}
          title="No federated events found"
          description={
            upcomingOnly
              ? 'No upcoming events from partner communities. Try showing past events too.'
              : 'Try adjusting your filters or check back later.'
          }
        />
      )}

      {/* Events List */}
      {!isLoading && !loadError && events.length > 0 && (
        <>
          <motion.div
            variants={containerVariants}
            initial="hidden"
            animate="visible"
            className="space-y-4"
          >
            {events.map((event) => (
              <motion.div key={`${event.timebank.id}-${event.id}`} variants={itemVariants}>
                <FederatedEventCard event={event} />
              </motion.div>
            ))}
          </motion.div>

          {/* Load More */}
          {hasMore && (
            <div className="text-center pt-4">
              <Button
                variant="flat"
                className="bg-theme-elevated text-theme-primary"
                onPress={handleLoadMore}
                isLoading={isLoadingMore}
              >
                Load More
              </Button>
            </div>
          )}
        </>
      )}
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Federated Event Card
// ─────────────────────────────────────────────────────────────────────────────

interface FederatedEventCardProps {
  event: FederatedEvent;
}

function FederatedEventCard({ event }: FederatedEventCardProps) {
  const startDate = new Date(event.start_date);
  const isPast = startDate < new Date();
  const avatarSrc = resolveAvatarUrl(event.organizer?.avatar);
  const coverSrc = event.cover_image ? resolveAssetUrl(event.cover_image) : null;

  const formattedDate = startDate.toLocaleDateString('en-US', {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
  });
  const formattedTime = startDate.toLocaleTimeString('en-US', {
    hour: 'numeric',
    minute: '2-digit',
  });

  return (
    <article>
      <GlassCard className={`p-5 hover:scale-[1.01] transition-transform ${isPast ? 'opacity-60' : ''}`}>
        <div className="flex gap-3 sm:gap-4">
          {/* Cover Image or Date Box */}
          {coverSrc ? (
            <div className="flex-shrink-0 w-20 sm:w-24 h-20 sm:h-24 rounded-lg overflow-hidden bg-theme-hover">
              <img
                src={coverSrc}
                alt={event.title}
                className="w-full h-full object-cover"
                loading="lazy"
              />
            </div>
          ) : (
            <div className="flex-shrink-0 w-14 sm:w-16 text-center">
              <time
                dateTime={event.start_date}
                className="block bg-gradient-to-br from-amber-500/20 to-orange-500/20 rounded-lg p-2"
              >
                <span className="text-amber-400 text-xs font-medium uppercase block">
                  {startDate.toLocaleString('default', { month: 'short' })}
                </span>
                <span className="text-theme-primary text-xl sm:text-2xl font-bold block">
                  {startDate.getDate()}
                </span>
                <span className="text-theme-subtle text-xs block">
                  {startDate.toLocaleString('default', { weekday: 'short' })}
                </span>
              </time>
            </div>
          )}

          {/* Event Details */}
          <div className="flex-1 min-w-0">
            <div className="flex items-center gap-2 flex-wrap mb-1">
              <h3 className="font-semibold text-theme-primary text-lg">
                {event.title}
              </h3>
              {event.is_online && (
                <Chip
                  size="sm"
                  variant="flat"
                  className="bg-cyan-500/20 text-cyan-600 dark:text-cyan-400"
                  startContent={<Wifi className="w-3 h-3" aria-hidden="true" />}
                >
                  Online
                </Chip>
              )}
            </div>

            <p className="text-theme-muted text-sm line-clamp-2 mb-2">
              {event.description}
            </p>

            {/* Meta Row */}
            <div className="flex flex-wrap items-center gap-4 text-sm text-theme-subtle">
              <span className="flex items-center gap-1">
                <Clock className="w-4 h-4" aria-hidden="true" />
                <time dateTime={event.start_date}>
                  {formattedDate} at {formattedTime}
                </time>
              </span>

              {event.location && !event.is_online && (
                <span className="flex items-center gap-1">
                  <MapPin className="w-4 h-4" aria-hidden="true" />
                  <span className="truncate max-w-[150px]">{event.location}</span>
                </span>
              )}

              <span className="flex items-center gap-1">
                <Users className="w-4 h-4" aria-hidden="true" />
                {event.attendees_count} going
              </span>
            </div>

            {/* Footer: Organizer + Community */}
            <div className="flex items-center justify-between mt-3 pt-2 border-t border-theme-default">
              <div className="flex items-center gap-2 min-w-0">
                <Avatar
                  src={avatarSrc}
                  name={event.organizer?.name || 'Organizer'}
                  size="sm"
                  className="flex-shrink-0 w-6 h-6"
                />
                <span className="text-sm text-theme-subtle truncate">
                  {event.organizer?.name}
                </span>
              </div>
              <Chip
                size="sm"
                variant="flat"
                className="bg-indigo-500/10 text-indigo-600 dark:text-indigo-400"
                startContent={<Globe className="w-3 h-3" aria-hidden="true" />}
              >
                {event.timebank.name}
              </Chip>
            </div>
          </div>

          {/* Arrow */}
          <div className="flex-shrink-0 self-center hidden sm:block">
            <ChevronRight className="w-5 h-5 text-theme-subtle" aria-hidden="true" />
          </div>
        </div>
      </GlassCard>
    </article>
  );
}

export default FederationEventsPage;
