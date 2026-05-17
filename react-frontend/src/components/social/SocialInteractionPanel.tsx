// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * SocialInteractionPanel — shared Facebook-style likes, share, and comments.
 *
 * This is the canonical React surface for polymorphic social targets
 * (post/listing/event/goal/poll/review/volunteer/challenge/resource/job/blog/discussion).
 */

import { useCallback, useEffect, useState } from 'react';
import { Button } from '@heroui/react';
import { AnimatePresence, motion } from 'framer-motion';
import Heart from 'lucide-react/icons/heart';
import MessageCircle from 'lucide-react/icons/message-circle';
import { useTranslation } from 'react-i18next';
import { useAuth } from '@/contexts';
import { useSocialInteractions } from '@/hooks/useSocialInteractions';
import { cn } from '@/lib/helpers';
import { CommentsSection } from './CommentsSection';
import { LikersModal } from './LikersModal';
import { ShareButton } from './ShareButton';

export interface SocialInteractionPanelProps {
  targetType: string;
  targetId: number;
  initialLiked?: boolean;
  initialLikesCount?: number;
  initialCommentsCount?: number;
  title?: string;
  description?: string | null;
  showShare?: boolean;
  targetOwnerId?: number | string | null;
  defaultShowComments?: boolean;
  className?: string;
  commentsClassName?: string;
  compact?: boolean;
}

const SHAREABLE_TARGET_TYPES = new Set([
  'post',
  'listing',
  'event',
  'poll',
  'job',
  'blog',
  'discussion',
  'goal',
  'challenge',
  'volunteer',
]);

function idsMatch(left: number | string | null | undefined, right: number | string | null | undefined): boolean {
  return left != null && right != null && String(left) === String(right);
}

function hasCommentHash() {
  return typeof window !== 'undefined' && /^#comment-\d+$/.test(window.location.hash);
}

function getUserName(user: ReturnType<typeof useAuth>['user'], fallback: string): string {
  if (!user) return fallback;
  return user.name || `${user.first_name ?? ''} ${user.last_name ?? ''}`.trim() || fallback;
}

