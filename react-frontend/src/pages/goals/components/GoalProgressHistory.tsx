// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * G5 - Goal Progress History Timeline
 *
 * Shows a chronological timeline of all goal events:
 * - Progress updates
 * - Check-ins with moods
 * - Milestones reached
 * - Buddy joins
 * - Goal completion
 *
 * API: GET /api/v2/goals/{id}/history
 */

import { useState, useEffect, useCallback } from 'react';
import { motion } from 'framer-motion';
import {
  Button,
  Spinner,
  Chip,
  Progress,
} from '@heroui/react';
import {
  TrendingUp,
  ClipboardCheck,
  Trophy,
  Users,
  CheckCircle,
  Star,
  Target,
  RefreshCw,
  Clock,
  Smile,
  Frown,
  Meh,
  Heart,
  Zap,
} from 'lucide-react';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { formatRelativeTime } from '@/lib/helpers';

/* ───────────────────────── Types ───────────────────────── */

interface HistoryEvent {
  id: number;
  type: 'progress_update' | 'checkin' | 'milestone' | 'buddy_joined' | 'completed' | 'created';
  description: string;
  data: Record<string, unknown>;
  created_at: string;
}

interface GoalProgressHistoryProps {
  goalId: number;
  className?: string;
}

/* ───────────────────────── Event Icon/Color Mapping ───────────────────────── */

function getEventIcon(type: string) {
  switch (type) {
    case 'progress_update':
      return <TrendingUp className="w-3.5 h-3.5" />;
    case 'checkin':
      return <ClipboardCheck className="w-3.5 h-3.5" />;
    case 'milestone':
      return <Trophy className="w-3.5 h-3.5" />;
    case 'buddy_joined':
      return <Users className="w-3.5 h-3.5" />;
    case 'completed':
      return <CheckCircle className="w-3.5 h-3.5" />;
    case 'created':
      return <Target className="w-3.5 h-3.5" />;
    default:
      return <Star className="w-3.5 h-3.5" />;
  }
}

function getEventColor(type: string): string {
  switch (type) {
    case 'progress_update':
      return 'bg-blue-500';
    case 'checkin':
      return 'bg-indigo-500';
    case 'milestone':
      return 'bg-amber-500';
    case 'buddy_joined':
      return 'bg-purple-500';
    case 'completed':
      return 'bg-emerald-500';
    case 'created':
      return 'bg-gray-400';
    default:
      return 'bg-gray-400';
  }
}

function getEventChipColor(type: string): 'primary' | 'success' | 'warning' | 'secondary' | 'default' {
  switch (type) {
    case 'progress_update': return 'primary';
    case 'checkin': return 'primary';
    case 'milestone': return 'warning';
    case 'buddy_joined': return 'secondary';
    case 'completed': return 'success';
    default: return 'default';
  }
}

function getMoodIcon(mood: string) {
  switch (mood) {
    case 'great': return <Star className="w-3.5 h-3.5 text-amber-400" aria-hidden="true" />;
    case 'good': return <Smile className="w-3.5 h-3.5 text-emerald-400" aria-hidden="true" />;
    case 'okay': return <Meh className="w-3.5 h-3.5 text-blue-400" aria-hidden="true" />;
    case 'struggling': return <Frown className="w-3.5 h-3.5 text-orange-400" aria-hidden="true" />;
    case 'motivated': return <Zap className="w-3.5 h-3.5 text-purple-400" aria-hidden="true" />;
    case 'grateful': return <Heart className="w-3.5 h-3.5 text-rose-400" aria-hidden="true" />;
    default: return null;
  }
}

/* ───────────────────────── Helpers ───────────────────────── */

