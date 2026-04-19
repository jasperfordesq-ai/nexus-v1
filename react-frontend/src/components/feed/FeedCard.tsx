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
  Input,
  Tooltip,
  Skeleton,
  Divider,
  Card,
  CardBody,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
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
  ThumbsDown,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard, BottomSheet, ConfettiCelebration } from '@/components/ui';
import { useTenant, useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl, resolveAssetUrl, formatRelativeTime, formatDate, formatTime } from '@/lib/helpers';
import { useFeedTracking } from '@/hooks/useFeedTracking';
import { useLongPress } from '@/hooks/useLongPress';
import type { FeedItem, FeedComment, PollData } from './types';
import { getAuthor, getItemDetailPath, getItemDetailLabel } from './types';
import { WhyShown } from './WhyShown';
import { FeedContentRenderer } from './FeedContentRenderer';
import { ImageCarousel } from './ImageCarousel';
import { MediaGrid } from './MediaGrid';
import { VideoPlayer } from './VideoPlayer';
import { ReactionPicker, ReactionSummary, type ReactionType } from '@/components/social';
import { LinkPreviewCard } from '@/components/social/LinkPreviewCard';
import { MentionRenderer } from '@/components/social/MentionRenderer';
import { UserHoverCard } from '@/components/social/UserHoverCard';
import { SafeHtml, containsHtml } from '@/components/ui/SafeHtml';
import { ShareButton, SharedByAttribution } from './ShareButton';
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
  /** Called with the feed item when the user mutes the post author. Omit to hide the menu option. */
  onMuteUser?: (item: FeedItem) => void;
  /** Called with the feed item when the user reports a post. Omit to hide the menu option. */
  onReportPost?: (item: FeedItem) => void;
  /** Called with the feed item when the user deletes their own post. */
  onDeletePost: (item: FeedItem) => void;
  /** Called with the feed item when an admin deletes any post. */
  onAdminDeletePost?: (item: FeedItem) => void;
  /** Called with the feed item when the user wants to edit their own post. */
  onEditPost?: (item: FeedItem) => void;
  /** Called when the user marks a post as "not interested" (algorithm feedback). */
  onNotInterested?: (item: FeedItem) => void;
  onVotePoll: (pollId: number, optionId: number) => void;
  /** Current feed mode — used for "Why shown" explainer (only in ranking mode) */
  feedMode?: 'ranking' | 'recent';
  isAuthenticated: boolean;
  currentUserId?: number;
  /** Whether the current user is an admin (shows admin delete on all posts). */
  isAdmin?: boolean;
  /** If true, comments section is open and loaded immediately on mount. */
  defaultShowComments?: boolean;
}

/* ───────────────────────── Type Badge Config ───────────────────────── */

/**
 * Item types the backend `BookmarkService` accepts.
 * MUST stay in sync with `app/Services/BookmarkService.php::VALID_TYPES`.
 */
const BOOKMARKABLE_TYPES = new Set<FeedItem['type']>([
  'post',
  'listing',
  'event',
  'job',
  'blog',
  'discussion',
]);

/**
 * Item types the backend `ShareService` accepts for polymorphic reposting.
 * MUST stay in sync with `app/Services/ShareService.php::VALID_TYPES`.
 * Milestones (badge_earned, level_up) and reviews are not repostable.
 */
