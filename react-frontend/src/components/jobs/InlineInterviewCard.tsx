// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Button, Chip } from '@heroui/react';
import {
  Calendar,
  Clock,
  MapPin,
  CheckCircle,
  XCircle,
  Video,
  CalendarPlus,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { API_BASE } from '@/lib/api';
import type { InlineInterview } from './JobDetailTypes';

interface InlineInterviewCardProps {
  pendingInterview: InlineInterview;
  isResponding: boolean;
  onAccept: () => void;
  onDeclineOpen: () => void;
}

export function InlineInterviewCard({
  pendingInterview,
  isResponding,
  onAccept,
  onDeclineOpen,
}: InlineInterviewCardProps) {
  const { t } = useTranslation('jobs');

  if (pendingInterview.status !== 'proposed') return null;

  return (
    <GlassCard className="p-5 border-l-4 border-l-secondary bg-secondary/5">
      <div className="flex items-start gap-3">
        <div className="w-10 h-10 rounded-lg bg-secondary/20 flex items-center justify-center flex-shrink-0">
          <Calendar className="w-5 h-5 text-secondary" aria-hidden="true" />
        </div>
        <div className="flex-1 space-y-3">
          <div>
            <h3 className="text-base font-semibold text-theme-primary">
              {t('inline_response.interview_pending', 'You have an interview scheduled')}
            </h3>
            <div className="flex flex-wrap gap-x-4 gap-y-1 mt-2 text-sm text-theme-secondary">
              <span className="flex items-center gap-1">
                <Calendar className="w-3.5 h-3.5" aria-hidden="true" />
                {new Date(pendingInterview.scheduled_at).toLocaleString()}
              </span>
              <Chip size="sm" variant="flat" color="secondary">
                {t(`interview.type_${pendingInterview.interview_type}`, pendingInterview.interview_type)}
              </Chip>
              {pendingInterview.duration_mins && (
                <span className="flex items-center gap-1">
                  <Clock className="w-3.5 h-3.5" aria-hidden="true" />
                  {pendingInterview.duration_mins} {t('interview.minutes', 'min')}
                </span>
              )}
            </div>
            {pendingInterview.meeting_link && (
              <div className="mt-2">
                <Button
                  as="a"
                  href={pendingInterview.meeting_link}
                  target="_blank"
                  rel="noopener noreferrer"
                  size="sm"
                  color="success"
                  variant="flat"
                  startContent={<Video className="w-3.5 h-3.5" aria-hidden="true" />}
                >
                  {t('interview.join_call', 'Join Video Call')}
                </Button>
              </div>
            )}
            {pendingInterview.id && (
              <div className="mt-2 flex items-center gap-1.5">
                <Button
                  size="sm"
                  variant="flat"
                  as="a"
                  href={`${API_BASE}/v2/jobs/interviews/${pendingInterview.id}/calendar`}
                  download="interview.ics"
                  startContent={<CalendarPlus className="w-3.5 h-3.5" />}
                >
                  {t('interview.download_ics', { defaultValue: 'Download .ics' })}
                </Button>
              </div>
            )}
            {pendingInterview.location_notes && (
              <p className="text-sm text-theme-muted mt-1">
                {pendingInterview.interview_type === 'video' && !pendingInterview.meeting_link ? (
                  <a
                    href={pendingInterview.location_notes}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="text-primary hover:underline"
                  >
                    {pendingInterview.location_notes}
                  </a>
                ) : pendingInterview.interview_type !== 'video' ? (
                  <span className="flex items-center gap-1">
                    <MapPin className="w-3.5 h-3.5" aria-hidden="true" />
                    {pendingInterview.location_notes}
                  </span>
                ) : null}
              </p>
            )}
          </div>
          <div className="flex gap-2">
            <Button
              color="success"
              size="sm"
              isLoading={isResponding}
              onPress={onAccept}
              startContent={<CheckCircle className="w-4 h-4" aria-hidden="true" />}
            >
              {t('inline_response.interview_accept', 'Accept Interview')}
            </Button>
            <Button
              color="danger"
              variant="flat"
              size="sm"
              isDisabled={isResponding}
              onPress={onDeclineOpen}
              startContent={<XCircle className="w-4 h-4" aria-hidden="true" />}
            >
              {t('inline_response.interview_decline', 'Decline Interview')}
            </Button>
          </div>
        </div>
      </div>
    </GlassCard>
  );
}
