// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * PostTab — rich text post creation with multi-image upload, emoji picker,
 * voice input, link preview, character count, and draft persistence.
 */

import { useState, useCallback, useEffect, useRef, lazy, Suspense } from 'react';
import { Button, Avatar, Spinner } from '@heroui/react';
import { useTranslation } from 'react-i18next';
import { useAuth, useToast } from '@/contexts';
import { useDraftPersistence } from '@/hooks';
import { useMediaQuery } from '@/hooks/useMediaQuery';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl } from '@/lib/helpers';
import type { ComposeEditorHandle } from '../shared/ComposeEditor';

const ComposeEditor = lazy(() =>
  import('../shared/ComposeEditor').then((m) => ({ default: m.ComposeEditor })),
);
import { MultiImageUploader } from '../shared/MultiImageUploader';
import { EmojiPicker } from '../shared/EmojiPicker';
import { VoiceInput } from '../shared/VoiceInput';
import { CharacterCount } from '../shared/CharacterCount';
import { LinkPreview } from '../shared/LinkPreview';
import { useComposeSubmit } from '../ComposeSubmitContext';
import type { TabSubmitProps } from '../types';

const MAX_CONTENT_CHARS = 5000;

interface PostDraft {
  htmlContent: string;
  plainText: string;
}

export function PostTab({ onSuccess, onClose, groupId, templateData }: TabSubmitProps) {
  const { t } = useTranslation('feed');
  const { user } = useAuth();
  const toast = useToast();
  const { register, unregister } = useComposeSubmit();
  const isMobile = useMediaQuery('(max-width: 639px)');
  const editorRef = useRef<ComposeEditorHandle>(null);
  const submitRef = useRef<() => void>(() => {});

  const [draft, setDraft, clearDraft] = useDraftPersistence<PostDraft>(
    'compose-draft-post',
    { htmlContent: '', plainText: '' },
  );

  const [imageFiles, setImageFiles] = useState<File[]>([]);
  const [imagePreviews, setImagePreviews] = useState<string[]>([]);
  const [isSubmitting, setIsSubmitting] = useState(false);

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

  const canSubmit = draft.plainText.trim().length > 0 || imageFiles.length > 0;

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

  // Multi-image handlers
  const handleImageAdd = useCallback((file: File, preview: string) => {
    setImageFiles((prev) => [...prev, file]);
    setImagePreviews((prev) => [...prev, preview]);
  }, []);

  const handleImageRemove = useCallback((index: number) => {
    setImageFiles((prev) => prev.filter((_, i) => i !== index));
    setImagePreviews((prev) => prev.filter((_, i) => i !== index));
  }, []);

  const handleImageReorder = useCallback((files: File[], previews: string[]) => {
    setImageFiles(files);
    setImagePreviews(previews);
  }, []);

  const handleSubmit = async () => {
    if (!canSubmit) return;

    setIsSubmitting(true);
    try {
      // Decide content: use HTML if it's rich, plain text otherwise
      const contentToSend = draft.htmlContent || draft.plainText.trim();

      let res;

      if (imageFiles.length > 0) {
        // Use multipart/form-data when images are attached
        const formData = new FormData();
        formData.append('content', contentToSend);
        formData.append('visibility', 'public');
        if (groupId) formData.append('group_id', String(groupId));

        // Append all images (primary + extras for future multi-image support)
        imageFiles.forEach((file, i) => {
          formData.append(i === 0 ? 'image' : `image_${i}`, file);
        });

        res = await api.upload('/v2/feed/posts', formData);
      } else {
        // JSON for text-only posts
        res = await api.post('/v2/feed/posts', {
          content: contentToSend,
          visibility: 'public',
          ...(groupId ? { group_id: groupId } : {}),
        });
      }

      if (res.success) {
        clearDraft();
        toast.success(t('compose.post_created'));
        onClose();
        onSuccess('post');
      } else {
        toast.error(t('compose.post_failed'));
      }
    } catch (err) {
      logError('Failed to create post', err);
      toast.error(t('compose.post_failed'));
    } finally {
      setIsSubmitting(false);
    }
  };
  submitRef.current = handleSubmit;

  // Register submit capabilities for mobile header button
  useEffect(() => {
    register({
      canSubmit,
      isSubmitting,
      onSubmit: () => submitRef.current(),
      buttonLabel: t('compose.post_button'),
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

      {/* Multi-image uploader */}
      <MultiImageUploader
        files={imageFiles}
        previews={imagePreviews}
        onAdd={handleImageAdd}
        onRemove={handleImageRemove}
        onReorder={handleImageReorder}
        maxImages={4}
        onError={(msg) => toast.error(msg)}
      />

      {/* Toolbar + submit row */}
      <div className={`flex items-center justify-between pt-1 ${isMobile ? 'sticky bottom-0 bg-[var(--surface-base)] py-3 border-t border-[var(--border-default)]' : ''}`}>
        <div className="flex items-center gap-1">
          <EmojiPicker onSelect={handleEmojiSelect} />
          <VoiceInput onTranscript={handleVoiceTranscript} />
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
              {t('compose.post_button')}
            </Button>
          </div>
        )}
      </div>
    </div>
  );
}
