// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * LevelProgress - Glassmorphic XP/level progress bar
 *
 * Ported from Next.js glass-progress.tsx, adapted for light/dark theme support.
 * Used in dashboard gamification sidebar and profile pages.
 */

export interface LevelProgressProps {
  currentXP: number;
  requiredXP: number;
  level: number;
  /** Optional: show compact variant without level text */
  compact?: boolean;
}

export function LevelProgress({ currentXP, requiredXP, level, compact = false }: LevelProgressProps) {
  const percentage = requiredXP > 0 ? Math.min(Math.round((currentXP / requiredXP) * 100), 100) : 0;

  return (
    <div className="space-y-2">
      {!compact && (
        <div className="flex justify-between items-center">
          <span className="text-theme-primary font-medium">Level {level}</span>
          <span className="text-theme-subtle text-sm">
            {currentXP.toLocaleString()} / {requiredXP.toLocaleString()} XP
          </span>
        </div>
      )}
      <div className="relative h-3 bg-theme-elevated rounded-full overflow-hidden border border-[var(--border-default)]">
        <div
          className="absolute inset-y-0 left-0 bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500 rounded-full transition-all duration-500"
          style={{ width: `${percentage}%` }}
        />
        <div className="absolute inset-0 bg-gradient-to-b from-white/20 to-transparent dark:from-white/20 dark:to-transparent rounded-full" />
      </div>
      {compact && (
        <div className="flex justify-between text-xs">
          <span className="text-theme-muted">
            {currentXP.toLocaleString()} / {requiredXP.toLocaleString()} XP
          </span>
          <span className="text-theme-primary font-medium">{percentage}%</span>
        </div>
      )}
    </div>
  );
}
