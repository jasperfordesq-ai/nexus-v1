// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * G3 - Goal Check-in Modal
 *
 * Allows users to log periodic check-ins on their goals with:
 * - Progress slider (0-100%)
 * - Text note
 * - Mood selector (emoji picker)
 *
 * API: GET /api/v2/goals/{id}/checkins
 *      POST /api/v2/goals/{id}/checkins
 */

import { useState, useEffect, useCallback } from 'react';
import { motion } from 'framer-motion';
import {
  Button,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Textarea,
  Slider,
  Spinner,
  Chip,
  Divider,
} from '@heroui/react';
import {
  ClipboardCheck,
  Smile,
  Frown,
  Meh,
  Heart,
  Zap,
  Star,
  Clock,
  TrendingUp,
  MessageSquare,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { formatRelativeTime } from '@/lib/helpers';

import { useTranslation } from 'react-i18next';
/* ───────────────────────── Types ───────────────────────── */

interface CheckIn {
  id: number;
  goal_id: number;
  progress_value: number;
  note: string | null;
  mood: string | null;
  created_at: string;
}

interface GoalCheckinModalProps {
  isOpen: boolean;
  onClose: () => void;
  goalId: number;
  goalTitle: string;
  currentProgress: number;
  onCheckinCreated: () => void;
}

/* ───────────────────────── Mood Options ───────────────────────── */

const MOODS = [
  { value: 'great', labelKey: 'mood.great', icon: Star, color: 'text-amber-400' },
  { value: 'good', labelKey: 'mood.good', icon: Smile, color: 'text-emerald-400' },
  { value: 'okay', labelKey: 'mood.okay', icon: Meh, color: 'text-blue-400' },
  { value: 'struggling', labelKey: 'mood.struggling', icon: Frown, color: 'text-orange-400' },
  { value: 'motivated', labelKey: 'mood.motivated', icon: Zap, color: 'text-purple-400' },
  { value: 'grateful', labelKey: 'mood.grateful', icon: Heart, color: 'text-rose-400' },
] as const;

function getMoodIcon(mood: string) {
  const found = MOODS.find((m) => m.value === mood);
  if (!found) return null;
  const Icon = found.icon;
  return <Icon className={`w-4 h-4 ${found.color}`} aria-hidden="true" />;
}

function getMoodLabel(mood: string): string {
  return MOODS.find((m) => m.value === mood)?.labelKey || mood;
}

/* ───────────────────────── Component ───────────────────────── */

export function GoalCheckinModal({
  isOpen,
  onClose,
  goalId,
  goalTitle,
  currentProgress,
  onCheckinCreated,
}: GoalCheckinModalProps) {
  const toast = useToast();
  const { t } = useTranslation('goals');

  // Form state
  const [progressValue, setProgressValue] = useState(currentProgress);
  const [note, setNote] = useState('');
  const [selectedMood, setSelectedMood] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  // History state
  const [checkins, setCheckins] = useState<CheckIn[]>([]);
  const [isLoadingHistory, setIsLoadingHistory] = useState(true);
  const [showHistory, setShowHistory] = useState(false);

  // Reset form when opening
  useEffect(() => {
    if (isOpen) {
      setProgressValue(currentProgress);
      setNote('');
      setSelectedMood(null);
      setShowHistory(false);
    }
  }, [isOpen, currentProgress]);

  // Load check-in history
  const loadCheckins = useCallback(async () => {
    try {
      setIsLoadingHistory(true);
      const response = await api.get<CheckIn[]>(`/v2/goals/${goalId}/checkins`);
      if (response.success && response.data) {
        setCheckins(Array.isArray(response.data) ? response.data : []);
      }
    } catch (err) {
      logError('Failed to load check-ins', err);
    } finally {
      setIsLoadingHistory(false);
    }
  }, [goalId]);

  useEffect(() => {
    if (isOpen && showHistory) {
      loadCheckins();
    }
  }, [isOpen, showHistory, loadCheckins]);

  const handleSubmit = async () => {
    try {
      setIsSubmitting(true);
      const response = await api.post(`/v2/goals/${goalId}/checkins`, {
        progress_value: progressValue,
        note: note.trim() || undefined,
        mood: selectedMood || undefined,
      });

      if (response.success) {
        toast.success(t('checkin_recorded'));
        onCheckinCreated();
        onClose();
      } else {
        toast.error(t('checkin_failed'));
      }
    } catch (err) {
      logError('Failed to create check-in', err);
      toast.error(t('checkin_failed'));
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      size="lg"
      scrollBehavior="inside"
      classNames={{ base: 'bg-content1 border border-theme-default' }}
    >
      <ModalContent>
        <ModalHeader className="flex flex-col gap-1">
          <div className="flex items-center gap-2 text-theme-primary">
            <ClipboardCheck className="w-5 h-5 text-emerald-400" aria-hidden="true" />
            Check In
          </div>
          <p className="text-sm text-theme-muted font-normal">{goalTitle}</p>
        </ModalHeader>
        <ModalBody className="space-y-5">
          {/* Tab toggle: New Check-in vs History */}
          <div className="flex gap-2">
            <Button
              size="sm"
              variant={!showHistory ? 'solid' : 'flat'}
              className={!showHistory
                ? 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white'
                : 'bg-theme-elevated text-theme-muted'}
              onPress={() => setShowHistory(false)}
              startContent={<ClipboardCheck className="w-4 h-4" aria-hidden="true" />}
            >
              New Check-in
            </Button>
            <Button
              size="sm"
              variant={showHistory ? 'solid' : 'flat'}
              className={showHistory
                ? 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white'
                : 'bg-theme-elevated text-theme-muted'}
              onPress={() => setShowHistory(true)}
              startContent={<Clock className="w-4 h-4" aria-hidden="true" />}
            >
              History
            </Button>
          </div>

          {showHistory ? (
            /* Check-in History */
            <div>
              {isLoadingHistory ? (
                <div className="flex items-center justify-center py-8">
                  <Spinner size="md" color="primary" />
                </div>
              ) : checkins.length === 0 ? (
                <div className="text-center py-8">
                  <ClipboardCheck className="w-10 h-10 text-theme-subtle mx-auto mb-2" aria-hidden="true" />
                  <p className="text-sm text-theme-muted">No check-ins yet. Record your first one!</p>
                </div>
              ) : (
                <div className="space-y-3 max-h-80 overflow-y-auto pr-1">
                  {checkins.map((checkin, index) => (
                    <motion.div
                      key={checkin.id}
                      initial={{ opacity: 0, y: 10 }}
                      animate={{ opacity: 1, y: 0 }}
                      transition={{ delay: index * 0.05 }}
                    >
                      <GlassCard className="p-3">
                        <div className="flex items-start justify-between gap-2">
                          <div className="flex-1 min-w-0">
                            <div className="flex items-center gap-2 mb-1">
                              <TrendingUp className="w-3.5 h-3.5 text-emerald-400" aria-hidden="true" />
                              <span className="text-sm font-semibold text-theme-primary">
                                {checkin.progress_value}%
                              </span>
                              {checkin.mood && (
                                <Chip
                                  size="sm"
                                  variant="flat"
                                  className="text-[10px] bg-theme-elevated"
                                  startContent={getMoodIcon(checkin.mood)}
                                >
                                  {getMoodLabel(checkin.mood)}
                                </Chip>
                              )}
                            </div>
                            {checkin.note && (
                              <p className="text-xs text-theme-muted line-clamp-2 mb-1">
                                {checkin.note}
                              </p>
                            )}
                            <span className="text-xs text-theme-subtle">
                              {formatRelativeTime(checkin.created_at)}
                            </span>
                          </div>
                        </div>
                      </GlassCard>
                    </motion.div>
                  ))}
                </div>
              )}
            </div>
          ) : (
            /* New Check-in Form */
            <>
              {/* Progress Slider */}
              <div>
                <label className="text-sm font-medium text-theme-primary mb-2 block">
                  Progress
                </label>
                <Slider
                  step={5}
                  minValue={0}
                  maxValue={100}
                  value={progressValue}
                  onChange={(val) => setProgressValue(val as number)}
                  className="max-w-full"
                  classNames={{
                    track: 'bg-theme-hover',
                    filler: 'bg-gradient-to-r from-indigo-500 to-purple-600',
                    thumb: 'bg-white shadow-md border-2 border-indigo-500',
                  }}
                  aria-label="Progress percentage"
                />
                <div className="flex justify-between text-xs text-theme-subtle mt-1">
                  <span>0%</span>
                  <span className="font-semibold text-theme-primary">{progressValue}%</span>
                  <span>100%</span>
                </div>
              </div>

              <Divider />

              {/* Mood Selector */}
              <div>
                <label className="text-sm font-medium text-theme-primary mb-2 block">
                  How are you feeling?
                </label>
                <div className="flex gap-2 flex-wrap">
                  {MOODS.map((mood) => {
                    const Icon = mood.icon;
                    const isSelected = selectedMood === mood.value;
                    return (
                      <Button
                        key={mood.value}
                        size="sm"
                        variant={isSelected ? 'solid' : 'flat'}
                        className={isSelected
                          ? 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white'
                          : 'bg-theme-elevated text-theme-muted'}
                        startContent={<Icon className={`w-4 h-4 ${isSelected ? 'text-white' : mood.color}`} aria-hidden="true" />}
                        onPress={() => setSelectedMood(isSelected ? null : mood.value)}
                      >
                        {t(mood.labelKey)}
                      </Button>
                    );
                  })}
                </div>
              </div>

              <Divider />

              {/* Note */}
              <div>
                <Textarea
                  label="Note (optional)"
                  placeholder="How's it going? Any wins or challenges?"
                  value={note}
                  onChange={(e) => setNote(e.target.value)}
                  minRows={2}
                  maxRows={4}
                  classNames={{
                    input: 'bg-transparent text-theme-primary',
                    inputWrapper: 'bg-theme-elevated border-theme-default',
                  }}
                  startContent={<MessageSquare className="w-4 h-4 text-theme-subtle mt-1" aria-hidden="true" />}
                />
              </div>
            </>
          )}
        </ModalBody>
        <ModalFooter>
          <Button variant="flat" onPress={onClose} className="text-theme-muted">
            Cancel
          </Button>
          {!showHistory && (
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              onPress={handleSubmit}
              isLoading={isSubmitting}
            >
              Record Check-in
            </Button>
          )}
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
}

export default GoalCheckinModal;
