// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Mega Menu
 * Multi-column popover menu for the "More" navigation dropdown.
 * Supports keyboard navigation (arrow keys + Enter) and focus management.
 */

import { useRef, useEffect, useCallback, useMemo } from 'react';
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

  // Flatten all items for keyboard navigation
  const allItems = [...activityItems, ...federationItems, ...aboutItems];

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

  // Keyboard navigation within the mega menu
  const handleKeyDown = useCallback((e: React.KeyboardEvent) => {
    if (!menuRef.current) return;

    const buttons = Array.from(menuRef.current.querySelectorAll<HTMLButtonElement>('button[data-mega-item]'));
    const currentIndex = buttons.indexOf(document.activeElement as HTMLButtonElement);

    if (e.key === 'ArrowDown') {
      e.preventDefault();
      const next = currentIndex < buttons.length - 1 ? currentIndex + 1 : 0;
      buttons[next]?.focus();
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      const prev = currentIndex > 0 ? currentIndex - 1 : buttons.length - 1;
      buttons[prev]?.focus();
    } else if (e.key === 'ArrowRight') {
      e.preventDefault();
      // Jump to next column
      const columnStarts = [0, activityItems.length, activityItems.length + federationItems.length];
      const currentCol = columnStarts.findIndex((start, i) =>
        currentIndex >= start && (i === columnStarts.length - 1 || currentIndex < columnStarts[i + 1])
      );
      if (currentCol < columnStarts.length - 1) {
        const nextColStart = columnStarts[currentCol + 1];
        if (nextColStart < buttons.length) buttons[nextColStart]?.focus();
      }
    } else if (e.key === 'ArrowLeft') {
      e.preventDefault();
      const columnStarts = [0, activityItems.length, activityItems.length + federationItems.length];
      const currentCol = columnStarts.findIndex((start, i) =>
        currentIndex >= start && (i === columnStarts.length - 1 || currentIndex < columnStarts[i + 1])
      );
      if (currentCol > 0) {
        const prevColStart = columnStarts[currentCol - 1];
        buttons[prevColStart]?.focus();
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
  }, [activityItems.length, federationItems.length, allItems.length, onOpenChange]);

  // Respect prefers-reduced-motion for popover animation
  const reducedMotion = useMemo(() => {
    if (typeof window === 'undefined') return false;
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }, []);

  const hasFederation = federationItems.length > 0;
  const columnCount = hasFederation ? 3 : 2;

  const renderColumn = (items: MegaMenuItem[], title: string, showBorder: boolean) => {
    if (items.length === 0) return null;
    return (
      <div className={`p-2 ${showBorder ? 'border-l border-[var(--border-default)]' : ''}`}>
        <p className="px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-theme-subtle">{title}</p>
        <div className="space-y-0.5">
          {items.map((item) => (
            <Button
              key={item.href}
              data-mega-item
              onPress={() => onNavigate(item.href)}
              variant="light"
              className={`w-full flex items-start gap-3 px-3 py-2 rounded-lg text-left transition-colors motion-reduce:transition-none focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500/50 h-auto justify-start ${
                location.pathname.startsWith(item.href)
                  ? 'bg-theme-active text-theme-primary'
                  : 'text-theme-muted hover:text-theme-primary hover:bg-theme-hover'
              }`}
            >
              <item.icon className="w-4 h-4 mt-0.5 shrink-0" aria-hidden="true" />
              <div className="min-w-0">
                <p className="text-sm font-medium leading-tight">{item.label}</p>
                {item.desc && <p className="text-xs text-theme-subtle mt-0.5 leading-tight">{item.desc}</p>}
              </div>
            </Button>
          ))}
        </div>
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
          className={`grid gap-0 p-2 ${
            columnCount === 3 ? 'grid-cols-3 min-w-[640px]' : 'grid-cols-2 min-w-[480px]'
          }`}
          aria-label="More navigation"
          onKeyDown={handleKeyDown}
        >
          {renderColumn(activityItems, t('sections.activity'), false)}
          {renderColumn(federationItems, t('sections.partner_communities'), activityItems.length > 0)}
          {renderColumn(aboutItems, t('sections.about'), activityItems.length > 0 || federationItems.length > 0)}
        </nav>
      </PopoverContent>
    </Popover>
    </>
  );
}

export default MegaMenu;
