// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * UpcomingEventsWidget - Shows next upcoming events in the sidebar
 */

import { Link } from 'react-router-dom';
import { CalendarDays, Clock, MapPin } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { useTenant } from '@/contexts';

export interface UpcomingEvent {
  id: number;
  title: string;
  start_date: string;
  start_time?: string;
  location?: string;
}

interface UpcomingEventsWidgetProps {
  events: UpcomingEvent[];
}

function formatMonth(dateStr: string): string {
  const date = new Date(dateStr);
  return date.toLocaleString('default', { month: 'short' }).toUpperCase();
}

function formatDay(dateStr: string): string {
  const date = new Date(dateStr);
  return date.getDate().toString();
}

export function UpcomingEventsWidget({ events }: UpcomingEventsWidgetProps) {
  const { tenantPath } = useTenant();
  const { t } = useTranslation('feed');

  if (events.length === 0) return null;

  return (
    <GlassCard className="p-4">
      <div className="flex items-center justify-between mb-3">
        <div className="flex items-center gap-2">
          <CalendarDays className="w-4 h-4 text-pink-500" aria-hidden="true" />
          <h3 className="font-semibold text-sm text-[var(--text-primary)]">
            {t('sidebar.events.title', 'Upcoming Events')}
          </h3>
        </div>
        <Link
          to={tenantPath('/events')}
          className="text-xs text-indigo-500 hover:text-indigo-600 transition-colors"
        >
          {t('sidebar.events.see_all', 'See All')}
        </Link>
      </div>

      <div className="space-y-3">
        {events.map((event) => (
          <Link
            key={event.id}
            to={tenantPath(`/events/${event.id}`)}
            className="flex items-start gap-3 p-2 rounded-lg hover:bg-[var(--surface-elevated)] transition-colors group"
          >
            {/* Calendar date card */}
            <div className="w-11 h-12 rounded-lg bg-[var(--surface-elevated)] border border-[var(--border-default)] flex flex-col items-center justify-center overflow-hidden flex-shrink-0">
              <div className="w-full h-1 bg-gradient-to-r from-pink-500 to-rose-500" />
              <span className="text-[10px] font-bold text-pink-500 mt-1 leading-none">
                {formatMonth(event.start_date)}
              </span>
              <span className="text-sm font-bold text-[var(--text-primary)] leading-none">
                {formatDay(event.start_date)}
              </span>
            </div>

            {/* Event info */}
            <div className="flex-1 min-w-0">
              <p className="text-sm font-medium text-[var(--text-primary)] truncate group-hover:text-indigo-500 transition-colors">
                {event.title}
              </p>
              {event.start_time && (
                <p className="flex items-center gap-1 text-xs text-[var(--text-muted)] mt-0.5">
                  <Clock className="w-3 h-3" aria-hidden="true" />
                  {event.start_time}
                </p>
              )}
              {event.location && (
                <p className="flex items-center gap-1 text-xs text-[var(--text-muted)] mt-0.5 truncate">
                  <MapPin className="w-3 h-3 flex-shrink-0" aria-hidden="true" />
                  {event.location}
                </p>
              )}
            </div>
          </Link>
        ))}
      </div>
    </GlassCard>
  );
}

export default UpcomingEventsWidget;
