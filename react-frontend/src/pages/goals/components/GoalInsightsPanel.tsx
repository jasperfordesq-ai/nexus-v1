// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { Button, Chip, Progress, Skeleton } from '@heroui/react';
import Bell from 'lucide-react/icons/bell';
import CheckCircle from 'lucide-react/icons/circle-check-big';
import Flame from 'lucide-react/icons/flame';
import Flag from 'lucide-react/icons/flag';
import HandHeart from 'lucide-react/icons/hand-heart';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import Sparkles from 'lucide-react/icons/sparkles';
import { useTranslation } from 'react-i18next';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { formatRelativeTime } from '@/lib/helpers';

interface GoalMilestone {
  id: number;
  title: string;
  target_percent: number | null;
  target_value: number | null;
  completed_at: string | null;
}

interface BuddyNote {
  id: number;
  type: 'nudge' | 'encouragement' | 'offer_help' | 'celebration' | 'note';
  message: string | null;
  created_at: string;
  buddy_name?: string | null;
}

interface GoalInsights {
  checkin_count: number;
  last_checkin_at: string | null;
  checkin_frequency: 'none' | 'daily' | 'weekly' | 'biweekly' | 'monthly';
  next_checkin_due_at: string | null;
  is_checkin_due: boolean;
  streak_count: number;
  best_streak_count: number;
  milestones: GoalMilestone[];
  completed_milestones: number;
  milestone_count: number;
  buddy_notes: BuddyNote[];
}

interface GoalInsightsPanelProps {
  goalId: number;
  canNudge?: boolean;
}

