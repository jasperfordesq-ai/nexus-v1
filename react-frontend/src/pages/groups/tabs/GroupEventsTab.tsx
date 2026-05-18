// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Group Events Tab
 * Lists upcoming and past events for the group, with create-event shortcut for members.
 */

import { Link } from 'react-router-dom';
import { Button, Chip, Spinner } from '@heroui/react';
import Calendar from 'lucide-react/icons/calendar';
import Plus from 'lucide-react/icons/plus';
import Clock from 'lucide-react/icons/clock';
import MapPin from 'lucide-react/icons/map-pin';
import Users from 'lucide-react/icons/users';
import ChevronRight from 'lucide-react/icons/chevron-right';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useTranslation } from 'react-i18next';
import { useAuth } from '@/contexts';
import { useTenant } from '@/contexts';
import { formatDateTime, formatMonthShort } from '@/lib/helpers';
import type { Event } from '@/types/api';

interface GroupEventsTabProps {
  groupId: number;
  events: Event[];
  eventsLoading: boolean;
  isMember: boolean;
}

export function GroupEventsTab({
  groupId,
  events,
  eventsLoading,
  isMember,
}: GroupEventsTabProps) {
  const { t } = useTranslation('groups');
  const { isAuthenticated } = useAuth();
  const { tenantPath } = useTenant();

  return (
    <GlassCard className="p-4 sm:p-6">
      <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h2 className="text-lg font-semibold text-theme-primary">{t('detail.group_events_heading')}</h2>
        {isMember && isAuthenticated && (
          <Link to={tenantPath(`/events/create?group_id=${groupId}`)}>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              size="sm"
              startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
            >
              {t('detail.create_event')}
            </Button>
          </Link>
        )}
      </div>

      {eventsLoading ? (
        <div className="flex justify-center py-8">
          <Spinner size="lg" />
        </div>
      ) : events.length === 0 ? (
        <EmptyState
          icon={<Calendar className="w-12 h-12" aria-hidden="true" />}
          title={t('detail.no_events_title')}
          description={t('detail.no_events_desc')}
          action={
            isMember && isAuthenticated && (
              <Link to={tenantPath(`/events/create?group_id=${groupId}`)}>
                <Button
                  className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                  startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
                >
                  {t('detail.create_event')}
                </Button>
              </Link>
            )
          }
        />
      ) : (
        <div className="space-y-3">
          {events.map((event) => {
            const eventDate = new Date(event.start_date);
            const isPast = eventDate < new Date();
            const monthLabel = formatMonthShort(eventDate, true);
            const timeLabel = formatDateTime(eventDate, { hour: '2-digit', minute: '2-digit' });

            return (
              <Link key={event.id} to={tenantPath(`/events/${event.id}`)}>
                <div className={`flex min-w-0 items-center gap-3 rounded-lg bg-theme-elevated p-3 transition-colors hover:bg-theme-hover sm:gap-4 sm:p-4 ${isPast ? 'opacity-60' : ''}`}>
                  {/* Date Badge */}
                  <div className="flex h-14 w-14 flex-shrink-0 flex-col items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500/20 to-purple-500/20 text-center">
                    <span className="text-xs font-medium text-indigo-400 uppercase">
                      {monthLabel}
                    </span>
                    <span className="text-lg font-bold text-theme-primary leading-none">
                      {eventDate.getDate()}
                    </span>
                  </div>

                  <div className="flex-1 min-w-0">
                    <h3 className="font-medium text-theme-primary truncate">{event.title}</h3>
                    <div className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-theme-subtle">
                      <span className="flex items-center gap-1 whitespace-nowrap">
                        <Clock className="w-3 h-3" aria-hidden="true" />
                        {timeLabel}
                      </span>
                      {event.location && (
                        <span className="flex min-w-0 max-w-full items-center gap-1 truncate">
                          <MapPin className="w-3 h-3 flex-shrink-0" aria-hidden="true" />
                          <span className="truncate">{event.location}</span>
                        </span>
                      )}
                      <span className="flex items-center gap-1 whitespace-nowrap">
                        <Users className="w-3 h-3" aria-hidden="true" />
                        {event.attendees_count ?? 0} {t('detail.attending')}
                      </span>
                    </div>
                  </div>

                  {isPast && (
                    <Chip size="sm" variant="flat" className="bg-theme-hover text-theme-subtle">
                      {t('detail.past_chip')}
                    </Chip>
                  )}

                  <ChevronRight className="w-5 h-5 text-theme-subtle flex-shrink-0" aria-hidden="true" />
                </div>
              </Link>
            );
          })}
        </div>
      )}
    </GlassCard>
  );
}
