// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * CommentsSection — Reusable threaded comments with reactions, replies, edit, delete.
 * Drop this into any content page (listings, events, blog posts, etc.).
 */

import { useState, useCallback, useEffect, useRef } from 'react';
import { Link } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import {
  Button,
  Avatar,
  Input,
  Textarea,
  Skeleton,
  Tooltip,
} from '@heroui/react';
import {
  Send,
  MessageCircle,
  Clock,
  CornerDownRight,
  Pencil,
  Trash2,
  Check,
  X,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useTenant } from '@/contexts';
import { resolveAvatarUrl, formatRelativeTime } from '@/lib/helpers';
import type { FeedComment } from '@/components/feed/types';
import { AVAILABLE_REACTIONS } from '@/hooks/useSocialInteractions';
import type { MentionUser } from '@/hooks/useSocialInteractions';

/* ─── Props ─────────────────────────────────────────────────── */

export interface CommentsSectionProps {
  comments: FeedComment[];
  commentsCount: number;
  commentsLoading: boolean;
  commentsLoaded: boolean;
  loadComments: () => Promise<void>;
  submitComment: (content: string, parentId?: number) => Promise<boolean>;
  editComment: (commentId: number, content: string) => Promise<boolean>;
  deleteComment: (commentId: number) => Promise<boolean>;
  toggleReaction: (commentId: number, emoji: string) => Promise<void>;
  isAuthenticated: boolean;
  currentUserId?: number;
  currentUserAvatar?: string;
  currentUserName?: string;
  searchMentions?: (query: string) => Promise<MentionUser[]>;
}

/* ─── Single Comment ────────────────────────────────────────── */

interface CommentItemProps {
  comment: FeedComment;
  depth: number;
  onReply: (parentId: number) => void;
  onEdit: (commentId: number, content: string) => Promise<boolean>;
  onDelete: (commentId: number) => Promise<boolean>;
  onToggleReaction: (commentId: number, emoji: string) => Promise<void>;
  isAuthenticated: boolean;
  currentUserId?: number;
}

