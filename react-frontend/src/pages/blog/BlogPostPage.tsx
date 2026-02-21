// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Blog Post Detail Page - Single blog article view with comments
 *
 * Uses V2 API: GET /api/v2/blog/{slug}
 * Uses V2 API: GET /api/v2/comments?target_type=blog_post&target_id={id}
 * Uses V2 API: POST /api/v2/comments
 * Uses V2 API: POST /api/v2/comments/{id}/reactions
 */

import { useState, useEffect, useCallback } from 'react';
import { useParams, Link } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import {
  Button,
  Input,
  Chip,
  Avatar,
  Textarea,
} from '@heroui/react';
import {
  RefreshCw,
  AlertTriangle,
  Calendar,
  Clock,
  Eye,
  ArrowLeft,
  MessageCircle,
  Send,
  ChevronDown,
  ChevronUp,
  Reply,
  Smile,
  Heart,
  ThumbsUp,
  ThumbsDown,
  Laugh,
  Angry,
} from 'lucide-react';
import DOMPurify from 'dompurify';
import { GlassCard } from '@/components/ui';
import { Breadcrumbs } from '@/components/navigation';
import { PageMeta } from '@/components/seo';
import { useAuth, useToast, useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAssetUrl, resolveAvatarUrl, formatRelativeTime } from '@/lib/helpers';

/* ───────────────────────── Types ───────────────────────── */

interface BlogPostDetail {
  id: number;
  title: string;
  slug: string;
  excerpt: string;
  content: string;
  featured_image: string | null;
  published_at: string;
  created_at: string;
  views: number;
  reading_time: number;
  meta_title: string | null;
  meta_description: string | null;
  author: {
    id: number;
    name: string;
    avatar: string | null;
  };
  category: {
    id: number;
    name: string;
    color: string;
  } | null;
}

interface CommentAuthor {
  id: number;
  name: string;
  avatar: string | null;
}

interface BlogComment {
  id: number;
  content: string;
  created_at: string;
  edited: boolean;
  is_own: boolean;
  author: CommentAuthor;
  reactions: Record<string, number>;
  user_reactions: string[];
  replies: BlogComment[];
}

/* ───────────────────────── Emoji Picker ───────────────────────── */

const REACTION_EMOJIS = [
  { emoji: 'heart', icon: Heart, label: 'Love' },
  { emoji: 'thumbs_up', icon: ThumbsUp, label: 'Like' },
  { emoji: 'thumbs_down', icon: ThumbsDown, label: 'Dislike' },
  { emoji: 'laugh', icon: Laugh, label: 'Haha' },
  { emoji: 'angry', icon: Angry, label: 'Angry' },
];

/* ───────────────────────── Main Component ───────────────────────── */

