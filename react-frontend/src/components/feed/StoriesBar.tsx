// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * StoriesBar — Horizontal scrollable story circles at top of feed.
 *
 * Shows users with active 24-hour stories. First item is "Your Story" (create).
 * Unseen stories have a gradient ring; seen stories have a gray ring.
 * Clicking opens the StoryViewer overlay.
 */

import { useState, useEffect, useRef, useCallback } from 'react';
import { Avatar, Skeleton, Button } from '@heroui/react';
import Plus from 'lucide-react/icons/plus';
import ChevronLeft from 'lucide-react/icons/chevron-left';
import ChevronRight from 'lucide-react/icons/chevron-right';
import { useTranslation } from 'react-i18next';
import { useAuth } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl } from '@/lib/helpers';
import { StoryViewer } from '@/components/stories/StoryViewer';
import { StoryCreator } from '@/components/stories/StoryCreator';

export interface StoryUser {
  user_id: number;
  name: string;
  first_name: string;
  avatar_url?: string | null;
  story_count: number;
  has_unseen: boolean;
  is_own: boolean;
  is_connected: boolean;
  latest_at: string;
}

interface StoriesBarProps {
  /** Legacy friends prop — ignored when API stories are loaded */
  friends?: Array<{ id: number; name: string; avatar_url?: string; is_online?: boolean }>;
}

const SCROLL_DISTANCE = 200;
const MAX_STORY_NAME_LENGTH = 12;

