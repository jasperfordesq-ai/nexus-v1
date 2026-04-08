// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * BookmarksEmptyIllustration — SVG illustration for empty bookmarks state.
 * Theme-aware via CSS tokens. Renders a bookmark ribbon with heart.
 */

export function BookmarksEmptyIllustration({ className = 'w-32 h-32' }: { className?: string }) {
  return (
    <svg viewBox="0 0 128 128" fill="none" xmlns="http://www.w3.org/2000/svg" className={className} aria-hidden="true">
      {/* Background circle */}
      <circle cx="64" cy="64" r="56" fill="var(--surface-elevated, #f3f4f6)" opacity="0.5" />

      {/* Bookmark ribbon */}
      <path d="M42 24 L42 96 L64 80 L86 96 L86 24 Z"
        fill="var(--color-primary, #6366f1)" opacity="0.12" />
      <path d="M42 24 L42 96 L64 80 L86 96 L86 24 Z"
        stroke="var(--color-primary, #6366f1)" strokeWidth="2" opacity="0.35" strokeLinejoin="round" />

      {/* Heart in center */}
      <path d="M64 50 C60 42 48 42 48 52 C48 62 64 72 64 72 C64 72 80 62 80 52 C80 42 68 42 64 50 Z"
        fill="var(--color-primary, #6366f1)" opacity="0.25" />

      {/* Small sparkles */}
      <circle cx="96" cy="40" r="3" fill="var(--color-primary, #6366f1)" opacity="0.2" />
      <circle cx="28" cy="56" r="2" fill="var(--color-primary, #6366f1)" opacity="0.15" />
      <circle cx="100" cy="72" r="2.5" fill="var(--color-primary, #6366f1)" opacity="0.18" />
    </svg>
  );
}
