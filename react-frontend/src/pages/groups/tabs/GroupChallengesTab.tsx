// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Group Challenges Tab
 * Active and completed challenges with progress tracking, countdown timers,
 * and admin challenge creation.
 */

import { useState, useEffect, useCallback, useMemo } from 'react';
import {
  Button,
  Spinner,
  Progress,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Input,
  Textarea,
  Select,
  SelectItem,
  Chip,
  useDisclosure,
} from '@heroui/react';
import Trophy from 'lucide-react/icons/trophy';
import Target from 'lucide-react/icons/target';
import Clock from 'lucide-react/icons/clock';
import Plus from 'lucide-react/icons/plus';
import Award from 'lucide-react/icons/award';
import Flame from 'lucide-react/icons/flame';
import CheckCircle2 from 'lucide-react/icons/circle-check';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { SafeHtml } from '@/components/ui/SafeHtml';
import { EmptyState } from '@/components/feedback';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { formatRelativeTime } from '@/lib/helpers';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface Challenge {
  id: number;
  title: string;
  description: string;
  metric: ChallengeMetric;
  target_value: number;
  current_value: number;
  reward_xp: number;
  start_date: string;
  end_date: string;
  is_completed: boolean;
  completed_at?: string;
  created_by: {
    id: number;
    name: string;
  };
  created_at: string;
}

type ChallengeMetric = 'posts' | 'discussions' | 'events' | 'members' | 'files';

interface GroupChallengesTabProps {
  groupId: number;
  isAdmin: boolean;
  isMember?: boolean;
}

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

const METRIC_OPTIONS: { key: ChallengeMetric; label: string }[] = [
  { key: 'posts', label: 'Posts' },
  { key: 'discussions', label: 'Discussions' },
  { key: 'events', label: 'Events' },
  { key: 'members', label: 'Members' },
  { key: 'files', label: 'Files' },
];

/** Return progress percentage clamped 0–100 */
function getProgressPercent(current: number, target: number): number {
  if (target <= 0) return 100;
  return Math.min(100, Math.round((current / target) * 100));
}

/** Colour class based on progress percentage */
function getProgressColor(percent: number): 'success' | 'warning' | 'danger' {
  if (percent > 75) return 'success';
  if (percent >= 25) return 'warning';
  return 'danger';
}