export function StoriesBar({ friends: _friends }: StoriesBarProps) {
  const { t } = useTranslation('feed');
  const { user, isAuthenticated } = useAuth();
  const [storyUsers, setStoryUsers] = useState<StoryUser[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [viewerOpen, setViewerOpen] = useState(false);
  const [viewerStartIndex, setViewerStartIndex] = useState(0);
  const [creatorOpen, setCreatorOpen] = useState(false);
  const [showLeftArrow, setShowLeftArrow] = useState(false);
  const [showRightArrow, setShowRightArrow] = useState(false);
  const scrollRef = useRef<HTMLDivElement>(null);

  const loadStories = useCallback(async () => {
    if (!isAuthenticated) return;
    try {
      setIsLoading(true);
      const response = await api.get<StoryUser[]>('/v2/stories');
      if (response.success && response.data) {
        setStoryUsers(Array.isArray(response.data) ? response.data : []);
      }
    } catch (err) {
      logError('Failed to load stories', err);
    } finally {
      setIsLoading(false);
    }
  }, [isAuthenticated]);

  const loadStoriesRef = useRef(loadStories);
  useEffect(() => { loadStoriesRef.current = loadStories; }, [loadStories]);

  useEffect(() => {
    loadStoriesRef.current();
  }, []); // eslint-disable-line react-hooks/exhaustive-deps -- run only on mount; isAuthenticated changes handled by ref update

  // Scroll arrow visibility
  const updateArrows = useCallback(() => {
    const el = scrollRef.current;
    if (!el) return;
    setShowLeftArrow(el.scrollLeft > 10);
    setShowRightArrow(el.scrollLeft + el.clientWidth < el.scrollWidth - 10);
  }, []);

  useEffect(() => {
    const el = scrollRef.current;
    if (!el) return;
    updateArrows();
    el.addEventListener('scroll', updateArrows, { passive: true });
    window.addEventListener('resize', updateArrows);
    return () => {
      el.removeEventListener('scroll', updateArrows);
      window.removeEventListener('resize', updateArrows);
    };
  }, [updateArrows]);

  const scroll = useCallback((direction: 'left' | 'right') => {
    const el = scrollRef.current;
    if (!el) return;
    const amount = direction === 'left' ? -SCROLL_DISTANCE : SCROLL_DISTANCE;
    el.scrollBy({ left: amount, behavior: 'smooth' });
  }, []);

  const handleStoryClick = (index: number) => {
    setViewerStartIndex(index);
    setViewerOpen(true);
  };

  const handleCreateClick = () => {
    setCreatorOpen(true);
  };

  const handleStoryCreated = () => {
    setCreatorOpen(false);
    loadStories();
  };

  const handleViewerClose = () => {
    setViewerOpen(false);
    // Refresh to update seen/unseen state
    loadStories();
  };

  const truncateName = (name: string, max = MAX_STORY_NAME_LENGTH): string =>
    name.length > max ? `${name.slice(0, max)}...` : name;

  if (!isAuthenticated) return null;

  // Show skeleton while loading
  if (isLoading) {
    return (
      <div className="w-full overflow-hidden">
        <div className="flex items-start gap-3 px-1 py-2">
          {Array.from({ length: 6 }).map((_, i) => (
            <div key={i} className="flex flex-col items-center gap-1.5 flex-shrink-0 w-16">
              <Skeleton className="w-14 h-14 rounded-full" />
              <Skeleton className="h-3 w-10 rounded" />
            </div>
          ))}
        </div>
      </div>
    );
  }

  // Determine if current user has their own story in the list
  const hasOwnStory = storyUsers.some((su) => su.is_own);

  return (
    <>
      <div className="relative w-full group">
        {/* Left scroll arrow */}
        {showLeftArrow && (
          <Button
            isIconOnly
            variant="flat"
            size="sm"
            onPress={() => scroll('left')}
            className="absolute left-0 top-1/2 -translate-y-1/2 z-10 w-8 h-8 rounded-full bg-[var(--surface-elevated)] shadow-md opacity-0 group-hover:opacity-100 transition-opacity border border-[var(--border-default)]"
            aria-label={t('stories.scroll_left')}
          >
            <ChevronLeft className="w-4 h-4 text-[var(--text-primary)]" />
          </Button>
        )}

        {/* Right scroll arrow */}
        {showRightArrow && (
          <Button
            isIconOnly
            variant="flat"
            size="sm"
            onPress={() => scroll('right')}
            className="absolute right-0 top-1/2 -translate-y-1/2 z-10 w-8 h-8 rounded-full bg-[var(--surface-elevated)] shadow-md opacity-0 group-hover:opacity-100 transition-opacity border border-[var(--border-default)]"
            aria-label={t('stories.scroll_right')}
          >
            <ChevronRight className="w-4 h-4 text-[var(--text-primary)]" />
          </Button>
        )}

        <div
          ref={scrollRef}
          className="w-full overflow-x-auto scrollbar-hide"
        >
          <div className="flex items-start gap-3 px-1 py-2 min-w-min">
            {/* Your Story — create button */}
            <Button
              variant="light"
              onPress={handleCreateClick}
              className="flex flex-col items-center gap-1.5 flex-shrink-0 w-16 h-auto group/create p-0 min-w-0"
              aria-label={t('stories.create_your_story')}
            >
              <div className="relative">
                <div className={`w-14 h-14 rounded-full p-[2px] ${
                  hasOwnStory
                    ? 'bg-gradient-to-tr from-yellow-400 via-red-500 to-purple-600'
                    : 'bg-[var(--border-default)]'
                }`}>
                  <div className="w-full h-full rounded-full bg-[var(--surface-elevated)] p-[2px]">
                    <Avatar
                      src={resolveAvatarUrl(user?.avatar ?? null)}
                      name={user?.first_name || 'You'}
                      className="w-full h-full"
                      size="md"
                    />
                  </div>
                </div>
                {/* Plus badge */}
                <span className="absolute -bottom-0.5 -right-0.5 w-5 h-5 rounded-full bg-blue-500 border-2 border-[var(--surface-elevated)] flex items-center justify-center">
                  <Plus className="w-3 h-3 text-white" />
                </span>
              </div>
              <span className="text-xs truncate w-full text-center text-[var(--text-primary)]">
                {t('stories.your_story')}
              </span>
            </Button>

            {/* Other users' stories */}
            {storyUsers.filter((su) => !su.is_own).map((storyUser) => {
              // idx+1 to account for "your story" being index 0 in the viewer
              // but actually we want the index into storyUsers array
              const actualIndex = storyUsers.findIndex(
                (su) => su.user_id === storyUser.user_id
              );

              return (
                <Button
                  key={storyUser.user_id}
                  variant="light"
                  onPress={() => handleStoryClick(actualIndex)}
                  className="flex flex-col items-center gap-1.5 flex-shrink-0 w-16 h-auto p-0 min-w-0"
                  aria-label={t('stories.view_story_from', { name: storyUser.name })}
                >
                  <div className="relative">
                    {/* Ring: gradient for unseen, gray for seen */}
                    <div className={`w-14 h-14 rounded-full p-[2px] ${
                      storyUser.has_unseen
                        ? 'bg-gradient-to-tr from-yellow-400 via-red-500 to-purple-600'
                        : 'bg-gray-300 dark:bg-gray-600'
                    }`}>
                      <div className="w-full h-full rounded-full bg-[var(--surface-elevated)] p-[2px]">
                        <Avatar
                          src={resolveAvatarUrl(storyUser.avatar_url ?? null)}
                          name={storyUser.name}
                          className="w-full h-full"
                          size="md"
                        />
                      </div>
                    </div>
                  </div>
                  <span className="text-xs truncate w-full text-center text-[var(--text-primary)]">
                    {truncateName((storyUser.first_name || storyUser.name || '').split(' ')[0] ?? '')}
                  </span>
                </Button>
              );
            })}
          </div>
        </div>
      </div>

      {/* Story Viewer Overlay */}
      {viewerOpen && storyUsers.length > 0 && (
        <StoryViewer
          storyUsers={storyUsers}
          initialUserIndex={viewerStartIndex}
          onClose={handleViewerClose}
        />
      )}

      {/* Story Creator Overlay */}
      {creatorOpen && (
        <StoryCreator
          onClose={() => setCreatorOpen(false)}
          onCreated={handleStoryCreated}
        />
      )}
    </>
  );
}

export default StoriesBar;
