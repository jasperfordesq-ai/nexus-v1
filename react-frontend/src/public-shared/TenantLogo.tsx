// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

'use client';

/**
 * Shared TenantLogo — the public-shared port of the SPA's branding/TenantLogo,
 * so the header brand is the REAL one in both the Vite SPA and the Next SSR app.
 * Driven by the runtime port (branding + Link + hrefFor). Renders the uploaded
 * tenant logo when present, else an initials chip on the brand colour, plus the
 * gradient wordmark — matching the SPA classes so it looks identical.
 */

import type { ReactNode } from 'react';

import { usePublicRuntime } from './runtime';

type LogoSize = 'sm' | 'md' | 'lg';

const logoMaxWidth: Record<LogoSize, string> = {
  sm: 'max-w-[110px] sm:max-w-[150px]',
  md: 'max-w-[200px] sm:max-w-[260px]',
  lg: 'max-w-[200px] sm:max-w-[260px]',
};

// Default to the "landscape" shape (no client image measurement on the server).
const logoHeight: Record<LogoSize, string> = {
  sm: 'h-8',
  md: 'h-10 sm:h-12',
  lg: 'h-11 sm:h-14',
};

const nameClass: Record<LogoSize, string> = {
  sm: 'text-lg',
  md: 'text-lg sm:text-xl',
  lg: 'text-xl',
};

const avatarSize: Record<LogoSize, string> = {
  sm: 'h-8 w-8 text-xs',
  md: 'h-9 w-9 text-sm',
  lg: 'h-11 w-11 text-base',
};

function getInitials(name: string): string {
  const words = name.trim().split(/\s+/);
  const first = words[0] ?? '';
  const last = words[words.length - 1] ?? '';
  if (words.length === 1) return first.substring(0, 2).toUpperCase();
  return ((first[0] ?? '') + (last[0] ?? '')).toUpperCase();
}

function shouldUseDarkText(hex: string): boolean {
  if (!hex || !hex.startsWith('#') || hex.length < 7) return false;
  const r = parseInt(hex.slice(1, 3), 16);
  const g = parseInt(hex.slice(3, 5), 16);
  const b = parseInt(hex.slice(5, 7), 16);
  return (0.299 * r + 0.587 * g + 0.114 * b) / 255 > 0.6;
}

export interface TenantLogoProps {
  size?: LogoSize;
  showName?: boolean;
  className?: string;
}

export function TenantLogo({ size = 'md', showName = true, className = '' }: TenantLogoProps): ReactNode {
  const { branding, Link, hrefFor } = usePublicRuntime();
  const name = branding?.name ?? '';
  const logoUrl = branding?.logoUrl;
  const primaryColor = branding?.primaryColor || '#6366f1';
  const darkText = shouldUseDarkText(primaryColor);
  const hasLogo = Boolean(logoUrl);

  return (
    <Link href={hrefFor('/')} className={`flex items-center gap-2 sm:gap-3 min-w-0 no-underline ${className}`} aria-label={name}>
      {hasLogo ? (
        <img
          src={logoUrl}
          alt={name}
          className={`${logoHeight[size]} w-auto object-contain ${logoMaxWidth[size]} transition-all duration-200`}
          width={size === 'sm' ? 150 : 240}
          height={size === 'sm' ? 32 : 48}
        />
      ) : (
        <span
          className={`inline-flex items-center justify-center rounded-full font-bold shrink-0 ring-2 ring-offset-1 ring-offset-transparent ring-border ${avatarSize[size]}`}
          style={{ backgroundColor: primaryColor, color: darkText ? '#1a1a2e' : '#ffffff' }}
          aria-hidden="true"
        >
          {getInitials(name)}
        </span>
      )}
      {showName && !hasLogo ? (
        <span className={`font-bold ${nameClass[size]} text-gradient truncate max-w-[120px] sm:max-w-[160px] md:max-w-[200px] lg:max-w-[240px]`}>
          {name}
        </span>
      ) : null}
    </Link>
  );
}
