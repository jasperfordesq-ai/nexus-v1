// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { type ReactNode } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { GlassButton } from '../ui';

export interface MobileNavProps {
  /** Whether the nav is open */
  isOpen: boolean;
  /** Close handler */
  onClose: () => void;
  /** Navigation content */
  children: ReactNode;
}

/**
 * MobileNav - Slide-in mobile navigation panel
 *
 * Full-screen glass overlay with slide animation
 */
export function MobileNav({ isOpen, onClose, children }: MobileNavProps) {
  return (
    <AnimatePresence>
      {isOpen && (
        <>
          {/* Backdrop */}
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            transition={{ duration: 0.2 }}
            className="fixed inset-0 bg-black/60 backdrop-blur-sm z-[var(--z-modal-backdrop)] lg:hidden"
            onClick={onClose}
          />

          {/* Panel */}
          <motion.div
            initial={{ x: '-100%' }}
            animate={{ x: 0 }}
            exit={{ x: '-100%' }}
            transition={{ type: 'spring', damping: 25, stiffness: 300 }}
            className="fixed top-0 left-0 bottom-0 w-80 max-w-[85vw] glass-surface-strong border-r border-[var(--glass-border)] z-[var(--z-modal)] lg:hidden overflow-y-auto"
          >
            {/* Header */}
            <div className="flex items-center justify-between p-4 border-b border-[var(--glass-border)]">
              <span className="text-xl font-bold text-gradient">NEXUS</span>
              <GlassButton
                variant="ghost"
                size="sm"
                className="p-2"
                onClick={onClose}
                aria-label="Close menu"
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
                    d="M6 18L18 6M6 6l12 12"
                  />
                </svg>
              </GlassButton>
            </div>

            {/* Content */}
            <nav className="p-4">
              {children}
            </nav>
          </motion.div>
        </>
      )}
    </AnimatePresence>
  );
}

/**
 * MobileNavLink - Navigation link styled for mobile nav
 */
export interface MobileNavLinkProps {
  href: string;
  children: ReactNode;
  active?: boolean;
  onClick?: () => void;
  icon?: ReactNode;
}

export function MobileNavLink({ href, children, active, onClick, icon }: MobileNavLinkProps) {
  return (
    <a
      href={href}
      onClick={onClick}
      className={`
        flex items-center gap-3 px-4 py-3 rounded-[var(--radius-md)]
        text-base font-medium transition-all duration-[var(--transition-fast)]
        ${active
          ? 'bg-[var(--glass-bg-hover)] text-[var(--foreground)] glow-primary'
          : 'text-[var(--foreground-muted)] hover:text-[var(--foreground)] hover:bg-[var(--glass-bg)]'
        }
      `}
    >
      {icon && <span className="w-5 h-5 opacity-70">{icon}</span>}
      {children}
    </a>
  );
}

/**
 * MobileNavSection - Group of links with optional title
 */
export interface MobileNavSectionProps {
  title?: string;
  children: ReactNode;
}

export function MobileNavSection({ title, children }: MobileNavSectionProps) {
  return (
    <div className="mb-6">
      {title && (
        <h3
          className="px-4 mb-2 text-xs font-semibold uppercase tracking-wider text-theme-subtle"
        >
          {title}
        </h3>
      )}
      <div className="space-y-1">
        {children}
      </div>
    </div>
  );
}

export default MobileNav;
