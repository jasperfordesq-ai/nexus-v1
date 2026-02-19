// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { type ReactNode } from 'react';
import { GlassButton } from '../ui';

export interface HeaderProps {
  /** Logo or brand element */
  logo?: ReactNode;
  /** Navigation items */
  children?: ReactNode;
  /** Right-side actions (e.g., user menu, CTA) */
  actions?: ReactNode;
  /** Show mobile menu button */
  onMenuClick?: () => void;
}

/**
 * Header - Glass-styled header component
 *
 * Fixed position with glass surface and subtle bottom border
 */
export function Header({ logo, children, actions, onMenuClick }: HeaderProps) {
  return (
    <header className="glass-surface-strong fixed top-0 left-0 right-0 z-[var(--z-sticky)] border-b border-[var(--glass-border)]">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex items-center justify-between h-16">
          {/* Logo */}
          <div className="flex items-center gap-4">
            {onMenuClick && (
              <GlassButton
                variant="ghost"
                size="sm"
                className="lg:hidden p-2"
                onClick={onMenuClick}
                aria-label="Open menu"
              >
                <svg
                  className="w-6 h-6"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M4 6h16M4 12h16M4 18h16"
                  />
                </svg>
              </GlassButton>
            )}
            {logo || (
              <span className="text-xl font-bold text-gradient">NEXUS</span>
            )}
          </div>

          {/* Desktop Navigation */}
          <nav className="hidden lg:flex items-center gap-1">
            {children}
          </nav>

          {/* Actions */}
          <div className="flex items-center gap-3">
            {actions}
          </div>
        </div>
      </div>
    </header>
  );
}

/**
 * HeaderNavLink - Navigation link styled for header
 */
export interface HeaderNavLinkProps {
  href: string;
  children: ReactNode;
  active?: boolean;
  onClick?: () => void;
}

export function HeaderNavLink({ href, children, active, onClick }: HeaderNavLinkProps) {
  return (
    <a
      href={href}
      onClick={onClick}
      className={`
        px-4 py-2 rounded-[var(--radius-md)] text-sm font-medium
        transition-all duration-[var(--transition-fast)]
        ${active
          ? 'bg-[var(--glass-bg-hover)] text-[var(--foreground)] border-glow-primary'
          : 'text-[var(--foreground-muted)] hover:text-[var(--foreground)] hover:bg-[var(--glass-bg)]'
        }
      `}
    >
      {children}
    </a>
  );
}

export default Header;
