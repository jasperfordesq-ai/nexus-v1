// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * WellbeingTab - Volunteer burnout/wellbeing dashboard (V10)
 *
 * Shows wellbeing score, hours stats, streak, burnout warnings,
 * suggested rest days, and a mood check-in form.
 */

import { useState, useEffect, useCallback } from 'react';
import { motion } from 'framer-motion';
import {
  Button,
  Progress,
  Chip,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Textarea,
  useDisclosure,
} from '@heroui/react';
import {
  Heart,
  Activity,
  AlertTriangle,
  Smile,
  Flame,
  CalendarCheck,
  RefreshCw,
  TrendingDown,
  Coffee,
  Sun,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

/* ───────────────────────── Types ───────────────────────── */

interface WellbeingData {
  score: number;
  hours_this_week: number;
  hours_this_month: number;
  streak_days: number;
  burnout_risk: 'low' | 'moderate' | 'high';
  warnings: string[];
  suggested_rest_days: string[];
  recent_checkins: MoodCheckin[];
}

interface MoodCheckin {
  id: number;
  mood: number;
  note: string | null;
  created_at: string;
}

/* ───────────────────────── Mood Helpers ───────────────────────── */

const MOOD_OPTIONS = [
  { value: 1, label: 'Struggling', emoji: '😞' },
  { value: 2, label: 'Low', emoji: '😔' },
  { value: 3, label: 'Okay', emoji: '😐' },
  { value: 4, label: 'Good', emoji: '😊' },
  { value: 5, label: 'Great', emoji: '😄' },
] as const;

function getMoodEmoji(value: number): string {
  return MOOD_OPTIONS.find((m) => m.value === value)?.emoji ?? '😐';
}

function getMoodLabel(value: number): string {
  return MOOD_OPTIONS.find((m) => m.value === value)?.label ?? 'Unknown';
}

/* ───────────────────────── Score Color ───────────────────────── */

function getScoreColor(score: number): { text: string; bg: string; indicator: string } {
  if (score >= 70) return { text: 'text-emerald-400', bg: 'bg-emerald-500/10', indicator: 'bg-gradient-to-r from-emerald-500 to-green-400' };
  if (score >= 40) return { text: 'text-amber-400', bg: 'bg-amber-500/10', indicator: 'bg-gradient-to-r from-amber-500 to-yellow-400' };
  return { text: 'text-rose-400', bg: 'bg-rose-500/10', indicator: 'bg-gradient-to-r from-rose-500 to-red-400' };
}

function getScoreLabel(score: number): string {
  if (score >= 80) return 'Excellent';
  if (score >= 70) return 'Good';
  if (score >= 50) return 'Fair';
  if (score >= 30) return 'Needs Attention';
  return 'Critical';
}

function getRiskColor(risk: string): 'success' | 'warning' | 'danger' {
  switch (risk) {
    case 'low': return 'success';
    case 'moderate': return 'warning';
    case 'high': return 'danger';
    default: return 'warning';
  }
}

/* ───────────────────────── Main Component ───────────────────────── */

export function WellbeingTab() {
  const [data, setData] = useState<WellbeingData | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Check-in modal state
  const { isOpen, onOpen, onClose } = useDisclosure();
  const [selectedMood, setSelectedMood] = useState<number>(3);
  const [checkinNote, setCheckinNote] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  const load = useCallback(async () => {
    try {
      setIsLoading(true);
      setError(null);

      const response = await api.get<WellbeingData>('/v2/volunteering/wellbeing');

      if (response.success && response.data) {
        setData(response.data as WellbeingData);
      } else {
        setError('Failed to load wellbeing data.');
      }
    } catch (err) {
      logError('Failed to load wellbeing data', err);
      setError('Unable to load wellbeing data. Please try again.');
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const handleCheckin = async () => {
    try {
      setIsSubmitting(true);

      const response = await api.post('/v2/volunteering/wellbeing/checkin', {
        mood: selectedMood,
        note: checkinNote.trim() || undefined,
      });

      if (response.success) {
        onClose();
        setSelectedMood(3);
        setCheckinNote('');
        load();
      }
    } catch (err) {
      logError('Failed to submit wellbeing check-in', err);
    } finally {
      setIsSubmitting(false);
    }
  };

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: { opacity: 1, transition: { staggerChildren: 0.05 } },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 20 },
    visible: { opacity: 1, y: 0 },
  };

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <Heart className="w-5 h-5 text-rose-400" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary">Volunteer Wellbeing</h2>
        </div>
        <Button
          size="sm"
          className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
          startContent={<Smile className="w-4 h-4" aria-hidden="true" />}
          onPress={onOpen}
        >
          Log How I'm Feeling
        </Button>
      </div>

      {/* Error */}
      {error && !isLoading && (
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <p className="text-theme-muted mb-4">{error}</p>
          <Button
            className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
            startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
            onPress={load}
          >
            Try Again
          </Button>
        </GlassCard>
      )}

      {/* Loading */}
      {!error && isLoading && (
        <div className="space-y-4">
          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
            {[1, 2, 3, 4].map((i) => (
              <GlassCard key={i} className="p-5 animate-pulse">
                <div className="h-8 bg-theme-hover rounded w-1/2 mb-2" />
                <div className="h-3 bg-theme-hover rounded w-3/4" />
              </GlassCard>
            ))}
          </div>
          <GlassCard className="p-5 animate-pulse">
            <div className="h-4 bg-theme-hover rounded w-1/3 mb-4" />
            <div className="h-6 bg-theme-hover rounded w-full" />
          </GlassCard>
        </div>
      )}

      {/* Empty State */}
      {!error && !isLoading && !data && (
        <EmptyState
          icon={<Heart className="w-12 h-12" aria-hidden="true" />}
          title="No wellbeing data yet"
          description="Start logging your mood and tracking your volunteer wellness."
          action={
            <Button
              className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
              onPress={onOpen}
            >
              Log How I'm Feeling
            </Button>
          }
        />
      )}

      {/* Dashboard Content */}
      {!error && !isLoading && data && (
        <motion.div
          variants={containerVariants}
          initial="hidden"
          animate="visible"
          className="space-y-6"
        >
          {/* Stat Cards Row */}
          <motion.div variants={itemVariants} className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
            {/* Wellbeing Score */}
            <GlassCard className="p-5">
              <div className="flex items-center gap-3">
                <div className={`w-10 h-10 rounded-xl ${getScoreColor(data.score).bg} flex items-center justify-center`}>
                  <Activity className={`w-5 h-5 ${getScoreColor(data.score).text}`} aria-hidden="true" />
                </div>
                <div>
                  <p className={`text-2xl font-bold ${getScoreColor(data.score).text}`}>{data.score}</p>
                  <p className="text-xs text-theme-muted">Wellbeing Score</p>
                </div>
              </div>
            </GlassCard>

            {/* Hours This Week */}
            <GlassCard className="p-5">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-xl bg-indigo-500/10 flex items-center justify-center">
                  <Activity className="w-5 h-5 text-indigo-400" aria-hidden="true" />
                </div>
                <div>
                  <p className="text-2xl font-bold text-theme-primary">{data.hours_this_week}h</p>
                  <p className="text-xs text-theme-muted">This Week</p>
                </div>
              </div>
            </GlassCard>

            {/* Hours This Month */}
            <GlassCard className="p-5">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-xl bg-violet-500/10 flex items-center justify-center">
                  <CalendarCheck className="w-5 h-5 text-violet-400" aria-hidden="true" />
                </div>
                <div>
                  <p className="text-2xl font-bold text-theme-primary">{data.hours_this_month}h</p>
                  <p className="text-xs text-theme-muted">This Month</p>
                </div>
              </div>
            </GlassCard>

            {/* Streak */}
            <GlassCard className="p-5">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-xl bg-orange-500/10 flex items-center justify-center">
                  <Flame className="w-5 h-5 text-orange-400" aria-hidden="true" />
                </div>
                <div>
                  <p className="text-2xl font-bold text-theme-primary">{data.streak_days}</p>
                  <p className="text-xs text-theme-muted">Day Streak</p>
                </div>
              </div>
            </GlassCard>
          </motion.div>

          {/* Wellbeing Score Bar */}
          <motion.div variants={itemVariants}>
            <GlassCard className="p-5">
              <div className="flex items-center justify-between mb-3">
                <div className="flex items-center gap-2">
                  <Heart className="w-4 h-4 text-rose-400" aria-hidden="true" />
                  <span className="text-sm font-medium text-theme-primary">Wellbeing Score</span>
                </div>
                <div className="flex items-center gap-2">
                  <Chip size="sm" color={getRiskColor(data.burnout_risk)} variant="flat">
                    {data.burnout_risk === 'low' ? 'Low Risk' : data.burnout_risk === 'moderate' ? 'Moderate Risk' : 'High Risk'}
                  </Chip>
                  <span className={`text-sm font-semibold ${getScoreColor(data.score).text}`}>
                    {data.score}/100 &mdash; {getScoreLabel(data.score)}
                  </span>
                </div>
              </div>
              <Progress
                value={data.score}
                maxValue={100}
                classNames={{
                  indicator: getScoreColor(data.score).indicator,
                  track: 'bg-theme-hover',
                }}
                size="lg"
                aria-label={`Wellbeing score: ${data.score} out of 100`}
              />
            </GlassCard>
          </motion.div>

          {/* Warning Cards */}
          {data.warnings.length > 0 && (
            <motion.div variants={itemVariants} className="space-y-3">
              {data.warnings.map((warning, i) => (
                <GlassCard key={i} className="p-4 border-l-4 border-amber-500">
                  <div className="flex items-start gap-3">
                    <AlertTriangle className="w-5 h-5 text-amber-400 flex-shrink-0 mt-0.5" aria-hidden="true" />
                    <div>
                      <p className="text-sm font-medium text-theme-primary">Burnout Warning</p>
                      <p className="text-sm text-theme-muted">{warning}</p>
                    </div>
                  </div>
                </GlassCard>
              ))}
            </motion.div>
          )}

          {/* Suggested Rest Days */}
          {data.suggested_rest_days.length > 0 && (
            <motion.div variants={itemVariants}>
              <GlassCard className="p-5">
                <div className="flex items-center gap-2 mb-4">
                  <Coffee className="w-4 h-4 text-teal-400" aria-hidden="true" />
                  <h3 className="font-semibold text-theme-primary">Suggested Rest Days</h3>
                </div>
                <p className="text-sm text-theme-muted mb-3">
                  Based on your recent activity, we recommend taking a break on these days:
                </p>
                <div className="flex flex-wrap gap-2">
                  {data.suggested_rest_days.map((day, i) => (
                    <Chip
                      key={i}
                      size="sm"
                      variant="flat"
                      color="primary"
                      startContent={<Sun className="w-3 h-3" />}
                    >
                      {new Date(day).toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' })}
                    </Chip>
                  ))}
                </div>
              </GlassCard>
            </motion.div>
          )}

          {/* Recent Check-ins */}
          {data.recent_checkins.length > 0 && (
            <motion.div variants={itemVariants}>
              <GlassCard className="p-5">
                <div className="flex items-center gap-2 mb-4">
                  <Smile className="w-4 h-4 text-rose-400" aria-hidden="true" />
                  <h3 className="font-semibold text-theme-primary">Recent Mood Check-ins</h3>
                </div>
                <div className="space-y-3">
                  {data.recent_checkins.map((checkin) => (
                    <div key={checkin.id} className="flex items-center gap-3 p-3 rounded-xl bg-theme-elevated">
                      <span className="text-2xl" role="img" aria-label={getMoodLabel(checkin.mood)}>
                        {getMoodEmoji(checkin.mood)}
                      </span>
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-2">
                          <span className="text-sm font-medium text-theme-primary">{getMoodLabel(checkin.mood)}</span>
                          <span className="text-xs text-theme-subtle">
                            {new Date(checkin.created_at).toLocaleDateString(undefined, {
                              month: 'short',
                              day: 'numeric',
                              hour: '2-digit',
                              minute: '2-digit',
                            })}
                          </span>
                        </div>
                        {checkin.note && (
                          <p className="text-xs text-theme-muted truncate">{checkin.note}</p>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              </GlassCard>
            </motion.div>
          )}

          {/* Low Score Call-to-Action */}
          {data.score < 40 && (
            <motion.div variants={itemVariants}>
              <GlassCard className="p-5 border border-rose-500/30">
                <div className="flex items-start gap-3">
                  <TrendingDown className="w-6 h-6 text-rose-400 flex-shrink-0" aria-hidden="true" />
                  <div>
                    <h3 className="font-semibold text-theme-primary mb-1">Your Wellbeing Needs Attention</h3>
                    <p className="text-sm text-theme-muted mb-3">
                      Your wellbeing score is below the healthy threshold. Consider taking some time off
                      and reaching out to your community coordinator if you need support.
                    </p>
                    <Button
                      size="sm"
                      variant="flat"
                      className="bg-theme-elevated text-theme-muted"
                      startContent={<Coffee className="w-4 h-4" aria-hidden="true" />}
                    >
                      View Self-Care Tips
                    </Button>
                  </div>
                </div>
              </GlassCard>
            </motion.div>
          )}
        </motion.div>
      )}

      {/* Mood Check-in Modal */}
      <Modal isOpen={isOpen} onClose={onClose} size="lg" classNames={{
        base: 'bg-content1 border border-theme-default',
      }}>
        <ModalContent>
          <ModalHeader className="text-theme-primary">
            <div className="flex items-center gap-2">
              <Smile className="w-5 h-5 text-rose-400" aria-hidden="true" />
              How Are You Feeling?
            </div>
          </ModalHeader>
          <ModalBody className="space-y-6">
            <p className="text-sm text-theme-muted">
              Take a moment to check in with yourself. Your responses help us suggest when you might need a break.
            </p>

            {/* Mood Selector */}
            <div>
              <p className="text-sm font-medium text-theme-primary mb-3">Select your mood</p>
              <div className="flex justify-center gap-3">
                {MOOD_OPTIONS.map((mood) => (
                  <button
                    key={mood.value}
                    type="button"
                    onClick={() => setSelectedMood(mood.value)}
                    className={`flex flex-col items-center gap-1 p-3 rounded-xl transition-all ${
                      selectedMood === mood.value
                        ? 'bg-rose-500/20 ring-2 ring-rose-500 scale-110'
                        : 'bg-theme-elevated hover:bg-theme-hover'
                    }`}
                    aria-label={`Mood: ${mood.label}`}
                    aria-pressed={selectedMood === mood.value}
                  >
                    <span className="text-3xl" role="img" aria-hidden="true">{mood.emoji}</span>
                    <span className="text-xs text-theme-muted">{mood.label}</span>
                  </button>
                ))}
              </div>
            </div>

            {/* Optional Note */}
            <Textarea
              label="Add a note (optional)"
              placeholder="How's your energy level? Anything on your mind?"
              value={checkinNote}
              onChange={(e) => setCheckinNote(e.target.value)}
              maxLength={500}
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default',
              }}
            />
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onClose} className="text-theme-muted">Cancel</Button>
            <Button
              className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
              onPress={handleCheckin}
              isLoading={isSubmitting}
              startContent={!isSubmitting ? <Heart className="w-4 h-4" aria-hidden="true" /> : undefined}
            >
              Submit Check-in
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default WellbeingTab;
