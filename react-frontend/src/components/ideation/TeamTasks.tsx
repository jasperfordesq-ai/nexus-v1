// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Team Tasks Component (I5)
 *
 * Task management within a group/team workspace.
 * - Task list with status (todo/in_progress/done), assignee, due date
 * - Create task modal
 * - Update task status inline
 * - Task stats summary
 */

import { useState, useEffect, useCallback } from 'react';
import {
  Button,
  Input,
  Textarea,
  Select,
  SelectItem,
  Chip,
  Spinner,
  Avatar,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  useDisclosure,
} from '@heroui/react';
import {
  Plus,
  CheckSquare,
  Clock,
  AlertCircle,
  Trash2,
  Calendar,
  BarChart3,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useAuth, useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl } from '@/lib/helpers';

/* ───────────────────────── Types ───────────────────────── */

interface Task {
  id: number;
  group_id: number;
  title: string;
  description: string | null;
  status: 'todo' | 'in_progress' | 'done';
  priority: 'low' | 'medium' | 'high' | 'urgent';
  assigned_to: number | null;
  due_date: string | null;
  created_at: string;
  assignee?: {
    id: number;
    name: string;
    avatar_url: string | null;
  } | null;
}

interface TaskStats {
  total: number;
  todo: number;
  in_progress: number;
  done: number;
  overdue: number;
}

interface GroupMember {
  id: number;
  name: string;
  avatar_url?: string | null;
}

interface TeamTasksProps {
  groupId: number;
  isGroupAdmin: boolean;
  members?: GroupMember[];
}

const PRIORITY_COLORS: Record<string, 'default' | 'warning' | 'danger' | 'secondary'> = {
  low: 'default',
  medium: 'secondary',
  high: 'warning',
  urgent: 'danger',
};

/* ───────────────────────── Main Component ───────────────────────── */

