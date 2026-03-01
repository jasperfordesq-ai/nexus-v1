// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Challenge Detail Page - View challenge details and browse/submit ideas
 *
 * Features:
 * - Challenge header with status, description, deadlines, prize
 * - Admin controls: status transitions, edit, delete
 * - Ideas list with sort toggle (Top Voted / Newest)
 * - Vote toggle on each idea
 * - Submit Idea modal (when challenge is open)
 * - Cursor-based pagination for ideas
 */

import { useState, useEffect, useCallback } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import {
  Button,
  Chip,
  Spinner,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Input,
  Textarea,
  Dropdown,
  DropdownTrigger,
  DropdownMenu,
  DropdownItem,
  Avatar,
  Tooltip,
  useDisclosure,
} from '@heroui/react';
import {
  ArrowLeft,
  ArrowBigUp,
  Lightbulb,
  Plus,
  RefreshCw,
  AlertTriangle,
  Calendar,
  MessageCircle,
  Trophy,
  MoreVertical,
  Edit3,
  Trash2,
  Heart,
  Eye,
  Star,
  Copy,
  Users,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useAuth, useToast, useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl, resolveAssetUrl, formatRelativeTime } from '@/lib/helpers';

/* ───────────────────────── Types ───────────────────────── */

interface Challenge {
  id: number;
  tenant_id: number;
  user_id: number;
  title: string;
  description: string;
  category: string | null;
  status: 'draft' | 'open' | 'voting' | 'closed';
  ideas_count: number;
  submission_deadline: string | null;
  voting_deadline: string | null;
  prize_description: string | null;
  max_ideas_per_user: number | null;
  created_at: string;
  user_idea_count?: number;
  tags: string[];
  cover_image: string | null;
  is_favorited: boolean;
  favorites_count: number;
  views_count: number;
  is_featured: boolean;
  creator: {
    id: number;
    name: string;
    avatar_url: string | null;
  };
}

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
  image_url: string | null;
  creator: {
    id: number;
    name: string;
    avatar_url: string | null;
  };
}

interface VoteResult {
  voted: boolean;
  votes_count: number;
}

type SortMode = 'votes' | 'newest';

const STATUS_COLOR_MAP: Record<string, 'default' | 'success' | 'warning' | 'danger'> = {
  draft: 'default',
  open: 'success',
  voting: 'warning',
  closed: 'danger',
};

const IDEA_STATUS_COLOR_MAP: Record<string, 'default' | 'success' | 'warning' | 'secondary'> = {
  submitted: 'default',
  shortlisted: 'warning',
  winner: 'success',
  withdrawn: 'secondary',
};

/* ───────────────────────── Main Component ───────────────────────── */

