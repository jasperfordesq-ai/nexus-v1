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

import { useState, useCallback, useEffect } from 'react';
import { Link } from 'react-router-dom';
import {
  Button,
  Dropdown,
  DropdownTrigger,
  DropdownMenu,
  DropdownItem,
} from '@heroui/react';
import Repeat2 from 'lucide-react/icons/repeat-2';
import Quote from 'lucide-react/icons/quote';
import Copy from 'lucide-react/icons/copy';
import Share2 from 'lucide-react/icons/share-2';
import MessageSquare from 'lucide-react/icons/message-square';
import { useTranslation } from 'react-i18next';
import { useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import type { FeedItem } from './types';
import { getItemDetailPath } from './types';
import { QuotePostModal } from './QuotePostModal';
import { ExternalShareModal } from './ExternalShareModal';
import { ShareViaDMModal } from './ShareViaDMModal';

export interface ShareButtonProps {
  /**
   * Legacy: pass when `type` is omitted — resolves to type='post'. Prefer `type` + `id`.
   * Kept for test compatibility.
   */
  postId?: number;
  /** Polymorphic item type (post, listing, event, poll, job, blog, discussion, goal, challenge, volunteer). */
  type?: FeedItem['type'];
  /** Polymorphic item id. */
  id?: number;
  shareCount: number;
  isShared: boolean;
  isAuthenticated: boolean;
  /** The full FeedItem — needed for quote post and share modals. */
  post?: FeedItem;
  onShareChange?: (newCount: number, newIsShared: boolean) => void;
  /**
   * When true, render an icon-only ghost trigger — used in the feed card footer
   * where share is a secondary action. Defaults to false (full button with label).
   */
  compact?: boolean;
}

export function ShareButton({
  postId,
  type,
  id,
  shareCount,
  isShared,
  isAuthenticated,
  post,
  onShareChange,
  compact = false,
}: ShareButtonProps) {
  const toast = useToast();
  const { t } = useTranslation('feed');
  const { tenantPath } = useTenant();
  const [localCount, setLocalCount] = useState(shareCount);
  const [localIsShared, setLocalIsShared] = useState(isShared);
  const [isLoading, setIsLoading] = useState(false);

  // Modal states
  const [showQuoteModal, setShowQuoteModal] = useState(false);
  const [showExternalShareModal, setShowExternalShareModal] = useState(false);
  const [showDMModal, setShowDMModal] = useState(false);

  useEffect(() => {
    setLocalCount(shareCount);
  }, [shareCount]);

  useEffect(() => {
    setLocalIsShared(isShared);
  }, [isShared]);

  // Resolve the polymorphic identity with a safe fallback to the legacy postId path.
  const resolvedType: FeedItem['type'] = type ?? 'post';
  const resolvedId: number = id ?? postId ?? 0;
  const isNativePost = resolvedType === 'post';
  // Achievement items (badge_earned, level_up) have no shareable destination —
  // they're personal milestones, not content. Hide the share menu entirely.
  const isShareable = resolvedType !== 'badge_earned' && resolvedType !== 'level_up';

  // Deep-link URL strategy:
  //   1. Prefer getItemDetailPath(post) — canonical detail route for typed items
  //      (listing, event, goal, job, blog, challenge, volunteer, review→profile).
  //   2. For native posts, link to the dedicated PostDetailPage at /feed/posts/:id.
  //   3. Fallback to the polymorphic /feed/item/:type/:id route for any other
  //      reactable type without a module-specific page (poll, discussion, etc.).
  const postUrl = (() => {
    const base = window.location.origin;
    if (post) {
      const detailPath = getItemDetailPath(post);
      if (detailPath) return `${base}${tenantPath(detailPath)}`;
    }
    if (resolvedType === 'post') {
      return `${base}${tenantPath(`/feed/posts/${resolvedId}`)}`;
    }
    return `${base}${tenantPath(`/feed/item/${resolvedType}/${resolvedId}`)}`;
  })();
  const postTitle = post?.title || post?.content?.slice(0, 80) || t('share.default_title');
  const postText = post?.content?.slice(0, 200) || '';

  const handleRepost = useCallback(async () => {
    if (!isAuthenticated) return;

    try {
      setIsLoading(true);

      const newIsShared = !localIsShared;
      const newCount = newIsShared ? localCount + 1 : localCount - 1;
      setLocalIsShared(newIsShared);
      setLocalCount(Math.max(0, newCount));

      // Polymorphic toggle endpoint — single API for all shareable types.
      // POST /v2/shares is idempotent: if a share already exists for this user+item,
      // the backend (ShareService::toggle) removes it and returns {shared: false}.
      // We always POST — never assign `api.post`/`api.delete` to a variable, since
      // that loses the `this` binding and breaks `this.request` inside the method.
      const response = await api.post<{ shared: boolean; count: number }>(
        '/v2/shares',
        { type: resolvedType, id: resolvedId }
      );
      if (!response.success) {
        setLocalIsShared(!newIsShared);
        setLocalCount(localCount);
        // Map backend error codes to specific toast messages. Falls back to the
        // generic share-failed string so unknown codes still give the user feedback.
        const code = (response as { code?: string }).code;
        if (code === 'SELF_SHARE') {
          toast.error(t('share.cannot_share_own_post'));
        } else {
          toast.error(response.error || t('toast.share_failed'));
        }
        return;
      }
      // Reconcile local state from authoritative server response when available.
      if (response.data) {
        setLocalIsShared(response.data.shared);
        setLocalCount(response.data.count);
      }
      if (newIsShared) {
        toast.success(t('toast.post_shared'));
      } else {
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
  }, [isAuthenticated, localIsShared, localCount, resolvedType, resolvedId, isShared, shareCount, onShareChange, toast, t]);

  const handleCopyLink = useCallback(async () => {
    try {
      await navigator.clipboard.writeText(postUrl);
      toast.success(t('share.link_copied'));
    } catch {
      toast.error(t('share.copy_failed'));
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

  if (!isShareable) {
    return null;
  }

  return (
    <>
      <Dropdown placement="bottom">
        <DropdownTrigger>
          {compact ? (
            <Button
              isIconOnly
              size="sm"
              variant="light"
              aria-label={localCount > 0
                ? t('share.button_label_count', { count: localCount })
                : t('share.button_label')}
              className={`${
                localIsShared
                  ? 'text-emerald-500'
                  : 'text-[var(--text-muted)] hover:text-emerald-500'
              } transition-colors min-w-0`}
              isDisabled={!isAuthenticated || isLoading}
            >
              <span className="relative inline-flex items-center">
                <Repeat2
                  className={`w-[18px] h-[18px] transition-all ${
                    localIsShared ? 'text-emerald-500' : ''
                  }`}
                  aria-hidden="true"
                />
                {localCount > 0 && (
                  <span className="absolute -top-1 -right-2 text-[10px] font-semibold tabular-nums bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 rounded-full px-1 min-w-[16px] h-4 flex items-center justify-center leading-none">
                    {localCount}
                  </span>
                )}
              </span>
            </Button>
          ) : (
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
          )}
        </DropdownTrigger>
        <DropdownMenu
          aria-label={t('share.menu_label')}
          onAction={handleDropdownAction}
          disabledKeys={isNativePost ? [] : ['quote']}
        >
          <DropdownItem
            key="repost"
            startContent={<Repeat2 className="w-4 h-4" aria-hidden="true" />}
            description={localIsShared
              ? t('share.repost_remove_desc')
              : t('share.repost_desc')}
          >
            {localIsShared ? t('share.repost_remove') : t('share.repost')}
          </DropdownItem>
          {/*
            Quote Post requires the original to be a feed_posts row (quoted_post_id FK).
            Hidden for typed items — supporting typed quotes needs a schema change
            (quoted_source_type / quoted_source_id on feed_posts).
          */}
          {isNativePost && post ? (
            <DropdownItem
              key="quote"
              startContent={<Quote className="w-4 h-4" aria-hidden="true" />}
              description={t('share.quote_desc')}
            >
              {t('share.quote')}
            </DropdownItem>
          ) : null}
          <DropdownItem
            key="copy"
            startContent={<Copy className="w-4 h-4" aria-hidden="true" />}
          >
            {t('share.copy_link')}
          </DropdownItem>
          <DropdownItem
            key="external"
            startContent={<Share2 className="w-4 h-4" aria-hidden="true" />}
          >
            {t('share.external')}
          </DropdownItem>
          <DropdownItem
            key="dm"
            startContent={<MessageSquare className="w-4 h-4" aria-hidden="true" />}
          >
            {t('share.send_dm')}
          </DropdownItem>
        </DropdownMenu>
      </Dropdown>

      {/* Quote Post Modal */}
      {post && (
        <QuotePostModal
          isOpen={showQuoteModal}
          onClose={() => setShowQuoteModal(false)}
          post={post}
        />
      )}

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
 * SharedByAttribution - Shows "Shared by X" header on reposted content.
 * Rendered by FeedCard when `item.shared_by` is populated.
 */
export function SharedByAttribution({
  user,
}: {
  user: {
    id: number;
    name: string;
    avatar_url?: string | null;
    shared_at?: string;
  };
}) {
  const { t } = useTranslation('feed');
  const { tenantPath } = useTenant();
  return (
    <div className="flex items-center gap-2 text-xs text-[var(--text-subtle)]">
      <Repeat2 className="w-3.5 h-3.5 text-emerald-500 flex-shrink-0" aria-hidden="true" />
      <Link
        to={tenantPath(`/profile/${user.id}`)}
        className="inline-flex items-center gap-1.5 hover:text-[var(--text-primary)] transition-colors"
      >
        <span className="font-semibold text-[var(--text-muted)]">{user.name}</span>
        <span>{t('share.shared_by_text')}</span>
      </Link>
    </div>
  );
}

export default ShareButton;
