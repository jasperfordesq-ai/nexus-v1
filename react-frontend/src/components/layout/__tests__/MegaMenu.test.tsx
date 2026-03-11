// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for MegaMenu component
 * Covers rendering, keyboard navigation, focus management, and accessibility.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import React from 'react';
import type { LucideIcon } from 'lucide-react';

// Mock i18n
vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string) => {
      const map: Record<string, string> = {
        'nav.more': 'More',
        'sections.activity': 'Activity',
        'sections.partner_communities': 'Partner Communities',
        'sections.about': 'About',
      };
      return map[key] ?? key;
    },
    i18n: { language: 'en', changeLanguage: vi.fn() },
  }),
  initReactI18next: { type: '3rdParty', init: () => {} },
}));

// Stub icon component
const StubIcon: LucideIcon = React.forwardRef((props: Record<string, unknown>, ref: React.Ref<SVGSVGElement>) =>
  React.createElement('svg', { ...props, ref, 'data-testid': 'icon' })
) as unknown as LucideIcon;
(StubIcon as { displayName: string }).displayName = 'StubIcon';

import { MegaMenu } from '../MegaMenu';
import type { MegaMenuSection } from '../MegaMenu';

const leftSections: MegaMenuSection[] = [
  {
    key: 'core',
    title: 'Core',
    items: [
      { label: 'Volunteering', href: '/volunteering', icon: StubIcon },
      { label: 'Goals', href: '/goals', icon: StubIcon },
    ],
  },
  {
    key: 'progress',
    title: 'Progress',
    collapsible: true,
    defaultExpanded: false,
    items: [
      { label: 'Achievements', href: '/achievements', icon: StubIcon },
    ],
  },
];

const rightSections: MegaMenuSection[] = [
  {
    key: 'about',
    title: 'About',
    items: [
      { label: 'About Us', href: '/about', icon: StubIcon },
      { label: 'Contact', href: '/contact', icon: StubIcon },
    ],
  },
  {
    key: 'federation',
    title: 'Partner Communities',
    collapsible: true,
    defaultExpanded: false,
    items: [
      { label: 'Partner Hub', href: '/federation', icon: StubIcon },
    ],
  },
];

// Count items that are visible by default (non-collapsible sections only)
const visibleLeftCount = leftSections.filter(s => !s.collapsible).reduce((n, s) => n + s.items.length, 0);
const visibleRightCount = rightSections.filter(s => !s.collapsible).reduce((n, s) => n + s.items.length, 0);
const visibleCount = visibleLeftCount + visibleRightCount;

function renderMegaMenu(overrides: Partial<React.ComponentProps<typeof MegaMenu>> = {}) {
  const defaultProps = {
    isOpen: true,
    onOpenChange: vi.fn(),
    isActive: false,
    leftSections,
    rightSections,
    onNavigate: vi.fn(),
    ...overrides,
  };
  return { ...render(
    <MemoryRouter>
      <MegaMenu {...defaultProps} />
    </MemoryRouter>
  ), props: defaultProps };
}

