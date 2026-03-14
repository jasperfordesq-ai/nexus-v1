// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Event Detail Page - Single event view with enhanced RSVP, sharing, and organizer check-in
 */

import { useState, useEffect, useCallback } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { motion, AnimatePresence } from 'framer-motion';
import {
  Button,
  Avatar,
  AvatarGroup,
  Chip,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Tabs,
  Tab,
  Card,
  CardBody,
  Skeleton,
  Textarea,
} from '@heroui/react';
import {
  Calendar,
  Clock,
  MapPin,
  Users,
  Edit,
  Trash2,
  ExternalLink,
  AlertCircle,
  RefreshCw,
  CheckCircle2,
  Heart,
  XCircle,
  Copy,
  UserCheck,
  ClipboardCheck,
  Ban,
  ListOrdered,
  Link2,
  Repeat,
  ArrowRight,
  CalendarRange,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { Breadcrumbs } from '@/components/navigation';
import { LoadingScreen, EmptyState } from '@/components/feedback';
import { LocationMapCard } from '@/components/location';
import { useAuth, useToast, useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl, resolveAssetUrl } from '@/lib/helpers';
import type { Event, User, RsvpResponse } from '@/types/api';

type RsvpOption = 'going' | 'interested' | 'not_going';

interface AttendeeWithCheckIn extends User {
  checked_in?: boolean;
  rsvp_status?: string;
}



export function EventDetailPage() {
  const { t } = useTranslation('events');
  usePageTitle(t('title'));
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { user, isAuthenticated } = useAuth();
  const { tenantPath } = useTenant();
  const toast = useToast();

  const [event, setEvent] = useState<Event | null>(null);
  const [attendees, setAttendees] = useState<AttendeeWithCheckIn[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [rsvpStatus, setRsvpStatus] = useState<RsvpOption | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isDeleting, setIsDeleting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [showDeleteModal, setShowDeleteModal] = useState(false);
  const [showCancelModal, setShowCancelModal] = useState(false);
  const [cancelReason, setCancelReason] = useState('');
  const [isCancelling, setIsCancelling] = useState(false);
  const [activeTab, setActiveTab] = useState('details');
  const [checkingInUserId, setCheckingInUserId] = useState<number | null>(null);
  const [isWaitlisted, setIsWaitlisted] = useState(false);
  const [waitlistPosition, setWaitlistPosition] = useState<number | null>(null);
  const [seriesEvents, setSeriesEvents] = useState<Event[]>([]);
  const [isLoadingSeriesEvents, setIsLoadingSeriesEvents] = useState(false);

  /** Map backend rsvp_status to our 3-option model */
  function normalizeRsvpStatus(status: string | null | undefined): RsvpOption | null {
    if (!status) return null;
    if (status === 'going' || status === 'attending') return 'going';
    if (status === 'interested' || status === 'maybe') return 'interested';
    if (status === 'not_going' || status === 'not_attending') return 'not_going';
    return null;
  }

  const loadEvent = useCallback(async () => {
    if (!id) return;

    try {
      setIsLoading(true);
      setError(null);
      const [eventRes, attendeesRes] = await Promise.all([
        api.get<Event>(`/v2/events/${id}`),
        api.get<AttendeeWithCheckIn[]>(`/v2/events/${id}/attendees?per_page=50`).catch(() => ({ success: true, data: [] })),
      ]);

      if (eventRes.success && eventRes.data) {
        setEvent(eventRes.data);
        setRsvpStatus(normalizeRsvpStatus(eventRes.data.rsvp_status));
      } else {
        setError(t('detail.not_found_desc'));
      }
      if (attendeesRes.success && attendeesRes.data) {
        setAttendees(attendeesRes.data);
      }
    } catch (err) {
      logError('Failed to load event', err);
      setError(t('detail.unable_to_load'));
    } finally {
      setIsLoading(false);
    }
  }, [id]);

  useEffect(() => {
    loadEvent();
  }, [loadEvent]);

  // Fetch other events in the same series
  useEffect(() => {
    if (!event?.series?.id) {
      setSeriesEvents([]);
      return;
    }

    let cancelled = false;
    async function fetchSeriesEvents() {
      setIsLoadingSeriesEvents(true);
      try {
        const res = await api.get<Event[]>(`/v2/events?series_id=${event!.series!.id}&per_page=5`);
        if (!cancelled && res.success && res.data) {
          // Filter out the current event
          setSeriesEvents(
            (Array.isArray(res.data) ? res.data : []).filter((e) => e.id !== event!.id)
          );
        }
      } catch (err) {
        logError('Failed to load series events', err);
      } finally {
        if (!cancelled) setIsLoadingSeriesEvents(false);
      }
    }

    fetchSeriesEvents();
    return () => { cancelled = true; };
  }, [event?.series?.id, event?.id]);

  async function handleRsvp(newStatus: RsvpOption) {
    if (!event || !isAuthenticated) return;

    // If already this status, cancel (remove RSVP)
    if (rsvpStatus === newStatus) {
      try {
        setIsSubmitting(true);
        const response = await api.delete(`/v2/events/${event.id}/rsvp`);
        if (response.success) {
          const prevStatus = rsvpStatus;
          setRsvpStatus(null);
          setEvent((prev) => {
            if (!prev) return null;
            return {
              ...prev,
              attendees_count: prevStatus === 'going' ? Math.max(0, (prev.attendees_count ?? 1) - 1) : prev.attendees_count,
              interested_count: prevStatus === 'interested' ? Math.max(0, (prev.interested_count ?? 1) - 1) : prev.interested_count,
            };
          });
          toast.success(t('toast.rsvp_removed'));
        } else {
          toast.error(t('toast.rsvp_cancel_failed'));
        }
      } catch (err) {
        logError('Failed to cancel RSVP', err);
        toast.error(t('toast.something_wrong'));
      } finally {
        setIsSubmitting(false);
      }
      return;
    }

    try {
      setIsSubmitting(true);
      const response = await api.post<RsvpResponse>(`/v2/events/${event.id}/rsvp`, { status: newStatus });
      if (response.success && response.data) {
        const rsvpData = response.data;

        // Check if user was waitlisted instead
        if (rsvpData.status === 'waitlisted') {
          setIsWaitlisted(true);
          setWaitlistPosition(rsvpData.waitlist_position ?? null);
          toast.info(rsvpData.message || t('toast.added_to_waitlist'));
          return;
        }

        const prevStatus = rsvpStatus;
        setRsvpStatus(newStatus);

        // Update counts from response
        if (rsvpData.rsvp_counts) {
          setEvent((prev) => {
            if (!prev) return null;
            return {
              ...prev,
              attendees_count: rsvpData.rsvp_counts.going,
              interested_count: rsvpData.rsvp_counts.interested,
              spots_left: prev.max_attendees != null ? Math.max(0, prev.max_attendees - rsvpData.rsvp_counts.going) : null,
              is_full: prev.max_attendees != null ? rsvpData.rsvp_counts.going >= prev.max_attendees : false,
            };
          });
        } else {
          // Optimistic fallback
          setEvent((prev) => {
            if (!prev) return null;
            let goingCount = prev.attendees_count ?? 0;
            let interestedCount = prev.interested_count ?? 0;
            if (prevStatus === 'going') goingCount = Math.max(0, goingCount - 1);
            if (prevStatus === 'interested') interestedCount = Math.max(0, interestedCount - 1);
            if (newStatus === 'going') goingCount += 1;
            if (newStatus === 'interested') interestedCount += 1;
            return { ...prev, attendees_count: goingCount, interested_count: interestedCount };
          });
        }

        const messages: Record<RsvpOption, string> = {
          going: t('toast.rsvp_going'),
          interested: t('toast.rsvp_interested'),
          not_going: t('toast.rsvp_not_going'),
        };
        toast.success(messages[newStatus]);
      } else {
        toast.error(t('toast.rsvp_failed'));
      }
    } catch (err) {
      logError('Failed to update RSVP', err);
      toast.error(t('toast.something_wrong'));
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleShare() {
    const url = window.location.href;
    try {
      await navigator.clipboard.writeText(url);
      toast.success(t('toast.share_copied'));
    } catch {
      // Fallback for older browsers
      toast.error(t('toast.share_failed'));
    }
  }

  async function handleDelete() {
    if (!event) return;

    try {
      setIsDeleting(true);
      const response = await api.delete(`/v2/events/${event.id}`);
      if (response.success) {
        toast.success(t('toast.deleted'));
        navigate(tenantPath('/events'));
      } else {
        toast.error(t('toast.delete_failed'));
      }
    } catch (err) {
      logError('Failed to delete event', err);
      toast.error(t('toast.something_wrong'));
    } finally {
      setIsDeleting(false);
      setShowDeleteModal(false);
    }
  }

  async function handleCheckIn(attendeeId: number) {
    if (!event) return;

    try {
      setCheckingInUserId(attendeeId);
      // Use RSVP endpoint with 'attended' status, or a dedicated check-in endpoint
      const response = await api.post(`/v2/events/${event.id}/attendees/${attendeeId}/check-in`);
      if (response.success) {
        setAttendees((prev) =>
          prev.map((a) => a.id === attendeeId ? { ...a, checked_in: true } : a)
        );
        toast.success(t('toast.checkin_success'));
      } else {
        toast.error(t('toast.checkin_failed'));
      }
    } catch (err) {
      logError('Failed to check in attendee', err);
      toast.error(t('toast.something_wrong'));
    } finally {
      setCheckingInUserId(null);
    }
  }

  async function handleCancelEvent() {
    if (!event) return;

    try {
      setIsCancelling(true);
      const response = await api.post(`/v2/events/${event.id}/cancel`, { reason: cancelReason });
      if (response.success) {
        setEvent((prev) => prev ? { ...prev, status: 'cancelled', cancellation_reason: cancelReason } : null);
        toast.success(t('toast.event_cancelled'));
        setShowCancelModal(false);
      } else {
        toast.error(t('toast.cancel_failed'));
      }
    } catch (err) {
      logError('Failed to cancel event', err);
      toast.error(t('toast.something_wrong'));
    } finally {
      setIsCancelling(false);
    }
  }

  async function handleJoinWaitlist() {
    if (!event) return;

    try {
      setIsSubmitting(true);
      const response = await api.post(`/v2/events/${event.id}/waitlist`);
      if (response.success && response.data) {
        setIsWaitlisted(true);
        setWaitlistPosition((response.data as { position?: number }).position ?? null);
        toast.success(t('toast.added_to_waitlist'));
      } else {
        toast.error(t('toast.waitlist_join_failed'));
      }
    } catch (err) {
      logError('Failed to join waitlist', err);
      toast.error(t('toast.something_wrong'));
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleLeaveWaitlist() {
    if (!event) return;

    try {
      setIsSubmitting(true);
      const response = await api.delete(`/v2/events/${event.id}/waitlist`);
      if (response.success) {
        setIsWaitlisted(false);
        setWaitlistPosition(null);
        toast.success(t('toast.removed_from_waitlist'));
      }
    } catch (err) {
      logError('Failed to leave waitlist', err);
      toast.error(t('toast.something_wrong'));
    } finally {
      setIsSubmitting(false);
    }
  }

  const isOrganizer = user && event && user.id === event.organizer?.id;
  const isCancelled = event?.status === 'cancelled';
  const goingAttendees = attendees.filter((a) => a.rsvp_status === 'going' || a.rsvp_status === 'attending' || !a.rsvp_status);
  const checkedInCount = attendees.filter((a) => a.checked_in).length;

  if (isLoading) {
    return <LoadingScreen message={t('detail.loading')} />;
  }

  if (error && !event) {
    return (
      <div className="max-w-4xl mx-auto">
        <GlassCard className="p-8 text-center">
          <AlertCircle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary mb-2">{t('detail.unable_to_load')}</h2>
          <p className="text-theme-muted mb-4">{error}</p>
          <div className="flex justify-center gap-3">
            <Link to={tenantPath("/events")}>
              <Button
                variant="flat"
                className="bg-theme-elevated text-theme-primary"
              >
                {t('detail.browse_events')}
              </Button>
            </Link>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
              onPress={() => loadEvent()}
            >
              {t('detail.try_again')}
            </Button>
          </div>
        </GlassCard>
      </div>
    );
  }

  if (!event) {
    return (
      <EmptyState
        icon={<AlertCircle className="w-12 h-12" aria-hidden="true" />}
        title={t('detail.not_found')}
        description={t('detail.not_found_desc')}
        action={
          <Link to={tenantPath("/events")}>
            <Button className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white">
              {t('detail.browse_events')}
            </Button>
          </Link>
        }
      />
    );
  }

  const startDate = new Date(event.start_date);
  const endDate = event.end_date ? new Date(event.end_date) : null;
  const isPast = startDate < new Date();
  const goingCount = event.attendees_count ?? 0;
  const interestedCount = event.interested_count ?? 0;

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      className="max-w-4xl mx-auto space-y-6"
    >
      {/* Breadcrumbs */}
      <Breadcrumbs items={[
        { label: t('title'), href: tenantPath('/events') },
        { label: event?.title || t('title') },
      ]} />

      {/* Cover Image */}
      {event.cover_image && (
        <div className="rounded-xl overflow-hidden">
          <img
            src={resolveAssetUrl(event.cover_image)}
            alt={t('detail.cover_alt', { title: event.title })}
            className="w-full h-48 sm:h-64 object-cover"
            loading="lazy"
          />
        </div>
      )}

      {/* Main Content */}
      <GlassCard className="p-6 sm:p-8">
        {/* Header */}
        <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
          <div className="flex items-center gap-4">
            {/* Date Badge */}
            <div className="bg-gradient-to-br from-amber-500/20 to-orange-500/20 rounded-xl p-4 text-center">
              <div className="text-amber-400 text-sm font-medium uppercase">
                {startDate.toLocaleString('default', { month: 'short' })}
              </div>
              <div className="text-theme-primary text-3xl font-bold">
                {startDate.getDate()}
              </div>
            </div>

            <div className="flex flex-wrap gap-2">
              {isPast && (
                <Chip variant="flat" color="default" size="sm">
                  {t('detail.past_event')}
                </Chip>
              )}
              {event.category_name && (
                <Chip variant="flat" color="secondary" size="sm">
                  {event.category_name}
                </Chip>
              )}
            </div>
          </div>

          {isOrganizer && !isCancelled && (
            <div className="flex gap-2 flex-wrap">
              <Link to={tenantPath(`/events/${event.id}/edit`)}>
                <Button
                  size="sm"
                  variant="flat"
                  className="bg-theme-elevated text-theme-primary"
                  startContent={<Edit className="w-4 h-4" aria-hidden="true" />}
                >
                  {t('detail.edit')}
                </Button>
              </Link>
              <Button
                size="sm"
                variant="flat"
                className="bg-amber-500/10 text-amber-400"
                startContent={<Ban className="w-4 h-4" aria-hidden="true" />}
                onPress={() => setShowCancelModal(true)}
              >
                {t('detail.cancel_event')}
              </Button>
              <Button
                size="sm"
                variant="flat"
                className="bg-red-500/10 text-red-400"
                startContent={<Trash2 className="w-4 h-4" aria-hidden="true" />}
                onPress={() => setShowDeleteModal(true)}
              >
                {t('detail.delete')}
              </Button>
            </div>
          )}
        </div>

        {/* Title */}
        <h1 className="text-3xl font-bold text-theme-primary mb-4">{event.title}</h1>

        {/* E5: Cancellation Banner */}
        {isCancelled && (
          <div className="mb-6 p-4 rounded-xl bg-red-500/10 border border-red-500/30">
            <div className="flex items-center gap-3">
              <Ban className="w-5 h-5 text-red-400 flex-shrink-0" aria-hidden="true" />
              <div>
                <p className="text-red-400 font-semibold">{t('detail.event_cancelled')}</p>
                {event.cancellation_reason && (
                  <p className="text-red-300/80 text-sm mt-1">{t('detail.cancellation_reason', { reason: event.cancellation_reason })}</p>
                )}
              </div>
            </div>
          </div>
        )}

        {/* E1: Recurring event indicator */}
        {event.is_recurring && (
          <div className="mb-4">
            <Chip variant="flat" color="secondary" size="sm" startContent={<Repeat className="w-3 h-3" aria-hidden="true" />}>
              {t('detail.recurring_event')}
            </Chip>
          </div>
        )}

        {/* E7: Series link (navigable) */}
        {event.series && (
          <div className="mb-4 flex flex-wrap items-center gap-2">
            <Link to={tenantPath(`/events?series=${event.series.id}`)}>
              <Chip
                variant="flat"
                color="primary"
                size="sm"
                startContent={<Link2 className="w-3 h-3" aria-hidden="true" />}
                className="cursor-pointer hover:opacity-80 transition-opacity"
              >
                {event.series.title}
              </Chip>
            </Link>
            <span className="text-sm text-theme-subtle">
              {t('detail.events_in_series', { count: event.series.event_count })}
            </span>
          </div>
        )}

        {/* RSVP / Waitlist Status Display */}
        {isAuthenticated && (rsvpStatus || isWaitlisted) && (
          <div className="mb-6 flex flex-wrap gap-2">
            {rsvpStatus && (
              <Chip
                variant="flat"
                color={rsvpStatus === 'going' ? 'success' : rsvpStatus === 'interested' ? 'warning' : 'default'}
                size="lg"
                startContent={
                  rsvpStatus === 'going'
                    ? <CheckCircle2 className="w-4 h-4" aria-hidden="true" />
                    : rsvpStatus === 'interested'
                      ? <Heart className="w-4 h-4" aria-hidden="true" />
                      : <XCircle className="w-4 h-4" aria-hidden="true" />
                }
              >
                {rsvpStatus === 'going' ? t('detail.rsvp_going') : rsvpStatus === 'interested' ? t('detail.rsvp_interested') : t('detail.rsvp_not_going')}
              </Chip>
            )}
            {isWaitlisted && (
              <Chip variant="flat" color="warning" size="lg" startContent={<ListOrdered className="w-4 h-4" aria-hidden="true" />}>
                {t('detail.on_waitlist')}{waitlistPosition ? ` (#${waitlistPosition})` : ''}
              </Chip>
            )}
          </div>
        )}

        {/* Attendee Count Breakdown with Capacity Info (E2) */}
        <div className="flex flex-wrap items-center gap-2 sm:gap-4 mb-6">
          <div className="flex items-center gap-2 text-sm">
            <div className="w-2.5 h-2.5 rounded-full bg-emerald-500" />
            <span className="text-theme-primary font-medium">{goingCount}</span>
            <span className="text-theme-muted">{t('detail.going_count')}</span>
          </div>
          {interestedCount > 0 && (
            <div className="flex items-center gap-2 text-sm">
              <div className="w-2.5 h-2.5 rounded-full bg-amber-500" />
              <span className="text-theme-primary font-medium">{interestedCount}</span>
              <span className="text-theme-muted">{t('detail.interested_count')}</span>
            </div>
          )}
          {event.max_attendees != null && (
            <>
              <div className="flex items-center gap-2 text-sm">
                <div className="w-2.5 h-2.5 rounded-full bg-gray-400" />
                <span className="text-theme-muted">{t('detail.max_capacity', { count: event.max_attendees })}</span>
              </div>
              {event.spots_left != null && event.spots_left > 0 && (
                <Chip size="sm" variant="flat" color={event.spots_left <= 3 ? 'danger' : 'success'}>
                  {t('detail.spots_left', { count: event.spots_left })}
                </Chip>
              )}
              {event.is_full && (
                <Chip size="sm" variant="flat" color="danger">{t('detail.event_full')}</Chip>
              )}
            </>
          )}
          {(event.waitlist_count ?? 0) > 0 && (
            <div className="flex items-center gap-2 text-sm">
              <ListOrdered className="w-3.5 h-3.5 text-theme-subtle" aria-hidden="true" />
              <span className="text-theme-muted">{t('detail.waitlist_count', { count: event.waitlist_count })}</span>
            </div>
          )}
        </div>

        {/* Meta Grid */}
        <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
          <div className="flex items-center gap-3 text-theme-muted">
            <div className="p-2 rounded-lg bg-amber-500/20">
              <Calendar className="w-5 h-5 text-amber-600 dark:text-amber-400" aria-hidden="true" />
            </div>
            <div>
              <div className="text-xs text-theme-subtle">{t('detail.date_label')}</div>
              <time dateTime={event.start_date} className="text-theme-primary block">
                {startDate.toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' })}
              </time>
            </div>
          </div>

          <div className="flex items-center gap-3 text-theme-muted">
            <div className="p-2 rounded-lg bg-indigo-500/20">
              <Clock className="w-5 h-5 text-indigo-600 dark:text-indigo-400" aria-hidden="true" />
            </div>
            <div>
              <div className="text-xs text-theme-subtle">{t('detail.time_label')}</div>
              <div className="text-theme-primary">
                <time dateTime={event.start_date}>
                  {startDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                </time>
                {endDate && (
                  <>
                    {' - '}
                    <time dateTime={event.end_date!}>
                      {endDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                    </time>
                  </>
                )}
              </div>
            </div>
          </div>

          {event.location && (
            <div className="flex items-center gap-3 text-theme-muted">
              <div className="p-2 rounded-lg bg-emerald-500/20">
                <MapPin className="w-5 h-5 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
              </div>
              <div>
                <div className="text-xs text-theme-subtle">{t('detail.location_label')}</div>
                <div className="text-theme-primary">{event.location}</div>
              </div>
            </div>
          )}
        </div>

        {/* Location Map */}
        {event.location && !event.is_online && event.coordinates?.lat && event.coordinates?.lng && (
          <LocationMapCard
            title={t('detail.event_location')}
            locationText={event.location}
            markers={[{
              id: event.id,
              lat: Number(event.coordinates.lat),
              lng: Number(event.coordinates.lng),
              title: event.title,
            }]}
            center={{ lat: Number(event.coordinates.lat), lng: Number(event.coordinates.lng) }}
            mapHeight="250px"
            zoom={15}
            className="mt-6"
          />
        )}

        {/* Tabs: Details / Attendees / Check-in (organizer only) */}
        <Tabs
          selectedKey={activeTab}
          onSelectionChange={(key) => setActiveTab(key as string)}
          variant="underlined"
          classNames={{
            tabList: 'border-b border-theme-default mb-6',
            tab: 'text-theme-muted data-[selected=true]:text-theme-primary',
            cursor: 'bg-gradient-to-r from-indigo-500 to-purple-600',
          }}
        >
          <Tab key="details" title={t('detail.tab_details')} />
          <Tab
            key="attendees"
            title={
              <span className="flex items-center gap-2">
                {t('detail.tab_attendees')}
                <Chip size="sm" variant="flat" color="default">{goingCount + interestedCount}</Chip>
              </span>
            }
          />
          {isOrganizer && (
            <Tab
              key="checkin"
              title={
                <span className="flex items-center gap-2">
                  <ClipboardCheck className="w-4 h-4" aria-hidden="true" />
                  {t('detail.tab_checkin')}
                  {checkedInCount > 0 && (
                    <Chip size="sm" variant="flat" color="success">{checkedInCount}</Chip>
                  )}
                </span>
              }
            />
          )}
        </Tabs>

        {/* Tab Content */}
        <AnimatePresence mode="wait">
          {activeTab === 'details' && (
            <motion.div
              key="details"
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -10 }}
            >
              {/* Description */}
              <div className="mb-8">
                <h2 className="text-lg font-semibold text-theme-primary mb-3">{t('detail.about')}</h2>
                <div className="prose prose-invert max-w-none">
                  <p className="text-theme-muted whitespace-pre-wrap">{event.description}</p>
                </div>
              </div>

              {/* Organizer */}
              {event.organizer && (
                <div className="mb-8">
                  <h2 className="text-lg font-semibold text-theme-primary mb-3">{t('detail.organized_by')}</h2>
                  <div className="flex items-center gap-3">
                    <Avatar
                      src={resolveAvatarUrl(event.organizer.avatar)}
                      name={`${event.organizer.first_name} ${event.organizer.last_name}`}
                      size="sm"
                    />
                    <span className="text-theme-primary font-medium">
                      {event.organizer.first_name} {event.organizer.last_name}
                    </span>
                  </div>
                </div>
              )}

              {/* Quick Attendee Preview */}
              {attendees.length > 0 && (
                <div className="mb-8">
                  <h2 className="text-lg font-semibold text-theme-primary mb-4 flex items-center gap-2">
                    <Users className="w-5 h-5 text-purple-600 dark:text-purple-400" aria-hidden="true" />
                    {t('detail.attendees')}
                  </h2>
                  <div className="flex items-center gap-4">
                    <AvatarGroup max={8}>
                      {attendees.map((attendee) => (
                        <Avatar
                          key={attendee.id}
                          src={resolveAvatarUrl(attendee.avatar)}
                          name={attendee.name || `${attendee.first_name} ${attendee.last_name}`}
                          size="sm"
                          className="ring-2 ring-black/50"
                        />
                      ))}
                    </AvatarGroup>
                    {(goingCount + interestedCount) > attendees.length && (
                      <span className="text-theme-subtle text-sm">
                        {t('detail.more_attendees', { count: (goingCount + interestedCount) - attendees.length })}
                      </span>
                    )}
                  </div>
                </div>
              )}
            </motion.div>
          )}

          {activeTab === 'attendees' && (
            <motion.div
              key="attendees"
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -10 }}
              className="space-y-4"
            >
              {attendees.length === 0 ? (
                <div className="text-center py-8">
                  <Users className="w-10 h-10 text-theme-subtle mx-auto mb-3" aria-hidden="true" />
                  <p className="text-theme-muted">{t('detail.no_attendees')}</p>
                </div>
              ) : (
                <div className="space-y-2">
                  {attendees.map((attendee) => (
                    <div key={attendee.id} className="flex items-center gap-3 p-3 rounded-lg bg-theme-elevated">
                      <Avatar
                        src={resolveAvatarUrl(attendee.avatar)}
                        name={attendee.name || `${attendee.first_name} ${attendee.last_name}`}
                        size="sm"
                      />
                      <div className="flex-1 min-w-0">
                        <p className="text-theme-primary font-medium truncate">
                          {attendee.name || `${attendee.first_name || ''} ${attendee.last_name || ''}`.trim()}
                        </p>
                      </div>
                      <Chip
                        size="sm"
                        variant="flat"
                        color={
                          attendee.rsvp_status === 'going' || attendee.rsvp_status === 'attending'
                            ? 'success'
                            : attendee.rsvp_status === 'interested' || attendee.rsvp_status === 'maybe'
                              ? 'warning'
                              : 'default'
                        }
                      >
                        {attendee.rsvp_status === 'going' || attendee.rsvp_status === 'attending'
                          ? t('detail.attendee_going')
                          : attendee.rsvp_status === 'interested' || attendee.rsvp_status === 'maybe'
                            ? t('detail.attendee_interested')
                            : t('detail.attendee_rsvp')}
                      </Chip>
                      {attendee.checked_in && (
                        <Chip size="sm" variant="flat" color="success" startContent={<UserCheck className="w-3 h-3" aria-hidden="true" />}>
                          {t('detail.attendee_checked_in')}
                        </Chip>
                      )}
                    </div>
                  ))}
                </div>
              )}
            </motion.div>
          )}

          {activeTab === 'checkin' && isOrganizer && (
            <motion.div
              key="checkin"
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -10 }}
              className="space-y-4"
            >
              {/* Check-in Stats */}
              <GlassCard className="p-4">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-theme-muted text-sm">{t('detail.checkin_progress')}</p>
                    <p className="text-2xl font-bold text-theme-primary">
                      {checkedInCount} <span className="text-base font-normal text-theme-muted">/ {goingAttendees.length}</span>
                    </p>
                  </div>
                  <div className="w-16 h-16 relative">
                    <svg className="w-16 h-16 -rotate-90" viewBox="0 0 64 64">
                      <circle
                        cx="32" cy="32" r="28"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="6"
                        className="text-theme-muted"
                      />
                      <circle
                        cx="32" cy="32" r="28"
                        fill="none"
                        stroke="url(#checkinGradient)"
                        strokeWidth="6"
                        strokeLinecap="round"
                        strokeDasharray={`${2 * Math.PI * 28}`}
                        strokeDashoffset={`${2 * Math.PI * 28 * (1 - (goingAttendees.length > 0 ? checkedInCount / goingAttendees.length : 0))}`}
                      />
                      <defs>
                        <linearGradient id="checkinGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                          <stop offset="0%" stopColor="#6366f1" />
                          <stop offset="100%" stopColor="#a855f7" />
                        </linearGradient>
                      </defs>
                    </svg>
                    <span className="absolute inset-0 flex items-center justify-center text-sm font-bold text-theme-primary">
                      {goingAttendees.length > 0 ? Math.round((checkedInCount / goingAttendees.length) * 100) : 0}%
                    </span>
                  </div>
                </div>
              </GlassCard>

              {/* Attendee Check-in List */}
              {goingAttendees.length === 0 ? (
                <div className="text-center py-8">
                  <UserCheck className="w-10 h-10 text-theme-subtle mx-auto mb-3" aria-hidden="true" />
                  <p className="text-theme-muted">{t('detail.no_checkin_attendees')}</p>
                </div>
              ) : (
                <div className="space-y-2">
                  {goingAttendees.map((attendee) => (
                    <div key={attendee.id} className="flex items-center gap-3 p-3 rounded-lg bg-theme-elevated">
                      <Avatar
                        src={resolveAvatarUrl(attendee.avatar)}
                        name={attendee.name || `${attendee.first_name} ${attendee.last_name}`}
                        size="sm"
                      />
                      <div className="flex-1 min-w-0">
                        <p className="text-theme-primary font-medium truncate">
                          {attendee.name || `${attendee.first_name || ''} ${attendee.last_name || ''}`.trim()}
                        </p>
                      </div>
                      {attendee.checked_in ? (
                        <Chip
                          size="sm"
                          variant="flat"
                          color="success"
                          startContent={<CheckCircle2 className="w-3 h-3" aria-hidden="true" />}
                        >
                          {t('detail.attendee_checked_in')}
                        </Chip>
                      ) : (
                        <Button
                          size="sm"
                          className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                          startContent={<UserCheck className="w-3.5 h-3.5" aria-hidden="true" />}
                          isLoading={checkingInUserId === attendee.id}
                          onPress={() => handleCheckIn(attendee.id)}
                        >
                          {t('detail.check_in')}
                        </Button>
                      )}
                    </div>
                  ))}
                </div>
              )}
            </motion.div>
          )}
        </AnimatePresence>

        {/* Action Buttons */}
        {isAuthenticated && !isPast && !isCancelled && (
          <div className="flex flex-col sm:flex-row gap-3 pt-6 border-t border-theme-default mt-8">
            {/* RSVP Options */}
            <div className="flex gap-2 flex-wrap" role="group" aria-label={t('detail.rsvp_aria')}>
              <Button
                className={
                  rsvpStatus === 'going'
                    ? 'bg-emerald-500 text-white'
                    : 'bg-theme-elevated text-theme-primary hover:bg-emerald-500/20'
                }
                startContent={<CheckCircle2 className="w-4 h-4" aria-hidden="true" />}
                onPress={() => handleRsvp('going')}
                isLoading={isSubmitting}
                aria-pressed={rsvpStatus === 'going'}
              >
                {t('detail.going_btn')}
              </Button>
              <Button
                className={
                  rsvpStatus === 'interested'
                    ? 'bg-amber-500 text-white'
                    : 'bg-theme-elevated text-theme-primary hover:bg-amber-500/20'
                }
                startContent={<Heart className="w-4 h-4" aria-hidden="true" />}
                onPress={() => handleRsvp('interested')}
                isLoading={isSubmitting}
                aria-pressed={rsvpStatus === 'interested'}
              >
                {t('detail.interested_btn')}
              </Button>
              <Button
                className={
                  rsvpStatus === 'not_going'
                    ? 'bg-theme-hover text-theme-muted'
                    : 'bg-theme-elevated text-theme-primary hover:bg-theme-hover'
                }
                variant="flat"
                startContent={<XCircle className="w-4 h-4" aria-hidden="true" />}
                onPress={() => handleRsvp('not_going')}
                isLoading={isSubmitting}
                aria-pressed={rsvpStatus === 'not_going'}
              >
                {t('detail.not_going_btn')}
              </Button>

              {/* E3: Waitlist join/leave button when event is full */}
              {event.is_full && !rsvpStatus && !isWaitlisted && (
                <Button
                  className="bg-theme-elevated text-theme-primary hover:bg-amber-500/20"
                  startContent={<ListOrdered className="w-4 h-4" aria-hidden="true" />}
                  onPress={handleJoinWaitlist}
                  isLoading={isSubmitting}
                >
                  {t('detail.join_waitlist')}
                </Button>
              )}
              {isWaitlisted && (
                <Button
                  className="bg-amber-500/10 text-amber-400"
                  variant="flat"
                  startContent={<XCircle className="w-4 h-4" aria-hidden="true" />}
                  onPress={handleLeaveWaitlist}
                  isLoading={isSubmitting}
                >
                  {t('detail.leave_waitlist')}
                </Button>
              )}
            </div>

            {/* Share */}
            <Button
              variant="flat"
              className="bg-theme-elevated text-theme-primary"
              startContent={<Copy className="w-4 h-4" aria-hidden="true" />}
              onPress={handleShare}
            >
              {t('detail.share')}
            </Button>

            {/* Online event link */}
            {event.online_url && (
              <a href={event.online_url} target="_blank" rel="noopener noreferrer">
                <Button
                  variant="flat"
                  className="bg-theme-elevated text-theme-primary"
                  startContent={<ExternalLink className="w-4 h-4" aria-hidden="true" />}
                >
                  {t('detail.event_link')}
                </Button>
              </a>
            )}
          </div>
        )}
      </GlassCard>

      {/* E7: Other Events in This Series */}
      {event.series && (
        <GlassCard className="p-6">
          <h2 className="text-lg font-semibold text-theme-primary mb-4 flex items-center gap-2">
            <CalendarRange className="w-5 h-5 text-indigo-600 dark:text-indigo-400" aria-hidden="true" />
            {t('detail.other_series_events')}
          </h2>

          {isLoadingSeriesEvents ? (
            <div className="space-y-3">
              {[1, 2, 3].map((i) => (
                <Skeleton key={i} className="w-full h-16 rounded-lg" />
              ))}
            </div>
          ) : seriesEvents.length > 0 ? (
            <div className="space-y-3">
              {seriesEvents.map((seriesEvent) => {
                const evtDate = new Date(seriesEvent.start_date);
                return (
                  <Link key={seriesEvent.id} to={tenantPath(`/events/${seriesEvent.id}`)}>
                    <Card
                      isPressable
                      className="bg-theme-elevated border border-theme-default hover:border-indigo-500/50 transition-colors"
                    >
                      <CardBody className="flex flex-row items-center gap-4 p-3">
                        {/* Mini Date Badge */}
                        <div className="bg-gradient-to-br from-amber-500/20 to-orange-500/20 rounded-lg p-2 text-center min-w-[48px]">
                          <div className="text-amber-400 text-[10px] font-medium uppercase leading-none">
                            {evtDate.toLocaleString('default', { month: 'short' })}
                          </div>
                          <div className="text-theme-primary text-lg font-bold leading-tight">
                            {evtDate.getDate()}
                          </div>
                        </div>

                        <div className="flex-1 min-w-0">
                          <p className="text-theme-primary font-medium truncate">
                            {seriesEvent.title}
                          </p>
                          <p className="text-theme-subtle text-sm">
                            {evtDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                            {seriesEvent.location && ` \u00B7 ${seriesEvent.location}`}
                          </p>
                        </div>

                        <ArrowRight className="w-4 h-4 text-theme-subtle flex-shrink-0" aria-hidden="true" />
                      </CardBody>
                    </Card>
                  </Link>
                );
              })}

              {/* View all link */}
              {event.series.event_count > 5 && (
                <Link to={tenantPath(`/events?series=${event.series.id}`)} className="block text-center">
                  <Button
                    variant="flat"
                    size="sm"
                    className="bg-theme-elevated text-theme-primary"
                    endContent={<ArrowRight className="w-3.5 h-3.5" aria-hidden="true" />}
                  >
                    {t('detail.view_all_series', { count: event.series.event_count })}
                  </Button>
                </Link>
              )}
            </div>
          ) : (
            <p className="text-theme-subtle text-sm text-center py-4">
              {t('detail.no_other_series_events')}
            </p>
          )}
        </GlassCard>
      )}

      {/* Delete Confirmation Modal */}
      <Modal
        isOpen={showDeleteModal}
        onOpenChange={setShowDeleteModal}
        classNames={{
          base: 'bg-content1 border border-theme-default',
          header: 'border-b border-theme-default',
          body: 'py-6',
          footer: 'border-t border-theme-default',
        }}
      >
        <ModalContent>
          <ModalHeader className="text-theme-primary">{t('detail.delete_modal_title')}</ModalHeader>
          <ModalBody>
            <p className="text-theme-muted">
              {t('detail.delete_confirm', { title: event.title })}
            </p>
          </ModalBody>
          <ModalFooter>
            <Button
              variant="flat"
              className="bg-theme-elevated text-theme-primary"
              onPress={() => setShowDeleteModal(false)}
            >
              {t('detail.cancel')}
            </Button>
            <Button
              className="bg-red-500 text-white"
              onPress={handleDelete}
              isLoading={isDeleting}
            >
              {t('detail.delete_confirm_btn')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* E5: Cancel Event Modal */}
      <Modal
        isOpen={showCancelModal}
        onOpenChange={setShowCancelModal}
        classNames={{
          base: 'bg-content1 border border-theme-default',
          header: 'border-b border-theme-default',
          body: 'py-6',
          footer: 'border-t border-theme-default',
        }}
      >
        <ModalContent>
          <ModalHeader className="text-theme-primary">{t('detail.cancel_modal_title')}</ModalHeader>
          <ModalBody>
            <p className="text-theme-muted mb-4">
              {t('detail.cancel_confirm', { title: event.title })}
            </p>
            <Textarea
              placeholder={t('detail.cancel_reason_placeholder')}
              value={cancelReason}
              onValueChange={setCancelReason}
              minRows={3}
              maxRows={6}
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
              }}
            />
          </ModalBody>
          <ModalFooter>
            <Button
              variant="flat"
              className="bg-theme-elevated text-theme-primary"
              onPress={() => setShowCancelModal(false)}
            >
              {t('detail.keep_event')}
            </Button>
            <Button
              className="bg-amber-500 text-white"
              startContent={<Ban className="w-4 h-4" aria-hidden="true" />}
              onPress={handleCancelEvent}
              isLoading={isCancelling}
            >
              {t('detail.cancel_event')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </motion.div>
  );
}

export default EventDetailPage;
