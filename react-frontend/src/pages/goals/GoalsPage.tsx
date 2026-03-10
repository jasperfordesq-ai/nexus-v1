// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Goals Page - Personal and community goal tracking
 *
 * Features:
 * - Create, edit, delete goals
 * - Progress tracking with +1 increments
 * - Mark complete with celebration animation
 * - Buddy system for public goals
 * - Goal detail modal with timeline
 * - Discover tab for community goals
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { Link } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import {
  Button,
  Input,
  Progress,
  Chip,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Textarea,
  Switch,
  Avatar,
  Dropdown,
  DropdownTrigger,
  DropdownMenu,
  DropdownItem,
  useDisclosure,
  Skeleton,
} from '@heroui/react';
import {
  Target,
  Plus,
  RefreshCw,
  AlertTriangle,
  Calendar,
  TrendingUp,
  Users,
  CheckCircle,
  Globe,
  Lock,
  MoreVertical,
  Edit3,
  Trash2,
  Award,
  Clock,
  MessageCircle,
  Heart,
  UserPlus,
  Sparkles,
  PartyPopper,
  ClipboardCheck,
  FileText,
  History,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useAuth, useToast, useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl } from '@/lib/helpers';
import { GoalTemplatePickerModal } from './components/GoalTemplatePickerModal';
import { GoalCheckinModal } from './components/GoalCheckinModal';
import { GoalReminderToggle } from './components/GoalReminderToggle';
import { GoalProgressHistory } from './components/GoalProgressHistory';

/* ───────────────────────── Types ───────────────────────── */

interface Goal {
  id: number;
  user_id: number;
  title: string;
  description: string;
  target_value: number;
  current_value: number;
  deadline: string | null;
  is_public: boolean;
  status: 'active' | 'completed';
  created_at: string;
  updated_at: string;
  user_name: string;
  user_avatar: string | null;
  progress_percentage: number;
  is_owner?: boolean;
  buddy_id?: number | null;
  buddy_name?: string | null;
  buddy_avatar?: string | null;
  is_buddy?: boolean;
  likes_count?: number;
  comments_count?: number;
  progress_history?: ProgressEntry[];
}

interface ProgressEntry {
  id: number;
  increment: number;
  note: string | null;
  created_at: string;
}

type GoalTab = 'my' | 'buddying' | 'discover';

/* ───────────────────────── Confetti Particle ───────────────────────── */

function ConfettiCelebration({ show }: { show: boolean }) {
  if (!show) return null;

  const particles = Array.from({ length: 20 }, (_, i) => ({
    id: i,
    x: Math.random() * 200 - 100,
    y: -(Math.random() * 200 + 50),
    rotation: Math.random() * 360,
    scale: Math.random() * 0.5 + 0.5,
    color: ['#6366f1', '#a855f7', '#22c55e', '#f59e0b', '#ec4899'][Math.floor(Math.random() * 5)],
  }));

  return (
    <AnimatePresence>
      {show && (
        <div className="absolute inset-0 pointer-events-none overflow-hidden z-10">
          {particles.map((p) => (
            <motion.div
              key={p.id}
              initial={{ x: '50%', y: '50%', opacity: 1, scale: p.scale, rotate: 0 }}
              animate={{
                x: `calc(50% + ${p.x}px)`,
                y: `calc(50% + ${p.y}px)`,
                opacity: 0,
                rotate: p.rotation,
              }}
              exit={{ opacity: 0 }}
              transition={{ duration: 1.2, ease: 'easeOut' }}
              className="absolute w-3 h-3 rounded-sm"
              style={{ backgroundColor: p.color }}
            />
          ))}
          <motion.div
            initial={{ scale: 0, opacity: 0 }}
            animate={{ scale: [0, 1.2, 1], opacity: [0, 1, 0] }}
            transition={{ duration: 1.5, times: [0, 0.3, 1] }}
            className="absolute inset-0 flex items-center justify-center"
          >
            <PartyPopper className="w-16 h-16 text-amber-400" />
          </motion.div>
        </div>
      )}
    </AnimatePresence>
  );
}

/* ───────────────────────── Main Component ───────────────────────── */

