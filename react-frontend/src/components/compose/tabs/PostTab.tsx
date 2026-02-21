// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * PostTab — text post + image upload form for the Compose Hub.
 */

import { useState } from 'react';
import { Button, Textarea, Avatar } from '@heroui/react';
import { useAuth, useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl } from '@/lib/helpers';
import { ImageUploader } from '../shared/ImageUploader';
import type { TabSubmitProps } from '../types';

export function PostTab({ onSuccess, onClose, groupId }: TabSubmitProps) {
  const { user } = useAuth();
  const toast = useToast();
  const [content, setContent] = useState('');
  const [imageFile, setImageFile] = useState<File | null>(null);
  const [imagePreview, setImagePreview] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const canSubmit = content.trim().length > 0 || imageFile !== null;

  const handleSubmit = async () => {
    if (!canSubmit) return;

    setIsSubmitting(true);
    try {
      if (imageFile) {
        const formData = new FormData();
        formData.append('content', content.trim());
        formData.append('visibility', 'public');
        formData.append('image', imageFile);
        if (groupId) formData.append('group_id', String(groupId));

        const res = await api.post('/social/create-post', formData as unknown as Record<string, unknown>);
        if (res.success) {
          toast.success('Post created!');
          onClose();
          onSuccess('post');
        }
      } else {
        const res = await api.post('/v2/feed/posts', {
          content: content.trim(),
          visibility: 'public',
          ...(groupId ? { group_id: groupId } : {}),
        });
        if (res.success) {
          toast.success('Post created!');
          onClose();
          onSuccess('post');
        }
      }
    } catch (err) {
      logError('Failed to create post', err);
      toast.error('Failed to create post');
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
          className="mt-1"
          isBordered
        />
        <Textarea
          placeholder="What's on your mind?"
          value={content}
          onChange={(e) => setContent(e.target.value)}
          minRows={3}
          maxRows={8}
          classNames={{
            input: 'bg-transparent text-[var(--text-primary)]',
            inputWrapper: 'bg-[var(--surface-elevated)] border-[var(--border-default)] hover:border-[var(--color-primary)]/40',
          }}
        />
      </div>

      <div className="pl-11">
        <ImageUploader
          file={imageFile}
          preview={imagePreview}
          onSelect={(f, p) => { setImageFile(f); setImagePreview(p); }}
          onRemove={() => { setImageFile(null); setImagePreview(null); }}
          onError={(msg) => toast.error(msg)}
        />
      </div>

      <div className="flex flex-col-reverse sm:flex-row justify-end gap-2 pt-2">
        <Button
          variant="flat"
          onPress={onClose}
          className="text-[var(--text-muted)]"
        >
          Cancel
        </Button>
        <Button
          className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white shadow-lg shadow-indigo-500/20"
          onPress={handleSubmit}
          isLoading={isSubmitting}
          isDisabled={!canSubmit}
        >
          Post
        </Button>
      </div>
    </div>
  );
}
