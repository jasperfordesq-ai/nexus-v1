/**
 * Events Page - Community events listing
 */

import { useState, useEffect, useCallback, memo } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Input, Select, SelectItem } from '@heroui/react';
import {
  Search,
  Calendar,
  MapPin,
  Users,
  Clock,
  Plus,
  Filter,
  CalendarDays,
  ChevronRight,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useAuth } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import type { Event } from '@/types/api';

type EventFilter = 'upcoming' | 'past' | 'all';

export function EventsPage() {
  const { isAuthenticated } = useAuth();
  const [searchParams, setSearchParams] = useSearchParams();

  const [events, setEvents] = useState<Event[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [searchQuery, setSearchQuery] = useState(searchParams.get('q') || '');
  const [filter, setFilter] = useState<EventFilter>('upcoming');

  const loadEvents = useCallback(async () => {
    try {
      setIsLoading(true);
      const params = new URLSearchParams();
      if (searchQuery) params.set('q', searchQuery);
      params.set('filter', filter);
      params.set('limit', '20');

      const response = await api.get<Event[]>(`/v2/events?${params}`);
      if (response.success && response.data) {
        setEvents(response.data);
      }
    } catch (error) {
      logError('Failed to load events', error);
    } finally {
      setIsLoading(false);
    }
  }, [searchQuery, filter]);

  useEffect(() => {
    loadEvents();

    const params = new URLSearchParams();
    if (searchQuery) params.set('q', searchQuery);
    setSearchParams(params, { replace: true });
  }, [searchQuery, filter]);

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
          <h1 className="text-2xl font-bold text-white flex items-center gap-3">
            <Calendar className="w-7 h-7 text-amber-400" />
            Events
          </h1>
          <p className="text-white/60 mt-1">Discover and join community events</p>
        </div>
        {isAuthenticated && (
          <Link to="/events/create">
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              startContent={<Plus className="w-4 h-4" />}
            >
              Create Event
            </Button>
          </Link>
        )}
      </div>

      {/* Filters */}
      <GlassCard className="p-4">
        <div className="flex flex-col lg:flex-row gap-4">
          <div className="flex-1">
            <Input
              placeholder="Search events..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              startContent={<Search className="w-4 h-4 text-white/40" />}
              classNames={{
                input: 'bg-transparent text-white placeholder:text-white/40',
                inputWrapper: 'bg-white/5 border-white/10 hover:bg-white/10',
              }}
            />
          </div>

          <Select
            placeholder="Filter"
            selectedKeys={[filter]}
            onChange={(e) => setFilter(e.target.value as EventFilter)}
            className="w-40"
            classNames={{
              trigger: 'bg-white/5 border-white/10 hover:bg-white/10',
              value: 'text-white',
            }}
            startContent={<Filter className="w-4 h-4 text-white/40" />}
          >
            <SelectItem key="upcoming">Upcoming</SelectItem>
            <SelectItem key="past">Past</SelectItem>
            <SelectItem key="all">All Events</SelectItem>
          </Select>
        </div>
      </GlassCard>

      {/* Events List */}
      {isLoading ? (
        <div className="space-y-6">
          {[1, 2, 3].map((i) => (
            <GlassCard key={i} className="p-5 animate-pulse">
              <div className="flex gap-4">
                <div className="w-16 h-20 rounded-lg bg-white/10" />
                <div className="flex-1">
                  <div className="h-5 bg-white/10 rounded w-1/2 mb-2" />
                  <div className="h-4 bg-white/10 rounded w-3/4 mb-3" />
                  <div className="h-3 bg-white/10 rounded w-1/4" />
                </div>
              </div>
            </GlassCard>
          ))}
        </div>
      ) : events.length === 0 ? (
        <EmptyState
          icon={<Calendar className="w-12 h-12" />}
          title="No events found"
          description={filter === 'upcoming' ? "No upcoming events scheduled" : "No events match your search"}
          action={
            isAuthenticated && (
              <Link to="/events/create">
                <Button className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white">
                  Create Event
                </Button>
              </Link>
            )
          }
        />
      ) : (
        <motion.div
          variants={containerVariants}
          initial="hidden"
          animate="visible"
          className="space-y-8"
        >
          {Object.entries(groupedEvents).map(([month, monthEvents]) => (
            <section key={month}>
              <h2 className="text-lg font-semibold text-white/80 mb-4 flex items-center gap-2">
                <CalendarDays className="w-5 h-5 text-amber-400" />
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
        </motion.div>
      )}
    </div>
  );
}

interface EventCardProps {
  event: Event;
}

const EventCard = memo(function EventCard({ event }: EventCardProps) {
  const startDate = new Date(event.start_date);
  const isPast = startDate < new Date();

  return (
    <Link to={`/events/${event.id}`}>
      <GlassCard className={`p-5 hover:scale-[1.01] transition-transform ${isPast ? 'opacity-60' : ''}`}>
        <div className="flex gap-4">
          {/* Date Box */}
          <div className="flex-shrink-0 w-16 text-center">
            <div className="bg-gradient-to-br from-amber-500/20 to-orange-500/20 rounded-lg p-2">
              <div className="text-amber-400 text-xs font-medium uppercase">
                {startDate.toLocaleString('default', { month: 'short' })}
              </div>
              <div className="text-white text-2xl font-bold">
                {startDate.getDate()}
              </div>
              <div className="text-white/50 text-xs">
                {startDate.toLocaleString('default', { weekday: 'short' })}
              </div>
            </div>
          </div>

          {/* Event Details */}
          <div className="flex-1 min-w-0">
            <h3 className="font-semibold text-white text-lg">{event.title}</h3>
            <p className="text-white/60 text-sm line-clamp-2 mt-1">{event.description}</p>

            <div className="flex flex-wrap items-center gap-4 mt-3 text-sm text-white/50">
              <span className="flex items-center gap-1">
                <Clock className="w-4 h-4" />
                {startDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
              </span>
              {event.location && (
                <span className="flex items-center gap-1">
                  <MapPin className="w-4 h-4" />
                  {event.location}
                </span>
              )}
              <span className="flex items-center gap-1">
                <Users className="w-4 h-4" />
                {event.attendees_count ?? 0} attending
              </span>
            </div>
          </div>

          {/* Arrow */}
          <div className="flex-shrink-0 self-center">
            <ChevronRight className="w-5 h-5 text-white/30" />
          </div>
        </div>
      </GlassCard>
    </Link>
  );
});

export default EventsPage;
