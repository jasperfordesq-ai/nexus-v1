// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Coordinator Tasks
 * Task management interface for timebank coordinators to track follow-ups and actions.
 */

import { useState, useCallback, useEffect } from 'react';
import {
  Card, CardBody, Button, Input, Textarea, Select, SelectItem,
  Modal, ModalContent, ModalHeader, ModalBody, ModalFooter,
  useDisclosure, Chip, Spinner, Pagination, Avatar,
  Dropdown, DropdownTrigger, DropdownMenu, DropdownItem, Checkbox,
} from '@heroui/react';
import ClipboardList from 'lucide-react/icons/clipboard-list';
import Plus from 'lucide-react/icons/plus';
import Calendar from 'lucide-react/icons/calendar';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import CheckCircle from 'lucide-react/icons/circle-check-big';
import Clock from 'lucide-react/icons/clock';
import MoreVertical from 'lucide-react/icons/ellipsis-vertical';
import Trash2 from 'lucide-react/icons/trash-2';
import Edit3 from 'lucide-react/icons/pen-line';
import User from 'lucide-react/icons/user';
import Search from 'lucide-react/icons/search';
import { Link } from 'react-router-dom';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminCrm } from '../../api/adminApi';
import { PageHeader, MemberSearchPicker, type MemberSearchMember } from '../../components';

import { useTranslation } from 'react-i18next';
interface Task {
  id: number;
  tenant_id: number;
  assigned_to: number;
  user_id: number | null;
  title: string;
  description: string | null;
  priority: 'low' | 'medium' | 'high' | 'urgent';
  status: 'pending' | 'in_progress' | 'completed' | 'cancelled';
  due_date: string | null;
  completed_at: string | null;
  created_by: number;
  created_at: string;
  updated_at: string;
  assigned_to_name: string;
  created_by_name: string;
  user_name: string | null;
  user_avatar: string | null;
}

interface AdminMember {
  id: number;
  name: string;
  email: string;
  avatar_url: string;
  role: string;
}

type StatusFilter = 'all' | 'pending' | 'in_progress' | 'completed';
type PriorityFilter = 'all' | 'low' | 'medium' | 'high' | 'urgent';

const STATUS_TABS: { key: StatusFilter; labelKey: string }[] = [
  { key: 'all', labelKey: 'crm.status_all' },
  { key: 'pending', labelKey: 'crm.status_pending' },
  { key: 'in_progress', labelKey: 'crm.status_in_progress' },
  { key: 'completed', labelKey: 'crm.status_completed' },
];

const PRIORITY_OPTIONS: { key: PriorityFilter; labelKey: string }[] = [
  { key: 'all', labelKey: 'crm.priority_all' },
  { key: 'low', labelKey: 'crm.priority_low' },
  { key: 'medium', labelKey: 'crm.priority_medium' },
  { key: 'high', labelKey: 'crm.priority_high' },
  { key: 'urgent', labelKey: 'crm.priority_urgent' },
];

const PRIORITY_COLOR_MAP: Record<string, 'default' | 'primary' | 'warning' | 'danger'> = {
  low: 'default',
  medium: 'primary',
  high: 'warning',
  urgent: 'danger',
};

const STATUS_COLOR_MAP: Record<string, 'default' | 'primary' | 'success'> = {
  pending: 'default',
  in_progress: 'primary',
  completed: 'success',
  cancelled: 'default',
};

const ITEMS_PER_PAGE = 20;

function isOverdue(dueDate: string | null, status: string): boolean {
  if (!dueDate || status === 'completed' || status === 'cancelled') return false;
  return new Date(dueDate) < new Date();
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString(undefined, {
    year: 'numeric', month: 'short', day: 'numeric',
  });
}

function formatDateTime(dateStr: string): string {
  return new Date(dateStr).toLocaleString(undefined, {
    year: 'numeric', month: 'short', day: 'numeric',
    hour: '2-digit', minute: '2-digit',
  });
}

