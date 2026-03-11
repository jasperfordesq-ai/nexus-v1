// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Link } from 'react-router-dom';
import { Avatar, Tooltip } from '@heroui/react';
import { motion } from 'framer-motion';
import { useTenant } from '@/contexts';

/** Extract 1–2 initials from a tenant name. */
function getInitials(name: string): string {
  const words = name.trim().split(/\s+/);
  if (words.length === 1) return words[0].substring(0, 2).toUpperCase();
  return (words[0][0] + words[words.length - 1][0]).toUpperCase();
}

/** Returns true when the background is light enough to need dark text. */
function shouldUseDarkText(hex: string): boolean {
  if (!hex || !hex.startsWith('#') || hex.length < 7) return false;
  const r = parseInt(hex.slice(1, 3), 16);
  const g = parseInt(hex.slice(3, 5), 16);
  const b = parseInt(hex.slice(5, 7), 16);
  return (0.299 * r + 0.587 * g + 0.114 * b) / 255 > 0.6;
}

/* ─── size maps ──────────────────────────────────────────────── */

const avatarSizeMap = { sm: 'sm', md: 'sm', lg: 'md' } as const;

const imgClassMap = {
  sm: 'h-7 w-auto object-contain max-w-[140px]',
  md: 'h-8 sm:h-9 w-auto object-contain max-w-[160px]',
  lg: 'h-9 w-auto object-contain max-w-[160px]',
} as const;

const nameClassMap = {
  sm: 'text-lg',
  md: 'text-lg sm:text-xl',
  lg: 'text-xl',
} as const;

/* ─── component ──────────────────────────────────────────────── */

export interface TenantLogoProps {
  /** Visual size preset: sm (Footer), md (Navbar), lg (MobileDrawer). */
  size?: 'sm' | 'md' | 'lg';
  /** Show the tenant name alongside the icon / image. */
  showName?: boolean;
  /** Show the tagline below the name (lg+ viewports only). */
  showTagline?: boolean;
  /** Compact mode — shrinks logo and hides name. Used when header is scrolled. */
  compact?: boolean;
  /** Extra classes on the outer <Link>. */
  className?: string;
}

export function TenantLogo({
  size = 'md',
  showName = true,
  showTagline = false,
  compact = false,
  className = '',
}: TenantLogoProps) {
  const { branding, tenantPath } = useTenant();

  const primaryColor = branding.primaryColor || '#6366f1';
  const darkText = shouldUseDarkText(primaryColor);
  const needsTooltip = branding.name.length > 20;

  // When compact, use smaller sizes
  const effectiveSize = compact ? 'sm' : size;

  /* ── icon / image ────────────────────────────────────────── */
  const iconElement = branding.logo ? (
    <img
      src={branding.logo}
      alt={branding.name}
      className={`${imgClassMap[effectiveSize]} transition-all duration-200`}
      loading={size === 'sm' ? 'lazy' : 'eager'}
    />
  ) : (
    <Avatar
      name={branding.name}
      getInitials={() => getInitials(branding.name)}
      size={avatarSizeMap[effectiveSize]}
      classNames={{
        base: 'ring-2 ring-offset-1 ring-offset-transparent ring-default-200 dark:ring-default-100 shrink-0 transition-all duration-200',
      }}
      style={{
        backgroundColor: primaryColor,
        color: darkText ? '#1a1a2e' : '#ffffff',
      }}
      aria-hidden="true"
    />
  );

  /* ── name text ───────────────────────────────────────────── */
  const nameSpan = (
    <span
      className={`font-bold ${nameClassMap[size]} text-gradient truncate max-w-[120px] sm:max-w-[160px] md:max-w-[200px] lg:max-w-[240px]`}
    >
      {branding.name}
    </span>
  );

  const nameElement = needsTooltip ? (
    <Tooltip content={branding.name} placement="bottom" delay={400} closeDelay={0}>
      {nameSpan}
    </Tooltip>
  ) : (
    nameSpan
  );

  /* ── tagline ─────────────────────────────────────────────── */
  const taglineElement =
    showTagline && branding.tagline ? (
      <span className="hidden lg:inline text-xs text-theme-subtle truncate max-w-[200px]">
        {branding.tagline}
      </span>
    ) : null;

  /* ── render ──────────────────────────────────────────────── */
  return (
    <Link
      to={tenantPath('/')}
      className={`flex items-center gap-2 ${className}`.trim()}
    >
      <motion.div
        initial={{ opacity: 0, scale: 0.92 }}
        animate={{ opacity: 1, scale: 1 }}
        transition={{ duration: 0.2, ease: 'easeOut' }}
        className="flex items-center"
      >
        {iconElement}
      </motion.div>

      {showName && !compact && (
        <div className="hidden min-[480px]:flex flex-col min-w-0 transition-all duration-200">
          {nameElement}
          {taglineElement}
        </div>
      )}
    </Link>
  );
}