describe('MegaMenu', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('Rendering', () => {
    it('renders the More trigger button', () => {
      renderMegaMenu({ isOpen: false });
      expect(screen.getByText('More')).toBeTruthy();
    });

    it('renders section headings when open', () => {
      renderMegaMenu();
      expect(screen.getByText('Core')).toBeTruthy();
      expect(screen.getByText('Progress')).toBeTruthy();
      expect(screen.getByText('Partner Communities')).toBeTruthy();
      expect(screen.getAllByText('About').length).toBeGreaterThanOrEqual(1);
    });

    it('renders non-collapsible section items by default', () => {
      renderMegaMenu();
      expect(screen.getByText('Volunteering')).toBeTruthy();
      expect(screen.getByText('Goals')).toBeTruthy();
      expect(screen.getByText('About Us')).toBeTruthy();
      expect(screen.getByText('Contact')).toBeTruthy();
    });

    it('hides collapsible section items by default', () => {
      renderMegaMenu();
      expect(screen.queryByText('Achievements')).toBeNull();
      expect(screen.queryByText('Partner Hub')).toBeNull();
    });

    it('renders 2-column grid', () => {
      renderMegaMenu();
      const nav = document.querySelector('nav[aria-label="More navigation"]');
      expect(nav).toBeTruthy();
      expect(nav!.className).toContain('grid-cols-2');
    });

    it('renders item descriptions when provided', () => {
      renderMegaMenu({
        leftSections: [{
          key: 'test',
          title: 'Test',
          items: [
            { label: 'Volunteering', desc: 'Help your community', href: '/volunteering', icon: StubIcon },
          ],
        }],
      });
      expect(screen.getByText('Help your community')).toBeTruthy();
    });
  });

  describe('Navigation', () => {
    it('calls onNavigate when a menu item is clicked', () => {
      const { props } = renderMegaMenu();
      fireEvent.click(screen.getByText('Volunteering'));
      expect(props.onNavigate).toHaveBeenCalledWith('/volunteering');
    });

    it('calls onNavigate with correct href for each item', () => {
      const { props } = renderMegaMenu();
      fireEvent.click(screen.getByText('Contact'));
      expect(props.onNavigate).toHaveBeenCalledWith('/contact');
    });
  });

  describe('Keyboard navigation', () => {
    function getMegaButtons() {
      return Array.from(document.querySelectorAll<HTMLButtonElement>('button[data-mega-item]'));
    }

    it('ArrowDown moves focus to next item', () => {
      renderMegaMenu();
      const buttons = getMegaButtons();
      buttons[0]?.focus();
      const nav = document.querySelector('nav[aria-label="More navigation"]')!;
      fireEvent.keyDown(nav, { key: 'ArrowDown' });
      expect(document.activeElement).toBe(buttons[1]);
    });

    it('ArrowDown wraps from last to first item', () => {
      renderMegaMenu();
      const buttons = getMegaButtons();
      buttons[buttons.length - 1]?.focus();
      const nav = document.querySelector('nav[aria-label="More navigation"]')!;
      fireEvent.keyDown(nav, { key: 'ArrowDown' });
      expect(document.activeElement).toBe(buttons[0]);
    });

    it('ArrowUp moves focus to previous item', () => {
      renderMegaMenu();
      const buttons = getMegaButtons();
      buttons[1]?.focus();
      const nav = document.querySelector('nav[aria-label="More navigation"]')!;
      fireEvent.keyDown(nav, { key: 'ArrowUp' });
      expect(document.activeElement).toBe(buttons[0]);
    });

    it('ArrowUp wraps from first to last item', () => {
      renderMegaMenu();
      const buttons = getMegaButtons();
      buttons[0]?.focus();
      const nav = document.querySelector('nav[aria-label="More navigation"]')!;
      fireEvent.keyDown(nav, { key: 'ArrowUp' });
      expect(document.activeElement).toBe(buttons[buttons.length - 1]);
    });

    it('ArrowRight jumps to right column start', () => {
      renderMegaMenu();
      const buttons = getMegaButtons();
      buttons[0]?.focus();
      const nav = document.querySelector('nav[aria-label="More navigation"]')!;
      fireEvent.keyDown(nav, { key: 'ArrowRight' });
      // Right column starts after all visible left items
      expect(document.activeElement).toBe(buttons[visibleLeftCount]);
    });

    it('ArrowLeft jumps to left column start', () => {
      renderMegaMenu();
      const buttons = getMegaButtons();
      buttons[visibleLeftCount]?.focus();
      const nav = document.querySelector('nav[aria-label="More navigation"]')!;
      fireEvent.keyDown(nav, { key: 'ArrowLeft' });
      expect(document.activeElement).toBe(buttons[0]);
    });

    it('Home moves focus to first item', () => {
      renderMegaMenu();
      const buttons = getMegaButtons();
      buttons[2]?.focus();
      const nav = document.querySelector('nav[aria-label="More navigation"]')!;
      fireEvent.keyDown(nav, { key: 'Home' });
      expect(document.activeElement).toBe(buttons[0]);
    });

    it('End moves focus to last item', () => {
      renderMegaMenu();
      const buttons = getMegaButtons();
      buttons[0]?.focus();
      const nav = document.querySelector('nav[aria-label="More navigation"]')!;
      fireEvent.keyDown(nav, { key: 'End' });
      expect(document.activeElement).toBe(buttons[buttons.length - 1]);
    });

    it('Escape calls onOpenChange(false)', () => {
      const { props } = renderMegaMenu();
      const nav = document.querySelector('nav[aria-label="More navigation"]')!;
      fireEvent.keyDown(nav, { key: 'Escape' });
      expect(props.onOpenChange).toHaveBeenCalledWith(false);
    });
  });

  describe('Accessibility', () => {
    it('has nav element with aria-label', () => {
      renderMegaMenu();
      expect(screen.getByRole('navigation', { name: 'More navigation' })).toBeTruthy();
    });

    it('only visible items have data-mega-item attribute', () => {
      renderMegaMenu();
      const buttons = document.querySelectorAll('button[data-mega-item]');
      expect(buttons.length).toBe(visibleCount);
    });

    it('icons have aria-hidden="true"', () => {
      renderMegaMenu();
      const icons = document.querySelectorAll('[data-testid="icon"]');
      icons.forEach(icon => {
        expect(icon.getAttribute('aria-hidden')).toBe('true');
      });
    });
  });

  describe('Active state', () => {
    it('highlights item matching current location', () => {
      render(
        <MemoryRouter initialEntries={['/volunteering']}>
          <MegaMenu
            isOpen={true}
            onOpenChange={vi.fn()}
            isActive={false}
            leftSections={leftSections}
            rightSections={rightSections.filter(s => s.key === 'about')}
            onNavigate={vi.fn()}
          />
        </MemoryRouter>
      );
      const volButton = screen.getByText('Volunteering').closest('button');
      expect(volButton?.className).toContain('bg-theme-active');
    });

    it('does not highlight non-matching items', () => {
      render(
        <MemoryRouter initialEntries={['/volunteering']}>
          <MegaMenu
            isOpen={true}
            onOpenChange={vi.fn()}
            isActive={false}
            leftSections={leftSections}
            rightSections={rightSections.filter(s => s.key === 'about')}
            onNavigate={vi.fn()}
          />
        </MemoryRouter>
      );
      const aboutButton = screen.getByText('Contact').closest('button');
      expect(aboutButton?.className).not.toContain('bg-theme-active');
    });
  });

  describe('Collapsible sections', () => {
    it('hides collapsible section items by default', () => {
      renderMegaMenu();
      expect(screen.queryByText('Achievements')).toBeNull();
      expect(screen.queryByText('Partner Hub')).toBeNull();
    });

    it('shows items after clicking the section toggle', () => {
      renderMegaMenu();
      const toggle = screen.getByText('Progress').closest('button')!;
      fireEvent.click(toggle);
      expect(screen.getByText('Achievements')).toBeTruthy();
    });

    it('toggle has aria-expanded=false by default', () => {
      renderMegaMenu();
      const toggle = screen.getByText('Progress').closest('button')!;
      expect(toggle.getAttribute('aria-expanded')).toBe('false');
    });

    it('toggle has aria-expanded=true after clicking', () => {
      renderMegaMenu();
      const toggle = screen.getByText('Progress').closest('button')!;
      fireEvent.click(toggle);
      expect(toggle.getAttribute('aria-expanded')).toBe('true');
    });

    it('federation toggle works independently', () => {
      renderMegaMenu();
      const fedToggle = screen.getByText('Partner Communities').closest('button')!;
      fireEvent.click(fedToggle);
      expect(screen.getByText('Partner Hub')).toBeTruthy();
      // Progress should still be collapsed
      expect(screen.queryByText('Achievements')).toBeNull();
    });

    it('shows section with defaultExpanded=true', () => {
      renderMegaMenu({
        leftSections: [{
          key: 'expanded',
          title: 'Expanded',
          collapsible: true,
          defaultExpanded: true,
          items: [{ label: 'Visible Item', href: '/visible', icon: StubIcon }],
        }],
      });
      expect(screen.getByText('Visible Item')).toBeTruthy();
    });
  });
});