/** Human-readable countdown string */
function getTimeRemaining(endDate: string): string {
  const now = Date.now();
  const end = new Date(endDate).getTime();
  const diff = end - now;
  if (diff <= 0) return 'Ended';
  const days = Math.floor(diff / (1000 * 60 * 60 * 24));
  const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
  if (days > 0) return `${days}d ${hours}h remaining`;
  const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
  return `${hours}h ${minutes}m remaining`;
}

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function GroupChallengesTab({ groupId, isAdmin }: GroupChallengesTabProps) {
  const { t } = useTranslation('groups');
  const toast = useToast();
  const { isOpen, onOpen, onClose } = useDisclosure();

  // ─── State ───
  const [challenges, setChallenges] = useState<Challenge[]>([]);
  const [loading, setLoading] = useState(true);
  const [creating, setCreating] = useState(false);
  const [showCompleted, setShowCompleted] = useState(false);

  // Form state
  const [formTitle, setFormTitle] = useState('');
  const [formDescription, setFormDescription] = useState('');
  const [formMetric, setFormMetric] = useState<ChallengeMetric>('posts');
  const [formTarget, setFormTarget] = useState('');
  const [formRewardXp, setFormRewardXp] = useState('');
  const [formEndDate, setFormEndDate] = useState('');

  // ─── Derived lists ───
  const activeChallenges = useMemo(
    () => challenges.filter((c) => !c.is_completed),
    [challenges],
  );
  const completedChallenges = useMemo(
    () => challenges.filter((c) => c.is_completed),
    [challenges],
  );

  // ─── Load challenges ───
  const loadChallenges = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get(`/v2/groups/${groupId}/challenges`);
      if (res.success) {
        const payload = res.data;
        const items: Challenge[] = Array.isArray(payload)
          ? payload
          : (payload as { challenges?: Challenge[] })?.challenges ?? [];
        // Sort active first (by end date ascending), completed by completed_at desc
        items.sort((a, b) => {
          if (a.is_completed !== b.is_completed) return a.is_completed ? 1 : -1;
          if (!a.is_completed) {
            return new Date(a.end_date).getTime() - new Date(b.end_date).getTime();
          }
          return new Date(b.completed_at ?? b.created_at).getTime() -
            new Date(a.completed_at ?? a.created_at).getTime();
        });
        setChallenges(items);
      }
    } catch (err) {
      logError('GroupChallengesTab.load', err);
      toast.error(t('challenges.load_failed', 'Failed to load challenges'));
    }
    setLoading(false);
  }, [groupId, toast, t]);

  useEffect(() => { loadChallenges(); }, [loadChallenges]);

  // ─── Reset form ───
  const resetForm = useCallback(() => {
    setFormTitle('');
    setFormDescription('');
    setFormMetric('posts');
    setFormTarget('');
    setFormRewardXp('');
    setFormEndDate('');
  }, []);

  // ─── Create challenge ───
  const handleCreate = useCallback(async () => {
    const trimmedTitle = formTitle.trim();
    const trimmedDesc = formDescription.trim();
    const targetVal = parseInt(formTarget, 10);
    const xpVal = parseInt(formRewardXp, 10);
    if (!trimmedTitle || !trimmedDesc || !targetVal || !xpVal || !formEndDate) return;

    setCreating(true);
    try {
      const res = await api.post(`/v2/groups/${groupId}/challenges`, {
        title: trimmedTitle,
        description: trimmedDesc,
        metric: formMetric,
        target_value: targetVal,
        reward_xp: xpVal,
        end_date: formEndDate,
      });
      if (res.success) {
        toast.success(t('challenges.created', 'Challenge created'));
        resetForm();
        onClose();
        loadChallenges();
      }
    } catch (err) {
      logError('GroupChallengesTab.create', err);
      toast.error(t('challenges.create_failed', 'Failed to create challenge'));
    }
    setCreating(false);
  }, [groupId, formTitle, formDescription, formMetric, formTarget, formRewardXp, formEndDate, toast, onClose, loadChallenges, resetForm, t]);

  // ─── Render challenge card ───
  const renderChallengeCard = (challenge: Challenge) => {
    const percent = getProgressPercent(challenge.current_value, challenge.target_value);
    const color = getProgressColor(percent);
    const ended = new Date(challenge.end_date).getTime() <= Date.now();

    return (
      <div
        key={challenge.id}
        className={`p-4 rounded-lg border transition-colors ${
          challenge.is_completed
            ? 'border-success/30 bg-success/5'
            : 'border-theme-default bg-theme-elevated'
        }`}
      >
        {/* Header row */}
        <div className="flex items-start justify-between gap-3">
          <div className="flex-1 min-w-0">
            <div className="flex items-center gap-2 mb-1">
              {challenge.is_completed ? (
                <Trophy className="w-4 h-4 text-warning flex-shrink-0" aria-hidden="true" />
              ) : (
                <Target className="w-4 h-4 text-primary flex-shrink-0" aria-hidden="true" />
              )}
              <h3 className="font-semibold text-theme-primary truncate">
                {challenge.title}
              </h3>
              <Chip
                size="sm"
                variant="flat"
                className="flex-shrink-0 capitalize"
                color={challenge.is_completed ? 'success' : ended ? 'danger' : 'primary'}
              >
                {challenge.is_completed
                  ? t('challenges.completed_chip', 'Completed')
                  : ended
                    ? t('challenges.ended_chip', 'Ended')
                    : challenge.metric}
              </Chip>
            </div>
            <SafeHtml content={challenge.description} className="text-sm text-theme-secondary line-clamp-2" as="p" />
          </div>

          {/* XP reward badge */}
          <div className="flex-shrink-0 flex items-center gap-1 px-2 py-1 rounded-lg bg-gradient-to-r from-amber-500/10 to-orange-500/10">
            <Flame className="w-3.5 h-3.5 text-[var(--color-warning)]" aria-hidden="true" />
            <span className="text-xs font-semibold text-amber-600 dark:text-amber-400">
              {challenge.reward_xp} XP
            </span>
          </div>
        </div>

        {/* Progress bar */}
        <div className="mt-3">
          <div className="flex items-center justify-between mb-1">
            <span className="text-xs text-theme-subtle">
              {challenge.current_value} / {challenge.target_value} {challenge.metric}
            </span>
            <span className="text-xs font-medium text-theme-secondary">
              {percent}%
            </span>
          </div>
          <Progress
            aria-label={t('challenges.progress_aria', 'Challenge progress: {{percent}}%', { percent })}
            value={percent}
            color={color}
            size="sm"
            className="w-full"
          />
        </div>

        {/* Footer: time remaining + creator */}
        <div className="flex items-center justify-between mt-3 text-xs text-theme-subtle">
          <div className="flex items-center gap-1">
            {challenge.is_completed ? (
              <>
                <CheckCircle2 className="w-3 h-3 text-success" aria-hidden="true" />
                <span>
                  {t('challenges.completed_on', 'Completed')}
                  {challenge.completed_at && ` ${formatRelativeTime(challenge.completed_at)}`}
                </span>
              </>
            ) : (
              <>
                <Clock className="w-3 h-3" aria-hidden="true" />
                <span>{getTimeRemaining(challenge.end_date)}</span>
              </>
            )}
          </div>
          <span>
            {t('challenges.created_by', 'by {{name}}', { name: challenge.created_by.name })}
            {' · '}
            {formatRelativeTime(challenge.created_at)}
          </span>
        </div>
      </div>
    );
  };

  // ─── Render ───
  return (
    <div className="space-y-4">
      {/* Active Challenges */}
      <GlassCard className="p-6">
        <div className="flex justify-between items-center mb-4">
          <h2 className="text-lg font-semibold text-theme-primary flex items-center gap-2">
            <Target className="w-5 h-5" aria-hidden="true" />
            {t('challenges.heading', 'Challenges')}
          </h2>
          {isAdmin && (
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              size="sm"
              startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
              onPress={onOpen}
            >
              {t('challenges.create', 'Create Challenge')}
            </Button>
          )}
        </div>

        {loading ? (
          <div className="flex justify-center py-8">
            <Spinner size="lg" />
          </div>
        ) : activeChallenges.length === 0 && completedChallenges.length === 0 ? (
          <EmptyState
            icon={<Trophy className="w-12 h-12" aria-hidden="true" />}
            title={t('challenges.no_challenges_title', 'No challenges yet')}
            description={
              isAdmin
                ? t('challenges.no_challenges_admin_desc', 'Create a challenge to motivate group members')
                : t('challenges.no_challenges_desc', 'No challenges have been created yet')
            }
            action={
              isAdmin
                ? {
                    label: t('challenges.create', 'Create Challenge'),
                    onClick: onOpen,
                  }
                : undefined
            }
          />
        ) : (
          <>
            {/* Active list */}
            {activeChallenges.length > 0 ? (
              <div className="space-y-3" role="list" aria-label={t('challenges.active_list_aria', 'Active challenges')}>
                {activeChallenges.map(renderChallengeCard)}
              </div>
            ) : (
              <p className="text-sm text-theme-subtle text-center py-4">
                {t('challenges.no_active', 'No active challenges right now')}
              </p>
            )}

            {/* Completed section (collapsed by default) */}
            {completedChallenges.length > 0 && (
              <div className="mt-6">
                <Button
                  type="button"
                  variant="light"
                  size="sm"
                  className="flex items-center gap-2 text-sm font-medium text-theme-secondary hover:text-theme-primary"
                  onPress={() => setShowCompleted(!showCompleted)}
                  aria-expanded={showCompleted}
                  aria-controls="completed-challenges-list"
                >
                  <Award className="w-4 h-4 text-warning" aria-hidden="true" />
                  {t('challenges.completed_section', 'Completed Challenges')}
                  <Chip size="sm" variant="flat" color="success">
                    {completedChallenges.length}
                  </Chip>
                </Button>

                {showCompleted && (
                  <div
                    id="completed-challenges-list"
                    className="space-y-3 mt-3"
                    role="list"
                    aria-label={t('challenges.completed_list_aria', 'Completed challenges')}
                  >
                    {completedChallenges.map(renderChallengeCard)}
                  </div>
                )}
              </div>
            )}
          </>
        )}
      </GlassCard>

      {/* Create Challenge Modal */}
      <Modal
        isOpen={isOpen}
        onOpenChange={(open) => {
          if (!open) {
            resetForm();
            onClose();
          }
        }}
        classNames={{
          base: 'bg-content1 border border-theme-default',
          header: 'border-b border-theme-default',
          footer: 'border-t border-theme-default',
        }}
      >
        <ModalContent>
          {(onModalClose) => (
            <>
              <ModalHeader className="text-theme-primary flex items-center gap-2">
                <Target className="w-5 h-5 text-purple-400" aria-hidden="true" />
                {t('challenges.create_modal_title', 'Create Challenge')}
              </ModalHeader>
              <ModalBody className="gap-4">
                <Input
                  label={t('challenges.title_label', 'Title')}
                  placeholder={t('challenges.title_placeholder', 'Challenge title')}
                  value={formTitle}
                  onChange={(e) => setFormTitle(e.target.value)}
                  isRequired
                  classNames={{
                    input: 'bg-transparent text-theme-primary',
                    inputWrapper: 'bg-theme-elevated border-theme-default',
                    label: 'text-theme-muted',
                  }}
                />
                <Textarea
                  label={t('challenges.description_label', 'Description')}
                  placeholder={t('challenges.description_placeholder', 'Describe the challenge and what participants need to do...')}
                  value={formDescription}
                  onChange={(e) => setFormDescription(e.target.value)}
                  minRows={3}
                  isRequired
                  classNames={{
                    input: 'bg-transparent text-theme-primary',
                    inputWrapper: 'bg-theme-elevated border-theme-default',
                    label: 'text-theme-muted',
                  }}
                />
                <Select
                  label={t('challenges.metric_label', 'Metric')}
                  selectedKeys={[formMetric]}
                  onSelectionChange={(keys) => {
                    const selected = Array.from(keys)[0] as ChallengeMetric;
                    if (selected) setFormMetric(selected);
                  }}
                  classNames={{
                    trigger: 'bg-theme-elevated border-theme-default',
                    label: 'text-theme-muted',
                    value: 'text-theme-primary',
                  }}
                >
                  {METRIC_OPTIONS.map((opt) => (
                    <SelectItem key={opt.key}>
                      {t(`challenges.metric_${opt.key}`, opt.label)}
                    </SelectItem>
                  ))}
                </Select>
                <div className="grid grid-cols-2 gap-3">
                  <Input
                    label={t('challenges.target_label', 'Target Value')}
                    placeholder="e.g. 50"
                    type="number"
                    min={1}
                    value={formTarget}
                    onChange={(e) => setFormTarget(e.target.value)}
                    isRequired
                    classNames={{
                      input: 'bg-transparent text-theme-primary',
                      inputWrapper: 'bg-theme-elevated border-theme-default',
                      label: 'text-theme-muted',
                    }}
                  />
                  <Input
                    label={t('challenges.reward_xp_label', 'Reward XP')}
                    placeholder="e.g. 100"
                    type="number"
                    min={1}
                    value={formRewardXp}
                    onChange={(e) => setFormRewardXp(e.target.value)}
                    isRequired
                    classNames={{
                      input: 'bg-transparent text-theme-primary',
                      inputWrapper: 'bg-theme-elevated border-theme-default',
                      label: 'text-theme-muted',
                    }}
                  />
                </div>
                <Input
                  label={t('challenges.end_date_label', 'End Date')}
                  type="date"
                  value={formEndDate}
                  onChange={(e) => setFormEndDate(e.target.value)}
                  isRequired
                  classNames={{
                    input: 'bg-transparent text-theme-primary',
                    inputWrapper: 'bg-theme-elevated border-theme-default',
                    label: 'text-theme-muted',
                  }}
                />
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onModalClose}>
                  {t('challenges.cancel', 'Cancel')}
                </Button>
                <Button
                  className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                  isLoading={creating}
                  isDisabled={
                    !formTitle.trim() ||
                    !formDescription.trim() ||
                    !formTarget ||
                    !formRewardXp ||
                    !formEndDate
                  }
                  onPress={handleCreate}
                >
                  {t('challenges.create_submit', 'Create Challenge')}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}

export default GroupChallengesTab;
