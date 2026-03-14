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
import { Calendar, Plus, Clock, MapPin, Users, ChevronRight } from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useTranslation } from 'react-i18next';
import { useAuth } from '@/contexts';
import { useTenant } from '@/contexts';
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
    <GlassCard className="p-6">
      <div className="flex justify-between items-center mb-4">
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

            return (
              <Link key={event.id} to={tenantPath(`/events/${event.id}`)}>
                <div className={`flex items-center gap-4 p-4 rounded-lg bg-theme-elevated hover:bg-theme-hover transition-colors ${isPast ? 'opacity-60' : ''}`}>
                  {/* Date Badge */}
                  <div className="flex-shrink-0 w-14 h-14 rounded-xl bg-gradient-to-br from-indigo-500/20 to-purple-500/20 flex flex-col items-center justify-center text-center">
                    <span className="text-xs font-medium text-indigo-400 uppercase">
                      {eventDate.toLocaleDateString(undefined, { month: 'short' })}
                    </span>
                    <span className="text-lg font-bold text-theme-primary leading-none">
                      {eventDate.getDate()}
                    </span>
                  </div>

                  <div className="flex-1 min-w-0">
                    <h3 className="font-medium text-theme-primary truncate">{event.title}</h3>
                    <div className="flex items-center gap-3 mt-1 text-xs text-theme-subtle">
                      <span className="flex items-center gap-1">
                        <Clock className="w-3 h-3" aria-hidden="true" />
                        {eventDate.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })}
                      </span>
                      {event.location && (
                        <span className="flex items-center gap-1 truncate">
                          <MapPin className="w-3 h-3 flex-shrink-0" aria-hidden="true" />
                          {event.location}
                        </span>
                      )}
                      <span className="flex items-center gap-1">
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
