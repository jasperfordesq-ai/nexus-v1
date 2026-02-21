// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

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
  useDisclosure,
} from '@heroui/react';
import {
  Newspaper,
  Plus,
  RefreshCw,
  AlertTriangle,
  ImagePlus,
  X,
  BarChart3,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useAuth, useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl } from '@/lib/helpers';
import { usePageTitle } from '@/hooks';
import { FeedCard } from '@/components/feed/FeedCard';
import type { FeedItem, FeedFilter, PollData } from '@/components/feed/types';
import { getAuthor } from '@/components/feed/types';

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
  const [isLoadingMore, setIsLoadingMore] = useState(false);

  // Create post
  const { isOpen: isCreateOpen, onOpen: onCreateOpen, onClose: onCreateClose } = useDisclosure();
  const [newPostContent, setNewPostContent] = useState('');
  const [isCreating, setIsCreating] = useState(false);
  const [postMode, setPostMode] = useState<'text' | 'poll'>('text');

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

  // Use a ref for cursor to avoid infinite re-render loop
  const cursorRef = useRef<string | undefined>();

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
      if (append && cursorRef.current) params.set('cursor', cursorRef.current);

      const response = await api.get<FeedItem[]>(
        `/v2/feed?${params}`
      );

      if (response.success && response.data) {
        const feedItems = Array.isArray(response.data) ? response.data : [];

        if (append) {
          setItems((prev) => [...prev, ...feedItems]);
        } else {
          setItems(feedItems);
        }
        setHasMore(response.meta?.has_more ?? false);
        cursorRef.current = response.meta?.cursor ?? undefined;
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
  }, [filter]);

  useEffect(() => {
    cursorRef.current = undefined;
    loadFeed();
  }, [filter, loadFeed]);

  /* ───────── Create Post (Text + Image) ───────── */

  const handleImageSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    if (!file.type.startsWith('image/')) {
      toast.error('Please select an image file');
      return;
    }

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
        const formData = new FormData();
        formData.append('content', newPostContent.trim());
        formData.append('visibility', 'public');
        formData.append('image', imageFile);

        const response = await api.post('/social/create-post', formData as unknown as Record<string, unknown>);
        if (response.success) {
          onCreateClose();
          resetCreateForm();
          cursorRef.current = undefined;
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
          cursorRef.current = undefined;
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
        cursorRef.current = undefined;
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

export default FeedPage;
