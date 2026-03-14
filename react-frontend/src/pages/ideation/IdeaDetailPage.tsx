// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Idea Detail Page - View full idea with comments and voting
 *
 * Features:
 * - Full idea content with creator info
 * - Vote toggle button with count
 * - Status chip (shortlisted/winner)
 * - Admin controls: set shortlisted / set winner
 * - Comments section with add comment form
 * - Cursor-based pagination for comments
 */

import { useState, useEffect, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
  Button,
  Chip,
  Spinner,
  Input,
  Textarea,
  Avatar,
  Dropdown,
  DropdownTrigger,
  DropdownMenu,
  DropdownItem,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Select,
  SelectItem,
  useDisclosure,
} from '@heroui/react';
import {
  ArrowLeft,
  ArrowBigUp,
  AlertTriangle,
  RefreshCw,
  MessageCircle,
  MoreVertical,
  Award,
  Star,
  Trash2,
  Send,
  Users,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useAuth, useToast, useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl, formatRelativeTime } from '@/lib/helpers';

/* ───────────────────────── Types ───────────────────────── */

interface Idea {
  id: number;
  challenge_id: number;
  user_id: number;
  title: string;
  description: string;
  votes_count: number;
  comments_count: number;
  status: 'submitted' | 'shortlisted' | 'winner' | 'withdrawn';
  has_voted: boolean;
  created_at: string;
  creator: {
    id: number;
    name: string;
    avatar_url: string | null;
  };
}

interface Comment {
  id: number;
  idea_id: number;
  user_id: number;
  body: string;
  created_at: string;
  author: {
    id: number;
    name: string;
    avatar_url: string | null;
  };
}

interface VoteResult {
  voted: boolean;
  votes_count: number;
}

const IDEA_STATUS_COLOR_MAP: Record<string, 'default' | 'success' | 'warning' | 'secondary'> = {
  submitted: 'default',
  shortlisted: 'warning',
  winner: 'success',
  withdrawn: 'secondary',
};

/* ───────────────────────── Main Component ───────────────────────── */

