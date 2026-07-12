// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Button } from '@/components/ui/Button';
import { useConfirm } from '@/components/ui/ConfirmDialog';
import { GlassCard } from '@/components/ui/GlassCard';
import { Modal, ModalContent, ModalHeader, ModalBody, ModalFooter } from '@/components/ui/Modal';
import { OverlayActionButton } from '@/components/ui/OverlayActionButton';
import { Spinner } from '@/components/ui/Spinner';
import { Textarea } from '@/components/ui/Textarea';
import { ToggleButton, ToggleButtonGroup } from '@/components/ui/ToggleButtonGroup';
import { useDisclosure } from '@/components/ui/useDisclosure';
/**
 * Group Media Tab
 * Photo/video gallery with grid layout, lightbox modal, upload, and delete.
 */

import React, { useState, useEffect, useCallback, useRef } from 'react';

import Camera from 'lucide-react/icons/camera';
import Film from 'lucide-react/icons/film';
import Upload from 'lucide-react/icons/upload';
import Trash2 from 'lucide-react/icons/trash-2';
import X from 'lucide-react/icons/x';
import ChevronLeft from 'lucide-react/icons/chevron-left';
import ChevronRight from 'lucide-react/icons/chevron-right';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/feedback';
import { useToast } from '@/contexts';
import { logError } from '@/lib/logger';
import { formatRelativeTime } from '@/lib/helpers';
import {
  deleteGroupMedia,
  getGroupMediaBlob,
  listGroupMedia,
  uploadGroupMedia,
  type GroupMediaItem,
  type GroupMediaType,
} from '../api/media';
import { GroupApiError } from '../api/core';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface GroupMediaTabProps {
  groupId: number;
  isAdmin: boolean;
  isMember?: boolean;
}

// ─────────────────────────────────────────────────────────────────────────────
// Filter chip config
// ─────────────────────────────────────────────────────────────────────────────

