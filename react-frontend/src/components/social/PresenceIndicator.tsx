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
import { useTranslation } from 'react-i18next';
import { usePresenceOptional, type PresenceStatus } from '@/contexts/PresenceContext';

interface PresenceIndicatorProps {
  /** User ID to show presence for */
  userId: number;
  /** Size variant */
  size?: 'sm' | 'md' | 'lg';
  /** Custom class for positioning override */
  className?: string;
  /** Show a dot even when offline (gray dot). Default: false (hides when offline). */
  showOffline?: boolean;
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
 * Human-readable status label key.
 */
const STATUS_LABEL_KEYS: Record<string, string> = {
  online: 'presence.online',
  away: 'presence.away',
  dnd: 'presence.dnd',
  offline: 'presence.offline',
};

/**
 * Format "last seen" as a relative timestamp.
 */
const LAST_SEEN_KEYS = {
  just_now: 'presence.just_now',
  minutes_ago: 'presence.minutes_ago',
  hours_ago: 'presence.hours_ago',
  days_ago: 'presence.days_ago',
};

export const PresenceIndicator = memo(function PresenceIndicator({
  userId,
  size = 'md',
  className,
  showOffline = false,
}: PresenceIndicatorProps) {
  const { t } = useTranslation('social');
  const presence = usePresenceOptional();

  const presenceState = useMemo(() => {
    if (!presence) return null;
    return presence.getPresence(userId);
  }, [presence, userId]);

  // Don't render anything if presence is unavailable
  if (!presenceState) return null;

  // Hide offline users unless showOffline is set
  if (presenceState.status === 'offline' && !showOffline) {
    return null;
  }

  const { status, last_seen_at, custom_status, status_emoji } = presenceState;
  const colorClass = getStatusColor(status);
  const sizeClass = sizeClasses[size];
  const ringClass = ringClasses[size];

  const statusLabel = t(STATUS_LABEL_KEYS[status] ?? STATUS_LABEL_KEYS.offline ?? 'presence.offline');

  // Format last seen
  const formatLastSeen = (lastSeen: string | null): string => {
    if (!lastSeen) return '';
    const now = Date.now();
    const then = new Date(lastSeen).getTime();
    const diffMs = now - then;
    if (diffMs < 60_000) return t(LAST_SEEN_KEYS.just_now, 'Just now');
    const diffMin = Math.floor(diffMs / 60_000);
    if (diffMin < 60) return t(LAST_SEEN_KEYS.minutes_ago, '{{count}}m ago', { count: diffMin });
    const diffHours = Math.floor(diffMin / 60);
    if (diffHours < 24) return t(LAST_SEEN_KEYS.hours_ago, '{{count}}h ago', { count: diffHours });
    const diffDays = Math.floor(diffHours / 24);
    return t(LAST_SEEN_KEYS.days_ago, '{{count}}d ago', { count: diffDays });
  };

  // Build tooltip content
  const tooltipLines: string[] = [];
  if (status_emoji && custom_status) {
    tooltipLines.push(`${status_emoji} ${custom_status}`);
  } else if (custom_status) {
    tooltipLines.push(custom_status);
  } else {
    tooltipLines.push(statusLabel);
  }
  if (status === 'away' && last_seen_at) {
    tooltipLines.push(t('presence.last_seen', 'Last seen {{time}}', { time: formatLastSeen(last_seen_at) }));
  }

  const tooltipContent = tooltipLines.join(' \u2022 ');

  return (
    <Tooltip content={tooltipContent} placement="top" delay={300}>
      <div
        className={`absolute bottom-0 right-0 ${className ?? ''}`}
        aria-label={statusLabel}
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
