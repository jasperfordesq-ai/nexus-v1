// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Events Page - Community events listing with category filtering
 */

import { useState, useEffect, useCallback, useRef, memo } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Input, Select, SelectItem, Chip, Skeleton } from '@heroui/react';
import {
  Search,
  Calendar,
  List,
  Map as MapIcon,
  MapPin,
  Users,
  Clock,
  Plus,
  Filter,
  CalendarDays,
  ChevronRight,
  RefreshCw,
  AlertTriangle,
  Tag,
  Star,
  Ban,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { EntityMapView } from '@/components/location';
import { EmptyState } from '@/components/feedback';
import { useAuth, useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { MAPS_ENABLED } from '@/lib/map-config';
import { logError } from '@/lib/logger';
import { usePageTitle } from '@/hooks';
import type { Event } from '@/types/api';

type EventFilter = 'upcoming' | 'past' | 'all';

const ITEMS_PER_PAGE = 20;
const SEARCH_DEBOUNCE_MS = 300;

/** Event category metadata — names resolved via t() inside the component */
const EVENT_CATEGORY_IDS = [
  { id: 'all', icon: Star, color: 'default' as const },
  { id: 'workshop', icon: Tag, color: 'secondary' as const },
  { id: 'social', icon: Users, color: 'success' as const },
  { id: 'outdoor', icon: MapPin, color: 'warning' as const },
  { id: 'online', icon: CalendarDays, color: 'primary' as const },
  { id: 'meeting', icon: Calendar, color: 'danger' as const },
  { id: 'training', icon: Clock, color: 'secondary' as const },
  { id: 'other', icon: Filter, color: 'default' as const },
] as const;

export function EventsPage() {
  const { t } = useTranslation('events');
  usePageTitle(t('title'));
  const { isAuthenticated } = useAuth();
  const { tenantPath } = useTenant();
  const toast = useToast();
  const [searchParams, setSearchParams] = useSearchParams();

  const EVENT_CATEGORIES = EVENT_CATEGORY_IDS.map((cat) => ({
    ...cat,
    name: t(`category.${cat.id}`),
  }));

  const [events, setEvents] = useState<Event[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [hasMore, setHasMore] = useState(true);
  const [, setNextCursor] = useState<string | null>(null);
  const nextCursorRef = useRef<string | null>(null);
  const [searchQuery, setSearchQuery] = useState(searchParams.get('q') || '');
  const [debouncedQuery, setDebouncedQuery] = useState(searchQuery);
  const [filter, setFilter] = useState<EventFilter>('upcoming');
  const [selectedCategory, setSelectedCategory] = useState(searchParams.get('category') || 'all');
  const [viewMode, setViewMode] = useState<'list' | 'map'>('list');

  const searchTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const abortControllerRef = useRef<AbortController | null>(null);

  // Debounce search query
  useEffect(() => {
    if (searchTimeoutRef.current) {
      clearTimeout(searchTimeoutRef.current);
    }
    searchTimeoutRef.current = setTimeout(() => {
      setDebouncedQuery(searchQuery);
    }, SEARCH_DEBOUNCE_MS);

    return () => {
      if (searchTimeoutRef.current) {
        clearTimeout(searchTimeoutRef.current);
      }
    };
  }, [searchQuery]);

  const loadEvents = useCallback(async (append = false) => {
    // Abort any in-flight request to prevent race conditions
    if (!append && abortControllerRef.current) {
      abortControllerRef.current.abort();
    }
    const controller = new AbortController();
    if (!append) {
      abortControllerRef.current = controller;
    }

    try {
      if (!append) {
        setIsLoading(true);
        setError(null);
      } else {
        setIsLoadingMore(true);
      }

      const params = new URLSearchParams();
      if (debouncedQuery) params.set('q', debouncedQuery);
      if (filter !== 'all') params.set('when', filter);
      params.set('per_page', String(ITEMS_PER_PAGE));
      if (append && nextCursorRef.current) {
        params.set('cursor', nextCursorRef.current);
      }
      if (selectedCategory && selectedCategory !== 'all') {
        params.set('category', selectedCategory);
      }

      const response = await api.get<Event[]>(`/v2/events?${params}`);
      if (controller.signal.aborted) return;
      if (response.success && response.data) {
        if (append) {
          setEvents((prev) => [...prev, ...response.data!]);
        } else {
          setEvents(response.data);
        }
        const cursor = response.meta?.cursor ?? null;
        nextCursorRef.current = cursor;
        setNextCursor(cursor);
        setHasMore(response.meta?.has_more ?? (response.data?.length ?? 0) >= ITEMS_PER_PAGE);
      } else {
        if (!append) {
          setError(t('unable_to_load'));
        }
      }
    } catch (err) {
      if (controller.signal.aborted) return;
      logError('Failed to load events', err);
      if (!append) {
        setError(t('unable_to_load'));
      } else {
        toast.error(t('error_load_more'));
      }
    } finally {
      setIsLoading(false);
      setIsLoadingMore(false);
    }
  }, [debouncedQuery, filter, selectedCategory, t, toast]);

  useEffect(() => {
    nextCursorRef.current = null;
    setNextCursor(null);
    setHasMore(true);
    loadEvents();
    return () => {
      if (abortControllerRef.current) {
        abortControllerRef.current.abort();
      }
    };
  }, [debouncedQuery, filter, selectedCategory, loadEvents]);

  // Update URL params
  useEffect(() => {
    const params = new URLSearchParams();
    if (searchQuery) params.set('q', searchQuery);
    if (selectedCategory && selectedCategory !== 'all') params.set('category', selectedCategory);
    setSearchParams(params, { replace: true });
  }, [searchQuery, selectedCategory, setSearchParams]);

  const loadMoreEvents = useCallback(() => {
    if (isLoadingMore || !hasMore) return;
    loadEvents(true);
  }, [isLoadingMore, hasMore, loadEvents]);

  // Group events by month
  const groupedEvents = events.reduce((groups, event) => {
    const date = new Date(event.start_date);
    const key = date.toLocaleString('default', { month: 'long', year: 'numeric' });
    if (!groups[key]) groups[key] = [];
    groups[key].push(event);
    return groups;
  }, {} as Record<string, Event[]>);

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
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-3">
            <Calendar className="w-7 h-7 text-amber-400" aria-hidden="true" />
            {t('title')}
          </h1>
          <p className="text-theme-muted mt-1">{t('subtitle')}</p>
        </div>
        {isAuthenticated && (
          <Link to={tenantPath('/events/create')}>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
            >
              {t('create_event')}
            </Button>
          </Link>
        )}
      </div>

      {/* Search & Time Filter */}
      <GlassCard className="p-4">
        <div className="flex flex-col lg:flex-row gap-4">
          <div className="flex-1">
            <Input
              placeholder={t('search_placeholder')}
              aria-label={t('search_aria')}
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              startContent={<Search className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
              classNames={{
                input: 'bg-transparent text-theme-primary placeholder:text-theme-subtle',
                inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
              }}
            />
          </div>

          <Select
            placeholder={t('filter_placeholder')}
            aria-label={t('filter_aria')}
            selectedKeys={[filter]}
            onChange={(e) => setFilter(e.target.value as EventFilter)}
            className="w-32 sm:w-40"
            classNames={{
              trigger: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
              value: 'text-theme-primary',
            }}
            startContent={<Filter className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
          >
            <SelectItem key="upcoming">{t('filter_upcoming')}</SelectItem>
            <SelectItem key="past">{t('filter_past')}</SelectItem>
            <SelectItem key="all">{t('filter_all')}</SelectItem>
          </Select>

          {MAPS_ENABLED && (
            <div className="flex rounded-lg overflow-hidden border border-default-200" role="group" aria-label={t('view_mode_aria')}>
              <Button
                isIconOnly
                size="sm"
                variant="light"
                className={`rounded-none ${viewMode === 'list' ? 'bg-primary/10 text-primary' : ''}`}
                aria-label={t('view_list')}
                aria-pressed={viewMode === 'list'}
                onPress={() => setViewMode('list')}
              >
                <List className="w-4 h-4" aria-hidden="true" />
              </Button>
              <Button
                isIconOnly
                size="sm"
                variant="light"
                className={`rounded-none ${viewMode === 'map' ? 'bg-primary/10 text-primary' : ''}`}
                aria-label={t('view_map')}
                aria-pressed={viewMode === 'map'}
                onPress={() => setViewMode('map')}
              >
                <MapIcon className="w-4 h-4" aria-hidden="true" />
              </Button>
            </div>
          )}
        </div>
      </GlassCard>

      {/* Category Filter Chips */}
      <div className="flex flex-wrap gap-2" role="group" aria-label={t('category_aria')}>
        {EVENT_CATEGORIES.map((cat) => {
          const isSelected = selectedCategory === cat.id;
          const IconComp = cat.icon;
          return (
            <Chip
              key={cat.id}
              variant={isSelected ? 'solid' : 'flat'}
              color={isSelected ? 'primary' : 'default'}
              className={
                isSelected
                  ? 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white cursor-pointer'
                  : 'bg-theme-elevated text-theme-muted cursor-pointer hover:bg-theme-hover'
              }
              startContent={<IconComp className="w-3.5 h-3.5" aria-hidden="true" />}
              onClick={() => setSelectedCategory(cat.id)}
              aria-pressed={isSelected}
            >
              {cat.name}
            </Chip>
          );
        })}
      </div>

      {/* Error State */}
      {error && !isLoading && (
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary mb-2">{t('unable_to_load')}</h2>
          <p className="text-theme-muted mb-4">{error}</p>
          <Button
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
            startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
            onPress={() => loadEvents()}
          >
            {t('try_again')}
          </Button>
        </GlassCard>
      )}

      {/* Events List / Map */}
      {!error && (
        <>
          {isLoading ? (
            <div className="space-y-6" aria-label="Loading events" aria-busy="true">
              {[1, 2, 3].map((i) => (
                <GlassCard key={i} className="p-5">
                  <div className="flex gap-4">
                    <Skeleton className="w-16 h-20 rounded-lg flex-shrink-0">
                      <div className="w-16 h-20 rounded-lg bg-default-300" />
                    </Skeleton>
                    <div className="flex-1 space-y-2">
                      <Skeleton className="rounded-lg w-1/2">
                        <div className="h-5 rounded-lg bg-default-300" />
                      </Skeleton>
                      <Skeleton className="rounded-lg w-3/4">
                        <div className="h-4 rounded-lg bg-default-200" />
                      </Skeleton>
                      <Skeleton className="rounded-lg w-1/4">
                        <div className="h-3 rounded-lg bg-default-200" />
                      </Skeleton>
                    </div>
                  </div>
                </GlassCard>
              ))}
            </div>
          ) : events.length === 0 ? (
            <EmptyState
              icon={<Calendar className="w-12 h-12" aria-hidden="true" />}
              title={t('no_events')}
              description={
                selectedCategory !== 'all'
                  ? t('no_events_category', { category: EVENT_CATEGORIES.find((c) => c.id === selectedCategory)?.name })
                  : filter === 'upcoming'
                    ? t('no_events_upcoming')
                    : t('no_events_search')
              }
              action={
                isAuthenticated && (
                  <Link to={tenantPath('/events/create')}>
                    <Button className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white">
                      {t('create_event')}
                    </Button>
                  </Link>
                )
              }
            />
          ) : viewMode === 'map' ? (
            <EntityMapView
              items={events}
              getCoordinates={(e) =>
                e.coordinates ? { lat: Number(e.coordinates.lat), lng: Number(e.coordinates.lng) } : null
              }
              getMarkerConfig={(e) => ({
                id: e.id,
                title: e.title,
              })}
              renderInfoContent={(e) => (
                <div className="p-2 max-w-[250px]">
                  <h4 className="font-semibold text-sm text-gray-900">{e.title}</h4>
                  {e.location && <p className="text-xs text-gray-500 mt-0.5">{e.location}</p>}
                  <p className="text-xs text-gray-600 mt-1">
                    {new Date(e.start_date).toLocaleDateString()}
                  </p>
                </div>
              )}
              isLoading={isLoading}
              emptyMessage={t('no_location')}
            />
          ) : (
            <motion.div
              variants={containerVariants}
              initial="hidden"
              animate="visible"
              className="space-y-8"
            >
              {Object.entries(groupedEvents).map(([month, monthEvents]) => (
                <section key={month} aria-label={t('events_in_month', 'Events in {{month}}', { month })}>
                  <h2 className="text-lg font-semibold text-theme-secondary mb-4 flex items-center gap-2">
                    <CalendarDays className="w-5 h-5 text-amber-400" aria-hidden="true" />
                    {month}
                  </h2>
                  <div className="space-y-4">
                    {monthEvents.map((event) => (
                      <motion.div key={event.id} variants={itemVariants}>
                        <EventCard event={event} />
                      </motion.div>
                    ))}
                  </div>
                </section>
              ))}

              {/* Load More Button */}
              {hasMore && (
                <div className="pt-4 text-center">
                  <Button
                    variant="flat"
                    className="bg-theme-elevated text-theme-muted"
                    onPress={loadMoreEvents}
                    isLoading={isLoadingMore}
                  >
                    {t('load_more')}
                  </Button>
                </div>
              )}
            </motion.div>
          )}
        </>
      )}
    </div>
  );
}

interface EventCardProps {
  event: Event;
}

const EventCard = memo(function EventCard({ event }: EventCardProps) {
  const { t } = useTranslation('events');
  const { tenantPath } = useTenant();
  const startDate = new Date(event.start_date);
  const isPast = startDate < new Date();
  const isCancelled = event.status === 'cancelled';

  return (
    <Link to={tenantPath(`/events/${event.id}`)} aria-label={`${event.title} on ${startDate.toLocaleDateString()}`}>
      <article>
        <GlassCard className={`p-5 hover:scale-[1.01] transition-transform ${isPast || isCancelled ? 'opacity-60' : ''}`}>
          <div className="flex gap-3 sm:gap-4">
            {/* Date Box */}
            <div className="flex-shrink-0 w-14 sm:w-16 text-center">
              <time dateTime={event.start_date} className="block bg-gradient-to-br from-amber-500/20 to-orange-500/20 rounded-lg p-2">
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

            {/* Event Details */}
            <div className="flex-1 min-w-0">
              <div className="flex items-center gap-2 flex-wrap">
                <h3 className="font-semibold text-theme-primary text-lg">{event.title}</h3>
                {event.category_name && (
                  <Chip size="sm" variant="flat" color="secondary" className="text-xs">
                    {event.category_name}
                  </Chip>
                )}
              </div>
              <p className="text-theme-muted text-sm line-clamp-2 mt-1">{event.description}</p>

              <div className="flex flex-wrap items-center gap-4 mt-3 text-sm text-theme-subtle">
                {event.status === 'cancelled' && (
                  <span className="flex items-center gap-1 text-red-400 font-medium">
                    <Ban className="w-4 h-4" aria-hidden="true" />
                    {t('card.cancelled')}
                  </span>
                )}
                <span className="flex items-center gap-1">
                  <Clock className="w-4 h-4" aria-hidden="true" />
                  <time dateTime={event.start_date}>
                    {startDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                  </time>
                </span>
                {event.location && (
                  <span className="flex items-center gap-1">
                    <MapPin className="w-4 h-4" aria-hidden="true" />
                    {event.location}
                  </span>
                )}
                <span className="flex items-center gap-1">
                  <Users className="w-4 h-4" aria-hidden="true" />
                  {t('going', { count: event.attendees_count ?? 0 })}
                  {(event.interested_count ?? 0) > 0 && (
                    <span className="text-theme-subtle">&middot; {t('interested', { count: event.interested_count })}</span>
                  )}
                </span>
                {event.max_attendees != null && event.spots_left != null && event.spots_left > 0 && event.status !== 'cancelled' && (
                  <span className={`text-xs font-medium ${event.spots_left <= 3 ? 'text-red-400' : 'text-emerald-400'}`}>
                    {t('card.spots_left', { count: event.spots_left })}
                  </span>
                )}
                {event.is_full && event.status !== 'cancelled' && (
                  <span className="text-xs font-medium text-red-400">{t('card.full')}</span>
                )}
              </div>
            </div>

            {/* Arrow */}
            <div className="flex-shrink-0 self-center">
              <ChevronRight className="w-5 h-5 text-theme-subtle" aria-hidden="true" />
            </div>
          </div>
        </GlassCard>
      </article>
    </Link>
  );
});

export default EventsPage;
