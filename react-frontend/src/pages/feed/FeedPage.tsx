/**
 * Feed Page - Social feed with posts, likes, comments, polls, and moderation
 *
 * Uses V2 API: GET /api/v2/feed, POST /api/v2/feed/posts, POST /api/v2/feed/like
 * Uses V2 API: GET /api/v2/comments, POST /api/v2/comments
 * Uses V2 API: POST /api/v2/feed/posts/{id}/hide, POST /api/v2/feed/users/{id}/mute
 * Uses V2 API: POST /api/v2/feed/posts/{id}/report
 * Uses V2 API: POST /api/v2/feed/polls, POST /api/v2/feed/polls/{id}/vote
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { Link } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import {
  Button,
  Input,
  Avatar,
  Textarea,
  Chip,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Dropdown,
  DropdownTrigger,
  DropdownMenu,
  DropdownItem,
  Progress,
  useDisclosure,
} from '@heroui/react';
import {
  Newspaper,
  Plus,
  RefreshCw,
  AlertTriangle,
  Heart,
  MessageCircle,
  Send,
  MoreHorizontal,
  EyeOff,
  VolumeX,
  Flag,
  Trash2,
  ImagePlus,
  X,
  BarChart3,
  Check,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useAuth, useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl, resolveAssetUrl, formatRelativeTime } from '@/lib/helpers';
import { usePageTitle } from '@/hooks';

/* ───────────────────────── Types ───────────────────────── */

type FeedFilter = 'all' | 'posts' | 'listings' | 'events' | 'polls' | 'goals';
type PostMode = 'text' | 'poll';

interface FeedItem {
  id: number;
  content: string;
  title?: string;
  // Support both flat and nested author formats from API
  author_name?: string;
  author_avatar?: string;
  author_id?: number;
  author?: {
    id: number;
    name: string;
    avatar_url?: string;
  };
  created_at: string;
  type: 'post' | 'listing' | 'event' | 'poll' | 'goal';
  likes_count: number;
  comments_count: number;
  is_liked: boolean;
  image_url?: string;
  // Poll data (loaded lazily for poll-type items)
  poll_data?: PollData;
}

interface PollData {
  id: number;
  question: string;
  options: PollOption[];
  total_votes: number;
  user_vote_option_id: number | null;
  is_active: boolean;
}

interface PollOption {
  id: number;
  text: string;
  vote_count: number;
  percentage: number;
}

interface FeedCommentAuthor {
  id: number;
  name: string;
  avatar: string | null;
}

interface FeedComment {
  id: number;
  content: string;
  created_at: string;
  edited: boolean;
  is_own: boolean;
  author: FeedCommentAuthor;
  reactions: Record<string, number>;
  user_reactions: string[];
  replies: FeedComment[];
}

/* ───────────────────────── Helpers ───────────────────────── */

/** Normalize author fields from API (supports both flat and nested) */
function getAuthor(item: FeedItem) {
  return {
    id: item.author_id ?? item.author?.id ?? 0,
    name: item.author_name ?? item.author?.name ?? 'Unknown',
    avatar: item.author_avatar ?? item.author?.avatar_url ?? null,
  };
}

/* ───────────────────────── Main Component ───────────────────────── */

