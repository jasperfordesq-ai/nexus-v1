// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * SearchEmptyIllustration — SVG illustration for no search results.
 * Theme-aware via CSS tokens. Renders a magnifying glass with question mark.
 */

export function SearchEmptyIllustration({ className = 'w-32 h-32' }: { className?: string }) {
  return (
    <svg viewBox="0 0 128 128" fill="none" xmlns="http://www.w3.org/2000/svg" className={className} aria-hidden="true">
      {/* Background circle */}
      <circle cx="64" cy="64" r="56" fill="var(--surface-elevated, #f3f4f6)" opacity="0.5" />

      {/* Magnifying glass lens */}
      <circle cx="56" cy="52" r="24" stroke="var(--color-primary, #6366f1)" strokeWidth="3" opacity="0.4" />
      <circle cx="56" cy="52" r="24" fill="var(--color-primary, #6366f1)" opacity="0.08" />

      {/* Handle */}
      <line x1="73" y1="69" x2="96" y2="92" stroke="var(--color-primary, #6366f1)" strokeWidth="4" strokeLinecap="round" opacity="0.35" />

      {/* Question mark inside */}
      <path d="M50 44 C50 38 56 36 60 38 C64 40 64 44 60 48 L56 52"
        stroke="var(--color-primary, #6366f1)" strokeWidth="2.5" strokeLinecap="round" opacity="0.5" fill="none" />
      <circle cx="56" cy="58" r="1.5" fill="var(--color-primary, #6366f1)" opacity="0.5" />

      {/* Small decorative dots */}
      <circle cx="100" cy="36" r="3" fill="var(--color-primary, #6366f1)" opacity="0.2" />
      <circle cx="24" cy="76" r="2" fill="var(--color-primary, #6366f1)" opacity="0.15" />
    </svg>
  );
}
