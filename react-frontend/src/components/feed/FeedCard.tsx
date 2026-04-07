// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * FeedCard — renders an individual feed item with content, actions, comments, and polls.
 * Extracted from FeedPage.tsx for maintainability.
 */

import React, { useState, useEffect, useRef, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import {
  Button,
  Avatar,
  Chip,
  Dropdown,
  DropdownTrigger,
  DropdownMenu,
  DropdownItem,
  Progress,
  Input,
  Tooltip,
  Skeleton,
  Divider,
  Card,
  CardBody,
} from '@heroui/react';
import {
  Heart,
  MessageCircle,
  Send,
  MoreHorizontal,
  Eye,
  EyeOff,
  VolumeX,
  Flag,
  Trash2,
  Check,
  Star,
  Clock,
  TrendingUp,
  BarChart3,
  Target,
  ShoppingBag,
  Calendar,
  MapPin,
  ArrowRight,
  BookOpen,
  Users,
  Trophy,
  Zap,
  Pencil,
  X,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard, BottomSheet } from '@/components/ui';
import { useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl, resolveAssetUrl, formatRelativeTime, formatDate, formatTime } from '@/lib/helpers';
import { useFeedTracking } from '@/hooks/useFeedTracking';
import type { FeedItem, FeedComment, PollData } from './types';
import { getAuthor, getItemDetailPath, getItemDetailLabel } from './types';
import { FeedContentRenderer } from './FeedContentRenderer';
import { ImageCarousel } from './ImageCarousel';
import { MediaGrid } from './MediaGrid';
import { VideoPlayer } from './VideoPlayer';
import { ReactionPicker, ReactionSummary, type ReactionType } from '@/components/social';
import { LinkPreviewCard } from '@/components/social/LinkPreviewCard';
import { MentionRenderer } from '@/components/social/MentionRenderer';
import { UserHoverCard } from '@/components/social/UserHoverCard';
import { SafeHtml, containsHtml } from '@/components/ui/SafeHtml';
import { ShareButton } from './ShareButton';
import { QuotedPostEmbed } from './QuotedPostEmbed';
import { BookmarkButton } from '@/components/social';
import { PostAnalyticsModal } from './PostAnalyticsModal';

/* ───────────────────────── Props ───────────────────────── */

export interface FeedCardProps {
  item: FeedItem;
  /** Called with the feed item when the user toggles the like button (legacy). */
  onToggleLike: (item: FeedItem) => void;
  /** Called when the user reacts to a post with an emoji reaction. */
  onReact?: (item: FeedItem, reactionType: ReactionType) => void;
  /** Called with the feed item when the user hides a post. */
  onHidePost: (item: FeedItem) => void;
  /** Called with the feed item when the user mutes the post author. */
  onMuteUser: (item: FeedItem) => void;
  /** Called with the feed item when the user reports a post. */
  onReportPost: (item: FeedItem) => void;
  /** Called with the feed item when the user deletes their own post. */
  onDeletePost: (item: FeedItem) => void;
  /** Called with the feed item when an admin deletes any post. */
  onAdminDeletePost?: (item: FeedItem) => void;
  onVotePoll: (pollId: number, optionId: number) => void;
  isAuthenticated: boolean;
  currentUserId?: number;
  /** Whether the current user is an admin (shows admin delete on all posts). */
  isAdmin?: boolean;
  /** If true, comments section is open and loaded immediately on mount. */
  defaultShowComments?: boolean;
}

/* ───────────────────────── Type Badge Config ───────────────────────── */

const typeConfig = {
  post: { labelKey: null, color: 'default' as const, icon: null, gradient: '' },
  listing: {
    labelKey: 'card.type_listing',
    color: 'primary' as const,
    icon: <ShoppingBag className="w-3 h-3" aria-hidden="true" />,
    gradient: 'from-indigo-500/10 to-blue-500/10',
  },
  event: {
    labelKey: 'card.type_event',
    color: 'success' as const,
    icon: <Calendar className="w-3 h-3" aria-hidden="true" />,
    gradient: 'from-emerald-500/10 to-green-500/10',
  },
  poll: {
    labelKey: 'card.type_poll',
    color: 'warning' as const,
    icon: <BarChart3 className="w-3 h-3" aria-hidden="true" />,
    gradient: 'from-amber-500/10 to-orange-500/10',
  },
  goal: {
    labelKey: 'card.type_goal',
    color: 'secondary' as const,
    icon: <Target className="w-3 h-3" aria-hidden="true" />,
    gradient: 'from-purple-500/10 to-pink-500/10',
  },
  review: {
    labelKey: 'card.type_review',
    color: 'warning' as const,
    icon: <Star className="w-3 h-3" aria-hidden="true" />,
    gradient: 'from-amber-500/10 to-yellow-500/10',
  },
  job: {
    labelKey: 'card.type_job',
    color: 'primary' as const,
    icon: <TrendingUp className="w-3 h-3" aria-hidden="true" />,
    gradient: 'from-blue-500/10 to-cyan-500/10',
  },
  challenge: {
    labelKey: 'card.type_challenge',
    color: 'secondary' as const,
    icon: <Target className="w-3 h-3" aria-hidden="true" />,
    gradient: 'from-violet-500/10 to-purple-500/10',
  },
  volunteer: {
    labelKey: 'card.type_volunteer',
    color: 'success' as const,
    icon: <Heart className="w-3 h-3" aria-hidden="true" />,
    gradient: 'from-green-500/10 to-emerald-500/10',
  },
  blog: {
    labelKey: 'card.type_blog',
    color: 'primary' as const,
    icon: <BookOpen className="w-3 h-3" aria-hidden="true" />,
    gradient: 'from-sky-500/10 to-blue-500/10',
  },
  discussion: {
    labelKey: 'card.type_discussion',
    color: 'secondary' as const,
    icon: <Users className="w-3 h-3" aria-hidden="true" />,
    gradient: 'from-fuchsia-500/10 to-purple-500/10',
  },
  badge_earned: {
    labelKey: 'card.type_badge_earned',
    color: 'warning' as const,
    icon: <Trophy className="w-3 h-3" aria-hidden="true" />,
    gradient: 'from-yellow-500/10 to-amber-500/10',
  },
  level_up: {
    labelKey: 'card.type_level_up',
    color: 'success' as const,
    icon: <Zap className="w-3 h-3" aria-hidden="true" />,
    gradient: 'from-emerald-500/10 to-teal-500/10',
  },
};

/* ────────────── Heart Overlay (double-tap to like) ────────────── */

function HeartOverlay({ show }: { show: boolean }) {
  return (
    <AnimatePresence>
      {show && (
        <motion.div
          key="heart-overlay"
          initial={{ scale: 0, opacity: 0 }}
          animate={{ scale: 1.2, opacity: 1 }}
          exit={{ scale: 1.4, opacity: 0 }}
          transition={{ duration: 0.4, ease: 'easeOut' }}
          className="absolute inset-0 flex items-center justify-center pointer-events-none z-10"
        >
          <Heart className="w-20 h-20 text-white fill-white drop-shadow-lg" />
        </motion.div>
      )}
    </AnimatePresence>
  );
}

/**
 * Hook to detect double-tap / double-click on an element.
 * Returns a click handler and a ref for the timeout.
 */
function useDoubleTap(onDoubleTap: () => void, delay = 300) {
  const lastTapRef = useRef<number>(0);

  const handleTap = useCallback(() => {
    const now = Date.now();
    if (now - lastTapRef.current < delay) {
      lastTapRef.current = 0;
      onDoubleTap();
    } else {
      lastTapRef.current = now;
    }
  }, [onDoubleTap, delay]);

  return handleTap;
}

/* ───────────────────────── Comment Item ───────────────────────── */

interface CommentItemProps {
  comment: FeedComment;
  currentUserId?: number;
  isAuthenticated: boolean;
  onEdit: (commentId: number, content: string) => Promise<boolean>;
  onDelete: (commentId: number) => Promise<boolean>;
}

export const CommentItem = React.memo(function CommentItem({ comment, currentUserId, isAuthenticated, onEdit, onDelete }: CommentItemProps) {
  const { t } = useTranslation('feed');
  const { tenantPath } = useTenant();
  const [showReplies, setShowReplies] = useState(false);
  const [isEditing, setIsEditing] = useState(false);
  const [editContent, setEditContent] = useState(comment.content);
  const [isSubmittingEdit, setIsSubmittingEdit] = useState(false);

  const isOwn = currentUserId === comment.author.id || comment.is_own;

  const handleSaveEdit = async () => {
    if (!editContent.trim()) return;
    setIsSubmittingEdit(true);
    const ok = await onEdit(comment.id, editContent.trim());
    if (ok) setIsEditing(false);
    setIsSubmittingEdit(false);
  };

  const handleDelete = async () => {
    if (!window.confirm(t('card.delete_comment_confirm', 'Delete this comment?'))) return;
    await onDelete(comment.id);
  };

  return (
    <div className="flex items-start gap-2.5">
      <Link to={tenantPath(`/profile/${comment.author.id}`)}>
        <Avatar
          name={comment.author.name}
          src={resolveAvatarUrl(comment.author.avatar)}
          size="sm"
          className="w-7 h-7 flex-shrink-0 ring-2 ring-white/10"
        />
      </Link>
      <div className="flex-1 min-w-0">
        <div className="bg-[var(--surface-elevated)] rounded-2xl px-3.5 py-2.5 border border-[var(--border-default)]">
          <div className="flex items-center gap-2">
            <Link
              to={tenantPath(`/profile/${comment.author.id}`)}
              className="text-xs font-semibold text-[var(--text-primary)] hover:text-[var(--color-primary)] transition-colors"
            >
              {comment.author.name}
            </Link>
            {comment.edited && (
              <span className="text-[10px] text-[var(--text-subtle)] italic">({t('card.edited')})</span>
            )}
          </div>
          {isEditing ? (
            <div className="mt-1.5 space-y-2">
              <Input
                value={editContent}
                onChange={(e) => setEditContent(e.target.value)}
                size="sm"
                classNames={{
                  input: 'bg-transparent text-[var(--text-primary)] text-xs',
                  inputWrapper: 'bg-[var(--surface-hover)] border-[var(--border-default)] min-h-[32px]',
                }}
              />
              <div className="flex gap-1.5">
                <Button
                  size="sm"
                  isIconOnly
                  variant="flat"
                  className="w-6 h-6 min-w-0 bg-emerald-500/20 text-emerald-500"
                  onPress={handleSaveEdit}
                  isLoading={isSubmittingEdit}
                  aria-label={t('card.save', 'Save')}
                >
                  <Check className="w-3 h-3" />
                </Button>
                <Button
                  size="sm"
                  isIconOnly
                  variant="flat"
                  className="w-6 h-6 min-w-0 bg-red-500/10 text-red-500"
                  onPress={() => { setIsEditing(false); setEditContent(comment.content); }}
                  aria-label={t('card.cancel', 'Cancel')}
                >
                  <X className="w-3 h-3" />
                </Button>
              </div>
            </div>
          ) : containsHtml(comment.content) ? (
            <SafeHtml content={comment.content} className="text-xs text-[var(--text-secondary)] mt-0.5 whitespace-pre-wrap leading-relaxed" as="div" />
          ) : (
            <p className="text-xs text-[var(--text-secondary)] mt-0.5 whitespace-pre-wrap leading-relaxed"><MentionRenderer text={comment.content} showUserCard={false} /></p>
          )}
        </div>
        <div className="flex items-center gap-3 mt-1 px-1">
          <span className="text-[10px] text-[var(--text-subtle)]">
            <Clock className="w-2.5 h-2.5 inline mr-0.5 -mt-px" aria-hidden="true" />
            {formatRelativeTime(comment.created_at)}
          </span>
          {comment.replies && comment.replies.length > 0 && (
            <Button
              variant="light"
              size="sm"
              className="text-[10px] text-[var(--color-primary)] p-0 min-w-0 h-auto"
              onPress={() => setShowReplies(!showReplies)}
            >
              {showReplies ? t('card.hide') : `${comment.replies.length}`} {comment.replies.length === 1 ? t('card.reply') : t('card.replies')}
            </Button>
          )}
          {isAuthenticated && !isEditing && isOwn && (
            <>
              <Button
                variant="light"
                size="sm"
                onPress={() => { setIsEditing(true); setEditContent(comment.content); }}
                className="text-[10px] text-[var(--text-subtle)] hover:text-[var(--text-primary)] flex items-center gap-0.5 h-auto p-0 min-w-0"
                startContent={<Pencil className="w-2.5 h-2.5" aria-hidden="true" />}
              >
                {t('card.edit', 'Edit')}
              </Button>
              <Button
                variant="light"
                size="sm"
                onPress={handleDelete}
                className="text-[10px] text-red-400 hover:text-red-500 flex items-center gap-0.5 h-auto p-0 min-w-0"
                startContent={<Trash2 className="w-2.5 h-2.5" aria-hidden="true" />}
              >
                {t('card.delete', 'Delete')}
              </Button>
            </>
          )}
        </div>

        {/* Nested Replies */}
        <AnimatePresence>
          {showReplies && comment.replies && (
            <motion.div
              initial={{ opacity: 0, height: 0 }}
              animate={{ opacity: 1, height: 'auto' }}
              exit={{ opacity: 0, height: 0 }}
              className="mt-2 ml-2 space-y-2 border-l-2 border-[var(--color-primary)]/30 pl-3"
            >
              {comment.replies.map((reply) => (
                <div key={reply.id} className="flex items-start gap-2">
                  <Avatar
                    name={reply.author.name}
                    src={resolveAvatarUrl(reply.author.avatar)}
                    size="sm"
                    className="w-6 h-6 flex-shrink-0"
                  />
                  <div>
                    <div className="bg-[var(--surface-elevated)] rounded-xl px-2.5 py-1.5 border border-[var(--border-default)]">
                      <span className="text-[10px] font-semibold text-[var(--text-primary)]">{reply.author.name}</span>
                      <p className="text-[11px] text-[var(--text-secondary)] whitespace-pre-wrap">{reply.content}</p>
                    </div>
                    <span className="text-[10px] text-[var(--text-subtle)] ml-1">
                      {formatRelativeTime(reply.created_at)}
                    </span>
                  </div>
                </div>
              ))}
            </motion.div>
          )}
        </AnimatePresence>
      </div>
    </div>
  );
});

/* ───────────────────────── Feed Card ───────────────────────── */

const FeedCard = React.memo(function FeedCard({
  item,
  onToggleLike,
  onReact,
  onHidePost,
  onMuteUser,
  onReportPost,
  onDeletePost,
  onAdminDeletePost,
  onVotePoll,
  isAuthenticated,
  currentUserId,
  isAdmin,
  defaultShowComments = false,
}: FeedCardProps) {
  const { t } = useTranslation('feed');
  const { tenantPath } = useTenant();
  const [showComments, setShowComments] = useState(defaultShowComments);
  const [comments, setComments] = useState<FeedComment[]>([]);
  const [isLoadingComments, setIsLoadingComments] = useState(defaultShowComments);
  const [newComment, setNewComment] = useState('');
  const [isSubmittingComment, setIsSubmittingComment] = useState(false);
  const [localCommentsCount, setLocalCommentsCount] = useState(item.comments_count);
  const [pollData, setPollData] = useState<PollData | null>(item.poll_data ?? null);
  const [isLoadingPoll, setIsLoadingPoll] = useState(false);

  // Double-tap to like
  const [showHeartOverlay, setShowHeartOverlay] = useState(false);
  const heartTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Bottom sheet for mobile options menu
  const [isOptionsSheetOpen, setIsOptionsSheetOpen] = useState(false);

  const handleDoubleTapLike = useCallback(() => {
    if (!isAuthenticated) return;
    // Only like if not already liked
    if (!item.is_liked) {
      onToggleLike(item);
    }
    // Always show the heart animation
    setShowHeartOverlay(true);
    if (heartTimeoutRef.current) clearTimeout(heartTimeoutRef.current);
    heartTimeoutRef.current = setTimeout(() => setShowHeartOverlay(false), 800);
  }, [isAuthenticated, item, onToggleLike]);

  const doubleTapHandler = useDoubleTap(handleDoubleTapLike);

  // Clean up heart timeout on unmount
  useEffect(() => {
    return () => {
      if (heartTimeoutRef.current) clearTimeout(heartTimeoutRef.current);
    };
  }, []);

  // Post analytics modal
  const [showAnalytics, setShowAnalytics] = useState(false);

  // View tracking — fire once per session per post when entering viewport
  const viewTrackedRef = useRef(false);
  const viewTargetRef = useRef<HTMLDivElement | null>(null);

  useEffect(() => {
    // Reset tracking flag when item changes
    viewTrackedRef.current = false;

    const el = viewTargetRef.current;
    if (!el) return;

    const observer = new IntersectionObserver(
      (entries) => {
        if (entries[0]?.isIntersecting && !viewTrackedRef.current) {
          viewTrackedRef.current = true;
          api.post(`/v2/feed/posts/${item.id}/view`).catch(() => {});
          observer.disconnect();
        }
      },
      { threshold: 0.5 }
    );
    observer.observe(el);

    return () => observer.disconnect();
  }, [item.id]);

  const author = getAuthor(item);
  const isOwnPost = currentUserId === author.id;
  const config = typeConfig[item.type];
  const typeLabel = config.labelKey ? t(config.labelKey) : null;
  const detailPath = getItemDetailPath(item);
  const detailLabel = getItemDetailLabel(item);
  const { ref: trackingRef, recordClick } = useFeedTracking(item.id, isAuthenticated);

  // Load poll data lazily ONLY when the item is a poll, poll_data was NOT
  // provided inline, and we haven't already loaded or started loading it.
  useEffect(() => {
    if (item.type === 'poll' && !item.poll_data && !pollData && !isLoadingPoll) {
      setIsLoadingPoll(true);
      api.get<PollData>(`/v2/feed/polls/${item.id}`)
        .then((response) => {
          if (response.success && response.data) {
            setPollData(response.data);
          }
        })
        .catch((err) => logError('Failed to load poll', err))
        .finally(() => setIsLoadingPoll(false));
    }
  }, [item.type, item.id, item.poll_data, pollData, isLoadingPoll]);

  const handleVote = (optionId: number) => {
    if (!pollData) return;
    onVotePoll(item.id, optionId);

    // Optimistic update: update pollData locally
    const totalBefore = pollData.total_votes + (pollData.user_vote_option_id ? 0 : 1);
    const updatedOptions = pollData.options.map((opt) => {
      let newCount = opt.vote_count;
      if (opt.id === pollData.user_vote_option_id) newCount -= 1;
      if (opt.id === optionId) newCount += 1;
      return {
        ...opt,
        vote_count: Math.max(0, newCount),
        percentage: totalBefore > 0 ? Math.round((Math.max(0, newCount) / totalBefore) * 100 * 10) / 10 : 0,
      };
    });

    setPollData({
      ...pollData,
      options: updatedOptions,
      total_votes: totalBefore,
      user_vote_option_id: optionId,
    });
  };

  const loadComments = async () => {
    try {
      setIsLoadingComments(true);
      const response = await api.get<{ comments: FeedComment[] }>(
        `/v2/comments?target_type=${item.type}&target_id=${item.id}`
      );

      if (response.success && response.data) {
        setComments(response.data.comments ?? []);
      }
    } catch (err) {
      logError('Failed to load comments', err);
    } finally {
      setIsLoadingComments(false);
    }
  };

  // Auto-load comments on mount when defaultShowComments is true
  useEffect(() => {
    if (defaultShowComments) {
      loadComments();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- load once on mount; defaultShowComments is initial prop
  }, []);

  const toggleComments = () => {
    if (!showComments) {
      loadComments();
    }
    setShowComments(!showComments);
  };

  const handleSubmitComment = async () => {
    if (!newComment.trim()) return;

    try {
      setIsSubmittingComment(true);
      const response = await api.post('/v2/comments', {
        target_type: item.type,
        target_id: item.id,
        content: newComment.trim(),
      });

      if (response.success) {
        setNewComment('');
        setLocalCommentsCount((prev) => prev + 1);
        loadComments(); // Reload to show new comment
      }
    } catch (err) {
      logError('Failed to submit comment', err);
    } finally {
      setIsSubmittingComment(false);
    }
  };

  const handleEditComment = async (commentId: number, content: string): Promise<boolean> => {
    try {
      const response = await api.put(`/v2/comments/${commentId}`, { content });
      if (response.success) {
        loadComments();
        return true;
      }
      return false;
    } catch (err) {
      logError('Failed to edit comment', err);
      return false;
    }
  };

  const handleDeleteComment = async (commentId: number): Promise<boolean> => {
    try {
      const response = await api.delete(`/v2/comments/${commentId}`);
      if (response.success) {
        setLocalCommentsCount((prev) => Math.max(0, prev - 1));
        loadComments();
        return true;
      }
      return false;
    } catch (err) {
      logError('Failed to delete comment', err);
      return false;
    }
  };

  return (
    <GlassCard ref={(el: HTMLDivElement | null) => { (trackingRef as React.MutableRefObject<HTMLDivElement | null>).current = el; viewTargetRef.current = el; }} hoverable className="overflow-hidden group">
      {/* Type accent bar */}
      {typeLabel && (
        <div className={`h-0.5 bg-gradient-to-r ${config.gradient}`} />
      )}

      <div className="p-5">
        {/* Header */}
        <div className="flex items-start justify-between mb-4">
          <div className="flex items-center gap-3">
            <UserHoverCard userId={author.id}>
              <Link to={tenantPath(`/profile/${author.id}`)} className="relative">
                <Avatar
                  name={author.name}
                  src={resolveAvatarUrl(author.avatar)}
                  size="md"
                  className="ring-2 ring-[var(--border-default)] group-hover:ring-[var(--color-primary)]/30 transition-all"
                  isBordered
                />
              </Link>
            </UserHoverCard>
            <div className="min-w-0">
              <div className="flex items-center gap-2 flex-wrap">
                <UserHoverCard userId={author.id}>
                  <Link
                    to={tenantPath(`/profile/${author.id}`)}
                    className="font-semibold text-[var(--text-primary)] hover:text-[var(--color-primary)] transition-colors text-sm truncate"
                  >
                    {author.name}
                  </Link>
                </UserHoverCard>
                {typeLabel && (
                  detailPath ? (
                    <Link to={tenantPath(detailPath)} onClick={recordClick}>
                      <Chip
                        size="sm"
                        variant="flat"
                        color={config.color}
                        startContent={config.icon}
                        className="text-[10px] h-5 cursor-pointer hover:opacity-80 transition-opacity"
                      >
                        {typeLabel}
                      </Chip>
                    </Link>
                  ) : (
                    <Chip
                      size="sm"
                      variant="flat"
                      color={config.color}
                      startContent={config.icon}
                      className="text-[10px] h-5"
                    >
                      {typeLabel}
                    </Chip>
                  )
                )}
              </div>
              <Tooltip content={new Date(item.created_at).toLocaleString()} placement="bottom" delay={500} closeDelay={0} size="sm">
                <p className="text-xs text-[var(--text-subtle)] flex items-center gap-1 cursor-default">
                  <Clock className="w-3 h-3" aria-hidden="true" />
                  {formatRelativeTime(item.created_at)}
                </p>
              </Tooltip>
            </div>
          </div>

          {/* 3-dot moderation menu — Dropdown on desktop, BottomSheet on mobile */}
          {isAuthenticated && (
            <>
              {/* Desktop: HeroUI Dropdown */}
              <div className="hidden sm:block">
                <Dropdown placement="bottom-end">
                  <DropdownTrigger>
                    <Button
                      isIconOnly
                      size="sm"
                      variant="light"
                      className="text-[var(--text-subtle)] hover:text-[var(--text-primary)] min-w-0 opacity-0 group-hover:opacity-100 transition-opacity"
                      aria-label={t('card.post_options', 'Post options')}
                    >
                      <MoreHorizontal className="w-4 h-4" />
                    </Button>
                  </DropdownTrigger>
                  <DropdownMenu aria-label={t('card.post_actions', 'Post actions')}>
                    {isOwnPost ? (
                      <>
                        <DropdownItem
                          key="analytics"
                          startContent={<BarChart3 className="w-4 h-4" aria-hidden="true" />}
                          onPress={() => setShowAnalytics(true)}
                        >
                          {t('card.view_analytics', 'View Analytics')}
                        </DropdownItem>
                        <DropdownItem
                          key="delete"
                          startContent={<Trash2 className="w-4 h-4" aria-hidden="true" />}
                          className="text-danger"
                          color="danger"
                          onPress={() => onDeletePost(item)}
                        >
                          {t('card.delete_post')}
                        </DropdownItem>
                      </>
                    ) : (
                      <>
                        <DropdownItem
                          key="hide"
                          startContent={<EyeOff className="w-4 h-4" aria-hidden="true" />}
                          onPress={() => onHidePost(item)}
                        >
                          {t('card.hide_post')}
                        </DropdownItem>
                        <DropdownItem
                          key="mute"
                          startContent={<VolumeX className="w-4 h-4" aria-hidden="true" />}
                          onPress={() => onMuteUser(item)}
                        >
                          {t('card.mute_user', { name: author.name })}
                        </DropdownItem>
                        <DropdownItem
                          key="report"
                          startContent={<Flag className="w-4 h-4" aria-hidden="true" />}
                          className="text-danger"
                          color="danger"
                          onPress={() => onReportPost(item)}
                        >
                          {t('card.report_post')}
                        </DropdownItem>
                        {isAdmin && onAdminDeletePost && (
                          <DropdownItem
                            key="admin-delete"
                            startContent={<Trash2 className="w-4 h-4" aria-hidden="true" />}
                            className="text-danger"
                            color="danger"
                            onPress={() => onAdminDeletePost(item)}
                          >
                            {t('card.admin_delete', 'Delete (Admin)')}
                          </DropdownItem>
                        )}
                      </>
                    )}
                  </DropdownMenu>
                </Dropdown>
              </div>

              {/* Mobile: Button that opens BottomSheet */}
              <Button
                isIconOnly
                size="sm"
                variant="light"
                className="sm:hidden text-[var(--text-subtle)] hover:text-[var(--text-primary)] min-w-0"
                aria-label={t('card.post_options', 'Post options')}
                onPress={() => setIsOptionsSheetOpen(true)}
              >
                <MoreHorizontal className="w-4 h-4" />
              </Button>

              {/* Mobile BottomSheet for post options */}
              <BottomSheet
                isOpen={isOptionsSheetOpen}
                onClose={() => setIsOptionsSheetOpen(false)}
                title={t('card.post_options', 'Post options')}
                snapPoints={['auto']}
              >
                <div className="flex flex-col gap-1">
                  {isOwnPost ? (
                    <>
                      <Button
                        variant="light"
                        className="justify-start text-[var(--text-primary)]"
                        startContent={<BarChart3 className="w-4 h-4" aria-hidden="true" />}
                        onPress={() => { setIsOptionsSheetOpen(false); setShowAnalytics(true); }}
                      >
                        {t('card.view_analytics', 'View Analytics')}
                      </Button>
                      <Button
                        variant="light"
                        className="justify-start text-danger"
                        startContent={<Trash2 className="w-4 h-4" aria-hidden="true" />}
                        onPress={() => { setIsOptionsSheetOpen(false); onDeletePost(item); }}
                      >
                        {t('card.delete_post')}
                      </Button>
                    </>
                  ) : (
                    <>
                      <Button
                        variant="light"
                        className="justify-start text-[var(--text-primary)]"
                        startContent={<EyeOff className="w-4 h-4" aria-hidden="true" />}
                        onPress={() => { setIsOptionsSheetOpen(false); onHidePost(item); }}
                      >
                        {t('card.hide_post')}
                      </Button>
                      <Button
                        variant="light"
                        className="justify-start text-[var(--text-primary)]"
                        startContent={<VolumeX className="w-4 h-4" aria-hidden="true" />}
                        onPress={() => { setIsOptionsSheetOpen(false); onMuteUser(item); }}
                      >
                        {t('card.mute_user', { name: author.name })}
                      </Button>
                      <Button
                        variant="light"
                        className="justify-start text-danger"
                        startContent={<Flag className="w-4 h-4" aria-hidden="true" />}
                        onPress={() => { setIsOptionsSheetOpen(false); onReportPost(item); }}
                      >
                        {t('card.report_post')}
                      </Button>
                      {isAdmin && onAdminDeletePost && (
                        <Button
                          variant="light"
                          className="justify-start text-danger"
                          startContent={<Trash2 className="w-4 h-4" aria-hidden="true" />}
                          onPress={() => { setIsOptionsSheetOpen(false); onAdminDeletePost(item); }}
                        >
                          {t('card.admin_delete', 'Delete (Admin)')}
                        </Button>
                      )}
                    </>
                  )}
                </div>
              </BottomSheet>
            </>
          )}
        </div>

        {/* Content */}
        <div className="mb-4">
          {item.title && item.title !== item.content && (
            detailPath ? (
              <Link
                to={tenantPath(detailPath)}
                className="text-sm font-semibold text-[var(--text-primary)] hover:text-[var(--color-primary)] transition-colors mb-1.5 block"
              >
                {item.title}
              </Link>
            ) : (
              <p className="text-sm font-semibold text-[var(--text-primary)] mb-1.5">{item.title}</p>
            )
          )}
          <FeedContentRenderer
            content={item.content}
            truncated={item.content_truncated}
            detailPath={detailPath ? tenantPath(detailPath) : undefined}
          />
        </div>

        {/* Link Previews */}
        {item.link_previews && item.link_previews.length > 0 && (
          <div className={`mb-4 ${item.link_previews.length > 1 ? 'space-y-2' : ''}`}>
            {item.link_previews.map((lp) => (
              <LinkPreviewCard
                key={lp.url}
                preview={lp}
                compact={item.link_previews!.length > 1}
              />
            ))}
          </div>
        )}
        {/* Quoted Post Embed (quote repost) */}
        {item.quoted_post && (
          <div className="mb-4">
            <QuotedPostEmbed post={item.quoted_post} />
          </div>
        )}

        {/* Event/Listing metadata */}
        {(item.type === 'event' || item.type === 'listing') && (item.start_date || item.location) && (
          <div className="mb-4 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-[var(--text-muted)]">
            {item.start_date && (
              <span className="flex items-center gap-1.5">
                <Calendar className="w-3.5 h-3.5 text-emerald-500" aria-hidden="true" />
                <span>{formatDate(item.start_date, { month: 'short', day: 'numeric', year: 'numeric' })}</span>
                <span className="text-[var(--text-subtle)]">{formatTime(item.start_date)}</span>
              </span>
            )}
            {item.location && (
              <span className="flex items-center gap-1.5">
                <MapPin className="w-3.5 h-3.5 text-emerald-500" aria-hidden="true" />
                <span>{item.location}</span>
              </span>
            )}
          </div>
        )}

        {/* Media: Multi-image carousel/grid, single image/video, or placeholder */}
        {/* Wrapped with double-tap-to-like detection */}
        {item.media && item.media.length > 1 ? (
          /* Multi-media: use grid for 2-4, carousel for 5+ */
          <div className="mb-4 -mx-5 overflow-hidden relative" onClick={doubleTapHandler}>
            <HeartOverlay show={showHeartOverlay} />
            {item.media.length <= 4 ? (
              <MediaGrid media={item.media} className="mx-5" />
            ) : (
              <ImageCarousel media={item.media} className="mx-5" />
            )}
          </div>
        ) : item.media && item.media.length === 1 && item.media[0] ? (
          /* Single media item — use VideoPlayer for video, ImageCarousel for image */
          <div className="mb-4 -mx-5 overflow-hidden relative" onClick={doubleTapHandler}>
            <HeartOverlay show={showHeartOverlay} />
            {item.media[0].media_type === 'video' ? (
              <VideoPlayer media={item.media[0]} className="mx-5" />
            ) : (
              <ImageCarousel media={item.media} className="mx-5" />
            )}
          </div>
        ) : item.image_url ? (
          <div className="mb-4 -mx-5 overflow-hidden relative" onClick={doubleTapHandler}>
            <HeartOverlay show={showHeartOverlay} />
            {detailPath ? (
              <Link to={tenantPath(detailPath)}>
                <img
                  src={resolveAssetUrl(item.image_url)}
                  alt={t('card.image_alt', '{{type}} image by {{name}}', { type: typeLabel ?? t('card.type_post', 'Post'), name: author.name })}
                  className="w-full max-h-[28rem] object-cover hover:scale-[1.02] transition-transform duration-500"
                  loading="lazy"
                  width={800}
                  height={448}
                  onError={(e) => { (e.target as HTMLImageElement).style.display = 'none'; }}
                />
              </Link>
            ) : (
              <img
                src={resolveAssetUrl(item.image_url)}
                alt={t('card.image_alt', '{{type}} image by {{name}}', { type: t('card.type_post', 'Post'), name: author.name })}
                className="w-full max-h-[28rem] object-cover hover:scale-[1.02] transition-transform duration-500"
                loading="lazy"
                width={800}
                height={448}
              />
            )}
          </div>
        ) : null}

        {/* Poll Display */}
        {item.type === 'poll' && (
          <div className="mb-4">
            {isLoadingPoll ? (
              <div className="space-y-3">
                {[1, 2, 3].map((i) => (
                  <Skeleton key={i} className="h-10 w-full rounded-lg" />
                ))}
              </div>
            ) : pollData ? (
              <Card shadow="none" className="bg-[var(--surface-elevated)] border border-[var(--border-default)]">
                <CardBody className="gap-3 p-4">
                  {pollData.options.map((option) => {
                    const isVoted = pollData.user_vote_option_id === option.id;
                    const hasVoted = pollData.user_vote_option_id !== null;

                    return (
                      <div key={option.id}>
                        {hasVoted ? (
                          /* Show results */
                          <div className="relative">
                            <div className="flex items-center justify-between mb-1.5">
                              <span className={`text-sm ${isVoted ? 'font-semibold text-[var(--color-primary)]' : 'text-[var(--text-primary)]'}`}>
                                {isVoted && <Check className="w-3.5 h-3.5 inline mr-1.5 -mt-0.5" aria-hidden="true" />}
                                {option.text}
                              </span>
                              <span className={`text-xs font-medium ml-2 ${isVoted ? 'text-[var(--color-primary)]' : 'text-[var(--text-muted)]'}`}>
                                {option.percentage}%
                              </span>
                            </div>
                            <Progress
                              value={option.percentage}
                              size="sm"
                              color={isVoted ? 'primary' : 'default'}
                              classNames={{
                                track: 'bg-[var(--surface-hover)]',
                                indicator: isVoted ? 'bg-gradient-to-r from-indigo-500 to-purple-500' : '',
                              }}
                              aria-label={`${option.text}: ${option.percentage}%`}
                            />
                          </div>
                        ) : (
                          /* Show vote button */
                          <Button
                            variant="bordered"
                            size="sm"
                            className="w-full justify-start text-[var(--text-primary)] border-[var(--border-default)] hover:border-[var(--color-primary)] hover:bg-[var(--color-primary)]/5 transition-all"
                            onPress={() => handleVote(option.id)}
                          >
                            {option.text}
                          </Button>
                        )}
                      </div>
                    );
                  })}
                  <p className="text-xs text-[var(--text-subtle)] flex items-center gap-1.5 pt-1">
                    <TrendingUp className="w-3 h-3" aria-hidden="true" />
                    {pollData.total_votes} {pollData.total_votes === 1 ? t('card.vote') : t('card.votes')}
                  </p>
                </CardBody>
              </Card>
            ) : null}
          </div>
        )}

        {/* Review Display */}
        {item.type === 'review' && item.rating && item.receiver && (
          <Card shadow="none" className="mb-4 bg-gradient-to-br from-amber-500/10 to-orange-500/10 border border-amber-500/20">
            <CardBody className="p-4">
              <div className="flex items-center justify-between mb-2">
                <div className="flex items-center gap-2">
                  <span className="text-xs text-[var(--text-muted)] uppercase tracking-wide font-medium">{t('card.reviewed')}</span>
                  <Link
                    to={tenantPath(`/profile/${item.receiver.id}`)}
                    className="text-sm font-semibold text-[var(--text-primary)] hover:text-[var(--color-primary)] transition-colors"
                  >
                    {item.receiver.name}
                  </Link>
                </div>
                <div className="flex items-center gap-0.5">
                  {[1, 2, 3, 4, 5].map((star) => (
                    <Star
                      key={star}
                      className={`w-4 h-4 transition-colors ${star <= item.rating! ? 'text-amber-400 fill-amber-400' : 'text-[var(--text-subtle)]'}`}
                      aria-hidden="true"
                    />
                  ))}
                </div>
              </div>
              {item.content && (
                <p className="text-sm text-[var(--text-secondary)] italic leading-relaxed">&ldquo;{item.content}&rdquo;</p>
              )}
            </CardBody>
          </Card>
        )}

        {/* Badge Earned Display */}
        {item.type === 'badge_earned' && (
          <Card shadow="none" className="mb-4 bg-gradient-to-br from-yellow-500/10 to-amber-500/10 border border-yellow-500/20">
            <CardBody className="p-4 text-center">
              <div className="text-4xl mb-2" aria-hidden="true">{item.badge_icon || '\uD83C\uDFC6'}</div>
              <p className="text-sm font-semibold text-[var(--text-primary)]">
                {t('card.badge_earned_message', {
                  name: author.name,
                  badge: item.badge_name || item.title || '',
                  defaultValue: '{{name}} earned the "{{badge}}" badge!',
                })}
              </p>
            </CardBody>
          </Card>
        )}

        {/* Level Up Display */}
        {item.type === 'level_up' && (
          <Card shadow="none" className="mb-4 bg-gradient-to-br from-emerald-500/10 to-teal-500/10 border border-emerald-500/20">
            <CardBody className="p-4 text-center">
              <div className="text-4xl mb-2" aria-hidden="true">{'\u2B50'}</div>
              <p className="text-sm font-semibold text-[var(--text-primary)]">
                {t('card.level_up_message', {
                  name: author.name,
                  level: item.new_level || item.title?.replace('Level ', '') || '',
                  defaultValue: '{{name}} reached Level {{level}}!',
                })}
              </p>
            </CardBody>
          </Card>
        )}

        {/* View detail CTA — for listings, events, goals, reviews */}
        {detailPath && detailLabel && (
          <div className="mb-3">
            <Link
              to={tenantPath(detailPath)}
              className={`inline-flex items-center justify-center gap-2 py-2 px-5 rounded-xl text-sm font-medium transition-all bg-gradient-to-r ${config.gradient || 'from-[var(--color-primary)]/10 to-[var(--color-primary)]/5'} text-[var(--text-primary)] hover:opacity-80 border border-[var(--border-default)] hover:border-[var(--color-primary)]/30`}
            >
              {config.icon}
              {t(detailLabel)}
              <ArrowRight className="w-3.5 h-3.5" aria-hidden="true" />
            </Link>
          </div>
        )}

        {/* Stats Row — Reactions + Comments Count + Views */}
        {((item.reactions?.total ?? item.likes_count) > 0 || localCommentsCount > 0 || (item.views_count ?? 0) > 0) && (
          <div className="flex items-center justify-between text-xs text-[var(--text-subtle)] mb-3">
            <span className="flex items-center gap-3">
              {item.reactions && item.reactions.total > 0 ? (
                <ReactionSummary
                  counts={item.reactions.counts}
                  total={item.reactions.total}
                  topReactors={item.reactions.top_reactors}
                  entityType="post"
                  entityId={item.id}
                />
              ) : item.likes_count > 0 ? (
                <span className="flex items-center gap-1.5">
                  <span className="flex -space-x-0.5">
                    <span className="w-4 h-4 rounded-full bg-gradient-to-br from-rose-500 to-pink-500 flex items-center justify-center">
                      <Heart className="w-2.5 h-2.5 text-white fill-white" aria-hidden="true" />
                    </span>
                  </span>
                  {item.likes_count} {item.likes_count === 1 ? t('card.like') : t('card.likes')}
                </span>
              ) : null}
              {(item.views_count ?? 0) > 0 && (
                <span className="flex items-center gap-1">
                  <Eye className="w-3.5 h-3.5" aria-hidden="true" />
                  {item.views_count}
                </span>
              )}
            </span>
            {localCommentsCount > 0 && (
              <Button
                variant="light"
                size="sm"
                className="text-xs text-[var(--text-subtle)] hover:text-[var(--text-primary)] p-0 min-w-0 h-auto"
                onPress={toggleComments}
              >
                {localCommentsCount} {localCommentsCount === 1 ? t('card.comment') : t('card.comments')}
              </Button>
            )}
          </div>
        )}

        {/* Divider before actions */}
        <Divider className="mb-1" />

        {/* Action Buttons */}
        <div className="flex items-center justify-around -mx-1">
          {onReact ? (
            <ReactionPicker
              userReaction={(item.reactions?.user_reaction as ReactionType | null) ?? null}
              onReact={(type) => onReact(item, type)}
              isAuthenticated={isAuthenticated}
              size="sm"
            />
          ) : (
            <Tooltip content={item.is_liked ? t('card.unlike') : t('card.like_action')} delay={400} closeDelay={0} size="sm">
              <Button
                size="sm"
                variant="light"
                className={`flex-1 max-w-[140px] ${item.is_liked
                  ? 'text-rose-500 font-medium'
                  : 'text-[var(--text-muted)] hover:text-rose-500'
                } transition-colors`}
                startContent={
                  <Heart
                    className={`w-[18px] h-[18px] transition-all ${item.is_liked ? 'fill-rose-500 text-rose-500 scale-110' : ''}`}
                    aria-hidden="true"
                  />
                }
                onPress={isAuthenticated ? () => onToggleLike(item) : undefined}
                isDisabled={!isAuthenticated}
              >
                {t('card.like_action')}
              </Button>
            </Tooltip>
          )}

          <Tooltip content={t('card.view_comments')} delay={400} closeDelay={0} size="sm">
            <Button
              size="sm"
              variant="light"
              className={`flex-1 max-w-[140px] ${showComments ? 'text-[var(--color-primary)] font-medium' : 'text-[var(--text-muted)]'} hover:text-[var(--color-primary)] transition-colors`}
              startContent={<MessageCircle className={`w-[18px] h-[18px] ${showComments ? 'fill-[var(--color-primary)]/20' : ''}`} aria-hidden="true" />}
              onPress={toggleComments}
            >
              {t('card.comment_action')}
            </Button>
          </Tooltip>

          {/* Share Button (enhanced dropdown) */}
          {isAuthenticated && (
            <ShareButton
              postId={item.id}
              shareCount={item.share_count ?? 0}
              isShared={item.is_shared ?? false}
              isAuthenticated={isAuthenticated}
              post={item}
            />
          )}

          {/* Bookmark Button */}
          {isAuthenticated && (
            <BookmarkButton type={item.type === 'post' ? 'post' : item.type} id={item.id} isBookmarked={item.is_bookmarked} />
          )}
        </div>

        {/* Post Analytics Modal */}
        {showAnalytics && (
          <PostAnalyticsModal
            isOpen={showAnalytics}
            onClose={() => setShowAnalytics(false)}
            postId={item.id}
          />
        )}

        {/* Comments Section */}
        <AnimatePresence>
          {showComments && (
            <motion.div
              initial={{ opacity: 0, height: 0 }}
              animate={{ opacity: 1, height: 'auto' }}
              exit={{ opacity: 0, height: 0 }}
              transition={{ duration: 0.2, ease: 'easeInOut' }}
              className="mt-3 pt-3 border-t border-[var(--border-default)] space-y-3"
            >
              {/* Comment Input */}
              {isAuthenticated && (
                <div className="flex items-start gap-2.5">
                  <Input
                    placeholder={t('card.write_comment')}
                    aria-label={t('card.write_comment')}
                    value={newComment}
                    onChange={(e) => setNewComment(e.target.value)}
                    onKeyDown={(e) => e.key === 'Enter' && !e.shiftKey && handleSubmitComment()}
                    size="sm"
                    radius="full"
                    classNames={{
                      input: 'bg-transparent text-[var(--text-primary)] text-sm',
                      inputWrapper: 'bg-[var(--surface-elevated)] border-[var(--border-default)] hover:border-[var(--color-primary)]/40 h-9',
                    }}
                    endContent={
                      <Button
                        isIconOnly
                        size="sm"
                        variant="light"
                        className="text-[var(--color-primary)] min-w-0 w-auto h-auto p-0 disabled:opacity-30"
                        onPress={handleSubmitComment}
                        isDisabled={!newComment.trim() || isSubmittingComment}
                        aria-label={t('card.send_comment', 'Send comment')}
                      >
                        <Send className="w-4 h-4" />
                      </Button>
                    }
                  />
                </div>
              )}

              {/* Comments List */}
              {isLoadingComments ? (
                <div className="space-y-3">
                  {[1, 2].map((i) => (
                    <div key={i} className="flex items-start gap-2.5">
                      <Skeleton className="w-7 h-7 rounded-full flex-shrink-0" />
                      <div className="flex-1">
                        <Skeleton className="h-3 w-20 rounded mb-1.5" />
                        <Skeleton className="h-3 w-3/4 rounded" />
                      </div>
                    </div>
                  ))}
                </div>
              ) : comments.length === 0 ? (
                <p className="text-xs text-[var(--text-subtle)] text-center py-3 italic">
                  {t('card.no_comments')}
                </p>
              ) : (
                <div className="space-y-3">
                  {comments.map((comment) => (
                    <CommentItem
                      key={comment.id}
                      comment={comment}
                      currentUserId={currentUserId}
                      isAuthenticated={isAuthenticated}
                      onEdit={handleEditComment}
                      onDelete={handleDeleteComment}
                    />
                  ))}
                </div>
              )}
            </motion.div>
          )}
        </AnimatePresence>
      </div>
    </GlassCard>
  );
});

export { FeedCard };
export default FeedCard;
