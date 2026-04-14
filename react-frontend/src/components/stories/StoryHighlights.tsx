// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * StoryHighlights — Circle row on profile page showing saved story highlights.
 *
 * Each highlight shows a cover image circle + title.
 * Owner sees a "+" button to create new highlights.
 * Clicking opens the highlight stories in the StoryViewer.
 * Owner can edit title, remove stories from highlight, and reorder highlights.
 */

import { useState, useEffect, useCallback } from 'react';
import {
  Skeleton,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Button,
  Input,
  useDisclosure,
} from '@heroui/react';
import { Plus, X, Pencil, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useAuth, useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAssetUrl } from '@/lib/helpers';
import { StoryViewer } from '@/components/stories/StoryViewer';
import type { StoryUser } from '@/components/feed/StoriesBar';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface Highlight {
  id: number;
  title: string;
  cover_url?: string | null;
  story_count: number;
  display_order: number;
  created_at: string;
}

interface HighlightStory {
  id: number;
  user_id: number;
  media_type: 'image' | 'text' | 'poll';
  media_url?: string | null;
  text_content?: string | null;
  background_gradient?: string | null;
  background_color?: string | null;
  duration: number;
  view_count: number;
  is_viewed: boolean;
  expires_at: string;
  created_at: string;
  user?: {
    id: number;
    name: string;
    first_name: string;
    avatar_url?: string | null;
  };
  poll_question?: string;
  poll_options?: string[];
  poll_results?: { votes: Record<number, number>; total_votes: number };
}

