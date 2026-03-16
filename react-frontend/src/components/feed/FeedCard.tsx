// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * FeedCard — renders an individual feed item with content, actions, comments, and polls.
 * Extracted from FeedPage.tsx for maintainability.
 */

import React, { useState, useEffect } from 'react';
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
  Repeat2,
  BookOpen,
  Users,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { useTenant, useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl, resolveAssetUrl, formatRelativeTime, formatDate, formatTime } from '@/lib/helpers';
import { useFeedTracking } from '@/hooks/useFeedTracking';
import type { FeedItem, FeedComment, PollData } from './types';
import { getAuthor, getItemDetailPath, getItemDetailLabel } from './types';
import { FeedContentRenderer } from './FeedContentRenderer';

/* ───────────────────────── Props ───────────────────────── */

export interface FeedCardProps {
  item: FeedItem;
  /** Called with the feed item when the user toggles the like button. */
  onToggleLike: (item: FeedItem) => void;
  /** Called with the feed item when the user hides a post. */
  onHidePost: (item: FeedItem) => void;
  /** Called with the feed item when the user mutes the post author. */
  onMuteUser: (item: FeedItem) => void;
  /** Called with the feed item when the user reports a post. */
  onReportPost: (item: FeedItem) => void;
  /** Called with the feed item when the user deletes their own post. */
  onDeletePost: (item: FeedItem) => void;
  onVotePoll: (pollId: number, optionId: number) => void;
  isAuthenticated: boolean;
  currentUserId?: number;
}

/* ───────────────────────── Type Badge Config ───────────────────────── */

const typeConfig = {
  post: { label: null, color: 'default' as const, icon: null, gradient: '' },
  listing: {
    label: 'Listing',
    color: 'primary' as const,
    icon: <ShoppingBag className="w-3 h-3" aria-hidden="true" />,
    gradient: 'from-indigo-500/10 to-blue-500/10',
  },
  event: {
    label: 'Event',
    color: 'success' as const,
    icon: <Calendar className="w-3 h-3" aria-hidden="true" />,
    gradient: 'from-emerald-500/10 to-green-500/10',
  },
  poll: {
    label: 'Poll',
    color: 'warning' as const,
    icon: <BarChart3 className="w-3 h-3" aria-hidden="true" />,
    gradient: 'from-amber-500/10 to-orange-500/10',
  },
  goal: {
    label: 'Goal',
    color: 'secondary' as const,
    icon: <Target className="w-3 h-3" aria-hidden="true" />,
    gradient: 'from-purple-500/10 to-pink-500/10',
  },
  review: {
    label: 'Review',
    color: 'warning' as const,
    icon: <Star className="w-3 h-3" aria-hidden="true" />,
    gradient: 'from-amber-500/10 to-yellow-500/10',
  },
  job: {
    label: 'Job',
    color: 'primary' as const,
    icon: <TrendingUp className="w-3 h-3" aria-hidden="true" />,
    gradient: 'from-blue-500/10 to-cyan-500/10',
  },
  challenge: {
    label: 'Challenge',
    color: 'secondary' as const,
    icon: <Target className="w-3 h-3" aria-hidden="true" />,
    gradient: 'from-violet-500/10 to-purple-500/10',
  },
  volunteer: {
    label: 'Volunteer',
    color: 'success' as const,
    icon: <Heart className="w-3 h-3" aria-hidden="true" />,
    gradient: 'from-green-500/10 to-emerald-500/10',
  },
  blog: {
    label: 'Blog',
    color: 'primary' as const,
    icon: <BookOpen className="w-3 h-3" aria-hidden="true" />,
    gradient: 'from-sky-500/10 to-blue-500/10',
  },
  discussion: {
    label: 'Discussion',
    color: 'secondary' as const,
    icon: <Users className="w-3 h-3" aria-hidden="true" />,
    gradient: 'from-fuchsia-500/10 to-purple-500/10',
  },
};

/* ───────────────────────── Comment Item ───────────────────────── */

interface CommentItemProps {
  comment: FeedComment;
}

