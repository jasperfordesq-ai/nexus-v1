/**
 * Event Detail Page - Single event view
 */

import { useState, useEffect, useCallback } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { motion } from 'framer-motion';
import {
  Button,
  Avatar,
  AvatarGroup,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
} from '@heroui/react';
import {
  ArrowLeft,
  Calendar,
  Clock,
  MapPin,
  Users,
  Share2,
  Edit,
  Trash2,
  UserPlus,
  UserMinus,
  ExternalLink,
  AlertCircle,
  RefreshCw,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { LoadingScreen, EmptyState } from '@/components/feedback';
import { useAuth, useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl } from '@/lib/helpers';
import type { Event, User } from '@/types/api';

export function EventDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { user, isAuthenticated } = useAuth();
  const toast = useToast();

  const [event, setEvent] = useState<Event | null>(null);
  const [attendees, setAttendees] = useState<User[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isAttending, setIsAttending] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isDeleting, setIsDeleting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [showDeleteModal, setShowDeleteModal] = useState(false);

  const loadEvent = useCallback(async () => {
    if (!id) return;

    try {
      setIsLoading(true);
      setError(null);
      const [eventRes, attendeesRes] = await Promise.all([
        api.get<Event>(`/v2/events/${id}`),
        api.get<User[]>(`/v2/events/${id}/attendees?limit=10`).catch(() => ({ success: true, data: [] })),
      ]);

      if (eventRes.success && eventRes.data) {
        setEvent(eventRes.data);
        setIsAttending(eventRes.data.rsvp_status === 'attending');
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

  async function handleRsvp() {
    if (!event || !isAuthenticated) return;

    try {
      setIsSubmitting(true);
      if (isAttending) {
        const response = await api.delete(`/v2/events/${event.id}/rsvp`);
        if (response.success) {
          setIsAttending(false);
          setEvent((prev) => prev ? { ...prev, attendees_count: (prev.attendees_count ?? 1) - 1 } : null);
          toast.success('RSVP cancelled');
        } else {
          toast.error('Failed to cancel RSVP');
        }
      } else {
        const response = await api.post(`/v2/events/${event.id}/rsvp`);
        if (response.success) {
          setIsAttending(true);
          setEvent((prev) => prev ? { ...prev, attendees_count: (prev.attendees_count ?? 0) + 1 } : null);
          toast.success("You're attending!");
        } else {
          toast.error('Failed to RSVP');
        }
      }
    } catch (err) {
      logError('Failed to update RSVP', err);
      toast.error('Something went wrong');
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleDelete() {
    if (!event) return;

    try {
      setIsDeleting(true);
      const response = await api.delete(`/v2/events/${event.id}`);
      if (response.success) {
        toast.success('Event deleted');
        navigate('/events');
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

  const isOrganizer = user && event && user.id === event.organizer?.id;

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
            <Button
              variant="flat"
              className="bg-theme-elevated text-theme-primary"
              startContent={<ArrowLeft className="w-4 h-4" aria-hidden="true" />}
              onPress={() => navigate(-1)}
            >
              Go Back
            </Button>
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
          <Link to="/events">
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

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      className="max-w-4xl mx-auto space-y-6"
    >
      {/* Back Button */}
      <button
        onClick={() => navigate(-1)}
        className="flex items-center gap-2 text-theme-muted hover:text-theme-primary transition-colors"
        aria-label="Go back to events"
      >
        <ArrowLeft className="w-4 h-4" aria-hidden="true" />
        Back to events
      </button>

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

            {isPast && (
              <span className="px-3 py-1 rounded-full bg-theme-hover text-theme-muted text-sm">
                Past Event
              </span>
            )}
          </div>

          {isOrganizer && (
            <div className="flex gap-2">
              <Link to={`/events/${event.id}/edit`}>
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

        {/* Description */}
        <div className="mb-8">
          <h2 className="text-lg font-semibold text-theme-primary mb-3">About this event</h2>
          <div className="prose prose-invert max-w-none">
            <p className="text-theme-muted whitespace-pre-wrap">{event.description}</p>
          </div>
        </div>

        {/* Attendees */}
        <div className="mb-8">
          <h2 className="text-lg font-semibold text-theme-primary mb-4 flex items-center gap-2">
            <Users className="w-5 h-5 text-purple-600 dark:text-purple-400" aria-hidden="true" />
            Attendees ({event.attendees_count ?? 0})
          </h2>

          {attendees.length > 0 ? (
            <div className="flex items-center gap-4">
              <AvatarGroup max={8}>
                {attendees.map((attendee) => (
                  <Avatar
                    key={attendee.id}
                    src={resolveAvatarUrl(attendee.avatar)}
                    name={attendee.name}
                    size="sm"
                    className="ring-2 ring-black/50"
                  />
                ))}
              </AvatarGroup>
              {(event.attendees_count ?? 0) > attendees.length && (
                <span className="text-theme-subtle text-sm">
                  +{(event.attendees_count ?? 0) - attendees.length} more
                </span>
              )}
            </div>
          ) : (
            <p className="text-theme-subtle">No attendees yet. Be the first!</p>
          )}
        </div>

        {/* Action Buttons */}
        {isAuthenticated && !isPast && (
          <div className="flex flex-wrap gap-3 pt-6 border-t border-theme-default">
            <Button
              className={isAttending
                ? 'bg-theme-hover text-theme-primary'
                : 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white'
              }
              startContent={isAttending ? <UserMinus className="w-4 h-4" aria-hidden="true" /> : <UserPlus className="w-4 h-4" aria-hidden="true" />}
              onPress={handleRsvp}
              isLoading={isSubmitting}
            >
              {isAttending ? 'Cancel RSVP' : 'RSVP'}
            </Button>
            <Button
              variant="flat"
              className="bg-theme-elevated text-theme-primary"
              startContent={<Share2 className="w-4 h-4" aria-hidden="true" />}
            >
              Share
            </Button>
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
          base: 'bg-theme-card border border-theme-default',
          header: 'border-b border-theme-default',
          body: 'py-6',
          footer: 'border-t border-theme-default',
        }}
      >
        <ModalContent>
          <ModalHeader className="text-theme-primary">Delete Event</ModalHeader>
          <ModalBody>
            <p className="text-theme-muted">
              Are you sure you want to delete "{event.title}"? This action cannot be undone.
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
