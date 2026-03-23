// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * StoryViewer — Fullscreen overlay story viewer (Instagram-style).
 *
 * Features:
 * - Progress bars at top (one per story, auto-advance)
 * - Tap left/right to navigate, hold to pause
 * - Swipe between users, keyboard nav (left/right/Escape)
 * - Quick reactions, view count (owner only), poll voting
 * - Preloads next user's story images
 */

import { useState, useEffect, useRef, useCallback } from 'react';
import { createPortal } from 'react-dom';
import { motion, AnimatePresence } from 'framer-motion';
import { Avatar } from '@heroui/react';
import {
  X,
  Eye,
  MoreHorizontal,
  Trash2,
  Play,
  ChevronLeft,
  ChevronRight,
} from 'lucide-react';
import { useAuth } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl, resolveAssetUrl } from '@/lib/helpers';
import type { StoryUser } from '@/components/feed/StoriesBar';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface Story {
  id: number;
  user_id: number;
  media_type: 'image' | 'text' | 'poll';
  media_url?: string | null;
  thumbnail_url?: string | null;
  text_content?: string | null;
  text_style?: { fontFamily?: string; fontSize?: string } | null;
  background_color?: string | null;
  background_gradient?: string | null;
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

interface StoryViewerProps {
  storyUsers: StoryUser[];
  initialUserIndex: number;
  onClose: () => void;
}

interface StoryViewerListItem {
  id: number;
  name: string;
  avatar_url?: string | null;
  viewed_at: string;
}

// ─────────────────────────────────────────────────────────────────────────────
// Reaction emojis
// ─────────────────────────────────────────────────────────────────────────────

const REACTIONS = [
  { emoji: '\u2764\uFE0F', type: 'heart' },
  { emoji: '\uD83D\uDE02', type: 'laugh' },
  { emoji: '\uD83D\uDE2E', type: 'wow' },
  { emoji: '\uD83D\uDD25', type: 'fire' },
  { emoji: '\uD83D\uDC4F', type: 'clap' },
  { emoji: '\uD83D\uDE22', type: 'sad' },
] as const;

// ─────────────────────────────────────────────────────────────────────────────
// Gradient presets for text stories
// ─────────────────────────────────────────────────────────────────────────────

const GRADIENT_MAP: Record<string, string> = {
  'from-purple-600 to-blue-500': 'linear-gradient(135deg, #9333ea, #3b82f6)',
  'from-orange-500 to-pink-500': 'linear-gradient(135deg, #f97316, #ec4899)',
  'from-green-500 to-teal-500': 'linear-gradient(135deg, #22c55e, #14b8a6)',
  'from-blue-600 to-indigo-500': 'linear-gradient(135deg, #2563eb, #6366f1)',
  'from-red-500 to-yellow-500': 'linear-gradient(135deg, #ef4444, #eab308)',
  'from-gray-700 to-gray-900': 'linear-gradient(135deg, #374151, #111827)',
  'from-pink-500 to-rose-400': 'linear-gradient(135deg, #ec4899, #fb7185)',
  'from-cyan-500 to-blue-500': 'linear-gradient(135deg, #06b6d4, #3b82f6)',
};

function resolveGradient(gradient: string | null | undefined): string {
  if (!gradient) return 'linear-gradient(135deg, #374151, #111827)';
  return GRADIENT_MAP[gradient] || `linear-gradient(135deg, ${gradient})`;
}

// ─────────────────────────────────────────────────────────────────────────────
// Time formatting
// ─────────────────────────────────────────────────────────────────────────────

function timeAgo(dateStr: string): string {
  const diff = Date.now() - new Date(dateStr).getTime();
  const mins = Math.floor(diff / 60000);
  if (mins < 1) return 'Just now';
  if (mins < 60) return `${mins}m ago`;
  const hours = Math.floor(mins / 60);
  if (hours < 24) return `${hours}h ago`;
  return `${Math.floor(hours / 24)}d ago`;
}

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function StoryViewer({ storyUsers, initialUserIndex, onClose }: StoryViewerProps) {
  const { user: currentUser } = useAuth();
  const [currentUserIdx, setCurrentUserIdx] = useState(initialUserIndex);
  const [currentStoryIdx, setCurrentStoryIdx] = useState(0);
  const [stories, setStories] = useState<Story[]>([]);
  const [isLoadingStories, setIsLoadingStories] = useState(true);
  const [isPaused, setIsPaused] = useState(false);
  const [progress, setProgress] = useState(0);
  const [showViewers, setShowViewers] = useState(false);
  const [viewers, setViewers] = useState<StoryViewerListItem[]>([]);
  const [showMenu, setShowMenu] = useState(false);
  const [hasVoted, setHasVoted] = useState(false);
  const [pollResults, setPollResults] = useState<{ votes: Record<number, number>; total_votes: number } | null>(null);
  const [reactedWith, setReactedWith] = useState<string | null>(null);

  const progressTimerRef = useRef<number | null>(null);
  const progressStartRef = useRef(0);
  const holdTimerRef = useRef<number | null>(null);
  const preloadedImages = useRef<Set<string>>(new Set());

  const currentUserStory = storyUsers[currentUserIdx];
  const currentStory = stories[currentStoryIdx];
  const isOwner = currentUserStory && currentUser && currentUserStory.user_id === currentUser.id;

  // Load stories for the current user
  const loadUserStories = useCallback(async (userId: number) => {
    setIsLoadingStories(true);
    setCurrentStoryIdx(0);
    setProgress(0);
    setHasVoted(false);
    setPollResults(null);
    setReactedWith(null);

    try {
      const response = await api.get<Story[]>(`/v2/stories/user/${userId}`);
      if (response.success && response.data) {
        const storyList = Array.isArray(response.data) ? response.data : [];
        setStories(storyList);

        // Find first unseen story
        const firstUnseenIdx = storyList.findIndex((s) => !s.is_viewed);
        if (firstUnseenIdx > 0) {
          setCurrentStoryIdx(firstUnseenIdx);
        }
      } else {
        setStories([]);
      }
    } catch (err) {
      logError('Failed to load user stories', err);
      setStories([]);
    } finally {
      setIsLoadingStories(false);
    }
  }, []);

  // Load stories when user changes
  useEffect(() => {
    if (currentUserStory && currentUserStory.user_id != null) {
      loadUserStories(currentUserStory.user_id);
    }
  }, [currentUserIdx, currentUserStory, loadUserStories]);

  // Mark story as viewed
  useEffect(() => {
    if (currentStory && !isOwner) {
      api.post(`/v2/stories/${currentStory.id}/view`).catch(() => {});
    }
  }, [currentStory, isOwner]);

  // Preload next user's stories
  useEffect(() => {
    if (currentUserIdx < storyUsers.length - 1) {
      const nextUser = storyUsers[currentUserIdx + 1];
      // Preload avatar
      if (nextUser.avatar_url) {
        const img = new Image();
        img.src = resolveAvatarUrl(nextUser.avatar_url);
      }
    }
  }, [currentUserIdx, storyUsers]);

  // Preload next story image
  useEffect(() => {
    if (currentStoryIdx < stories.length - 1) {
      const nextStory = stories[currentStoryIdx + 1];
      if (nextStory?.media_url && !preloadedImages.current.has(nextStory.media_url)) {
        const img = new Image();
        img.src = resolveAssetUrl(nextStory.media_url);
        preloadedImages.current.add(nextStory.media_url);
      }
    }
  }, [currentStoryIdx, stories]);

  // Progress timer
  useEffect(() => {
    if (!currentStory || isPaused || isLoadingStories) return;

    // For poll stories, pause until voted
    if (currentStory.media_type === 'poll' && !hasVoted) return;

    const duration = currentStory.duration * 1000;
    const startTime = Date.now() - (progress * duration);
    progressStartRef.current = startTime;

    const animate = () => {
      const elapsed = Date.now() - progressStartRef.current;
      const pct = Math.min(elapsed / duration, 1);
      setProgress(pct);

      if (pct < 1) {
        progressTimerRef.current = requestAnimationFrame(animate);
      } else {
        // Auto-advance
        goNext();
      }
    };

    progressTimerRef.current = requestAnimationFrame(animate);

    return () => {
      if (progressTimerRef.current) {
        cancelAnimationFrame(progressTimerRef.current);
      }
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [currentStory, isPaused, isLoadingStories, hasVoted]);

  // Navigation
  const goNext = useCallback(() => {
    if (currentStoryIdx < stories.length - 1) {
      setCurrentStoryIdx((prev) => prev + 1);
      setProgress(0);
      setHasVoted(false);
      setPollResults(null);
      setReactedWith(null);
    } else if (currentUserIdx < storyUsers.length - 1) {
      setCurrentUserIdx((prev) => prev + 1);
    } else {
      onClose();
    }
  }, [currentStoryIdx, stories.length, currentUserIdx, storyUsers.length, onClose]);

  const goPrev = useCallback(() => {
    if (currentStoryIdx > 0) {
      setCurrentStoryIdx((prev) => prev - 1);
      setProgress(0);
      setHasVoted(false);
      setPollResults(null);
      setReactedWith(null);
    } else if (currentUserIdx > 0) {
      setCurrentUserIdx((prev) => prev - 1);
    }
  }, [currentStoryIdx, currentUserIdx]);

  // Keyboard navigation
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      switch (e.key) {
        case 'Escape':
          onClose();
          break;
        case 'ArrowRight':
          goNext();
          break;
        case 'ArrowLeft':
          goPrev();
          break;
        case ' ':
          e.preventDefault();
          setIsPaused((prev) => !prev);
          break;
      }
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [onClose, goNext, goPrev]);

  // Touch hold to pause
  const handlePointerDown = () => {
    holdTimerRef.current = window.setTimeout(() => {
      setIsPaused(true);
    }, 200);
  };

  const handlePointerUp = (side: 'left' | 'right') => {
    if (holdTimerRef.current) {
      clearTimeout(holdTimerRef.current);
      holdTimerRef.current = null;
    }

    if (isPaused) {
      setIsPaused(false);
      return;
    }

    if (side === 'left') {
      goPrev();
    } else {
      goNext();
    }
  };

  // Reactions
  const handleReaction = async (reactionType: string) => {
    if (!currentStory || reactedWith) return;
    setReactedWith(reactionType);
    try {
      await api.post(`/v2/stories/${currentStory.id}/react`, { reaction_type: reactionType });
    } catch (err) {
      logError('Failed to react to story', err);
      setReactedWith(null);
    }
  };

  // Poll voting
  const handlePollVote = async (optionIndex: number) => {
    if (!currentStory || hasVoted) return;
    setHasVoted(true);
    try {
      const response = await api.post<{ votes: Record<number, number>; total_votes: number }>(
        `/v2/stories/${currentStory.id}/poll/vote`,
        { option_index: optionIndex }
      );
      if (response.success && response.data) {
        setPollResults(response.data);
      }
    } catch (err) {
      logError('Failed to vote on poll', err);
      // Still show as voted if we got an "already voted" error
    }
  };

  // View viewers list
  const handleViewViewers = async () => {
    if (!currentStory || !isOwner) return;
    try {
      const response = await api.get<StoryViewerListItem[]>(`/v2/stories/${currentStory.id}/viewers`);
      if (response.success && response.data) {
        setViewers(Array.isArray(response.data) ? response.data : []);
        setShowViewers(true);
        setIsPaused(true);
      }
    } catch (err) {
      logError('Failed to load viewers', err);
    }
  };

  // Delete story
  const handleDeleteStory = async () => {
    if (!currentStory || !isOwner) return;
    try {
      await api.delete(`/v2/stories/${currentStory.id}`);
      setShowMenu(false);
      goNext();
    } catch (err) {
      logError('Failed to delete story', err);
    }
  };

  if (!currentUserStory) return null;

  const storyContent = (
    <div
      className="fixed inset-0 z-[9999] bg-black flex items-center justify-center"
      role="dialog"
      aria-modal="true"
      aria-label={`Story from ${currentUserStory.name}`}
    >
      {/* Desktop: previous user arrow */}
      {currentUserIdx > 0 && (
        <button
          className="absolute left-4 top-1/2 -translate-y-1/2 z-50 w-10 h-10 rounded-full bg-white/10 backdrop-blur flex items-center justify-center hover:bg-white/20 transition-colors hidden md:flex"
          onClick={() => setCurrentUserIdx((prev) => prev - 1)}
          aria-label="Previous user"
        >
          <ChevronLeft className="w-5 h-5 text-white" />
        </button>
      )}

      {/* Desktop: next user arrow */}
      {currentUserIdx < storyUsers.length - 1 && (
        <button
          className="absolute right-4 top-1/2 -translate-y-1/2 z-50 w-10 h-10 rounded-full bg-white/10 backdrop-blur flex items-center justify-center hover:bg-white/20 transition-colors hidden md:flex"
          onClick={() => setCurrentUserIdx((prev) => prev + 1)}
          aria-label="Next user"
        >
          <ChevronRight className="w-5 h-5 text-white" />
        </button>
      )}

      {/* Story container — 9:16 aspect on desktop, full on mobile */}
      <div className="relative w-full h-full md:w-[min(420px,90vw)] md:h-[min(750px,90vh)] md:rounded-2xl overflow-hidden">
        <AnimatePresence mode="wait">
          <motion.div
            key={`${currentUserIdx}-${currentStoryIdx}`}
            initial={{ opacity: 0, scale: 0.95 }}
            animate={{ opacity: 1, scale: 1 }}
            exit={{ opacity: 0, scale: 0.95 }}
            transition={{ duration: 0.2 }}
            className="absolute inset-0"
          >
            {/* Story background/content */}
            {isLoadingStories ? (
              <div className="w-full h-full bg-gray-900 flex items-center justify-center">
                <div className="w-8 h-8 border-2 border-white/30 border-t-white rounded-full animate-spin" />
              </div>
            ) : currentStory ? (
              <>
                {/* Image story */}
                {currentStory.media_type === 'image' && currentStory.media_url && (
                  <div className="w-full h-full bg-black">
                    <img
                      src={resolveAssetUrl(currentStory.media_url)}
                      alt={`Story from ${currentUserStory.name}`}
                      className="w-full h-full object-cover"
                      loading="eager"
                    />
                    {/* Text overlay */}
                    {currentStory.text_content && (
                      <div className="absolute inset-0 flex items-center justify-center p-8">
                        <p className="text-white text-xl font-semibold text-center drop-shadow-lg max-w-sm">
                          {currentStory.text_content}
                        </p>
                      </div>
                    )}
                  </div>
                )}

                {/* Text story */}
                {currentStory.media_type === 'text' && (
                  <div
                    className="w-full h-full flex items-center justify-center p-8"
                    style={{
                      background: resolveGradient(currentStory.background_gradient),
                      backgroundColor: currentStory.background_color || '#1f2937',
                    }}
                  >
                    <p
                      className="text-white text-center max-w-sm drop-shadow-lg"
                      style={{
                        fontSize: currentStory.text_style?.fontSize || '1.5rem',
                        fontFamily: currentStory.text_style?.fontFamily || 'sans-serif',
                        lineHeight: 1.4,
                      }}
                    >
                      {currentStory.text_content}
                    </p>
                  </div>
                )}

                {/* Poll story */}
                {currentStory.media_type === 'poll' && (
                  <div
                    className="w-full h-full flex flex-col items-center justify-center p-8 gap-6"
                    style={{
                      background: resolveGradient(currentStory.background_gradient),
                      backgroundColor: currentStory.background_color || '#1f2937',
                    }}
                  >
                    <h3 className="text-white text-xl font-bold text-center drop-shadow-lg max-w-sm">
                      {currentStory.poll_question}
                    </h3>
                    <div className="w-full max-w-sm flex flex-col gap-3">
                      {(currentStory.poll_options || []).map((option, idx) => {
                        const results = pollResults || currentStory.poll_results;
                        const totalVotes = results?.total_votes || 0;
                        const thisVotes = results?.votes?.[idx] || 0;
                        const pct = totalVotes > 0 ? Math.round((thisVotes / totalVotes) * 100) : 0;

                        return (
                          <button
                            key={idx}
                            onClick={() => !hasVoted && handlePollVote(idx)}
                            disabled={hasVoted}
                            className={`relative w-full px-4 py-3 rounded-xl text-left transition-all overflow-hidden ${
                              hasVoted
                                ? 'bg-white/20 cursor-default'
                                : 'bg-white/30 hover:bg-white/40 cursor-pointer active:scale-[0.98]'
                            }`}
                            aria-label={`Vote for ${option}`}
                          >
                            {/* Results bar */}
                            {hasVoted && (
                              <motion.div
                                initial={{ width: 0 }}
                                animate={{ width: `${pct}%` }}
                                transition={{ duration: 0.5, ease: 'easeOut' }}
                                className="absolute inset-y-0 left-0 bg-white/20 rounded-xl"
                              />
                            )}
                            <span className="relative z-10 text-white font-medium text-sm">
                              {option}
                            </span>
                            {hasVoted && (
                              <span className="relative z-10 float-right text-white/80 text-sm font-medium">
                                {pct}%
                              </span>
                            )}
                          </button>
                        );
                      })}
                      {hasVoted && (
                        <p className="text-white/60 text-xs text-center mt-1">
                          {(pollResults || currentStory.poll_results)?.total_votes || 0} votes
                        </p>
                      )}
                    </div>
                  </div>
                )}
              </>
            ) : (
              <div className="w-full h-full bg-gray-900 flex items-center justify-center">
                <p className="text-white/50 text-sm">No stories available</p>
              </div>
            )}

            {/* Tap zones */}
            <div className="absolute inset-0 flex z-10">
              <button
                className="w-1/3 h-full cursor-pointer"
                onPointerDown={handlePointerDown}
                onPointerUp={() => handlePointerUp('left')}
                onPointerCancel={() => {
                  if (holdTimerRef.current) clearTimeout(holdTimerRef.current);
                  setIsPaused(false);
                }}
                aria-label="Previous story"
              />
              <div className="w-1/3 h-full" />
              <button
                className="w-1/3 h-full cursor-pointer"
                onPointerDown={handlePointerDown}
                onPointerUp={() => handlePointerUp('right')}
                onPointerCancel={() => {
                  if (holdTimerRef.current) clearTimeout(holdTimerRef.current);
                  setIsPaused(false);
                }}
                aria-label="Next story"
              />
            </div>

            {/* Progress bars */}
            {stories.length > 0 && (
              <div className="absolute top-0 left-0 right-0 z-20 flex gap-1 px-2 pt-2">
                {stories.map((_, idx) => (
                  <div
                    key={idx}
                    className="flex-1 h-0.5 rounded-full bg-white/30 overflow-hidden"
                    role="progressbar"
                    aria-label={`Story ${idx + 1} of ${stories.length}`}
                    aria-valuenow={idx === currentStoryIdx ? Math.round(progress * 100) : idx < currentStoryIdx ? 100 : 0}
                    aria-valuemin={0}
                    aria-valuemax={100}
                  >
                    <div
                      className="h-full bg-white rounded-full transition-none"
                      style={{
                        width:
                          idx < currentStoryIdx
                            ? '100%'
                            : idx === currentStoryIdx
                              ? `${progress * 100}%`
                              : '0%',
                      }}
                    />
                  </div>
                ))}
              </div>
            )}

            {/* Header: user info + close */}
            <div className="absolute top-4 left-0 right-0 z-30 flex items-center gap-3 px-4 pt-2">
              <Avatar
                src={resolveAvatarUrl(currentUserStory.avatar_url ?? null)}
                name={currentUserStory.name}
                size="sm"
                className="w-8 h-8 flex-shrink-0 ring-1 ring-white/30"
              />
              <div className="flex-1 min-w-0">
                <p className="text-white text-sm font-semibold truncate">
                  {currentUserStory.name}
                </p>
                {currentStory && (
                  <p className="text-white/60 text-xs">
                    {timeAgo(currentStory.created_at)}
                  </p>
                )}
              </div>

              {/* Pause indicator */}
              {isPaused && (
                <button
                  onClick={() => setIsPaused(false)}
                  className="p-1.5 rounded-full hover:bg-white/10 transition-colors"
                  aria-label="Resume story"
                >
                  <Play className="w-4 h-4 text-white" />
                </button>
              )}

              {/* More menu */}
              {isOwner && (
                <div className="relative">
                  <button
                    onClick={() => { setShowMenu(!showMenu); setIsPaused(true); }}
                    className="p-1.5 rounded-full hover:bg-white/10 transition-colors"
                    aria-label="Story options"
                  >
                    <MoreHorizontal className="w-4 h-4 text-white" />
                  </button>
                  {showMenu && (
                    <div className="absolute right-0 top-8 bg-gray-800 rounded-lg shadow-xl border border-white/10 overflow-hidden min-w-[140px]">
                      <button
                        onClick={handleDeleteStory}
                        className="flex items-center gap-2 w-full px-4 py-2.5 text-sm text-red-400 hover:bg-white/5 transition-colors"
                      >
                        <Trash2 className="w-4 h-4" />
                        Delete Story
                      </button>
                    </div>
                  )}
                </div>
              )}

              {/* Close button */}
              <button
                onClick={onClose}
                className="p-1.5 rounded-full hover:bg-white/10 transition-colors"
                aria-label="Close story viewer"
              >
                <X className="w-5 h-5 text-white" />
              </button>
            </div>

            {/* Bottom bar: reactions + view count */}
            <div className="absolute bottom-0 left-0 right-0 z-30 px-4 pb-6 pt-16 bg-gradient-to-t from-black/60 to-transparent">
              {/* View count (owner only) */}
              {isOwner && currentStory && (
                <button
                  onClick={handleViewViewers}
                  className="flex items-center gap-1.5 mb-3 text-white/70 hover:text-white transition-colors"
                  aria-label={`${currentStory.view_count} views`}
                >
                  <Eye className="w-4 h-4" />
                  <span className="text-sm">{currentStory.view_count}</span>
                </button>
              )}

              {/* Reactions */}
              {!isOwner && currentStory && currentStory.media_type !== 'poll' && (
                <div className="flex items-center gap-2">
                  {REACTIONS.map(({ emoji, type }) => (
                    <button
                      key={type}
                      onClick={() => handleReaction(type)}
                      className={`w-10 h-10 rounded-full flex items-center justify-center transition-all ${
                        reactedWith === type
                          ? 'bg-white/30 scale-125'
                          : 'bg-white/10 hover:bg-white/20 hover:scale-110 active:scale-95'
                      }`}
                      disabled={!!reactedWith}
                      aria-label={`React with ${type}`}
                    >
                      <span className="text-lg">{emoji}</span>
                    </button>
                  ))}
                </div>
              )}
            </div>
          </motion.div>
        </AnimatePresence>
      </div>

      {/* Viewers Modal */}
      {showViewers && (
        <div
          className="absolute inset-0 z-50 flex items-end md:items-center justify-center"
          onClick={() => { setShowViewers(false); setIsPaused(false); }}
        >
          <motion.div
            initial={{ y: 100, opacity: 0 }}
            animate={{ y: 0, opacity: 1 }}
            exit={{ y: 100, opacity: 0 }}
            className="w-full md:w-[400px] max-h-[60vh] bg-gray-900 rounded-t-2xl md:rounded-2xl overflow-hidden"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="flex items-center justify-between px-4 py-3 border-b border-white/10">
              <h3 className="text-white font-semibold">Viewers ({viewers.length})</h3>
              <button
                onClick={() => { setShowViewers(false); setIsPaused(false); }}
                className="p-1 rounded-full hover:bg-white/10 transition-colors"
                aria-label="Close viewers list"
              >
                <X className="w-4 h-4 text-white" />
              </button>
            </div>
            <div className="overflow-y-auto max-h-[calc(60vh-50px)]">
              {viewers.length === 0 ? (
                <p className="text-white/50 text-sm text-center py-6">No viewers yet</p>
              ) : (
                viewers.map((v) => (
                  <div key={v.id} className="flex items-center gap-3 px-4 py-3 hover:bg-white/5">
                    <Avatar
                      src={resolveAvatarUrl(v.avatar_url ?? null)}
                      name={v.name}
                      size="sm"
                      className="w-8 h-8"
                    />
                    <div className="flex-1 min-w-0">
                      <p className="text-white text-sm font-medium truncate">{v.name}</p>
                      <p className="text-white/40 text-xs">{timeAgo(v.viewed_at)}</p>
                    </div>
                  </div>
                ))
              )}
            </div>
          </motion.div>
        </div>
      )}

      {/* Close menu on outside click */}
      {showMenu && (
        <div
          className="absolute inset-0 z-20"
          onClick={() => { setShowMenu(false); setIsPaused(false); }}
        />
      )}
    </div>
  );

  return createPortal(storyContent, document.body);
}

export default StoryViewer;