function ProgressValueBar({ data }: { data: Record<string, unknown> | undefined }) {
  if (!data || data.progress_value == null) return null;
  const numVal = Number(data.progress_value);
  return (
    <div className="mt-2">
      <Progress
        value={numVal}
        size="sm"
        classNames={{
          indicator: 'bg-gradient-to-r from-indigo-500 to-purple-600',
          track: 'bg-theme-hover',
        }}
        aria-label={`Progress: ${numVal}%`}
      />
      <span className="text-xs text-theme-subtle">{numVal}%</span>
    </div>
  );
}

/* ───────────────────────── Component ───────────────────────── */

export function GoalProgressHistory({ goalId, className = '' }: GoalProgressHistoryProps) {
  const [events, setEvents] = useState<HistoryEvent[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const loadHistory = useCallback(async () => {
    try {
      setIsLoading(true);
      setError(null);
      const response = await api.get<HistoryEvent[]>(`/v2/goals/${goalId}/history`);
      if (response.success && response.data) {
        setEvents(Array.isArray(response.data) ? response.data : []);
      }
    } catch (err) {
      logError('Failed to load goal history', err);
      setError('Failed to load history.');
    } finally {
      setIsLoading(false);
    }
  }, [goalId]);

  useEffect(() => {
    loadHistory();
  }, [loadHistory]);

  if (isLoading) {
    return (
      <div className={`flex items-center justify-center py-8 ${className}`}>
        <Spinner size="md" color="primary" />
      </div>
    );
  }

  if (error) {
    return (
      <div className={`text-center py-6 ${className}`}>
        <p className="text-sm text-theme-muted mb-2">{error}</p>
        <Button
          size="sm"
          variant="flat"
          className="bg-theme-elevated text-theme-primary"
          startContent={<RefreshCw className="w-3.5 h-3.5" aria-hidden="true" />}
          onPress={loadHistory}
        >
          Retry
        </Button>
      </div>
    );
  }

  if (events.length === 0) {
    return (
      <div className={`text-center py-6 ${className}`}>
        <Clock className="w-8 h-8 text-theme-subtle mx-auto mb-2" aria-hidden="true" />
        <p className="text-sm text-theme-muted">No activity recorded yet.</p>
      </div>
    );
  }

  return (
    <div className={`space-y-0 ${className}`}>
      <div className="relative border-l-2 border-theme-default ml-3 pl-6 space-y-4">
        {events.map((event, index) => (
          <motion.div
            key={event.id}
            initial={{ opacity: 0, x: -10 }}
            animate={{ opacity: 1, x: 0 }}
            transition={{ delay: index * 0.04 }}
            className="relative"
          >
            {/* Timeline dot */}
            <div
              className={`absolute -left-[31px] top-1 w-4 h-4 rounded-full ${getEventColor(event.type)} flex items-center justify-center text-white`}
            >
              {getEventIcon(event.type)}
            </div>

            {/* Event content */}
            <div className="bg-theme-elevated rounded-lg p-3">
              <div className="flex items-start justify-between gap-2">
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2 mb-1 flex-wrap">
                    <Chip
                      size="sm"
                      variant="flat"
                      color={getEventChipColor(event.type)}
                      className="text-[10px]"
                    >
                      {event.type.replace(/_/g, ' ')}
                    </Chip>
                    {typeof event.data?.mood === 'string' ? getMoodIcon(event.data.mood) : null}
                  </div>
                  <p className="text-sm text-theme-primary">{event.description}</p>

                  {/* Progress bar for checkins/progress_updates */}
                  {event.data?.progress_value != null && (
                    <ProgressValueBar data={event.data} />
                  )}

                  {/* Note for check-ins */}
                  {typeof event.data?.note === 'string' && event.data.note && (
                    <p className="text-xs text-theme-muted mt-1 italic">
                      &ldquo;{event.data.note}&rdquo;
                    </p>
                  )}
                </div>

                <span className="text-xs text-theme-subtle whitespace-nowrap flex-shrink-0">
                  {formatRelativeTime(event.created_at)}
                </span>
              </div>
            </div>
          </motion.div>
        ))}
      </div>
    </div>
  );
}

export default GoalProgressHistory;
