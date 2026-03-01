// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * FeaturedBadge - Visual badge for featured listings
 */

import { Star } from 'lucide-react';

interface FeaturedBadgeProps {
  className?: string;
  size?: 'sm' | 'md';
}

export function FeaturedBadge({ className = '', size = 'sm' }: FeaturedBadgeProps) {
  const sizeClasses = size === 'sm'
    ? 'text-[10px] px-1.5 py-0.5 gap-0.5'
    : 'text-xs px-2 py-1 gap-1';

  return (
    <span
      className={`inline-flex items-center rounded-full font-medium
        bg-amber-500/20 text-amber-600 dark:text-amber-400 ${sizeClasses} ${className}`}
      aria-label="Featured listing"
    >
      <Star className={size === 'sm' ? 'w-3 h-3' : 'w-3.5 h-3.5'} fill="currentColor" />
      Featured
    </span>
  );
}

export default FeaturedBadge;
