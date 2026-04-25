// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Events Page - Community events listing with category filtering
 */

import { useState, useEffect, useCallback, useRef, memo, useMemo } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Input, Select, SelectItem, Chip, Skeleton } from '@heroui/react';
import Search from 'lucide-react/icons/search';
import Calendar from 'lucide-react/icons/calendar';
import List from 'lucide-react/icons/list';
import MapIcon from 'lucide-react/icons/map';
import MapPin from 'lucide-react/icons/map-pin';
import Users from 'lucide-react/icons/users';
import Clock from 'lucide-react/icons/clock';
import Plus from 'lucide-react/icons/plus';
import Filter from 'lucide-react/icons/filter';
import CalendarDays from 'lucide-react/icons/calendar-days';
import ChevronRight from 'lucide-react/icons/chevron-right';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import Tag from 'lucide-react/icons/tag';
import Star from 'lucide-react/icons/star';
import Ban from 'lucide-react/icons/ban';
import X from 'lucide-react/icons/x';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { SafeHtml } from '@/components/ui/SafeHtml';
import { EntityMapView } from '@/components/location';
import { EmptyState } from '@/components/feedback';
import { useAuth, useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { MAPS_ENABLED } from '@/lib/map-config';
import { logError } from '@/lib/logger';
import { formatDateTime, formatDateValue, formatMonthShort, resolveAssetUrl } from '@/lib/helpers';
import { usePageTitle } from '@/hooks';
import { PageMeta } from '@/components/seo/PageMeta';
import type { Event } from '@/types/api';

type EventFilter = 'upcoming' | 'past' | 'all';

const ITEMS_PER_PAGE = 20;
const SEARCH_DEBOUNCE_MS = 300;

const containerVariants = {
  hidden: {},
  visible: {
    transition: {
      staggerChildren: 0.05,
    },
  },
};

const itemVariants = {
  hidden: { opacity: 0, y: 20 },
  visible: { opacity: 1, y: 0, transition: { duration: 0.3 } },
};

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
  const { isAuthenticated, user } = useAuth();
  const { tenantPath } = useTenant();
  const toast = useToast();
  const [searchParams, setSearchParams] = useSearchParams();

  const EVENT_CATEGORIES = EVENT_CATEGORY_IDS.map((cat) => {
    const key = `category.${cat.id}`;
    const translated = t(key);
    // Fall back to capitalized ID if translation key is missing (t() returns the key itself when missing)
    const name = translated === key
      ? cat.id.charAt(0).toUpperCase() + cat.id.slice(1)
      : translated;
    return { ...cat, name };
  });

  const [events, setEvents] = useState<Event[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [hasMore, setHasMore] = useState(true);
  const [totalCount, setTotalCount] = useState<number | null>(null);
  const [, setNextCursor] = useState<string | null>(null);
  const nextCursorRef = useRef<string | null>(null);
  const [searchQuery, setSearchQuery] = useState(searchParams.get('q') || '');
  const [debouncedQuery, setDebouncedQuery] = useState(searchQuery);
  const [filter, setFilter] = useState<EventFilter>('upcoming');
  const [selectedCategory, setSelectedCategory] = useState(searchParams.get('category') || 'all');
  const [viewMode, setViewMode] = useState<'list' | 'map'>('list');
  const [nearMeEnabled, setNearMeEnabled] = useState(false);
  const [radiusKm, setRadiusKm] = useState(25);

  const activeFilterCount = useMemo(() => {
    let count = 0;
    if (searchQuery.trim()) count += 1;
    if (filter !== 'upcoming') count += 1;
    if (selectedCategory !== 'all') count += 1;
    if (nearMeEnabled) count += 1;
    return count;
  }, [searchQuery, filter, selectedCategory, nearMeEnabled]);

  const clearFilters = useCallback(() => {
    setSearchQuery('');
    setFilter('upcoming');
    setSelectedCategory('all');
    setNearMeEnabled(false);
  }, []);

  const tRef = useRef(t);
  tRef.current = t;
  const toastRef = useRef(toast);
  toastRef.current = toast;

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

  const loadEvents = useCallback(async (append = false): Promise<void> => {
    // Abort any in-flight request to prevent race conditions
    if (!append && abortControllerRef.current) {
      abortControllerRef.current.abort();
    }
    const controller = new AbortController();
    if (!append) {
      abortControllerRef.current = controller;
    }

    try {
      if (controller.signal.aborted) return;
      if (!append) {
        setIsLoading(true);
        setError(null);
        setTotalCount(null);
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

      let endpoint = '/v2/events';
      if (nearMeEnabled && user?.latitude != null && user?.longitude != null) {
        endpoint = '/v2/events/nearby';
        params.set('lat', String(user.latitude));
        params.set('lng', String(user.longitude));
        params.set('radius_km', String(radiusKm));
      }

      const response = await api.get<Event[]>(`${endpoint}?${params}`);
      if (controller.signal.aborted) return;
      if (response.success && response.data) {
        if (controller.signal.aborted) return;
        if (append) {
          setEvents((prev) => [...prev, ...response.data!]);
        } else {
          setEvents(response.data);
        }
        if (controller.signal.aborted) return;
        const cursor = response.meta?.cursor ?? null;
        nextCursorRef.current = cursor;
        setNextCursor(cursor);
        setHasMore(response.meta?.has_more ?? (response.data?.length ?? 0) >= ITEMS_PER_PAGE);
        if (response.meta?.total_items !== undefined) {
          setTotalCount(response.meta.total_items);
        }
      } else {
        if (controller.signal.aborted) return;
        if (!append) {
          setError(tRef.current('unable_to_load'));
        }
      }
    } catch (err) {
      if (controller.signal.aborted) return;
      logError('Failed to load events', err);
      if (!append) {
        if (nearMeEnabled) {
          toastRef.current.error(tRef.current('nearby_error'));
        }
        setError(tRef.current('unable_to_load'));
      } else {
        toastRef.current.error(tRef.current('error_load_more'));
      }
    } finally {
      if (!controller.signal.aborted) {
        setIsLoading(false);
        setIsLoadingMore(false);
      }
    }
  }, [debouncedQuery, filter, selectedCategory, nearMeEnabled, user?.latitude, user?.longitude, radiusKm]);

  const loadEventsRef = useRef(loadEvents);
  loadEventsRef.current = loadEvents;

  useEffect(() => {
    nextCursorRef.current = null;
    setNextCursor(null);
    setHasMore(true);
    loadEventsRef.current(false);
    return () => {
      if (abortControllerRef.current) {
        abortControllerRef.current.abort();
      }
    };
  }, [debouncedQuery, filter, selectedCategory, nearMeEnabled, user?.latitude, user?.longitude, radiusKm]);

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

  function handleNearMeToggle() {
    if (nearMeEnabled) {
      setNearMeEnabled(false);
      return;
    }
    if (!user?.latitude || !user?.longitude) {
      toast.error(t('near_me_no_location'));
      return;
    }
    setNearMeEnabled(true);
  }

  // Group events by month
  const groupedEvents = events.reduce((groups, event) => {
    const date = new Date(event.start_date);
    const key = formatDateValue(date, { month: 'long', year: 'numeric' });
    if (!groups[key]) groups[key] = [];
    groups[key].push(event);
    return groups;
  }, {} as Record<string, Event[]>);

  return (
    <div className="space-y-6">
      <PageMeta title={t('page_title')} description={t('page_description')} />
      <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div className="space-y-2">
          <div className="inline-flex items-center gap-2 rounded-full border border-theme-default bg-theme-elevated px-3 py-1 text-xs font-medium text-theme-muted">
            <CalendarDays className="h-3.5 w-3.5 text-amber-500" aria-hidden="true" />
            {t('subtitle')}
          </div>
          <div className="space-y-1">
            <h1 className="text-3xl font-bold text-theme-primary sm:text-4xl">{t('title')}</h1>
            <p className="max-w-2xl text-sm text-theme-muted sm:text-base">{t('page_description')}</p>
          </div>
        </div>
        <div className="flex flex-wrap items-center gap-3">
          {totalCount != null && !isLoading && (
            <Chip
              variant="flat"
              color="primary"
              startContent={<Calendar className="h-3.5 w-3.5" aria-hidden="true" />}
            >
              {t('count_pill', { count: totalCount })}
            </Chip>
          )}
          {isAuthenticated && (
            <Link to={tenantPath('/events/create')}>
              <Button
                color="primary"
                className="font-semibold"
                startContent={<Plus className="w-4 h-4" />}
              >
                {t('create_event')}
              </Button>
            </Link>
          )}
        </div>
      </div>

      {/* Search & Time Filter */}
      <GlassCard className="p-4 sm:p-5">
        <div className="grid gap-3 lg:grid-cols-[minmax(260px,1fr)_auto_auto_auto_auto] lg:items-center">
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
            disallowEmptySelection
            onChange={(e) => setFilter(e.target.value as EventFilter)}
            className="w-full lg:w-40"
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

          <Button
            variant={nearMeEnabled ? 'solid' : 'flat'}
            className={nearMeEnabled
              ? 'bg-primary text-white min-h-[40px] w-full lg:w-auto'
              : 'bg-theme-elevated text-theme-primary min-h-[40px] w-full lg:w-auto'}
            startContent={<MapPin className="w-4 h-4" aria-hidden="true" />}
            onPress={handleNearMeToggle}
            aria-pressed={nearMeEnabled}
            aria-label={t('near_me')}
          >
            {t('near_me')}
          </Button>

          {nearMeEnabled && (
            <Select
              aria-label={t('radius_label')}
              selectedKeys={[String(radiusKm)]}
              disallowEmptySelection
              onSelectionChange={(keys) => {
                const val = keys instanceof Set ? ([...keys][0] as string) : '25';
                setRadiusKm(Number(val) || 25);
              }}
              className="w-full lg:w-36"
              classNames={{
                trigger: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                value: 'text-theme-primary',
              }}
            >
              <SelectItem key="5">{t('radius_5')}</SelectItem>
              <SelectItem key="10">{t('radius_10')}</SelectItem>
              <SelectItem key="25">{t('radius_25')}</SelectItem>
              <SelectItem key="50">{t('radius_50')}</SelectItem>
              <SelectItem key="100">{t('radius_100')}</SelectItem>
            </Select>
          )}

          {MAPS_ENABLED && (
            <div className="flex min-h-[40px] rounded-xl overflow-hidden border border-theme-default bg-theme-elevated" role="group" aria-label={t('view_mode_aria')}>
              <Button
                isIconOnly
                size="sm"
                variant="light"
                className={`rounded-none ${viewMode === 'list' ? 'bg-indigo-500/15 text-indigo-600 dark:text-indigo-400' : 'text-theme-muted hover:bg-theme-hover'}`}
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
                className={`rounded-none ${viewMode === 'map' ? 'bg-indigo-500/15 text-indigo-600 dark:text-indigo-400' : 'text-theme-muted hover:bg-theme-hover'}`}
                aria-label={t('view_map')}
                aria-pressed={viewMode === 'map'}
                onPress={() => setViewMode('map')}
              >
                <MapIcon className="w-4 h-4" aria-hidden="true" />
              </Button>
            </div>
          )}
        </div>

        {activeFilterCount > 0 && (
          <div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-theme-default pt-4">
            <div className="flex flex-wrap items-center gap-2 text-sm text-theme-muted">
              <span className="font-medium text-theme-secondary">{t('active_filters')}</span>
              {searchQuery.trim() && <Chip size="sm" variant="flat">{t('active_search', { query: searchQuery.trim() })}</Chip>}
              {filter !== 'upcoming' && <Chip size="sm" variant="flat">{t(`filter_${filter}`)}</Chip>}
              {selectedCategory !== 'all' && (
                <Chip size="sm" variant="flat">
                  {EVENT_CATEGORIES.find((cat) => cat.id === selectedCategory)?.name ?? selectedCategory}
                </Chip>
              )}
              {nearMeEnabled && <Chip size="sm" variant="flat">{t('active_near_me', { radius: radiusKm })}</Chip>}
            </div>
            <Button
              size="sm"
              variant="light"
              className="text-theme-muted"
              startContent={<X className="h-4 w-4" aria-hidden="true" />}
              onPress={clearFilters}
            >
              {t('clear_filters')}
            </Button>
          </div>
        )}
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
                  ? 'bg-linear-to-r from-indigo-500 to-purple-600 text-white cursor-pointer'
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
            color="primary"
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
            <div className="space-y-6" aria-label={t('loading_aria', 'Loading events')} aria-busy="true">
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
                    <Button color="primary">
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
                  <h4 className="font-semibold text-sm text-theme-primary">{e.title}</h4>
                  {e.location && <p className="text-xs text-theme-muted mt-0.5">{e.location}</p>}
                  <p className="text-xs text-theme-muted mt-1">
                    {formatDateValue(e.start_date)}
                  </p>
                </div>
              )}
              isLoading={isLoading}
              emptyMessage={t('no_location')}
            />
          ) : (
            <motion.div
              key={debouncedQuery + filter + selectedCategory}
              className="space-y-8"
              variants={containerVariants}
              initial="hidden"
              animate="visible"
            >
              {Object.entries(groupedEvents).map(([month, monthEvents]) => (
                <section key={month} aria-label={t('events_in_month', 'Events in {{month}}', { month })}>
                  <h2 className="text-sm font-semibold uppercase tracking-wide text-theme-muted mb-3 flex items-center gap-2">
                    <CalendarDays className="w-4 h-4 text-amber-500" aria-hidden="true" />
                    {month}
                  </h2>
                  <div className="space-y-4">
                    {monthEvents.map((event) => (
                      <motion.div
                        key={event.id}
                        variants={itemVariants}
                      >
                        <EventCard event={event} />
                      </motion.div>
                    ))}
                  </div>
                </section>
              ))}

              {/* Load More Button */}
              {hasMore && (
                <div className="space-y-3 pt-4">
                  {totalCount != null && totalCount > 0 && (
                    <div className="space-y-1.5">
                      <div className="flex justify-between text-xs text-theme-muted px-1">
                        <span>{events.length.toLocaleString()} / {totalCount.toLocaleString()}</span>
                        <span className="font-medium text-theme-secondary">{Math.round((events.length / totalCount) * 100)}%</span>
                      </div>
                      <div className="h-1.5 rounded-full bg-theme-elevated overflow-hidden">
                        <motion.div
                          className="h-full rounded-full bg-primary"
                          initial={{ width: '0%' }}
                          animate={{ width: `${Math.round((events.length / totalCount) * 100)}%` }}
                          transition={{ duration: 0.6, ease: 'easeOut' }}
                        />
                      </div>
                    </div>
                  )}
                  <div className="text-center">
                    <Button
                      variant="flat"
                      className="bg-theme-elevated text-theme-muted hover:bg-theme-hover"
                      onPress={loadMoreEvents}
                      isLoading={isLoadingMore}
                    >
                      {totalCount != null && totalCount > events.length
                        ? t('load_more_count', { remaining: (totalCount - events.length).toLocaleString() })
                        : t('load_more')}
                    </Button>
                  </div>
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
  const eventDateLabel = formatDateValue(startDate);
  const monthLabel = formatMonthShort(startDate, true);
  const weekdayLabel = formatDateValue(startDate, { weekday: 'short' });
  const timeLabel = formatDateTime(startDate, { hour: '2-digit', minute: '2-digit' });
  const coverImage = event.cover_image ? resolveAssetUrl(event.cover_image) : null;

  return (
    <Link to={tenantPath(`/events/${event.id}`)} aria-label={t('card.open_aria', { title: event.title, date: eventDateLabel })}>
      <article>
        <GlassCard className={`overflow-hidden hover:border-primary/40 hover:shadow-lg transition ${isPast || isCancelled ? 'opacity-65' : ''}`}>
          {coverImage && (
            <img
              src={coverImage}
              alt={t('detail.cover_alt', { title: event.title })}
              className="h-36 w-full object-cover sm:hidden"
              loading="lazy"
            />
          )}
          <div className="flex gap-3 p-4 sm:gap-4 sm:p-5">
            {/* Date Box */}
            <div className="flex-shrink-0 w-14 sm:w-16 text-center">
              <time dateTime={event.start_date} className="block rounded-lg border border-amber-500/25 bg-amber-500/10 p-2">
                <span className="text-amber-600 dark:text-amber-400 text-xs font-medium uppercase block">
                  {monthLabel}
                </span>
                <span className="text-theme-primary text-xl sm:text-2xl font-bold block">
                  {startDate.getDate()}
                </span>
                <span className="text-theme-subtle text-xs block">
                  {weekdayLabel}
                </span>
              </time>
            </div>

            {/* Event Details */}
            <div className="flex-1 min-w-0">
              <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                  <div className="flex items-center gap-2 flex-wrap">
                    <h3 className="font-semibold text-theme-primary text-base sm:text-lg leading-snug">{event.title}</h3>
                    {event.category_name && (
                      <Chip size="sm" variant="flat" color="secondary" className="text-xs">
                        {event.category_name}
                      </Chip>
                    )}
                  </div>
                  <SafeHtml content={event.description} className="text-theme-muted text-sm line-clamp-2 mt-1" as="p" />
                </div>
                {coverImage && (
                  <img
                    src={coverImage}
                    alt={t('detail.cover_alt', { title: event.title })}
                    className="hidden h-20 w-28 flex-shrink-0 rounded-lg object-cover sm:block"
                    loading="lazy"
                  />
                )}
              </div>

              <div className="mt-3 flex flex-wrap items-center gap-2">
                {isCancelled && (
                  <Chip size="sm" variant="flat" color="danger" startContent={<Ban className="h-3.5 w-3.5" aria-hidden="true" />}>
                    {t('card.cancelled')}
                  </Chip>
                )}
                {event.is_full && !isCancelled && (
                  <Chip size="sm" variant="flat" color="danger">{t('card.full')}</Chip>
                )}
                {event.max_attendees != null && event.spots_left != null && event.spots_left > 0 && !isCancelled && (
                  <Chip size="sm" variant="flat" color={event.spots_left <= 3 ? 'danger' : 'success'}>
                    {t('card.spots_left', { count: event.spots_left })}
                  </Chip>
                )}
              </div>

              <div className="flex flex-wrap items-center gap-x-4 gap-y-2 mt-3 text-sm text-theme-subtle">
                <span className="flex items-center gap-1.5">
                  <Clock className="w-4 h-4" aria-hidden="true" />
                  <time dateTime={event.start_date}>
                    {timeLabel}
                  </time>
                </span>
                {event.location && (
                  <span className="flex min-w-0 items-center gap-1.5">
                    <MapPin className="h-4 w-4 flex-shrink-0" aria-hidden="true" />
                    <span className="truncate">{event.location}</span>
                  </span>
                )}
                <span className="flex items-center gap-1.5">
                  <Users className="w-4 h-4" aria-hidden="true" />
                  {t('going', { count: event.attendees_count ?? 0 })}
                  {(event.interested_count ?? 0) > 0 && (
                    <span className="text-theme-subtle">&middot; {t('interested', { count: event.interested_count })}</span>
                  )}
                </span>
              </div>
            </div>

            <div className="hidden flex-shrink-0 self-center sm:block">
              <ChevronRight className="w-5 h-5 text-theme-subtle" aria-hidden="true" />
            </div>
          </div>
        </GlassCard>
      </article>
    </Link>
  );
});

export default EventsPage;
