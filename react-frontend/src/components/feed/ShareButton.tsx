// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * ShareButton - Enhanced share/repost button for feed posts.
 *
 * Dropdown menu with options:
 *   1. Repost — simple share (toggle share/unshare)
 *   2. Quote — opens QuotePostModal
 *   3. Copy Link — copies post URL to clipboard
 *   4. Share... — Web Share API or ExternalShareModal fallback
 *   5. Send via Message — opens ShareViaDMModal
 *
 * API: POST/DELETE /api/v2/feed/posts/{id}/share
 */

import { useState, useCallback } from 'react';
import {
  Button,
  Dropdown,
  DropdownTrigger,
  DropdownMenu,
  DropdownItem,
} from '@heroui/react';
import {
  Repeat2,
  Quote,
  Copy,
  Share2,
  MessageSquare,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import type { FeedItem } from './types';
import { QuotePostModal } from './QuotePostModal';
import { ExternalShareModal } from './ExternalShareModal';
import { ShareViaDMModal } from './ShareViaDMModal';

export interface ShareButtonProps {
  postId: number;
  shareCount: number;
  isShared: boolean;
  isAuthenticated: boolean;
  /** The full FeedItem — needed for quote post and share modals. */
  post: FeedItem;
  onShareChange?: (newCount: number, newIsShared: boolean) => void;
}

export function ShareButton({
  postId,
  shareCount,
  isShared,
  isAuthenticated,
  post,
  onShareChange,
}: ShareButtonProps) {
  const toast = useToast();
  const { t } = useTranslation('feed');
  const { tenantSlug } = useTenant();
  const [localCount, setLocalCount] = useState(shareCount);
  const [localIsShared, setLocalIsShared] = useState(isShared);
  const [isLoading, setIsLoading] = useState(false);

  // Modal states
  const [showQuoteModal, setShowQuoteModal] = useState(false);
  const [showExternalShareModal, setShowExternalShareModal] = useState(false);
  const [showDMModal, setShowDMModal] = useState(false);

  const postUrl = `${window.location.origin}/${tenantSlug}/feed?post=${postId}`;
  const postTitle = post.title || post.content?.slice(0, 80) || 'Post';
  const postText = post.content?.slice(0, 200) || '';

  const handleRepost = useCallback(async () => {
    if (!isAuthenticated) return;

    try {
      setIsLoading(true);

      const newIsShared = !localIsShared;
      const newCount = newIsShared ? localCount + 1 : localCount - 1;
      setLocalIsShared(newIsShared);
      setLocalCount(Math.max(0, newCount));

      if (newIsShared) {
        const response = await api.post(`/v2/feed/posts/${postId}/share`);
        if (!response.success) {
          setLocalIsShared(false);
          setLocalCount(localCount);
          toast.error(response.error || t('toast.share_failed'));
          return;
        }
        toast.success(t('toast.post_shared'));
      } else {
        const response = await api.delete(`/v2/feed/posts/${postId}/share`);
        if (!response.success) {
          setLocalIsShared(true);
          setLocalCount(localCount);
          toast.error(response.error || t('toast.share_failed'));
          return;
        }
        toast.info(t('toast.share_removed'));
      }

      onShareChange?.(Math.max(0, newCount), newIsShared);
    } catch (err) {
      logError('Failed to toggle share', err);
      setLocalIsShared(isShared);
      setLocalCount(shareCount);
      toast.error(t('toast.share_failed'));
    } finally {
      setIsLoading(false);
    }
  }, [isAuthenticated, localIsShared, localCount, postId, isShared, shareCount, onShareChange, toast, t]);

  const handleCopyLink = useCallback(async () => {
    try {
      await navigator.clipboard.writeText(postUrl);
      toast.success(t('share.link_copied', 'Link copied to clipboard'));
    } catch {
      toast.error(t('share.copy_failed', 'Failed to copy link'));
    }
  }, [postUrl, toast, t]);

  const handleExternalShare = useCallback(async () => {
    if (typeof navigator.share === 'function') {
      try {
        await navigator.share({
          title: postTitle,
          text: postText,
          url: postUrl,
        });
      } catch (err) {
        // User cancelled share — not an error
        if ((err as DOMException)?.name !== 'AbortError') {
          logError('Web Share API failed', err);
        }
      }
    } else {
      setShowExternalShareModal(true);
    }
  }, [postTitle, postText, postUrl]);

  const handleDropdownAction = useCallback((key: React.Key) => {
    switch (key) {
      case 'repost':
        handleRepost();
        break;
      case 'quote':
        setShowQuoteModal(true);
        break;
      case 'copy':
        handleCopyLink();
        break;
      case 'external':
        handleExternalShare();
        break;
      case 'dm':
        setShowDMModal(true);
        break;
    }
  }, [handleRepost, handleCopyLink, handleExternalShare]);

  return (
    <>
      <Dropdown placement="bottom">
        <DropdownTrigger>
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
            isDisabled={!isAuthenticated || isLoading}
          >
            {localCount > 0 ? t('share.button_label_count', { count: localCount }) : t('share.button_label')}
          </Button>
        </DropdownTrigger>
        <DropdownMenu
          aria-label={t('share.menu_label', 'Share options')}
          onAction={handleDropdownAction}
        >
          <DropdownItem
            key="repost"
            startContent={<Repeat2 className="w-4 h-4" aria-hidden="true" />}
            description={localIsShared
              ? t('share.repost_remove_desc', 'Remove repost from your feed')
              : t('share.repost_desc', 'Share to your feed')}
          >
            {localIsShared ? t('share.repost_remove', 'Remove Repost') : t('share.repost', 'Repost')}
          </DropdownItem>
          <DropdownItem
            key="quote"
            startContent={<Quote className="w-4 h-4" aria-hidden="true" />}
            description={t('share.quote_desc', 'Repost with your commentary')}
          >
            {t('share.quote', 'Quote Post')}
          </DropdownItem>
          <DropdownItem
            key="copy"
            startContent={<Copy className="w-4 h-4" aria-hidden="true" />}
          >
            {t('share.copy_link', 'Copy Link')}
          </DropdownItem>
          <DropdownItem
            key="external"
            startContent={<Share2 className="w-4 h-4" aria-hidden="true" />}
          >
            {t('share.external', 'Share...')}
          </DropdownItem>
          <DropdownItem
            key="dm"
            startContent={<MessageSquare className="w-4 h-4" aria-hidden="true" />}
          >
            {t('share.send_dm', 'Send via Message')}
          </DropdownItem>
        </DropdownMenu>
      </Dropdown>

      {/* Quote Post Modal */}
      <QuotePostModal
        isOpen={showQuoteModal}
        onClose={() => setShowQuoteModal(false)}
        post={post}
      />

      {/* External Share Modal (fallback for Web Share API) */}
      <ExternalShareModal
        isOpen={showExternalShareModal}
        onClose={() => setShowExternalShareModal(false)}
        url={postUrl}
        title={postTitle}
        text={postText}
      />

      {/* Share via DM Modal */}
      <ShareViaDMModal
        isOpen={showDMModal}
        onClose={() => setShowDMModal(false)}
        postUrl={postUrl}
        postContent={postText}
      />
    </>
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
