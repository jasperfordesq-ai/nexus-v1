// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * ReactionPicker — Animated emoji reaction picker for posts and comments.
 *
 * Appears on hover (desktop, 300ms delay) or long-press (mobile).
 * Shows 8 reaction types in a glassmorphism popup bar above the trigger button.
 * Selecting a reaction replaces the button text/icon with the chosen emoji.
 * Tapping the same reaction again removes it.
 */

import { useState, useRef, useCallback, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Button, Tooltip } from '@heroui/react';
import Heart from 'lucide-react/icons/heart';
import Clock from 'lucide-react/icons/clock';
import { useTranslation } from 'react-i18next';

/* ───────────────────────── Reaction Config ───────────────────────── */

export type ReactionType = 'love' | 'like' | 'laugh' | 'wow' | 'sad' | 'celebrate' | 'clap' | 'time_credit';

export interface ReactionConfig {
  type: ReactionType;
  emoji: string;
  label: string;
}

export const REACTION_CONFIGS: ReactionConfig[] = [
  { type: 'like', emoji: '\uD83D\uDC4D', label: 'reaction.like' },
  { type: 'love', emoji: '\u2764\uFE0F', label: 'reaction.love' },
  { type: 'laugh', emoji: '\uD83D\uDE02', label: 'reaction.laugh' },
  { type: 'wow', emoji: '\uD83D\uDE2E', label: 'reaction.wow' },
  { type: 'sad', emoji: '\uD83D\uDE22', label: 'reaction.sad' },
  { type: 'celebrate', emoji: '\uD83C\uDF89', label: 'reaction.celebrate' },
  { type: 'clap', emoji: '\uD83D\uDC4F', label: 'reaction.clap' },
  { type: 'time_credit', emoji: '\u23F0', label: 'reaction.time_credit' },
];

/** Map from reaction type to its emoji */
export const REACTION_EMOJI_MAP: Record<ReactionType, string> = Object.fromEntries(
  REACTION_CONFIGS.map((r) => [r.type, r.emoji])
) as Record<ReactionType, string>;

/** Map from reaction type to its label */
export const REACTION_LABEL_MAP: Record<ReactionType, string> = Object.fromEntries(
  REACTION_CONFIGS.map((r) => [r.type, r.label])
) as Record<ReactionType, string>;

/* ───────────────────────── Props ───────────────────────── */

export interface ReactionPickerProps {
  /** Currently selected reaction type (null if none) */
  userReaction: ReactionType | null;
  /** Called when a reaction is selected or deselected */
  onReact: (type: ReactionType) => void;
  /** Whether the user is authenticated */
  isAuthenticated: boolean;
  /** Whether the picker is disabled (e.g. during API call) */
  isDisabled?: boolean;
  /** Size variant */
  size?: 'sm' | 'md';
}

/* ───────────────────────── Component ───────────────────────── */

