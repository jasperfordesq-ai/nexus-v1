// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * FeedEmptyIllustration — SVG illustration for empty feed state.
 * Theme-aware via CSS tokens. Renders a stylised speech bubble + sparkle motif.
 */

export function FeedEmptyIllustration({ className = 'w-32 h-32' }: { className?: string }) {
  return (
    <svg viewBox="0 0 128 128" fill="none" xmlns="http://www.w3.org/2000/svg" className={className} aria-hidden="true">
      {/* Background circle */}
      <circle cx="64" cy="64" r="56" fill="var(--surface-elevated, #f3f4f6)" opacity="0.5" />

      {/* Main speech bubble */}
      <rect x="28" y="32" width="72" height="48" rx="12" fill="var(--color-primary, #6366f1)" opacity="0.15" />
      <rect x="28" y="32" width="72" height="48" rx="12" stroke="var(--color-primary, #6366f1)" strokeWidth="2" opacity="0.4" />

      {/* Bubble tail */}
      <path d="M44 80 L38 92 L54 80" fill="var(--color-primary, #6366f1)" opacity="0.15" />
      <path d="M44 80 L38 92 L54 80" stroke="var(--color-primary, #6366f1)" strokeWidth="2" opacity="0.4" strokeLinejoin="round" />

      {/* Content lines inside bubble */}
      <rect x="38" y="44" width="40" height="4" rx="2" fill="var(--color-primary, #6366f1)" opacity="0.3" />
      <rect x="38" y="54" width="52" height="4" rx="2" fill="var(--color-primary, #6366f1)" opacity="0.2" />
      <rect x="38" y="64" width="28" height="4" rx="2" fill="var(--color-primary, #6366f1)" opacity="0.15" />

      {/* Sparkle top-right */}
      <path d="M96 24 L98 30 L104 28 L98 32 L100 38 L96 34 L92 38 L94 32 L88 28 L94 30 Z"
        fill="var(--color-primary, #6366f1)" opacity="0.6" />

      {/* Small sparkle */}
      <circle cx="108" cy="44" r="3" fill="var(--color-primary, #6366f1)" opacity="0.3" />
      <circle cx="20" cy="50" r="2" fill="var(--color-primary, #6366f1)" opacity="0.2" />
    </svg>
  );
}
