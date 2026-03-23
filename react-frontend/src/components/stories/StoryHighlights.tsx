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
import { Plus, X } from 'lucide-react';
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
  const toast = useToast();
  const [highlights, setHighlights] = useState<Highlight[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [viewerOpen, setViewerOpen] = useState(false);
  const [viewerStories, setViewerStories] = useState<StoryUser[]>([]);
  const { isOpen: isCreateOpen, onOpen: onCreateOpen, onClose: onCreateClose } = useDisclosure();
  const [newTitle, setNewTitle] = useState('');
  const [isCreating, setIsCreating] = useState(false);

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
          id: userId,
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
            <button
              onClick={onCreateOpen}
              className="flex flex-col items-center gap-1.5 flex-shrink-0 w-18 group"
              aria-label="Create new highlight"
            >
              <div className="w-16 h-16 rounded-full border-2 border-dashed border-[var(--border-default)] flex items-center justify-center group-hover:border-[var(--color-primary)] transition-colors">
                <Plus className="w-6 h-6 text-[var(--text-muted)] group-hover:text-[var(--color-primary)] transition-colors" />
              </div>
              <span className="text-xs text-[var(--text-muted)] text-center">New</span>
            </button>
          )}

          {/* Highlight circles */}
          {highlights.map((highlight) => (
            <button
              key={highlight.id}
              onClick={() => handleHighlightClick(highlight)}
              className="flex flex-col items-center gap-1.5 flex-shrink-0 w-18 group relative"
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

              {/* Delete button for owner */}
              {isOwner && (
                <button
                  onClick={(e) => handleDeleteHighlight(highlight.id, e)}
                  className="absolute -top-1 -right-1 w-5 h-5 rounded-full bg-red-500 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"
                  aria-label={`Delete highlight: ${highlight.title}`}
                >
                  <X className="w-3 h-3" />
                </button>
              )}
            </button>
          ))}
        </div>
      </div>

      {/* Create Highlight Modal */}
      <Modal isOpen={isCreateOpen} onClose={onCreateClose} size="sm">
        <ModalContent>
          <ModalHeader>Create Highlight</ModalHeader>
          <ModalBody>
            <Input
              value={newTitle}
              onValueChange={setNewTitle}
              label="Highlight Title"
              placeholder="e.g., Travel, Food, Events..."
              variant="bordered"
              maxLength={100}
              autoFocus
            />
            <p className="text-xs text-[var(--text-muted)]">
              You can add stories to this highlight later from the story viewer.
            </p>
          </ModalBody>
          <ModalFooter>
            <Button variant="light" onPress={onCreateClose}>
              Cancel
            </Button>
            <Button
              color="primary"
              onPress={handleCreateHighlight}
              isLoading={isCreating}
              isDisabled={!newTitle.trim()}
            >
              Create
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