export default function CoordinatorTasks() {
  const { t } = useTranslation('admin');
  usePageTitle("CRM");
  const { tenantPath } = useTenant();
  const toast = useToast();

  // State
  const [tasks, setTasks] = useState<Task[]>([]);
  const [total, setTotal] = useState(0);
  const [totalPages, setTotalPages] = useState(1);
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [statusFilter, setStatusFilter] = useState<StatusFilter>('all');
  const [priorityFilter, setPriorityFilter] = useState<PriorityFilter>('all');
  const [admins, setAdmins] = useState<AdminMember[]>([]);
  const [adminsLoaded, setAdminsLoaded] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');

  // Modal state
  const createModal = useDisclosure();
  const deleteModal = useDisclosure();
  const [editingTask, setEditingTask] = useState<Task | null>(null);
  const [deletingTask, setDeletingTask] = useState<Task | null>(null);
  const [saving, setSaving] = useState(false);
  const [deleting, setDeleting] = useState(false);

  // Form state
  const [formTitle, setFormTitle] = useState('');
  const [formDescription, setFormDescription] = useState('');
  const [formPriority, setFormPriority] = useState<string>('medium');
  const [formAssignedTo, setFormAssignedTo] = useState<string>('');
  const [formUserId, setFormUserId] = useState('');
  const [formMember, setFormMember] = useState<MemberSearchMember | null>(null);
  const [formDueDate, setFormDueDate] = useState('');

  const loadTasks = useCallback(async () => {
    setLoading(true);
    try {
      const params: Record<string, unknown> = {
        page,
        limit: ITEMS_PER_PAGE,
      };
      if (statusFilter !== 'all') params.status = statusFilter;
      if (priorityFilter !== 'all') params.priority = priorityFilter;
      if (searchQuery.trim().length >= 2) params.search = searchQuery.trim();

      const res = await adminCrm.getTasks(params);
      if (res.success) {
        setTasks(Array.isArray(res.data) ? res.data as Task[] : []);
        setTotal(res.meta?.total || 0);
        setTotalPages(res.meta?.total_pages || 1);
      }
    } catch {
      toast.error("Failed to load tasks");
      setTasks([]);
    }
    setLoading(false);
  }, [page, statusFilter, priorityFilter, searchQuery, toast])


  const loadAdmins = useCallback(async () => {
    if (adminsLoaded) return;
    try {
      const res = await adminCrm.getAdmins();
      if (res.success && res.data) {
        setAdmins(res.data as unknown as AdminMember[]);
      }
    } catch {
      // silent — admins dropdown will be empty
    }
    setAdminsLoaded(true);
  }, [adminsLoaded]);

  useEffect(() => { loadTasks(); }, [loadTasks]);
  useEffect(() => { loadAdmins(); }, [loadAdmins]);

  // Reset page when filters change
  useEffect(() => { setPage(1); }, [statusFilter, priorityFilter, searchQuery]);

  const resetForm = useCallback(() => {
    setFormTitle('');
    setFormDescription('');
    setFormPriority('medium');
    setFormAssignedTo('');
    setFormUserId('');
    setFormMember(null);
    setFormDueDate('');
    setEditingTask(null);
  }, []);

  const openCreate = useCallback(() => {
    resetForm();
    createModal.onOpen();
  }, [resetForm, createModal]);

  const openEdit = useCallback((task: Task) => {
    setEditingTask(task);
    setFormTitle(task.title);
    setFormDescription(task.description || '');
    setFormPriority(task.priority);
    setFormAssignedTo(String(task.assigned_to));
    setFormUserId(task.user_id ? String(task.user_id) : '');
    setFormMember(task.user_id && task.user_name ? {
      id: task.user_id,
      name: task.user_name,
      email: '',
      avatar_url: task.user_avatar,
    } : null);
    setFormDueDate(task.due_date || '');
    createModal.onOpen();
  }, [createModal]);

  const handleSave = useCallback(async () => {
    if (!formTitle.trim()) {
      toast.error("Title is required");
      return;
    }
    setSaving(true);
    try {
      const payload: Record<string, unknown> = {
        title: formTitle.trim(),
        description: formDescription.trim() || null,
        priority: formPriority,
        assigned_to: formAssignedTo ? Number(formAssignedTo) : undefined,
        // When editing, send null explicitly so the backend clears the field
        user_id: formUserId ? Number(formUserId) : (editingTask ? null : undefined),
        due_date: formDueDate || (editingTask ? null : undefined),
      };

      if (editingTask) {
        await adminCrm.updateTask(editingTask.id, payload as Parameters<typeof adminCrm.updateTask>[1]);
        toast.success("Task Updated");
      } else {
        await adminCrm.createTask(payload as Parameters<typeof adminCrm.createTask>[0]);
        toast.success("Task Created");
      }
      createModal.onClose();
      resetForm();
      await loadTasks();
    } catch {
      toast.error(editingTask ? "Failed to update task" : "Failed to create task");
    }
    setSaving(false);
  }, [formTitle, formDescription, formPriority, formAssignedTo, formUserId, formDueDate, editingTask, createModal, resetForm, loadTasks, toast])


  const handleDelete = useCallback(async () => {
    if (!deletingTask) return;
    setDeleting(true);
    try {
      await adminCrm.deleteTask(deletingTask.id);
      toast.success("Task Deleted");
      deleteModal.onClose();
      setDeletingTask(null);
      await loadTasks();
    } catch {
      toast.error("Failed to delete task");
    }
    setDeleting(false);
  }, [deletingTask, deleteModal, loadTasks, toast])


  const handleStatusChange = useCallback(async (task: Task, newStatus: Task['status']) => {
    try {
      await adminCrm.updateTask(task.id, { status: newStatus });
      toast.success(`Task status changed`);
      await loadTasks();
    } catch {
      toast.error("Failed to update task status");
    }
  }, [loadTasks, toast])


  const handleQuickComplete = useCallback(async (task: Task) => {
    const newStatus = task.status === 'completed' ? 'pending' : 'completed';
    await handleStatusChange(task, newStatus);
  }, [handleStatusChange]);

  const openDeleteConfirm = useCallback((task: Task) => {
    setDeletingTask(task);
    deleteModal.onOpen();
  }, [deleteModal]);

  return (
    <div className="max-w-6xl mx-auto space-y-6">
      <PageHeader
        title={"Coordinator Tasks"}
        description={`Tasks Total`}
        actions={
          <Button color="primary" startContent={<Plus className="w-4 h-4" />} onPress={openCreate}>
            {"Create Task"}
          </Button>
        }
      />

      {/* Filters */}
      <div className="flex flex-col sm:flex-row gap-4 flex-wrap items-end">
        {/* Status tabs */}
        <div className="flex gap-1 p-1 bg-default-100 dark:bg-default-50 rounded-lg">
          {STATUS_TABS.map((tab) => (
            <Button
              key={tab.key}
              size="sm"
              variant={statusFilter === tab.key ? 'solid' : 'light'}
              color={statusFilter === tab.key ? 'primary' : 'default'}
              onPress={() => setStatusFilter(tab.key)}
            >
              {t(tab.labelKey)}
            </Button>
          ))}
        </div>

        {/* Priority filter */}
        <Select
          size="sm"
          label={"Priority"}
          selectedKeys={[priorityFilter]}
          onChange={(e) => setPriorityFilter((e.target.value || 'all') as PriorityFilter)}
          className="max-w-[180px]"
        >
          {PRIORITY_OPTIONS.map((opt) => (
            <SelectItem key={opt.key}>{t(opt.labelKey)}</SelectItem>
          ))}
        </Select>

        {/* Search */}
        <Input
          size="sm"
          label={"Search"}
          placeholder={"Search Tasks..."}
          className="max-w-[220px]"
          startContent={<Search className="w-4 h-4" />}
          value={searchQuery}
          onValueChange={setSearchQuery}
          isClearable
          onClear={() => setSearchQuery('')}
        />
      </div>

      {/* Loading */}
      {loading && (
        <div className="flex justify-center py-12">
          <Spinner size="lg" label={"Loading Tasks"} />
        </div>
      )}

      {/* Empty state */}
      {!loading && tasks.length === 0 && (
        <Card>
          <CardBody className="py-12 text-center">
            <ClipboardList className="w-12 h-12 mx-auto text-default-300 mb-4" />
            <p className="text-default-500 text-lg font-medium">{"No tasks found"}</p>
            <p className="text-default-400 text-sm mt-1">
              {statusFilter !== 'all' || priorityFilter !== 'all'
                ? "No tasks match your current filters"
                : "No tasks have been created yet. Create one above."}
            </p>
          </CardBody>
        </Card>
      )}

      {/* Task list */}
      {!loading && tasks.length > 0 && (
        <div className="space-y-3">
          {tasks.map((task) => {
            const overdue = isOverdue(task.due_date, task.status);
            return (
              <Card
                key={task.id}
                className={`transition-all ${overdue ? 'border-l-4 border-l-danger' : ''} ${
                  task.status === 'completed' ? 'opacity-75' : ''
                }`}
              >
                <CardBody className="p-4">
                  <div className="flex items-start gap-3">
                    {/* Quick complete checkbox */}
                    <div className="pt-0.5">
                      <Checkbox
                        isSelected={task.status === 'completed'}
                        onChange={() => handleQuickComplete(task)}
                        aria-label={`Mark "${task.title}" as ${task.status === 'completed' ? 'pending' : 'completed'}`}
                      />
                    </div>

                    {/* Main content */}
                    <div className="flex-1 min-w-0">
                      <div className="flex items-start justify-between gap-2">
                        <div className="flex-1 min-w-0">
                          <h3
                            className={`font-semibold text-base ${
                              task.status === 'completed' ? 'line-through text-default-400' : ''
                            }`}
                          >
                            {task.title}
                          </h3>
                          {task.description && (
                            <p className="text-sm text-default-500 mt-1 line-clamp-2">
                              {task.description}
                            </p>
                          )}
                        </div>

                        {/* Actions dropdown */}
                        <Dropdown>
                          <DropdownTrigger>
                            <Button isIconOnly size="sm" variant="light" aria-label={"Task Actions"}>
                              <MoreVertical className="w-4 h-4" />
                            </Button>
                          </DropdownTrigger>
                          <DropdownMenu aria-label={"Task Actions"}>
                            <DropdownItem
                              key="edit"
                              startContent={<Edit3 className="w-4 h-4" />}
                              onPress={() => openEdit(task)}
                            >
                              {"Edit"}
                            </DropdownItem>
                            <DropdownItem
                              key="complete"
                              startContent={<CheckCircle className="w-4 h-4" />}
                              onPress={() => handleStatusChange(task, 'completed')}
                            >
                              {"Mark Complete"}
                            </DropdownItem>
                            <DropdownItem
                              key="in_progress"
                              startContent={<Clock className="w-4 h-4" />}
                              onPress={() => handleStatusChange(task, 'in_progress')}
                            >
                              {"Mark in Progress"}
                            </DropdownItem>
                            <DropdownItem
                              key="cancel"
                              startContent={<AlertTriangle className="w-4 h-4" />}
                              onPress={() => handleStatusChange(task, 'cancelled')}
                            >
                              {"Cancel"}
                            </DropdownItem>
                            <DropdownItem
                              key="delete"
                              className="text-danger"
                              color="danger"
                              startContent={<Trash2 className="w-4 h-4" />}
                              onPress={() => openDeleteConfirm(task)}
                            >
                              {"Delete"}
                            </DropdownItem>
                          </DropdownMenu>
                        </Dropdown>
                      </div>

                      {/* Metadata row */}
                      <div className="flex flex-wrap items-center gap-2 mt-3">
                        <Chip size="sm" color={PRIORITY_COLOR_MAP[task.priority]} variant="flat">
                          {task.priority.charAt(0).toUpperCase() + task.priority.slice(1)}
                        </Chip>
                        <Chip size="sm" color={STATUS_COLOR_MAP[task.status] || 'default'} variant="flat">
                          {task.status.replace('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase())}
                        </Chip>

                        {task.due_date && (
                          <span
                            className={`inline-flex items-center gap-1 text-xs ${
                              overdue ? 'text-danger font-semibold' : 'text-default-500'
                            }`}
                          >
                            <Calendar className="w-3 h-3" />
                            {overdue && <AlertTriangle className="w-3 h-3" />}
                            {"Due"} {formatDate(task.due_date)}
                          </span>
                        )}

                        <span className="inline-flex items-center gap-1 text-xs text-default-400">
                          <User className="w-3 h-3" />
                          {task.assigned_to_name}
                        </span>
                      </div>

                      {/* Related member + created by */}
                      <div className="flex flex-wrap items-center gap-4 mt-2">
                        {task.user_name && (
                          <Link
                            to={tenantPath(`/admin/users/${task.user_id}/edit`)}
                            className="flex items-center gap-2 text-xs text-default-500 hover:text-primary transition-colors"
                          >
                            <Avatar
                              src={task.user_avatar || undefined}
                              name={task.user_name}
                              size="sm"
                              className="w-5 h-5"
                            />
                            <span>{"Related to"} {task.user_name}</span>
                          </Link>
                        )}
                        <span className="text-xs text-default-400">
                          {"Created by"} {task.created_by_name} &middot; {formatDateTime(task.created_at)}
                        </span>
                      </div>
                    </div>
                  </div>
                </CardBody>
              </Card>
            );
          })}
        </div>
      )}

      {/* Pagination */}
      {totalPages > 1 && (
        <div className="flex justify-center pt-4">
          <Pagination
            total={totalPages}
            page={page}
            onChange={setPage}
            showControls
          />
        </div>
      )}

      {/* Create / Edit Modal */}
      <Modal isOpen={createModal.isOpen} onOpenChange={createModal.onOpenChange} size="lg">
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader className="flex items-center gap-2">
                {editingTask ? <Edit3 className="w-5 h-5" /> : <Plus className="w-5 h-5" />}
                {editingTask ? "Edit Task" : "Create Task"}
              </ModalHeader>
              <ModalBody className="gap-4">
                <Input
                  label={"Title"}
                  placeholder={"Task title..."}
                  value={formTitle}
                  onValueChange={setFormTitle}
                  isRequired
                  autoFocus
                />
                <Textarea
                  label={"Description"}
                  placeholder={"Optional Description or Notes..."}
                  value={formDescription}
                  onValueChange={setFormDescription}
                  minRows={3}
                />
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <Select
                    label={"Priority"}
                    selectedKeys={[formPriority]}
                    onChange={(e) => setFormPriority(e.target.value || 'medium')}
                  >
                    <SelectItem key="low">{"Low"}</SelectItem>
                    <SelectItem key="medium">{"Medium"}</SelectItem>
                    <SelectItem key="high">{"High"}</SelectItem>
                    <SelectItem key="urgent">{"Urgent"}</SelectItem>
                  </Select>
                  <Select
                    label={"Assign to"}
                    selectedKeys={formAssignedTo ? [formAssignedTo] : []}
                    onChange={(e) => setFormAssignedTo(e.target.value)}
                  >
                    {admins.map((admin) => (
                      <SelectItem key={String(admin.id)}>
                        {admin.name} ({admin.role})
                      </SelectItem>
                    ))}
                  </Select>
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <MemberSearchPicker
                    label={"Search Member"}
                    placeholder={"Type a Name or Email to Search..."}
                    noResultsText={"No members found found"}
                    value={formUserId}
                    selectedMember={formMember}
                    onSelectedMemberChange={setFormMember}
                    onValueChange={setFormUserId}
                    size="md"
                  />
                  <Input
                    label={"Due Date"}
                    type="date"
                    value={formDueDate}
                    onValueChange={setFormDueDate}
                  />
                </div>
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose} isDisabled={saving}>
                  {"Cancel"}
                </Button>
                <Button color="primary" onPress={handleSave} isLoading={saving} isDisabled={saving}>
                  {editingTask ? "Update Task" : "Create Task"}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>

      {/* Delete Confirmation Modal */}
      <Modal isOpen={deleteModal.isOpen} onOpenChange={deleteModal.onOpenChange} size="sm">
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader className="flex items-center gap-2 text-danger">
                <Trash2 className="w-5 h-5" />
                {"Delete Task"}
              </ModalHeader>
              <ModalBody>
                <p>
                  {"Are you sure you want to delete this task? This cannot be undone."}
                  {deletingTask && <strong> ({deletingTask.title})</strong>}
                </p>
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose} isDisabled={deleting}>
                  {"Cancel"}
                </Button>
                <Button color="danger" onPress={handleDelete} isLoading={deleting} isDisabled={deleting}>
                  {"Delete"}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}