export function FeedPage() {
  usePageTitle('Feed');
  const { isAuthenticated, user } = useAuth();
  const toast = useToast();
  const [items, setItems] = useState<FeedItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [filter, setFilter] = useState<FeedFilter>('all');
  const [hasMore, setHasMore] = useState(false);
  const [cursor, setCursor] = useState<string | undefined>();
  const [isLoadingMore, setIsLoadingMore] = useState(false);

  // Create post
  const { isOpen: isCreateOpen, onOpen: onCreateOpen, onClose: onCreateClose } = useDisclosure();
  const [newPostContent, setNewPostContent] = useState('');
  const [isCreating, setIsCreating] = useState(false);
  const [postMode, setPostMode] = useState<PostMode>('text');

  // Image upload
  const [imageFile, setImageFile] = useState<File | null>(null);
  const [imagePreview, setImagePreview] = useState<string | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);

  // Poll creation
  const [pollQuestion, setPollQuestion] = useState('');
  const [pollOptions, setPollOptions] = useState<string[]>(['', '']);

  // Report modal
  const { isOpen: isReportOpen, onOpen: onReportOpen, onClose: onReportClose } = useDisclosure();
  const [reportPostId, setReportPostId] = useState<number | null>(null);
  const [reportReason, setReportReason] = useState('');
  const [isReporting, setIsReporting] = useState(false);

  const loadFeed = useCallback(async (append = false) => {
    try {
      if (append) {
        setIsLoadingMore(true);
      } else {
        setIsLoading(true);
        setError(null);
      }

      const params = new URLSearchParams();
      params.set('per_page', '20');
      if (filter !== 'all') params.set('type', filter);
      if (append && cursor) params.set('cursor', cursor);

      const response = await api.get<{ data: FeedItem[]; meta: { cursor: string | null; has_more: boolean } }>(
        `/v2/feed?${params}`
      );

      if (response.success && response.data) {
        const responseData = response.data as unknown as { data?: FeedItem[]; meta?: { cursor: string | null; has_more: boolean } };
        const feedItems = responseData.data ?? (response.data as unknown as FeedItem[]);
        const resMeta = responseData.meta;

        if (append) {
          setItems((prev) => [...prev, ...(Array.isArray(feedItems) ? feedItems : [])]);
        } else {
          setItems(Array.isArray(feedItems) ? feedItems : []);
        }
        setHasMore(resMeta?.has_more ?? false);
        setCursor(resMeta?.cursor ?? undefined);
      } else {
        if (!append) setError('Failed to load feed.');
      }
    } catch (err) {
      logError('Failed to load feed', err);
      if (!append) setError('Failed to load feed. Please try again.');
    } finally {
      setIsLoading(false);
      setIsLoadingMore(false);
    }
  }, [cursor, filter]);

  useEffect(() => {
    setCursor(undefined);
    loadFeed();
  }, [filter, loadFeed]);

  /* ───────── Create Post (Text + Image) ───────── */

  const handleImageSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    // Validate file type
    if (!file.type.startsWith('image/')) {
      toast.error('Please select an image file');
      return;
    }

    // Validate file size (5MB)
    if (file.size > 5 * 1024 * 1024) {
      toast.error('Image must be smaller than 5MB');
      return;
    }

    setImageFile(file);
    const reader = new FileReader();
    reader.onload = (ev) => setImagePreview(ev.target?.result as string);
    reader.readAsDataURL(file);
  };

  const removeImage = () => {
    setImageFile(null);
    setImagePreview(null);
    if (fileInputRef.current) {
      fileInputRef.current.value = '';
    }
  };

  const handleCreatePost = async () => {
    if (!newPostContent.trim() && !imageFile) return;

    try {
      setIsCreating(true);

      if (imageFile) {
        // Use FormData for image upload
        const formData = new FormData();
        formData.append('content', newPostContent.trim());
        formData.append('visibility', 'public');
        formData.append('image', imageFile);

        // Use legacy create-post endpoint that supports file uploads
        const response = await api.post('/social/create-post', formData as unknown as Record<string, unknown>);
        if (response.success) {
          onCreateClose();
          resetCreateForm();
          setCursor(undefined);
          loadFeed();
          toast.success('Post created!');
        }
      } else {
        const response = await api.post('/v2/feed/posts', {
          content: newPostContent.trim(),
          visibility: 'public',
        });

        if (response.success) {
          onCreateClose();
          resetCreateForm();
          setCursor(undefined);
          loadFeed();
          toast.success('Post created!');
        }
      }
    } catch (err) {
      logError('Failed to create post', err);
      toast.error('Failed to create post');
    } finally {
      setIsCreating(false);
    }
  };

  /* ───────── Create Poll ───────── */

  const handleCreatePoll = async () => {
    if (!pollQuestion.trim()) {
      toast.error('Please enter a question');
      return;
    }

    const validOptions = pollOptions.filter((o) => o.trim().length > 0);
    if (validOptions.length < 2) {
      toast.error('Add at least 2 options');
      return;
    }

    try {
      setIsCreating(true);
      const response = await api.post('/v2/feed/polls', {
        question: pollQuestion.trim(),
        options: validOptions.map((o) => o.trim()),
      });

      if (response.success) {
        onCreateClose();
        resetCreateForm();
        setCursor(undefined);
        loadFeed();
        toast.success('Poll created!');
      }
    } catch (err) {
      logError('Failed to create poll', err);
      toast.error('Failed to create poll');
    } finally {
      setIsCreating(false);
    }
  };

  const addPollOption = () => {
    if (pollOptions.length < 6) {
      setPollOptions([...pollOptions, '']);
    }
  };

  const updatePollOption = (index: number, value: string) => {
    const updated = [...pollOptions];
    updated[index] = value;
    setPollOptions(updated);
  };

  const removePollOption = (index: number) => {
    if (pollOptions.length > 2) {
      setPollOptions(pollOptions.filter((_, i) => i !== index));
    }
  };

  const resetCreateForm = () => {
    setNewPostContent('');
    setPostMode('text');
    setImageFile(null);
    setImagePreview(null);
    setPollQuestion('');
    setPollOptions(['', '']);
    if (fileInputRef.current) fileInputRef.current.value = '';
  };

  /* ───────── Like Toggle ───────── */

  const handleToggleLike = async (item: FeedItem) => {
    // Optimistic update
    setItems((prev) =>
      prev.map((fi) =>
        fi.id === item.id && fi.type === item.type
          ? {
              ...fi,
              is_liked: !fi.is_liked,
              likes_count: fi.is_liked ? fi.likes_count - 1 : fi.likes_count + 1,
            }
          : fi
      )
    );

    try {
      await api.post('/v2/feed/like', {
        target_type: item.type,
        target_id: item.id,
      });
    } catch (err) {
      logError('Failed to toggle like', err);
      // Revert on error
      setItems((prev) =>
        prev.map((fi) =>
          fi.id === item.id && fi.type === item.type
            ? {
                ...fi,
                is_liked: !fi.is_liked,
                likes_count: fi.is_liked ? fi.likes_count - 1 : fi.likes_count + 1,
              }
            : fi
        )
      );
    }
  };

  /* ───────── Moderation ───────── */

  const handleHidePost = async (postId: number) => {
    try {
      await api.post(`/v2/feed/posts/${postId}/hide`);
      setItems((prev) => prev.filter((fi) => !(fi.id === postId && fi.type === 'post')));
      toast.success('Post hidden');
    } catch (err) {
      logError('Failed to hide post', err);
      toast.error('Failed to hide post');
    }
  };

  const handleMuteUser = async (userId: number) => {
    try {
      await api.post(`/v2/feed/users/${userId}/mute`);
      // Remove all posts from muted user
      setItems((prev) => prev.filter((fi) => getAuthor(fi).id !== userId));
      toast.success('User muted');
    } catch (err) {
      logError('Failed to mute user', err);
      toast.error('Failed to mute user');
    }
  };

  const openReportModal = (postId: number) => {
    setReportPostId(postId);
    setReportReason('');
    onReportOpen();
  };

  const handleReport = async () => {
    if (!reportPostId || !reportReason.trim()) {
      toast.error('Please provide a reason');
      return;
    }

    try {
      setIsReporting(true);
      await api.post(`/v2/feed/posts/${reportPostId}/report`, {
        reason: reportReason.trim(),
      });
      onReportClose();
      setReportPostId(null);
      setReportReason('');
      toast.success('Post reported. Thank you for helping keep our community safe.');
    } catch (err) {
      logError('Failed to report post', err);
      toast.error('Failed to report post');
    } finally {
      setIsReporting(false);
    }
  };

  const handleDeletePost = async (item: FeedItem) => {
    try {
      await api.post('/social/delete', {
        target_type: item.type,
        target_id: item.id,
      });
      setItems((prev) => prev.filter((fi) => !(fi.id === item.id && fi.type === item.type)));
      toast.success('Post deleted');
    } catch (err) {
      logError('Failed to delete post', err);
      toast.error('Failed to delete post');
    }
  };

  /* ───────── Poll Voting ───────── */

  const handleVotePoll = async (pollId: number, optionId: number) => {
    try {
      const response = await api.post<PollData>(`/v2/feed/polls/${pollId}/vote`, {
        option_id: optionId,
      });

      if (response.success && response.data) {
        // Update the poll data in the feed item
        setItems((prev) =>
          prev.map((fi) =>
            fi.id === pollId && fi.type === 'poll'
              ? { ...fi, poll_data: response.data as PollData }
              : fi
          )
        );
      }
    } catch (err) {
      logError('Failed to vote', err);
      toast.error('Failed to submit vote');
    }
  };

  const filterOptions: { key: FeedFilter; label: string }[] = [
    { key: 'all', label: 'All' },
    { key: 'posts', label: 'Posts' },
    { key: 'listings', label: 'Listings' },
    { key: 'events', label: 'Events' },
    { key: 'polls', label: 'Polls' },
    { key: 'goals', label: 'Goals' },
  ];

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: { opacity: 1, transition: { staggerChildren: 0.06 } },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 20 },
    visible: { opacity: 1, y: 0 },
  };

  return (
    <div className="max-w-2xl mx-auto space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-3">
            <Newspaper className="w-7 h-7 text-indigo-400" aria-hidden="true" />
            Community Feed
          </h1>
          <p className="text-theme-muted mt-1">See what&apos;s happening in your community</p>
        </div>

        {isAuthenticated && (
          <Button
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
            startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
            onPress={onCreateOpen}
          >
            New Post
          </Button>
        )}
      </div>

      {/* Quick Post Box */}
      {isAuthenticated && (
        <GlassCard className="p-4">
          <div
            className="flex items-center gap-3 cursor-pointer"
            onClick={onCreateOpen}
            role="button"
            tabIndex={0}
            onKeyDown={(e) => e.key === 'Enter' && onCreateOpen()}
          >
            <Avatar
              name={user?.first_name || 'You'}
              src={resolveAvatarUrl(user?.avatar)}
              size="sm"
            />
            <div className="flex-1 bg-theme-hover rounded-full px-4 py-2.5 text-theme-subtle text-sm">
              What&apos;s on your mind?
            </div>
          </div>
        </GlassCard>
      )}

      {/* Filter Chips */}
      <div className="flex gap-2 flex-wrap">
        {filterOptions.map((opt) => (
          <Button
            key={opt.key}
            size="sm"
            variant={filter === opt.key ? 'solid' : 'flat'}
            className={
              filter === opt.key
                ? 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white'
                : 'bg-theme-elevated text-theme-muted'
            }
            onPress={() => setFilter(opt.key)}
          >
            {opt.label}
          </Button>
        ))}
      </div>

      {/* Error State */}
      {error && !isLoading && (
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary mb-2">Unable to Load Feed</h2>
          <p className="text-theme-muted mb-4">{error}</p>
          <Button
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
            startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
            onPress={() => loadFeed()}
          >
            Try Again
          </Button>
        </GlassCard>
      )}

      {/* Feed Items */}
      {!error && (
        <>
          {isLoading ? (
            <div className="space-y-4">
              {[1, 2, 3].map((i) => (
                <GlassCard key={i} className="p-5 animate-pulse">
                  <div className="flex items-center gap-3 mb-4">
                    <div className="w-10 h-10 rounded-full bg-theme-hover" />
                    <div className="flex-1">
                      <div className="h-4 bg-theme-hover rounded w-1/3 mb-2" />
                      <div className="h-3 bg-theme-hover rounded w-1/5" />
                    </div>
                  </div>
                  <div className="h-4 bg-theme-hover rounded w-full mb-2" />
                  <div className="h-4 bg-theme-hover rounded w-3/4 mb-4" />
                  <div className="flex gap-4">
                    <div className="h-3 bg-theme-hover rounded w-16" />
                    <div className="h-3 bg-theme-hover rounded w-16" />
                  </div>
                </GlassCard>
              ))}
            </div>
          ) : items.length === 0 ? (
            <EmptyState
              icon={<Newspaper className="w-12 h-12" aria-hidden="true" />}
              title="No posts yet"
              description={
                filter !== 'all'
                  ? `No ${filter} in the feed right now`
                  : 'Be the first to share something with your community!'
              }
              action={
                isAuthenticated ? (
                  <Button
                    className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                    onPress={onCreateOpen}
                  >
                    Create Post
                  </Button>
                ) : undefined
              }
            />
          ) : (
            <motion.div variants={containerVariants} initial="hidden" animate="visible" className="space-y-4">
              <AnimatePresence mode="popLayout">
                {items.map((item) => (
                  <motion.div key={`${item.type}-${item.id}`} variants={itemVariants} layout>
                    <FeedCard
                      item={item}
                      onToggleLike={() => handleToggleLike(item)}
                      onHidePost={() => handleHidePost(item.id)}
                      onMuteUser={() => handleMuteUser(getAuthor(item).id)}
                      onReportPost={() => openReportModal(item.id)}
                      onDeletePost={() => handleDeletePost(item)}
                      onVotePoll={handleVotePoll}
                      isAuthenticated={isAuthenticated}
                      currentUserId={user?.id}
                    />
                  </motion.div>
                ))}
              </AnimatePresence>

              {hasMore && (
                <div className="pt-4 text-center">
                  <Button
                    variant="flat"
                    className="bg-theme-elevated text-theme-muted"
                    onPress={() => loadFeed(true)}
                    isLoading={isLoadingMore}
                  >
                    Load More
                  </Button>
                </div>
              )}
            </motion.div>
          )}
        </>
      )}

      {/* Hidden file input for image upload */}
      <input
        ref={fileInputRef}
        type="file"
        accept="image/jpeg,image/png,image/gif,image/webp"
        className="hidden"
        onChange={handleImageSelect}
      />

      {/* Create Post Modal */}
      <Modal
        isOpen={isCreateOpen}
        onClose={() => { onCreateClose(); resetCreateForm(); }}
        size="lg"
        classNames={{
          base: 'bg-content1 border border-theme-default',
        }}
      >
        <ModalContent>
          <ModalHeader className="text-theme-primary">
            <div className="flex items-center gap-3 w-full">
              <span>Create Post</span>
              <div className="flex gap-1 ml-auto">
                <Chip
                  size="sm"
                  variant={postMode === 'text' ? 'solid' : 'flat'}
                  className={postMode === 'text' ? 'bg-indigo-500 text-white cursor-pointer' : 'bg-theme-elevated text-theme-muted cursor-pointer'}
                  onClick={() => setPostMode('text')}
                >
                  Text
                </Chip>
                <Chip
                  size="sm"
                  variant={postMode === 'poll' ? 'solid' : 'flat'}
                  className={postMode === 'poll' ? 'bg-indigo-500 text-white cursor-pointer' : 'bg-theme-elevated text-theme-muted cursor-pointer'}
                  onClick={() => setPostMode('poll')}
                >
                  <BarChart3 className="w-3 h-3 mr-1 inline" aria-hidden="true" />
                  Poll
                </Chip>
              </div>
            </div>
          </ModalHeader>
          <ModalBody>
            {postMode === 'text' ? (
              <>
                <div className="flex items-start gap-3">
                  <Avatar
                    name={user?.first_name || 'You'}
                    src={resolveAvatarUrl(user?.avatar)}
                    size="sm"
                    className="mt-1"
                  />
                  <Textarea
                    placeholder="What's on your mind?"
                    value={newPostContent}
                    onChange={(e) => setNewPostContent(e.target.value)}
                    minRows={3}
                    maxRows={8}
                    classNames={{
                      input: 'bg-transparent text-theme-primary',
                      inputWrapper: 'bg-theme-elevated border-theme-default',
                    }}
                    autoFocus
                  />
                </div>

                {/* Image Preview */}
                {imagePreview && (
                  <div className="relative mt-3 rounded-xl overflow-hidden border border-theme-default">
                    <img src={imagePreview} alt="Upload preview" className="w-full max-h-60 object-cover" />
                    <Button
                      isIconOnly
                      size="sm"
                      variant="flat"
                      className="absolute top-2 right-2 bg-black/60 text-white min-w-0"
                      onPress={removeImage}
                      aria-label="Remove image"
                    >
                      <X className="w-4 h-4" />
                    </Button>
                  </div>
                )}

                {/* Image Upload Button */}
                <div className="flex items-center gap-2 mt-2">
                  <Button
                    size="sm"
                    variant="flat"
                    className="bg-theme-elevated text-theme-muted"
                    startContent={<ImagePlus className="w-4 h-4" aria-hidden="true" />}
                    onPress={() => fileInputRef.current?.click()}
                  >
                    {imageFile ? 'Change Image' : 'Add Image'}
                  </Button>
                  {imageFile && (
                    <span className="text-xs text-theme-subtle">
                      {imageFile.name} ({(imageFile.size / 1024 / 1024).toFixed(1)}MB)
                    </span>
                  )}
                </div>
              </>
            ) : (
              /* Poll Creation */
              <div className="space-y-4">
                <div className="flex items-start gap-3">
                  <Avatar
                    name={user?.first_name || 'You'}
                    src={resolveAvatarUrl(user?.avatar)}
                    size="sm"
                    className="mt-1"
                  />
                  <Input
                    placeholder="Ask a question..."
                    value={pollQuestion}
                    onChange={(e) => setPollQuestion(e.target.value)}
                    classNames={{
                      input: 'bg-transparent text-theme-primary',
                      inputWrapper: 'bg-theme-elevated border-theme-default',
                    }}
                    autoFocus
                  />
                </div>

                <div className="space-y-2 pl-11">
                  {pollOptions.map((opt, index) => (
                    <div key={index} className="flex items-center gap-2">
                      <Input
                        placeholder={`Option ${index + 1}`}
                        value={opt}
                        onChange={(e) => updatePollOption(index, e.target.value)}
                        size="sm"
                        classNames={{
                          input: 'bg-transparent text-theme-primary',
                          inputWrapper: 'bg-theme-elevated border-theme-default',
                        }}
                      />
                      {pollOptions.length > 2 && (
                        <Button
                          isIconOnly
                          size="sm"
                          variant="light"
                          className="text-theme-muted min-w-0"
                          onPress={() => removePollOption(index)}
                          aria-label={`Remove option ${index + 1}`}
                        >
                          <X className="w-4 h-4" />
                        </Button>
                      )}
                    </div>
                  ))}

                  {pollOptions.length < 6 && (
                    <Button
                      size="sm"
                      variant="flat"
                      className="bg-theme-elevated text-indigo-400"
                      startContent={<Plus className="w-3 h-3" aria-hidden="true" />}
                      onPress={addPollOption}
                    >
                      Add Option
                    </Button>
                  )}
                </div>
              </div>
            )}
          </ModalBody>
          <ModalFooter>
            <Button
              variant="flat"
              onPress={() => { onCreateClose(); resetCreateForm(); }}
              className="text-theme-muted"
            >
              Cancel
            </Button>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              onPress={postMode === 'text' ? handleCreatePost : handleCreatePoll}
              isLoading={isCreating}
              isDisabled={
                postMode === 'text'
                  ? (!newPostContent.trim() && !imageFile)
                  : (!pollQuestion.trim() || pollOptions.filter((o) => o.trim()).length < 2)
              }
            >
              {postMode === 'text' ? 'Post' : 'Create Poll'}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Report Post Modal */}
      <Modal
        isOpen={isReportOpen}
        onClose={onReportClose}
        classNames={{
          base: 'bg-content1 border border-theme-default',
        }}
      >
        <ModalContent>
          <ModalHeader className="text-theme-primary">Report Post</ModalHeader>
          <ModalBody>
            <p className="text-sm text-theme-muted mb-3">
              Please describe why you are reporting this post. Our moderators will review your report.
            </p>
            <Textarea
              label="Reason"
              placeholder="Describe the issue..."
              value={reportReason}
              onChange={(e) => setReportReason(e.target.value)}
              minRows={3}
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default',
              }}
              autoFocus
            />
          </ModalBody>
          <ModalFooter>
            <Button
              variant="flat"
              onPress={onReportClose}
              className="text-theme-muted"
            >
              Cancel
            </Button>
            <Button
              color="danger"
              onPress={handleReport}
              isLoading={isReporting}
              isDisabled={!reportReason.trim()}
            >
              Submit Report
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

/* ───────────────────────── Feed Card ───────────────────────── */

interface FeedCardProps {
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

function FeedCard({
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
  }[item.type];

  const typeColor = {
    post: 'default',
    listing: 'primary',
    event: 'success',
    poll: 'warning',
    goal: 'secondary',
  }[item.type] as 'default' | 'primary' | 'success' | 'warning' | 'secondary';

  // Load poll data for poll-type items
  useEffect(() => {
    if (item.type === 'poll' && !pollData && !isLoadingPoll) {
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
  }, [item.type, item.id, pollData, isLoadingPoll]);

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
      const response = await api.get<{ data: { comments: FeedComment[] } }>(
        `/v2/comments?target_type=${item.type}&target_id=${item.id}`
      );

      if (response.success && response.data) {
        const data = response.data as unknown as { data?: { comments?: FeedComment[] } };
        setComments(data.data?.comments ?? []);
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
                to={`/profile/${author.id}`}
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
            alt=""
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
}

/* ───────────────────────── Comment Item ───────────────────────── */

interface CommentItemProps {
  comment: FeedComment;
}

function CommentItem({ comment }: CommentItemProps) {
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
              to={`/profile/${comment.author.id}`}
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

export default FeedPage;
