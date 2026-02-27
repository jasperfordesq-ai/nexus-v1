// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * CharacterCount — compact progress bar with character count display.
 * Pure presentational component with color transitions at usage thresholds.
 */

interface CharacterCountProps {
  current: number;
  max: number;
}

export function CharacterCount({ current, max }: CharacterCountProps) {
  const percentage = max > 0 ? Math.min((current / max) * 100, 100) : 0;
  const isOverLimit = current >= max;
  const isWarning = percentage >= 80 && percentage < 95;
  const isDanger = percentage >= 95;

  const barColor = isDanger
    ? 'bg-red-500'
    : isWarning
      ? 'bg-amber-500'
      : 'bg-[var(--color-primary)]';

  const textColor = isOverLimit
    ? 'text-red-500'
    : 'text-[var(--text-muted)]';

  return (
    <div className="flex items-center gap-2 h-6 mt-1">
      {/* Progress bar */}
      <div className="flex-1 h-2 rounded-full bg-[var(--surface-hover)] overflow-hidden">
        <div
          className={`h-full rounded-full transition-all duration-200 ${barColor}`}
          style={{ width: `${percentage}%` }}
        />
      </div>

      {/* Count text */}
      <span className={`text-sm tabular-nums whitespace-nowrap ${textColor}`}>
        {current}/{max}
      </span>
    </div>
  );
}
