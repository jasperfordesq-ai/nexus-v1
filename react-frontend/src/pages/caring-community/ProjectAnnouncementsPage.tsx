// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Button, Chip, Progress, Spinner } from '@heroui/react';
import ArrowLeft from 'lucide-react/icons/arrow-left';
import Bell from 'lucide-react/icons/bell';
import BellOff from 'lucide-react/icons/bell-off';
import CalendarDays from 'lucide-react/icons/calendar-days';
import Flag from 'lucide-react/icons/flag';
import MapPin from 'lucide-react/icons/map-pin';
import Megaphone from 'lucide-react/icons/megaphone';
import Milestone from 'lucide-react/icons/milestone';
import Users from 'lucide-react/icons/users';
import { GlassCard } from '@/components/ui';
import { PageMeta } from '@/components/seo';
import { useAuth, useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

type ProjectStatus = 'draft' | 'active' | 'paused' | 'completed' | 'cancelled';

interface ProjectUpdate {
  id: number;
  stage_label: string | null;
  title: string;
  body: string | null;
  progress_percent: number | null;
  is_milestone: boolean;
  status: 'draft' | 'published';
  published_at: string | null;
}

interface ProjectAnnouncement {
  id: number;
  title: string;
  summary: string | null;
  location: string | null;
  status: ProjectStatus;
  current_stage: string | null;
  progress_percent: number;
  starts_at: string | null;
  ends_at: string | null;
  published_at: string | null;
  last_update_at: string | null;
  subscriber_count: number;
  is_subscribed?: boolean;
  updates?: ProjectUpdate[];
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

export default function ProjectAnnouncementsPage() {
  const { id } = useParams<{ id?: string }>();
  const { t } = useTranslation('project_announcements');
  const { isAuthenticated } = useAuth();
  const { tenantPath } = useTenant();
  usePageTitle(t('meta.title'));

  const [projects, setProjects] = useState<ProjectAnnouncement[]>([]);
  const [project, setProject] = useState<ProjectAnnouncement | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  const isDetail = Boolean(id);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      if (id) {
        const res = await api.get<{ data: ProjectAnnouncement } | ProjectAnnouncement>(
          `/v2/caring-community/projects/${id}`,
        );
        setProject(unwrapData<ProjectAnnouncement>(res.data));
      } else {
        const res = await api.get<{ data: ProjectAnnouncement[] } | ProjectAnnouncement[]>(
          '/v2/caring-community/projects',
        );
        setProjects(unwrapData<ProjectAnnouncement[]>(res.data));
      }
    } catch (err: unknown) {
      logError('ProjectAnnouncementsPage.load', err);
      setError(err instanceof Error ? err.message : t('errors.load'));
    } finally {
      setLoading(false);
    }
  }, [id, t]);

  useEffect(() => {
    void load();
  }, [load]);

  const toggleSubscription = async () => {
    if (!project) return;
    setSubmitting(true);
    try {
      if (project.is_subscribed) {
        await api.delete(`/v2/caring-community/projects/${project.id}/subscribe`);
      } else {
        await api.post(`/v2/caring-community/projects/${project.id}/subscribe`);
      }
      await load();
    } catch (err: unknown) {
      logError('ProjectAnnouncementsPage.toggleSubscription', err);
      setError(err instanceof Error ? err.message : t('errors.subscribe'));
    } finally {
      setSubmitting(false);
    }
  };

  const renderProjectCard = (item: ProjectAnnouncement) => (
    <Link key={item.id} to={tenantPath(`/caring-community/projects/${item.id}`)} className="group">
      <GlassCard className="h-full p-5 transition-transform group-hover:-translate-y-0.5">
        <div className="flex flex-col gap-4">
          <div className="flex items-start justify-between gap-3">
            <div className="min-w-0">
              <h2 className="text-lg font-semibold text-theme-primary">{item.title}</h2>
              {item.summary && (
                <p className="mt-2 line-clamp-2 text-sm leading-6 text-theme-muted">{item.summary}</p>
              )}
            </div>
            <Chip size="sm" color={statusColor(item.status)} variant="flat">
              {t(`status.${item.status}`)}
            </Chip>
          </div>

          <Progress
            aria-label={t('progress_label')}
            value={item.progress_percent}
            color={item.status === 'completed' ? 'success' : 'primary'}
            size="sm"
          />

          <div className="grid gap-2 text-sm text-theme-muted sm:grid-cols-2">
            {item.current_stage && (
              <span className="inline-flex items-center gap-2">
                <Flag className="h-4 w-4" aria-hidden="true" />
                {item.current_stage}
              </span>
            )}
            {item.location && (
              <span className="inline-flex items-center gap-2">
                <MapPin className="h-4 w-4" aria-hidden="true" />
                {item.location}
              </span>
            )}
            <span className="inline-flex items-center gap-2">
              <Users className="h-4 w-4" aria-hidden="true" />
              {t('subscriber_count', { count: item.subscriber_count })}
            </span>
            <span className="inline-flex items-center gap-2">
              <CalendarDays className="h-4 w-4" aria-hidden="true" />
              {formatDate(item.last_update_at ?? item.published_at, t('date_unknown'))}
            </span>
          </div>
        </div>
      </GlassCard>
    </Link>
  );

  if (loading) {
    return (
      <div className="flex justify-center py-16">
        <Spinner size="lg" />
      </div>
    );
  }

  if (isDetail && project) {
    return (
      <>
        <PageMeta title={project.title} description={project.summary ?? t('meta.description')} noIndex />
        <div className="mx-auto flex max-w-4xl flex-col gap-6 px-4 py-8">
          <Link to={tenantPath('/caring-community/projects')} className="w-fit">
            <Button
              variant="light"
              startContent={<ArrowLeft className="h-4 w-4" aria-hidden="true" />}
            >
              {t('back_to_projects')}
            </Button>
          </Link>

          {error && <p className="text-sm text-danger">{error}</p>}

          <GlassCard className="p-6">
            <div className="flex flex-col gap-5">
              <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="min-w-0 flex-1">
                  <Chip color={statusColor(project.status)} variant="flat" size="sm">
                    {t(`status.${project.status}`)}
                  </Chip>
                  <h1 className="mt-3 text-3xl font-bold leading-tight text-theme-primary">
                    {project.title}
                  </h1>
                  {project.summary && (
                    <p className="mt-3 max-w-3xl text-base leading-8 text-theme-muted">
                      {project.summary}
                    </p>
                  )}
                </div>
                {isAuthenticated && (
                  <Button
                    color={project.is_subscribed ? 'default' : 'primary'}
                    variant={project.is_subscribed ? 'flat' : 'solid'}
                    isLoading={submitting}
                    startContent={project.is_subscribed
                      ? <BellOff className="h-4 w-4" aria-hidden="true" />
                      : <Bell className="h-4 w-4" aria-hidden="true" />}
                    onPress={() => void toggleSubscription()}
                  >
                    {project.is_subscribed ? t('unsubscribe') : t('subscribe')}
                  </Button>
                )}
              </div>

              <Progress
                aria-label={t('progress_label')}
                value={project.progress_percent}
                color={project.status === 'completed' ? 'success' : 'primary'}
              />

              <div className="grid gap-3 text-sm text-theme-muted sm:grid-cols-2">
                {project.current_stage && (
                  <span className="inline-flex items-center gap-2">
                    <Flag className="h-4 w-4" aria-hidden="true" />
                    {project.current_stage}
                  </span>
                )}
                {project.location && (
                  <span className="inline-flex items-center gap-2">
                    <MapPin className="h-4 w-4" aria-hidden="true" />
                    {project.location}
                  </span>
                )}
                <span className="inline-flex items-center gap-2">
                  <Users className="h-4 w-4" aria-hidden="true" />
                  {t('subscriber_count', { count: project.subscriber_count })}
                </span>
                <span className="inline-flex items-center gap-2">
                  <CalendarDays className="h-4 w-4" aria-hidden="true" />
                  {formatDate(project.last_update_at ?? project.published_at, t('date_unknown'))}
                </span>
              </div>
            </div>
          </GlassCard>

          <section className="flex flex-col gap-4">
            <div className="flex items-center gap-2">
              <Milestone className="h-5 w-5 text-primary" aria-hidden="true" />
              <h2 className="text-xl font-semibold text-theme-primary">{t('updates_title')}</h2>
            </div>

            {(project.updates ?? []).length === 0 && (
              <GlassCard className="p-6 text-center text-theme-muted">
                {t('no_updates')}
              </GlassCard>
            )}

            {(project.updates ?? []).map((update) => (
              <GlassCard key={update.id} className="p-5">
                <div className="flex flex-col gap-3">
                  <div className="flex flex-wrap items-center gap-2">
                    {update.is_milestone && (
                      <Chip color="success" size="sm" variant="flat">
                        {t('milestone')}
                      </Chip>
                    )}
                    {update.stage_label && (
                      <Chip color="primary" size="sm" variant="flat">
                        {update.stage_label}
                      </Chip>
                    )}
                    {update.published_at && (
                      <span className="text-xs text-theme-muted">
                        {formatDate(update.published_at, t('date_unknown'))}
                      </span>
                    )}
                  </div>
                  <h3 className="text-lg font-semibold text-theme-primary">{update.title}</h3>
                  {update.body && (
                    <p className="whitespace-pre-line text-sm leading-7 text-theme-muted">{update.body}</p>
                  )}
                  {update.progress_percent !== null && (
                    <Progress
                      aria-label={t('progress_label')}
                      value={update.progress_percent}
                      size="sm"
                    />
                  )}
                </div>
              </GlassCard>
            ))}
          </section>
        </div>
      </>
    );
  }

  return (
    <>
      <PageMeta title={t('meta.title')} description={t('meta.description')} noIndex />
      <div className="mx-auto flex max-w-5xl flex-col gap-6 px-4 py-8">
        <div className="flex items-start gap-3">
          <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-primary/10">
            <Megaphone className="h-5 w-5 text-primary" aria-hidden="true" />
          </div>
          <div>
            <h1 className="text-3xl font-bold text-theme-primary">{t('meta.title')}</h1>
            <p className="mt-2 max-w-2xl text-sm leading-6 text-theme-muted">{t('meta.description')}</p>
          </div>
        </div>

        {error && <p className="text-sm text-danger">{error}</p>}

        {!error && projects.length === 0 && (
          <GlassCard className="p-8 text-center text-theme-muted">{t('empty')}</GlassCard>
        )}

        <div className="grid gap-4 md:grid-cols-2">
          {projects.map(renderProjectCard)}
        </div>
      </div>
    </>
  );
}
