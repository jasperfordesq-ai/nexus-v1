// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Event Detail Page - Single event view with enhanced RSVP, sharing, and organizer check-in
 */

import { useState, useEffect, useCallback } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
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
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { Breadcrumbs } from '@/components/navigation';
import { LoadingScreen, EmptyState } from '@/components/feedback';
import { LocationMapCard } from '@/components/location';
import { useAuth, useToast, useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl } from '@/lib/helpers';
import type { Event, User } from '@/types/api';

type RsvpOption = 'going' | 'interested' | 'not_going';

interface AttendeeWithCheckIn extends User {
  checked_in?: boolean;
  rsvp_status?: string;
}

export function EventDetailPage() {
  usePageTitle('Event');
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
  const [activeTab, setActiveTab] = useState('details');
  const [checkingInUserId, setCheckingInUserId] = useState<number | null>(null);

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
        api.get<AttendeeWithCheckIn[]>(`/v2/events/${id}/attendees?limit=50`).catch(() => ({ success: true, data: [] })),
      ]);

      if (eventRes.success && eventRes.data) {
        setEvent(eventRes.data);
        setRsvpStatus(normalizeRsvpStatus(eventRes.data.rsvp_status));
      } else {
        setError('Event not found or has been removed');
      }
      if (attendeesRes.success && attendeesRes.data) {
        setAttendees(attendeesRes.data);
      }
    } catch (err) {
      logError('Failed to load event', err);
      setError('Failed to load event. Please try again.');
    } finally {
      setIsLoading(false);
    }
  }, [id]);

  useEffect(() => {
    loadEvent();
  }, [loadEvent]);

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
          toast.success('RSVP removed');
        } else {
          toast.error('Failed to cancel RSVP');
        }
      } catch (err) {
        logError('Failed to cancel RSVP', err);
        toast.error('Something went wrong');
      } finally {
        setIsSubmitting(false);
      }
      return;
    }

    try {
      setIsSubmitting(true);
      const response = await api.post(`/v2/events/${event.id}/rsvp`, { status: newStatus });
      if (response.success) {
        const prevStatus = rsvpStatus;
        setRsvpStatus(newStatus);

        // Update counts optimistically
        setEvent((prev) => {
          if (!prev) return null;
          let goingCount = prev.attendees_count ?? 0;
          let interestedCount = prev.interested_count ?? 0;

          // Decrement old status count
          if (prevStatus === 'going') goingCount = Math.max(0, goingCount - 1);
          if (prevStatus === 'interested') interestedCount = Math.max(0, interestedCount - 1);

          // Increment new status count
          if (newStatus === 'going') goingCount += 1;
          if (newStatus === 'interested') interestedCount += 1;

          return {
            ...prev,
            attendees_count: goingCount,
            interested_count: interestedCount,
          };
        });

        const messages: Record<RsvpOption, string> = {
          going: "You're going!",
          interested: "Marked as interested",
          not_going: "Marked as not going",
        };
        toast.success(messages[newStatus]);
      } else {
        toast.error('Failed to update RSVP');
      }
    } catch (err) {
      logError('Failed to update RSVP', err);
      toast.error('Something went wrong');
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleShare() {
    const url = window.location.href;
    try {
      await navigator.clipboard.writeText(url);
      toast.success('Event link copied to clipboard');
    } catch {
      // Fallback for older browsers
      toast.error('Failed to copy link');
    }
  }

  async function handleDelete() {
    if (!event) return;

    try {
      setIsDeleting(true);
      const response = await api.delete(`/v2/events/${event.id}`);
      if (response.success) {
        toast.success('Event deleted');
        navigate(tenantPath('/events'));
      } else {
        toast.error('Failed to delete event');
      }
    } catch (err) {
      logError('Failed to delete event', err);
      toast.error('Something went wrong');
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
        toast.success('Attendee checked in');
      } else {
        toast.error('Failed to check in attendee');
      }
    } catch (err) {
      logError('Failed to check in attendee', err);
      toast.error('Something went wrong');
    } finally {
      setCheckingInUserId(null);
    }
  }

  const isOrganizer = user && event && user.id === event.organizer?.id;
  const goingAttendees = attendees.filter((a) => a.rsvp_status === 'going' || a.rsvp_status === 'attending' || !a.rsvp_status);
  const checkedInCount = attendees.filter((a) => a.checked_in).length;

  if (isLoading) {
    return <LoadingScreen message="Loading event..." />;
  }

  if (error && !event) {
    return (
      <div className="max-w-4xl mx-auto">
        <GlassCard className="p-8 text-center">
          <AlertCircle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary mb-2">Unable to Load Event</h2>
          <p className="text-theme-muted mb-4">{error}</p>
          <div className="flex justify-center gap-3">
            <Link to={tenantPath("/events")}>
              <Button
                variant="flat"
                className="bg-theme-elevated text-theme-primary"
              >
                Browse Events
              </Button>
            </Link>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
              onPress={() => loadEvent()}
            >
              Try Again
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
        title="Event Not Found"
        description="The event you are looking for does not exist"
        action={
          <Link to={tenantPath("/events")}>
            <Button className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white">
              Browse Events
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
        { label: 'Events', href: tenantPath('/events') },
        { label: event?.title || 'Event' },
      ]} />

      {/* Cover Image */}
      {event.cover_image && (
        <div className="rounded-xl overflow-hidden">
          <img
            src={event.cover_image}
            alt={`Cover for ${event.title}`}
            className="w-full h-48 sm:h-64 object-cover"
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
                  Past Event
                </Chip>
              )}
              {event.category_name && (
                <Chip variant="flat" color="secondary" size="sm">
                  {event.category_name}
                </Chip>
              )}
            </div>
          </div>

          {isOrganizer && (
            <div className="flex gap-2">
              <Link to={tenantPath(`/events/${event.id}/edit`)}>
                <Button
                  size="sm"
                  variant="flat"
                  className="bg-theme-elevated text-theme-primary"
                  startContent={<Edit className="w-4 h-4" aria-hidden="true" />}
                >
                  Edit
                </Button>
              </Link>
              <Button
                size="sm"
                variant="flat"
                className="bg-red-500/10 text-red-400"
                startContent={<Trash2 className="w-4 h-4" aria-hidden="true" />}
                onPress={() => setShowDeleteModal(true)}
              >
                Delete
              </Button>
            </div>
          )}
        </div>

        {/* Title */}
        <h1 className="text-3xl font-bold text-theme-primary mb-4">{event.title}</h1>

        {/* RSVP Status Display */}
        {isAuthenticated && rsvpStatus && (
          <div className="mb-6">
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
              {rsvpStatus === 'going' ? "You're Going" : rsvpStatus === 'interested' ? "You're Interested" : "Not Going"}
            </Chip>
          </div>
        )}

        {/* Attendee Count Breakdown */}
        <div className="flex flex-wrap items-center gap-2 sm:gap-4 mb-6">
          <div className="flex items-center gap-2 text-sm">
            <div className="w-2.5 h-2.5 rounded-full bg-emerald-500" />
            <span className="text-theme-primary font-medium">{goingCount}</span>
            <span className="text-theme-muted">going</span>
          </div>
          {interestedCount > 0 && (
            <div className="flex items-center gap-2 text-sm">
              <div className="w-2.5 h-2.5 rounded-full bg-amber-500" />
              <span className="text-theme-primary font-medium">{interestedCount}</span>
              <span className="text-theme-muted">interested</span>
            </div>
          )}
          {event.max_attendees && (
            <div className="flex items-center gap-2 text-sm">
              <div className="w-2.5 h-2.5 rounded-full bg-gray-400" />
              <span className="text-theme-muted">{event.max_attendees} max capacity</span>
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
              <div className="text-xs text-theme-subtle">Date</div>
              <time dateTime={event.start_date} className="text-theme-primary block">
                {startDate.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' })}
              </time>
            </div>
          </div>

          <div className="flex items-center gap-3 text-theme-muted">
            <div className="p-2 rounded-lg bg-indigo-500/20">
              <Clock className="w-5 h-5 text-indigo-600 dark:text-indigo-400" aria-hidden="true" />
            </div>
            <div>
              <div className="text-xs text-theme-subtle">Time</div>
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
                <div className="text-xs text-theme-subtle">Location</div>
                <div className="text-theme-primary">{event.location}</div>
              </div>
            </div>
          )}
        </div>

        {/* Location Map */}
        {event.location && !event.is_online && event.coordinates?.lat && event.coordinates?.lng && (
          <LocationMapCard
            title="Event Location"
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
          <Tab key="details" title="Details" />
          <Tab
            key="attendees"
            title={
              <span className="flex items-center gap-2">
                Attendees
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
                  Check-in
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
                <h2 className="text-lg font-semibold text-theme-primary mb-3">About this event</h2>
                <div className="prose prose-invert max-w-none">
                  <p className="text-theme-muted whitespace-pre-wrap">{event.description}</p>
                </div>
              </div>

              {/* Organizer */}
              {event.organizer && (
                <div className="mb-8">
                  <h2 className="text-lg font-semibold text-theme-primary mb-3">Organized by</h2>
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
                    Attendees
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
                        +{(goingCount + interestedCount) - attendees.length} more
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
                  <p className="text-theme-muted">No attendees yet. Be the first to RSVP!</p>
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
                          ? 'Going'
                          : attendee.rsvp_status === 'interested' || attendee.rsvp_status === 'maybe'
                            ? 'Interested'
                            : 'RSVP'}
                      </Chip>
                      {attendee.checked_in && (
                        <Chip size="sm" variant="flat" color="success" startContent={<UserCheck className="w-3 h-3" aria-hidden="true" />}>
                          Checked in
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
                    <p className="text-theme-muted text-sm">Check-in Progress</p>
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
                  <p className="text-theme-muted">No attendees to check in yet.</p>
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
                          Checked in
                        </Chip>
                      ) : (
                        <Button
                          size="sm"
                          className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                          startContent={<UserCheck className="w-3.5 h-3.5" aria-hidden="true" />}
                          isLoading={checkingInUserId === attendee.id}
                          onPress={() => handleCheckIn(attendee.id)}
                        >
                          Check in
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
        {isAuthenticated && !isPast && (
          <div className="flex flex-col sm:flex-row gap-3 pt-6 border-t border-theme-default mt-8">
            {/* RSVP Options */}
            <div className="flex gap-2" role="group" aria-label="RSVP options">
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
                Going
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
                Interested
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
                Not Going
              </Button>
            </div>

            {/* Share */}
            <Button
              variant="flat"
              className="bg-theme-elevated text-theme-primary"
              startContent={<Copy className="w-4 h-4" aria-hidden="true" />}
              onPress={handleShare}
            >
              Share
            </Button>

            {/* Online event link */}
            {event.online_url && (
              <a href={event.online_url} target="_blank" rel="noopener noreferrer">
                <Button
                  variant="flat"
                  className="bg-theme-elevated text-theme-primary"
                  startContent={<ExternalLink className="w-4 h-4" aria-hidden="true" />}
                >
                  Event Link
                </Button>
              </a>
            )}
          </div>
        )}
      </GlassCard>

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
          <ModalHeader className="text-theme-primary">Delete Event</ModalHeader>
          <ModalBody>
            <p className="text-theme-muted">
              Are you sure you want to delete &ldquo;{event.title}&rdquo;? This action cannot be undone.
            </p>
          </ModalBody>
          <ModalFooter>
            <Button
              variant="flat"
              className="bg-theme-elevated text-theme-primary"
              onPress={() => setShowDeleteModal(false)}
            >
              Cancel
            </Button>
            <Button
              className="bg-red-500 text-white"
              onPress={handleDelete}
              isLoading={isDeleting}
            >
              Delete Event
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </motion.div>
  );
}

export default EventDetailPage;