export function GoalInsightsPanel({ goalId, canNudge = false }: GoalInsightsPanelProps) {
  const { t } = useTranslation('goals');
  const toast = useToast();
  const [insights, setInsights] = useState<GoalInsights | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isSending, setIsSending] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const loadInsights = useCallback(async () => {
    try {
      setIsLoading(true);
      setError(null);
      const response = await api.get<GoalInsights>(`/v2/goals/${goalId}/insights`);
      if (response.success && response.data) {
        setInsights(response.data);
      } else {
        setError(t('insights.load_failed'));
      }
    } catch (err) {
      logError('Failed to load goal insights', err);
      setError(t('insights.load_failed'));
    } finally {
      setIsLoading(false);
    }
  }, [goalId, t]);

  useEffect(() => {
    loadInsights();
  }, [loadInsights]);

  const sendBuddyAction = async (type: BuddyNote['type']) => {
    try {
      setIsSending(type);
      const response = await api.post(`/v2/goals/${goalId}/buddy/nudge`, { type });
      if (response.success) {
        toast.success(t('insights.buddy_sent'));
        loadInsights();
      } else {
        toast.error(t('insights.buddy_failed'));
      }
    } catch (err) {
      logError('Failed to send buddy action', err);
      toast.error(t('insights.buddy_failed'));
    } finally {
      setIsSending(null);
    }
  };

  if (isLoading) {
    return (
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3" aria-busy="true" aria-label={t('insights.loading')}>
        {[1, 2, 3, 4].map((item) => (
          <Skeleton key={item} className="rounded-lg">
            <div className="h-20 rounded-lg bg-default-200" />
          </Skeleton>
        ))}
      </div>
    );
  }

  if (error || !insights) {
    return (
      <div className="rounded-lg bg-theme-elevated p-4 text-center">
        <p className="text-sm text-theme-muted mb-3">{error ?? t('insights.load_failed')}</p>
        <Button
          size="sm"
          variant="flat"
          className="bg-theme-hover text-theme-primary"
          startContent={<RefreshCw className="w-3.5 h-3.5" aria-hidden="true" />}
          onPress={loadInsights}
        >
          {t('history.retry')}
        </Button>
      </div>
    );
  }

  const milestonePercent = insights.milestone_count > 0
    ? Math.round((insights.completed_milestones / insights.milestone_count) * 100)
    : 0;

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <InsightCard
          icon={<Flame className="w-4 h-4 text-orange-400" aria-hidden="true" />}
          label={t('insights.current_streak')}
          value={t('insights.streak_value', { count: insights.streak_count })}
          helper={t('insights.best_streak', { count: insights.best_streak_count })}
        />
        <InsightCard
          icon={<Bell className="w-4 h-4 text-sky-400" aria-hidden="true" />}
          label={t('insights.next_checkin')}
          value={insights.next_checkin_due_at ? formatRelativeTime(insights.next_checkin_due_at) : t('insights.no_cadence')}
          helper={insights.is_checkin_due ? t('insights.checkin_due') : t('insights.frequency', { frequency: t(`frequency.${insights.checkin_frequency}`) })}
          tone={insights.is_checkin_due ? 'warning' : 'default'}
        />
        <InsightCard
          icon={<CheckCircle className="w-4 h-4 text-emerald-400" aria-hidden="true" />}
          label={t('insights.checkins')}
          value={t('insights.checkins_value', { count: insights.checkin_count })}
          helper={insights.last_checkin_at ? t('insights.last_checkin', { time: formatRelativeTime(insights.last_checkin_at) }) : t('insights.no_checkins')}
        />
        <div className="rounded-lg bg-theme-elevated p-3">
          <div className="flex items-center gap-2 text-xs font-semibold uppercase text-theme-subtle">
            <Flag className="w-4 h-4 text-indigo-400" aria-hidden="true" />
            {t('insights.milestones')}
          </div>
          <div className="mt-2 flex items-center justify-between text-sm">
            <span className="font-semibold text-theme-primary">
              {t('insights.milestones_value', { completed: insights.completed_milestones, total: insights.milestone_count })}
            </span>
            <span className="text-xs text-theme-subtle">{milestonePercent}%</span>
          </div>
          <Progress
            value={milestonePercent}
            size="sm"
            classNames={{ indicator: 'bg-gradient-to-r from-indigo-500 to-purple-600', track: 'bg-theme-hover' }}
            aria-label={t('insights.milestones_progress_aria', { percent: milestonePercent })}
          />
        </div>
      </div>

      {insights.milestones.length > 0 && (
        <div className="rounded-lg bg-theme-elevated p-3">
          <div className="mb-3 flex items-center gap-2 text-xs font-semibold uppercase text-theme-subtle">
            <Flag className="w-4 h-4 text-indigo-400" aria-hidden="true" />
            {t('insights.milestone_plan')}
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
            {insights.milestones.map((milestone) => (
              <div key={milestone.id} className="flex items-center justify-between gap-2 rounded-lg bg-theme-hover px-3 py-2">
                <span className="text-sm text-theme-primary">{milestone.title}</span>
                <Chip size="sm" color={milestone.completed_at ? 'success' : 'default'} variant="flat">
                  {milestone.completed_at
                    ? t('insights.milestone_done')
                    : t('insights.milestone_target', { percent: Math.round(Number(milestone.target_percent ?? 0)) })}
                </Chip>
              </div>
            ))}
          </div>
        </div>
      )}

      {canNudge && (
        <div className="rounded-lg bg-purple-500/10 p-3">
          <div className="flex items-start gap-3">
            <HandHeart className="mt-0.5 w-5 h-5 text-purple-400" aria-hidden="true" />
            <div className="flex-1">
              <h3 className="text-sm font-semibold text-theme-primary">{t('insights.buddy_actions_title')}</h3>
              <p className="text-xs text-theme-muted mt-1">{t('insights.buddy_actions_body')}</p>
              <div className="mt-3 flex flex-wrap gap-2">
                <Button size="sm" variant="flat" className="bg-theme-elevated text-theme-primary" isLoading={isSending === 'nudge'} onPress={() => sendBuddyAction('nudge')}>
                  {t('insights.action_nudge')}
                </Button>
                <Button size="sm" variant="flat" className="bg-theme-elevated text-theme-primary" isLoading={isSending === 'encouragement'} onPress={() => sendBuddyAction('encouragement')}>
                  {t('insights.action_encourage')}
                </Button>
                <Button size="sm" variant="flat" className="bg-theme-elevated text-theme-primary" isLoading={isSending === 'offer_help'} onPress={() => sendBuddyAction('offer_help')}>
                  {t('insights.action_offer_help')}
                </Button>
              </div>
            </div>
          </div>
        </div>
      )}

      {insights.buddy_notes.length > 0 && (
        <div className="rounded-lg bg-theme-elevated p-3">
          <div className="mb-3 flex items-center gap-2 text-xs font-semibold uppercase text-theme-subtle">
            <Sparkles className="w-4 h-4 text-purple-400" aria-hidden="true" />
            {t('insights.recent_buddy_support')}
          </div>
          <div className="space-y-2">
            {insights.buddy_notes.map((note) => (
              <div key={note.id} className="rounded-lg bg-theme-hover px-3 py-2">
                <div className="flex items-center justify-between gap-2">
                  <span className="text-sm font-medium text-theme-primary">{t(`insights.note_type.${note.type}`)}</span>
                  <span className="text-xs text-theme-subtle">{formatRelativeTime(note.created_at)}</span>
                </div>
                {note.message && <p className="text-xs text-theme-muted mt-1">{note.message}</p>}
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

function InsightCard({
  icon,
  label,
  value,
  helper,
  tone = 'default',
}: {
  icon: ReactNode;
  label: string;
  value: string;
  helper: string;
  tone?: 'default' | 'warning';
}) {
  return (
    <div className={`rounded-lg p-3 ${tone === 'warning' ? 'bg-amber-500/10' : 'bg-theme-elevated'}`}>
      <div className="flex items-center gap-2 text-xs font-semibold uppercase text-theme-subtle">
        {icon}
        {label}
      </div>
      <p className="mt-2 text-sm font-semibold text-theme-primary">{value}</p>
      <p className="text-xs text-theme-muted mt-1">{helper}</p>
    </div>
  );
}

export default GoalInsightsPanel;