export function SocialInteractionPanel({
  targetType,
  targetId,
  initialLiked = false,
  initialLikesCount = 0,
  initialCommentsCount = 0,
  title,
  description,
  showShare = true,
  targetOwnerId,
  defaultShowComments = false,
  className,
  commentsClassName,
  compact = false,
}: SocialInteractionPanelProps) {
  const { t } = useTranslation('social');
  const { user, isAuthenticated } = useAuth();
  const [showComments, setShowComments] = useState(defaultShowComments || hasCommentHash());
  const [isLikersOpen, setIsLikersOpen] = useState(false);

  const social = useSocialInteractions({
    targetType,
    targetId,
    initialLiked,
    initialLikesCount,
    initialCommentsCount,
  });
  const { commentsLoaded, commentsLoading, loadComments } = social;

  useEffect(() => {
    setShowComments(defaultShowComments || hasCommentHash());
  }, [targetType, targetId, defaultShowComments]);

  useEffect(() => {
    if (typeof window === 'undefined') return undefined;

    const handleHashChange = () => {
      if (hasCommentHash()) {
        setShowComments(true);
      }
    };

    handleHashChange();
    window.addEventListener('hashchange', handleHashChange);
    return () => window.removeEventListener('hashchange', handleHashChange);
  }, [targetType, targetId]);

  useEffect(() => {
    if (showComments && !commentsLoaded && !commentsLoading) {
      void loadComments();
    }
  }, [showComments, commentsLoaded, commentsLoading, loadComments]);

  const toggleComments = useCallback(() => {
    setShowComments((current) => !current);
  }, []);

  const currentUserName = getUserName(user, t('you'));
  const currentUserAvatar = user?.avatar_url ?? user?.avatar ?? undefined;
  const canShareToFeed = targetOwnerId == null || !idsMatch(user?.id, targetOwnerId);

  return (
    <div className={cn('space-y-3', className)}>
      {(social.likesCount > 0 || social.commentsCount > 0) && (
        <div className="flex flex-wrap items-center justify-between gap-2 text-xs text-theme-subtle">
          <div>
            {social.likesCount > 0 && (
              <Button
                variant="light"
                size="sm"
                className="h-auto min-w-0 px-0 py-0 text-xs text-theme-subtle hover:text-theme-primary"
                onPress={() => setIsLikersOpen(true)}
                aria-label={t('view_likes')}
              >
                <span className="inline-flex items-center gap-1.5">
                  <span className="inline-flex h-4 w-4 items-center justify-center rounded-full bg-rose-500">
                    <Heart className="h-2.5 w-2.5 fill-white text-white" aria-hidden="true" />
                  </span>
                  {t('likes_count', { count: social.likesCount })}
                </span>
              </Button>
            )}
          </div>

          {social.commentsCount > 0 && (
            <Button
              variant="light"
              size="sm"
              className="h-auto min-w-0 px-0 py-0 text-xs text-theme-subtle hover:text-theme-primary"
              onPress={() => setShowComments(true)}
              aria-expanded={showComments}
            >
              {t('comments_count', { count: social.commentsCount })}
            </Button>
          )}
        </div>
      )}

      <div
        className={cn(
          'flex flex-wrap items-center gap-2 border-y border-theme-default py-2',
          compact && 'gap-1 py-1.5',
        )}
      >
        <Button
          variant="light"
          size={compact ? 'sm' : 'md'}
          className={cn(
            'flex-1 min-w-[7rem] text-theme-muted hover:bg-rose-500/10 hover:text-rose-500',
            social.isLiked && 'bg-rose-500/10 text-rose-500',
          )}
          startContent={<Heart className={cn('h-4 w-4', social.isLiked && 'fill-current')} aria-hidden="true" />}
          isDisabled={!isAuthenticated || social.isLiking}
          isLoading={social.isLiking}
          aria-pressed={social.isLiked}
          aria-label={t('toggle_like')}
          onPress={() => {
            void social.toggleLike();
          }}
        >
          {social.isLiked ? t('liked') : t('like')}
        </Button>

        <Button
          variant="light"
          size={compact ? 'sm' : 'md'}
          className={cn(
            'flex-1 min-w-[7rem] text-theme-muted hover:bg-primary/10 hover:text-primary',
            showComments && 'bg-primary/10 text-primary',
          )}
          startContent={<MessageCircle className="h-4 w-4" aria-hidden="true" />}
          aria-expanded={showComments}
          aria-label={showComments ? t('close_comments') : t('open_comments')}
          onPress={toggleComments}
        >
          {t('comment_action')}
        </Button>

        {showShare && SHAREABLE_TARGET_TYPES.has(targetType) && (
          <ShareButton
            shareToFeed={social.shareToFeed}
            title={title}
            description={description ?? undefined}
            isAuthenticated={isAuthenticated}
            canShareToFeed={canShareToFeed}
            shareToFeedDisabledReason={t('cannot_share_own_content')}
            className={cn('flex-1 min-w-[7rem]', compact && 'h-8 text-sm')}
          />
        )}
      </div>

      <AnimatePresence initial={false}>
        {showComments && (
          <motion.div
            initial={{ opacity: 0, height: 0 }}
            animate={{ opacity: 1, height: 'auto' }}
            exit={{ opacity: 0, height: 0 }}
            transition={{ duration: 0.2, ease: 'easeInOut' }}
            className={cn('overflow-hidden', commentsClassName)}
          >
            <CommentsSection
              comments={social.comments}
              commentsCount={social.commentsCount}
              commentsLoading={social.commentsLoading}
              commentsLoaded={social.commentsLoaded}
              loadComments={social.loadComments}
              submitComment={social.submitComment}
              editComment={social.editComment}
              deleteComment={social.deleteComment}
              toggleReaction={social.toggleReaction}
              searchMentions={social.searchMentions}
              isAuthenticated={isAuthenticated}
              currentUserId={user?.id}
              currentUserAvatar={currentUserAvatar}
              currentUserName={currentUserName}
            />
          </motion.div>
        )}
      </AnimatePresence>

      <LikersModal
        isOpen={isLikersOpen}
        onClose={() => setIsLikersOpen(false)}
        loadLikers={social.loadLikers}
        likesCount={social.likesCount}
      />
    </div>
  );
}

export default SocialInteractionPanel;