function CommentItemInner({
  comment,
  depth,
  onReply,
  onEdit,
  onDelete,
  onToggleReaction,
  isAuthenticated,
  currentUserId,
}: CommentItemProps) {
  const { t } = useTranslation('social');
  const { tenantPath } = useTenant();

  const [isEditing, setIsEditing] = useState(false);
  const [editContent, setEditContent] = useState(comment.content);
  const [isSubmittingEdit, setIsSubmittingEdit] = useState(false);
  const [showReactions, setShowReactions] = useState(false);

  const isOwn = currentUserId === comment.author.id || comment.is_own;

  const handleSaveEdit = async () => {
    if (!editContent.trim()) return;
    setIsSubmittingEdit(true);
    const ok = await onEdit(comment.id, editContent.trim());
    if (ok) setIsEditing(false);
    setIsSubmittingEdit(false);
  };

  const handleDelete = async () => {
    if (!window.confirm(t('delete_comment_confirm', 'Delete this comment?'))) return;
    await onDelete(comment.id);
  };

  const reactions = comment.reactions ?? {};
  const userReactions = comment.user_reactions ?? [];
  const hasReactions = Object.keys(reactions).length > 0;

  return (
    <div className={`flex items-start gap-2.5 ${depth > 0 ? 'ml-6 sm:ml-8 pl-3 border-l-2 border-[var(--color-primary)]/20' : ''}`}>
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
              <span className="text-[10px] text-[var(--text-subtle)] italic">
                ({t('edited', 'edited')})
              </span>
            )}
          </div>

          {isEditing ? (
            <div className="mt-1.5 space-y-2">
              <Textarea
                value={editContent}
                onChange={(e) => setEditContent(e.target.value)}
                minRows={1}
                maxRows={4}
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
                  aria-label={t('save', 'Save')}
                >
                  <Check className="w-3 h-3" />
                </Button>
                <Button
                  size="sm"
                  isIconOnly
                  variant="flat"
                  className="w-6 h-6 min-w-0 bg-red-500/10 text-red-500"
                  onPress={() => { setIsEditing(false); setEditContent(comment.content); }}
                  aria-label={t('cancel', 'Cancel')}
                >
                  <X className="w-3 h-3" />
                </Button>
              </div>
            </div>
          ) : (
            <p className="text-xs text-[var(--text-secondary)] mt-0.5 whitespace-pre-wrap leading-relaxed">
              {comment.content}
            </p>
          )}
        </div>

        {/* Action row */}
        <div className="flex items-center gap-3 mt-1 px-1 flex-wrap">
          <span className="text-[10px] text-[var(--text-subtle)]">
            <Clock className="w-2.5 h-2.5 inline mr-0.5 -mt-px" aria-hidden="true" />
            {formatRelativeTime(comment.created_at)}
          </span>

          {isAuthenticated && !isEditing && (
            <>
              {depth < 2 && (
                <Button
                  variant="light"
                  size="sm"
                  onPress={() => onReply(comment.id)}
                  className="text-[10px] text-[var(--color-primary)] hover:underline flex items-center gap-0.5 h-auto p-0 min-w-0"
                  startContent={<CornerDownRight className="w-2.5 h-2.5" aria-hidden="true" />}
                >
                  {t('reply', 'Reply')}
                </Button>
              )}

              <Button
                variant="light"
                size="sm"
                onPress={() => setShowReactions(!showReactions)}
                className="text-[10px] text-[var(--text-subtle)] hover:text-[var(--text-primary)] h-auto p-0 min-w-0"
              >
                {t('react', 'React')}
              </Button>

              {isOwn && (
                <>
                  <Button
                    variant="light"
                    size="sm"
                    onPress={() => { setIsEditing(true); setEditContent(comment.content); }}
                    className="text-[10px] text-[var(--text-subtle)] hover:text-[var(--text-primary)] flex items-center gap-0.5 h-auto p-0 min-w-0"
                    startContent={<Pencil className="w-2.5 h-2.5" aria-hidden="true" />}
                  >
                    {t('edit', 'Edit')}
                  </Button>
                  <Button
                    variant="light"
                    size="sm"
                    onPress={handleDelete}
                    className="text-[10px] text-red-400 hover:text-red-500 flex items-center gap-0.5 h-auto p-0 min-w-0"
                    startContent={<Trash2 className="w-2.5 h-2.5" aria-hidden="true" />}
                  >
                    {t('delete', 'Delete')}
                  </Button>
                </>
              )}
            </>
          )}
        </div>

        {/* Emoji reactions display */}
        {hasReactions && (
          <div className="flex flex-wrap gap-1 mt-1 px-1">
            {Object.entries(reactions).map(([emoji, count]) => (
              <Tooltip key={emoji} content={`${emoji} (${count})`} size="sm" delay={300} closeDelay={0}>
                <Button
                  variant="flat"
                  size="sm"
                  onPress={() => isAuthenticated && onToggleReaction(comment.id, emoji)}
                  className={`inline-flex items-center gap-0.5 text-[10px] px-1.5 py-0.5 rounded-full border transition-colors h-auto min-w-0 ${
                    userReactions.includes(emoji)
                      ? 'border-[var(--color-primary)]/50 bg-[var(--color-primary)]/10 text-[var(--color-primary)]'
                      : 'border-[var(--border-default)] bg-[var(--surface-elevated)] text-[var(--text-subtle)]'
                  }`}
                >
                  <span>{emoji}</span>
                  <span>{count as number}</span>
                </Button>
              </Tooltip>
            ))}
          </div>
        )}

        {/* Reaction picker */}
        <AnimatePresence>
          {showReactions && (
            <motion.div
              initial={{ opacity: 0, y: -4 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -4 }}
              className="flex gap-1 mt-1 px-1"
            >
              {AVAILABLE_REACTIONS.map((emoji) => (
                <Button
                  key={emoji}
                  isIconOnly
                  size="sm"
                  variant="flat"
                  onPress={() => { onToggleReaction(comment.id, emoji); setShowReactions(false); }}
                  className={`w-7 h-7 rounded-full flex items-center justify-center text-sm hover:scale-125 transition-transform min-w-0 ${
                    userReactions.includes(emoji) ? 'bg-[var(--color-primary)]/20 ring-1 ring-[var(--color-primary)]' : 'hover:bg-[var(--surface-hover)]'
                  }`}
                  aria-label={emoji}
                >
                  {emoji}
                </Button>
              ))}
            </motion.div>
          )}
        </AnimatePresence>

        {/* Nested replies */}
        {comment.replies && comment.replies.length > 0 && (
          <div className="mt-2 space-y-2">
            {comment.replies.map((reply) => (
              <CommentItemInner
                key={reply.id}
                comment={reply}
                depth={depth + 1}
                onReply={onReply}
                onEdit={onEdit}
                onDelete={onDelete}
                onToggleReaction={onToggleReaction}
                isAuthenticated={isAuthenticated}
                currentUserId={currentUserId}
              />
            ))}
          </div>
        )}
      </div>
    </div>
  );
}

