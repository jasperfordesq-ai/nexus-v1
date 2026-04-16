// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * PostTab — rich text post creation with multi-image upload, emoji picker,
 * voice input, link preview, character count, and draft persistence.
 */

import { useState, useCallback, useEffect, useRef, lazy, Suspense } from 'react';
import { Button, Avatar, Spinner, DatePicker, TimeInput, Popover, PopoverTrigger, PopoverContent } from '@heroui/react';
import type { DateInputValue, TimeInputValue } from '@heroui/react';
import { today, getLocalTimeZone } from '@internationalized/date';
import { Calendar, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useAuth, useToast, useTenant } from '@/contexts';
import { useDraftPersistence } from '@/hooks';
import { useMediaQuery } from '@/hooks/useMediaQuery';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl } from '@/lib/helpers';
import type { ComposeEditorHandle } from '../shared/ComposeEditor';

const ComposeEditor = lazy(() =>
  import('../shared/ComposeEditor').then((m) => ({ default: m.ComposeEditor })),
);
import { MediaUploader, type MediaFile } from '../MediaUploader';
import { EmojiPicker } from '../shared/EmojiPicker';
import { GifPicker } from '../GifPicker';
import { VoiceInput } from '../shared/VoiceInput';
import { CharacterCount } from '../shared/CharacterCount';
import { LinkPreview } from '../shared/LinkPreview';
import { useComposeSubmit } from '../ComposeSubmitContext';
import type { TabSubmitProps } from '../types';

const MAX_CONTENT_CHARS = 5000;

/** Convert DateInputValue + optional TimeInputValue to ISO string */
function toScheduleIso(date: DateInputValue, time: TimeInputValue | null): string {
  const dateStr = date.toString(); // "2026-02-21"
  if (time) {
    const h = String(time.hour).padStart(2, '0');
    const m = String(time.minute).padStart(2, '0');
    return `${dateStr}T${h}:${m}:00`;
  }
  return `${dateStr}T00:00:00`;
}

interface PostDraft {
  htmlContent: string;
  plainText: string;
}

