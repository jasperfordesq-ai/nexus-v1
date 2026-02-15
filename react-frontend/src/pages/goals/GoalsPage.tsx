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

import { useState, useEffect, useCallback } from 'react';
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
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useAuth, useToast } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl, formatRelativeTime } from '@/lib/helpers';

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

type GoalTab = 'my' | 'discover';

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
  usePageTitle('Goals');
  const { isAuthenticated, user } = useAuth();
  const toast = useToast();
  const [goals, setGoals] = useState<Goal[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [tab, setTab] = useState<GoalTab>('my');
  const [hasMore, setHasMore] = useState(false);
  const [cursor, setCursor] = useState<string | undefined>();

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
  const [detailHistory, setDetailHistory] = useState<ProgressEntry[]>([]);
  const [isLoadingDetail, setIsLoadingDetail] = useState(false);

  // Completion celebration
  const [celebratingId, setCelebratingId] = useState<number | null>(null);

  const loadGoals = useCallback(async (append = false) => {
    try {
      if (!append) {
        setIsLoading(true);
        setError(null);
      }

      const params = new URLSearchParams();
      params.set('per_page', '20');
      if (append && cursor) params.set('cursor', cursor);

      const endpoint = tab === 'discover'
        ? `/v2/goals/discover?${params}`
        : `/v2/goals?${params}&status=all`;

      const response = await api.get<{ data: Goal[]; meta: { cursor: string | null; has_more: boolean } }>(endpoint);

      if (response.success && response.data) {
        const responseData = response.data as unknown as { data?: Goal[]; meta?: { cursor: string | null; has_more: boolean } };
        const items = responseData.data ?? (response.data as unknown as Goal[]);
        const resMeta = responseData.meta;

        if (append) {
          setGoals((prev) => [...prev, ...(Array.isArray(items) ? items : [])]);
        } else {
          setGoals(Array.isArray(items) ? items : []);
        }
        setHasMore(resMeta?.has_more ?? false);
        setCursor(resMeta?.cursor ?? undefined);
      } else {
        if (!append) setError('Failed to load goals.');
      }
    } catch (err) {
      logError('Failed to load goals', err);
      if (!append) setError('Failed to load goals. Please try again.');
    } finally {
      setIsLoading(false);
    }
  }, [tab, cursor]);

  useEffect(() => {
    setCursor(undefined);
    loadGoals();
  }, [tab]); // eslint-disable-line react-hooks/exhaustive-deps

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
        toast.success('Goal created!');
        loadGoals();
      }
    } catch (err) {
      logError('Failed to create goal', err);
      toast.error('Failed to create goal');
    } finally {
      setIsCreating(false);
    }
  };

  const handleProgressUpdate = async (goalId: number, increment: number) => {
    try {
      const response = await api.post(`/v2/goals/${goalId}/progress`, { increment });
      if (response.success) {
        toast.success('Progress updated!');
        loadGoals();
      }
    } catch (err) {
      logError('Failed to update progress', err);
      toast.error('Failed to update progress');
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
        toast.success('Goal updated!');
        loadGoals();
      }
    } catch (err) {
      logError('Failed to update goal', err);
      toast.error('Failed to update goal');
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
        toast.success('Goal deleted');
      }
    } catch (err) {
      logError('Failed to delete goal', err);
      toast.error('Failed to delete goal');
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
        toast.success('Goal completed! Congratulations!');
        // Clear celebration after animation
        setTimeout(() => setCelebratingId(null), 2000);
      }
    } catch (err) {
      logError('Failed to complete goal', err);
      toast.error('Failed to mark goal as complete');
    }
  };

  // ── Buddy ──
  const handleBecomeBuddy = async (goal: Goal) => {
    try {
      const response = await api.post(`/v2/goals/${goal.id}/buddy`, {});

      if (response.success) {
        toast.success('You are now a buddy!');
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
      toast.error('Failed to become buddy');
    }
  };

  // ── Detail ──
  const openDetail = async (goal: Goal) => {
    setDetailGoal(goal);
    setDetailHistory([]);
    onDetailOpen();

    try {
      setIsLoadingDetail(true);
      const response = await api.get<{ data: { progress_history?: ProgressEntry[] } }>(`/v2/goals/${goal.id}`);
      if (response.success && response.data) {
        const data = response.data as unknown as { data?: Goal & { progress_history?: ProgressEntry[] } };
        const fullGoal = data.data ?? (response.data as unknown as Goal & { progress_history?: ProgressEntry[] });
        if (fullGoal.progress_history) {
          setDetailHistory(fullGoal.progress_history);
        }
        // Update the detail goal with any fresh data
        if (fullGoal.id) {
          setDetailGoal(fullGoal);
        }
      }
    } catch (err) {
      logError('Failed to load goal details', err);
    } finally {
      setIsLoadingDetail(false);
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
            Goals
          </h1>
          <p className="text-theme-muted mt-1">Set goals and track your progress</p>
        </div>
        {isAuthenticated && (
          <Button
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
            startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
            onPress={onOpen}
          >
            New Goal
          </Button>
        )}
      </div>

      {/* Tabs */}
      <div className="flex gap-2">
        <Button
          variant={tab === 'my' ? 'solid' : 'flat'}
          className={tab === 'my' ? 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white' : 'bg-theme-elevated text-theme-muted'}
          onPress={() => setTab('my')}
          startContent={<Target className="w-4 h-4" aria-hidden="true" />}
        >
          My Goals
        </Button>
        <Button
          variant={tab === 'discover' ? 'solid' : 'flat'}
          className={tab === 'discover' ? 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white' : 'bg-theme-elevated text-theme-muted'}
          onPress={() => setTab('discover')}
          startContent={<Globe className="w-4 h-4" aria-hidden="true" />}
        >
          Discover
        </Button>
      </div>

      {/* Error State */}
      {error && !isLoading && (
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary mb-2">Unable to Load Goals</h2>
          <p className="text-theme-muted mb-4">{error}</p>
          <Button
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
            startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
            onPress={() => loadGoals()}
          >
            Try Again
          </Button>
        </GlassCard>
      )}

      {/* Goals List */}
      {!error && (
        <>
          {isLoading ? (
            <div className="space-y-4">
              {[1, 2, 3].map((i) => (
                <GlassCard key={i} className="p-5 animate-pulse">
                  <div className="h-5 bg-theme-hover rounded w-1/3 mb-3" />
                  <div className="h-3 bg-theme-hover rounded w-full mb-3" />
                  <div className="h-2 bg-theme-hover rounded w-full mb-2" />
                  <div className="h-3 bg-theme-hover rounded w-1/4" />
                </GlassCard>
              ))}
            </div>
          ) : goals.length === 0 ? (
            <EmptyState
              icon={<Target className="w-12 h-12" aria-hidden="true" />}
              title={tab === 'my' ? 'No goals yet' : 'No goals to discover'}
              description={tab === 'my' ? 'Create your first goal to start tracking progress' : 'No public goals available right now'}
              action={
                tab === 'my' && isAuthenticated && (
                  <Button
                    className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                    onPress={onOpen}
                  >
                    Create Goal
                  </Button>
                )
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
                      isDiscoverTab={tab === 'discover'}
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
                    Load More
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
          <ModalHeader className="text-theme-primary">Create New Goal</ModalHeader>
          <ModalBody className="space-y-4">
            <Input
              label="Goal Title"
              placeholder="e.g., Give 10 hours this month"
              value={newGoal.title}
              onChange={(e) => setNewGoal((prev) => ({ ...prev, title: e.target.value }))}
              isRequired
              classNames={inputClasses}
            />
            <Textarea
              label="Description"
              placeholder="Describe your goal..."
              value={newGoal.description}
              onChange={(e) => setNewGoal((prev) => ({ ...prev, description: e.target.value }))}
              classNames={inputClasses}
            />
            <Input
              type="number"
              label="Target Value"
              placeholder="100"
              value={String(newGoal.target_value)}
              onChange={(e) => setNewGoal((prev) => ({ ...prev, target_value: parseInt(e.target.value) || 0 }))}
              classNames={inputClasses}
            />
            <Input
              type="date"
              label="Deadline (optional)"
              value={newGoal.deadline}
              onChange={(e) => setNewGoal((prev) => ({ ...prev, deadline: e.target.value }))}
              classNames={inputClasses}
            />
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-theme-primary">Make Public</p>
                <p className="text-xs text-theme-muted">Others can see and support your goal</p>
              </div>
              <Switch
                isSelected={newGoal.is_public}
                onValueChange={(val) => setNewGoal((prev) => ({ ...prev, is_public: val }))}
              />
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onClose} className="text-theme-muted">Cancel</Button>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              onPress={handleCreate}
              isLoading={isCreating}
              isDisabled={!newGoal.title.trim()}
            >
              Create Goal
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* ─── Edit Goal Modal ─── */}
      <Modal isOpen={isEditOpen} onClose={onEditClose} size="lg" classNames={modalClasses}>
        <ModalContent>
          <ModalHeader className="text-theme-primary">Edit Goal</ModalHeader>
          <ModalBody className="space-y-4">
            <Input
              label="Goal Title"
              placeholder="e.g., Give 10 hours this month"
              value={editForm.title}
              onChange={(e) => setEditForm((prev) => ({ ...prev, title: e.target.value }))}
              isRequired
              classNames={inputClasses}
            />
            <Textarea
              label="Description"
              placeholder="Describe your goal..."
              value={editForm.description}
              onChange={(e) => setEditForm((prev) => ({ ...prev, description: e.target.value }))}
              classNames={inputClasses}
            />
            <Input
              type="number"
              label="Target Value"
              placeholder="100"
              value={String(editForm.target_value)}
              onChange={(e) => setEditForm((prev) => ({ ...prev, target_value: parseInt(e.target.value) || 0 }))}
              classNames={inputClasses}
            />
            <Input
              type="date"
              label="Deadline (optional)"
              value={editForm.deadline}
              onChange={(e) => setEditForm((prev) => ({ ...prev, deadline: e.target.value }))}
              classNames={inputClasses}
            />
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-theme-primary">Make Public</p>
                <p className="text-xs text-theme-muted">Others can see and support your goal</p>
              </div>
              <Switch
                isSelected={editForm.is_public}
                onValueChange={(val) => setEditForm((prev) => ({ ...prev, is_public: val }))}
              />
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onEditClose} className="text-theme-muted">Cancel</Button>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              onPress={handleSaveEdit}
              isLoading={isSavingEdit}
              isDisabled={!editForm.title.trim()}
            >
              Save Changes
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* ─── Delete Confirmation Modal ─── */}
      <Modal isOpen={isDeleteOpen} onClose={onDeleteClose} size="sm" classNames={modalClasses}>
        <ModalContent>
          <ModalHeader className="text-theme-primary">Delete Goal</ModalHeader>
          <ModalBody>
            <div className="text-center py-2">
              <Trash2 className="w-12 h-12 text-red-400 mx-auto mb-3" aria-hidden="true" />
              <p className="text-theme-primary font-medium mb-1">
                Are you sure you want to delete &ldquo;{deleteGoal?.title}&rdquo;?
              </p>
              <p className="text-sm text-theme-muted">This cannot be undone.</p>
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onDeleteClose} className="text-theme-muted">Cancel</Button>
            <Button
              color="danger"
              onPress={handleDelete}
              isLoading={isDeleting}
            >
              Delete Goal
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

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
                      Completed
                    </Chip>
                  ) : (
                    <Chip size="sm" color="primary" variant="flat">Active</Chip>
                  )}
                  {detailGoal.is_public ? (
                    <Chip size="sm" variant="flat" className="text-theme-subtle" startContent={<Globe className="w-3 h-3" />}>
                      Public
                    </Chip>
                  ) : (
                    <Chip size="sm" variant="flat" className="text-theme-subtle" startContent={<Lock className="w-3 h-3" />}>
                      Private
                    </Chip>
                  )}
                </div>
              </ModalHeader>
              <ModalBody className="space-y-6">
                {/* Description */}
                {detailGoal.description && (
                  <div>
                    <h4 className="text-sm font-semibold text-theme-primary mb-1">Description</h4>
                    <p className="text-sm text-theme-muted whitespace-pre-wrap">{detailGoal.description}</p>
                  </div>
                )}

                {/* Progress Visualization */}
                <div>
                  <h4 className="text-sm font-semibold text-theme-primary mb-2">Progress</h4>
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
                    aria-label={`Goal progress: ${Math.round(detailGoal.progress_percentage)}%`}
                  />
                </div>

                {/* Meta Info Grid */}
                <div className="grid grid-cols-2 gap-4">
                  {detailGoal.deadline && (
                    <div className="bg-theme-elevated rounded-xl p-3">
                      <div className="flex items-center gap-2 text-xs text-theme-subtle mb-1">
                        <Calendar className="w-3.5 h-3.5" aria-hidden="true" />
                        Deadline
                      </div>
                      <p className="text-sm text-theme-primary font-medium">
                        {new Date(detailGoal.deadline).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}
                      </p>
                    </div>
                  )}
                  <div className="bg-theme-elevated rounded-xl p-3">
                    <div className="flex items-center gap-2 text-xs text-theme-subtle mb-1">
                      <Clock className="w-3.5 h-3.5" aria-hidden="true" />
                      Created
                    </div>
                    <p className="text-sm text-theme-primary font-medium">
                      {new Date(detailGoal.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}
                    </p>
                  </div>
                  {detailGoal.buddy_name && (
                    <div className="bg-theme-elevated rounded-xl p-3">
                      <div className="flex items-center gap-2 text-xs text-theme-subtle mb-1">
                        <Users className="w-3.5 h-3.5" aria-hidden="true" />
                        Buddy
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
                        Social
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
                      <p className="text-xs text-theme-subtle">Goal Owner</p>
                    </div>
                  </div>
                )}

                {/* Progress Timeline */}
                <div>
                  <h4 className="text-sm font-semibold text-theme-primary mb-3">Progress Timeline</h4>
                  {isLoadingDetail ? (
                    <div className="space-y-2">
                      {[1, 2, 3].map((i) => (
                        <div key={i} className="flex gap-3 animate-pulse">
                          <div className="w-2 h-2 rounded-full bg-theme-hover mt-1.5 flex-shrink-0" />
                          <div className="flex-1">
                            <div className="h-3 bg-theme-hover rounded w-1/3 mb-1" />
                            <div className="h-3 bg-theme-hover rounded w-1/5" />
                          </div>
                        </div>
                      ))}
                    </div>
                  ) : detailHistory.length > 0 ? (
                    <div className="space-y-3 border-l-2 border-theme-default ml-1 pl-4">
                      {detailHistory.map((entry) => (
                        <div key={entry.id} className="relative">
                          <div className="absolute -left-[21px] top-1.5 w-2.5 h-2.5 rounded-full bg-gradient-to-r from-indigo-500 to-purple-600 border-2 border-white dark:border-gray-900" />
                          <div>
                            <div className="flex items-center gap-2">
                              <span className="text-sm font-medium text-theme-primary">
                                +{entry.increment}
                              </span>
                              {entry.note && (
                                <span className="text-xs text-theme-muted">{entry.note}</span>
                              )}
                            </div>
                            <span className="text-xs text-theme-subtle">{formatRelativeTime(entry.created_at)}</span>
                          </div>
                        </div>
                      ))}
                    </div>
                  ) : (
                    <p className="text-sm text-theme-subtle text-center py-4">No progress updates recorded yet.</p>
                  )}
                </div>
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onDetailClose} className="text-theme-muted">Close</Button>
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
  onProgressUpdate: (goalId: number, increment: number) => void;
  onEdit: (goal: Goal) => void;
  onDelete: (goal: Goal) => void;
  onComplete: (goal: Goal) => void;
  onBecomeBuddy: (goal: Goal) => void;
  onOpenDetail: (goal: Goal) => void;
}

function GoalCard({
  goal,
  isOwner,
  currentUserId,
  isCelebrating,
  isDiscoverTab,
  onProgressUpdate,
  onEdit,
  onDelete,
  onComplete,
  onBecomeBuddy,
  onOpenDetail,
}: GoalCardProps) {
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
                Completed
              </Chip>
            )}
            {goal.is_public ? (
              <Chip size="sm" variant="flat" className="text-theme-subtle" startContent={<Globe className="w-3 h-3" />}>
                Public
              </Chip>
            ) : (
              <Chip size="sm" variant="flat" className="text-theme-subtle" startContent={<Lock className="w-3 h-3" />}>
                Private
              </Chip>
            )}
            {isBuddy && (
              <Chip size="sm" color="secondary" variant="flat" startContent={<Users className="w-3 h-3" />}>
                You&apos;re a buddy!
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
              aria-label={`Goal progress: ${Math.round(goal.progress_percentage)}%`}
            />
          </div>

          {/* Meta info */}
          <div className="flex flex-wrap items-center gap-4 text-xs text-theme-subtle">
            {deadlineDate && (
              <span className={`flex items-center gap-1 ${isOverdue ? 'text-red-400' : ''}`}>
                <Calendar className="w-3 h-3" aria-hidden="true" />
                {isOverdue ? 'Overdue: ' : 'Due: '}{deadlineDate.toLocaleDateString()}
              </span>
            )}
            {goal.buddy_name && (
              <span className="flex items-center gap-1">
                <Users className="w-3 h-3" aria-hidden="true" />
                Buddy: {goal.buddy_name}
              </span>
            )}
            {!isOwner && (
              <Link
                to={`/profile/${goal.user_id}`}
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
                  aria-label="Goal actions"
                >
                  <MoreVertical className="w-4 h-4" />
                </Button>
              </DropdownTrigger>
              <DropdownMenu
                aria-label="Goal actions"
                onAction={(key) => {
                  if (key === 'edit') onEdit(goal);
                  if (key === 'delete') onDelete(goal);
                }}
              >
                <DropdownItem
                  key="edit"
                  startContent={<Edit3 className="w-4 h-4" aria-hidden="true" />}
                >
                  Edit
                </DropdownItem>
                <DropdownItem
                  key="delete"
                  className="text-danger"
                  color="danger"
                  startContent={<Trash2 className="w-4 h-4" aria-hidden="true" />}
                >
                  Delete
                </DropdownItem>
              </DropdownMenu>
            </Dropdown>
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
              Mark Complete
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
              Become Buddy
            </Button>
          )}
        </div>
      </div>
    </GlassCard>
  );
}

export default GoalsPage;
