// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router-dom';
import { Button, Card, CardBody, Checkbox, Chip, Input, Progress, Select, SelectItem, Spinner, Textarea, useConfirm } from '@/components/ui';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import {
  podcastsApi,
  type CreatePodcastEpisodePayload,
  type CreatePodcastShowPayload,
  type PodcastChapter,
  type PodcastEpisode,
  type PodcastShow,
  type PodcastVisibility,
} from '@/lib/api/podcasts';
import Archive from 'lucide-react/icons/archive';
import ArrowLeft from 'lucide-react/icons/arrow-left';
import CheckCircle from 'lucide-react/icons/circle-check-big';
import Megaphone from 'lucide-react/icons/megaphone';
import Plus from 'lucide-react/icons/plus';
import Radio from 'lucide-react/icons/radio';
import Trash2 from 'lucide-react/icons/trash-2';
import XCircle from 'lucide-react/icons/circle-x';

function parseTimestamp(value: string): number {
  const parts = value.split(':').map((part) => Number.parseInt(part, 10));
  if (parts.some(Number.isNaN)) return 0;
  const [first = 0, second = 0, third = 0] = parts;
  if (parts.length === 3) return (first * 3600) + (second * 60) + third;
  if (parts.length === 2) return (first * 60) + second;
  return first;
}

function looksLikeTimestamp(token: string): boolean {
  return /^(?:\d+:)?\d{1,2}:\d{2}$/.test(token) || /^\d+$/.test(token);
}

function parseChapters(input: string): PodcastChapter[] {
  return input
    .split('\n')
    .map((line) => line.trim())
    .filter(Boolean)
    .map((line, index) => {
      const [timestamp = '', ...titleParts] = line.split(/\s+/);
      const hasTimestamp = looksLikeTimestamp(timestamp);
      const title = titleParts.join(' ').trim();
      return {
        title: hasTimestamp ? (title || line) : line,
        starts_at_seconds: hasTimestamp ? parseTimestamp(timestamp) : 0,
        position: index,
      };
    });
}

function countInvalidChapterLines(input: string): number {
  return input
    .split('\n')
    .map((line) => line.trim())
    .filter(Boolean)
    .filter((line) => !looksLikeTimestamp(line.split(/\s+/)[0] ?? '')).length;
}

function mediaStatusColor(status?: string | null): 'success' | 'warning' | 'danger' | 'default' {
  if (!status || status === 'not_required') return 'default';
  if (status === 'complete' || status === 'clean') return 'success';
  if (status === 'failed' || status === 'blocked' || status === 'infected') return 'danger';
  return 'warning';
}

const emptyShow: CreatePodcastShowPayload = {
  title: '',
  summary: '',
  description: '',
  artwork_url: '',
  language: 'en',
  category: '',
  author_name: '',
  owner_email: '',
  copyright: '',
  funding_url: '',
  explicit: false,
  visibility: 'public',
};

const emptyEpisode: CreatePodcastEpisodePayload = {
  title: '',
  summary: '',
  description: '',
  audio_url: '',
  duration_seconds: undefined,
  episode_type: 'full',
  visibility: 'inherit',
  transcript: '',
};

