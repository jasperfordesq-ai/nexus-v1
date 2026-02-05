/**
 * Event Detail Page - Single event view
 */

import { useState, useEffect } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Avatar, AvatarGroup } from '@heroui/react';
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
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { LoadingScreen, EmptyState } from '@/components/feedback';
import { useAuth } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl } from '@/lib/helpers';
import type { Event, User } from '@/types/api';

export function EventDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { user, isAuthenticated } = useAuth();

  const [event, setEvent] = useState<Event | null>(null);
  const [attendees, setAttendees] = useState<User[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isAttending, setIsAttending] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    loadEvent();
  }, [id]);

  async function loadEvent() {
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
      setError('Event not found or has been removed');
    } finally {
      setIsLoading(false);
    }
  }

  async function handleRsvp() {
    if (!event || !isAuthenticated) return;

    try {
      setIsSubmitting(true);
      if (isAttending) {
        await api.delete(`/v2/events/${event.id}/rsvp`);
        setIsAttending(false);
        setEvent((prev) => prev ? { ...prev, attendees_count: (prev.attendees_count ?? 1) - 1 } : null);
      } else {
        await api.post(`/v2/events/${event.id}/rsvp`);
        setIsAttending(true);
        setEvent((prev) => prev ? { ...prev, attendees_count: (prev.attendees_count ?? 0) + 1 } : null);
      }
    } catch (err) {
      logError('Failed to update RSVP', err);
    } finally {
      setIsSubmitting(false);
    }
  }

  const isOrganizer = user && event && user.id === event.organizer?.id;

  if (isLoading) {
    return <LoadingScreen message="Loading event..." />;
  }

  if (error || !event) {
    return (
      <EmptyState
        icon={<AlertCircle className="w-12 h-12" />}
        title="Event Not Found"
        description={error || 'The event you are looking for does not exist'}
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
        className="flex items-center gap-2 text-white/60 hover:text-white transition-colors"
      >
        <ArrowLeft className="w-4 h-4" />
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
              <div className="text-white text-3xl font-bold">
                {startDate.getDate()}
              </div>
            </div>

            {isPast && (
              <span className="px-3 py-1 rounded-full bg-white/10 text-white/60 text-sm">
                Past Event
              </span>
            )}
          </div>

          {isOrganizer && (
            <div className="flex gap-2">
              <Button
                size="sm"
                variant="flat"
                className="bg-white/5 text-white"
                startContent={<Edit className="w-4 h-4" />}
              >
                Edit
              </Button>
              <Button
                size="sm"
                variant="flat"
                className="bg-red-500/10 text-red-400"
                startContent={<Trash2 className="w-4 h-4" />}
              >
                Delete
              </Button>
            </div>
          )}
        </div>

        {/* Title */}
        <h1 className="text-3xl font-bold text-white mb-4">{event.title}</h1>

        {/* Meta Grid */}
        <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
          <div className="flex items-center gap-3 text-white/60">
            <div className="p-2 rounded-lg bg-amber-500/20">
              <Calendar className="w-5 h-5 text-amber-400" />
            </div>
            <div>
              <div className="text-xs text-white/40">Date</div>
              <div className="text-white">
                {startDate.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' })}
              </div>
            </div>
          </div>

          <div className="flex items-center gap-3 text-white/60">
            <div className="p-2 rounded-lg bg-indigo-500/20">
              <Clock className="w-5 h-5 text-indigo-400" />
            </div>
            <div>
              <div className="text-xs text-white/40">Time</div>
              <div className="text-white">
                {startDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                {endDate && ` - ${endDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`}
              </div>
            </div>
          </div>

          {event.location && (
            <div className="flex items-center gap-3 text-white/60">
              <div className="p-2 rounded-lg bg-emerald-500/20">
                <MapPin className="w-5 h-5 text-emerald-400" />
              </div>
              <div>
                <div className="text-xs text-white/40">Location</div>
                <div className="text-white">{event.location}</div>
              </div>
            </div>
          )}
        </div>

        {/* Description */}
        <div className="mb-8">
          <h2 className="text-lg font-semibold text-white mb-3">About this event</h2>
          <div className="prose prose-invert max-w-none">
            <p className="text-white/70 whitespace-pre-wrap">{event.description}</p>
          </div>
        </div>

        {/* Attendees */}
        <div className="mb-8">
          <h2 className="text-lg font-semibold text-white mb-4 flex items-center gap-2">
            <Users className="w-5 h-5 text-purple-400" />
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
                <span className="text-white/50 text-sm">
                  +{(event.attendees_count ?? 0) - attendees.length} more
                </span>
              )}
            </div>
          ) : (
            <p className="text-white/50">No attendees yet. Be the first!</p>
          )}
        </div>

        {/* Action Buttons */}
        {isAuthenticated && !isPast && (
          <div className="flex flex-wrap gap-3 pt-6 border-t border-white/10">
            <Button
              className={isAttending
                ? 'bg-white/10 text-white'
                : 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white'
              }
              startContent={isAttending ? <UserMinus className="w-4 h-4" /> : <UserPlus className="w-4 h-4" />}
              onClick={handleRsvp}
              isLoading={isSubmitting}
            >
              {isAttending ? 'Cancel RSVP' : 'RSVP'}
            </Button>
            <Button
              variant="flat"
              className="bg-white/5 text-white"
              startContent={<Share2 className="w-4 h-4" />}
            >
              Share
            </Button>
            {event.online_url && (
              <a href={event.online_url} target="_blank" rel="noopener noreferrer">
                <Button
                  variant="flat"
                  className="bg-white/5 text-white"
                  startContent={<ExternalLink className="w-4 h-4" />}
                >
                  Event Link
                </Button>
              </a>
            )}
          </div>
        )}
      </GlassCard>
    </motion.div>
  );
}

export default EventDetailPage;