export function TeamTasks({ groupId, isGroupAdmin, members = [] }: TeamTasksProps) {
  const { t } = useTranslation('ideation');
  const { user } = useAuth();
  const toast = useToast();

  const [tasks, setTasks] = useState<Task[]>([]);
  const [stats, setStats] = useState<TaskStats | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [statusFilter, setStatusFilter] = useState<string>('all');

  // Create modal
  const { isOpen: isCreateOpen, onOpen: onCreateOpen, onClose: onCreateClose } = useDisclosure();
  const [taskForm, setTaskForm] = useState({
    title: '',
    description: '',
    status: 'todo',
    priority: 'medium',
    assigned_to: '',
    due_date: '',
  });
  const [isCreating, setIsCreating] = useState(false);

  const isAdmin = isGroupAdmin || (user?.role && ['admin', 'tenant_admin', 'tenant_super_admin', 'super_admin'].includes(user.role));

  const fetchTasks = useCallback(async () => {
    try {
      setIsLoading(true);
      const params = new URLSearchParams();
      if (statusFilter !== 'all') {
        params.set('status', statusFilter);
      }
      const response = await api.get<Task[]>(`/v2/groups/${groupId}/tasks?${params}`);
      if (response.success && response.data) {
        setTasks(Array.isArray(response.data) ? response.data : []);
      }
    } catch (err) {
      logError('Failed to fetch tasks', err);
    } finally {
      setIsLoading(false);
    }
  }, [groupId, statusFilter]);

  const fetchStats = useCallback(async () => {
    try {
      const response = await api.get<TaskStats>(`/v2/groups/${groupId}/tasks/stats`);
      if (response.success && response.data) {
        setStats(response.data);
      }
    } catch (err) {
      logError('Failed to fetch task stats', err);
    }
  }, [groupId]);

  useEffect(() => {
    fetchTasks();
    fetchStats();
  }, [fetchTasks, fetchStats]);

  const handleCreateTask = async () => {
    if (!taskForm.title.trim()) return;

    setIsCreating(true);
    try {
      await api.post(`/v2/groups/${groupId}/tasks`, {
        title: taskForm.title.trim(),
        description: taskForm.description.trim() || null,
        status: taskForm.status,
        priority: taskForm.priority,
        assigned_to: taskForm.assigned_to ? parseInt(taskForm.assigned_to, 10) : null,
        due_date: taskForm.due_date || null,
      });
      toast.success(t('toast.task_created'));
      setTaskForm({
        title: '',
        description: '',
        status: 'todo',
        priority: 'medium',
        assigned_to: '',
        due_date: '',
      });
      onCreateClose();
      fetchTasks();
      fetchStats();
    } catch (err) {
      logError('Failed to create task', err);
      toast.error(t('toast.error_generic'));
    } finally {
      setIsCreating(false);
    }
  };

  const handleUpdateStatus = async (taskId: number, newStatus: string) => {
    try {
      await api.put(`/v2/team-tasks/${taskId}`, { status: newStatus });
      toast.success(t('toast.task_updated'));
      fetchTasks();
      fetchStats();
    } catch (err) {
      logError('Failed to update task', err);
      toast.error(t('toast.error_generic'));
    }
  };

  const handleDeleteTask = async (taskId: number) => {
    try {
      await api.delete(`/v2/team-tasks/${taskId}`);
      toast.success(t('toast.task_deleted'));
      setTasks(prev => prev.filter(t => t.id !== taskId));
      fetchStats();
    } catch (err) {
      logError('Failed to delete task', err);
      toast.error(t('toast.error_generic'));
    }
  };

  const isOverdue = (dueDate: string | null) => {
    if (!dueDate) return false;
    return new Date(dueDate) < new Date();
  };

  const formatDate = (dateStr: string | null) => {
    if (!dateStr) return null;
    try {
      return new Date(dateStr).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
      });
    } catch {
      return dateStr;
    }
  };

  return (
    <div className="space-y-4">
      {/* Stats Bar */}
      {stats && (
        <div className="flex flex-wrap gap-3 mb-4">
          <GlassCard className="px-3 py-2 flex items-center gap-2">
            <BarChart3 className="w-4 h-4 text-[var(--color-text-tertiary)]" />
            <span className="text-sm text-[var(--color-text)]">
              {stats.total} {t('tasks.title').toLowerCase()}
            </span>
          </GlassCard>
          <GlassCard className="px-3 py-2 flex items-center gap-2">
            <CheckSquare className="w-4 h-4 text-green-500" />
            <span className="text-sm text-green-600 dark:text-green-400">{stats.done}</span>
          </GlassCard>
          <GlassCard className="px-3 py-2 flex items-center gap-2">
            <Clock className="w-4 h-4 text-amber-500" />
            <span className="text-sm text-amber-600 dark:text-amber-400">{stats.in_progress}</span>
          </GlassCard>
          {stats.overdue > 0 && (
            <GlassCard className="px-3 py-2 flex items-center gap-2">
              <AlertCircle className="w-4 h-4 text-red-500" />
              <span className="text-sm text-red-600 dark:text-red-400">
                {stats.overdue} {t('tasks.overdue')}
              </span>
            </GlassCard>
          )}
        </div>
      )}

      {/* Filter + Create */}
      <div className="flex items-center justify-between">
        <div className="flex gap-2">
          {['all', 'todo', 'in_progress', 'done'].map((status) => (
            <Button
              key={status}
              size="sm"
              variant={statusFilter === status ? 'solid' : 'flat'}
              color={statusFilter === status ? 'primary' : 'default'}
              onPress={() => setStatusFilter(status)}
            >
              {status === 'all' ? 'All' : t(`tasks.status_${status}`)}
            </Button>
          ))}
        </div>
        <Button
          color="primary"
          size="sm"
          startContent={<Plus className="w-4 h-4" />}
          onPress={onCreateOpen}
        >
          {t('tasks.create')}
        </Button>
      </div>

      {/* Loading */}
      {isLoading && (
        <div className="flex justify-center py-8">
          <Spinner size="md" />
        </div>
      )}

      {/* Empty */}
      {!isLoading && tasks.length === 0 && (
        <EmptyState
          icon={<CheckSquare className="w-10 h-10 text-theme-subtle" />}
          title={t('tasks.empty_title')}
          description={t('tasks.empty_description')}
        />
      )}

      {/* Task List */}
      {!isLoading && tasks.length > 0 && (
        <div className="space-y-2">
          {tasks.map((task) => (
            <GlassCard key={task.id} className="p-3">
              <div className="flex items-start gap-3">
                {/* Status Toggle */}
                <Button
                  isIconOnly
                  size="sm"
                  variant="flat"
                  onPress={() => {
                    const nextStatus = task.status === 'todo' ? 'in_progress' : task.status === 'in_progress' ? 'done' : 'todo';
                    handleUpdateStatus(task.id, nextStatus);
                  }}
                  className={`mt-0.5 w-5 h-5 min-w-0 rounded border-2 flex items-center justify-center shrink-0 transition-colors p-0 ${
                    task.status === 'done'
                      ? 'bg-green-500 border-green-500 text-white'
                      : task.status === 'in_progress'
                        ? 'border-amber-400 bg-amber-50 dark:bg-amber-950/20'
                        : 'border-[var(--color-border)]'
                  }`}
                  aria-label={t(`tasks.status_${task.status}`)}
                >
                  {task.status === 'done' && (
                    <CheckSquare className="w-3 h-3" />
                  )}
                  {task.status === 'in_progress' && (
                    <Clock className="w-3 h-3 text-amber-500" />
                  )}
                </Button>

                {/* Content */}
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2 mb-0.5">
                    <span className={`text-sm font-medium ${
                      task.status === 'done'
                        ? 'line-through text-[var(--color-text-tertiary)]'
                        : 'text-[var(--color-text)]'
                    }`}>
                      {task.title}
                    </span>
                    <Chip size="sm" variant="flat" color={PRIORITY_COLORS[task.priority] ?? 'default'} className="text-[10px]">
                      {t(`tasks.priority_${task.priority}`)}
                    </Chip>
                  </div>

                  {task.description && (
                    <p className="text-xs text-[var(--color-text-tertiary)] line-clamp-1 mb-1">
                      {task.description}
                    </p>
                  )}

                  <div className="flex items-center gap-3 text-xs text-[var(--color-text-tertiary)]">
                    {task.assignee && (
                      <span className="flex items-center gap-1">
                        <Avatar
                          src={resolveAvatarUrl(task.assignee.avatar_url)}
                          size="sm"
                          className="w-4 h-4"
                          name={task.assignee.name}
                        />
                        {task.assignee.name}
                      </span>
                    )}
                    {task.due_date && (
                      <span className={`flex items-center gap-1 ${
                        isOverdue(task.due_date) && task.status !== 'done'
                          ? 'text-red-500 font-medium'
                          : ''
                      }`}>
                        <Calendar className="w-3 h-3" />
                        {formatDate(task.due_date)}
                        {isOverdue(task.due_date) && task.status !== 'done' && (
                          <AlertCircle className="w-3 h-3" />
                        )}
                      </span>
                    )}
                  </div>
                </div>

                {/* Delete */}
                {isAdmin && (
                  <Button
                    isIconOnly
                    variant="light"
                    size="sm"
                    onPress={() => handleDeleteTask(task.id)}
                    aria-label={t('toast.task_deleted')}
                  >
                    <Trash2 className="w-3.5 h-3.5 text-[var(--color-text-tertiary)]" />
                  </Button>
                )}
              </div>
            </GlassCard>
          ))}
        </div>
      )}

      {/* Create Task Modal */}
      <Modal isOpen={isCreateOpen} onClose={onCreateClose} size="lg">
        <ModalContent>
          <ModalHeader>{t('tasks.create')}</ModalHeader>
          <ModalBody>
            <Input
              label={t('form.title_label')}
              placeholder={t('form.title_placeholder')}
              value={taskForm.title}
              onValueChange={(val) => setTaskForm(prev => ({ ...prev, title: val }))}
              variant="bordered"
              isRequired
            />
            <Textarea
              label={t('form.description_label')}
              placeholder={t('form.description_placeholder')}
              value={taskForm.description}
              onValueChange={(val) => setTaskForm(prev => ({ ...prev, description: val }))}
              variant="bordered"
              minRows={2}
            />
            <div className="grid grid-cols-2 gap-3">
              <Select
                label={t('tasks.status_label', { defaultValue: 'Status' })}
                selectedKeys={[taskForm.status]}
                onSelectionChange={(keys) => {
                  const selected = Array.from(keys)[0];
                  if (selected) setTaskForm(prev => ({ ...prev, status: String(selected) }));
                }}
                variant="bordered"
              >
                <SelectItem key="todo">{t('tasks.status_todo')}</SelectItem>
                <SelectItem key="in_progress">{t('tasks.status_in_progress')}</SelectItem>
                <SelectItem key="done">{t('tasks.status_done')}</SelectItem>
              </Select>
              <Select
                label={t('tasks.priority_label', { defaultValue: 'Priority' })}
                selectedKeys={[taskForm.priority]}
                onSelectionChange={(keys) => {
                  const selected = Array.from(keys)[0];
                  if (selected) setTaskForm(prev => ({ ...prev, priority: String(selected) }));
                }}
                variant="bordered"
              >
                <SelectItem key="low">{t('tasks.priority_low')}</SelectItem>
                <SelectItem key="medium">{t('tasks.priority_medium')}</SelectItem>
                <SelectItem key="high">{t('tasks.priority_high')}</SelectItem>
                <SelectItem key="urgent">{t('tasks.priority_urgent')}</SelectItem>
              </Select>
            </div>
            <div className="grid grid-cols-2 gap-3">
              {members.length > 0 && (
                <Select
                  label={t('tasks.assigned_to')}
                  placeholder={t('tasks.unassigned')}
                  selectedKeys={taskForm.assigned_to ? new Set([taskForm.assigned_to]) : new Set<string>()}
                  onSelectionChange={(keys) => {
                    if (keys === 'all') return;
                    const selected = Array.from(keys)[0];
                    setTaskForm(prev => ({ ...prev, assigned_to: selected ? String(selected) : '' }));
                  }}
                  variant="bordered"
                >
                  {members.map((m) => (
                    <SelectItem key={String(m.id)}>{m.name}</SelectItem>
                  ))}
                </Select>
              )}
              <Input
                type="date"
                label={t('tasks.due_date')}
                value={taskForm.due_date}
                onValueChange={(val) => setTaskForm(prev => ({ ...prev, due_date: val }))}
                variant="bordered"
              />
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onCreateClose}>
              {t('form.cancel')}
            </Button>
            <Button
              color="primary"
              isLoading={isCreating}
              isDisabled={!taskForm.title.trim()}
              onPress={handleCreateTask}
            >
              {isCreating ? t('form.creating') : t('tasks.create')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default TeamTasks;