export const CommentItem = React.memo(function CommentItem({ comment }: CommentItemProps) {
  const { t } = useTranslation('feed');
  const { tenantPath } = useTenant();
  const [showReplies, setShowReplies] = useState(false);

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
          <p className="text-xs text-[var(--text-secondary)] mt-0.5 whitespace-pre-wrap leading-relaxed">{comment.content}</p>
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
  onHidePost,
  onMuteUser,
  onReportPost,
  onDeletePost,
  onVotePoll,
  isAuthenticated,
  currentUserId,
}: FeedCardProps) {
  const { t } = useTranslation('feed');
  const { tenantPath } = useTenant();
  const toast = useToast();
  const [showComments, setShowComments] = useState(false);
  const [comments, setComments] = useState<FeedComment[]>([]);
  const [isLoadingComments, setIsLoadingComments] = useState(false);
  const [newComment, setNewComment] = useState('');
  const [isSubmittingComment, setIsSubmittingComment] = useState(false);
  const [localCommentsCount, setLocalCommentsCount] = useState(item.comments_count);
  const [pollData, setPollData] = useState<PollData | null>(item.poll_data ?? null);
  const [isLoadingPoll, setIsLoadingPoll] = useState(false);

  const author = getAuthor(item);
  const isOwnPost = currentUserId === author.id;
  const config = typeConfig[item.type];
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

  return (
    <GlassCard ref={trackingRef} hoverable className="overflow-hidden group">
      {/* Type accent bar */}
      {config.label && (
        <div className={`h-0.5 bg-gradient-to-r ${config.gradient}`} />
      )}

      <div className="p-5">
        {/* Header */}
        <div className="flex items-start justify-between mb-4">
          <div className="flex items-center gap-3">
            <Link to={tenantPath(`/profile/${author.id}`)} className="relative">
              <Avatar
                name={author.name}
                src={resolveAvatarUrl(author.avatar)}
                size="md"
                className="ring-2 ring-[var(--border-default)] group-hover:ring-[var(--color-primary)]/30 transition-all"
                isBordered
              />
            </Link>
            <div className="min-w-0">
              <div className="flex items-center gap-2 flex-wrap">
                <Link
                  to={tenantPath(`/profile/${author.id}`)}
                  className="font-semibold text-[var(--text-primary)] hover:text-[var(--color-primary)] transition-colors text-sm truncate"
                >
                  {author.name}
                </Link>
                {config.label && (
                  detailPath ? (
                    <Link to={tenantPath(detailPath)} onClick={recordClick}>
                      <Chip
                        size="sm"
                        variant="flat"
                        color={config.color}
                        startContent={config.icon}
                        className="text-[10px] h-5 cursor-pointer hover:opacity-80 transition-opacity"
                      >
                        {config.label}
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
                      {config.label}
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

          {/* 3-dot moderation menu */}
          {isAuthenticated && (
            <Dropdown placement="bottom-end">
              <DropdownTrigger>
                <Button
                  isIconOnly
                  size="sm"
                  variant="light"
                  className="text-[var(--text-subtle)] hover:text-[var(--text-primary)] min-w-0 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity"
                  aria-label="Post options"
                >
                  <MoreHorizontal className="w-4 h-4" />
                </Button>
              </DropdownTrigger>
              <DropdownMenu aria-label="Post actions">
                {isOwnPost ? (
                  <DropdownItem
                    key="delete"
                    startContent={<Trash2 className="w-4 h-4" aria-hidden="true" />}
                    className="text-danger"
                    color="danger"
                    onPress={() => onDeletePost(item)}
                  >
                    {t('card.delete_post')}
                  </DropdownItem>
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
                  </>
                )}
              </DropdownMenu>
            </Dropdown>
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

        {/* Image */}
        {item.image_url && (
          <div className="mb-4 -mx-5 overflow-hidden">
            {detailPath ? (
              <Link to={tenantPath(detailPath)}>
                <img
                  src={resolveAssetUrl(item.image_url)}
                  alt={`${config.label ?? 'Post'} image by ${author.name}`}
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
                alt={`Post image by ${author.name}`}
                className="w-full max-h-[28rem] object-cover hover:scale-[1.02] transition-transform duration-500"
                loading="lazy"
                width={800}
                height={448}
              />
            )}
          </div>
        )}

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

        {/* View detail CTA — for listings, events, goals, reviews */}
        {detailPath && detailLabel && (
          <div className="mb-3">
            <Link
              to={tenantPath(detailPath)}
              className={`inline-flex items-center justify-center gap-2 py-2 px-5 rounded-xl text-sm font-medium transition-all bg-gradient-to-r ${config.gradient || 'from-[var(--color-primary)]/10 to-[var(--color-primary)]/5'} text-[var(--text-primary)] hover:opacity-80 border border-[var(--border-default)] hover:border-[var(--color-primary)]/30`}
            >
              {config.icon}
              {detailLabel}
              <ArrowRight className="w-3.5 h-3.5" aria-hidden="true" />
            </Link>
          </div>
        )}

        {/* Stats Row */}
        {(item.likes_count > 0 || localCommentsCount > 0) && (
          <div className="flex items-center justify-between text-xs text-[var(--text-subtle)] mb-3">
            <span>
              {item.likes_count > 0 && (
                <span className="flex items-center gap-1.5">
                  <span className="flex -space-x-0.5">
                    <span className="w-4 h-4 rounded-full bg-gradient-to-br from-rose-500 to-pink-500 flex items-center justify-center">
                      <Heart className="w-2.5 h-2.5 text-white fill-white" aria-hidden="true" />
                    </span>
                  </span>
                  {item.likes_count} {item.likes_count === 1 ? t('card.like') : t('card.likes')}
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

          {/* Share Button */}
          {item.type === 'post' && isAuthenticated && (
            <Tooltip content={t('card.share_action', 'Share')} delay={400} closeDelay={0} size="sm">
              <Button
                size="sm"
                variant="light"
                className="flex-1 max-w-[140px] text-[var(--text-muted)] hover:text-emerald-500 transition-colors"
                startContent={<Repeat2 className="w-[18px] h-[18px]" aria-hidden="true" />}
                onPress={async () => {
                  try {
                    const res = await api.post(`/v2/feed/posts/${item.id}/share`);
                    if (res.success) {
                      toast.success(t('card.shared_success', 'Post shared to your feed'));
                    } else {
                      toast.error(res.error || t('card.share_failed', 'Failed to share'));
                    }
                  } catch {
                    toast.error(t('card.share_failed', 'Failed to share'));
                  }
                }}
              >
                {t('card.share_action', 'Share')}
              </Button>
            </Tooltip>
          )}
        </div>

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
                        aria-label="Send comment"
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
                    <CommentItem key={comment.id} comment={comment} />
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
