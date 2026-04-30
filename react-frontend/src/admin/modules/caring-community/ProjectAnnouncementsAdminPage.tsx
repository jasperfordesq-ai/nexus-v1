// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
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

export default function ProjectAnnouncementsAdminPage() {
  const { t } = useTranslation('project_announcements_admin');
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

  const statusItems = useMemo(() => [
    { key: '', label: t('filters.all') },
    { key: 'draft', label: t('status.draft') },
    { key: 'active', label: t('status.active') },
    { key: 'paused', label: t('status.paused') },
    { key: 'completed', label: t('status.completed') },
    { key: 'cancelled', label: t('status.cancelled') },
  ], [t]);

  const fetchProjects = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const suffix = statusFilter ? `?status=${encodeURIComponent(statusFilter)}` : '';
      const res = await api.get<{ data: ProjectAnnouncement[] } | ProjectAnnouncement[]>(
        `/v2/admin/caring-community/projects${suffix}`,
      );
      if (!res.success) {
        setError(res.error ?? t('errors.load'));
        return;
      }
      setProjects(unwrapData<ProjectAnnouncement[]>(res.data) ?? []);
    } catch (err: unknown) {
      logError('ProjectAnnouncementsAdminPage.fetchProjects', err);
      setError(err instanceof Error ? err.message : t('errors.load'));
    } finally {
      setLoading(false);
    }
  }, [statusFilter, t]);

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
        setError(res.error ?? t('errors.create'));
        return;
      }
      createModal.onClose();
      await fetchProjects();
    } catch (err: unknown) {
      logError('ProjectAnnouncementsAdminPage.createProject', err);
      setError(err instanceof Error ? err.message : t('errors.create'));
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
        setError(res.error ?? t('errors.update'));
        return;
      }
      updateModal.onClose();
      await fetchProjects();
    } catch (err: unknown) {
      logError('ProjectAnnouncementsAdminPage.createUpdate', err);
      setError(err instanceof Error ? err.message : t('errors.update'));
    } finally {
      setSubmitting(false);
    }
  };

  const publishProject = async (projectId: number) => {
    setActionId(projectId);
    try {
      const res = await api.post(`/v2/admin/caring-community/projects/${projectId}/publish`);
      if (!res.success) {
        setError(res.error ?? t('errors.publish'));
        return;
      }
      await fetchProjects();
    } catch (err: unknown) {
      logError('ProjectAnnouncementsAdminPage.publishProject', err);
      setError(err instanceof Error ? err.message : t('errors.publish'));
    } finally {
      setActionId(null);
    }
  };

  const setProjectStatus = async (project: ProjectAnnouncement, status: ProjectStatus) => {
    setActionId(project.id);
    try {
      const res = await api.put(`/v2/admin/caring-community/projects/${project.id}`, { status });
      if (!res.success) {
        setError(res.error ?? t('errors.status'));
        return;
      }
      await fetchProjects();
    } catch (err: unknown) {
      logError('ProjectAnnouncementsAdminPage.setProjectStatus', err);
      setError(err instanceof Error ? err.message : t('errors.status'));
    } finally {
      setActionId(null);
    }
  };

  return (
    <>
      <Card>
        <CardHeader className="flex flex-wrap items-center justify-between gap-4">
          <div className="flex items-center gap-2">
            <Megaphone className="h-5 w-5 text-primary" aria-hidden="true" />
            <div>
              <h1 className="text-lg font-semibold">{t('title')}</h1>
              <p className="text-sm text-default-500">{t('subtitle')}</p>
            </div>
          </div>
          <Button
            color="primary"
            startContent={<Plus className="h-4 w-4" aria-hidden="true" />}
            onPress={resetCreate}
          >
            {t('create_project')}
          </Button>
        </CardHeader>
        <Divider />
        <CardBody className="gap-4">
          <div className="flex max-w-xs">
            <Select
              aria-label={t('filters.status')}
              selectedKeys={[statusFilter]}
              onSelectionChange={(keys) => setStatusFilter(String(Array.from(keys)[0] ?? ''))}
              size="sm"
              variant="bordered"
            >
              {statusItems.map((item) => (
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
            <p className="py-6 text-center text-sm text-default-500">{t('empty')}</p>
          )}

          {!loading && projects.length > 0 && (
            <Table aria-label={t('table.aria')} removeWrapper>
              <TableHeader>
                <TableColumn>{t('table.project')}</TableColumn>
                <TableColumn>{t('table.status')}</TableColumn>
                <TableColumn>{t('table.progress')}</TableColumn>
                <TableColumn>{t('table.subscribers')}</TableColumn>
                <TableColumn>{t('table.updated')}</TableColumn>
                <TableColumn>{t('table.actions')}</TableColumn>
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
                        {t(`status.${project.status}`)}
                      </Chip>
                    </TableCell>
                    <TableCell>
                      <div className="min-w-28">
                        <Progress
                          aria-label={t('table.progress')}
                          value={project.progress_percent}
                          size="sm"
                        />
                        <p className="mt-1 text-xs text-default-500">
                          {t('progress_value', { value: project.progress_percent })}
                        </p>
                      </div>
                    </TableCell>
                    <TableCell>{project.subscriber_count}</TableCell>
                    <TableCell>
                      {formatDate(project.last_update_at ?? project.published_at ?? project.created_at, t('date_unknown'))}
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
                            {t('actions.publish')}
                          </Button>
                        )}
                        <Button
                          size="sm"
                          variant="flat"
                          startContent={<Milestone className="h-3.5 w-3.5" aria-hidden="true" />}
                          onPress={() => openUpdate(project)}
                        >
                          {t('actions.add_update')}
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
                            {t('actions.pause')}
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
                            {t('actions.complete')}
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
          <ModalHeader>{t('create_modal.title')}</ModalHeader>
          <ModalBody className="gap-4">
            <Input
              label={t('fields.title')}
              value={title}
              onValueChange={setTitle}
              variant="bordered"
              isRequired
            />
            <Textarea
              label={t('fields.summary')}
              value={summary}
              onValueChange={setSummary}
              variant="bordered"
              minRows={3}
            />
            <div className="grid gap-3 sm:grid-cols-2">
              <Input
                label={t('fields.location')}
                value={location}
                onValueChange={setLocation}
                variant="bordered"
              />
              <Input
                label={t('fields.stage')}
                value={currentStage}
                onValueChange={setCurrentStage}
                variant="bordered"
              />
            </div>
            <Input
              label={t('fields.progress')}
              type="number"
              min={0}
              max={100}
              value={progressPercent}
              onValueChange={setProgressPercent}
              variant="bordered"
            />
            <Switch isSelected={publishNow} onValueChange={setPublishNow}>
              {t('fields.publish_now')}
            </Switch>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={createModal.onClose}>
              {t('actions.cancel')}
            </Button>
            <Button color="primary" isLoading={submitting} onPress={() => void createProject()}>
              {t('create_modal.submit')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      <Modal isOpen={updateModal.isOpen} onClose={updateModal.onClose} size="2xl">
        <ModalContent>
          <ModalHeader>{t('update_modal.title')}</ModalHeader>
          <ModalBody className="gap-4">
            <Input
              label={t('fields.update_title')}
              value={updateTitle}
              onValueChange={setUpdateTitle}
              variant="bordered"
              isRequired
            />
            <Textarea
              label={t('fields.update_body')}
              value={updateBody}
              onValueChange={setUpdateBody}
              variant="bordered"
              minRows={4}
            />
            <div className="grid gap-3 sm:grid-cols-2">
              <Input
                label={t('fields.stage')}
                value={updateStage}
                onValueChange={setUpdateStage}
                variant="bordered"
              />
              <Input
                label={t('fields.progress')}
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
                {t('fields.milestone')}
              </Switch>
              <Switch isSelected={publishUpdateNow} onValueChange={setPublishUpdateNow}>
                {t('fields.publish_update_now')}
              </Switch>
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={updateModal.onClose}>
              {t('actions.cancel')}
            </Button>
            <Button
              color="primary"
              isLoading={submitting}
              startContent={<Send className="h-4 w-4" aria-hidden="true" />}
              onPress={() => void createUpdate()}
            >
              {t('update_modal.submit')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </>
  );
}