export function GoalsPage() {
  const { t } = useTranslation('gamification');
  usePageTitle(t('goals.page_title'));
  const { isAuthenticated, user } = useAuth();
  const toast = useToast();
  const [goals, setGoals] = useState<Goal[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [tab, setTab] = useState<GoalTab>('my');
  const [hasMore, setHasMore] = useState(false);
  const [, setCursor] = useState<string | undefined>();
  const cursorRef = useRef<string | undefined>();

  // Create modal
  const { isOpen, onOpen, onClose } = useDisclosure();
  const [newGoal, setNewGoal] = useState({
    title: '',
    description: '',
    target_value: 100,
    deadline: '',
    is_public: false,
  });
  const [isCreating, setIsCreating] = useState(false);

  // Edit modal
  const { isOpen: isEditOpen, onOpen: onEditOpen, onClose: onEditClose } = useDisclosure();
  const [editGoal, setEditGoal] = useState<Goal | null>(null);
  const [editForm, setEditForm] = useState({
    title: '',
    description: '',
    target_value: 100,
    deadline: '',
    is_public: false,
  });
  const [isSavingEdit, setIsSavingEdit] = useState(false);

  // Delete modal
  const { isOpen: isDeleteOpen, onOpen: onDeleteOpen, onClose: onDeleteClose } = useDisclosure();
  const [deleteGoal, setDeleteGoal] = useState<Goal | null>(null);
  const [isDeleting, setIsDeleting] = useState(false);

  // Detail modal
  const { isOpen: isDetailOpen, onOpen: onDetailOpen, onClose: onDetailClose } = useDisclosure();
  const [detailGoal, setDetailGoal] = useState<Goal | null>(null);

  // Completion celebration
  const [celebratingId, setCelebratingId] = useState<number | null>(null);

  // G1 - Template picker
  const { isOpen: isTemplateOpen, onOpen: onTemplateOpen, onClose: onTemplateClose } = useDisclosure();

  // G3 - Check-in modal
  const { isOpen: isCheckinOpen, onOpen: onCheckinOpen, onClose: onCheckinClose } = useDisclosure();
  const [checkinGoal, setCheckinGoal] = useState<Goal | null>(null);

  const loadGoals = useCallback(async (append = false) => {
    try {
      if (!append) {
        setIsLoading(true);
        setError(null);
      }

      const params = new URLSearchParams();
      params.set('per_page', '20');
      if (append && cursorRef.current) params.set('cursor', cursorRef.current);

      const endpoint = tab === 'discover'
        ? `/v2/goals/discover?${params}`
        : tab === 'buddying'
        ? `/v2/goals/mentoring?${params}`
        : `/v2/goals?${params}&status=all`;

      const response = await api.get<Goal[]>(endpoint);

      if (response.success && response.data) {
        const items = Array.isArray(response.data) ? response.data : [];

        if (append) {
          setGoals((prev) => [...prev, ...items]);
        } else {
          setGoals(items);
        }
        setHasMore(response.meta?.has_more ?? false);
        const newCursor = response.meta?.cursor ?? undefined;
        cursorRef.current = newCursor;
        setCursor(newCursor);
      } else {
        if (!append) setError(t('goals.load_error', 'Failed to load goals. Please try again.'));
      }
    } catch (err) {
      logError('Failed to load goals', err);
      if (!append) setError(t('goals.load_error', 'Failed to load goals. Please try again.'));
    } finally {
      setIsLoading(false);
    }
  }, [tab, t]);

  useEffect(() => {
    cursorRef.current = undefined;
    setCursor(undefined);
    loadGoals();
  }, [tab, loadGoals]);

  const handleCreate = async () => {
    if (!newGoal.title.trim()) return;

    try {
      setIsCreating(true);
      const response = await api.post('/v2/goals', {
        title: newGoal.title,
        description: newGoal.description,
        target_value: newGoal.target_value,
        deadline: newGoal.deadline || undefined,
        is_public: newGoal.is_public,
      });

      if (response.success) {
        onClose();
        setNewGoal({ title: '', description: '', target_value: 100, deadline: '', is_public: false });
        toast.success(t('goals.toast.created'));
        loadGoals();
      }
    } catch (err) {
      logError('Failed to create goal', err);
      toast.error(t('goals.toast.create_failed'));
    } finally {
      setIsCreating(false);
    }
  };

  const handleProgressUpdate = async (goalId: number, increment: number) => {
    try {
      const response = await api.post(`/v2/goals/${goalId}/progress`, { increment });
      if (response.success) {
        toast.success(t('goals.toast.progress_updated'));
        loadGoals();
      }
    } catch (err) {
      logError('Failed to update progress', err);
      toast.error(t('goals.toast.progress_failed'));
    }
  };

  // ── Edit ──
  const openEdit = (goal: Goal) => {
    setEditGoal(goal);
    setEditForm({
      title: goal.title,
      description: goal.description || '',
      target_value: goal.target_value,
      deadline: goal.deadline ? goal.deadline.split('T')[0] : '',
      is_public: goal.is_public,
    });
    onEditOpen();
  };

  const handleSaveEdit = async () => {
    if (!editGoal || !editForm.title.trim()) return;

    try {
      setIsSavingEdit(true);
      const response = await api.put(`/v2/goals/${editGoal.id}`, {
        title: editForm.title,
        description: editForm.description,
        target_value: editForm.target_value,
        deadline: editForm.deadline || null,
        is_public: editForm.is_public,
      });

      if (response.success) {
        onEditClose();
        setEditGoal(null);
        toast.success(t('goals.toast.updated'));
        loadGoals();
      }
    } catch (err) {
      logError('Failed to update goal', err);
      toast.error(t('goals.toast.update_failed'));
    } finally {
      setIsSavingEdit(false);
    }
  };

  // ── Delete ──
  const openDelete = (goal: Goal) => {
    setDeleteGoal(goal);
    onDeleteOpen();
  };

  const handleDelete = async () => {
    if (!deleteGoal) return;

    try {
      setIsDeleting(true);
      const response = await api.delete(`/v2/goals/${deleteGoal.id}`);

      if (response.success) {
        onDeleteClose();
        setGoals((prev) => prev.filter((g) => g.id !== deleteGoal.id));
        setDeleteGoal(null);
        toast.success(t('goals.toast.deleted'));
      }
    } catch (err) {
      logError('Failed to delete goal', err);
      toast.error(t('goals.toast.delete_failed'));
    } finally {
      setIsDeleting(false);
    }
  };

  // ── Complete ──
  const handleComplete = async (goal: Goal) => {
    try {
      const response = await api.post(`/v2/goals/${goal.id}/complete`, {});

      if (response.success) {
        setCelebratingId(goal.id);
        // Update local state immediately
        setGoals((prev) =>
          prev.map((g) =>
            g.id === goal.id
              ? { ...g, status: 'completed' as const, progress_percentage: 100, current_value: g.target_value }
              : g
          )
        );
        toast.success(t('goals.toast.completed'));
        // Clear celebration after animation
        setTimeout(() => setCelebratingId(null), 2000);
      }
    } catch (err) {
      logError('Failed to complete goal', err);
      toast.error(t('goals.toast.complete_failed'));
    }
  };

  // ── Buddy ──
  const handleBecomeBuddy = async (goal: Goal) => {
    try {
      const response = await api.post(`/v2/goals/${goal.id}/buddy`, {});

      if (response.success) {
        toast.success(t('goals.toast.buddy_joined'));
        // Update local state
        setGoals((prev) =>
          prev.map((g) =>
            g.id === goal.id
              ? { ...g, buddy_id: user?.id ?? null, buddy_name: user ? `${user.first_name} ${user.last_name}` : null, is_buddy: true }
              : g
          )
        );
      }
    } catch (err) {
      logError('Failed to become buddy', err);
      toast.error(t('goals.toast.buddy_failed'));
    }
  };

  // ── Check-in (G3) ──
  const openCheckin = (goal: Goal) => {
    setCheckinGoal(goal);
    onCheckinOpen();
  };

  // ── Detail ──
  const openDetail = async (goal: Goal) => {
    setDetailGoal(goal);
    onDetailOpen();

    // Fetch fresh goal data for the detail modal
    try {
      const response = await api.get<Goal>(`/v2/goals/${goal.id}`);
      if (response.success && response.data && response.data.id) {
        setDetailGoal(response.data);
      }
    } catch (err) {
      logError('Failed to load goal details', err);
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

  const modalClasses = {
    base: 'bg-content1 border border-theme-default',
  };

  const inputClasses = {
    input: 'bg-transparent text-theme-primary',
    inputWrapper: 'bg-theme-elevated border-theme-default',
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-3">
            <Target className="w-7 h-7 text-emerald-400" aria-hidden="true" />
            {t('goals.title')}
          </h1>
          <p className="text-theme-muted mt-1">{t('goals.subtitle')}</p>
        </div>
        {isAuthenticated && (
          <div className="flex gap-2">
            <Button
              variant="flat"
              className="bg-theme-elevated text-theme-primary"
              startContent={<FileText className="w-4 h-4" aria-hidden="true" />}
              onPress={onTemplateOpen}
            >
              {t('goals.from_template', 'From Template')}
            </Button>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
              onPress={onOpen}
            >
              {t('goals.new_goal')}
            </Button>
          </div>
        )}
      </div>

      {/* Tabs */}
      <div className="flex gap-2 flex-wrap">
        <Button
          variant={tab === 'my' ? 'solid' : 'flat'}
          className={tab === 'my' ? 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white' : 'bg-theme-elevated text-theme-muted'}
          onPress={() => setTab('my')}
          startContent={<Target className="w-4 h-4" aria-hidden="true" />}
        >
          {t('goals.tab_my')}
        </Button>
        <Button
          variant={tab === 'buddying' ? 'solid' : 'flat'}
          className={tab === 'buddying' ? 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white' : 'bg-theme-elevated text-theme-muted'}
          onPress={() => setTab('buddying')}
          startContent={<Users className="w-4 h-4" aria-hidden="true" />}
        >
          {t('goals.tab_buddying')}
        </Button>
        <Button
          variant={tab === 'discover' ? 'solid' : 'flat'}
          className={tab === 'discover' ? 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white' : 'bg-theme-elevated text-theme-muted'}
          onPress={() => setTab('discover')}
          startContent={<Globe className="w-4 h-4" aria-hidden="true" />}
        >
          {t('goals.tab_discover')}
        </Button>
      </div>

      {/* Error State */}
      {error && !isLoading && (
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary mb-2">{t('goals.unable_to_load')}</h2>
          <p className="text-theme-muted mb-4">{error}</p>
          <Button
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
            startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
            onPress={() => loadGoals()}
          >
            {t('goals.try_again')}
          </Button>
        </GlassCard>
      )}

      {/* Goals List */}
      {!error && (
        <>
          {isLoading ? (
            <div className="space-y-4" aria-label="Loading goals" aria-busy="true">
              {[1, 2, 3].map((i) => (
                <GlassCard key={i} className="p-5">
                  <div className="space-y-3">
                    <Skeleton className="rounded-lg w-1/3">
                      <div className="h-5 rounded-lg bg-default-300" />
                    </Skeleton>
                    <Skeleton className="rounded-lg w-full">
                      <div className="h-3 rounded-lg bg-default-200" />
                    </Skeleton>
                    <Skeleton className="rounded-lg w-full">
                      <div className="h-2 rounded-lg bg-default-200" />
                    </Skeleton>
                    <Skeleton className="rounded-lg w-1/4">
                      <div className="h-3 rounded-lg bg-default-200" />
                    </Skeleton>
                  </div>
                </GlassCard>
              ))}
            </div>
          ) : goals.length === 0 ? (
            <EmptyState
              icon={tab === 'buddying' ? <Users className="w-12 h-12" aria-hidden="true" /> : <Target className="w-12 h-12" aria-hidden="true" />}
              title={tab === 'my' ? t('goals.empty_my_title') : tab === 'buddying' ? t('goals.empty_buddying_title') : t('goals.empty_discover_title')}
              description={tab === 'my' ? t('goals.empty_my_description') : tab === 'buddying' ? t('goals.empty_buddying_description') : t('goals.empty_discover_description')}
              action={
                tab === 'my' && isAuthenticated ? (
                  <Button
                    className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                    onPress={onOpen}
                  >
                    {t('goals.create_goal')}
                  </Button>
                ) : tab === 'buddying' && isAuthenticated ? (
                  <Button
                    className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                    onPress={() => setTab('discover')}
                    startContent={<Globe className="w-4 h-4" aria-hidden="true" />}
                  >
                    {t('goals.tab_discover')}
                  </Button>
                ) : undefined
              }
            />
          ) : (
            <motion.div
              variants={containerVariants}
              initial="hidden"
              animate="visible"
              className="space-y-4"
            >
              {goals.map((goal) => {
                const isOwner = goal.is_owner ?? goal.user_id === user?.id;
                return (
                  <motion.div key={goal.id} variants={itemVariants}>
                    <GoalCard
                      goal={goal}
                      isOwner={isOwner}
                      currentUserId={user?.id ?? null}
                      isCelebrating={celebratingId === goal.id}
                      onProgressUpdate={handleProgressUpdate}
                      onEdit={openEdit}
                      onDelete={openDelete}
                      onComplete={handleComplete}
                      onBecomeBuddy={handleBecomeBuddy}
                      onOpenDetail={openDetail}
                      onCheckin={openCheckin}
                      isDiscoverTab={tab === 'discover'}
                      isBuddyingTab={tab === 'buddying'}
                    />
                  </motion.div>
                );
              })}

              {hasMore && (
                <div className="pt-4 text-center">
                  <Button
                    variant="flat"
                    className="bg-theme-elevated text-theme-muted"
                    onPress={() => loadGoals(true)}
                  >
                    {t('goals.load_more')}
                  </Button>
                </div>
              )}
            </motion.div>
          )}
        </>
      )}

      {/* ─── Create Goal Modal ─── */}
      <Modal isOpen={isOpen} onClose={onClose} size="lg" classNames={modalClasses}>
        <ModalContent>
          <ModalHeader className="text-theme-primary">{t('goals.modal.create_title')}</ModalHeader>
          <ModalBody className="space-y-4">
            <Input
              label={t('goals.modal.goal_title_label')}
              placeholder={t('goals.modal.goal_title_placeholder')}
              value={newGoal.title}
              onChange={(e) => setNewGoal((prev) => ({ ...prev, title: e.target.value }))}
              isRequired
              classNames={inputClasses}
            />
            <Textarea
              label={t('goals.modal.description_label')}
              placeholder={t('goals.modal.description_placeholder')}
              value={newGoal.description}
              onChange={(e) => setNewGoal((prev) => ({ ...prev, description: e.target.value }))}
              classNames={inputClasses}
            />
            <Input
              type="number"
              label={t('goals.modal.target_value_label')}
              placeholder="100"
              value={String(newGoal.target_value)}
              onChange={(e) => setNewGoal((prev) => ({ ...prev, target_value: parseInt(e.target.value) || 0 }))}
              classNames={inputClasses}
            />
            <Input
              type="date"
              label={t('goals.modal.deadline_label')}
              value={newGoal.deadline}
              onChange={(e) => setNewGoal((prev) => ({ ...prev, deadline: e.target.value }))}
              classNames={inputClasses}
            />
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-theme-primary">{t('goals.modal.make_public')}</p>
                <p className="text-xs text-theme-muted">{t('goals.modal.make_public_desc')}</p>
              </div>
              <Switch
                isSelected={newGoal.is_public}
                onValueChange={(val) => setNewGoal((prev) => ({ ...prev, is_public: val }))}
              />
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onClose} className="text-theme-muted">{t('goals.modal.cancel')}</Button>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              onPress={handleCreate}
              isLoading={isCreating}
              isDisabled={!newGoal.title.trim()}
            >
              {t('goals.create_goal')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* ─── Edit Goal Modal ─── */}
      <Modal isOpen={isEditOpen} onClose={onEditClose} size="lg" classNames={modalClasses}>
        <ModalContent>
          <ModalHeader className="text-theme-primary">{t('goals.modal.edit_title')}</ModalHeader>
          <ModalBody className="space-y-4">
            <Input
              label={t('goals.modal.goal_title_label')}
              placeholder={t('goals.modal.goal_title_placeholder')}
              value={editForm.title}
              onChange={(e) => setEditForm((prev) => ({ ...prev, title: e.target.value }))}
              isRequired
              classNames={inputClasses}
            />
            <Textarea
              label={t('goals.modal.description_label')}
              placeholder={t('goals.modal.description_placeholder')}
              value={editForm.description}
              onChange={(e) => setEditForm((prev) => ({ ...prev, description: e.target.value }))}
              classNames={inputClasses}
            />
            <Input
              type="number"
              label={t('goals.modal.target_value_label')}
              placeholder="100"
              value={String(editForm.target_value)}
              onChange={(e) => setEditForm((prev) => ({ ...prev, target_value: parseInt(e.target.value) || 0 }))}
              classNames={inputClasses}
            />
            <Input
              type="date"
              label={t('goals.modal.deadline_label')}
              value={editForm.deadline}
              onChange={(e) => setEditForm((prev) => ({ ...prev, deadline: e.target.value }))}
              classNames={inputClasses}
            />
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-theme-primary">{t('goals.modal.make_public')}</p>
                <p className="text-xs text-theme-muted">{t('goals.modal.make_public_desc')}</p>
              </div>
              <Switch
                isSelected={editForm.is_public}
                onValueChange={(val) => setEditForm((prev) => ({ ...prev, is_public: val }))}
              />
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onEditClose} className="text-theme-muted">{t('goals.modal.cancel')}</Button>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              onPress={handleSaveEdit}
              isLoading={isSavingEdit}
              isDisabled={!editForm.title.trim()}
            >
              {t('goals.modal.save_changes')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* ─── Delete Confirmation Modal ─── */}
      <Modal isOpen={isDeleteOpen} onClose={onDeleteClose} size="sm" classNames={modalClasses}>
        <ModalContent>
          <ModalHeader className="text-theme-primary">{t('goals.modal.delete_title')}</ModalHeader>
          <ModalBody>
            <div className="text-center py-2">
              <Trash2 className="w-12 h-12 text-red-400 mx-auto mb-3" aria-hidden="true" />
              <p className="text-theme-primary font-medium mb-1">
                {t('goals.modal.delete_confirm', { title: deleteGoal?.title })}
              </p>
              <p className="text-sm text-theme-muted">{t('goals.modal.delete_warning')}</p>
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onDeleteClose} className="text-theme-muted">{t('goals.modal.cancel')}</Button>
            <Button
              color="danger"
              onPress={handleDelete}
              isLoading={isDeleting}
            >
              {t('goals.modal.delete_goal')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* ─── G1 - Template Picker Modal ─── */}
      <GoalTemplatePickerModal
        isOpen={isTemplateOpen}
        onClose={onTemplateClose}
        onTemplateSelected={() => {
          setCursor(undefined);
          loadGoals();
        }}
      />

      {/* ─── G3 - Check-in Modal ─── */}
      {checkinGoal && (
        <GoalCheckinModal
          isOpen={isCheckinOpen}
          onClose={() => {
            onCheckinClose();
            setCheckinGoal(null);
          }}
          goalId={checkinGoal.id}
          goalTitle={checkinGoal.title}
          currentProgress={Math.round(checkinGoal.progress_percentage)}
          onCheckinCreated={() => {
            setCursor(undefined);
            loadGoals();
          }}
        />
      )}

      {/* ─── Goal Detail Modal ─── */}
      <Modal isOpen={isDetailOpen} onClose={onDetailClose} size="2xl" scrollBehavior="inside" classNames={modalClasses}>
        <ModalContent>
          {detailGoal && (
            <>
              <ModalHeader className="flex flex-col gap-1">
                <div className="flex items-center gap-2 text-theme-primary">
                  <Target className="w-5 h-5 text-emerald-400" aria-hidden="true" />
                  {detailGoal.title}
                </div>
                <div className="flex items-center gap-2 flex-wrap">
                  {detailGoal.status === 'completed' ? (
                    <Chip size="sm" color="success" variant="flat" startContent={<CheckCircle className="w-3 h-3" />}>
                      {t('goals.status.completed')}
                    </Chip>
                  ) : (
                    <Chip size="sm" color="primary" variant="flat">{t('goals.status.active')}</Chip>
                  )}
                  {detailGoal.is_public ? (
                    <Chip size="sm" variant="flat" className="text-theme-subtle" startContent={<Globe className="w-3 h-3" />}>
                      {t('goals.visibility.public')}
                    </Chip>
                  ) : (
                    <Chip size="sm" variant="flat" className="text-theme-subtle" startContent={<Lock className="w-3 h-3" />}>
                      {t('goals.visibility.private')}
                    </Chip>
                  )}
                </div>
              </ModalHeader>
              <ModalBody className="space-y-6">
                {/* Description */}
                {detailGoal.description && (
                  <div>
                    <h4 className="text-sm font-semibold text-theme-primary mb-1">{t('goals.detail.description')}</h4>
                    <p className="text-sm text-theme-muted whitespace-pre-wrap">{detailGoal.description}</p>
                  </div>
                )}

                {/* Progress Visualization */}
                <div>
                  <h4 className="text-sm font-semibold text-theme-primary mb-2">{t('goals.detail.progress')}</h4>
                  <div className="flex justify-between text-xs text-theme-subtle mb-1">
                    <span>{detailGoal.current_value} / {detailGoal.target_value}</span>
                    <span>{Math.min(100, Math.round(detailGoal.progress_percentage))}%</span>
                  </div>
                  <Progress
                    value={Math.min(100, detailGoal.progress_percentage)}
                    classNames={{
                      indicator: detailGoal.status === 'completed'
                        ? 'bg-emerald-500'
                        : 'bg-gradient-to-r from-indigo-500 to-purple-600',
                      track: 'bg-theme-hover',
                    }}
                    size="lg"
                    aria-label={t('goals.detail.progress_aria', { percent: Math.round(detailGoal.progress_percentage) })}
                  />
                </div>

                {/* Meta Info Grid */}
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                  {detailGoal.deadline && (
                    <div className="bg-theme-elevated rounded-xl p-3">
                      <div className="flex items-center gap-2 text-xs text-theme-subtle mb-1">
                        <Calendar className="w-3.5 h-3.5" aria-hidden="true" />
                        {t('goals.detail.deadline')}
                      </div>
                      <p className="text-sm text-theme-primary font-medium">
                        {new Date(detailGoal.deadline).toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' })}
                      </p>
                    </div>
                  )}
                  <div className="bg-theme-elevated rounded-xl p-3">
                    <div className="flex items-center gap-2 text-xs text-theme-subtle mb-1">
                      <Clock className="w-3.5 h-3.5" aria-hidden="true" />
                      {t('goals.detail.created')}
                    </div>
                    <p className="text-sm text-theme-primary font-medium">
                      {new Date(detailGoal.created_at).toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' })}
                    </p>
                  </div>
                  {detailGoal.buddy_name && (
                    <div className="bg-theme-elevated rounded-xl p-3">
                      <div className="flex items-center gap-2 text-xs text-theme-subtle mb-1">
                        <Users className="w-3.5 h-3.5" aria-hidden="true" />
                        {t('goals.detail.buddy')}
                      </div>
                      <div className="flex items-center gap-2">
                        <Avatar
                          name={detailGoal.buddy_name}
                          src={resolveAvatarUrl(detailGoal.buddy_avatar)}
                          size="sm"
                          className="w-6 h-6"
                        />
                        <p className="text-sm text-theme-primary font-medium">{detailGoal.buddy_name}</p>
                      </div>
                    </div>
                  )}
                  {(detailGoal.likes_count !== undefined || detailGoal.comments_count !== undefined) && (
                    <div className="bg-theme-elevated rounded-xl p-3">
                      <div className="flex items-center gap-2 text-xs text-theme-subtle mb-1">
                        <Sparkles className="w-3.5 h-3.5" aria-hidden="true" />
                        {t('goals.detail.social')}
                      </div>
                      <div className="flex items-center gap-3 text-sm text-theme-primary font-medium">
                        {detailGoal.likes_count !== undefined && (
                          <span className="flex items-center gap-1">
                            <Heart className="w-3.5 h-3.5 text-rose-400" aria-hidden="true" /> {detailGoal.likes_count}
                          </span>
                        )}
                        {detailGoal.comments_count !== undefined && (
                          <span className="flex items-center gap-1">
                            <MessageCircle className="w-3.5 h-3.5 text-blue-400" aria-hidden="true" /> {detailGoal.comments_count}
                          </span>
                        )}
                      </div>
                    </div>
                  )}
                </div>

                {/* Owner info (if viewing someone else's goal) */}
                {detailGoal.user_name && (
                  <div className="flex items-center gap-3 bg-theme-elevated rounded-xl p-3">
                    <Avatar
                      name={detailGoal.user_name}
                      src={resolveAvatarUrl(detailGoal.user_avatar)}
                      size="sm"
                    />
                    <div>
                      <p className="text-sm font-medium text-theme-primary">{detailGoal.user_name}</p>
                      <p className="text-xs text-theme-subtle">{t('goals.detail.goal_owner')}</p>
                    </div>
                  </div>
                )}

                {/* G5 - Full Progress History Timeline */}
                <div>
                  <h4 className="text-sm font-semibold text-theme-primary mb-3 flex items-center gap-2">
                    <History className="w-4 h-4 text-indigo-400" aria-hidden="true" />
                    {t('goals.detail.progress_timeline')}
                  </h4>
                  <GoalProgressHistory goalId={detailGoal.id} />
                </div>
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onDetailClose} className="text-theme-muted">{t('goals.modal.close')}</Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}

/* ───────────────────────── Goal Card ───────────────────────── */

interface GoalCardProps {
  goal: Goal;
  isOwner: boolean;
  currentUserId: number | null;
  isCelebrating: boolean;
  isDiscoverTab: boolean;
  isBuddyingTab?: boolean;
  onProgressUpdate: (goalId: number, increment: number) => void;
  onEdit: (goal: Goal) => void;
  onDelete: (goal: Goal) => void;
  onComplete: (goal: Goal) => void;
  onBecomeBuddy: (goal: Goal) => void;
  onOpenDetail: (goal: Goal) => void;
  onCheckin: (goal: Goal) => void;
}

function GoalCard({
  goal,
  isOwner,
  currentUserId,
  isCelebrating,
  isDiscoverTab,
  isBuddyingTab = false,
  onProgressUpdate,
  onEdit,
  onDelete,
  onComplete,
  onBecomeBuddy,
  onOpenDetail,
  onCheckin,
}: GoalCardProps) {
  const { t } = useTranslation('gamification');
  const { tenantPath } = useTenant();
  const isCompleted = goal.status === 'completed' || goal.progress_percentage >= 100;
  const deadlineDate = goal.deadline ? new Date(goal.deadline) : null;
  const isOverdue = deadlineDate && deadlineDate < new Date() && !isCompleted;
  const canComplete = isOwner && !isCompleted && goal.progress_percentage >= 100;
  const isBuddy = goal.is_buddy || (goal.buddy_id != null && goal.buddy_id === currentUserId);
  const canBecomeBuddy = isDiscoverTab && !isOwner && goal.is_public && !goal.buddy_id && !isBuddy && currentUserId !== null;

  return (
    <GlassCard className={`p-5 relative overflow-hidden ${isCompleted ? 'border-l-4 border-emerald-500' : ''} ${isOverdue ? 'border-l-4 border-red-500' : ''}`}>
      {/* Confetti celebration overlay */}
      <ConfettiCelebration show={isCelebrating} />

      <div className="flex items-start justify-between gap-4">
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2 mb-1 flex-wrap">
            <Button
              variant="light"
              className="font-semibold text-theme-primary text-lg hover:text-indigo-400 text-left p-0 h-auto min-w-0"
              onPress={() => onOpenDetail(goal)}
            >
              {goal.title}
            </Button>
            {isCompleted && (
              <Chip size="sm" color="success" variant="flat" startContent={<CheckCircle className="w-3 h-3" />}>
                {t('goals.status.completed')}
              </Chip>
            )}
            {goal.is_public ? (
              <Chip size="sm" variant="flat" className="text-theme-subtle" startContent={<Globe className="w-3 h-3" />}>
                {t('goals.visibility.public')}
              </Chip>
            ) : (
              <Chip size="sm" variant="flat" className="text-theme-subtle" startContent={<Lock className="w-3 h-3" />}>
                {t('goals.visibility.private')}
              </Chip>
            )}
            {isBuddy && !isBuddyingTab && (
              <Chip size="sm" color="secondary" variant="flat" startContent={<Users className="w-3 h-3" />}>
                {t('goals.youre_a_buddy')}
              </Chip>
            )}
          </div>

          {goal.description && (
            <p className="text-sm text-theme-muted mb-3 line-clamp-2">{goal.description}</p>
          )}

          {/* Progress Bar */}
          <div className="mb-3">
            <div className="flex justify-between text-xs text-theme-subtle mb-1">
              <span>{goal.current_value} / {goal.target_value}</span>
              <span>{Math.min(100, Math.round(goal.progress_percentage))}%</span>
            </div>
            <Progress
              value={Math.min(100, goal.progress_percentage)}
              classNames={{
                indicator: isCompleted
                  ? 'bg-emerald-500'
                  : 'bg-gradient-to-r from-indigo-500 to-purple-600',
                track: 'bg-theme-hover',
              }}
              size="md"
              aria-label={t('goals.detail.progress_aria', { percent: Math.round(goal.progress_percentage) })}
            />
          </div>

          {/* Meta info */}
          <div className="flex flex-wrap items-center gap-2 sm:gap-4 text-xs text-theme-subtle">
            {deadlineDate && (
              <span className={`flex items-center gap-1 ${isOverdue ? 'text-red-400' : ''}`}>
                <Calendar className="w-3 h-3" aria-hidden="true" />
                {isOverdue ? t('goals.overdue') : t('goals.due')}{deadlineDate.toLocaleDateString()}
              </span>
            )}
            {goal.buddy_name && !isBuddyingTab && (
              <span className="flex items-center gap-1">
                <Users className="w-3 h-3" aria-hidden="true" />
                {t('goals.buddy_label')}: {goal.buddy_name}
              </span>
            )}
            {(!isOwner || isBuddyingTab) && goal.user_name && (
              <Link
                to={tenantPath(`/profile/${goal.user_id}`)}
                className="flex items-center gap-1.5 hover:text-theme-primary transition-colors"
              >
                <Avatar
                  name={goal.user_name}
                  src={resolveAvatarUrl(goal.user_avatar)}
                  size="sm"
                  className="w-4 h-4"
                />
                {goal.user_name}
              </Link>
            )}
          </div>
        </div>

        {/* Actions Column */}
        <div className="flex flex-col gap-2 flex-shrink-0 items-end">
          {/* Owner 3-dot menu */}
          {isOwner && (
            <Dropdown>
              <DropdownTrigger>
                <Button
                  isIconOnly
                  size="sm"
                  variant="light"
                  className="text-theme-muted"
                  aria-label={t('goals.actions_aria')}
                >
                  <MoreVertical className="w-4 h-4" />
                </Button>
              </DropdownTrigger>
              <DropdownMenu
                aria-label={t('goals.actions_aria')}
                onAction={(key) => {
                  if (key === 'edit') onEdit(goal);
                  if (key === 'delete') onDelete(goal);
                }}
              >
                <DropdownItem
                  key="edit"
                  startContent={<Edit3 className="w-4 h-4" aria-hidden="true" />}
                >
                  {t('goals.action_edit')}
                </DropdownItem>
                <DropdownItem
                  key="delete"
                  className="text-danger"
                  color="danger"
                  startContent={<Trash2 className="w-4 h-4" aria-hidden="true" />}
                >
                  {t('goals.action_delete')}
                </DropdownItem>
              </DropdownMenu>
            </Dropdown>
          )}

          {/* G4 - Reminder Toggle */}
          {isOwner && !isCompleted && (
            <GoalReminderToggle goalId={goal.id} />
          )}

          {/* G3 - Check-in button */}
          {isOwner && !isCompleted && (
            <Button
              size="sm"
              variant="flat"
              className="bg-indigo-500/10 text-indigo-400"
              startContent={<ClipboardCheck className="w-4 h-4" aria-hidden="true" />}
              onPress={() => onCheckin(goal)}
            >
              Check In
            </Button>
          )}

          {/* Quick +1 for owner */}
          {isOwner && !isCompleted && (
            <Button
              size="sm"
              variant="flat"
              className="bg-emerald-500/10 text-emerald-400"
              onPress={() => onProgressUpdate(goal.id, 1)}
            >
              <TrendingUp className="w-4 h-4" aria-hidden="true" />
              +1
            </Button>
          )}

          {/* Mark Complete button when progress >= 100% */}
          {canComplete && (
            <Button
              size="sm"
              className="bg-gradient-to-r from-emerald-500 to-green-600 text-white"
              startContent={<Award className="w-4 h-4" aria-hidden="true" />}
              onPress={() => onComplete(goal)}
            >
              {t('goals.mark_complete')}
            </Button>
          )}

          {/* Become Buddy for discover tab */}
          {canBecomeBuddy && (
            <Button
              size="sm"
              variant="flat"
              className="bg-purple-500/10 text-purple-400"
              startContent={<UserPlus className="w-4 h-4" aria-hidden="true" />}
              onPress={() => onBecomeBuddy(goal)}
            >
              {t('goals.become_buddy')}
            </Button>
          )}
        </div>
      </div>
    </GlassCard>
  );
}

export default GoalsPage;