export function ReactionPicker({
  userReaction,
  onReact,
  isAuthenticated,
  isDisabled = false,
  size = 'md',
}: ReactionPickerProps) {
  const { t } = useTranslation('feed');
  const [isPickerOpen, setIsPickerOpen] = useState(false);
  const hoverTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const closeTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const longPressTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const containerRef = useRef<HTMLDivElement>(null);
  const isLongPressRef = useRef(false);

  // Cleanup timeouts on unmount
  useEffect(() => {
    return () => {
      if (hoverTimeoutRef.current) clearTimeout(hoverTimeoutRef.current);
      if (closeTimeoutRef.current) clearTimeout(closeTimeoutRef.current);
      if (longPressTimeoutRef.current) clearTimeout(longPressTimeoutRef.current);
    };
  }, []);

  /* ───── Desktop: hover with 300ms delay ───── */

  const handleMouseEnter = useCallback(() => {
    // Cancel any pending close so the picker stays open
    if (closeTimeoutRef.current) {
      clearTimeout(closeTimeoutRef.current);
      closeTimeoutRef.current = null;
    }
    if (!isAuthenticated || isDisabled) return;
    hoverTimeoutRef.current = setTimeout(() => {
      setIsPickerOpen(true);
    }, 300);
  }, [isAuthenticated, isDisabled]);

  const handleMouseLeave = useCallback(() => {
    if (hoverTimeoutRef.current) {
      clearTimeout(hoverTimeoutRef.current);
      hoverTimeoutRef.current = null;
    }
    // Small delay before closing to allow moving to the picker
    closeTimeoutRef.current = setTimeout(() => {
      setIsPickerOpen(false);
      closeTimeoutRef.current = null;
    }, 300);
  }, []);

  /* ───── Mobile: long-press ───── */

  const handleTouchStart = useCallback(() => {
    if (!isAuthenticated || isDisabled) return;
    isLongPressRef.current = false;
    longPressTimeoutRef.current = setTimeout(() => {
      isLongPressRef.current = true;
      setIsPickerOpen(true);
    }, 500);
  }, [isAuthenticated, isDisabled]);

  const handleTouchEnd = useCallback(() => {
    if (longPressTimeoutRef.current) {
      clearTimeout(longPressTimeoutRef.current);
      longPressTimeoutRef.current = null;
    }
  }, []);

  /* ───── Select reaction ───── */

  const handleSelectReaction = useCallback(
    (type: ReactionType) => {
      onReact(type);
      setIsPickerOpen(false);
    },
    [onReact]
  );

  /* ───── Quick tap on button (no picker) ───── */

  const handleQuickTap = useCallback(() => {
    if (!isAuthenticated || isDisabled) return;
    // If long press just fired, ignore
    if (isLongPressRef.current) {
      isLongPressRef.current = false;
      return;
    }
    // Quick tap: toggle like (default reaction)
    if (userReaction) {
      // Remove current reaction
      onReact(userReaction);
    } else {
      // Add default "like" reaction
      onReact('like');
    }
  }, [isAuthenticated, isDisabled, userReaction, onReact]);

  /* ───── Close picker when clicking outside ───── */

  useEffect(() => {
    if (!isPickerOpen) return;

    const handleClickOutside = (e: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
        setIsPickerOpen(false);
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, [isPickerOpen]);

  /* ───── Button content based on current reaction ───── */

  const currentConfig = userReaction ? REACTION_CONFIGS.find((r) => r.type === userReaction) : null;

  const buttonLabel = currentConfig ? t(currentConfig.label) : t('card.like_action', 'Like');
  const buttonEmoji = currentConfig ? currentConfig.emoji : null;

  // Color based on reaction type
  const getReactionColor = (type: ReactionType | null): string => {
    if (!type) return 'text-[var(--text-muted)] hover:text-rose-500';
    switch (type) {
      case 'love':
        return 'text-rose-500 font-medium';
      case 'like':
        return 'text-[var(--color-info)] font-medium';
      case 'laugh':
        return 'text-[var(--color-warning)] font-medium';
      case 'wow':
        return 'text-[var(--color-warning)] font-medium';
      case 'sad':
        return 'text-blue-400 font-medium';
      case 'celebrate':
        return 'text-emerald-500 font-medium';
      case 'clap':
        return 'text-orange-500 font-medium';
      case 'time_credit':
        return 'text-purple-500 font-medium';
      default:
        return 'text-[var(--text-muted)]';
    }
  };

  return (
    <div
      ref={containerRef}
      className="relative"
      onMouseEnter={handleMouseEnter}
      onMouseLeave={handleMouseLeave}
    >
      {/* Picker popup */}
      <AnimatePresence>
        {isPickerOpen && (
          <motion.div
            initial={{ opacity: 0, scale: 0.6, y: 8 }}
            animate={{ opacity: 1, scale: 1, y: 0 }}
            exit={{ opacity: 0, scale: 0.6, y: 8 }}
            transition={{ type: 'spring', stiffness: 400, damping: 25 }}
            /*
              Anchor the popup to the BUTTON'S left edge rather than centering it.
              The feed card is `overflow-hidden`, and after the footer redesign the
              Like button sits near the card's left edge — a centered popup was
              getting clipped on the left. Extending rightward keeps it inside the
              card body which always has room.
            */
            className="absolute bottom-full left-0 pb-2 z-50"
            onMouseEnter={() => {
              if (hoverTimeoutRef.current) clearTimeout(hoverTimeoutRef.current);
              if (closeTimeoutRef.current) {
                clearTimeout(closeTimeoutRef.current);
                closeTimeoutRef.current = null;
              }
            }}
          >
            <div className="flex items-center gap-0.5 px-2 py-1.5 rounded-full bg-[var(--surface-dropdown)]/95 backdrop-blur-xl border border-[var(--border-default)] shadow-xl shadow-black/20">
              {REACTION_CONFIGS.map((config) => (
                <Tooltip
                  key={config.type}
                  content={t(config.label)}
                  delay={200}
                  closeDelay={0}
                  size="sm"
                  placement="top"
                >
                  <motion.button
                    whileHover={{ scale: 1.35, y: -4 }}
                    whileTap={{ scale: 0.9 }}
                    transition={{ type: 'spring', stiffness: 500, damping: 20 }}
                    className={`w-9 h-9 flex items-center justify-center rounded-full cursor-pointer text-xl transition-colors
                      ${userReaction === config.type
                        ? 'bg-[var(--surface-active)] ring-2 ring-[var(--color-primary)]/40'
                        : 'hover:bg-[var(--surface-hover)]'
                      }`}
                    onClick={() => handleSelectReaction(config.type)}
                    aria-label={t(config.label)}
                    type="button"
                  >
                    {config.type === 'time_credit' ? (
                      <Clock className="w-5 h-5 text-purple-400" aria-hidden="true" />
                    ) : (
                      <span role="img" aria-label={t(config.label)}>{config.emoji}</span>
                    )}
                  </motion.button>
                </Tooltip>
              ))}
            </div>
          </motion.div>
        )}
      </AnimatePresence>

      {/* Main reaction button */}
      <Button
        size={size}
        variant="light"
        className={`flex-1 max-w-[140px] ${getReactionColor(userReaction)} transition-colors`}
        startContent={
          buttonEmoji ? (
            userReaction === 'time_credit' ? (
              <Clock className="w-[18px] h-[18px]" aria-hidden="true" />
            ) : (
              <span className="text-lg leading-none" aria-hidden="true">{buttonEmoji}</span>
            )
          ) : (
            <Heart
              className={`w-[18px] h-[18px] transition-all ${userReaction === 'love' ? 'fill-rose-500 text-rose-500 scale-110' : ''}`}
              aria-hidden="true"
            />
          )
        }
        onPress={handleQuickTap}
        onTouchStart={handleTouchStart}
        onTouchEnd={handleTouchEnd}
        isDisabled={!isAuthenticated || isDisabled}
        aria-label={userReaction ? t('reaction.click_to_remove', '{{label}} (click to remove)', { label: buttonLabel }) : t('reaction.react_to_post', 'React to this post')}
      >
        {buttonLabel}
      </Button>
    </div>
  );
}

export default ReactionPicker;
