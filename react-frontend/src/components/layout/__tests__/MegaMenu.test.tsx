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

const activityItems = [
  { label: 'Volunteering', href: '/volunteering', icon: StubIcon },
  { label: 'Goals', href: '/goals', icon: StubIcon },
];

const federationItems = [
  { label: 'Partner Hub', href: '/federation', icon: StubIcon },
];

const aboutItems = [
  { label: 'About', href: '/about', icon: StubIcon },
  { label: 'Contact', href: '/contact', icon: StubIcon },
];

function renderMegaMenu(overrides: Partial<React.ComponentProps<typeof MegaMenu>> = {}) {
  const defaultProps = {
    isOpen: true,
    onOpenChange: vi.fn(),
    isActive: false,
    activityItems,
    federationItems,
    aboutItems,
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

    it('renders column headings when open', () => {
      renderMegaMenu();
      expect(screen.getByText('Activity')).toBeTruthy();
      expect(screen.getByText('Partner Communities')).toBeTruthy();
      expect(screen.getAllByText('About').length).toBeGreaterThanOrEqual(1);
    });

    it('renders all menu items', () => {
      renderMegaMenu();
      expect(screen.getByText('Volunteering')).toBeTruthy();
      expect(screen.getByText('Goals')).toBeTruthy();
      expect(screen.getByText('Partner Hub')).toBeTruthy();
      expect(screen.getAllByText('About').length).toBeGreaterThanOrEqual(1);
      expect(screen.getByText('Contact')).toBeTruthy();
    });

    it('renders 3-column grid when federation items exist', () => {
      renderMegaMenu();
      // HeroUI Popover renders content via portal, so check the full document
      const nav = document.querySelector('nav[aria-label="More navigation"]');
      expect(nav).toBeTruthy();
      expect(nav!.className).toContain('grid-cols-3');
    });

    it('renders 2-column grid when no federation items', () => {
      renderMegaMenu({ federationItems: [] });
      const nav = document.querySelector('nav[aria-label="More navigation"]');
      expect(nav).toBeTruthy();
      expect(nav!.className).toContain('grid-cols-2');
    });

    it('renders item descriptions when provided', () => {
      renderMegaMenu({
        activityItems: [
          { label: 'Volunteering', desc: 'Help your community', href: '/volunteering', icon: StubIcon },
        ],
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

    it('ArrowRight jumps to next column start', () => {
      renderMegaMenu();
      const buttons = getMegaButtons();
      // First item in activity column (index 0), ArrowRight should go to federation column (index 2)
      buttons[0]?.focus();
      const nav = document.querySelector('nav[aria-label="More navigation"]')!;
      fireEvent.keyDown(nav, { key: 'ArrowRight' });
      expect(document.activeElement).toBe(buttons[activityItems.length]);
    });

    it('ArrowLeft jumps to previous column start', () => {
      renderMegaMenu();
      const buttons = getMegaButtons();
      // First item in federation column, ArrowLeft should go to activity column start
      const fedStart = activityItems.length;
      buttons[fedStart]?.focus();
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

    it('all menu item buttons have data-mega-item attribute', () => {
      renderMegaMenu();
      const buttons = document.querySelectorAll('button[data-mega-item]');
      expect(buttons.length).toBe(activityItems.length + federationItems.length + aboutItems.length);
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
            activityItems={activityItems}
            federationItems={[]}
            aboutItems={aboutItems}
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
            activityItems={activityItems}
            federationItems={[]}
            aboutItems={aboutItems}
            onNavigate={vi.fn()}
          />
        </MemoryRouter>
      );
      const aboutButton = screen.getByText('Contact').closest('button');
      expect(aboutButton?.className).not.toContain('bg-theme-active');
    });
  });
});