export function BlogPostPage() {
  const { slug } = useParams<{ slug: string }>();
  const { isAuthenticated, user } = useAuth();
  const { tenantPath } = useTenant();
  const toast = useToast();
  const [post, setPost] = useState<BlogPostDetail | null>(null);
  usePageTitle(post?.title || 'Blog');
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Comments state
  const [comments, setComments] = useState<BlogComment[]>([]);
  const [commentCount, setCommentCount] = useState(0);
  const [isLoadingComments, setIsLoadingComments] = useState(false);
  const [newComment, setNewComment] = useState('');
  const [isSubmittingComment, setIsSubmittingComment] = useState(false);

  const loadPost = useCallback(async () => {
    if (!slug) return;

    try {
      setIsLoading(true);
      setError(null);

      const response = await api.get<BlogPostDetail>(`/v2/blog/${slug}`);

      if (response.success && response.data) {
        setPost(response.data);
      } else {
        setError('Blog post not found.');
      }
    } catch (err) {
      logError('Failed to load blog post', err);
      setError('Failed to load this blog post. Please try again.');
    } finally {
      setIsLoading(false);
    }
  }, [slug]);

  const loadComments = useCallback(async (postId: number) => {
    try {
      setIsLoadingComments(true);
      const response = await api.get<{ comments: BlogComment[] }>(
        `/v2/comments?target_type=blog_post&target_id=${postId}`
      );

      if (response.success && response.data) {
        const loaded = response.data.comments ?? [];
        setComments(loaded);
        // Count all comments including replies
        const countAll = (list: BlogComment[]): number =>
          list.reduce((acc, c) => acc + 1 + (c.replies ? countAll(c.replies) : 0), 0);
        setCommentCount(countAll(loaded));
      }
    } catch (err) {
      logError('Failed to load comments', err);
    } finally {
      setIsLoadingComments(false);
    }
  }, []);

  useEffect(() => {
    loadPost();
  }, [loadPost]);

  useEffect(() => {
    if (post?.id) {
      loadComments(post.id);
    }
  }, [post?.id, loadComments]);

  const handleSubmitComment = async () => {
    if (!newComment.trim() || !post) return;

    try {
      setIsSubmittingComment(true);
      const response = await api.post('/v2/comments', {
        target_type: 'blog_post',
        target_id: post.id,
        content: newComment.trim(),
      });

      if (response.success) {
        // Optimistic add
        const optimisticComment: BlogComment = {
          id: Date.now(),
          content: newComment.trim(),
          created_at: new Date().toISOString(),
          edited: false,
          is_own: true,
          author: {
            id: user?.id ?? 0,
            name: user ? `${user.first_name} ${user.last_name}` : 'You',
            avatar: user?.avatar ?? null,
          },
          reactions: {},
          user_reactions: [],
          replies: [],
        };
        setComments((prev) => [optimisticComment, ...prev]);
        setCommentCount((prev) => prev + 1);
        setNewComment('');
        toast.success('Comment posted!');
        // Reload to get server data
        loadComments(post.id);
      }
    } catch (err) {
      logError('Failed to submit comment', err);
      toast.error('Failed to post comment');
    } finally {
      setIsSubmittingComment(false);
    }
  };

  const handleToggleReaction = async (commentId: number, emoji: string) => {
    if (!isAuthenticated) return;

    // Optimistic update
    setComments((prev) => updateCommentReaction(prev, commentId, emoji));

    try {
      await api.post(`/v2/comments/${commentId}/reactions`, { emoji });
    } catch (err) {
      logError('Failed to toggle reaction', err);
      // Revert on error
      setComments((prev) => updateCommentReaction(prev, commentId, emoji));
    }
  };

  const categoryColorMap: Record<string, string> = {
    blue: 'bg-blue-500/10 text-blue-500',
    gray: 'bg-gray-500/10 text-gray-500',
    fuchsia: 'bg-fuchsia-500/10 text-fuchsia-500',
    purple: 'bg-purple-500/10 text-purple-500',
    green: 'bg-emerald-500/10 text-emerald-500',
    red: 'bg-rose-500/10 text-rose-500',
    yellow: 'bg-amber-500/10 text-amber-500',
  };

  // Loading state
  if (isLoading) {
    return (
      <div className="max-w-3xl mx-auto space-y-6">
        <div className="animate-pulse">
          <div className="h-6 bg-theme-hover rounded w-1/4 mb-4" />
          <div className="h-64 bg-theme-hover rounded-xl mb-6" />
          <div className="h-8 bg-theme-hover rounded w-3/4 mb-3" />
          <div className="h-4 bg-theme-hover rounded w-1/3 mb-8" />
          <div className="space-y-3">
            <div className="h-4 bg-theme-hover rounded w-full" />
            <div className="h-4 bg-theme-hover rounded w-full" />
            <div className="h-4 bg-theme-hover rounded w-5/6" />
            <div className="h-4 bg-theme-hover rounded w-full" />
            <div className="h-4 bg-theme-hover rounded w-3/4" />
          </div>
        </div>
      </div>
    );
  }

  // Error state
  if (error || !post) {
    return (
      <div className="max-w-3xl mx-auto">
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary mb-2">
            {error || 'Post not found'}
          </h2>
          <p className="text-theme-muted mb-4">
            This post may have been removed or the link may be incorrect.
          </p>
          <div className="flex gap-3 justify-center">
            <Button
              as={Link}
              to={tenantPath("/blog")}
              variant="flat"
              className="text-theme-muted"
              startContent={<ArrowLeft className="w-4 h-4" aria-hidden="true" />}
            >
              Back to Blog
            </Button>
            <Button
              className="bg-gradient-to-r from-blue-500 to-indigo-600 text-white"
              startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
              onPress={loadPost}
            >
              Try Again
            </Button>
          </div>
        </GlassCard>
      </div>
    );
  }

  const imageUrl = post.featured_image ? resolveAssetUrl(post.featured_image) : null;

  return (
    <>
      <PageMeta
        title={post.meta_title || post.title}
        description={post.meta_description || post.excerpt}
      />

      <article className="max-w-3xl mx-auto space-y-6">
        {/* Breadcrumbs */}
        <Breadcrumbs items={[
          { label: 'Blog', href: tenantPath('/blog') },
          { label: post.title },
        ]} />

        {/* Featured Image */}
        {imageUrl && (
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            className="rounded-2xl overflow-hidden"
          >
            <img
              src={imageUrl}
              alt={post.title}
              className="w-full max-h-48 sm:max-h-96 object-cover"
            />
          </motion.div>
        )}

        {/* Post Header */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.1 }}
        >
          {post.category && (
            <Chip
              size="sm"
              variant="flat"
              className={`mb-3 ${categoryColorMap[post.category.color] ?? categoryColorMap.blue}`}
            >
              {post.category.name}
            </Chip>
          )}

          <h1 className="text-3xl font-bold text-theme-primary mb-4">
            {post.title}
          </h1>

          {/* Meta */}
          <div className="flex flex-wrap items-center gap-2 sm:gap-4 text-sm text-theme-muted pb-6 border-b border-theme-default">
            <Link
              to={tenantPath(`/profile/${post.author.id}`)}
              className="flex items-center gap-2 hover:text-theme-primary transition-colors"
            >
              <Avatar
                name={post.author.name}
                src={resolveAvatarUrl(post.author.avatar)}
                size="sm"
                className="w-8 h-8"
              />
              <span>{post.author.name}</span>
            </Link>

            <span className="flex items-center gap-1">
              <Calendar className="w-4 h-4" aria-hidden="true" />
              {new Date(post.published_at).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
              })}
            </span>

            <span className="flex items-center gap-1">
              <Clock className="w-4 h-4" aria-hidden="true" />
              {post.reading_time} min read
            </span>

            {post.views > 0 && (
              <span className="flex items-center gap-1">
                <Eye className="w-4 h-4" aria-hidden="true" />
                {post.views} views
              </span>
            )}

            <span className="flex items-center gap-1">
              <MessageCircle className="w-4 h-4" aria-hidden="true" />
              {commentCount} {commentCount === 1 ? 'comment' : 'comments'}
            </span>
          </div>
        </motion.div>

        {/* Post Content */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.2 }}
        >
          <GlassCard className="p-6 sm:p-8">
            <div
              className="prose prose-sm sm:prose dark:prose-invert max-w-none
                text-theme-primary
                [&_h2]:text-theme-primary [&_h2]:font-bold [&_h2]:mt-8 [&_h2]:mb-4
                [&_h3]:text-theme-primary [&_h3]:font-semibold [&_h3]:mt-6 [&_h3]:mb-3
                [&_p]:text-theme-muted [&_p]:leading-relaxed [&_p]:mb-4
                [&_a]:text-blue-500 [&_a]:hover:text-blue-600
                [&_ul]:text-theme-muted [&_ul]:list-disc [&_ul]:pl-6 [&_ul]:mb-4
                [&_ol]:text-theme-muted [&_ol]:list-decimal [&_ol]:pl-6 [&_ol]:mb-4
                [&_li]:mb-1
                [&_blockquote]:border-l-4 [&_blockquote]:border-blue-500 [&_blockquote]:pl-4 [&_blockquote]:italic [&_blockquote]:text-theme-subtle
                [&_img]:rounded-xl [&_img]:my-6
                [&_code]:bg-theme-elevated [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:rounded [&_code]:text-sm
                [&_pre]:bg-theme-elevated [&_pre]:p-4 [&_pre]:rounded-xl [&_pre]:overflow-x-auto
              "
              dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(post.content) }}
            />
          </GlassCard>
        </motion.div>

        {/* ─── Comments Section ─── */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.3 }}
        >
          <GlassCard className="p-6 sm:p-8">
            <h2 className="text-xl font-bold text-theme-primary flex items-center gap-2 mb-6">
              <MessageCircle className="w-5 h-5 text-blue-400" aria-hidden="true" />
              Comments ({commentCount})
            </h2>

            {/* Add Comment Form */}
            {isAuthenticated ? (
              <div className="flex items-start gap-3 mb-6">
                <Avatar
                  name={user ? `${user.first_name} ${user.last_name}` : 'You'}
                  src={resolveAvatarUrl(user?.avatar)}
                  size="sm"
                  className="mt-1 flex-shrink-0"
                />
                <div className="flex-1">
                  <Textarea
                    placeholder="Share your thoughts..."
                    value={newComment}
                    onChange={(e) => setNewComment(e.target.value)}
                    minRows={2}
                    maxRows={6}
                    classNames={{
                      input: 'bg-transparent text-theme-primary text-sm',
                      inputWrapper: 'bg-theme-elevated border-theme-default',
                    }}
                  />
                  <div className="flex justify-end mt-2">
                    <Button
                      className="bg-gradient-to-r from-blue-500 to-indigo-600 text-white"
                      size="sm"
                      onPress={handleSubmitComment}
                      isLoading={isSubmittingComment}
                      isDisabled={!newComment.trim()}
                      startContent={<Send className="w-4 h-4" aria-hidden="true" />}
                    >
                      Post Comment
                    </Button>
                  </div>
                </div>
              </div>
            ) : (
              <div className="text-center py-4 mb-6 bg-theme-elevated rounded-xl">
                <p className="text-sm text-theme-muted mb-2">Sign in to join the conversation</p>
                <Button
                  as={Link}
                  to={tenantPath("/login")}
                  size="sm"
                  variant="flat"
                  className="text-indigo-500"
                >
                  Sign In
                </Button>
              </div>
            )}

            {/* Comments List */}
            {isLoadingComments ? (
              <div className="space-y-4">
                {[1, 2, 3].map((i) => (
                  <div key={i} className="flex items-start gap-3 animate-pulse">
                    <div className="w-8 h-8 rounded-full bg-theme-hover flex-shrink-0" />
                    <div className="flex-1">
                      <div className="h-3 bg-theme-hover rounded w-1/4 mb-2" />
                      <div className="h-4 bg-theme-hover rounded w-3/4 mb-1" />
                      <div className="h-4 bg-theme-hover rounded w-1/2" />
                    </div>
                  </div>
                ))}
              </div>
            ) : comments.length === 0 ? (
              <div className="text-center py-8">
                <MessageCircle className="w-10 h-10 text-theme-subtle mx-auto mb-3 opacity-50" aria-hidden="true" />
                <p className="text-sm text-theme-subtle">No comments yet. Be the first to share your thoughts!</p>
              </div>
            ) : (
              <div className="space-y-4">
                {comments.map((comment) => (
                  <CommentItem
                    key={comment.id}
                    comment={comment}
                    postId={post.id}
                    isAuthenticated={isAuthenticated}
                    currentUser={user}
                    onReaction={handleToggleReaction}
                    onReplySubmitted={() => loadComments(post.id)}
                  />
                ))}
              </div>
            )}
          </GlassCard>
        </motion.div>

        {/* Footer */}
        <div className="pt-4 text-center">
          <Button
            as={Link}
            to={tenantPath("/blog")}
            variant="flat"
            className="bg-theme-elevated text-theme-muted"
            startContent={<ArrowLeft className="w-4 h-4" aria-hidden="true" />}
          >
            Back to Blog
          </Button>
        </div>
      </article>
    </>
  );
}

