// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Group Media Tab
 * Photo/video gallery with grid layout, lightbox modal, upload, and delete.
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import {
  Button,
  Spinner,
  Modal,
  ModalContent,
  ModalBody,
  Chip,
  useDisclosure,
  Image,
} from '@heroui/react';
import {
  Camera,
  Film,
  Upload,
  Trash2,
  X,
  ChevronLeft,
  ChevronRight,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { formatRelativeTime } from '@/lib/helpers';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

type MediaType = 'all' | 'image' | 'video';

interface MediaItem {
  id: number;
  group_id: number;
  type: 'image' | 'video';
  url: string;
  thumbnail_url: string | null;
  caption: string | null;
  uploaded_by: number;
  uploader_name: string;
  uploader_avatar: string | null;
  created_at: string;
}

interface GroupMediaTabProps {
  groupId: number;
  isAdmin: boolean;
  isMember?: boolean;
}

// ─────────────────────────────────────────────────────────────────────────────
// Filter chip config
// ─────────────────────────────────────────────────────────────────────────────

const FILTER_CHIPS: { key: MediaType; labelKey: string; fallback: string; icon?: typeof Camera }[] = [
  { key: 'all', labelKey: 'media.filter_all', fallback: 'All' },
  { key: 'image', labelKey: 'media.filter_photos', fallback: 'Photos', icon: Camera },
  { key: 'video', labelKey: 'media.filter_videos', fallback: 'Videos', icon: Film },
];

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function GroupMediaTab({ groupId, isAdmin, isMember = true }: GroupMediaTabProps) {
  const { t } = useTranslation('groups');
  const toast = useToast();
  const lightbox = useDisclosure();
  const fileInputRef = useRef<HTMLInputElement>(null);

  const [items, setItems] = useState<MediaItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [uploading, setUploading] = useState(false);
  const [cursor, setCursor] = useState<string | null>(null);
  const [hasMore, setHasMore] = useState(false);
  const [filter, setFilter] = useState<MediaType>('all');
  const [lightboxIndex, setLightboxIndex] = useState(0);
  const [deleting, setDeleting] = useState<number | null>(null);

  // ───────────────────────────────────────────────────────────────────────
  // Data loading
  // ───────────────────────────────────────────────────────────────────────

  const loadMedia = useCallback(
    async (reset = false) => {
      try {
        if (reset) setLoading(true);

        const params = new URLSearchParams({ per_page: '20' });
        if (!reset && cursor) params.set('cursor', cursor);
        if (filter !== 'all') params.set('type', filter);

        const resp = await api.get(`/v2/groups/${groupId}/media?${params}`);
        const data = (resp.data ?? {}) as { items?: MediaItem[]; cursor?: string | null; has_more?: boolean };

        if (reset) {
          setItems(data.items ?? []);
        } else {
          setItems((prev) => [...prev, ...(data.items ?? [])]);
        }
        setCursor(data.cursor ?? null);
        setHasMore(data.has_more ?? false);
      } catch (err) {
        logError('GroupMediaTab.loadMedia', err);
      } finally {
        setLoading(false);
      }
    },
    [groupId, cursor, filter],
  );

  useEffect(() => {
    loadMedia(true);
  }, [groupId, filter]); // eslint-disable-line react-hooks/exhaustive-deps

  // ───────────────────────────────────────────────────────────────────────
  // Upload
  // ───────────────────────────────────────────────────────────────────────

  const handleFileSelect = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    // Reset file input so the same file can be re-selected
    e.target.value = '';

    const isImage = file.type.startsWith('image/');
    const isVideo = file.type.startsWith('video/');

    if (!isImage && !isVideo) {
      toast.error(t('media.invalid_type', 'Please select an image or video file'));
      return;
    }

    // 50 MB limit for videos, 25 MB for images
    const maxSize = isVideo ? 50 * 1024 * 1024 : 25 * 1024 * 1024;
    if (file.size > maxSize) {
      toast.error(
        t('media.too_large', 'File exceeds the size limit ({{limit}}MB)', {
          limit: isVideo ? 50 : 25,
        }),
      );
      return;
    }

    setUploading(true);
    try {
      const formData = new FormData();
      formData.append('file', file);

      await api.upload(`/v2/groups/${groupId}/media`, formData);

      toast.success(t('media.upload_success', 'Media uploaded successfully'));
      loadMedia(true);
    } catch (err) {
      logError('GroupMediaTab.upload', err);
      toast.error(t('media.upload_error', 'Failed to upload media'));
    } finally {
      setUploading(false);
    }
  };

  // ───────────────────────────────────────────────────────────────────────
  // Delete
  // ───────────────────────────────────────────────────────────────────────

  const handleDelete = async (mediaId: number, e?: React.MouseEvent) => {
    e?.stopPropagation();
    setDeleting(mediaId);
    try {
      await api.delete(`/v2/groups/${groupId}/media/${mediaId}`);
      setItems((prev) => prev.filter((m) => m.id !== mediaId));
      toast.success(t('media.delete_success', 'Media deleted'));

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
      logError('GroupMediaTab.delete', err);
      toast.error(t('media.delete_error', 'Failed to delete media'));
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
  const canDelete = (item: MediaItem) =>
    isAdmin || item.uploaded_by === Number(localStorage.getItem('userId'));

  // ───────────────────────────────────────────────────────────────────────
  // Render
  // ───────────────────────────────────────────────────────────────────────

  if (loading && items.length === 0) {
    return (
      <div
        className="flex justify-center py-12"
        aria-label={t('media.loading', 'Loading media')}
        aria-busy="true"
      >
        <Spinner size="lg" />
      </div>
    );
  }

  return (
    <div className="space-y-4">
      {/* Toolbar: filter chips + upload */}
      <div className="flex flex-col sm:flex-row sm:items-center gap-3">
        <div className="flex flex-wrap gap-2 flex-1" role="group" aria-label={t('media.filter_group', 'Filter media')}>
          {FILTER_CHIPS.map((chip) => (
            <Chip
              key={chip.key}
              variant={filter === chip.key ? 'solid' : 'bordered'}
              color="primary"
              className="cursor-pointer"
              onClick={() => setFilter(chip.key)}
              aria-pressed={filter === chip.key}
            >
              {chip.icon && (
                <chip.icon className="w-3 h-3 mr-1 inline" aria-hidden="true" />
              )}
              {t(chip.labelKey, chip.fallback)}
            </Chip>
          ))}
        </div>

        {isMember && (
          <Button
            color="primary"
            size="sm"
            startContent={
              uploading ? (
                <Spinner size="sm" color="current" />
              ) : (
                <Upload className="w-4 h-4" aria-hidden="true" />
              )
            }
            onPress={() => fileInputRef.current?.click()}
            isDisabled={uploading}
            aria-label={t('media.upload_aria', 'Upload photo or video')}
          >
            {uploading
              ? t('media.uploading', 'Uploading...')
              : t('media.upload', 'Upload')}
          </Button>
        )}

        <input
          ref={fileInputRef}
          type="file"
          accept="image/*,video/*"
          className="hidden"
          onChange={handleFileSelect}
          aria-hidden="true"
        />
      </div>

      {/* Gallery grid */}
      {items.length === 0 ? (
        <EmptyState
          icon={<Camera className="w-10 h-10 text-default-400" />}
          title={t('media.empty_title', 'No media yet')}
          description={
            filter !== 'all'
              ? t('media.no_results', 'No {{type}} found', {
                  type: filter === 'image' ? t('media.photos', 'photos') : t('media.videos', 'videos'),
                })
              : t('media.empty_description', 'Upload photos and videos to share with the group')
          }
        />
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
          {items.map((item, index) => (
            <GlassCard
              key={item.id}
              className="relative group overflow-hidden cursor-pointer"
              onClick={() => openLightbox(index)}
            >
              {/* Thumbnail */}
              <div className="aspect-square relative">
                {item.type === 'video' ? (
                  <div className="w-full h-full bg-default-100 flex items-center justify-center">
                    {item.thumbnail_url ? (
                      <Image
                        src={item.thumbnail_url}
                        alt={item.caption || t('media.video_thumbnail', 'Video thumbnail')}
                        className="w-full h-full object-cover"
                        removeWrapper
                      />
                    ) : (
                      <Film className="w-12 h-12 text-default-400" aria-hidden="true" />
                    )}
                    {/* Video badge */}
                    <div className="absolute bottom-2 left-2 bg-black/70 text-white text-xs px-2 py-0.5 rounded-full flex items-center gap-1">
                      <Film className="w-3 h-3" aria-hidden="true" />
                      {t('media.video_badge', 'Video')}
                    </div>
                  </div>
                ) : (
                  <Image
                    src={item.thumbnail_url || item.url}
                    alt={item.caption || t('media.photo_alt', 'Group photo')}
                    className="w-full h-full object-cover"
                    removeWrapper
                  />
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

                {/* Delete button on hover (admin or uploader) */}
                {canDelete(item) && (
                  <button
                    className="absolute top-2 right-2 w-8 h-8 rounded-full bg-danger/80 hover:bg-danger text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-200"
                    onClick={(e) => handleDelete(item.id, e)}
                    disabled={deleting === item.id}
                    aria-label={t('media.delete_aria', 'Delete media')}
                  >
                    {deleting === item.id ? (
                      <Spinner size="sm" color="current" />
                    ) : (
                      <Trash2 className="w-4 h-4" />
                    )}
                  </button>
                )}
              </div>
            </GlassCard>
          ))}
        </div>
      )}

      {/* Load more */}
      {hasMore && (
        <div className="flex justify-center pt-4">
          <Button variant="flat" size="sm" onPress={() => loadMedia(false)} isLoading={loading}>
            {t('media.load_more', 'Load More')}
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
        <ModalContent>
          {currentItem && (
            <ModalBody>
              <div className="relative flex flex-col items-center">
                {/* Close button */}
                <button
                  className="absolute top-2 right-2 z-50 w-10 h-10 rounded-full bg-black/50 hover:bg-black/70 text-white flex items-center justify-center transition-colors"
                  onClick={lightbox.onClose}
                  aria-label={t('media.close_lightbox', 'Close')}
                >
                  <X className="w-5 h-5" />
                </button>

                {/* Navigation: previous */}
                {items.length > 1 && (
                  <button
                    className="absolute left-2 top-1/2 -translate-y-1/2 z-50 w-10 h-10 rounded-full bg-black/50 hover:bg-black/70 text-white flex items-center justify-center transition-colors"
                    onClick={() => navigateLightbox(-1)}
                    aria-label={t('media.prev', 'Previous')}
                  >
                    <ChevronLeft className="w-6 h-6" />
                  </button>
                )}

                {/* Navigation: next */}
                {items.length > 1 && (
                  <button
                    className="absolute right-2 top-1/2 -translate-y-1/2 z-50 w-10 h-10 rounded-full bg-black/50 hover:bg-black/70 text-white flex items-center justify-center transition-colors"
                    onClick={() => navigateLightbox(1)}
                    aria-label={t('media.next', 'Next')}
                  >
                    <ChevronRight className="w-6 h-6" />
                  </button>
                )}

                {/* Media content */}
                <div className="max-h-[80vh] flex items-center justify-center w-full">
                  {currentItem.type === 'video' ? (
                    <video
                      src={currentItem.url}
                      controls
                      className="max-h-[80vh] max-w-full rounded-lg"
                      aria-label={currentItem.caption || t('media.video_player', 'Video player')}
                    />
                  ) : (
                    <Image
                      src={currentItem.url}
                      alt={currentItem.caption || t('media.fullsize_alt', 'Full size image')}
                      className="max-h-[80vh] max-w-full object-contain rounded-lg"
                      removeWrapper
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
                      color="danger"
                      variant="flat"
                      size="sm"
                      className="mt-3"
                      startContent={<Trash2 className="w-4 h-4" aria-hidden="true" />}
                      onPress={() => handleDelete(currentItem.id)}
                      isLoading={deleting === currentItem.id}
                      aria-label={t('media.delete_aria', 'Delete media')}
                    >
                      {t('media.delete', 'Delete')}
                    </Button>
                  )}
                </div>
              </div>
            </ModalBody>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}

export default GroupMediaTab;
