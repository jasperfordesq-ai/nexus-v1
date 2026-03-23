// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * PresenceIndicator — Colored dot overlay for user avatars.
 *
 * Shows online/away/dnd/offline status with color-coded dot.
 * Designed to be positioned absolutely within a parent container.
 *
 * Usage:
 *   <div className="relative inline-block">
 *     <Avatar ... />
 *     <PresenceIndicator userId={member.id} />
 *   </div>
 */

import { memo, useMemo } from 'react';
import { motion } from 'framer-motion';
import { Tooltip } from '@heroui/react';
import { usePresenceOptional, type PresenceStatus } from '@/contexts/PresenceContext';

interface PresenceIndicatorProps {
  /** User ID to show presence for */
  userId: number;
  /** Size variant */
  size?: 'sm' | 'md' | 'lg';
  /** Custom class for positioning override */
  className?: string;
}

/**
 * Size mappings for the dot.
 */
const sizeClasses = {
  sm: 'w-2 h-2',
  md: 'w-2.5 h-2.5',
  lg: 'w-3 h-3',
} as const;

const ringClasses = {
  sm: 'ring-[1.5px]',
  md: 'ring-2',
  lg: 'ring-2',
} as const;

/**
 * Status color mappings.
 */
function getStatusColor(status: PresenceStatus): string {
  switch (status) {
    case 'online':
      return 'bg-green-500';
    case 'away':
      return 'bg-yellow-500';
    case 'dnd':
      return 'bg-red-500';
    case 'offline':
    default:
      return 'bg-gray-400 dark:bg-gray-600';
  }
}

/**
 * Human-readable status label.
 */
function getStatusLabel(status: PresenceStatus): string {
  switch (status) {
    case 'online':
      return 'Online';
    case 'away':
      return 'Away';
    case 'dnd':
      return 'Do Not Disturb';
    case 'offline':
    default:
      return 'Offline';
  }
}

/**
 * Format "last seen" as a relative timestamp.
 */
function formatLastSeen(lastSeen: string | null): string {
  if (!lastSeen) return '';

  const now = Date.now();
  const then = new Date(lastSeen).getTime();
  const diffMs = now - then;

  if (diffMs < 60_000) return 'Just now';

  const diffMin = Math.floor(diffMs / 60_000);
  if (diffMin < 60) return `${diffMin}m ago`;

  const diffHours = Math.floor(diffMin / 60);
  if (diffHours < 24) return `${diffHours}h ago`;

  const diffDays = Math.floor(diffHours / 24);
  return `${diffDays}d ago`;
}

export const PresenceIndicator = memo(function PresenceIndicator({
  userId,
  size = 'md',
  className,
}: PresenceIndicatorProps) {
  const presence = usePresenceOptional();

  const presenceState = useMemo(() => {
    if (!presence) return null;
    return presence.getPresence(userId);
  }, [presence, userId]);

  // Don't render anything if presence is unavailable or user is offline
  if (!presenceState || presenceState.status === 'offline') {
    return null;
  }

  const { status, last_seen_at, custom_status, status_emoji } = presenceState;
  const colorClass = getStatusColor(status);
  const sizeClass = sizeClasses[size];
  const ringClass = ringClasses[size];

  // Build tooltip content
  const tooltipLines: string[] = [];
  if (status_emoji && custom_status) {
    tooltipLines.push(`${status_emoji} ${custom_status}`);
  } else if (custom_status) {
    tooltipLines.push(custom_status);
  } else {
    tooltipLines.push(getStatusLabel(status));
  }
  if (status === 'away' && last_seen_at) {
    tooltipLines.push(`Last seen ${formatLastSeen(last_seen_at)}`);
  }

  const tooltipContent = tooltipLines.join(' \u2022 ');

  return (
    <Tooltip content={tooltipContent} placement="top" delay={300}>
      <div
        className={`absolute bottom-0 right-0 ${className ?? ''}`}
        aria-label={getStatusLabel(status)}
        role="status"
      >
        {status === 'online' ? (
          <motion.div
            className={`${sizeClass} rounded-full ${colorClass} ${ringClass} ring-white dark:ring-gray-800`}
            animate={{
              scale: [1, 1.15, 1],
              opacity: [1, 0.85, 1],
            }}
            transition={{
              duration: 2,
              repeat: Infinity,
              ease: 'easeInOut',
            }}
          />
        ) : (
          <div
            className={`${sizeClass} rounded-full ${colorClass} ${ringClass} ring-white dark:ring-gray-800`}
          />
        )}
      </div>
    </Tooltip>
  );
});

export default PresenceIndicator;