const SHAREABLE_TYPES = new Set<FeedItem['type']>([
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

/**
 * Per-type card config.
 * - `softGradient` is used for the subtle body tint (e.g. view-detail CTA, milestone card backgrounds).
 * - `accentGradient` is the saturated top accent strip — matches the pattern established by the poll card redesign.
 */
const typeConfig = {
  post: {
    labelKey: null,
    color: 'default' as const,
    icon: null,
    softGradient: '',
    // Neutral strip on post cards — keeps every feed card visually matched
    // without implying a "type" colour (posts have no type chip).
    accentGradient: 'from-[var(--border-default)] via-[var(--border-subtle)] to-[var(--border-default)]',
  },
  listing: {
    labelKey: 'card.type_listing',
    color: 'primary' as const,
    icon: <ShoppingBag className="w-3 h-3" aria-hidden="true" />,
    softGradient: 'from-indigo-500/10 to-blue-500/10',
    accentGradient: 'from-indigo-500 via-blue-500 to-indigo-500',
  },
  event: {
    labelKey: 'card.type_event',
    color: 'success' as const,
    icon: <Calendar className="w-3 h-3" aria-hidden="true" />,
    softGradient: 'from-emerald-500/10 to-green-500/10',
    accentGradient: 'from-emerald-500 via-green-500 to-emerald-500',
  },
  poll: {
    labelKey: 'card.type_poll',
    color: 'warning' as const,
    icon: <BarChart3 className="w-3 h-3" aria-hidden="true" />,
    softGradient: 'from-amber-500/10 to-orange-500/10',
    accentGradient: 'from-amber-500 via-orange-500 to-amber-500',
  },
  goal: {
    labelKey: 'card.type_goal',
    color: 'secondary' as const,
    icon: <Target className="w-3 h-3" aria-hidden="true" />,
    softGradient: 'from-purple-500/10 to-pink-500/10',
    accentGradient: 'from-purple-500 via-pink-500 to-purple-500',
  },
  review: {
    labelKey: 'card.type_review',
    color: 'warning' as const,
    icon: <Star className="w-3 h-3" aria-hidden="true" />,
    softGradient: 'from-amber-500/10 to-yellow-500/10',
    accentGradient: 'from-amber-500 via-yellow-500 to-amber-500',
  },
  job: {
    labelKey: 'card.type_job',
    color: 'primary' as const,
    icon: <TrendingUp className="w-3 h-3" aria-hidden="true" />,
    softGradient: 'from-blue-500/10 to-cyan-500/10',
    accentGradient: 'from-blue-500 via-cyan-500 to-blue-500',
  },
  challenge: {
    labelKey: 'card.type_challenge',
    color: 'secondary' as const,
    icon: <Target className="w-3 h-3" aria-hidden="true" />,
    softGradient: 'from-violet-500/10 to-purple-500/10',
    accentGradient: 'from-violet-500 via-purple-500 to-violet-500',
  },
  volunteer: {
    labelKey: 'card.type_volunteer',
    color: 'success' as const,
    icon: <Heart className="w-3 h-3" aria-hidden="true" />,
    softGradient: 'from-green-500/10 to-emerald-500/10',
    accentGradient: 'from-green-500 via-emerald-500 to-green-500',
  },
  blog: {
    labelKey: 'card.type_blog',
    color: 'primary' as const,
    icon: <BookOpen className="w-3 h-3" aria-hidden="true" />,
    softGradient: 'from-sky-500/10 to-blue-500/10',
    accentGradient: 'from-sky-500 via-blue-500 to-sky-500',
  },
  discussion: {
    labelKey: 'card.type_discussion',
    color: 'secondary' as const,
    icon: <Users className="w-3 h-3" aria-hidden="true" />,
    softGradient: 'from-fuchsia-500/10 to-purple-500/10',
    accentGradient: 'from-fuchsia-500 via-purple-500 to-fuchsia-500',
  },
  badge_earned: {
    labelKey: 'card.type_badge_earned',
    color: 'warning' as const,
    icon: <Trophy className="w-3 h-3" aria-hidden="true" />,
    softGradient: 'from-yellow-500/10 to-amber-500/10',
    accentGradient: 'from-yellow-500 via-amber-500 to-yellow-500',
  },
  level_up: {
    labelKey: 'card.type_level_up',
    color: 'success' as const,
    icon: <Zap className="w-3 h-3" aria-hidden="true" />,
    softGradient: 'from-emerald-500/10 to-teal-500/10',
    accentGradient: 'from-emerald-500 via-teal-500 to-emerald-500',
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
          aria-hidden="true"
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
  const [showDeleteModal, setShowDeleteModal] = useState(false);
  const [isDeletingComment, setIsDeletingComment] = useState(false);

  const isOwn = currentUserId === comment.author.id || comment.is_own;

  const handleSaveEdit = async () => {
    if (!editContent.trim()) return;
    setIsSubmittingEdit(true);
    const ok = await onEdit(comment.id, editContent.trim());
    if (ok) setIsEditing(false);
    setIsSubmittingEdit(false);
  };

  const handleDelete = () => {
    setShowDeleteModal(true);
  };

  const confirmDelete = async () => {
    setIsDeletingComment(true);
    await onDelete(comment.id);
    setIsDeletingComment(false);
    setShowDeleteModal(false);
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
        <div className="bg-theme-elevated rounded-2xl px-3.5 py-2.5 border border-theme-default">
          <div className="flex items-center gap-2">
            <Link
              to={tenantPath(`/profile/${comment.author.id}`)}
              className="text-xs font-semibold text-theme-primary hover:text-primary transition-colors"
            >
              {comment.author.name}
            </Link>
            {comment.edited && (
              <span className="text-[10px] text-theme-subtle italic">({t('card.edited')})</span>
            )}
          </div>
          {isEditing ? (
            <div className="mt-1.5 space-y-2">
              <Input
                value={editContent}
                onChange={(e) => setEditContent(e.target.value)}
                size="sm"
                classNames={{
                  input: 'bg-transparent text-theme-primary text-xs',
                  inputWrapper: 'bg-[var(--surface-hover)] border-theme-default min-h-[32px]',
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
            <SafeHtml content={comment.content} className="text-xs text-theme-secondary mt-0.5 whitespace-pre-wrap leading-relaxed" as="div" />
          ) : (
            <p className="text-xs text-theme-secondary mt-0.5 whitespace-pre-wrap leading-relaxed"><MentionRenderer text={comment.content} showUserCard={false} /></p>
          )}
        </div>
        <div className="flex items-center gap-3 mt-1 px-1">
          <span className="text-[10px] text-theme-subtle">
            <Clock className="w-2.5 h-2.5 inline me-0.5 -mt-px" aria-hidden="true" />
            {formatRelativeTime(comment.created_at)}
          </span>
          {comment.replies && comment.replies.length > 0 && (
            <Button
              variant="light"
              size="sm"
              className="text-[10px] text-primary p-0 min-w-0 h-auto"
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
                className="text-[10px] text-theme-subtle hover:text-theme-primary flex items-center gap-0.5 h-auto p-0 min-w-0"
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

        {/* Delete Comment Confirmation Modal */}
        <Modal
          isOpen={showDeleteModal}
          onClose={() => setShowDeleteModal(false)}
          size="sm"
          classNames={{
            base: 'bg-[var(--glass-bg)] backdrop-blur-xl border border-[var(--glass-border)]',
            backdrop: 'bg-black/60 backdrop-blur-sm',
          }}
        >
          <ModalContent>
            <ModalHeader className="text-theme-primary text-sm">
              {t('card.delete_comment_title')}
            </ModalHeader>
            <ModalBody>
              <p className="text-sm text-theme-muted">{t('card.delete_comment_body')}</p>
            </ModalBody>
            <ModalFooter>
              <Button
                size="sm"
                variant="flat"
                onPress={() => setShowDeleteModal(false)}
                className="text-theme-muted"
              >
                {t('card.cancel')}
              </Button>
              <Button
                size="sm"
                color="danger"
                variant="flat"
                isLoading={isDeletingComment}
                onPress={confirmDelete}
                className="font-medium"
              >
                {t('card.delete')}
              </Button>
            </ModalFooter>
          </ModalContent>
        </Modal>

        {/* Nested Replies */}
        <AnimatePresence>
          {showReplies && comment.replies && (
            <motion.div
              initial={{ opacity: 0, height: 0 }}
              animate={{ opacity: 1, height: 'auto' }}
              exit={{ opacity: 0, height: 0 }}
              className="mt-2 ms-2 space-y-2 border-s-2 border-primary/30 ps-3"
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
                    <div className="bg-theme-elevated rounded-xl px-2.5 py-1.5 border border-theme-default">
                      <span className="text-[10px] font-semibold text-theme-primary">{reply.author.name}</span>
                      <p className="text-[11px] text-theme-secondary whitespace-pre-wrap">{reply.content}</p>
                    </div>
                    <span className="text-[10px] text-theme-subtle ms-1">
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
  onEditPost,
  onNotInterested,
  onVotePoll,
  feedMode = 'ranking',
  isAuthenticated,
  currentUserId,
  isAdmin,
  defaultShowComments = false,
}: FeedCardProps) {
  const { t } = useTranslation('feed');
  const { tenantPath } = useTenant();
  const toast = useToast();
  const [showComments, setShowComments] = useState(defaultShowComments);
  const [comments, setComments] = useState<FeedComment[]>([]);
  const [isLoadingComments, setIsLoadingComments] = useState(defaultShowComments);
  const [newComment, setNewComment] = useState('');
  const [isSubmittingComment, setIsSubmittingComment] = useState(false);
  const [localCommentsCount, setLocalCommentsCount] = useState(item.comments_count);
  const [pollData, setPollData] = useState<PollData | null>(item.poll_data ?? null);
  const [isLoadingPoll, setIsLoadingPoll] = useState(false);
  const [pollLoadError, setPollLoadError] = useState(false);

  // Double-tap to like
  const [showHeartOverlay, setShowHeartOverlay] = useState(false);
  const heartTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Bottom sheet for mobile options menu
  const [isOptionsSheetOpen, setIsOptionsSheetOpen] = useState(false);

  // Long-press to open options on mobile
  const longPressHandlers = useLongPress({
    onLongPress: () => { if (isAuthenticated) setIsOptionsSheetOpen(true); },
  });

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

  // Confetti celebration for milestone feed items (badge_earned, level_up)
  const [showConfetti, setShowConfetti] = useState(false);
  const confettiTriggeredRef = useRef(false);
  const confettiTimeoutRefs = useRef<ReturnType<typeof setTimeout>[]>([]);

  // Tracks whether comments have been loaded at least once (prevents double-load when defaultShowComments=true)
  const commentLoadedRef = useRef(false);

  // H6: Mounted guard — prevents setState calls after unmount
  const isMountedRef = useRef(true);
  useEffect(() => {
    return () => { isMountedRef.current = false; };
  }, []);

  // Clean up confetti timeouts on unmount
  useEffect(() => {
    return () => {
      confettiTimeoutRefs.current.forEach(clearTimeout);
    };
  }, []);

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

          // Trigger confetti for milestone items on first view
          const isMilestone = item.type === 'badge_earned' || item.type === 'level_up';
          if (isMilestone && !confettiTriggeredRef.current) {
            confettiTriggeredRef.current = true;
            const t1 = setTimeout(() => { setShowConfetti(true); }, 300);
            const t2 = setTimeout(() => {
              setShowConfetti(false);
              confettiTimeoutRefs.current = confettiTimeoutRefs.current.filter(id => id !== t1 && id !== t2);
            }, 2000);
            confettiTimeoutRefs.current.push(t1, t2);
          }
        }
      },
      { threshold: 0.5 }
    );
    observer.observe(el);

    return () => observer.disconnect();
  }, [item.id, item.type]);

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
        .catch((err) => {
          logError('Failed to load poll', err);
          setPollLoadError(true);
        })
        .finally(() => setIsLoadingPoll(false));
    }
  }, [item.type, item.id, item.poll_data, pollData, isLoadingPoll]);

  const handleVote = (optionId: number) => {
    if (!pollData) return;
    const pollExpired = pollData.expires_at && new Date(pollData.expires_at) < new Date();
    if (pollExpired) {
      toast.error(t('poll.expired'));
      return;
    }
    onVotePoll(item.id, optionId);

    // Optimistic update: update pollData locally.
    // total_votes / vote_count may be null (non-creators of open polls) — coalesce to 0.
    const prevTotal = pollData.total_votes ?? 0;
    const totalBefore = prevTotal + (pollData.user_vote_option_id ? 0 : 1);
    const updatedOptions = pollData.options.map((opt) => {
      let newCount = opt.vote_count ?? 0;
      if (opt.id === pollData.user_vote_option_id) newCount -= 1;
      if (opt.id === optionId) newCount += 1;
      const safeCount = Math.max(0, newCount);
      return {
        ...opt,
        vote_count: safeCount,
        percentage: totalBefore > 0 ? Math.round((safeCount / totalBefore) * 100 * 10) / 10 : 0,
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
        if (isMountedRef.current) setComments(response.data.comments ?? []);
      }
    } catch (err) {
      logError('Failed to load comments', err);
      if (isMountedRef.current) {
        toast.error(t('card.comments_load_failed'));
        setShowComments(false); // Roll back the toggle
      }
    } finally {
      if (isMountedRef.current) setIsLoadingComments(false);
    }
  };

  // Auto-load comments on mount when defaultShowComments is true
  useEffect(() => {
    if (defaultShowComments) {
      loadComments();
      commentLoadedRef.current = true;
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- load once on mount; defaultShowComments is initial prop
  }, []);

  const toggleComments = () => {
    if (!showComments && !commentLoadedRef.current) {
      loadComments();
    }
    setShowComments(!showComments);
  };

  const handleSubmitComment = async () => {
    if (!newComment.trim() || isSubmittingComment) return;

    try {
      setIsSubmittingComment(true);
      const response = await api.post('/v2/comments', {
        target_type: item.type,
        target_id: item.id,
        content: newComment.trim(),
      });

      if (response.success) {
        if (isMountedRef.current) setNewComment('');
        if (isMountedRef.current) setLocalCommentsCount((prev) => prev + 1);
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
    <GlassCard ref={(el: HTMLDivElement | null) => { (trackingRef as React.MutableRefObject<HTMLDivElement | null>).current = el; viewTargetRef.current = el; }} hoverable className="overflow-hidden group relative">
      {/* Long-press touch target for mobile context menu */}
      <div onTouchStart={longPressHandlers.onTouchStart} onTouchMove={longPressHandlers.onTouchMove} onTouchEnd={longPressHandlers.onTouchEnd}>
      {/* Confetti celebration overlay for milestones */}
      <ConfettiCelebration show={showConfetti} />

      {/*
        Accent strip on every card. Typed cards use their saturated type colour
        (green=event, amber=poll, etc.); native posts use a neutral border-coloured
        strip so every card has matching anatomy in the feed.
      */}
      {config.accentGradient && (
        <div className={`h-1 bg-gradient-to-r ${config.accentGradient}`} aria-hidden="true" />
      )}

      <div className="p-5">
        {/*
          Shared-by attribution — rendered when this feed item appears in the
          viewer's feed because someone they know reposted it. Backend populates
          `shared_by` via post_shares join.
        */}
        {item.shared_by && (
          <div className="mb-3">
            <SharedByAttribution user={item.shared_by} />
          </div>
        )}

        {/* Header */}
        <div className="flex items-start justify-between mb-4">
          <div className="flex items-center gap-3">
            <UserHoverCard userId={author.id}>
              <Link to={tenantPath(`/profile/${author.id}`)} className="relative">
                <Avatar
                  name={author.name}
                  src={resolveAvatarUrl(author.avatar)}
                  size="md"
                  className="ring-2 ring-[var(--border-default)] group-hover:ring-primary/30 transition-all"
                  isBordered
                />
              </Link>
            </UserHoverCard>
            <div className="min-w-0">
              <div className="flex items-center gap-2 flex-wrap">
                <UserHoverCard userId={author.id}>
                  <Link
                    to={tenantPath(`/profile/${author.id}`)}
                    className="font-semibold text-theme-primary hover:text-primary transition-colors text-sm truncate"
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
                {/* Scheduling indicator — visible only to the post author */}
                {item.publish_status === 'scheduled' && isOwnPost && item.scheduled_at && (
                  <Tooltip content={new Date(item.scheduled_at).toLocaleString()} placement="bottom" delay={300} closeDelay={0} size="sm">
                    <Chip
                      size="sm"
                      variant="flat"
                      color="warning"
                      startContent={<Clock className="w-3 h-3" aria-hidden="true" />}
                      className="text-[10px] h-5"
                    >
                      {t('card.scheduled', 'Scheduled')}
                    </Chip>
                  </Tooltip>
                )}
              </div>
              <div className="flex items-center gap-1.5">
                <Tooltip content={new Date(item.created_at).toLocaleString()} placement="bottom" delay={500} closeDelay={0} size="sm">
                  <p className="text-xs text-theme-subtle flex items-center gap-1 cursor-default">
                    <Clock className="w-3 h-3" aria-hidden="true" />
                    <span>
                      {formatRelativeTime(item.created_at)}
                      {item.updated_at && item.updated_at !== item.created_at && (
                        <span className="italic"> · {t('card.edited')}</span>
                      )}
                    </span>
                  </p>
                </Tooltip>
                <WhyShown item={item} feedMode={feedMode} />
              </div>
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
                      className="text-theme-subtle hover:text-theme-primary min-w-0 opacity-0 group-hover:opacity-100 transition-opacity"
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
                        {onEditPost && item.type === 'post' && (
                          <DropdownItem
                            key="edit"
                            startContent={<Pencil className="w-4 h-4" aria-hidden="true" />}
                            onPress={() => onEditPost(item)}
                          >
                            {t('card.edit_post', 'Edit Post')}
                          </DropdownItem>
                        )}
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
                        {onNotInterested && (
                          <DropdownItem
                            key="not-interested"
                            startContent={<ThumbsDown className="w-4 h-4" aria-hidden="true" />}
                            onPress={() => onNotInterested(item)}
                          >
                            {t('card.not_interested', 'Not interested')}
                          </DropdownItem>
                        )}
                        {onMuteUser && (
                          <DropdownItem
                            key="mute"
                            startContent={<VolumeX className="w-4 h-4" aria-hidden="true" />}
                            onPress={() => onMuteUser(item)}
                          >
                            {t('card.mute_user', { name: author.name })}
                          </DropdownItem>
                        )}
                        {onReportPost && (
                          <DropdownItem
                            key="report"
                            startContent={<Flag className="w-4 h-4" aria-hidden="true" />}
                            className="text-danger"
                            color="danger"
                            onPress={() => onReportPost(item)}
                          >
                            {t('card.report_post')}
                          </DropdownItem>
                        )}
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
                className="sm:hidden text-theme-subtle hover:text-theme-primary min-w-0"
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
                        className="justify-start text-theme-primary"
                        startContent={<BarChart3 className="w-4 h-4" aria-hidden="true" />}
                        onPress={() => { setIsOptionsSheetOpen(false); setShowAnalytics(true); }}
                      >
                        {t('card.view_analytics', 'View Analytics')}
                      </Button>
                      {onEditPost && item.type === 'post' && (
                        <Button
                          variant="light"
                          className="justify-start text-theme-primary"
                          startContent={<Pencil className="w-4 h-4" aria-hidden="true" />}
                          onPress={() => { setIsOptionsSheetOpen(false); onEditPost(item); }}
                        >
                          {t('card.edit_post', 'Edit Post')}
                        </Button>
                      )}
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
                        className="justify-start text-theme-primary"
                        startContent={<EyeOff className="w-4 h-4" aria-hidden="true" />}
                        onPress={() => { setIsOptionsSheetOpen(false); onHidePost(item); }}
                      >
                        {t('card.hide_post')}
                      </Button>
                      {onNotInterested && (
                        <Button
                          variant="light"
                          className="justify-start text-theme-primary"
                          startContent={<ThumbsDown className="w-4 h-4" aria-hidden="true" />}
                          onPress={() => { setIsOptionsSheetOpen(false); onNotInterested(item); }}
                        >
                          {t('card.not_interested', 'Not interested')}
                        </Button>
                      )}
                      {onMuteUser && (
                        <Button
                          variant="light"
                          className="justify-start text-theme-primary"
                          startContent={<VolumeX className="w-4 h-4" aria-hidden="true" />}
                          aria-label={t('card.mute_user_label', { name: author.name ?? '' })}
                          onPress={() => { setIsOptionsSheetOpen(false); onMuteUser(item); }}
                        >
                          {t('card.mute_user', { name: author.name })}
                        </Button>
                      )}
                      {onReportPost && (
                        <Button
                          variant="light"
                          className="justify-start text-danger"
                          startContent={<Flag className="w-4 h-4" aria-hidden="true" />}
                          onPress={() => { setIsOptionsSheetOpen(false); onReportPost(item); }}
                        >
                          {t('card.report_post')}
                        </Button>
                      )}
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
                className="text-sm font-semibold text-theme-primary hover:text-primary transition-colors mb-1.5 block"
                onClick={recordClick}
              >
                {item.title}
              </Link>
            ) : (
              <p className="text-sm font-semibold text-theme-primary mb-1.5">{item.title}</p>
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

        {/* Event: calendar-page date chip + countdown + location. Listing: date + location only. */}
        {(item.type === 'event' || item.type === 'listing' || item.type === 'volunteer') && (item.start_date || item.location) && (() => {
          const isEvent = item.type === 'event';
          const startMs = item.start_date ? new Date(item.start_date).getTime() : null;
          const deltaMs = startMs != null ? startMs - Date.now() : null;
          const isUpcoming = deltaMs != null && deltaMs > 0;
          const isImminent = isUpcoming && deltaMs! < 48 * 3600 * 1000;
          const countdown = (() => {
            if (!isImminent || deltaMs == null) return null;
            const hours = Math.floor(deltaMs / 3600000);
            const minutes = Math.floor(deltaMs / 60000);
            if (minutes < 60) return t('card.event.starts_in_minutes', 'Starts in {{minutes}}m', { minutes });
            return t('card.event.starts_in_hours', 'Starts in {{hours}}h', { hours });
          })();
          const dateForChip = item.start_date ? new Date(item.start_date) : null;

          return (
            <div className="mb-4 flex flex-wrap items-center gap-3 text-xs text-theme-muted">
              {/* Event-only: calendar-page date chip */}
              {isEvent && dateForChip && (
                <div className="flex items-center gap-2.5">
                  <div className="flex flex-col items-center justify-center rounded-lg overflow-hidden border border-theme-default bg-[var(--surface-base)] w-11 h-12 shadow-sm" aria-hidden="true">
                    <div className="w-full px-1 py-0.5 text-[9px] font-bold uppercase tracking-wider bg-gradient-to-r from-emerald-500 to-green-500 text-white text-center leading-tight">
                      {dateForChip.toLocaleString(undefined, { month: 'short' })}
                    </div>
                    <div className="flex-1 flex items-center justify-center text-lg font-bold tabular-nums text-theme-primary leading-none">
                      {dateForChip.getDate()}
                    </div>
                  </div>
                  <div className="flex flex-col text-xs">
                    <span className="text-theme-primary font-medium">
                      {formatDate(item.start_date!, { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' })}
                    </span>
                    <span className="text-theme-subtle">{formatTime(item.start_date!)}</span>
                  </div>
                </div>
              )}

              {/* Non-event types keep the simpler inline date */}
              {!isEvent && item.start_date && (
                <span className="flex items-center gap-1.5">
                  <Calendar className="w-3.5 h-3.5 text-emerald-500" aria-hidden="true" />
                  <span>{formatDate(item.start_date, { month: 'short', day: 'numeric', year: 'numeric' })}</span>
                  <span className="text-theme-subtle">{formatTime(item.start_date)}</span>
                </span>
              )}

              {/* Countdown pill for imminent events */}
              {isEvent && countdown && (
                <Chip
                  size="sm"
                  variant="flat"
                  color="success"
                  startContent={<Clock className="w-3 h-3" aria-hidden="true" />}
                  className="h-6 text-[11px] font-medium"
                >
                  {countdown}
                </Chip>
              )}

              {item.location && (
                <span className="flex items-center gap-1.5">
                  <MapPin className="w-3.5 h-3.5 text-emerald-500" aria-hidden="true" />
                  <span className="truncate">{item.location}</span>
                </span>
              )}
            </div>
          );
        })()}

        {/* Job-specific chips: job_type (paid/volunteer/timebank), commitment, location */}
        {item.type === 'job' && (item.job_type || item.commitment || item.location) && (
          <div className="mb-4 flex flex-wrap items-center gap-2 text-xs">
            {item.job_type && (
              <Chip
                size="sm"
                variant="flat"
                color="primary"
                startContent={<TrendingUp className="w-3 h-3" aria-hidden="true" />}
                className="h-6 text-[11px] font-medium"
              >
                {t(`card.job.type_${item.job_type}`, item.job_type)}
              </Chip>
            )}
            {item.commitment && (
              <Chip
                size="sm"
                variant="flat"
                color="default"
                startContent={<Clock className="w-3 h-3" aria-hidden="true" />}
                className="h-6 text-[11px] font-medium"
              >
                {t(`card.job.commitment_${item.commitment}`, item.commitment)}
              </Chip>
            )}
            {item.location && (
              <span className="flex items-center gap-1.5 text-theme-muted">
                <MapPin className="w-3.5 h-3.5 text-primary" aria-hidden="true" />
                <span className="truncate">{item.location}</span>
              </span>
            )}
          </div>
        )}

        {/* Volunteer-specific chips: credits_offered + organization */}
        {item.type === 'volunteer' && (item.credits_offered != null || item.organization) && (
          <div className="mb-4 flex flex-wrap items-center gap-2 text-xs">
            {item.credits_offered != null && item.credits_offered > 0 && (
              <Chip
                size="sm"
                variant="flat"
                color="success"
                startContent={<Clock className="w-3 h-3" aria-hidden="true" />}
                className="h-6 text-[11px] font-medium"
              >
                {t('card.volunteer.credits_offered', '{{count}} time credits', { count: item.credits_offered })}
              </Chip>
            )}
            {item.organization && (
              <span className="flex items-center gap-1.5 text-theme-muted">
                <Users className="w-3.5 h-3.5 text-emerald-500" aria-hidden="true" />
                <span className="truncate">{item.organization}</span>
              </span>
            )}
          </div>
        )}

        {/* Challenge-specific chips: submission deadline + ideas count */}
        {item.type === 'challenge' && (item.submission_deadline || item.ideas_count != null) && (
          <div className="mb-4 flex flex-wrap items-center gap-2 text-xs">
            {item.submission_deadline && (() => {
              const deadlineMs = new Date(item.submission_deadline).getTime() - Date.now();
              const isClosingSoon = deadlineMs > 0 && deadlineMs < 72 * 3600 * 1000;
              return (
                <Chip
                  size="sm"
                  variant="flat"
                  color={isClosingSoon ? 'warning' : 'secondary'}
                  startContent={<Clock className="w-3 h-3" aria-hidden="true" />}
                  className="h-6 text-[11px] font-medium"
                >
                  {isClosingSoon
                    ? t('card.challenge.closing_soon', 'Closing soon')
                    : t('card.challenge.closes_on', 'Closes {{date}}', {
                        date: formatDate(item.submission_deadline!, { month: 'short', day: 'numeric' }),
                      })}
                </Chip>
              );
            })()}
            {item.ideas_count != null && item.ideas_count > 0 && (
              <Chip
                size="sm"
                variant="flat"
                color="default"
                startContent={<Target className="w-3 h-3" aria-hidden="true" />}
                className="h-6 text-[11px] font-medium"
              >
                {t('card.challenge.ideas_count', '{{count}} ideas', { count: item.ideas_count })}
              </Chip>
            )}
          </div>
        )}

        {/* Media: Multi-image carousel/grid, single image/video, or placeholder */}
        {/* Wrapped with double-tap-to-like detection */}
        {item.media && item.media.length > 1 ? (
          /* Multi-media: use grid for 2-4, carousel for 5+ */
          <div
            className="mb-4 -mx-5 overflow-hidden relative"
            onClick={doubleTapHandler}
            onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); doubleTapHandler(); } }}
            role="button"
            tabIndex={0}
            aria-label={t('card.like_action', 'Like')}
          >
            <HeartOverlay show={showHeartOverlay} />
            {item.media.length <= 4 ? (
              <MediaGrid media={item.media} className="mx-5" />
            ) : (
              <ImageCarousel media={item.media} className="mx-5" />
            )}
          </div>
        ) : item.media && item.media.length === 1 && item.media[0] ? (
          /* Single media item — use VideoPlayer for video, ImageCarousel for image */
          <div
            className="mb-4 -mx-5 overflow-hidden relative"
            onClick={doubleTapHandler}
            onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); doubleTapHandler(); } }}
            role="button"
            tabIndex={0}
            aria-label={t('card.like_action', 'Like')}
          >
            <HeartOverlay show={showHeartOverlay} />
            {item.media[0].media_type === 'video' ? (
              <VideoPlayer media={item.media[0]} className="mx-5" />
            ) : (
              <ImageCarousel media={item.media} className="mx-5" />
            )}
          </div>
        ) : item.image_url ? (
          <div
            className="mb-4 -mx-5 overflow-hidden relative"
            onClick={doubleTapHandler}
            onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); doubleTapHandler(); } }}
            role="button"
            tabIndex={0}
            aria-label={t('card.like_action', 'Like')}
          >
            <HeartOverlay show={showHeartOverlay} />
            {detailPath ? (
              <Link to={tenantPath(detailPath)}>
                <img
                  src={resolveAssetUrl(item.image_url)}
                  alt={t('card.image_alt', '{{type}} image by {{name}}', { type: typeLabel ?? t('card.type_post', 'Post'), name: author.name })}
                  className="w-full max-h-[28rem] object-cover group-hover:scale-[1.02] transition-transform duration-500"
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
                className="w-full max-h-[28rem] object-cover group-hover:scale-[1.02] transition-transform duration-500"
                loading="lazy"
                width={800}
                height={448}
              />
            )}
            <div className="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-colors duration-200 pointer-events-none" aria-hidden="true" />
          </div>
        ) : null}

        {/* Poll Display */}
        {item.type === 'poll' && (
          <div className="mb-4">
            {isLoadingPoll ? (
              <div className="rounded-2xl border border-theme-default bg-theme-elevated overflow-hidden">
                <div className="h-1 bg-gradient-to-r from-amber-500/40 via-orange-500/40 to-amber-500/40" />
                <div className="p-5 space-y-3">
                  <Skeleton className="h-6 w-3/4 rounded-lg" />
                  {[1, 2, 3].map((i) => (
                    <Skeleton key={i} className="h-12 w-full rounded-xl" />
                  ))}
                </div>
              </div>
            ) : pollLoadError ? (
              <div className="rounded-2xl border border-theme-default bg-theme-elevated p-5 text-center">
                <BarChart3 className="w-8 h-8 mx-auto mb-2 text-theme-subtle" aria-hidden="true" />
                <p className="text-sm text-theme-muted">{t('poll.load_failed')}</p>
              </div>
            ) : pollData ? (
              (() => {
                const now = new Date();
                const pollExpired = !!(pollData.expires_at && new Date(pollData.expires_at) < now);
                const hasVoted = pollData.user_vote_option_id !== null;
                const showResults = hasVoted || pollExpired;
                const totalVotes = pollData.total_votes ?? 0;
                // Backend hides per-option counts on OPEN polls for non-creators
                // (prevents results from influencing remaining voters). In that case
                // total_votes is null even though the user has voted.
                const resultsHidden = showResults && pollData.total_votes == null;

                // Identify leading option when results are visible
                const leadingOptionId = showResults
                  ? pollData.options.reduce<number | null>((lead, opt) => {
                      if (opt.percentage == null) return lead;
                      const leadOpt = pollData.options.find((o) => o.id === lead);
                      const leadPct = leadOpt?.percentage ?? -1;
                      return opt.percentage > leadPct ? opt.id : lead;
                    }, pollData.options[0]?.id ?? null)
                  : null;

                const timeRemaining = (() => {
                  if (!pollData.expires_at || pollExpired) return null;
                  const ms = new Date(pollData.expires_at).getTime() - now.getTime();
                  const hours = Math.floor(ms / 3600000);
                  const minutes = Math.floor(ms / 60000);
                  if (minutes < 60) return t('poll.closes_minutes', `Closes in ${minutes}m`, { minutes });
                  if (hours < 24) return t('poll.closes_hours', `Closes in ${hours}h`, { hours });
                  const days = Math.floor(hours / 24);
                  return t('poll.closes_days', `Closes in ${days}d`, { days });
                })();

                const pollDetailPath = tenantPath('/polls');

                return (
                  <div className="rounded-2xl border border-theme-default bg-theme-elevated overflow-hidden shadow-sm">
                    {/* Gradient accent bar */}
                    <div className={`h-1 bg-gradient-to-r ${pollExpired ? 'from-gray-400/30 to-gray-500/30' : 'from-amber-500 via-orange-500 to-amber-500'}`} />

                    {/* Header: pill + status */}
                    <div className="flex items-center justify-between gap-2 px-5 pt-4">
                      <div className="flex items-center gap-2">
                        <div className="flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-gradient-to-r from-amber-500/15 to-orange-500/15 border border-amber-500/20">
                          <BarChart3 className="w-3.5 h-3.5 text-amber-600 dark:text-amber-400" aria-hidden="true" />
                          <span className="text-[11px] font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300">
                            {t('card.type_poll', 'Poll')}
                          </span>
                        </div>
                        {!pollExpired && (
                          <span className="inline-flex items-center gap-1 text-[11px] font-medium text-emerald-600 dark:text-emerald-400">
                            <span className="relative flex h-1.5 w-1.5">
                              <span className="absolute inline-flex h-full w-full rounded-full bg-emerald-500 opacity-60 animate-ping" />
                              <span className="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500" />
                            </span>
                            {t('poll.live', 'Live')}
                          </span>
                        )}
                      </div>
                      {pollExpired ? (
                        <Chip size="sm" variant="flat" color="default" startContent={<Clock className="w-3 h-3" />} className="h-6 text-[11px]">
                          {t('poll.closed', 'Closed')}
                        </Chip>
                      ) : timeRemaining ? (
                        <Chip size="sm" variant="flat" color="warning" startContent={<Clock className="w-3 h-3" />} className="h-6 text-[11px]">
                          {timeRemaining}
                        </Chip>
                      ) : null}
                    </div>

                    {/* Question */}
                    {pollData.question && (
                      <h3 className="px-5 pt-3 text-[15px] sm:text-base font-semibold leading-snug text-theme-primary">
                        {pollData.question}
                      </h3>
                    )}

                    {/* Options */}
                    <div className="px-5 pt-3 pb-2 space-y-2">
                      {pollData.options.map((option) => {
                        const isVoted = pollData.user_vote_option_id === option.id;
                        const isLeading = showResults && option.id === leadingOptionId && totalVotes > 0;
                        const pct = option.percentage ?? 0;

                        if (showResults) {
                          return (
                            <div
                              key={option.id}
                              className={`relative rounded-xl overflow-hidden border transition-colors ${
                                isVoted
                                  ? 'border-amber-500/60 bg-amber-500/5'
                                  : isLeading
                                    ? 'border-theme-default bg-[var(--surface-base)]'
                                    : 'border-[var(--border-subtle)] bg-[var(--surface-base)]'
                              }`}
                            >
                              {/* Animated percentage fill (skipped when results are hidden) */}
                              {!resultsHidden && (
                                <div
                                  className={`absolute inset-y-0 left-0 transition-[width] duration-700 ease-out ${
                                    isVoted
                                      ? 'bg-gradient-to-r from-amber-500/25 to-orange-500/20'
                                      : isLeading
                                        ? 'bg-[var(--surface-hover)]'
                                        : 'bg-[var(--surface-hover)]/60'
                                  }`}
                                  style={{ width: `${pct}%` }}
                                  aria-hidden="true"
                                />
                              )}
                              <div className="relative flex items-center justify-between gap-3 px-3.5 py-3">
                                <div className="flex items-center gap-2 min-w-0">
                                  {isVoted ? (
                                    <span className="flex-shrink-0 flex items-center justify-center w-5 h-5 rounded-full bg-amber-500 text-white">
                                      <Check className="w-3 h-3" aria-hidden="true" />
                                    </span>
                                  ) : isLeading && !resultsHidden ? (
                                    <TrendingUp className="w-4 h-4 flex-shrink-0 text-theme-muted" aria-hidden="true" />
                                  ) : (
                                    <span className="flex-shrink-0 w-5 h-5" aria-hidden="true" />
                                  )}
                                  <span className={`text-sm leading-snug truncate ${(isVoted || (isLeading && !resultsHidden)) ? 'font-semibold text-theme-primary' : 'text-theme-primary'}`}>
                                    {option.text}
                                  </span>
                                </div>
                                <div className="flex flex-col items-end shrink-0">
                                  {option.percentage != null && (
                                    <span className={`text-sm font-bold tabular-nums ${isVoted ? 'text-amber-600 dark:text-amber-400' : 'text-theme-primary'}`}>
                                      {option.percentage}%
                                    </span>
                                  )}
                                  {option.vote_count != null && (
                                    <span className="text-[10px] text-theme-subtle tabular-nums">
                                      {option.vote_count} {option.vote_count === 1 ? t('card.vote', 'vote') : t('card.votes', 'votes')}
                                    </span>
                                  )}
                                  {isVoted && resultsHidden && (
                                    <span className="text-[10px] font-semibold text-amber-600 dark:text-amber-400 uppercase tracking-wide">
                                      {t('poll.your_vote', 'Your vote')}
                                    </span>
                                  )}
                                </div>
                              </div>
                            </div>
                          );
                        }

                        return (
                          <button
                            key={option.id}
                            onClick={() => handleVote(option.id)}
                            disabled={pollExpired}
                            className="group relative w-full text-left px-4 py-3 rounded-xl border-2 border-theme-default bg-[var(--surface-base)] hover:border-amber-500 hover:bg-amber-500/5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--surface-elevated)] active:scale-[0.99] transition-all text-sm font-medium text-theme-primary disabled:opacity-50 disabled:cursor-not-allowed"
                          >
                            <span className="flex items-center justify-between gap-3">
                              <span className="truncate">{option.text}</span>
                              <ArrowRight className="w-4 h-4 text-theme-subtle group-hover:text-amber-500 group-hover:translate-x-0.5 transition-all" aria-hidden="true" />
                            </span>
                          </button>
                        );
                      })}
                    </div>

                    {/* Footer: meta + CTA */}
                    <div className="flex items-center justify-between gap-3 px-5 py-3 border-t border-[var(--border-subtle)] bg-[var(--surface-base)]/40">
                      <div className="flex items-center gap-2 text-xs text-theme-muted min-w-0">
                        {resultsHidden ? (
                          <span className="inline-flex items-center gap-1.5 min-w-0">
                            <EyeOff className="w-3.5 h-3.5 flex-shrink-0" aria-hidden="true" />
                            <span className="truncate">
                              {t('poll.results_hidden_until_close', 'Results revealed when poll closes')}
                            </span>
                          </span>
                        ) : pollData.total_votes == null ? (
                          <span className="inline-flex items-center gap-1.5 min-w-0">
                            <EyeOff className="w-3.5 h-3.5 flex-shrink-0" aria-hidden="true" />
                            <span className="truncate">
                              {t('poll.vote_to_see_results', 'Vote to see results')}
                            </span>
                          </span>
                        ) : (
                          <span className="inline-flex items-center gap-1">
                            <Users className="w-3.5 h-3.5" aria-hidden="true" />
                            <span className="tabular-nums font-medium">
                              {totalVotes} {totalVotes === 1 ? t('card.vote', 'vote') : t('card.votes', 'votes')}
                            </span>
                          </span>
                        )}
                      </div>
                      <Button
                        as={Link}
                        to={pollDetailPath}
                        size="sm"
                        variant="flat"
                        color="warning"
                        endContent={<ArrowRight className="w-3.5 h-3.5" aria-hidden="true" />}
                        className="h-8 font-semibold"
                      >
                        {t('poll.view_full', 'View full poll')}
                      </Button>
                    </div>
                  </div>
                );
              })()
            ) : null}
          </div>
        )}

        {/* Review Display — oversized stars, pull-quote styling, receiver avatar inline */}
        {item.type === 'review' && item.rating && item.receiver && (
          <Card shadow="none" className="mb-4 bg-gradient-to-br from-amber-500/10 to-orange-500/10 border border-amber-500/20 overflow-hidden">
            <CardBody className="p-5">
              <div className="flex items-center justify-between gap-3 mb-3 flex-wrap">
                <Link
                  to={tenantPath(`/profile/${item.receiver.id}`)}
                  className="flex items-center gap-2.5 min-w-0 group/receiver"
                >
                  <Avatar
                    name={item.receiver.name}
                    size="sm"
                    className="ring-2 ring-amber-500/30 flex-shrink-0"
                  />
                  <div className="min-w-0">
                    <div className="text-[10px] font-semibold text-amber-700 dark:text-amber-400 uppercase tracking-wider leading-none">
                      {t('card.reviewed')}
                    </div>
                    <div className="text-sm font-semibold text-theme-primary group-hover/receiver:text-primary transition-colors truncate">
                      {item.receiver.name}
                    </div>
                  </div>
                </Link>
                <div className="flex items-center gap-1" aria-label={t('card.review.rating_aria', '{{rating}} out of 5 stars', { rating: item.rating })}>
                  {[1, 2, 3, 4, 5].map((star) => (
                    <Star
                      key={star}
                      className={`w-6 h-6 transition-colors ${star <= item.rating! ? 'text-amber-400 fill-amber-400 drop-shadow-sm' : 'text-[var(--border-default)]'}`}
                      aria-hidden="true"
                    />
                  ))}
                </div>
              </div>
              {item.content && (
                <blockquote className="relative border-s-4 border-amber-500/60 ps-4 py-1 text-[15px] text-theme-secondary italic leading-relaxed">
                  <span className="absolute -top-1 -start-1 text-2xl text-amber-500/40 leading-none select-none" aria-hidden="true">&ldquo;</span>
                  {item.content}
                </blockquote>
              )}
            </CardBody>
          </Card>
        )}

        {/* Badge Earned — celebratory "moment" framing with oversized icon */}
        {item.type === 'badge_earned' && (
          <Card shadow="none" className="mb-4 bg-gradient-to-br from-yellow-500/20 via-amber-500/15 to-orange-500/10 border border-yellow-500/30 overflow-hidden relative">
            <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top,_rgba(251,191,36,0.18),_transparent_70%)] pointer-events-none" aria-hidden="true" />
            <CardBody className="p-6 text-center relative">
              <div className="mx-auto mb-3 inline-flex items-center justify-center w-20 h-20 rounded-full bg-gradient-to-br from-yellow-400 to-amber-500 shadow-lg shadow-amber-500/30 text-5xl" aria-hidden="true">
                {item.badge_icon || '\uD83C\uDFC6'}
              </div>
              <p className="text-xs font-semibold text-amber-700 dark:text-amber-300 uppercase tracking-[0.2em] mb-1">
                {t('card.milestone.badge_unlocked', 'Badge unlocked')}
              </p>
              <p className="text-base font-bold text-theme-primary leading-snug">
                {t('card.badge_earned_message', {
                  name: author.name,
                  badge: item.badge_name || item.title || '',
                  defaultValue: '{{name}} earned the "{{badge}}" badge!',
                })}
              </p>
            </CardBody>
          </Card>
        )}

        {/* Level Up — celebratory "moment" framing with oversized icon */}
        {item.type === 'level_up' && (
          <Card shadow="none" className="mb-4 bg-gradient-to-br from-emerald-500/20 via-teal-500/15 to-cyan-500/10 border border-emerald-500/30 overflow-hidden relative">
            <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top,_rgba(16,185,129,0.18),_transparent_70%)] pointer-events-none" aria-hidden="true" />
            <CardBody className="p-6 text-center relative">
              <div className="mx-auto mb-3 inline-flex items-center justify-center w-20 h-20 rounded-full bg-gradient-to-br from-emerald-400 to-teal-500 shadow-lg shadow-emerald-500/30" aria-hidden="true">
                <Zap className="w-10 h-10 text-white fill-white" />
              </div>
              <p className="text-xs font-semibold text-emerald-700 dark:text-emerald-300 uppercase tracking-[0.2em] mb-1">
                {t('card.milestone.level_reached', 'Level reached')}
              </p>
              <p className="text-base font-bold text-theme-primary leading-snug">
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
              className={`inline-flex items-center justify-center gap-2 py-2 px-5 rounded-xl text-sm font-medium transition-all bg-gradient-to-r ${config.softGradient || 'from-primary/10 to-primary/5'} text-theme-primary hover:opacity-80 border border-theme-default hover:border-primary/30`}
              onClick={recordClick}
            >
              {config.icon}
              {t(detailLabel)}
              <ArrowRight className="w-3.5 h-3.5" aria-hidden="true" />
            </Link>
          </div>
        )}

        {/* Stats Row — Reactions + Comments Count + Views */}
        {((item.reactions?.total ?? item.likes_count) > 0 || localCommentsCount > 0 || (item.views_count ?? 0) > 0) && (
          <div className="flex items-center justify-between text-xs text-theme-subtle mb-3">
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
                className="text-xs text-theme-subtle hover:text-theme-primary p-0 min-w-0 h-auto"
                onPress={toggleComments}
              >
                {localCommentsCount} {localCommentsCount === 1 ? t('card.comment') : t('card.comments')}
              </Button>
            )}
          </div>
        )}

        {/* Divider before actions */}
        <Divider className="mb-1" />

        {/* Action Buttons — reactions + comment as primary; share + bookmark as ghost icon-only secondary actions */}
        <div className="flex items-center justify-between gap-1 -mx-1">
          <div className="flex items-center gap-1 min-w-0 flex-1">
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
                  className={`transition-all ${item.is_liked
                    ? 'text-rose-500 font-medium bg-rose-500/5'
                    : 'text-theme-muted hover:text-rose-500 hover:bg-rose-500/5'
                  }`}
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
                className={`transition-all ${showComments ? 'text-primary font-medium bg-primary/5' : 'text-theme-muted hover:text-primary hover:bg-primary/5'}`}
                startContent={<MessageCircle className={`w-[18px] h-[18px] ${showComments ? 'fill-primary/20' : ''}`} aria-hidden="true" />}
                onPress={toggleComments}
              >
                {t('card.comment_action')}
              </Button>
            </Tooltip>
          </div>

          <div className="flex items-center gap-0.5 flex-shrink-0">
            {/*
              Share button — polymorphic. Available for every type in SHAREABLE_TYPES
              (post, listing, event, poll, job, blog, discussion, goal, challenge, volunteer).
              The Quote Post option is auto-disabled for non-post types inside ShareButton
              because quote post requires a feed_posts row (quoted_post_id FK).
            */}
            {isAuthenticated && SHAREABLE_TYPES.has(item.type) && (
              <ShareButton
                type={item.type}
                id={item.id}
                shareCount={item.share_count ?? 0}
                isShared={item.is_shared ?? false}
                isAuthenticated={isAuthenticated}
                post={item}
                compact
              />
            )}

            {/*
              Bookmark — only rendered for types BookmarkService::VALID_TYPES supports.
              Keep this list in sync with app/Services/BookmarkService.php::VALID_TYPES.
            */}
            {isAuthenticated && BOOKMARKABLE_TYPES.has(item.type) && (
              <BookmarkButton type={item.type} id={item.id} isBookmarked={item.is_bookmarked} />
            )}
          </div>
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
              className="mt-3 pt-3 border-t border-theme-default space-y-3"
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
                      input: 'bg-transparent text-theme-primary text-sm',
                      inputWrapper: 'bg-theme-elevated border-theme-default hover:border-primary/40 h-9',
                    }}
                    endContent={
                      <Button
                        isIconOnly
                        size="sm"
                        variant="light"
                        className="text-primary min-w-0 w-auto h-auto p-0 disabled:opacity-30"
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
                <p className="text-xs text-theme-subtle text-center py-3 italic">
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
      </div>{/* end long-press touch target */}
    </GlassCard>
  );
});

export { FeedCard };
export default FeedCard;