/* ───────────────────────── Comment Item ───────────────────────── */

interface CommentItemProps {
  comment: BlogComment;
  postId: number;
  isAuthenticated: boolean;
  currentUser: { id: number; first_name?: string; last_name?: string; avatar?: string | null } | null;
  onReaction: (commentId: number, emoji: string) => void;
  onReplySubmitted: () => void;
  depth?: number;
}

function CommentItem({
  comment,
  postId,
  isAuthenticated,
  currentUser,
  onReaction,
  onReplySubmitted,
  depth = 0,
}: CommentItemProps) {
  const toast = useToast();
  const { tenantPath } = useTenant();
  const [showReplies, setShowReplies] = useState(depth === 0);
  const [showReplyInput, setShowReplyInput] = useState(false);
  const [replyContent, setReplyContent] = useState('');
  const [isSubmittingReply, setIsSubmittingReply] = useState(false);
  const [showEmojiPicker, setShowEmojiPicker] = useState(false);

  const hasReplies = comment.replies && comment.replies.length > 0;
  const hasReactions = Object.keys(comment.reactions).length > 0;

  const handleSubmitReply = async () => {
    if (!replyContent.trim()) return;

    try {
      setIsSubmittingReply(true);
      const response = await api.post('/v2/comments', {
        target_type: 'blog_post',
        target_id: postId,
        parent_id: comment.id,
        content: replyContent.trim(),
      });

      if (response.success) {
        setReplyContent('');
        setShowReplyInput(false);
        toast.success('Reply posted!');
        onReplySubmitted();
      }
    } catch (err) {
      logError('Failed to submit reply', err);
      toast.error('Failed to post reply');
    } finally {
      setIsSubmittingReply(false);
    }
  };

  const reactionIconMap: Record<string, typeof Heart> = {
    heart: Heart,
    thumbs_up: ThumbsUp,
    thumbs_down: ThumbsDown,
    laugh: Laugh,
    angry: Angry,
  };

  return (
    <div className={`${depth > 0 ? 'ml-3 sm:ml-6 md:ml-10' : ''}`}>
      <div className="flex items-start gap-2.5">
        <Link to={tenantPath(`/profile/${comment.author.id}`)} className="flex-shrink-0">
          <Avatar
            name={comment.author.name}
            src={resolveAvatarUrl(comment.author.avatar)}
            size="sm"
            className={depth > 0 ? 'w-7 h-7' : 'w-8 h-8'}
          />
        </Link>
        <div className="flex-1 min-w-0">
          {/* Comment Bubble */}
          <div className="bg-theme-elevated rounded-2xl px-4 py-2.5">
            <div className="flex items-center gap-2 mb-0.5">
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
            <p className="text-sm text-theme-muted whitespace-pre-wrap">{comment.content}</p>
          </div>

          {/* Reactions Display */}
          {hasReactions && (
            <div className="flex items-center gap-1.5 mt-1.5 flex-wrap">
              {Object.entries(comment.reactions).map(([emoji, count]) => {
                const IconComp = reactionIconMap[emoji] || Smile;
                const isActive = comment.user_reactions.includes(emoji);
                return (
                  <Button
                    key={emoji}
                    size="sm"
                    variant="flat"
                    className={`min-w-0 h-6 px-2 text-xs gap-1 ${
                      isActive
                        ? 'bg-indigo-500/15 text-indigo-500'
                        : 'bg-theme-hover text-theme-subtle'
                    }`}
                    onPress={() => onReaction(comment.id, emoji)}
                    isDisabled={!isAuthenticated}
                    aria-label={`${emoji} reaction (${count})`}
                  >
                    <IconComp className="w-3 h-3" aria-hidden="true" />
                    {count}
                  </Button>
                );
              })}
            </div>
          )}

          {/* Action Row */}
          <div className="flex items-center gap-3 mt-1 px-1">
            <span className="text-xs text-theme-subtle">{formatRelativeTime(comment.created_at)}</span>

            {/* React Button */}
            {isAuthenticated && (
              <div className="relative">
                <Button
                  variant="light"
                  size="sm"
                  className="text-xs text-theme-subtle p-0 min-w-0 h-auto hover:text-indigo-400"
                  onPress={() => setShowEmojiPicker(!showEmojiPicker)}
                  aria-label="Add reaction"
                >
                  <Smile className="w-3.5 h-3.5" aria-hidden="true" />
                </Button>
                <AnimatePresence>
                  {showEmojiPicker && (
                    <motion.div
                      initial={{ opacity: 0, scale: 0.9, y: -4 }}
                      animate={{ opacity: 1, scale: 1, y: 0 }}
                      exit={{ opacity: 0, scale: 0.9, y: -4 }}
                      className="absolute bottom-full left-0 mb-1 flex gap-1 bg-content1 border border-theme-default rounded-xl px-2 py-1.5 shadow-lg z-20"
                    >
                      {REACTION_EMOJIS.map((r) => {
                        const IconComp = r.icon;
                        return (
                          <Button
                            key={r.emoji}
                            isIconOnly
                            size="sm"
                            variant="light"
                            className="min-w-0 w-7 h-7 hover:bg-theme-hover"
                            onPress={() => {
                              onReaction(comment.id, r.emoji);
                              setShowEmojiPicker(false);
                            }}
                            aria-label={r.label}
                          >
                            <IconComp className="w-4 h-4" />
                          </Button>
                        );
                      })}
                    </motion.div>
                  )}
                </AnimatePresence>
              </div>
            )}

            {/* Reply Button */}
            {isAuthenticated && depth < 2 && (
              <Button
                variant="light"
                size="sm"
                className="text-xs text-theme-subtle p-0 min-w-0 h-auto hover:text-indigo-400"
                onPress={() => setShowReplyInput(!showReplyInput)}
                startContent={<Reply className="w-3.5 h-3.5" aria-hidden="true" />}
              >
                Reply
              </Button>
            )}

            {/* Toggle Replies */}
            {hasReplies && (
              <Button
                variant="light"
                size="sm"
                className="text-xs text-indigo-500 p-0 min-w-0 h-auto"
                onPress={() => setShowReplies(!showReplies)}
                startContent={
                  showReplies
                    ? <ChevronUp className="w-3.5 h-3.5" aria-hidden="true" />
                    : <ChevronDown className="w-3.5 h-3.5" aria-hidden="true" />
                }
              >
                {showReplies ? 'Hide' : `${comment.replies.length}`} {comment.replies.length === 1 ? 'reply' : 'replies'}
              </Button>
            )}
          </div>

          {/* Reply Input */}
          <AnimatePresence>
            {showReplyInput && (
              <motion.div
                initial={{ opacity: 0, height: 0 }}
                animate={{ opacity: 1, height: 'auto' }}
                exit={{ opacity: 0, height: 0 }}
                className="mt-2 overflow-hidden"
              >
                <div className="flex items-start gap-2">
                  <Avatar
                    name={currentUser ? `${currentUser.first_name ?? ''} ${currentUser.last_name ?? ''}`.trim() || 'You' : 'You'}
                    src={resolveAvatarUrl(currentUser?.avatar)}
                    size="sm"
                    className="w-6 h-6 mt-1 flex-shrink-0"
                  />
                  <div className="flex-1">
                    <Input
                      placeholder={`Reply to ${comment.author.name}...`}
                      value={replyContent}
                      onChange={(e) => setReplyContent(e.target.value)}
                      onKeyDown={(e) => e.key === 'Enter' && !e.shiftKey && handleSubmitReply()}
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
                          onPress={handleSubmitReply}
                          isDisabled={!replyContent.trim() || isSubmittingReply}
                          isLoading={isSubmittingReply}
                          aria-label="Send reply"
                        >
                          <Send className="w-4 h-4" />
                        </Button>
                      }
                    />
                  </div>
                </div>
              </motion.div>
            )}
          </AnimatePresence>

          {/* Nested Replies */}
          <AnimatePresence>
            {showReplies && hasReplies && (
              <motion.div
                initial={{ opacity: 0, height: 0 }}
                animate={{ opacity: 1, height: 'auto' }}
                exit={{ opacity: 0, height: 0 }}
                className="mt-3 space-y-3 border-l-2 border-theme-default pl-0 overflow-hidden"
              >
                {comment.replies.map((reply) => (
                  <CommentItem
                    key={reply.id}
                    comment={reply}
                    postId={postId}
                    isAuthenticated={isAuthenticated}
                    currentUser={currentUser}
                    onReaction={onReaction}
                    onReplySubmitted={onReplySubmitted}
                    depth={depth + 1}
                  />
                ))}
              </motion.div>
            )}
          </AnimatePresence>
        </div>
      </div>
    </div>
  );
}

/* ───────────────────────── Helpers ───────────────────────── */

/**
 * Recursively toggle a reaction on a comment in the tree.
 * Used for optimistic updates.
 */
function updateCommentReaction(
  comments: BlogComment[],
  commentId: number,
  emoji: string
): BlogComment[] {
  return comments.map((c) => {
    if (c.id === commentId) {
      const isActive = c.user_reactions.includes(emoji);
      const newUserReactions = isActive
        ? c.user_reactions.filter((e) => e !== emoji)
        : [...c.user_reactions, emoji];
      const newReactions = { ...c.reactions };
      if (isActive) {
        newReactions[emoji] = Math.max(0, (newReactions[emoji] || 1) - 1);
        if (newReactions[emoji] === 0) delete newReactions[emoji];
      } else {
        newReactions[emoji] = (newReactions[emoji] || 0) + 1;
      }
      return {
        ...c,
        user_reactions: newUserReactions,
        reactions: newReactions,
      };
    }
    if (c.replies && c.replies.length > 0) {
      return { ...c, replies: updateCommentReaction(c.replies, commentId, emoji) };
    }
    return c;
  });
}

export default BlogPostPage;
