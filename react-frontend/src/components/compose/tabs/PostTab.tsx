// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * PostTab — rich text post + multi-image upload for the Compose Hub.
 *
 * Features: Lexical rich text editor, multi-image with drag reorder,
 * emoji picker, voice input, character count, link preview, draft persistence.
 */

import { useState, useCallback, useEffect } from 'react';
import { Button, Avatar } from '@heroui/react';
import { useTranslation } from 'react-i18next';
import { useAuth, useToast } from '@/contexts';
import { useDraftPersistence } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl } from '@/lib/helpers';
import { ComposeEditor } from '../shared/ComposeEditor';
import { MultiImageUploader } from '../shared/MultiImageUploader';
import { EmojiPicker } from '../shared/EmojiPicker';
import { VoiceInput } from '../shared/VoiceInput';
import { CharacterCount } from '../shared/CharacterCount';
import { LinkPreview } from '../shared/LinkPreview';
import type { TabSubmitProps } from '../types';

const MAX_CHARS = 2000;

interface PostDraft {
  html: string;
  plainText: string;
}

export function PostTab({ onSuccess, onClose, groupId, templateData }: TabSubmitProps) {
  const { t } = useTranslation('feed');
  const { user } = useAuth();
  const toast = useToast();

  const [draft, setDraft, clearDraft] = useDraftPersistence<PostDraft>(
    'compose-draft-post',
    { html: '', plainText: '' },
  );

  const [imageFiles, setImageFiles] = useState<File[]>([]);
  const [imagePreviews, setImagePreviews] = useState<string[]>([]);
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Track plain text separately for char count / submit validation
  const [plainText, setPlainText] = useState(draft.plainText);

  // Apply template data when selected from TemplatePicker
  useEffect(() => {
    if (templateData) {
      setDraft({ html: `<p>${templateData.content}</p>`, plainText: templateData.content });
      setPlainText(templateData.content);
    }
  }, [templateData, setDraft]);

  const canSubmit = plainText.trim().length > 0 || imageFiles.length > 0;

  const handleHtmlChange = useCallback(
    (html: string) => {
      setDraft((prev) => ({ ...prev, html }));
    },
    [setDraft],
  );

  const handlePlainTextChange = useCallback(
    (text: string) => {
      setPlainText(text);
      setDraft((prev) => ({ ...prev, plainText: text }));
    },
    [setDraft],
  );

  const handleEmojiSelect = useCallback(
    (emoji: string) => {
      // Append emoji to plain-text equivalent (Lexical handles its own state)
      // For rich text, we insert at the end — the ComposeEditor will pick it up
      setDraft((prev) => ({
        html: prev.html
          ? prev.html.replace(/<\/p>$/, `${emoji}</p>`)
          : `<p>${emoji}</p>`,
        plainText: prev.plainText + emoji,
      }));
      setPlainText((prev) => prev + emoji);
    },
    [setDraft],
  );

  const handleVoiceTranscript = useCallback(
    (text: string) => {
      setDraft((prev) => ({
        html: prev.html
          ? prev.html.replace(/<\/p>$/, ` ${text}</p>`)
          : `<p>${text}</p>`,
        plainText: prev.plainText + (prev.plainText ? ' ' : '') + text,
      }));
      setPlainText((prev) => prev + (prev ? ' ' : '') + text);
    },
    [setDraft],
  );

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
      const contentToSend = draft.html.trim() || plainText.trim();

      if (imageFiles.length > 0) {
        const formData = new FormData();
        formData.append('content', contentToSend);
        formData.append('visibility', 'public');
        imageFiles.forEach((f) => formData.append('image', f));
        if (groupId) formData.append('group_id', String(groupId));

        const res = await api.upload('/social/create-post', formData);
        if (res.success) {
          clearDraft();
          toast.success(t('compose.post_created'));
          onClose();
          onSuccess('post');
        } else {
          toast.error(t('compose.post_failed'));
        }
      } else {
        const res = await api.post('/v2/feed/posts', {
          content: contentToSend,
          visibility: 'public',
          ...(groupId ? { group_id: groupId } : {}),
        });
        if (res.success) {
          clearDraft();
          toast.success(t('compose.post_created'));
          onClose();
          onSuccess('post');
        } else {
          toast.error(t('compose.post_failed'));
        }
      }
    } catch (err) {
      logError('Failed to create post', err);
      toast.error(t('compose.post_failed'));
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex items-start gap-3">
        <Avatar
          name={user?.first_name || 'You'}
          src={resolveAvatarUrl(user?.avatar)}
          size="sm"
          className="mt-1 flex-shrink-0"
          isBordered
        />
        <div className="flex-1 min-w-0">
          <ComposeEditor
            value={draft.html}
            onChange={handleHtmlChange}
            onPlainTextChange={handlePlainTextChange}
            placeholder={t('whats_on_your_mind')}
            maxLength={MAX_CHARS}
          />
          <CharacterCount current={plainText.length} max={MAX_CHARS} />
        </div>
      </div>

      {/* Link preview from content URLs */}
      <LinkPreview content={plainText} />

      {/* Multi-image uploader with drag reorder */}
      <MultiImageUploader
        files={imageFiles}
        previews={imagePreviews}
        onAdd={handleImageAdd}
        onRemove={handleImageRemove}
        onReorder={handleImageReorder}
        onError={(msg) => toast.error(msg)}
      />

      {/* Toolbar: emoji + voice + actions */}
      <div className="flex items-center justify-between pt-1">
        <div className="flex items-center gap-1">
          <EmojiPicker onSelect={handleEmojiSelect} />
          <VoiceInput onTranscript={handleVoiceTranscript} />
        </div>

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
      </div>
    </div>
  );
}