export function PostTab({ onSuccess, onClose, isOpen, groupId, templateData, onContentChange, editItem, onEditSuccess }: TabSubmitProps & {
  isOpen?: boolean;
  editItem?: import('@/components/feed/types').FeedItem | null;
  onEditSuccess?: (item: import('@/components/feed/types').FeedItem) => void;
}) {
  const { t } = useTranslation('feed');
  const { user } = useAuth();
  const toast = useToast();
  const { tenant } = useTenant();
  const { register, unregister } = useComposeSubmit();
  const isMobile = useMediaQuery('(max-width: 639px)');
  const editorRef = useRef<ComposeEditorHandle>(null);
  const submitRef = useRef<() => void>(() => {});
  // H8: Ref guard to prevent concurrent double-submissions
  const isSubmittingRef = useRef(false);

  const isEditing = !!editItem;

  // H10: Tenant-scoped draft key prevents cross-tenant draft leakage
  const draftKey = isEditing ? `compose-edit-${editItem?.id}` : `compose-draft-post-${tenant?.slug ?? 'default'}`;
  const [draft, setDraft, clearDraft] = useDraftPersistence<PostDraft>(
    draftKey,
    { htmlContent: editItem?.content ?? '', plainText: editItem?.content ?? '' },
  );

  const [mediaFiles, setMediaFiles] = useState<MediaFile[]>([]);
  const [selectedGifUrl, setSelectedGifUrl] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [scheduleDate, setScheduleDate] = useState<DateInputValue | null>(null);
  const [scheduleTime, setScheduleTime] = useState<TimeInputValue | null>(null);
  const [isScheduleOpen, setIsScheduleOpen] = useState(false);

  const isScheduled = scheduleDate !== null;

  // H10: Clear draft when modal closes without submitting (avoids stale draft on next open)
  useEffect(() => {
    if (!isOpen) {
      clearDraft();
    }
  }, [isOpen, clearDraft]);

  // Apply template data when selected from TemplatePicker
  useEffect(() => {
    if (templateData) {
      setDraft((prev) => ({
        ...prev,
        htmlContent: templateData.content,
        plainText: templateData.content,
      }));
    }
  }, [templateData, setDraft]);

  const canSubmit = draft.plainText.trim().length > 0 || mediaFiles.length > 0 || selectedGifUrl !== null;

  // Report unsaved content state up to ComposeHub for beforeunload guard
  useEffect(() => {
    onContentChange?.(canSubmit);
  }, [canSubmit, onContentChange]);

  const handleHtmlChange = useCallback(
    (html: string) => setDraft((prev) => ({ ...prev, htmlContent: html })),
    [setDraft],
  );

  const handlePlainTextChange = useCallback(
    (text: string) => setDraft((prev) => ({ ...prev, plainText: text })),
    [setDraft],
  );

  const handleEmojiSelect = useCallback(
    (emoji: string) => {
      // Insert emoji at cursor position via Lexical's editor API
      editorRef.current?.insertText(emoji);
    },
    [],
  );

  const handleVoiceTranscript = useCallback(
    (text: string) => {
      // Insert voice transcript at cursor position via Lexical's editor API
      editorRef.current?.insertText(text);
    },
    [],
  );

  // Media handlers for the new MediaUploader
  const handleMediaChange = useCallback((files: MediaFile[]) => {
    setMediaFiles(files);
  }, []);

  const handleSubmit = async () => {
    if (!canSubmit) return;
    // H8: Prevent concurrent double-submissions
    if (isSubmittingRef.current) return;
    isSubmittingRef.current = true;

    if (draft.plainText.trim().length === 0 && mediaFiles.length === 0 && selectedGifUrl === null) {
      toast.error(t('compose.content_required'));
      return;
    }
    if (draft.plainText.length > MAX_CONTENT_CHARS) {
      toast.error(t('compose.content_too_long', { max: MAX_CONTENT_CHARS }));
      return;
    }

    setIsSubmitting(true);
    try {
      // Decide content: use HTML if it's rich, plain text otherwise
      // Append GIF URL if one was selected
      const baseContent = draft.htmlContent || draft.plainText.trim();
      const contentToSend = selectedGifUrl
        ? `${baseContent}\n${selectedGifUrl}`.trim()
        : baseContent;

      // ── Edit mode: PUT existing post ──
      if (isEditing && editItem) {
        const res = await api.put(`/v2/feed/posts/${editItem.id}`, {
          content: contentToSend,
        });

        if (res.success && res.data) {
          clearDraft();
          toast.success(t('toast.post_updated', 'Post updated'));
          onEditSuccess?.(res.data as import('@/components/feed/types').FeedItem);
        } else {
          toast.error(t('toast.update_failed', 'Failed to update post'));
        }
        return;
      }

      // ── Create mode: POST new post ──
      let res;

      // Build scheduling payload
      const schedulePayload: Record<string, string> = {};
      if (isScheduled && scheduleDate) {
        schedulePayload.publish_status = 'scheduled';
        schedulePayload.scheduled_at = toScheduleIso(scheduleDate, scheduleTime);
      }

      if (mediaFiles.length > 0) {
        // Use multipart/form-data when images are attached
        const formData = new FormData();
        formData.append('content', contentToSend);
        formData.append('visibility', 'public');
        if (groupId) formData.append('group_id', String(groupId));
        if (schedulePayload.publish_status) {
          formData.append('publish_status', schedulePayload.publish_status);
          formData.append('scheduled_at', schedulePayload.scheduled_at ?? '');
        }

        // Append media files and alt texts for PostMediaService
        mediaFiles.forEach((item) => {
          formData.append('media[]', item.file);
          formData.append('alt_texts[]', item.altText || '');
        });

        res = await api.upload('/v2/feed/posts', formData);
      } else {
        // JSON for text-only posts
        res = await api.post('/v2/feed/posts', {
          content: contentToSend,
          visibility: 'public',
          ...(groupId ? { group_id: groupId } : {}),
          ...schedulePayload,
        });
      }

      if (res.success) {
        clearDraft();
        setMediaFiles([]);
        setSelectedGifUrl(null);
        setScheduleDate(null);
        setScheduleTime(null);
        toast.success(isScheduled ? t('compose.post_scheduled') : t('compose.post_created'));
        onClose();
        onSuccess('post');
      } else {
        toast.error(t('compose.post_failed'));
      }
    } catch (err) {
      logError(isEditing ? 'Failed to update post' : 'Failed to create post', err);
      toast.error(isEditing ? t('toast.update_failed', 'Failed to update post') : t('compose.post_failed'));
    } finally {
      setIsSubmitting(false);
      isSubmittingRef.current = false; // H8: Release ref guard
    }
  };
  submitRef.current = handleSubmit;

  // Register submit capabilities for mobile header button
  useEffect(() => {
    register({
      canSubmit,
      isSubmitting,
      onSubmit: () => submitRef.current(),
      buttonLabel: isEditing ? t('compose.save_changes', 'Save') : t('compose.post_button'),
      gradientClass: 'from-indigo-500 to-purple-600',
    });
    return unregister;
  }, [canSubmit, isSubmitting, register, unregister, t]);

  return (
    <div className="space-y-4">
      {/* Avatar + Rich text editor */}
      <div className="flex items-start gap-3">
        <Avatar
          name={user?.first_name || 'You'}
          src={resolveAvatarUrl(user?.avatar)}
          size="sm"
          className="mt-1 flex-shrink-0"
          isBordered
        />
        <div className="flex-1 min-w-0">
          <Suspense fallback={<Spinner size="sm" className="m-4" />}>
            <ComposeEditor
              ref={editorRef}
              value={draft.htmlContent}
              onChange={handleHtmlChange}
              onPlainTextChange={handlePlainTextChange}
              placeholder={t('whats_on_your_mind')}
              maxLength={MAX_CONTENT_CHARS}
            />
          </Suspense>
          <CharacterCount current={draft.plainText.length} max={MAX_CONTENT_CHARS} />
        </div>
      </div>

      {/* Link preview */}
      <LinkPreview content={draft.plainText} />

      {/* Multi-image uploader with drag-and-drop, alt text, reorder */}
      <MediaUploader
        mediaFiles={mediaFiles}
        onMediaChange={handleMediaChange}
        maxFiles={10}
        maxSizeMb={10}
        onError={(msg) => toast.error(msg)}
      />

      {/* GIF preview */}
      {selectedGifUrl && (
        <div className="relative inline-block">
          <img
            src={selectedGifUrl}
            alt="Selected GIF"
            className="max-w-[240px] max-h-[200px] rounded-lg border border-[var(--border-default)]"
          />
          <Button
            isIconOnly
            variant="flat"
            size="sm"
            onPress={() => setSelectedGifUrl(null)}
            className="absolute -top-1.5 -right-1.5 w-5 h-5 min-w-0 min-h-0 bg-red-500 rounded-full text-white hover:bg-red-600 transition-colors"
            aria-label={t('compose.cancel')}
          >
            <X className="w-3 h-3" />
          </Button>
        </div>
      )}

      {/* Schedule indicator */}
      {isScheduled && scheduleDate && (
        <div className="flex items-center gap-2 px-3 py-2 rounded-lg bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-300 text-sm">
          <Calendar className="w-4 h-4 flex-shrink-0" />
          <span>{t('compose.schedule_set', { date: toScheduleIso(scheduleDate, scheduleTime) })}</span>
          <Button
            isIconOnly
            variant="light"
            size="sm"
            onPress={() => { setScheduleDate(null); setScheduleTime(null); }}
            className="ml-auto p-0.5 min-w-0 min-h-0 h-auto hover:bg-primary-100 dark:hover:bg-primary-800/30"
            aria-label={t('compose.schedule_clear')}
          >
            <X className="w-3.5 h-3.5" />
          </Button>
        </div>
      )}

      {/* Toolbar + submit row */}
      <div className={`flex items-center justify-between pt-1 ${isMobile ? 'sticky bottom-0 bg-[var(--surface-base)] py-3 border-t border-[var(--border-default)]' : ''}`}>
        <div className="flex items-center gap-1">
          <EmojiPicker onSelect={handleEmojiSelect} />
          <GifPicker onSelect={setSelectedGifUrl} />
          <VoiceInput onTranscript={handleVoiceTranscript} />
          <Popover isOpen={isScheduleOpen} onOpenChange={setIsScheduleOpen} placement="top-start">
            <PopoverTrigger>
              <Button
                variant="light"
                size="sm"
                isIconOnly
                aria-label={t('compose.schedule_label')}
                className={isScheduled ? 'text-primary' : 'text-[var(--text-muted)]'}
              >
                <Calendar className="w-5 h-5" />
              </Button>
            </PopoverTrigger>
            <PopoverContent className="p-4 w-72 space-y-3">
              <p className="text-sm font-medium text-[var(--text-primary)]">{t('compose.schedule_label')}</p>
              <DatePicker
                label={t('compose.schedule_date_label')}
                variant="bordered"
                size="sm"
                value={scheduleDate}
                onChange={setScheduleDate}
                minValue={today(getLocalTimeZone())}
                granularity="day"
              />
              <TimeInput
                label={t('compose.schedule_time_label')}
                variant="bordered"
                size="sm"
                value={scheduleTime}
                onChange={setScheduleTime}
              />
              <div className="flex gap-2 justify-end">
                {isScheduled && (
                  <Button
                    variant="flat"
                    size="sm"
                    onPress={() => { setScheduleDate(null); setScheduleTime(null); setIsScheduleOpen(false); }}
                  >
                    {t('compose.schedule_clear')}
                  </Button>
                )}
                <Button
                  color="primary"
                  size="sm"
                  isDisabled={!scheduleDate}
                  onPress={() => setIsScheduleOpen(false)}
                >
                  {t('compose.schedule_confirm')}
                </Button>
              </div>
            </PopoverContent>
          </Popover>
        </div>

        {!isMobile && (
          <div className="flex items-center gap-2">
            <Button
              variant="flat"
              size="sm"
              onPress={onClose}
              className="text-[var(--text-muted)]"
            >
              {t('compose.cancel')}
            </Button>
            <Button
              size="sm"
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white shadow-lg shadow-indigo-500/20"
              onPress={handleSubmit}
              isLoading={isSubmitting}
              isDisabled={!canSubmit}
            >
              {isScheduled ? t('compose.schedule_button') : t('compose.post_button')}
            </Button>
          </div>
        )}
      </div>
    </div>
  );
}
