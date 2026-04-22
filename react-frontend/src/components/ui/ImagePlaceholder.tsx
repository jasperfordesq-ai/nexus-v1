// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { LucideIcon } from 'lucide-react';
import ShoppingBag from 'lucide-react/icons/shopping-bag';

interface ImagePlaceholderProps {
  /** Lucide icon to display in center */
  icon?: LucideIcon;
  /** Height variant */
  size?: 'sm' | 'md' | 'lg';
  /** Additional CSS classes on the outer container */
  className?: string;
}

/**
 * Glass morphism placeholder for cards/pages without an uploaded image.
 * Uses the site's brand gradient (indigo → purple → cyan) with a frosted
 * glass overlay and a large semi-transparent icon.
 */
export function ImagePlaceholder({
  icon: Icon = ShoppingBag,
  size = 'md',
  className = '',
}: ImagePlaceholderProps) {
  const heightClass = {
    sm: 'h-36',
    md: 'h-48 sm:h-56',
    lg: 'h-56 sm:h-72',
  }[size];

  const iconSize = {
    sm: 'w-10 h-10',
    md: 'w-14 h-14',
    lg: 'w-16 h-16',
  }[size];

  return (
    <div
      className={`relative ${heightClass} w-full overflow-hidden ${className}`}
      aria-hidden="true"
    >
      {/* Gradient background */}
      <div className="absolute inset-0 bg-gradient-to-br from-indigo-500/20 via-purple-500/15 to-cyan-500/20 dark:from-indigo-500/30 dark:via-purple-500/20 dark:to-cyan-500/25" />

      {/* Subtle pattern overlay */}
      <div
        className="absolute inset-0 opacity-[0.04] dark:opacity-[0.06]"
        style={{
          backgroundImage:
            'radial-gradient(circle at 1px 1px, currentColor 1px, transparent 0)',
          backgroundSize: '24px 24px',
        }}
      />

      {/* Decorative gradient orbs */}
      <div className="absolute -top-8 -right-8 w-32 h-32 rounded-full bg-indigo-400/10 dark:bg-indigo-400/15 blur-2xl" />
      <div className="absolute -bottom-8 -left-8 w-32 h-32 rounded-full bg-cyan-400/10 dark:bg-cyan-400/15 blur-2xl" />

      {/* Glass center with icon */}
      <div className="absolute inset-0 flex items-center justify-center">
        <div className="rounded-2xl bg-white/10 dark:bg-white/5 backdrop-blur-sm p-5 ring-1 ring-white/20 dark:ring-white/10">
          <Icon
            className={`${iconSize} text-indigo-400/50 dark:text-indigo-300/40`}
            strokeWidth={1.5}
          />
        </div>
      </div>
    </div>
  );
}
