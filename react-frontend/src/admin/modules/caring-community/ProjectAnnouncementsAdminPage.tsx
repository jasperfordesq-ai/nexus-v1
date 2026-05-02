// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useState } from 'react';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Chip,
  Divider,
  Input,
  Modal,
  ModalBody,
  ModalContent,
  ModalFooter,
  ModalHeader,
  Progress,
  Select,
  SelectItem,
  Spinner,
  Switch,
  Table,
  TableBody,
  TableCell,
  TableColumn,
  TableHeader,
  TableRow,
  Textarea,
  useDisclosure,
} from '@heroui/react';
import CheckCircle from 'lucide-react/icons/check-circle';
import Flag from 'lucide-react/icons/flag';
import Info from 'lucide-react/icons/info';
import Megaphone from 'lucide-react/icons/megaphone';
import Milestone from 'lucide-react/icons/milestone';
import PauseCircle from 'lucide-react/icons/pause-circle';
import Plus from 'lucide-react/icons/plus';
import Rocket from 'lucide-react/icons/rocket';
import Send from 'lucide-react/icons/send';
import api from '@/lib/api';
import { logError } from '@/lib/logger';

type ProjectStatus = 'draft' | 'active' | 'paused' | 'completed' | 'cancelled';

interface ProjectAnnouncement {
  id: number;
  title: string;
  summary: string | null;
  location: string | null;
  status: ProjectStatus;
  current_stage: string | null;
  progress_percent: number;
  subscriber_count: number;
  last_update_at: string | null;
  published_at: string | null;
  created_at: string | null;
}

function unwrapData<T>(raw: { data?: T } | T): T {
  return raw && typeof raw === 'object' && 'data' in raw ? (raw as { data: T }).data : raw as T;
}

function formatDate(value: string | null, fallback: string): string {
  if (!value) return fallback;
  return new Date(value).toLocaleDateString();
}

function statusColor(status: ProjectStatus): 'primary' | 'warning' | 'success' | 'default' | 'danger' {
  if (status === 'active') return 'primary';
  if (status === 'paused') return 'warning';
  if (status === 'completed') return 'success';
  if (status === 'cancelled') return 'danger';
  return 'default';
}

const STATUS_LABELS: Record<ProjectStatus, string> = {
  draft: 'Draft',
  active: 'Active',
  paused: 'Paused',
  completed: 'Completed',
  cancelled: 'Cancelled',
};

const STATUS_FILTER_ITEMS: Array<{ key: string; label: string }> = [
  { key: '', label: 'All' },
  { key: 'draft', label: 'Draft' },
  { key: 'active', label: 'Active' },
  { key: 'paused', label: 'Paused' },
  { key: 'completed', label: 'Completed' },
  { key: 'cancelled', label: 'Cancelled' },
];

