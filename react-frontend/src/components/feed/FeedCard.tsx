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
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl, resolveAssetUrl, formatRelativeTime } from '@/lib/helpers';
import type { FeedItem, FeedComment, PollData } from './types';
import { getAuthor } from './types';

/* ───────────────────────── Props ───────────────────────── */

export interface FeedCardProps {
  item: FeedItem;
  onToggleLike: () => void;
  onHidePost: () => void;
  onMuteUser: () => void;
  onReportPost: () => void;
  onDeletePost: () => void;
  onVotePoll: (pollId: number, optionId: number) => void;
  isAuthenticated: boolean;
  currentUserId?: number;
}

/* ───────────────────────── Comment Item ───────────────────────── */

interface CommentItemProps {
  comment: FeedComment;
}

export function CommentItem({ comment }: CommentItemProps) {
  const { tenantPath } = useTenant();
  const [showReplies, setShowReplies] = useState(false);

  return (
    <div className="flex items-start gap-2">
      <Link to={tenantPath(`/profile/${comment.author.id}`)}>
        <Avatar
          name={comment.author.name}
          src={resolveAvatarUrl(comment.author.avatar)}
          size="sm"
          className="w-7 h-7 flex-shrink-0"
        />
      </Link>
      <div className="flex-1 min-w-0">
        <div className="bg-theme-elevated rounded-xl px-3 py-2">
          <div className="flex items-center gap-2">
            <Link
              to={tenantPath(`/profile/${comment.author.id}`)}
              className="text-xs font-semibold text-theme-primary hover:underline"
            >
              {comment.author.name}
            </Link>
            {comment.edited && (
              <span className="text-xs text-theme-subtle">(edited)</span>
            )}
          </div>
          <p className="text-xs text-theme-muted mt-0.5 whitespace-pre-wrap">{comment.content}</p>
        </div>
        <div className="flex items-center gap-3 mt-1 px-1">
          <span className="text-xs text-theme-subtle">{formatRelativeTime(comment.created_at)}</span>
          {comment.replies && comment.replies.length > 0 && (
            <Button
              variant="light"
              size="sm"
              className="text-xs text-indigo-500 p-0 min-w-0 h-auto"
              onPress={() => setShowReplies(!showReplies)}
            >
              {showReplies ? 'Hide' : `${comment.replies.length}`} {comment.replies.length === 1 ? 'reply' : 'replies'}
            </Button>
          )}
        </div>

        {/* Nested Replies */}
        {showReplies && comment.replies && (
          <div className="mt-2 ml-2 space-y-2 border-l-2 border-theme-default pl-2">
            {comment.replies.map((reply) => (
              <div key={reply.id} className="flex items-start gap-2">
                <Avatar
                  name={reply.author.name}
                  src={resolveAvatarUrl(reply.author.avatar)}
                  size="sm"
                  className="w-6 h-6 flex-shrink-0"
                />
                <div>
                  <div className="bg-theme-elevated rounded-xl px-2.5 py-1.5">
                    <span className="text-xs font-semibold text-theme-primary">{reply.author.name}</span>
                    <p className="text-xs text-theme-muted whitespace-pre-wrap">{reply.content}</p>
                  </div>
                  <span className="text-xs text-theme-subtle ml-1">{formatRelativeTime(reply.created_at)}</span>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}

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
  const { tenantPath } = useTenant();
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

  const typeLabel = {
    post: null,
    listing: 'Listing',
    event: 'Event',
    poll: 'Poll',
    goal: 'Goal',
    review: 'Review',
  }[item.type];

  const typeColor = {
    post: 'default',
    listing: 'primary',
    event: 'success',
    poll: 'warning',
    goal: 'secondary',
    review: 'warning',
  }[item.type] as 'default' | 'primary' | 'success' | 'warning' | 'secondary';

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
    <GlassCard className="p-5">
      {/* Header */}
      <div className="flex items-start justify-between mb-3">
        <div className="flex items-center gap-3">
          <Link to={tenantPath(`/profile/${author.id}`)}>
            <Avatar
              name={author.name}
              src={resolveAvatarUrl(author.avatar)}
              size="sm"
            />
          </Link>
          <div>
            <div className="flex items-center gap-2">
              <Link
                to={tenantPath(`/profile/${author.id}`)}
                className="font-semibold text-theme-primary hover:underline text-sm"
              >
                {author.name}
              </Link>
              {typeLabel && (
                <Chip size="sm" variant="flat" color={typeColor} className="text-xs">
                  {typeLabel}
                </Chip>
              )}
            </div>
            <p className="text-xs text-theme-subtle">{formatRelativeTime(item.created_at)}</p>
          </div>
        </div>

        {/* 3-dot moderation menu */}
        {isAuthenticated && (
          <Dropdown>
            <DropdownTrigger>
              <Button
                isIconOnly
                size="sm"
                variant="light"
                className="text-theme-subtle min-w-0"
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
                  onPress={onDeletePost}
                >
                  Delete Post
                </DropdownItem>
              ) : (
                <>
                  <DropdownItem
                    key="hide"
                    startContent={<EyeOff className="w-4 h-4" aria-hidden="true" />}
                    onPress={onHidePost}
                  >
                    Hide Post
                  </DropdownItem>
                  <DropdownItem
                    key="mute"
                    startContent={<VolumeX className="w-4 h-4" aria-hidden="true" />}
                    onPress={onMuteUser}
                  >
                    Mute {author.name}
                  </DropdownItem>
                  <DropdownItem
                    key="report"
                    startContent={<Flag className="w-4 h-4" aria-hidden="true" />}
                    className="text-danger"
                    color="danger"
                    onPress={onReportPost}
                  >
                    Report Post
                  </DropdownItem>
                </>
              )}
            </DropdownMenu>
          </Dropdown>
        )}
      </div>

      {/* Content */}
      <div className="mb-3">
        {item.title && item.title !== item.content && (
          <p className="text-sm font-semibold text-theme-primary mb-1">{item.title}</p>
        )}
        <p className="text-sm text-theme-primary whitespace-pre-wrap">{item.content}</p>
      </div>

      {/* Image */}
      {item.image_url && (
        <div className="mb-3 rounded-xl overflow-hidden">
          <img
            src={resolveAssetUrl(item.image_url)}
            alt={`Post image by ${author.name}`}
            className="w-full max-h-96 object-cover"
            loading="lazy"
          />
        </div>
      )}

      {/* Poll Display */}
      {item.type === 'poll' && (
        <div className="mb-3">
          {isLoadingPoll ? (
            <div className="space-y-2 animate-pulse">
              <div className="h-8 bg-theme-hover rounded w-full" />
              <div className="h-8 bg-theme-hover rounded w-full" />
              <div className="h-8 bg-theme-hover rounded w-3/4" />
            </div>
          ) : pollData ? (
            <div className="space-y-2">
              {pollData.options.map((option) => {
                const isVoted = pollData.user_vote_option_id === option.id;
                const hasVoted = pollData.user_vote_option_id !== null;

                return (
                  <div key={option.id}>
                    {hasVoted ? (
                      /* Show results */
                      <div className="relative">
                        <div className="flex items-center justify-between mb-1">
                          <span className={`text-sm ${isVoted ? 'font-semibold text-indigo-400' : 'text-theme-primary'}`}>
                            {isVoted && <Check className="w-3.5 h-3.5 inline mr-1" aria-hidden="true" />}
                            {option.text}
                          </span>
                          <span className="text-xs text-theme-muted ml-2">{option.percentage}%</span>
                        </div>
                        <Progress
                          value={option.percentage}
                          size="sm"
                          color={isVoted ? 'primary' : 'default'}
                          classNames={{
                            track: 'bg-theme-elevated',
                          }}
                          aria-label={`${option.text}: ${option.percentage}%`}
                        />
                      </div>
                    ) : (
                      /* Show vote button */
                      <Button
                        variant="bordered"
                        size="sm"
                        className="w-full justify-start text-theme-primary border-theme-default hover:bg-indigo-500/10"
                        onPress={() => handleVote(option.id)}
                      >
                        {option.text}
                      </Button>
                    )}
                  </div>
                );
              })}
              <p className="text-xs text-theme-subtle pt-1">
                {pollData.total_votes} {pollData.total_votes === 1 ? 'vote' : 'votes'}
              </p>
            </div>
          ) : null}
        </div>
      )}

      {/* Review Display */}
      {item.type === 'review' && item.rating && item.receiver && (
        <div className="mb-3 p-4 rounded-lg bg-gradient-to-br from-amber-500/10 to-orange-500/10 border border-amber-500/20">
          <div className="flex items-center justify-between mb-2">
            <div className="flex items-center gap-2">
              <span className="text-sm text-theme-muted">Reviewed</span>
              <Link
                to={tenantPath(`/profile/${item.receiver.id}`)}
                className="text-sm font-medium text-theme-primary hover:text-indigo-600 dark:hover:text-indigo-400"
              >
                {item.receiver.name}
              </Link>
            </div>
            <div className="flex items-center gap-1">
              {[1, 2, 3, 4, 5].map((star) => (
                <Star
                  key={star}
                  className={`w-4 h-4 ${star <= item.rating! ? 'text-amber-400 fill-amber-400' : 'text-theme-muted'}`}
                  aria-hidden="true"
                />
              ))}
            </div>
          </div>
          {item.content && (
            <p className="text-sm text-theme-secondary italic">&quot;{item.content}&quot;</p>
          )}
        </div>
      )}

      {/* Stats Row */}
      {(item.likes_count > 0 || localCommentsCount > 0) && (
        <div className="flex items-center justify-between text-xs text-theme-subtle mb-3 pb-3 border-b border-theme-default">
          <span>
            {item.likes_count > 0 && (
              <span className="flex items-center gap-1">
                <Heart className="w-3 h-3 text-rose-400 fill-rose-400" aria-hidden="true" />
                {item.likes_count} {item.likes_count === 1 ? 'like' : 'likes'}
              </span>
            )}
          </span>
          {localCommentsCount > 0 && (
            <Button
              variant="light"
              size="sm"
              className="text-xs text-theme-subtle p-0 min-w-0 h-auto"
              onPress={toggleComments}
            >
              {localCommentsCount} {localCommentsCount === 1 ? 'comment' : 'comments'}
            </Button>
          )}
        </div>
      )}

      {/* Action Buttons */}
      <div className="flex items-center gap-1">
        <Button
          size="sm"
          variant="light"
          className={item.is_liked ? 'text-rose-500' : 'text-theme-muted'}
          startContent={
            <Heart
              className={`w-4 h-4 ${item.is_liked ? 'fill-rose-500 text-rose-500' : ''}`}
              aria-hidden="true"
            />
          }
          onPress={isAuthenticated ? onToggleLike : undefined}
          isDisabled={!isAuthenticated}
        >
          Like
        </Button>

        <Button
          size="sm"
          variant="light"
          className="text-theme-muted"
          startContent={<MessageCircle className="w-4 h-4" aria-hidden="true" />}
          onPress={toggleComments}
        >
          Comment
        </Button>
      </div>

      {/* Comments Section */}
      <AnimatePresence>
        {showComments && (
          <motion.div
            initial={{ opacity: 0, height: 0 }}
            animate={{ opacity: 1, height: 'auto' }}
            exit={{ opacity: 0, height: 0 }}
            className="mt-4 border-t border-theme-default pt-4 space-y-3"
          >
            {/* Comment Input */}
            {isAuthenticated && (
              <div className="flex items-start gap-2">
                <Input
                  placeholder="Write a comment..."
                  value={newComment}
                  onChange={(e) => setNewComment(e.target.value)}
                  onKeyDown={(e) => e.key === 'Enter' && !e.shiftKey && handleSubmitComment()}
                  size="sm"
                  classNames={{
                    input: 'bg-transparent text-theme-primary text-sm',
                    inputWrapper: 'bg-theme-elevated border-theme-default h-9',
                  }}
                  endContent={
                    <Button
                      isIconOnly
                      size="sm"
                      variant="light"
                      className="text-indigo-500 min-w-0 w-auto h-auto p-0"
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
              <div className="space-y-2">
                {[1, 2].map((i) => (
                  <div key={i} className="flex items-start gap-2 animate-pulse">
                    <div className="w-7 h-7 rounded-full bg-theme-hover flex-shrink-0" />
                    <div className="flex-1">
                      <div className="h-3 bg-theme-hover rounded w-1/4 mb-1" />
                      <div className="h-3 bg-theme-hover rounded w-3/4" />
                    </div>
                  </div>
                ))}
              </div>
            ) : comments.length === 0 ? (
              <p className="text-xs text-theme-subtle text-center py-2">No comments yet. Be the first!</p>
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
    </GlassCard>
  );
});

export { FeedCard };
export default FeedCard;