export function ChallengeDetailPage() {
  const { t } = useTranslation('ideation');
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { isAuthenticated, user } = useAuth();
  const { tenantPath } = useTenant();
  const toast = useToast();

  const [challenge, setChallenge] = useState<Challenge | null>(null);
  const [ideas, setIdeas] = useState<Idea[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingIdeas, setIsLoadingIdeas] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [sortMode, setSortMode] = useState<SortMode>('votes');
  const [hasMore, setHasMore] = useState(false);
  const [cursor, setCursor] = useState<string | undefined>();
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [votingIds, setVotingIds] = useState<Set<number>>(new Set());

  // Submit idea modal
  const { isOpen: isSubmitOpen, onOpen: onSubmitOpen, onClose: onSubmitClose } = useDisclosure();
  const [newIdea, setNewIdea] = useState({ title: '', description: '' });
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Delete challenge modal
  const { isOpen: isDeleteOpen, onOpen: onDeleteOpen, onClose: onDeleteClose } = useDisclosure();
  const [isDeleting, setIsDeleting] = useState(false);

  // Favorite toggle
  const [isFavoriting, setIsFavoriting] = useState(false);

  // Duplicate
  const [isDuplicating, setIsDuplicating] = useState(false);

  const isAdmin = user?.role && ['admin', 'tenant_admin', 'tenant_super_admin', 'super_admin'].includes(user.role);

  usePageTitle(challenge?.title ?? t('page_title'));

  /* ───── Fetch challenge ───── */
  const fetchChallenge = useCallback(async () => {
    try {
      setIsLoading(true);
      const response = await api.get<Challenge>(`/v2/ideation-challenges/${id}`);
      if (response.success && response.data) {
        setChallenge(response.data);
      }
    } catch (err) {
      logError('Failed to fetch challenge', err);
      setError(t('challenges.load_error'));
    } finally {
      setIsLoading(false);
    }
  }, [id, t]);

  /* ───── Fetch ideas ───── */
  const fetchIdeas = useCallback(async (sort: SortMode, loadMore = false) => {
    try {
      if (loadMore) {
        setIsLoadingMore(true);
      } else {
        setIsLoadingIdeas(true);
      }

      const params = new URLSearchParams();
      params.set('per_page', '20');
      params.set('sort', sort);
      if (loadMore && cursor) {
        params.set('cursor', cursor);
      }

      const response = await api.get<Idea[]>(`/v2/ideation-challenges/${id}/ideas?${params}`);

      if (response.success && response.data) {
        const items = Array.isArray(response.data) ? response.data : [];
        if (loadMore) {
          setIdeas(prev => [...prev, ...items]);
        } else {
          setIdeas(items);
        }
        setCursor(response.meta?.cursor ?? undefined);
        setHasMore(response.meta?.has_more ?? false);
      }
    } catch (err) {
      logError('Failed to fetch ideas', err);
    } finally {
      setIsLoadingIdeas(false);
      setIsLoadingMore(false);
    }
  }, [id, cursor]);

  useEffect(() => {
    fetchChallenge();
  }, [fetchChallenge]);

  useEffect(() => {
    if (id) {
      setCursor(undefined);
      fetchIdeas(sortMode);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id, sortMode]);

  /* ───── Actions ───── */

  const handleVote = async (ideaId: number) => {
    if (!isAuthenticated) return;
    if (votingIds.has(ideaId)) return;

    setVotingIds(prev => new Set(prev).add(ideaId));

    try {
      const response = await api.post<VoteResult>(`/v2/ideation-ideas/${ideaId}/vote`);
      const result = response.data;

      if (result) {
        setIdeas(prev => prev.map(idea => {
          if (idea.id === ideaId) {
            return {
              ...idea,
              has_voted: result.voted,
              votes_count: result.votes_count,
            };
          }
          return idea;
        }));

        toast.success(result.voted ? t('toast.vote_added') : t('toast.vote_removed'));
      }
    } catch (err) {
      logError('Failed to vote', err);
      toast.error(t('toast.error_generic'));
    } finally {
      setVotingIds(prev => {
        const next = new Set(prev);
        next.delete(ideaId);
        return next;
      });
    }
  };

  const handleSubmitIdea = async () => {
    if (!newIdea.title.trim() || !newIdea.description.trim()) return;

    setIsSubmitting(true);
    try {
      await api.post(`/v2/ideation-challenges/${id}/ideas`, {
        title: newIdea.title.trim(),
        description: newIdea.description.trim(),
      });

      toast.success(t('toast.idea_submitted'));
      setNewIdea({ title: '', description: '' });
      onSubmitClose();

      // Refresh
      setCursor(undefined);
      fetchIdeas(sortMode);
      fetchChallenge();
    } catch (err) {
      logError('Failed to submit idea', err);
      toast.error(t('toast.error_generic'));
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleStatusChange = async (newStatus: string) => {
    try {
      await api.put(`/v2/ideation-challenges/${id}/status`, { status: newStatus });
      toast.success(t('admin.status_updated'));
      fetchChallenge();
    } catch (err) {
      logError('Failed to update status', err);
      toast.error(t('toast.error_generic'));
    }
  };

  const handleDeleteChallenge = async () => {
    setIsDeleting(true);
    try {
      await api.delete(`/v2/ideation-challenges/${id}`);
      toast.success(t('toast.challenge_deleted'));
      navigate(tenantPath('/ideation'));
    } catch (err) {
      logError('Failed to delete challenge', err);
      toast.error(t('toast.error_generic'));
    } finally {
      setIsDeleting(false);
      onDeleteClose();
    }
  };

  const handleToggleFavorite = async () => {
    if (isFavoriting || !challenge) return;

    setIsFavoriting(true);
    try {
      const response = await api.post<{ favorited: boolean; favorites_count: number }>(
        `/v2/ideation-challenges/${id}/favorite`
      );

      if (response.data) {
        setChallenge(prev => prev ? {
          ...prev,
          is_favorited: response.data!.favorited,
          favorites_count: response.data!.favorites_count,
        } : prev);
      }
    } catch (err) {
      logError('Failed to toggle favorite', err);
      toast.error(t('toast.error_generic'));
    } finally {
      setIsFavoriting(false);
    }
  };

  const handleDuplicate = async () => {
    if (isDuplicating) return;

    setIsDuplicating(true);
    try {
      const response = await api.post<{ id: number }>(`/v2/ideation-challenges/${id}/duplicate`);

      if (response.data) {
        toast.success(t('duplicate.success'));
        navigate(tenantPath(`/ideation/${response.data.id}/edit`));
      }
    } catch (err) {
      logError('Failed to duplicate challenge', err);
      toast.error(t('duplicate.error'));
    } finally {
      setIsDuplicating(false);
    }
  };

  const formatDate = (dateStr: string | null) => {
    if (!dateStr) return null;
    try {
      return new Date(dateStr).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
      });
    } catch {
      return dateStr;
    }
  };

  const canSubmitIdea = challenge?.status === 'open' && isAuthenticated &&
    (challenge.max_ideas_per_user === null ||
     (challenge.user_idea_count ?? 0) < challenge.max_ideas_per_user);

  /* ───── Render ───── */

  if (isLoading) {
    return (
      <div className="flex justify-center py-12">
        <Spinner size="lg" />
      </div>
    );
  }

  if (error || !challenge) {
    return (
      <div className="max-w-4xl mx-auto px-4 py-6">
        <EmptyState
          icon={<AlertTriangle className="w-10 h-10 text-theme-subtle" />}
          title={t('challenges.load_error')}
          action={
            <Button
              color="primary"
              variant="flat"
              startContent={<RefreshCw className="w-4 h-4" />}
              onPress={() => fetchChallenge()}
            >
              {t('ideas.load_more')}
            </Button>
          }
        />
      </div>
    );
  }

  // Status transition options for the admin dropdown
  const statusTransitions: Record<string, { key: string; label: string }[]> = {
    draft: [{ key: 'open', label: t('admin.set_open') }],
    open: [
      { key: 'voting', label: t('admin.set_voting') },
      { key: 'closed', label: t('admin.set_closed') },
    ],
    voting: [{ key: 'closed', label: t('admin.set_closed') }],
    closed: [{ key: 'open', label: t('admin.reopen') }],
  };

  // Build admin dropdown items with uniform shape
  interface AdminMenuItem {
    key: string;
    label: string;
    onPress: () => void;
    className?: string;
    color?: 'danger';
    startContent?: React.ReactNode;
  }

  const adminMenuItems: AdminMenuItem[] = [
    ...(statusTransitions[challenge.status] ?? []).map((transition) => ({
      key: transition.key,
      label: transition.label,
      onPress: () => handleStatusChange(transition.key),
    })),
    {
      key: 'duplicate',
      label: t('duplicate.button'),
      startContent: <Copy className="w-4 h-4" />,
      onPress: handleDuplicate,
    },
    {
      key: 'edit',
      label: t('admin.edit_challenge'),
      startContent: <Edit3 className="w-4 h-4" />,
      onPress: () => navigate(tenantPath(`/ideation/${id}/edit`)),
    },
    {
      key: 'delete',
      label: t('admin.delete_challenge'),
      className: 'text-danger',
      color: 'danger',
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
        onPress={() => navigate(tenantPath('/ideation'))}
      >
        {t('title')}
      </Button>

      {/* Cover Image Banner */}
      {challenge.cover_image && (
        <div className="w-full h-48 sm:h-64 rounded-xl overflow-hidden mb-6">
          <img
            src={resolveAssetUrl(challenge.cover_image)}
            alt={challenge.title}
            className="w-full h-full object-cover"
          />
        </div>
      )}

      {/* Challenge Header */}
      <GlassCard className="p-6 mb-6">
        <div className="flex items-start justify-between gap-4 mb-4">
          <div className="flex-1">
            <div className="flex items-center gap-3 mb-2">
              <h1 className="text-2xl font-bold text-[var(--color-text)]">
                {challenge.title}
              </h1>
              <Chip
                size="sm"
                color={STATUS_COLOR_MAP[challenge.status] ?? 'default'}
                variant="flat"
              >
                {t(`status.${challenge.status}`)}
              </Chip>
              {challenge.is_featured && (
                <Chip
                  size="sm"
                  color="warning"
                  variant="flat"
                  startContent={<Star className="w-3 h-3 fill-current" />}
                >
                  {t('featured')}
                </Chip>
              )}
            </div>

            {challenge.category && (
              <Chip size="sm" variant="flat" className="mb-3">
                {challenge.category}
              </Chip>
            )}
          </div>

          <div className="flex items-center gap-2 shrink-0">
            {/* Favorite Button */}
            <Button
              isIconOnly
              variant="flat"
              size="sm"
              onPress={handleToggleFavorite}
              isDisabled={isFavoriting}
              aria-label={challenge.is_favorited ? t('favorites.remove') : t('favorites.add')}
            >
              <Heart
                className={`w-5 h-5 ${
                  challenge.is_favorited
                    ? 'text-red-500 fill-current'
                    : 'text-[var(--color-text-tertiary)]'
                }`}
              />
            </Button>

            {/* Admin Controls */}
            {isAdmin && (
              <Dropdown>
                <DropdownTrigger>
                  <Button isIconOnly variant="flat" size="sm">
                    <MoreVertical className="w-4 h-4" />
                  </Button>
                </DropdownTrigger>
                <DropdownMenu aria-label={t('admin.change_status')} items={adminMenuItems}>
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

        {/* Description */}
        <p className="text-[var(--color-text-secondary)] whitespace-pre-wrap mb-4">
          {challenge.description}
        </p>

        {/* Tags */}
        {challenge.tags && challenge.tags.length > 0 && (
          <div className="flex flex-wrap gap-1.5 mb-4">
            {challenge.tags.map((tag) => (
              <Chip key={tag} size="sm" variant="bordered" className="text-xs">
                {tag}
              </Chip>
            ))}
          </div>
        )}

        {/* Meta info */}
        <div className="flex flex-wrap items-center gap-4 text-sm text-[var(--color-text-tertiary)]">
          {/* Views count */}
          <span className="flex items-center gap-1.5">
            <Eye className="w-4 h-4" />
            {challenge.views_count} {t('views')}
          </span>

          {/* Favorites count */}
          <span className="flex items-center gap-1.5">
            <Heart className="w-4 h-4" />
            {challenge.favorites_count}
          </span>

          {challenge.submission_deadline && (
            <span className="flex items-center gap-1.5">
              <Calendar className="w-4 h-4" />
              {t('challenge.submission_deadline', { date: formatDate(challenge.submission_deadline) })}
            </span>
          )}
          {challenge.voting_deadline && (
            <span className="flex items-center gap-1.5">
              <Calendar className="w-4 h-4" />
              {t('challenge.voting_deadline', { date: formatDate(challenge.voting_deadline) })}
            </span>
          )}
          {challenge.max_ideas_per_user && (
            <span className="flex items-center gap-1.5">
              <Lightbulb className="w-4 h-4" />
              {t('challenge.max_ideas', { count: challenge.max_ideas_per_user })}
            </span>
          )}
        </div>

        {/* Prize */}
        {challenge.prize_description && (
          <div className="mt-4 p-3 rounded-lg bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800">
            <div className="flex items-center gap-2 mb-1">
              <Trophy className="w-4 h-4 text-amber-500" />
              <span className="font-semibold text-amber-700 dark:text-amber-400">
                {t('challenge.prize')}
              </span>
            </div>
            <p className="text-sm text-amber-800 dark:text-amber-300">
              {challenge.prize_description}
            </p>
          </div>
        )}
      </GlassCard>

      {/* Ideas Section */}
      <div className="flex items-center justify-between mb-4">
        <h2 className="text-xl font-semibold text-[var(--color-text)]">
          {t('ideas.title')} ({challenge.ideas_count})
        </h2>

        <div className="flex items-center gap-2">
          {/* Sort Toggle */}
          <div className="flex rounded-lg overflow-hidden border border-[var(--color-border)]">
            <button
              className={`px-3 py-1.5 text-sm transition-colors ${
                sortMode === 'votes'
                  ? 'bg-primary text-white'
                  : 'bg-[var(--color-surface)] text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-hover)]'
              }`}
              onClick={() => setSortMode('votes')}
            >
              {t('ideas.sort_votes')}
            </button>
            <button
              className={`px-3 py-1.5 text-sm transition-colors ${
                sortMode === 'newest'
                  ? 'bg-primary text-white'
                  : 'bg-[var(--color-surface)] text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-hover)]'
              }`}
              onClick={() => setSortMode('newest')}
            >
              {t('ideas.sort_newest')}
            </button>
          </div>

          {/* Submit Idea Button */}
          {canSubmitIdea && (
            <Button
              color="primary"
              size="sm"
              startContent={<Plus className="w-4 h-4" />}
              onPress={onSubmitOpen}
            >
              {t('ideas.submit')}
            </Button>
          )}
        </div>
      </div>

      {/* User idea count */}
      {challenge.max_ideas_per_user && isAuthenticated && (
        <p className="text-xs text-[var(--color-text-tertiary)] mb-3">
          {t('ideas.your_ideas', {
            count: challenge.user_idea_count ?? 0,
            max: challenge.max_ideas_per_user,
          })}
        </p>
      )}

      {/* Ideas Loading */}
      {isLoadingIdeas && (
        <div className="flex justify-center py-8">
          <Spinner size="md" />
        </div>
      )}

      {/* Ideas Empty */}
      {!isLoadingIdeas && ideas.length === 0 && (
        <EmptyState
          icon={<Lightbulb className="w-10 h-10 text-theme-subtle" />}
          title={t('ideas.empty_title')}
          description={t('ideas.empty_description')}
          action={
            canSubmitIdea ? (
              <Button
                color="primary"
                startContent={<Plus className="w-4 h-4" />}
                onPress={onSubmitOpen}
              >
                {t('ideas.submit')}
              </Button>
            ) : undefined
          }
        />
      )}

      {/* Ideas List */}
      {!isLoadingIdeas && ideas.length > 0 && (
        <div className="space-y-3">
          {ideas.map((idea) => (
            <GlassCard key={idea.id} className="p-4">
              <div className="flex items-start gap-4">
                {/* Vote Button */}
                <div className="flex flex-col items-center gap-0.5 min-w-[48px]">
                  <button
                    onClick={(e) => {
                      e.preventDefault();
                      handleVote(idea.id);
                    }}
                    disabled={!isAuthenticated || votingIds.has(idea.id)}
                    className={`p-1.5 rounded-lg transition-colors ${
                      idea.has_voted
                        ? 'bg-primary/10 text-primary'
                        : 'hover:bg-[var(--color-surface-hover)] text-[var(--color-text-tertiary)]'
                    } disabled:opacity-50 disabled:cursor-not-allowed`}
                    aria-label={idea.has_voted ? t('ideas.unvote') : t('ideas.vote')}
                  >
                    <ArrowBigUp
                      className={`w-6 h-6 ${idea.has_voted ? 'fill-current' : ''}`}
                    />
                  </button>
                  <span className={`text-sm font-semibold ${
                    idea.has_voted ? 'text-primary' : 'text-[var(--color-text-secondary)]'
                  }`}>
                    {idea.votes_count}
                  </span>
                </div>

                {/* Idea Content */}
                <div className="flex-1 min-w-0">
                  <Link
                    to={tenantPath(`/ideation/${challenge.id}/ideas/${idea.id}`)}
                    className="block"
                  >
                    <div className="flex items-start gap-2 mb-1">
                      <h3 className="text-base font-semibold text-[var(--color-text)] hover:text-primary transition-colors">
                        {idea.title}
                      </h3>
                      {idea.status !== 'submitted' && (
                        <Chip
                          size="sm"
                          color={IDEA_STATUS_COLOR_MAP[idea.status] ?? 'default'}
                          variant="flat"
                        >
                          {t(`idea_status.${idea.status}`)}
                        </Chip>
                      )}
                    </div>

                    <p className="text-sm text-[var(--color-text-secondary)] line-clamp-2 mb-2">
                      {idea.description}
                    </p>

                    {/* Idea image thumbnail */}
                    {idea.image_url && (
                      <div className="mb-2">
                        <img
                          src={resolveAssetUrl(idea.image_url)}
                          alt={t('idea_image')}
                          className="w-24 h-24 object-cover rounded-lg"
                        />
                      </div>
                    )}
                  </Link>

                  <div className="flex items-center gap-3 text-xs text-[var(--color-text-tertiary)]">
                    <span className="flex items-center gap-1">
                      <Avatar
                        src={resolveAvatarUrl(idea.creator.avatar_url)}
                        size="sm"
                        className="w-4 h-4"
                        name={idea.creator.name}
                      />
                      {idea.creator.name}
                    </span>
                    <span className="flex items-center gap-1">
                      <MessageCircle className="w-3.5 h-3.5" />
                      {t('ideas.comments', { count: idea.comments_count })}
                    </span>
                    <span>{formatRelativeTime(idea.created_at)}</span>

                    {/* Create Group shortcut for shortlisted/winner ideas */}
                    {(idea.status === 'shortlisted' || idea.status === 'winner') &&
                      isAuthenticated &&
                      (isAdmin || user?.id === idea.user_id) && (
                      <Tooltip content={t('convert_to_group.title')}>
                        <Button
                          isIconOnly
                          variant="light"
                          size="sm"
                          className="ml-auto min-w-6 w-6 h-6"
                          onPress={() => navigate(tenantPath(`/ideation/${challenge.id}/ideas/${idea.id}`))}
                          aria-label={t('convert_to_group.title')}
                        >
                          <Users className="w-3.5 h-3.5" />
                        </Button>
                      </Tooltip>
                    )}
                  </div>
                </div>
              </div>
            </GlassCard>
          ))}

          {/* Load More Ideas */}
          {hasMore && (
            <div className="flex justify-center mt-4">
              <Button
                variant="flat"
                isLoading={isLoadingMore}
                onPress={() => fetchIdeas(sortMode, true)}
              >
                {t('ideas.load_more')}
              </Button>
            </div>
          )}
        </div>
      )}

      {/* Submit Idea Modal */}
      <Modal isOpen={isSubmitOpen} onClose={onSubmitClose} size="lg">
        <ModalContent>
          <ModalHeader>{t('ideas.submit_title')}</ModalHeader>
          <ModalBody>
            <Input
              label={t('form.title_label')}
              placeholder={t('form.title_placeholder')}
              value={newIdea.title}
              onValueChange={(val) => setNewIdea(prev => ({ ...prev, title: val }))}
              variant="bordered"
              isRequired
            />
            <Textarea
              label={t('form.description_label')}
              placeholder={t('form.description_placeholder')}
              value={newIdea.description}
              onValueChange={(val) => setNewIdea(prev => ({ ...prev, description: val }))}
              variant="bordered"
              minRows={4}
              isRequired
            />
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onSubmitClose}>
              {t('form.cancel')}
            </Button>
            <Button
              color="primary"
              isLoading={isSubmitting}
              isDisabled={!newIdea.title.trim() || !newIdea.description.trim()}
              onPress={handleSubmitIdea}
            >
              {isSubmitting ? t('form.saving') : t('ideas.submit')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Delete Challenge Confirmation Modal */}
      <Modal isOpen={isDeleteOpen} onClose={onDeleteClose}>
        <ModalContent>
          <ModalHeader>{t('admin.delete_challenge')}</ModalHeader>
          <ModalBody>
            <p className="text-[var(--color-text-secondary)]">
              {t('admin.delete_confirm')}
            </p>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onDeleteClose}>
              {t('form.cancel')}
            </Button>
            <Button
              color="danger"
              isLoading={isDeleting}
              onPress={handleDeleteChallenge}
            >
              {t('admin.delete_challenge')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default ChallengeDetailPage;