const FILTER_CHIPS: { key: GroupMediaType; labelKey: string; icon?: typeof Camera }[] = [
  { key: 'all', labelKey: 'media.filter_all' },
  { key: 'image', labelKey: 'media.filter_photos', icon: Camera },
  { key: 'video', labelKey: 'media.filter_videos', icon: Film },
];

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function GroupMediaTab({ groupId, isMember = true }: GroupMediaTabProps) {
  const { t } = useTranslation(['groups', 'common']);
  const confirm = useConfirm();
  const toast = useToast();
  const lightbox = useDisclosure();
  const uploadModal = useDisclosure();
  const fileInputRef = useRef<HTMLInputElement>(null);

  const [items, setItems] = useState<GroupMediaItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [loadFailed, setLoadFailed] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [uploadCaption, setUploadCaption] = useState('');
  const [resolvedUrls, setResolvedUrls] = useState<Record<number, { url: string | null; thumbnail: string | null }>>({});
  const [cursor, setCursor] = useState<string | null>(null);
  const [hasMore, setHasMore] = useState(false);
  const [filter, setFilter] = useState<GroupMediaType>('all');
  const [lightboxIndex, setLightboxIndex] = useState(0);
  const [deleting, setDeleting] = useState<number | null>(null);
  const mediaControllerRef = useRef<AbortController | null>(null);
  const mediaSequenceRef = useRef(0);

  // ───────────────────────────────────────────────────────────────────────
  // Data loading
  // ───────────────────────────────────────────────────────────────────────

  const loadMedia = useCallback(async ({
    reset,
    requestedCursor = null,
    type,
  }: {
    reset: boolean;
    requestedCursor?: string | null;
    type: GroupMediaType;
  }) => {
    mediaControllerRef.current?.abort();
    const controller = new AbortController();
    const requestId = ++mediaSequenceRef.current;
    mediaControllerRef.current = controller;
    setLoading(true);
    setLoadFailed(false);

    try {
      const page = await listGroupMedia(groupId, {
        cursor: reset ? null : requestedCursor,
        type,
        signal: controller.signal,
      });
      if (controller.signal.aborted || requestId !== mediaSequenceRef.current) return;

      setItems((previous) => reset ? page.items : [...previous, ...page.items]);
      setCursor(page.cursor);
      setHasMore(page.hasMore);
    } catch (err) {
      if (err instanceof GroupApiError && err.isCancellation) return;
      logError('GroupMediaTab.loadMedia', err);
      if (!controller.signal.aborted && requestId === mediaSequenceRef.current) {
        setLoadFailed(true);
      }
    } finally {
      if (!controller.signal.aborted && requestId === mediaSequenceRef.current) {
        setLoading(false);
      }
    }
  }, [groupId]);

  useEffect(() => {
    void loadMedia({ reset: true, type: filter });
    return () => mediaControllerRef.current?.abort();
  }, [filter, groupId, loadMedia]);

  useEffect(() => {
    let cancelled = false;
    const createdUrls: string[] = [];

    const resolveUrl = async (url: string | null): Promise<string | null> => {
      if (!url) return null;
      if (!url.startsWith('/api/')) return url;
      try {
        const blob = await getGroupMediaBlob(url);
        const objectUrl = URL.createObjectURL(blob);
        createdUrls.push(objectUrl);
        return objectUrl;
      } catch (error) {
        logError('GroupMediaTab.loadProtectedMedia', error);
        return null;
      }
    };

    void Promise.all(items.map(async (item) => [item.id, {
      url: await resolveUrl(item.url),
      thumbnail: await resolveUrl(item.thumbnail_url),
    }] as const)).then((entries) => {
      if (!cancelled) setResolvedUrls(Object.fromEntries(entries));
    });

    return () => {
      cancelled = true;
      for (const url of createdUrls) URL.revokeObjectURL(url);
    };
  }, [items]);

  // ───────────────────────────────────────────────────────────────────────
  // Upload
  // ───────────────────────────────────────────────────────────────────────

  const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    // Reset file input so the same file can be re-selected
    e.target.value = '';

    const allowedImages = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    const allowedVideos = ['video/mp4', 'video/webm', 'video/quicktime'];
    const isImage = allowedImages.includes(file.type);
    const isVideo = allowedVideos.includes(file.type);

    if (!isImage && !isVideo) {
      toast.error(t('media.invalid_type'));
      return;
    }

    const maxSize = 50 * 1024 * 1024;
    if (file.size > maxSize) {
      toast.error(
        t('media.too_large', {
          limit: 50,
        }),
      );
      return;
    }

    setSelectedFile(file);
    setUploadCaption('');
    uploadModal.onOpen();
  };

  const closeUploadModal = (force = false) => {
    if (uploading && !force) return;
    setSelectedFile(null);
    setUploadCaption('');
    uploadModal.onClose();
  };

  const handleUpload = async () => {
    if (!selectedFile) return;
    setUploading(true);
    try {
      await uploadGroupMedia(groupId, selectedFile, uploadCaption);

      toast.success(t('media.upload_success'));
      closeUploadModal(true);
      void loadMedia({ reset: true, type: filter });
    } catch (err) {
      if (err instanceof GroupApiError && err.isCancellation) return;
      logError('GroupMediaTab.upload', err);
      toast.error(t('media.upload_error'));
    } finally {
      setUploading(false);
    }
  };

  // ───────────────────────────────────────────────────────────────────────
  // Delete
  // ───────────────────────────────────────────────────────────────────────

  const handleDelete = async (mediaId: number, e?: React.MouseEvent) => {
    e?.stopPropagation();
    const ok = await confirm({
      title: t('media.delete_confirm'),
      status: 'danger',
      confirmLabel: t('common:delete'),
    });
    if (!ok) return;
    setDeleting(mediaId);
    try {
      await deleteGroupMedia(groupId, mediaId);
      setItems((prev) => prev.filter((m) => m.id !== mediaId));
      toast.success(t('media.delete_success'));

      // If lightbox is open and the deleted item was shown, close it
      if (lightbox.isOpen) {
        const newItems = items.filter((m) => m.id !== mediaId);
        if (newItems.length === 0) {
          lightbox.onClose();
        } else if (lightboxIndex >= newItems.length) {
          setLightboxIndex(newItems.length - 1);
        }
      }
    } catch (err) {
      if (err instanceof GroupApiError && err.isCancellation) return;
      logError('GroupMediaTab.delete', err);
      toast.error(t('media.delete_error'));
    } finally {
      setDeleting(null);
    }
  };

  // ───────────────────────────────────────────────────────────────────────
  // Lightbox navigation
  // ───────────────────────────────────────────────────────────────────────

  const openLightbox = (index: number) => {
    setLightboxIndex(index);
    lightbox.onOpen();
  };

  const navigateLightbox = (direction: -1 | 1) => {
    setLightboxIndex((prev) => {
      const next = prev + direction;
      if (next < 0) return items.length - 1;
      if (next >= items.length) return 0;
      return next;
    });
  };

  const currentItem = items[lightboxIndex] ?? null;
  const canDelete = (item: GroupMediaItem) => item.capabilities.can_delete;

  // ───────────────────────────────────────────────────────────────────────
  // Render
  // ───────────────────────────────────────────────────────────────────────

  if (loading && items.length === 0) {
    return (
      <div
        role="status"
        className="flex justify-center py-12"
        aria-label={t('media.loading')}
        aria-busy="true"
      >
        <Spinner size="lg" />
      </div>
    );
  }

  if (loadFailed) {
    return (
      <GlassCard className="p-6">
        <div role="alert" className="flex flex-col items-center gap-3 text-center">
          <p className="text-sm text-danger">{t('common:error_title')}</p>
          <Button
            variant="flat"
            onPress={() => void loadMedia({ reset: true, type: filter })}
          >
            {t('try_again')}
          </Button>
        </div>
      </GlassCard>
    );
  }

  return (
    <div className="space-y-4">
      {/* Toolbar: filter chips + upload */}
      <div className="flex flex-col gap-3 rounded-xl border border-border bg-surface/80 p-3 shadow-sm sm:flex-row sm:items-center">
        <ToggleButtonGroup
          aria-label={t('media.filter_group')}
          selectionMode="single"
          disallowEmptySelection
          isDetached
          size="sm"
          selectedKeys={new Set([filter])}
          onSelectionChange={(keys) => { const [k] = Array.from(keys); if (k) setFilter(k as typeof filter); }}
          className="flex flex-wrap gap-2 flex-1"
        >
          {FILTER_CHIPS.map((chip) => (
            <ToggleButton
              key={chip.key}
              id={chip.key}
              variant="ghost"
              className="data-[selected=true]:bg-[var(--color-primary)] data-[selected=true]:text-white"
            >
              {chip.icon && (
                <chip.icon className="w-3 h-3" aria-hidden="true" />
              )}
              {t(chip.labelKey)}
            </ToggleButton>
          ))}
        </ToggleButtonGroup>

        {isMember && (
          <Button
            color="primary"
            size="sm"
            startContent={
              uploading ? (
                <div role="status" aria-busy="true" aria-label={t('common:loading')} className="flex justify-center py-4"><Spinner size="sm" color="current" /></div>
              ) : (
                <Upload className="w-4 h-4" aria-hidden="true" />
              )
            }
            onPress={() => fileInputRef.current?.click()}
            isDisabled={uploading}
            aria-label={t('media.upload_aria')}
          >
            {uploading
              ? t('media.uploading')
              : t('media.upload')}
          </Button>
        )}

        <input
          ref={fileInputRef}
          type="file"
          accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm,video/quicktime"
          className="hidden"
          onChange={handleFileSelect}
        />
      </div>

      {/* Gallery grid */}
      {items.length === 0 ? (
        <EmptyState
          icon={<Camera className="w-10 h-10 text-muted" />}
          title={t('media.empty_title')}
          description={
            filter !== 'all'
              ? t('media.no_results', {
                  type: filter === 'image' ? t('media.photos') : t('media.videos'),
                })
              : t('media.empty_description')
          }
        />
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
          {items.map((item, index) => {
            const resolved = resolvedUrls[item.id];
            const mediaUrl = resolved?.url ?? (item.url.startsWith('/api/') ? null : item.url);
            const thumbnailUrl = resolved?.thumbnail
              ?? (item.thumbnail_url?.startsWith('/api/') ? null : item.thumbnail_url)
              ?? (item.type === 'image' ? mediaUrl : null);
            return (
            <GlassCard
              key={item.id}
              className="relative group overflow-hidden border border-border bg-surface shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md"
            >
              <button type="button" className="block w-full text-left" onClick={() => openLightbox(index)} aria-label={item.caption || (item.type === 'video' ? t('media.video_player') : t('media.photo_alt'))}>
              <div className="aspect-square relative">
                {item.type === 'video' ? (
                  <div className="w-full h-full bg-surface-secondary flex items-center justify-center">
                    {thumbnailUrl ? (
                      <img
                        src={thumbnailUrl}
                        alt={item.caption || t('media.video_thumbnail')}
                        className="w-full h-full object-cover"
                      />
                    ) : (
                      <Film className="w-12 h-12 text-muted" aria-hidden="true" />
                    )}
                    {/* Video badge */}
                    <div className="absolute bottom-2 left-2 bg-black/70 text-white text-xs px-2 py-0.5 rounded-full flex items-center gap-1">
                      <Film className="w-3 h-3" aria-hidden="true" />
                      {t('media.video_badge')}
                    </div>
                  </div>
                ) : (
                  thumbnailUrl ? <img src={thumbnailUrl} alt={item.caption || t('media.photo_alt')} className="w-full h-full object-cover" /> : <Spinner size="sm" />
                )}

                {/* Hover overlay with caption + uploader */}
                <div
                  className="absolute inset-0 bg-black/0 group-hover:bg-black/50 transition-all duration-200 flex flex-col justify-end p-3 opacity-0 group-hover:opacity-100"
                  aria-hidden="true"
                >
                  {item.caption && (
                    <p className="text-white text-sm font-medium line-clamp-2 mb-1">
                      {item.caption}
                    </p>
                  )}
                  <p className="text-white/80 text-xs">
                    {item.uploader_name}
                  </p>
                </div>

                {/* Persistently discoverable on touch; hover/focus reveal on fine pointers. */}
              </div>
              </button>
                {canDelete(item) && (
                  <OverlayActionButton
                    variant="danger"
                    className="absolute top-2 right-2 rounded-full bg-danger/80 text-white transition-opacity duration-200 [--button-bg-hover:var(--danger)]"
                    onPress={() => handleDelete(item.id)}
                    isDisabled={deleting === item.id}
                    aria-label={t('media.delete_aria')}
                  >
                    {deleting === item.id ? (
                      <span role="status" aria-busy="true" aria-label={t('common:loading')} className="flex items-center justify-center">
                        <Spinner size="sm" color="current" />
                      </span>
                    ) : (
                      <Trash2 className="size-4" aria-hidden="true" />
                    )}
                  </OverlayActionButton>
                )}
            </GlassCard>
            );
          })}
        </div>
      )}

      {/* Load more */}
      {hasMore && (
        <div className="flex justify-center pt-4">
          <Button
            variant="flat"
            size="sm"
            onPress={() => void loadMedia({
              reset: false,
              requestedCursor: cursor,
              type: filter,
            })}
            isLoading={loading}
          >
            {t('media.load_more')}
          </Button>
        </div>
      )}

      {/* Lightbox modal */}
      <Modal
        isOpen={lightbox.isOpen}
        onClose={lightbox.onClose}
        size="5xl"
        classNames={{
          backdrop: 'bg-black/80',
          base: 'bg-transparent shadow-none',
          body: 'p-0',
          closeButton: 'text-white hover:bg-white/20 z-50',
        }}
        hideCloseButton
      >
        <ModalContent
          aria-label={currentItem?.type === 'video'
            ? t('media.video_player')
            : t('media.fullsize_alt')}
        >
          {currentItem && (
            <ModalBody>
              <div className="relative flex flex-col items-center">
                {/* Close button */}
                <Button
                  isIconOnly
                  variant="ghost"
                  className="absolute top-2 right-2 z-50 size-11 min-h-11 min-w-11 rounded-full bg-black/50 hover:bg-black/70 text-white transition-colors p-0"
                  onPress={lightbox.onClose}
                  aria-label={t('media.close_lightbox')}
                >
                  <X className="size-5" aria-hidden="true" />
                </Button>

                {/* Navigation: previous */}
                {items.length > 1 && (
                  <Button
                    isIconOnly
                    variant="ghost"
                    className="absolute left-2 top-1/2 -translate-y-1/2 z-50 size-11 min-h-11 min-w-11 rounded-full bg-black/50 hover:bg-black/70 text-white transition-colors p-0"
                    onPress={() => navigateLightbox(-1)}
                    aria-label={t('media.prev')}
                  >
                    <ChevronLeft className="size-6" aria-hidden="true" />
                  </Button>
                )}

                {/* Navigation: next */}
                {items.length > 1 && (
                  <Button
                    isIconOnly
                    variant="ghost"
                    className="absolute right-2 top-1/2 -translate-y-1/2 z-50 size-11 min-h-11 min-w-11 rounded-full bg-black/50 hover:bg-black/70 text-white transition-colors p-0"
                    onPress={() => navigateLightbox(1)}
                    aria-label={t('media.next')}
                  >
                    <ChevronRight className="size-6" aria-hidden="true" />
                  </Button>
                )}

                {/* Media content */}
                <div className="max-h-[80vh] flex items-center justify-center w-full">
                  {currentItem.type === 'video' ? (
                    <video
                      src={resolvedUrls[currentItem.id]?.url ?? undefined}
                      poster={resolvedUrls[currentItem.id]?.thumbnail ?? undefined}
                      controls
                      className="max-h-[80vh] max-w-full rounded-lg"
                      aria-label={currentItem.caption || t('media.video_player')}
                    />
                  ) : (
                    <img
                      src={resolvedUrls[currentItem.id]?.url ?? undefined}
                      alt={currentItem.caption || t('media.fullsize_alt')}
                      className="max-h-[80vh] max-w-full object-contain rounded-lg"
                    />
                  )}
                </div>

                {/* Caption, uploader, date */}
                <div className="w-full mt-4 px-4 pb-4 text-center">
                  {currentItem.caption && (
                    <p className="text-white text-base font-medium mb-1">
                      {currentItem.caption}
                    </p>
                  )}
                  <p className="text-white/70 text-sm">
                    {currentItem.uploader_name}
                    <span className="mx-2" aria-hidden="true">&#183;</span>
                    {formatRelativeTime(currentItem.created_at)}
                  </p>

                  {/* Delete in lightbox */}
                  {canDelete(currentItem) && (
                    <Button
                      variant="danger-soft"
                      size="sm"
                      className="mt-3 min-h-11"
                      startContent={<Trash2 className="w-4 h-4" aria-hidden="true" />}
                      onPress={() => handleDelete(currentItem.id)}
                      isLoading={deleting === currentItem.id}
                      aria-label={t('media.delete_aria')}
                    >
                      {t('media.delete')}
                    </Button>
                  )}
                </div>
              </div>
            </ModalBody>
          )}
        </ModalContent>
      </Modal>

      <Modal isOpen={uploadModal.isOpen} onOpenChange={(open) => { if (!open) closeUploadModal(); }} size="md">
        <ModalContent aria-label={t('media.upload_title')}>
          <ModalHeader>{t('media.upload_title')}</ModalHeader>
          <ModalBody className="gap-4">
            {selectedFile && (
              <p className="truncate text-sm text-theme-primary">{selectedFile.name}</p>
            )}
            <Textarea
              label={t('media.caption_label')}
              placeholder={t('media.caption_placeholder')}
              value={uploadCaption}
              onValueChange={setUploadCaption}
              maxLength={2000}
              minRows={3}
            />
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={() => closeUploadModal()} isDisabled={uploading}>{t('files.cancel')}</Button>
            <Button color="primary" onPress={handleUpload} isLoading={uploading} isDisabled={!selectedFile}>{t('media.upload')}</Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default GroupMediaTab;