export default function PodcastStudioPage() {
  const { t } = useTranslation('podcasts');
  usePageTitle(t('studio.title'));
  const { tenantPath } = useTenant();
  const toast = useToast();
  const confirm = useConfirm();

  const [shows, setShows] = useState<PodcastShow[]>([]);
  const [loading, setLoading] = useState(true);
  const [savingShow, setSavingShow] = useState(false);
  const [savingEpisode, setSavingEpisode] = useState(false);
  const [selectedShowId, setSelectedShowId] = useState<string>('');
  const [showForm, setShowForm] = useState<CreatePodcastShowPayload>(emptyShow);
  const [episodeForm, setEpisodeForm] = useState<CreatePodcastEpisodePayload>(emptyEpisode);
  const [audioFile, setAudioFile] = useState<File | null>(null);
  const [chaptersText, setChaptersText] = useState('');
  const [uploadProgress, setUploadProgress] = useState<number | null>(null);
  const [episodeError, setEpisodeError] = useState<string | null>(null);

  const chapterIssues = useMemo(() => countInvalidChapterLines(chaptersText), [chaptersText]);

  const selectedShow = useMemo(
    () => shows.find((show) => String(show.id) === selectedShowId) ?? null,
    [shows, selectedShowId],
  );

  const readinessChecks = useMemo(() => {
    if (!selectedShow) return [];

    const hasPublishedEpisode = selectedShow.episodes?.some((episode) => episode.status === 'published' && episode.moderation_status === 'approved') ?? false;

    return [
      { key: 'public_show', ok: selectedShow.visibility === 'public' && selectedShow.status === 'published' && selectedShow.moderation_status === 'approved' },
      { key: 'owner_email', ok: Boolean(selectedShow.owner_email) },
      { key: 'description', ok: Boolean(selectedShow.description || selectedShow.summary) },
      { key: 'artwork', ok: Boolean(selectedShow.artwork_url) },
      { key: 'published_episode', ok: hasPublishedEpisode },
    ];
  }, [selectedShow]);

  async function loadShows(): Promise<void> {
    setLoading(true);
    const res = await podcastsApi.authored();
    if (res.success && res.data) {
      setShows(res.data);
      setSelectedShowId((current) => current || (res.data?.[0] ? String(res.data[0].id) : ''));
    }
    setLoading(false);
  }

  useEffect(() => {
    loadShows();
  }, []);

  async function handleCreateShow(): Promise<void> {
    if (!showForm.title.trim()) return;
    setSavingShow(true);
    const res = await podcastsApi.createShow({
      ...showForm,
      title: showForm.title.trim(),
      visibility: (showForm.visibility ?? 'public') as PodcastVisibility,
    });
    setSavingShow(false);

    if (res.success && res.data) {
      toast.success(t('studio.show_created'));
      setShowForm(emptyShow);
      await loadShows();
      setSelectedShowId(String(res.data.id));
    } else {
      toast.error(t('studio.save_failed'));
    }
  }

  async function handlePublishShow(show: PodcastShow): Promise<void> {
    const res = await podcastsApi.publishShow(show.id);
    if (res.success) {
      toast.success(t('studio.show_published'));
      await loadShows();
    } else {
      toast.error(t('studio.save_failed'));
    }
  }

  async function handlePublishEpisode(showId: number, episodeId: number): Promise<void> {
    const res = await podcastsApi.publishEpisode(showId, episodeId);
    if (res.success) {
      toast.success(t('studio.episode_published'));
      await loadShows();
    } else {
      toast.error(t('studio.save_failed'));
    }
  }

  async function handleArchiveShow(show: PodcastShow): Promise<void> {
    const ok = await confirm({
      title: t('studio.archive_show'),
      body: t('studio.confirm_archive_show', { title: show.title }),
      status: 'warning',
      confirmLabel: t('studio.archive_show'),
    });
    if (!ok) return;
    const res = await podcastsApi.archiveShow(show.id);
    if (res.success) {
      toast.success(t('studio.show_archived'));
      await loadShows();
    } else {
      toast.error(t('studio.save_failed'));
    }
  }

  async function handleDeleteShow(show: PodcastShow): Promise<void> {
    const ok = await confirm({
      title: t('studio.delete_show'),
      body: t('studio.confirm_delete_show', { title: show.title }),
      status: 'danger',
      confirmLabel: t('studio.delete_show'),
    });
    if (!ok) return;
    const res = await podcastsApi.deleteShow(show.id);
    if (res.success) {
      toast.success(t('studio.show_deleted'));
      setSelectedShowId((current) => current === String(show.id) ? '' : current);
      await loadShows();
    } else {
      toast.error(t('studio.save_failed'));
    }
  }

  async function handleArchiveEpisode(showId: number, episodeId: number, title: string): Promise<void> {
    const ok = await confirm({
      title: t('studio.archive_episode'),
      body: t('studio.confirm_archive_episode', { title }),
      status: 'warning',
      confirmLabel: t('studio.archive_episode'),
    });
    if (!ok) return;
    const res = await podcastsApi.archiveEpisode(showId, episodeId);
    if (res.success) {
      toast.success(t('studio.episode_archived'));
      await loadShows();
    } else {
      toast.error(t('studio.save_failed'));
    }
  }

  async function handleDeleteEpisode(showId: number, episodeId: number, title: string): Promise<void> {
    const ok = await confirm({
      title: t('studio.delete_episode'),
      body: t('studio.confirm_delete_episode', { title }),
      status: 'danger',
      confirmLabel: t('studio.delete_episode'),
    });
    if (!ok) return;
    const res = await podcastsApi.deleteEpisode(showId, episodeId);
    if (res.success) {
      toast.success(t('studio.episode_deleted'));
      await loadShows();
    } else {
      toast.error(t('studio.save_failed'));
    }
  }

  async function handleCreateEpisode(): Promise<void> {
    if (!selectedShow || !episodeForm.title.trim() || (!episodeForm.audio_url.trim() && !audioFile)) return;
    setSavingEpisode(true);
    setEpisodeError(null);
    if (audioFile) setUploadProgress(0);
    const res = await podcastsApi.createEpisode(
      selectedShow.id,
      {
        ...episodeForm,
        title: episodeForm.title.trim(),
        // When a hosted file is present, omit audio_url so the upload path drives it.
        audio_url: audioFile ? '' : episodeForm.audio_url.trim(),
        audio_file: audioFile,
        chapters: parseChapters(chaptersText),
      },
      audioFile ? (percent) => setUploadProgress(percent) : undefined,
    );
    setSavingEpisode(false);
    setUploadProgress(null);

    if (res.success) {
      toast.success(t('studio.episode_created'));
      setEpisodeForm(emptyEpisode);
      setAudioFile(null);
      setChaptersText('');
      await loadShows();
    } else {
      // The API returns a specific reason (unsupported type, too large, upload
      // failed, invalid URL) — surface it instead of a generic save error.
      const message = res.error ?? t('studio.save_failed');
      setEpisodeError(message);
      toast.error(message);
    }
  }

  return (
    <div className="mx-auto max-w-7xl px-4 py-6">
      <Button as={Link} to={tenantPath('/podcasts')} variant="tertiary" size="sm" startContent={<ArrowLeft size={16} aria-hidden="true" />}>
        {t('actions.back_to_podcasts')}
      </Button>

      <div className="mt-5 mb-6">
        <h1 className="text-2xl font-bold leading-tight">{t('studio.title')}</h1>
        <p className="mt-1 max-w-2xl text-sm text-muted">{t('studio.subtitle')}</p>
      </div>

      {loading ? (
        <div className="flex justify-center py-16" role="status" aria-busy="true">
          <Spinner size="lg" />
        </div>
      ) : (
        <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(22rem,28rem)]">
          <section className="space-y-4">
            <Card>
              <CardBody className="space-y-4">
                <div className="flex items-center gap-2">
                  <Plus size={18} className="text-accent" aria-hidden="true" />
                  <h2 className="text-lg font-semibold">{t('studio.create_show')}</h2>
                </div>
                <div className="grid gap-3 sm:grid-cols-2">
                  <Input label={t('fields.show_title')} value={showForm.title} onValueChange={(title) => setShowForm((prev) => ({ ...prev, title }))} />
                  <Input label={t('fields.category')} value={showForm.category ?? ''} onValueChange={(category) => setShowForm((prev) => ({ ...prev, category }))} />
                  <Input label={t('fields.artwork_url')} value={showForm.artwork_url ?? ''} onValueChange={(artwork_url) => setShowForm((prev) => ({ ...prev, artwork_url }))} />
                  <Input label={t('fields.author_name')} value={showForm.author_name ?? ''} onValueChange={(author_name) => setShowForm((prev) => ({ ...prev, author_name }))} />
                  <Input label={t('fields.owner_email')} type="email" value={showForm.owner_email ?? ''} onValueChange={(owner_email) => setShowForm((prev) => ({ ...prev, owner_email }))} />
                  <Input label={t('fields.copyright')} value={showForm.copyright ?? ''} onValueChange={(copyright) => setShowForm((prev) => ({ ...prev, copyright }))} />
                  <Input label={t('fields.funding_url')} value={showForm.funding_url ?? ''} onValueChange={(funding_url) => setShowForm((prev) => ({ ...prev, funding_url }))} />
                  <Select
                    label={t('fields.visibility')}
                    selectedKeys={[showForm.visibility ?? 'public']}
                    onSelectionChange={(keys) => setShowForm((prev) => ({ ...prev, visibility: (Array.from(keys)[0] as PodcastVisibility) ?? 'public' }))}
                  >
                    <SelectItem id="public">{t('visibility.public')}</SelectItem>
                    <SelectItem id="members">{t('visibility.members')}</SelectItem>
                    <SelectItem id="private">{t('visibility.private')}</SelectItem>
                  </Select>
                </div>
                <Checkbox isSelected={Boolean(showForm.explicit)} onValueChange={(explicit) => setShowForm((prev) => ({ ...prev, explicit }))}>
                  {t('fields.explicit_show')}
                </Checkbox>
                <Textarea label={t('fields.summary')} value={showForm.summary ?? ''} onValueChange={(summary) => setShowForm((prev) => ({ ...prev, summary }))} />
                <Textarea label={t('fields.description')} value={showForm.description ?? ''} onValueChange={(description) => setShowForm((prev) => ({ ...prev, description }))} />
                <div className="flex justify-end">
                  <Button color="primary" isLoading={savingShow} isDisabled={!showForm.title.trim()} onPress={handleCreateShow}>
                    {t('studio.create_show')}
                  </Button>
                </div>
              </CardBody>
            </Card>

            <Card>
              <CardBody className="space-y-4">
                <div className="flex items-center gap-2">
                  <Radio size={18} className="text-accent" aria-hidden="true" />
                  <h2 className="text-lg font-semibold">{t('studio.add_episode')}</h2>
                </div>
                <Select
                  label={t('fields.show')}
                  selectedKeys={selectedShowId ? [selectedShowId] : []}
                  onSelectionChange={(keys) => setSelectedShowId((Array.from(keys)[0] as string) ?? '')}
                >
                  {shows.map((show) => (
                    <SelectItem key={show.id} id={String(show.id)}>{show.title}</SelectItem>
                  ))}
                </Select>
                <div className="grid gap-3 sm:grid-cols-2">
                  <Input label={t('fields.episode_title')} value={episodeForm.title} onValueChange={(title) => setEpisodeForm((prev) => ({ ...prev, title }))} />
                  <Input label={t('fields.audio_url')} value={episodeForm.audio_url} onValueChange={(audio_url) => setEpisodeForm((prev) => ({ ...prev, audio_url }))} />
                  <div className="sm:col-span-2">
                    <label htmlFor="podcast-audio-file" className="block text-sm font-medium text-foreground">
                      {t('fields.audio_file')}
                    </label>
                    <input
                      id="podcast-audio-file"
                      className="mt-1 block w-full rounded-md border border-border bg-surface px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-surface-secondary file:px-3 file:py-1 file:text-sm file:font-medium file:text-foreground"
                      type="file"
                      accept="audio/*,.mp3,.m4a,.wav,.ogg,.webm"
                      aria-describedby="podcast-audio-file-hint"
                      disabled={savingEpisode}
                      onChange={(event) => setAudioFile(event.currentTarget.files?.[0] ?? null)}
                    />
                    <p id="podcast-audio-file-hint" className="mt-1 text-xs text-muted">{audioFile ? audioFile.name : t('fields.audio_file_hint')}</p>
                  </div>
                  <Input
                    label={t('fields.duration_seconds')}
                    type="number"
                    value={episodeForm.duration_seconds ? String(episodeForm.duration_seconds) : ''}
                    onValueChange={(value) => setEpisodeForm((prev) => {
                      const parsed = Number(value);
                      return { ...prev, duration_seconds: value && Number.isFinite(parsed) ? parsed : undefined };
                    })}
                  />
                  <Input
                    label={t('fields.scheduled_for')}
                    type="datetime-local"
                    value={episodeForm.scheduled_for ?? ''}
                    onValueChange={(scheduled_for) => setEpisodeForm((prev) => ({ ...prev, scheduled_for: scheduled_for || undefined }))}
                  />
                  <Select
                    label={t('fields.episode_type')}
                    selectedKeys={[episodeForm.episode_type ?? 'full']}
                    onSelectionChange={(keys) => setEpisodeForm((prev) => ({ ...prev, episode_type: (Array.from(keys)[0] as CreatePodcastEpisodePayload['episode_type']) ?? 'full' }))}
                  >
                    <SelectItem id="full">{t('episode.type.full')}</SelectItem>
                    <SelectItem id="trailer">{t('episode.type.trailer')}</SelectItem>
                    <SelectItem id="bonus">{t('episode.type.bonus')}</SelectItem>
                  </Select>
                </div>
                <Textarea label={t('fields.summary')} value={episodeForm.summary ?? ''} onValueChange={(summary) => setEpisodeForm((prev) => ({ ...prev, summary }))} />
                <Textarea label={t('fields.description')} value={episodeForm.description ?? ''} onValueChange={(description) => setEpisodeForm((prev) => ({ ...prev, description }))} />
                <Textarea label={t('fields.transcript')} value={episodeForm.transcript ?? ''} onValueChange={(transcript) => setEpisodeForm((prev) => ({ ...prev, transcript }))} />
                <Textarea label={t('fields.chapters')} description={t('fields.chapters_hint')} value={chaptersText} onValueChange={setChaptersText} />
                {chapterIssues > 0 && (
                  <p className="text-xs text-warning">{t('studio.chapter_format_warning', { count: chapterIssues })}</p>
                )}
                {uploadProgress !== null && (
                  <div className="space-y-1">
                    <div className="flex items-center justify-between text-xs text-muted">
                      <span>{t('studio.uploading')}</span>
                      <span className="tabular-nums">{uploadProgress}%</span>
                    </div>
                    <Progress aria-label={t('studio.uploading')} value={uploadProgress} />
                  </div>
                )}
                {episodeError && (
                  <div role="alert" className="flex items-center gap-2 rounded-lg border border-danger/30 bg-danger/10 px-3 py-2 text-sm text-danger">
                    <XCircle size={16} aria-hidden="true" />
                    <span>{episodeError}</span>
                  </div>
                )}
                <div className="flex justify-end">
                  <Button color="primary" isLoading={savingEpisode} isDisabled={!selectedShow || !episodeForm.title.trim() || (!episodeForm.audio_url.trim() && !audioFile)} onPress={handleCreateEpisode}>
                    {t('studio.add_episode')}
                  </Button>
                </div>
              </CardBody>
            </Card>
          </section>

          <aside className="space-y-3">
            {selectedShow && (
              <Card>
                <CardBody className="space-y-3">
                  <div>
                    <h2 className="text-lg font-semibold">{t('studio.readiness_title')}</h2>
                    <p className="mt-1 text-sm text-muted">{t('studio.readiness_subtitle', { title: selectedShow.title })}</p>
                  </div>
                  <div className="space-y-2">
                    {readinessChecks.map((check) => {
                      const Icon = check.ok ? CheckCircle : XCircle;
                      return (
                        <div key={check.key} className="flex items-start gap-2 text-sm">
                          <Icon size={16} className={check.ok ? 'mt-0.5 text-success' : 'mt-0.5 text-warning'} aria-hidden="true" />
                          <span className={check.ok ? 'text-foreground' : 'text-muted'}>
                            {t(`studio.readiness.${check.key}`)}
                          </span>
                        </div>
                      );
                    })}
                  </div>
                </CardBody>
              </Card>
            )}

            <div className="flex items-center gap-2">
              <Megaphone size={18} className="text-accent" aria-hidden="true" />
              <h2 className="text-lg font-semibold">{t('studio.my_shows')}</h2>
            </div>
            {shows.length === 0 ? (
              <div className="rounded-lg border border-border bg-surface-secondary/60 px-4 py-8 text-center text-sm text-muted">
                {t('studio.no_shows')}
              </div>
            ) : (
              shows.map((show) => (
                <Card key={show.id}>
                  <CardBody className="space-y-3">
                    <div>
                      <Link className="font-semibold hover:text-accent" to={tenantPath(`/podcasts/${show.slug}`)}>
                        {show.title}
                      </Link>
                      <p className="mt-1 line-clamp-2 text-sm text-muted">{show.summary || t('show.no_summary')}</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                      <Chip size="sm" variant="soft">{t(`status.${show.status}`)}</Chip>
                      <Chip size="sm" variant="soft" color={show.moderation_status === 'approved' ? 'success' : 'warning'}>
                        {t(`moderation.${show.moderation_status}`)}
                      </Chip>
                    </div>
                    <div className="flex flex-wrap gap-2">
                      <Button size="sm" variant="tertiary" onPress={() => setSelectedShowId(String(show.id))}>
                        {t('studio.select_show')}
                      </Button>
                      {show.status === 'draft' && (
                        <Button size="sm" color="primary" onPress={() => handlePublishShow(show)}>
                          {t('studio.publish_show')}
                        </Button>
                      )}
                      {show.status !== 'archived' && (
                        <Button size="sm" variant="tertiary" startContent={<Archive size={14} aria-hidden="true" />} onPress={() => handleArchiveShow(show)}>
                          {t('studio.archive_show')}
                        </Button>
                      )}
                      <Button size="sm" variant="tertiary" color="danger" startContent={<Trash2 size={14} aria-hidden="true" />} onPress={() => handleDeleteShow(show)}>
                        {t('studio.delete_show')}
                      </Button>
                    </div>
                    {show.episodes && show.episodes.length > 0 && (
                      <div className="space-y-2 border-t border-border pt-3">
                        <h3 className="text-sm font-semibold">{t('show.episodes')}</h3>
                        {show.episodes.map((episode) => (
                          <div key={episode.id} className="flex flex-col gap-2 rounded-md bg-surface-secondary/60 px-3 py-2 sm:flex-row sm:items-center sm:justify-between">
                            <div className="min-w-0">
                              <p className="truncate text-sm font-medium">{episode.title}</p>
                              <div className="mt-1 flex flex-wrap gap-1">
                                <Chip size="sm" variant="soft">{t(`status.${episode.status}`)}</Chip>
                                <Chip size="sm" variant="soft" color={episode.moderation_status === 'approved' ? 'success' : 'warning'}>
                                  {t(`moderation.${episode.moderation_status}`)}
                                </Chip>
                                {(['media_scan_status', 'media_processing_status'] as const).map((field) => {
                                  const status = episode[field as keyof PodcastEpisode] as string | null | undefined;
                                  if (!status) return null;

                                  return (
                                    <Chip key={field} size="sm" variant="soft" color={mediaStatusColor(status)}>
                                      {t(`studio.${field}`, { status: t(`studio.media_status.${status}`, { defaultValue: status }) })}
                                    </Chip>
                                  );
                                })}
                              </div>
                            </div>
                            <div className="flex flex-wrap gap-2">
                              {episode.status === 'draft' && (
                                <Button size="sm" color="primary" onPress={() => handlePublishEpisode(show.id, episode.id)}>
                                  {t('studio.publish_episode')}
                                </Button>
                              )}
                              {episode.status !== 'archived' && (
                                <Button size="sm" variant="tertiary" startContent={<Archive size={14} aria-hidden="true" />} onPress={() => handleArchiveEpisode(show.id, episode.id, episode.title)}>
                                  {t('studio.archive_episode')}
                                </Button>
                              )}
                              <Button size="sm" variant="tertiary" color="danger" startContent={<Trash2 size={14} aria-hidden="true" />} onPress={() => handleDeleteEpisode(show.id, episode.id, episode.title)}>
                                {t('studio.delete_episode')}
                              </Button>
                            </div>
                          </div>
                        ))}
                      </div>
                    )}
                  </CardBody>
                </Card>
              ))
            )}
          </aside>
        </div>
      )}
    </div>
  );
}