/* ─── Mention Input ─────────────────────────────────────────── */

interface MentionInputProps {
  value: string;
  onChange: (value: string) => void;
  onKeyDown?: (e: React.KeyboardEvent<HTMLInputElement>) => void;
  placeholder?: string;
  size?: 'sm' | 'md' | 'lg';
  radius?: 'none' | 'sm' | 'md' | 'lg' | 'full';
  classNames?: Record<string, string>;
  endContent?: React.ReactNode;
  autoFocus?: boolean;
  searchMentions?: (query: string) => Promise<MentionUser[]>;
}

function MentionInput({
  value,
  onChange,
  onKeyDown,
  placeholder,
  size = 'sm',
  radius = 'full',
  classNames: inputClassNames,
  endContent,
  autoFocus,
  searchMentions,
}: MentionInputProps) {
  const [mentionResults, setMentionResults] = useState<MentionUser[]>([]);
  const [showMentions, setShowMentions] = useState(false);
  const [mentionQuery, setMentionQuery] = useState('');
  const [selectedIndex, setSelectedIndex] = useState(0);
  const debounceRef = useRef<ReturnType<typeof setTimeout>>();
  const inputRef = useRef<HTMLInputElement>(null);

  const handleChange = (newValue: string) => {
    onChange(newValue);

    if (!searchMentions) return;

    // Detect @mention pattern: @ followed by 2+ word characters at end of input or before space
    const match = newValue.match(/@(\w{2,})$/);
    if (match) {
      const query = match[1];
      setMentionQuery(query);
      setSelectedIndex(0);
      if (debounceRef.current) clearTimeout(debounceRef.current);
      debounceRef.current = setTimeout(async () => {
        const results = await searchMentions(query);
        setMentionResults(results.slice(0, 5));
        setShowMentions(results.length > 0);
      }, 300);
    } else {
      setShowMentions(false);
      setMentionResults([]);
    }
  };

  const selectMention = (user: MentionUser) => {
    // Replace @query with @Username
    const newValue = value.replace(new RegExp(`@${mentionQuery}$`), `@${user.name} `);
    onChange(newValue);
    setShowMentions(false);
    setMentionResults([]);
    inputRef.current?.focus();
  };

  const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (showMentions && mentionResults.length > 0) {
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        setSelectedIndex((prev) => (prev + 1) % mentionResults.length);
        return;
      }
      if (e.key === 'ArrowUp') {
        e.preventDefault();
        setSelectedIndex((prev) => (prev - 1 + mentionResults.length) % mentionResults.length);
        return;
      }
      if (e.key === 'Enter') {
        e.preventDefault();
        selectMention(mentionResults[selectedIndex]);
        return;
      }
      if (e.key === 'Escape') {
        e.preventDefault();
        setShowMentions(false);
        return;
      }
    }
    onKeyDown?.(e);
  };

  // Cleanup debounce on unmount
  useEffect(() => {
    return () => { if (debounceRef.current) clearTimeout(debounceRef.current); };
  }, []);

  return (
    <div className="relative flex-1">
      <Input
        ref={inputRef}
        placeholder={placeholder}
        aria-label={placeholder || "Write a comment"}
        value={value}
        onChange={(e) => handleChange(e.target.value)}
        onKeyDown={handleKeyDown}
        onBlur={() => { setTimeout(() => setShowMentions(false), 200); }}
        size={size}
        radius={radius}
        classNames={inputClassNames}
        endContent={endContent}
        autoFocus={autoFocus}
      />

      {/* Mention dropdown */}
      {showMentions && mentionResults.length > 0 && (
        <div className="absolute left-0 right-0 top-full mt-1 z-50 bg-[var(--surface-elevated)] border border-[var(--border-default)] rounded-lg shadow-lg overflow-hidden">
          {mentionResults.map((user, idx) => (
            <Button
              key={user.id}
              variant="light"
              onPress={() => selectMention(user)}
              onMouseDown={(e) => { e.preventDefault(); }}
              onMouseEnter={() => setSelectedIndex(idx)}
              className={`w-full flex items-center gap-2.5 px-3 py-2 text-left transition-colors justify-start h-auto rounded-none ${
                idx === selectedIndex
                  ? 'bg-[var(--color-primary)]/10 text-[var(--color-primary)]'
                  : 'text-[var(--text-primary)] hover:bg-[var(--surface-hover)]'
              }`}
            >
              <Avatar
                name={user.name}
                src={resolveAvatarUrl(user.avatar_url)}
                size="sm"
                className="w-6 h-6 flex-shrink-0"
              />
              <span className="text-xs font-medium truncate">{user.name}</span>
            </Button>
          ))}
        </div>
      )}
    </div>
  );
}

