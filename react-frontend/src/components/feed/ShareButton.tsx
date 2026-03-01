// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * ShareButton - Share/Repost button for feed posts
 *
 * Allows users to share (repost) feed posts to their own feed.
 * Displays share count and handles toggle share/unshare.
 *
 * API: POST/DELETE /api/v2/feed/posts/{id}/share
 */

import { useState } from 'react';
import { Button, Tooltip } from '@heroui/react';
import { Repeat2 } from 'lucide-react';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

export interface ShareButtonProps {
  postId: number;
  shareCount: number;
  isShared: boolean;
  isAuthenticated: boolean;
  onShareChange?: (newCount: number, newIsShared: boolean) => void;
}

export function ShareButton({
  postId,
  shareCount,
  isShared,
  isAuthenticated,
  onShareChange,
}: ShareButtonProps) {
  const toast = useToast();
  const [localCount, setLocalCount] = useState(shareCount);
  const [localIsShared, setLocalIsShared] = useState(isShared);
  const [isLoading, setIsLoading] = useState(false);

  const handleToggle = async () => {
    if (!isAuthenticated) return;

    try {
      setIsLoading(true);

      // Optimistic update
      const newIsShared = !localIsShared;
      const newCount = newIsShared ? localCount + 1 : localCount - 1;
      setLocalIsShared(newIsShared);
      setLocalCount(Math.max(0, newCount));

      if (newIsShared) {
        const response = await api.post(`/v2/feed/posts/${postId}/share`);
        if (!response.success) {
          // Revert
          setLocalIsShared(false);
          setLocalCount(localCount);
          toast.error(response.error || 'Failed to share');
          return;
        }
        toast.success('Post shared to your feed');
      } else {
        const response = await api.delete(`/v2/feed/posts/${postId}/share`);
        if (!response.success) {
          // Revert
          setLocalIsShared(true);
          setLocalCount(localCount);
          toast.error(response.error || 'Failed to unshare');
          return;
        }
        toast.info('Share removed');
      }

      onShareChange?.(Math.max(0, newCount), newIsShared);
    } catch (err) {
      logError('Failed to toggle share', err);
      setLocalIsShared(isShared);
      setLocalCount(shareCount);
      toast.error('Failed to share post');
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <Tooltip
      content={localIsShared ? 'Remove share' : 'Share to your feed'}
      delay={400}
      closeDelay={0}
      size="sm"
    >
      <Button
        size="sm"
        variant="light"
        className={`flex-1 max-w-[140px] ${
          localIsShared
            ? 'text-emerald-500 font-medium'
            : 'text-[var(--text-muted)] hover:text-emerald-500'
        } transition-colors`}
        startContent={
          <Repeat2
            className={`w-[18px] h-[18px] transition-all ${
              localIsShared ? 'text-emerald-500' : ''
            }`}
            aria-hidden="true"
          />
        }
        onPress={isAuthenticated ? handleToggle : undefined}
        isDisabled={!isAuthenticated || isLoading}
      >
        {localCount > 0 ? `Share (${localCount})` : 'Share'}
      </Button>
    </Tooltip>
  );
}

/**
 * SharedByAttribution - Shows "Shared by X" on reposted content
 */
export function SharedByAttribution({
  sharerName,
}: {
  sharerName: string;
  sharerAvatar?: string;
  sharerId?: number;
}) {
  return (
    <div className="flex items-center gap-1.5 px-5 py-2 text-xs text-[var(--text-subtle)] border-b border-[var(--border-default)]">
      <Repeat2 className="w-3.5 h-3.5 text-emerald-500" aria-hidden="true" />
      <span>
        <span className="font-medium text-[var(--text-muted)]">{sharerName}</span> shared this
      </span>
    </div>
  );
}

export default ShareButton;