export default function ProjectAnnouncementsAdminPage() {
  const createModal = useDisclosure();
  const updateModal = useDisclosure();

  const [projects, setProjects] = useState<ProjectAnnouncement[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [statusFilter, setStatusFilter] = useState('');
  const [actionId, setActionId] = useState<number | null>(null);

  const [title, setTitle] = useState('');
  const [summary, setSummary] = useState('');
  const [location, setLocation] = useState('');
  const [currentStage, setCurrentStage] = useState('');
  const [progressPercent, setProgressPercent] = useState('0');
  const [publishNow, setPublishNow] = useState(false);
  const [submitting, setSubmitting] = useState(false);

  const [selectedProject, setSelectedProject] = useState<ProjectAnnouncement | null>(null);
  const [updateTitle, setUpdateTitle] = useState('');
  const [updateBody, setUpdateBody] = useState('');
  const [updateStage, setUpdateStage] = useState('');
  const [updateProgress, setUpdateProgress] = useState('');
  const [isMilestone, setIsMilestone] = useState(true);
  const [publishUpdateNow, setPublishUpdateNow] = useState(true);

  const fetchProjects = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const suffix = statusFilter ? `?status=${encodeURIComponent(statusFilter)}` : '';
      const res = await api.get<{ data: ProjectAnnouncement[] } | ProjectAnnouncement[]>(
        `/v2/admin/caring-community/projects${suffix}`,
      );
      if (!res.success) {
        setError(res.error ?? 'Failed to load projects');
        return;
      }
      setProjects(unwrapData<ProjectAnnouncement[]>(res.data ?? []) ?? []);
    } catch (err: unknown) {
      logError('ProjectAnnouncementsAdminPage.fetchProjects', err);
      setError(err instanceof Error ? err.message : 'Failed to load projects');
    } finally {
      setLoading(false);
    }
  }, [statusFilter]);

  useEffect(() => {
    void fetchProjects();
  }, [fetchProjects]);

  const resetCreate = () => {
    setTitle('');
    setSummary('');
    setLocation('');
    setCurrentStage('');
    setProgressPercent('0');
    setPublishNow(false);
    setError(null);
    createModal.onOpen();
  };

  const createProject = async () => {
    if (!title.trim()) return;
    setSubmitting(true);
    try {
      const res = await api.post('/v2/admin/caring-community/projects', {
        title: title.trim(),
        summary: summary.trim() || null,
        location: location.trim() || null,
        current_stage: currentStage.trim() || null,
        progress_percent: Number(progressPercent || 0),
        status: publishNow ? 'active' : 'draft',
      });
      if (!res.success) {
        setError(res.error ?? 'Failed to create project');
        return;
      }
      createModal.onClose();
      await fetchProjects();
    } catch (err: unknown) {
      logError('ProjectAnnouncementsAdminPage.createProject', err);
      setError(err instanceof Error ? err.message : 'Failed to create project');
    } finally {
      setSubmitting(false);
    }
  };

  const openUpdate = (project: ProjectAnnouncement) => {
    setSelectedProject(project);
    setUpdateTitle('');
    setUpdateBody('');
    setUpdateStage(project.current_stage ?? '');
    setUpdateProgress(String(project.progress_percent));
    setIsMilestone(true);
    setPublishUpdateNow(true);
    updateModal.onOpen();
  };

  const createUpdate = async () => {
    if (!selectedProject || !updateTitle.trim()) return;
    setSubmitting(true);
    try {
      const res = await api.post(`/v2/admin/caring-community/projects/${selectedProject.id}/updates`, {
        title: updateTitle.trim(),
        body: updateBody.trim() || null,
        stage_label: updateStage.trim() || null,
        progress_percent: updateProgress === '' ? null : Number(updateProgress),
        is_milestone: isMilestone,
        status: publishUpdateNow ? 'published' : 'draft',
      });
      if (!res.success) {
        setError(res.error ?? 'Failed to post update');
        return;
      }
      updateModal.onClose();
      await fetchProjects();
    } catch (err: unknown) {
      logError('ProjectAnnouncementsAdminPage.createUpdate', err);
      setError(err instanceof Error ? err.message : 'Failed to post update');
    } finally {
      setSubmitting(false);
    }
  };

  const publishProject = async (projectId: number) => {
    setActionId(projectId);
    try {
      const res = await api.post(`/v2/admin/caring-community/projects/${projectId}/publish`);
      if (!res.success) {
        setError(res.error ?? 'Failed to publish project');
        return;
      }
      await fetchProjects();
    } catch (err: unknown) {
      logError('ProjectAnnouncementsAdminPage.publishProject', err);
      setError(err instanceof Error ? err.message : 'Failed to publish project');
    } finally {
      setActionId(null);
    }
  };

  const setProjectStatus = async (project: ProjectAnnouncement, status: ProjectStatus) => {
    setActionId(project.id);
    try {
      const res = await api.put(`/v2/admin/caring-community/projects/${project.id}`, { status });
      if (!res.success) {
        setError(res.error ?? 'Failed to update project status');
        return;
      }
      await fetchProjects();
    } catch (err: unknown) {
      logError('ProjectAnnouncementsAdminPage.setProjectStatus', err);
      setError(err instanceof Error ? err.message : 'Failed to update project status');
    } finally {
      setActionId(null);
    }
  };

  return (
    <>
      {/* Intro card */}
      <Card className="border-l-4 border-l-primary bg-primary-50 dark:bg-primary-900/20 mb-4" shadow="none">
        <CardBody className="px-4 py-3">
          <div className="flex gap-3">
            <Info className="mt-0.5 h-4 w-4 shrink-0 text-primary" aria-hidden="true" />
            <div className="space-y-1 text-sm">
              <p className="font-semibold text-primary-800 dark:text-primary-200">About this page</p>
              <p className="text-default-600">
                Project Announcements are non-urgent updates posted to the community feed and member
                notification centre. Use them for programme news, volunteer recruitment, event
                notices, and impact updates. Unlike Emergency Alerts, announcements are queued and
                delivered on the platform's normal notification schedule.
              </p>
              <div className="space-y-0.5 pt-1 text-default-500">
                <p><strong>Draft:</strong> Not visible to members — edit freely before publishing.</p>
                <p><strong>Active:</strong> Published and visible in the community feed. Use "Add update" to post milestone updates to subscribers.</p>
                <p><strong>Paused / Completed:</strong> Archived from the active feed. Subscribers are notified on completion.</p>
              </div>
            </div>
          </div>
        </CardBody>
      </Card>

      <Card>
        <CardHeader className="flex flex-wrap items-center justify-between gap-4">
          <div className="flex items-center gap-2">
            <Megaphone className="h-5 w-5 text-primary" aria-hidden="true" />
            <div>
              <h1 className="text-lg font-semibold">Project announcements</h1>
              <p className="text-sm text-default-500">
                Publish initiatives and milestone updates that members can subscribe to.
              </p>
            </div>
          </div>
          <Button
            color="primary"
            startContent={<Plus className="h-4 w-4" aria-hidden="true" />}
            onPress={resetCreate}
          >
            Create project
          </Button>
        </CardHeader>
        <Divider />
        <CardBody className="gap-4">
          <div className="flex max-w-xs">
            <Select
              aria-label="Filter by status"
              selectedKeys={[statusFilter]}
              onSelectionChange={(keys) => setStatusFilter(String(Array.from(keys)[0] ?? ''))}
              size="sm"
              variant="bordered"
            >
              {STATUS_FILTER_ITEMS.map((item) => (
                <SelectItem key={item.key}>{item.label}</SelectItem>
              ))}
            </Select>
          </div>

          {loading && (
            <div className="flex justify-center py-10">
              <Spinner size="lg" />
            </div>
          )}

          {!loading && error && <p className="text-sm text-danger">{error}</p>}

          {!loading && !error && projects.length === 0 && (
            <p className="py-6 text-center text-sm text-default-500">No projects yet.</p>
          )}

          {!loading && projects.length > 0 && (
            <Table aria-label="Project announcements" removeWrapper>
              <TableHeader>
                <TableColumn>Project</TableColumn>
                <TableColumn>Status</TableColumn>
                <TableColumn>Progress</TableColumn>
                <TableColumn>Subscribers</TableColumn>
                <TableColumn>Updated</TableColumn>
                <TableColumn>Actions</TableColumn>
              </TableHeader>
              <TableBody>
                {projects.map((project) => (
                  <TableRow key={project.id}>
                    <TableCell>
                      <div className="max-w-xs">
                        <p className="font-medium">{project.title}</p>
                        {project.current_stage && (
                          <p className="mt-1 flex items-center gap-1 text-xs text-default-500">
                            <Flag className="h-3.5 w-3.5" aria-hidden="true" />
                            {project.current_stage}
                          </p>
                        )}
                      </div>
                    </TableCell>
                    <TableCell>
                      <Chip color={statusColor(project.status)} size="sm" variant="flat">
                        {STATUS_LABELS[project.status]}
                      </Chip>
                    </TableCell>
                    <TableCell>
                      <div className="min-w-28">
                        <Progress
                          aria-label="Progress"
                          value={project.progress_percent}
                          size="sm"
                        />
                        <p className="mt-1 text-xs text-default-500">
                          {project.progress_percent}%
                        </p>
                      </div>
                    </TableCell>
                    <TableCell>{project.subscriber_count}</TableCell>
                    <TableCell>
                      {formatDate(project.last_update_at ?? project.published_at ?? project.created_at, '—')}
                    </TableCell>
                    <TableCell>
                      <div className="flex flex-wrap gap-2">
                        {project.status === 'draft' && (
                          <Button
                            size="sm"
                            color="primary"
                            variant="flat"
                            isLoading={actionId === project.id}
                            startContent={<Rocket className="h-3.5 w-3.5" aria-hidden="true" />}
                            onPress={() => void publishProject(project.id)}
                          >
                            Publish
                          </Button>
                        )}
                        <Button
                          size="sm"
                          variant="flat"
                          startContent={<Milestone className="h-3.5 w-3.5" aria-hidden="true" />}
                          onPress={() => openUpdate(project)}
                        >
                          Add update
                        </Button>
                        {project.status === 'active' && (
                          <Button
                            size="sm"
                            color="warning"
                            variant="flat"
                            isLoading={actionId === project.id}
                            startContent={<PauseCircle className="h-3.5 w-3.5" aria-hidden="true" />}
                            onPress={() => void setProjectStatus(project, 'paused')}
                          >
                            Pause
                          </Button>
                        )}
                        {project.status !== 'completed' && project.status !== 'cancelled' && (
                          <Button
                            size="sm"
                            color="success"
                            variant="flat"
                            isLoading={actionId === project.id}
                            startContent={<CheckCircle className="h-3.5 w-3.5" aria-hidden="true" />}
                            onPress={() => void setProjectStatus(project, 'completed')}
                          >
                            Complete
                          </Button>
                        )}
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardBody>
      </Card>

      <Modal isOpen={createModal.isOpen} onClose={createModal.onClose} size="2xl">
        <ModalContent>
          <ModalHeader>Create a project</ModalHeader>
          <ModalBody className="gap-4">
            <Input
              label="Title"
              value={title}
              onValueChange={setTitle}
              variant="bordered"
              isRequired
            />
            <Textarea
              label="Summary"
              value={summary}
              onValueChange={setSummary}
              variant="bordered"
              minRows={3}
            />
            <div className="grid gap-3 sm:grid-cols-2">
              <Input
                label="Location"
                value={location}
                onValueChange={setLocation}
                variant="bordered"
              />
              <Input
                label="Current stage"
                value={currentStage}
                onValueChange={setCurrentStage}
                variant="bordered"
              />
            </div>
            <Input
              label="Progress (%)"
              type="number"
              min={0}
              max={100}
              value={progressPercent}
              onValueChange={setProgressPercent}
              variant="bordered"
            />
            <Switch isSelected={publishNow} onValueChange={setPublishNow}>
              Publish immediately
            </Switch>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={createModal.onClose}>
              Cancel
            </Button>
            <Button color="primary" isLoading={submitting} onPress={() => void createProject()}>
              Create project
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      <Modal isOpen={updateModal.isOpen} onClose={updateModal.onClose} size="2xl">
        <ModalContent>
          <ModalHeader>Post a project update</ModalHeader>
          <ModalBody className="gap-4">
            <Input
              label="Update title"
              value={updateTitle}
              onValueChange={setUpdateTitle}
              variant="bordered"
              isRequired
            />
            <Textarea
              label="Update body"
              value={updateBody}
              onValueChange={setUpdateBody}
              variant="bordered"
              minRows={4}
            />
            <div className="grid gap-3 sm:grid-cols-2">
              <Input
                label="Stage"
                value={updateStage}
                onValueChange={setUpdateStage}
                variant="bordered"
              />
              <Input
                label="Progress (%)"
                type="number"
                min={0}
                max={100}
                value={updateProgress}
                onValueChange={setUpdateProgress}
                variant="bordered"
              />
            </div>
            <div className="grid gap-3 sm:grid-cols-2">
              <Switch isSelected={isMilestone} onValueChange={setIsMilestone}>
                Mark as milestone
              </Switch>
              <Switch isSelected={publishUpdateNow} onValueChange={setPublishUpdateNow}>
                Publish update now
              </Switch>
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={updateModal.onClose}>
              Cancel
            </Button>
            <Button
              color="primary"
              isLoading={submitting}
              startContent={<Send className="h-4 w-4" aria-hidden="true" />}
              onPress={() => void createUpdate()}
            >
              Publish update
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </>
  );
}