/* ─── Main Component ────────────────────────────────────────── */

export function CommentsSection({
  comments,
  commentsCount,
  commentsLoading,
  commentsLoaded,
  loadComments,
  submitComment,
  editComment,
  deleteComment,
  toggleReaction,
  isAuthenticated,
  currentUserId,
  currentUserAvatar,
  currentUserName,
  searchMentions,
}: CommentsSectionProps) {
  const { t } = useTranslation('social');

  const [newComment, setNewComment] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [replyingTo, setReplyingTo] = useState<number | null>(null);
  const [replyContent, setReplyContent] = useState('');
  const [isSubmittingReply, setIsSubmittingReply] = useState(false);

  // Load comments on mount if not already loaded
  useEffect(() => {
    if (!commentsLoaded && !commentsLoading) {
      void loadComments();
    }
  }, [commentsLoaded, commentsLoading, loadComments]);

  const handleSubmit = async () => {
    if (!newComment.trim() || isSubmitting) return;
    setIsSubmitting(true);
    const ok = await submitComment(newComment.trim());
    if (ok) setNewComment('');
    setIsSubmitting(false);
  };

  const handleReply = useCallback((parentId: number) => {
    setReplyingTo(parentId);
    setReplyContent('');
  }, []);

  const handleSubmitReply = async () => {
    if (!replyContent.trim() || isSubmittingReply || !replyingTo) return;
    setIsSubmittingReply(true);
    const ok = await submitComment(replyContent.trim(), replyingTo);
    if (ok) {
      setReplyingTo(null);
      setReplyContent('');
    }
    setIsSubmittingReply(false);
  };

  return (
    <div className="space-y-4">
      {/* Header */}
      <h3 className="text-sm font-semibold text-[var(--text-primary)] flex items-center gap-2">
        <MessageCircle className="w-4 h-4 text-[var(--color-primary)]" aria-hidden="true" />
        {t('comments_title', 'Comments')} {commentsCount > 0 && `(${commentsCount})`}
      </h3>

      {/* New comment input */}
      {isAuthenticated && (
        <div className="flex items-start gap-2.5">
          <Avatar
            name={currentUserName || 'You'}
            src={resolveAvatarUrl(currentUserAvatar)}
            size="sm"
            className="w-7 h-7 flex-shrink-0 ring-2 ring-white/10"
          />
          <MentionInput
            placeholder={t('write_comment', 'Write a comment...')}
            value={newComment}
            onChange={setNewComment}
            onKeyDown={(e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); void handleSubmit(); } }}
            size="sm"
            radius="full"
            classNames={{
              input: 'bg-transparent text-[var(--text-primary)] text-sm',
              inputWrapper: 'bg-[var(--surface-elevated)] border-[var(--border-default)] hover:border-[var(--color-primary)]/40 h-9',
            }}
            searchMentions={searchMentions}
            endContent={
              <Button
                isIconOnly
                size="sm"
                variant="light"
                className="text-[var(--color-primary)] min-w-0 w-auto h-auto p-0 disabled:opacity-30"
                onPress={handleSubmit}
                isDisabled={!newComment.trim() || isSubmitting}
                aria-label={t('send_comment', 'Send comment')}
              >
                <Send className="w-4 h-4" />
              </Button>
            }
          />
        </div>
      )}

      {/* Comments list */}
      {commentsLoading ? (
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
          {t('no_comments', 'No comments yet. Be the first to comment!')}
        </p>
      ) : (
        <div className="space-y-3">
          {comments.map((comment) => (
            <div key={comment.id}>
              <CommentItemInner
                comment={comment}
                depth={0}
                onReply={handleReply}
                onEdit={editComment}
                onDelete={deleteComment}
                onToggleReaction={toggleReaction}
                isAuthenticated={isAuthenticated}
                currentUserId={currentUserId}
              />

              {/* Reply input for this comment */}
              {replyingTo === comment.id && isAuthenticated && (
                <motion.div
                  initial={{ opacity: 0, height: 0 }}
                  animate={{ opacity: 1, height: 'auto' }}
                  className="ml-10 sm:ml-12 mt-2"
                >
                  <div className="flex gap-2 items-end">
                    <MentionInput
                      placeholder={t('write_reply', 'Write a reply...')}
                      value={replyContent}
                      onChange={setReplyContent}
                      onKeyDown={(e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); void handleSubmitReply(); } }}
                      size="sm"
                      radius="full"
                      classNames={{
                        input: 'bg-transparent text-[var(--text-primary)] text-xs',
                        inputWrapper: 'bg-[var(--surface-elevated)] border-[var(--border-default)] h-8',
                      }}
                      autoFocus
                      searchMentions={searchMentions}
                      endContent={
                        <div className="flex gap-1">
                          <Button
                            isIconOnly
                            size="sm"
                            variant="light"
                            className="text-[var(--color-primary)] min-w-0 w-auto h-auto p-0 disabled:opacity-30"
                            onPress={handleSubmitReply}
                            isDisabled={!replyContent.trim() || isSubmittingReply}
                            aria-label={t('send_reply', 'Send reply')}
                          >
                            <Send className="w-3.5 h-3.5" />
                          </Button>
                          <Button
                            isIconOnly
                            size="sm"
                            variant="light"
                            className="text-[var(--text-subtle)] min-w-0 w-auto h-auto p-0"
                            onPress={() => setReplyingTo(null)}
                            aria-label={t('cancel', 'Cancel')}
                          >
                            <X className="w-3.5 h-3.5" />
                          </Button>
                        </div>
                      }
                    />
                  </div>
                </motion.div>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

export default CommentsSection;
