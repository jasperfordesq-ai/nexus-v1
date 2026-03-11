// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Mega Menu
 * Two-column popover menu for the "More" navigation dropdown.
 * - Left column: Activity items, optionally grouped with thin dividers.
 * - Right column: About items + collapsible Partner Communities section.
 * Supports keyboard navigation (arrow keys + Enter) and focus management.
 */

import { useState, useRef, useEffect, useCallback, useMemo } from 'react';
import { useLocation } from 'react-router-dom';
import {
  Button,
  Popover,
  PopoverTrigger,
  PopoverContent,
} from '@heroui/react';
import { Menu, ChevronDown } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { LucideIcon } from 'lucide-react';

interface MegaMenuItem {
  label: string;
  desc?: string;
  href: string;
  icon: LucideIcon;
  /** When true, renders a thin divider line above this item to start a new group */
  dividerBefore?: boolean;
}

interface MegaMenuProps {
  isOpen: boolean;
  onOpenChange: (open: boolean) => void;
  isActive: boolean;
  activityItems: MegaMenuItem[];
  federationItems: MegaMenuItem[];
  aboutItems: MegaMenuItem[];
  onNavigate: (path: string) => void;
}

export function MegaMenu({
  isOpen,
  onOpenChange,
  isActive,
  activityItems,
  federationItems,
  aboutItems,
  onNavigate,
}: MegaMenuProps) {
  const { t } = useTranslation('common');
  const location = useLocation();
  const menuRef = useRef<HTMLDivElement>(null);
  const [federationExpanded, setFederationExpanded] = useState(false);


  // Focus the first button when the menu opens
  useEffect(() => {
    if (isOpen && menuRef.current) {
      // Small delay to let the popover render
      requestAnimationFrame(() => {
        const firstButton = menuRef.current?.querySelector<HTMLButtonElement>('button[data-mega-item]');
        firstButton?.focus();
      });
    }
  }, [isOpen]);

  // Two logical columns: Activity (left) | About + Federation (right).
  // The right column starts at activityItems.length in the flattened keyboard nav order.
  const handleKeyDown = useCallback((e: React.KeyboardEvent) => {
    if (!menuRef.current) return;

    const buttons = Array.from(menuRef.current.querySelectorAll<HTMLButtonElement>('button[data-mega-item]'));
    const currentIndex = buttons.indexOf(document.activeElement as HTMLButtonElement);
    const rightColStart = activityItems.length;

    if (e.key === 'ArrowDown') {
      e.preventDefault();
      buttons[(currentIndex + 1) % buttons.length]?.focus();
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      buttons[(currentIndex - 1 + buttons.length) % buttons.length]?.focus();
    } else if (e.key === 'ArrowRight') {
      e.preventDefault();
      if (currentIndex < rightColStart && rightColStart < buttons.length) {
        buttons[rightColStart]?.focus();
      }
    } else if (e.key === 'ArrowLeft') {
      e.preventDefault();
      if (currentIndex >= rightColStart) {
        buttons[0]?.focus();
      }
    } else if (e.key === 'Home') {
      e.preventDefault();
      buttons[0]?.focus();
    } else if (e.key === 'End') {
      e.preventDefault();
      buttons[buttons.length - 1]?.focus();
    } else if (e.key === 'Escape') {
      onOpenChange(false);
    }
  }, [activityItems.length, onOpenChange]);

  // Respect prefers-reduced-motion for popover animation
  const reducedMotion = useMemo(() => {
    if (typeof window === 'undefined') return false;
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }, []);

  // Shared item renderer used by both columns
  const renderItem = (item: MegaMenuItem) => (
    <Button
      key={item.href}
      data-mega-item
      onPress={() => onNavigate(item.href)}
      variant="light"
      className={`w-full flex items-start gap-3 px-3 py-2 rounded-lg text-left transition-colors motion-reduce:transition-none focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500/50 h-auto justify-start ${
        location.pathname.startsWith(item.href)
          ? "bg-theme-active text-theme-primary"
          : "text-theme-muted hover:text-theme-primary hover:bg-theme-hover"
      }`}
    >
      <item.icon className="w-4 h-4 mt-0.5 shrink-0" aria-hidden="true" />
      <div className="min-w-0">
        <p className="text-sm font-medium leading-tight">{item.label}</p>
        {item.desc && <p className="text-xs text-theme-subtle mt-0.5 leading-tight">{item.desc}</p>}
      </div>
    </Button>
  );

  // Left column: Activity items with optional sub-group dividers
  const renderActivityColumn = () => {
    if (activityItems.length === 0) return null;
    return (
      <div className="p-2">
        <p className="px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-theme-subtle">
          {t("sections.activity")}
        </p>
        <div>
          {activityItems.map((item, i) => (
            <div key={item.href}>
              {item.dividerBefore && i > 0 && (
                <div className="my-1.5 mx-3 border-t border-[var(--border-default)] opacity-40" />
              )}
              {renderItem(item)}
            </div>
          ))}
        </div>
      </div>
    );
  };

  // Right column: About items + collapsible Partner Communities section
  const renderRightColumn = () => {
    const hasAbout = aboutItems.length > 0;
    const hasFederation = federationItems.length > 0;
    if (!hasAbout && !hasFederation) return null;
    return (
      <div className="p-2 border-l border-[var(--border-default)]">
        {hasAbout && (
          <>
            <p className="px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-theme-subtle">
              {t("sections.about")}
            </p>
            <div className="space-y-0.5">
              {aboutItems.map(item => renderItem(item))}
            </div>
          </>
        )}
        {hasFederation && (
          <div className={hasAbout ? "mt-3" : ""}>
            {hasAbout && (
              <div className="mx-3 mb-2 border-t border-[var(--border-default)] opacity-40" />
            )}
            <button
              type="button"
              onClick={() => setFederationExpanded(prev => !prev)}
              aria-expanded={federationExpanded}
              className="w-full flex items-center justify-between px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-theme-subtle hover:text-theme-primary transition-colors motion-reduce:transition-none rounded-md hover:bg-theme-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500/50"
            >
              <span>{t("sections.partner_communities")}</span>
              <ChevronDown
                className={"w-3 h-3 transition-transform duration-200 motion-reduce:transition-none " + (federationExpanded ? "rotate-180" : "")}
                aria-hidden="true"
              />
            </button>
            {federationExpanded && (
              <div className="space-y-0.5 mt-0.5">
                {federationItems.map(item => renderItem(item))}
              </div>
            )}
          </div>
        )}
      </div>
    );
  };

  return (
    <>
    {/* Screen reader announcement for menu state changes */}
    <div className="sr-only" aria-live="polite" aria-atomic="true">
      {isOpen ? t('accessibility.menu_opened', 'Navigation menu opened') : ''}
    </div>
    <Popover
      placement="bottom-start"
      isOpen={isOpen}
      onOpenChange={onOpenChange}
      shouldBlockScroll={false}
      offset={8}
      motionProps={reducedMotion ? { initial: { opacity: 1 }, animate: { opacity: 1 }, exit: { opacity: 0 }, transition: { duration: 0 } } : undefined}
    >
      <PopoverTrigger>
        <Button
          variant="light"
          size="sm"
          aria-expanded={isOpen}
          aria-haspopup="true"
          className={`flex items-center gap-1 px-3 py-2 text-sm font-medium transition-all ${
            isActive
              ? 'bg-theme-active text-theme-primary'
              : 'text-theme-muted hover:text-theme-primary hover:bg-theme-hover'
          }`}
          endContent={<ChevronDown className="w-3 h-3" aria-hidden="true" />}
        >
          <Menu className="w-4 h-4" aria-hidden="true" />
          {t('nav.more')}
        </Button>
      </PopoverTrigger>
      <PopoverContent className="p-0 bg-[var(--surface-dropdown)] border border-[var(--border-default)] shadow-2xl rounded-xl max-h-[75vh] max-w-[90vw] overflow-y-auto">
        <nav
          ref={menuRef}
          className="grid grid-cols-2 gap-0 p-2 min-w-[500px]"
          aria-label="More navigation"
          onKeyDown={handleKeyDown}
        >
          {renderActivityColumn()}
          {renderRightColumn()}
        </nav>
      </PopoverContent>
    </Popover>
    </>
  );
}

export default MegaMenu;