export function IdeaDetailPage() {
  const { t } = useTranslation('ideation');
  const { challengeId, id } = useParams<{ challengeId: string; id: string }>();
  const navigate = useNavigate();
  const { isAuthenticated, user } = useAuth();
  const { tenantPath } = useTenant();
  const toast = useToast();

  const [idea, setIdea] = useState<Idea | null>(null);
  const [comments, setComments] = useState<Comment[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingComments, setIsLoadingComments] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [hasMoreComments, setHasMoreComments] = useState(false);
  const [commentsCursor, setCommentsCursor] = useState<string | undefined>();
  const [isLoadingMoreComments, setIsLoadingMoreComments] = useState(false);
  const [isVoting, setIsVoting] = useState(false);

  // Comment form
  const [newComment, setNewComment] = useState('');
  const [isPostingComment, setIsPostingComment] = useState(false);

  // Delete idea modal
  const { isOpen: isDeleteOpen, onOpen: onDeleteOpen, onClose: onDeleteClose } = useDisclosure();
  const [isDeletingIdea, setIsDeletingIdea] = useState(false);

  // Delete comment state
  const [deletingCommentId, setDeletingCommentId] = useState<number | null>(null);

  // Convert to group modal
  const [isConvertOpen, setIsConvertOpen] = useState(false);
  const [isConverting, setIsConverting] = useState(false);
  const [groupForm, setGroupForm] = useState({ name: '', description: '', visibility: 'public' });

  const isAdmin = user?.role && ['admin', 'tenant_admin', 'tenant_super_admin', 'super_admin'].includes(user.role);
  const isOwner = user?.id === idea?.user_id;

  usePageTitle(idea?.title ?? t('idea_detail.page_title'));

  /* ───── Fetch idea ───── */
  const fetchIdea = useCallback(async () => {
    try {
      setIsLoading(true);
      const response = await api.get<Idea>(`/v2/ideation-ideas/${id}`);
      if (response.success && response.data) {
        setIdea(response.data);
      }
    } catch (err) {
      logError('Failed to fetch idea', err);
      setError(t('ideas.load_error'));
    } finally {
      setIsLoading(false);
    }
  }, [id, t]);

  /* ───── Fetch comments ───── */
  const fetchComments = useCallback(async (loadMore = false) => {
    try {
      if (loadMore) {
        setIsLoadingMoreComments(true);
      } else {
        setIsLoadingComments(true);
      }

      const params = new URLSearchParams();
      params.set('per_page', '20');
      if (loadMore && commentsCursor) {
        params.set('cursor', commentsCursor);
      }

      const response = await api.get<Comment[]>(`/v2/ideation-ideas/${id}/comments?${params}`);

      if (response.success && response.data) {
        const items = Array.isArray(response.data) ? response.data : [];
        if (loadMore) {
          setComments(prev => [...prev, ...items]);
        } else {
          setComments(items);
        }
        setCommentsCursor(response.meta?.cursor ?? undefined);
        setHasMoreComments(response.meta?.has_more ?? false);
      }
    } catch (err) {
      logError('Failed to fetch comments', err);
    } finally {
      setIsLoadingComments(false);
      setIsLoadingMoreComments(false);
    }
  }, [id, commentsCursor]);

  useEffect(() => {
    fetchIdea();
    fetchComments();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);

  // Pre-populate group form when idea loads
  useEffect(() => {
    if (idea) {
      setGroupForm(prev => ({
        ...prev,
        name: idea.title,
        description: idea.description,
      }));
    }
  }, [idea?.id]); // eslint-disable-line react-hooks/exhaustive-deps

  /* ───── Actions ───── */

  const handleVote = async () => {
    if (!isAuthenticated || !idea || isVoting) return;

    setIsVoting(true);
    try {
      const response = await api.post<VoteResult>(`/v2/ideation-ideas/${idea.id}/vote`);
      const result = response.data;

      if (result) {
        setIdea(prev => prev ? {
          ...prev,
          has_voted: result.voted,
          votes_count: result.votes_count,
        } : prev);

        toast.success(result.voted ? t('toast.vote_added') : t('toast.vote_removed'));
      }
    } catch (err) {
      logError('Failed to vote', err);
      toast.error(t('toast.error_generic'));
    } finally {
      setIsVoting(false);
    }
  };

  const handlePostComment = async () => {
    if (!newComment.trim()) return;

    setIsPostingComment(true);
    try {
      await api.post(`/v2/ideation-ideas/${id}/comments`, {
        body: newComment.trim(),
      });

      toast.success(t('toast.comment_added'));
      setNewComment('');

      // Refresh comments
      setCommentsCursor(undefined);
      fetchComments();
      fetchIdea();
    } catch (err) {
      logError('Failed to post comment', err);
      toast.error(t('toast.error_generic'));
    } finally {
      setIsPostingComment(false);
    }
  };

  const handleDeleteComment = async (commentId: number) => {
    setDeletingCommentId(commentId);
    try {
      await api.delete(`/v2/ideation-comments/${commentId}`);
      toast.success(t('toast.comment_deleted'));

      setComments(prev => prev.filter(c => c.id !== commentId));
      // Update count on idea
      setIdea(prev => prev ? { ...prev, comments_count: Math.max(0, prev.comments_count - 1) } : prev);
    } catch (err) {
      logError('Failed to delete comment', err);
      toast.error(t('toast.error_generic'));
    } finally {
      setDeletingCommentId(null);
    }
  };

  const handleIdeaStatusChange = async (newStatus: string) => {
    try {
      await api.put(`/v2/ideation-ideas/${id}/status`, { status: newStatus });
      toast.success(t('admin.idea_status_updated'));
      fetchIdea();
    } catch (err) {
      logError('Failed to update idea status', err);
      toast.error(t('toast.error_generic'));
    }
  };

  const handleDeleteIdea = async () => {
    setIsDeletingIdea(true);
    try {
      await api.delete(`/v2/ideation-ideas/${id}`);
      toast.success(t('toast.idea_deleted'));
      navigate(tenantPath(`/ideation/${challengeId ?? idea?.challenge_id}`));
    } catch (err) {
      logError('Failed to delete idea', err);
      toast.error(t('toast.error_generic'));
    } finally {
      setIsDeletingIdea(false);
      onDeleteClose();
    }
  };

  const handleConvertToGroup = async () => {
    setIsConverting(true);
    try {
      const response = await api.post<{ id: number }>(`/v2/ideation-ideas/${id}/convert-to-group`, {
        name: groupForm.name,
        description: groupForm.description,
        visibility: groupForm.visibility,
      });

      if (response.data) {
        toast.success(t('convert_to_group.success'));
        setIsConvertOpen(false);
        navigate(tenantPath(`/groups/${response.data.id}`));
      }
    } catch (err) {
      logError('Failed to convert idea to group', err);
      toast.error(t('convert_to_group.error'));
    } finally {
      setIsConverting(false);
    }
  };

  const canConvertToGroup = isAuthenticated && idea &&
    (idea.status === 'shortlisted' || idea.status === 'winner') &&
    (isAdmin || isOwner);

  /* ───── Render ───── */

  if (isLoading) {
    return (
      <div className="flex justify-center py-12">
        <Spinner size="lg" />
      </div>
    );
  }

  if (error || !idea) {
    return (
      <div className="max-w-4xl mx-auto px-4 py-6">
        <EmptyState
          icon={<AlertTriangle className="w-10 h-10 text-theme-subtle" />}
          title={t('ideas.load_error')}
          action={
            <Button
              color="primary"
              variant="flat"
              startContent={<RefreshCw className="w-4 h-4" />}
              onPress={() => fetchIdea()}
            >
              {t('actions.retry', { defaultValue: 'Retry' })}
            </Button>
          }
        />
      </div>
    );
  }

  // Build dropdown menu items
  const dropdownItems = [
    ...(isAdmin ? [
      {
        key: 'shortlist',
        label: idea.status === 'shortlisted' ? t('admin.remove_status') : t('admin.set_shortlisted'),
        startContent: <Star className="w-4 h-4" />,
        onPress: () => handleIdeaStatusChange(
          idea.status === 'shortlisted' ? 'submitted' : 'shortlisted'
        ),
      },
      {
        key: 'winner',
        label: idea.status === 'winner' ? t('admin.remove_status') : t('admin.set_winner'),
        startContent: <Award className="w-4 h-4" />,
        onPress: () => handleIdeaStatusChange(
          idea.status === 'winner' ? 'submitted' : 'winner'
        ),
      },
    ] : []),
    {
      key: 'delete',
      label: t('ideas.delete'),
      className: 'text-danger',
      color: 'danger' as const,
      startContent: <Trash2 className="w-4 h-4" />,
      onPress: onDeleteOpen,
    },
  ];

  return (
    <div className="max-w-4xl mx-auto px-4 py-6">
      {/* Back link */}
      <Button
        variant="light"
        startContent={<ArrowLeft className="w-4 h-4" />}
        className="mb-4 -ml-2"
        onPress={() => navigate(tenantPath(`/ideation/${challengeId ?? idea?.challenge_id}`))}
      >
        {t('idea_detail.back_to_challenge')}
      </Button>

      {/* Idea Header */}
      <GlassCard className="p-6 mb-6">
        <div className="flex items-start justify-between gap-4">
          <div className="flex-1">
            <div className="flex items-center gap-3 mb-2">
              <h1 className="text-2xl font-bold text-[var(--color-text)]">
                {idea.title}
              </h1>
              {idea.status !== 'submitted' && (
                <Chip
                  size="sm"
                  color={IDEA_STATUS_COLOR_MAP[idea.status] ?? 'default'}
                  variant="flat"
                  startContent={idea.status === 'winner' ? <Award className="w-3 h-3" /> : undefined}
                >
                  {t(`idea_status.${idea.status}`)}
                </Chip>
              )}
            </div>

            {/* Creator and date */}
            <div className="flex items-center gap-3 text-sm text-[var(--color-text-tertiary)] mb-4">
              <Avatar
                src={resolveAvatarUrl(idea.creator.avatar_url)}
                size="sm"
                className="w-6 h-6"
                name={idea.creator.name}
              />
              <span>{t('idea_detail.submitted_by', { name: idea.creator.name })}</span>
              <span>{formatRelativeTime(idea.created_at)}</span>
            </div>

            {/* Description */}
            <p className="text-[var(--color-text-secondary)] whitespace-pre-wrap">
              {idea.description}
            </p>

            {/* Convert to Group Button */}
            {canConvertToGroup && (
              <Button
                color="primary"
                variant="flat"
                size="sm"
                className="mt-4"
                startContent={<Users className="w-4 h-4" />}
                onPress={() => setIsConvertOpen(true)}
              >
                {t('convert_to_group.button')}
              </Button>
            )}
          </div>

          {/* Vote + Admin */}
          <div className="flex flex-col items-center gap-2">
            {/* Vote Button */}
            <Button
              variant="flat"
              onPress={handleVote}
              isDisabled={!isAuthenticated || isVoting}
              className={`flex flex-col items-center gap-0.5 p-3 rounded-xl transition-colors h-auto min-w-0 ${
                idea.has_voted
                  ? 'bg-primary/10 text-primary'
                  : 'hover:bg-[var(--color-surface-hover)] text-[var(--color-text-tertiary)]'
              }`}
              aria-label={idea.has_voted ? t('ideas.unvote') : t('ideas.vote')}
            >
              <ArrowBigUp
                className={`w-8 h-8 ${idea.has_voted ? 'fill-current' : ''}`}
              />
              <span className="text-lg font-bold">{idea.votes_count}</span>
            </Button>

            {/* Admin / Owner Controls */}
            {(isAdmin || isOwner) && (
              <Dropdown>
                <DropdownTrigger>
                  <Button isIconOnly variant="flat" size="sm" aria-label="Idea actions">
                    <MoreVertical className="w-4 h-4" />
                  </Button>
                </DropdownTrigger>
                <DropdownMenu aria-label="Idea actions" items={dropdownItems}>
                  {(item) => (
                    <DropdownItem
                      key={item.key}
                      className={item.className}
                      color={item.color}
                      startContent={item.startContent}
                      onPress={item.onPress}
                    >
                      {item.label}
                    </DropdownItem>
                  )}
                </DropdownMenu>
              </Dropdown>
            )}
          </div>
        </div>
      </GlassCard>

      {/* Comments Section */}
      <div className="mb-4">
        <h2 className="text-xl font-semibold text-[var(--color-text)] flex items-center gap-2">
          <MessageCircle className="w-5 h-5" />
          {t('comments.title')} ({idea.comments_count})
        </h2>
      </div>

      {/* Add Comment Form */}
      {isAuthenticated && (
        <GlassCard className="p-4 mb-4">
          <div className="flex gap-3">
            <Avatar
              src={resolveAvatarUrl(user?.avatar_url)}
              size="sm"
              className="w-8 h-8 flex-shrink-0 mt-1"
              name={user?.first_name ?? ''}
            />
            <div className="flex-1">
              <Textarea
                placeholder={t('comments.add_placeholder')}
                value={newComment}
                onValueChange={setNewComment}
                variant="bordered"
                minRows={2}
                maxRows={6}
              />
              <div className="flex justify-end mt-2">
                <Button
                  color="primary"
                  size="sm"
                  isLoading={isPostingComment}
                  isDisabled={!newComment.trim()}
                  startContent={<Send className="w-4 h-4" />}
                  onPress={handlePostComment}
                >
                  {t('comments.add_button')}
                </Button>
              </div>
            </div>
          </div>
        </GlassCard>
      )}

      {/* Comments Loading */}
      {isLoadingComments && (
        <div className="flex justify-center py-8">
          <Spinner size="md" />
        </div>
      )}

      {/* Comments Empty */}
      {!isLoadingComments && comments.length === 0 && (
        <EmptyState
          icon={<MessageCircle className="w-10 h-10 text-theme-subtle" />}
          title={t('comments.empty_title')}
          description={t('comments.empty_description')}
        />
      )}

      {/* Comments List */}
      {!isLoadingComments && comments.length > 0 && (
        <div className="space-y-3">
          {comments.map((comment) => (
            <GlassCard key={comment.id} className="p-4">
              <div className="flex items-start gap-3">
                <Avatar
                  src={resolveAvatarUrl(comment.author.avatar_url)}
                  size="sm"
                  className="w-8 h-8 flex-shrink-0"
                  name={comment.author.name}
                />
                <div className="flex-1 min-w-0">
                  <div className="flex items-center justify-between gap-2 mb-1">
                    <div className="flex items-center gap-2">
                      <span className="text-sm font-semibold text-[var(--color-text)]">
                        {comment.author.name}
                      </span>
                      <span className="text-xs text-[var(--color-text-tertiary)]">
                        {formatRelativeTime(comment.created_at)}
                      </span>
                    </div>

                    {/* Delete comment (owner or admin) */}
                    {(user?.id === comment.user_id || isAdmin) && (
                      <Button
                        isIconOnly
                        variant="light"
                        size="sm"
                        isLoading={deletingCommentId === comment.id}
                        onPress={() => handleDeleteComment(comment.id)}
                        aria-label={t('comments.delete')}
                      >
                        <Trash2 className="w-3.5 h-3.5 text-[var(--color-text-tertiary)]" />
                      </Button>
                    )}
                  </div>
                  <p className="text-sm text-[var(--color-text-secondary)] whitespace-pre-wrap">
                    {comment.body}
                  </p>
                </div>
              </div>
            </GlassCard>
          ))}

          {/* Load More Comments */}
          {hasMoreComments && (
            <div className="flex justify-center mt-4">
              <Button
                variant="flat"
                isLoading={isLoadingMoreComments}
                onPress={() => fetchComments(true)}
              >
                {t('comments.load_more')}
              </Button>
            </div>
          )}
        </div>
      )}

      {/* Delete Idea Confirmation Modal */}
      <Modal isOpen={isDeleteOpen} onClose={onDeleteClose}>
        <ModalContent>
          <ModalHeader>{t('ideas.delete')}</ModalHeader>
          <ModalBody>
            <p className="text-[var(--color-text-secondary)]">
              {t('ideas.delete_confirm')}
            </p>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onDeleteClose}>
              {t('form.cancel')}
            </Button>
            <Button
              color="danger"
              isLoading={isDeletingIdea}
              onPress={handleDeleteIdea}
            >
              {t('ideas.delete')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Convert to Group Modal */}
      <Modal isOpen={isConvertOpen} onClose={() => setIsConvertOpen(false)} size="lg">
        <ModalContent>
          <ModalHeader className="flex flex-col gap-1">
            <span>{t('convert_to_group.title')}</span>
            <p className="text-sm font-normal text-[var(--color-text-tertiary)]">
              {t('convert_to_group.description')}
            </p>
          </ModalHeader>
          <ModalBody>
            <Input
              label={t('convert_to_group.name_label')}
              value={groupForm.name}
              onValueChange={(val) => setGroupForm(prev => ({ ...prev, name: val }))}
              variant="bordered"
              isRequired
            />
            <Textarea
              label={t('convert_to_group.description_label')}
              value={groupForm.description}
              onValueChange={(val) => setGroupForm(prev => ({ ...prev, description: val }))}
              variant="bordered"
              minRows={3}
              maxRows={8}
            />
            <Select
              label={t('convert_to_group.visibility_label')}
              selectedKeys={[groupForm.visibility]}
              onSelectionChange={(keys) => {
                const selected = Array.from(keys)[0] as string;
                if (selected) setGroupForm(prev => ({ ...prev, visibility: selected }));
              }}
              variant="bordered"
            >
              <SelectItem key="public">{t('convert_to_group.visibility_public')}</SelectItem>
              <SelectItem key="private">{t('convert_to_group.visibility_private')}</SelectItem>
            </Select>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={() => setIsConvertOpen(false)}>
              {t('form.cancel')}
            </Button>
            <Button
              color="primary"
              isLoading={isConverting}
              isDisabled={!groupForm.name.trim()}
              startContent={<Users className="w-4 h-4" />}
              onPress={handleConvertToGroup}
            >
              {isConverting ? t('convert_to_group.creating') : t('convert_to_group.confirm')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default IdeaDetailPage;