interface StoryHighlightsProps {
  userId: number;
  userName: string;
  userAvatar?: string | null;
}

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function StoryHighlights({ userId, userName, userAvatar }: StoryHighlightsProps) {
  const { user: currentUser } = useAuth();
  const { t } = useTranslation('stories');
  const toast = useToast();
  const [highlights, setHighlights] = useState<Highlight[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [viewerOpen, setViewerOpen] = useState(false);
  const [viewerStories, setViewerStories] = useState<StoryUser[]>([]);
  const { isOpen: isCreateOpen, onOpen: onCreateOpen, onClose: onCreateClose } = useDisclosure();
  const [newTitle, setNewTitle] = useState('');
  const [isCreating, setIsCreating] = useState(false);

  // Edit highlight state
  const { isOpen: isEditOpen, onOpen: onEditOpen, onClose: onEditClose } = useDisclosure();
  const [editingHighlight, setEditingHighlight] = useState<Highlight | null>(null);
  const [editTitle, setEditTitle] = useState('');
  const [editStories, setEditStories] = useState<HighlightStory[]>([]);
  const [isEditLoading, setIsEditLoading] = useState(false);
  const [isSavingTitle, setIsSavingTitle] = useState(false);
  const [removingStoryId, setRemovingStoryId] = useState<number | null>(null);

  const isOwner = currentUser?.id === userId;

  const loadHighlights = useCallback(async () => {
    try {
      setIsLoading(true);
      const response = await api.get<Highlight[]>(`/v2/stories/highlights/${userId}`);
      if (response.success && response.data) {
        setHighlights(Array.isArray(response.data) ? response.data : []);
      }
    } catch (err) {
      logError('Failed to load highlights', err);
    } finally {
      setIsLoading(false);
    }
  }, [userId]);

  useEffect(() => {
    loadHighlights();
  }, [loadHighlights]);

  const handleHighlightClick = async (highlight: Highlight) => {
    try {
      const response = await api.get<HighlightStory[]>(
        `/v2/stories/highlights/${highlight.id}/stories`
      );
      if (response.success && response.data && Array.isArray(response.data) && response.data.length > 0) {
        // Create a synthetic StoryUser for the viewer
        const syntheticUser: StoryUser = {
          user_id: userId,
          name: userName,
          first_name: userName.split(' ')[0] || userName,
          avatar_url: userAvatar ?? undefined,
          story_count: response.data.length,
          has_unseen: false,
          is_own: isOwner,
          is_connected: false,
          latest_at: response.data[response.data.length - 1]?.created_at || '',
        };
        setViewerStories([syntheticUser]);
        setViewerOpen(true);
      } else {
        toast.info('This highlight has no stories');
      }
    } catch (err) {
      logError('Failed to load highlight stories', err);
      toast.error('Failed to load highlight');
    }
  };

  const handleCreateHighlight = async () => {
    if (!newTitle.trim() || isCreating) return;
    setIsCreating(true);
    try {
      const response = await api.post('/v2/stories/highlights', {
        title: newTitle.trim(),
        story_ids: [],
      });
      if (response.success) {
        toast.success('Highlight created!');
        setNewTitle('');
        onCreateClose();
        loadHighlights();
      } else {
        toast.error('Failed to create highlight');
      }
    } catch (err) {
      logError('Failed to create highlight', err);
      toast.error('Failed to create highlight');
    } finally {
      setIsCreating(false);
    }
  };

  const handleDeleteHighlight = async (highlightId: number, e: React.MouseEvent) => {
    e.stopPropagation();
    try {
      const response = await api.delete(`/v2/stories/highlights/${highlightId}`);
      if (response.success) {
        setHighlights((prev) => prev.filter((h) => h.id !== highlightId));
        toast.success('Highlight deleted');
      }
    } catch (err) {
      logError('Failed to delete highlight', err);
    }
  };

  // ── Edit highlight handlers ──────────────────────────────────────────────

  const handleEditClick = async (highlight: Highlight, e: React.MouseEvent) => {
    e.stopPropagation();
    setEditingHighlight(highlight);
    setEditTitle(highlight.title);
    setEditStories([]);
    setIsEditLoading(true);
    onEditOpen();

    // Load stories in this highlight
    try {
      const response = await api.get<HighlightStory[]>(
        `/v2/stories/highlights/${highlight.id}/stories`
      );
      if (response.success && response.data) {
        setEditStories(Array.isArray(response.data) ? response.data : []);
      }
    } catch (err) {
      logError('Failed to load highlight stories for editing', err);
    } finally {
      setIsEditLoading(false);
    }
  };

  const handleSaveTitle = async () => {
    if (!editingHighlight || !editTitle.trim() || isSavingTitle) return;
    if (editTitle.trim() === editingHighlight.title) return; // No change

    setIsSavingTitle(true);
    try {
      const response = await api.put(`/v2/stories/highlights/${editingHighlight.id}`, {
        title: editTitle.trim(),
      });
      if (response.success) {
        toast.success('Highlight title updated');
        // Update local state
        setHighlights((prev) =>
          prev.map((h) => (h.id === editingHighlight.id ? { ...h, title: editTitle.trim() } : h))
        );
        setEditingHighlight((prev) => (prev ? { ...prev, title: editTitle.trim() } : prev));
      } else {
        toast.error('Failed to update title');
      }
    } catch (err) {
      logError('Failed to update highlight title', err);
      toast.error('Failed to update title');
    } finally {
      setIsSavingTitle(false);
    }
  };

  const handleRemoveStory = async (storyId: number) => {
    if (!editingHighlight || removingStoryId !== null) return;

    setRemovingStoryId(storyId);
    try {
      const response = await api.delete(
        `/v2/stories/highlights/${editingHighlight.id}/items/${storyId}`
      );
      if (response.success) {
        setEditStories((prev) => prev.filter((s) => s.id !== storyId));
        // Update story count in highlights list
        setHighlights((prev) =>
          prev.map((h) =>
            h.id === editingHighlight.id ? { ...h, story_count: Math.max(0, h.story_count - 1) } : h
          )
        );
        toast.success('Story removed from highlight');
      } else {
        toast.error('Failed to remove story');
      }
    } catch (err) {
      logError('Failed to remove story from highlight', err);
      toast.error('Failed to remove story');
    } finally {
      setRemovingStoryId(null);
    }
  };

  const handleEditClose = () => {
    onEditClose();
    setEditingHighlight(null);
    setEditTitle('');
    setEditStories([]);
  };

  // Show skeleton while loading
  if (isLoading) {
    return (
      <div className="flex gap-4 px-1 py-2 overflow-x-auto scrollbar-hide">
        {Array.from({ length: 4 }).map((_, i) => (
          <div key={i} className="flex flex-col items-center gap-1.5 flex-shrink-0 w-18">
            <Skeleton className="w-16 h-16 rounded-full" />
            <Skeleton className="h-3 w-12 rounded" />
          </div>
        ))}
      </div>
    );
  }

  // Don't render if no highlights and not owner
  if (highlights.length === 0 && !isOwner) return null;

  return (
    <>
      <div className="overflow-x-auto scrollbar-hide">
        <div className="flex items-start gap-4 px-1 py-2 min-w-min">
          {/* Create new highlight button (owner only) */}
          {isOwner && (
            <Button
              variant="light"
              onPress={onCreateOpen}
              className="flex flex-col items-center gap-1.5 flex-shrink-0 w-18 group h-auto min-w-0 p-0"
              aria-label={t('highlights.aria_create', 'Create new highlight')}
            >
              <div className="w-16 h-16 rounded-full border-2 border-dashed border-[var(--border-default)] flex items-center justify-center group-hover:border-[var(--color-primary)] transition-colors">
                <Plus className="w-6 h-6 text-[var(--text-muted)] group-hover:text-[var(--color-primary)] transition-colors" />
              </div>
              <span className="text-xs text-[var(--text-muted)] text-center">{t('highlights.new', 'New')}</span>
            </Button>
          )}

          {/* Highlight circles */}
          {highlights.map((highlight) => (
            <Button
              key={highlight.id}
              variant="light"
              onPress={() => handleHighlightClick(highlight)}
              className="flex flex-col items-center gap-1.5 flex-shrink-0 w-18 group relative h-auto min-w-0 p-0"
              aria-label={`View highlight: ${highlight.title}`}
            >
              <div className="w-16 h-16 rounded-full p-[2px] bg-[var(--border-default)] group-hover:bg-gradient-to-tr group-hover:from-yellow-400 group-hover:via-red-500 group-hover:to-purple-600 transition-all">
                <div className="w-full h-full rounded-full bg-[var(--surface-elevated)] p-[2px]">
                  {highlight.cover_url ? (
                    <img
                      src={resolveAssetUrl(highlight.cover_url)}
                      alt={highlight.title}
                      className="w-full h-full rounded-full object-cover"
                      loading="lazy"
                    />
                  ) : (
                    <div className="w-full h-full rounded-full bg-[var(--surface-elevated)] flex items-center justify-center">
                      <span className="text-lg text-[var(--text-muted)]">
                        {highlight.title.charAt(0).toUpperCase()}
                      </span>
                    </div>
                  )}
                </div>
              </div>
              <span className="text-xs text-[var(--text-primary)] text-center truncate w-full">
                {highlight.title}
              </span>

              {/* Owner action buttons */}
              {isOwner && (
                <>
                  {/* Edit button */}
                  <Button
                    isIconOnly
                    variant="flat"
                    className="absolute -top-1 -left-1 w-5 h-5 rounded-full bg-[var(--color-primary)] text-white opacity-0 group-hover:opacity-100 transition-opacity min-w-0 p-0"
                    onClick={(e) => { e.stopPropagation(); handleEditClick(highlight, e); }}
                    aria-label={`Edit highlight: ${highlight.title}`}
                  >
                    <Pencil className="w-3 h-3" />
                  </Button>
                  {/* Delete button */}
                  <Button
                    isIconOnly
                    variant="flat"
                    className="absolute -top-1 -right-1 w-5 h-5 rounded-full bg-red-500 text-white opacity-0 group-hover:opacity-100 transition-opacity min-w-0 p-0"
                    onClick={(e) => { e.stopPropagation(); handleDeleteHighlight(highlight.id, e); }}
                    aria-label={`Delete highlight: ${highlight.title}`}
                  >
                    <X className="w-3 h-3" />
                  </Button>
                </>
              )}
            </Button>
          ))}
        </div>
      </div>

      {/* Create Highlight Modal */}
      <Modal isOpen={isCreateOpen} onClose={onCreateClose} size="sm">
        <ModalContent>
          <ModalHeader>{t('highlights.create_title', 'Create Highlight')}</ModalHeader>
          <ModalBody>
            <Input
              value={newTitle}
              onValueChange={setNewTitle}
              label={t('highlights.title_label', 'Highlight Title')}
              placeholder={t('highlights.title_placeholder', 'e.g., Travel, Food, Events...')}
              variant="bordered"
              maxLength={100}
              autoFocus
            />
            <p className="text-xs text-[var(--text-muted)]">
              {t('highlights.create_hint', 'You can add stories to this highlight later from the story viewer.')}
            </p>
          </ModalBody>
          <ModalFooter>
            <Button variant="light" onPress={onCreateClose}>
              {t('highlights.cancel', 'Cancel')}
            </Button>
            <Button
              color="primary"
              onPress={handleCreateHighlight}
              isLoading={isCreating}
              isDisabled={!newTitle.trim()}
            >
              {t('highlights.create', 'Create')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Edit Highlight Modal */}
      <Modal isOpen={isEditOpen} onClose={handleEditClose} size="md">
        <ModalContent>
          <ModalHeader>{t('highlights.edit_title', 'Edit Highlight')}</ModalHeader>
          <ModalBody className="gap-4">
            {/* Title editing */}
            <div className="flex gap-2 items-end">
              <Input
                value={editTitle}
                onValueChange={setEditTitle}
                label={t('highlights.title_label', 'Highlight Title')}
                variant="bordered"
                maxLength={100}
                className="flex-1"
              />
              <Button
                color="primary"
                size="sm"
                onPress={handleSaveTitle}
                isLoading={isSavingTitle}
                isDisabled={!editTitle.trim() || editTitle.trim() === editingHighlight?.title}
              >
                Save
              </Button>
            </div>

            {/* Stories list */}
            <div>
              <p className="text-sm font-medium text-[var(--text-primary)] mb-2">
                Stories in this highlight
              </p>
              {isEditLoading ? (
                <div className="flex flex-col gap-2">
                  {Array.from({ length: 3 }).map((_, i) => (
                    <Skeleton key={i} className="h-14 w-full rounded-lg" />
                  ))}
                </div>
              ) : editStories.length === 0 ? (
                <p className="text-sm text-[var(--text-muted)] py-4 text-center">
                  No stories in this highlight yet.
                </p>
              ) : (
                <div className="flex flex-col gap-2 max-h-64 overflow-y-auto">
                  {editStories.map((story) => (
                    <div
                      key={story.id}
                      className="flex items-center gap-3 p-2 rounded-lg bg-[var(--surface-default)] border border-[var(--border-default)]"
                    >
                      {/* Story preview thumbnail */}
                      <div className="w-10 h-10 rounded-lg overflow-hidden flex-shrink-0 bg-[var(--surface-elevated)]">
                        {story.media_url ? (
                          <img
                            src={resolveAssetUrl(story.media_url)}
                            alt="Story"
                            className="w-full h-full object-cover"
                          />
                        ) : (
                          <div
                            className="w-full h-full flex items-center justify-center text-xs"
                            style={{
                              background: story.background_gradient || story.background_color || 'var(--surface-elevated)',
                            }}
                          >
                            {story.media_type === 'text' ? 'Aa' : story.media_type === 'poll' ? '?' : ''}
                          </div>
                        )}
                      </div>

                      {/* Story info */}
                      <div className="flex-1 min-w-0">
                        <p className="text-sm text-[var(--text-primary)] truncate">
                          {story.text_content
                            ? story.text_content.substring(0, 40) + (story.text_content.length > 40 ? '...' : '')
                            : story.poll_question
                              ? story.poll_question.substring(0, 40)
                              : `${story.media_type.charAt(0).toUpperCase()}${story.media_type.slice(1)} story`}
                        </p>
                        <p className="text-xs text-[var(--text-muted)]">
                          {new Date(story.created_at).toLocaleDateString()}
                        </p>
                      </div>

                      {/* Remove button */}
                      <Button
                        size="sm"
                        variant="light"
                        color="danger"
                        isIconOnly
                        isLoading={removingStoryId === story.id}
                        onPress={() => handleRemoveStory(story.id)}
                        aria-label={t('highlights.aria_remove_story', 'Remove story from highlight')}
                      >
                        <Trash2 className="w-4 h-4" />
                      </Button>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="light" onPress={handleEditClose}>
              Done
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Highlight Viewer */}
      {viewerOpen && viewerStories.length > 0 && (
        <StoryViewer
          storyUsers={viewerStories}
          initialUserIndex={0}
          onClose={() => setViewerOpen(false)}
        />
      )}
    </>
  );
}

export default StoryHighlights;
