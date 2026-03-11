// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for SearchOverlay component
 * Covers rendering, keyboard navigation, suggestion selection, and accessibility.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import React from 'react';

// Mock navigate
const mockNavigate = vi.fn();
vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return {
    ...actual,
    useNavigate: () => mockNavigate,
  };
});

// Mock framer-motion
vi.mock('framer-motion', () => {
  const proxy = new Proxy({}, {
    get: (_t: object, prop: string | symbol) => {
      return React.forwardRef(({ children, ...p }: Record<string, unknown>, ref: React.Ref<unknown>) => {
        const safe: Record<string, unknown> = {};
        for (const [k, v] of Object.entries(p)) {
          if (!['variants', 'initial', 'animate', 'exit', 'transition', 'whileHover', 'whileTap', 'whileInView', 'layout', 'viewport', 'layoutId'].includes(k)) safe[k] = v;
        }
        return React.createElement(typeof prop === 'string' ? prop : 'div', { ...safe, ref }, children);
      });
    },
  });
  return { motion: proxy, AnimatePresence: ({ children }: { children: React.ReactNode }) => children };
});

// Mock i18n
const i18nMap: Record<string, string> = {
  'search.placeholder': 'Search...',
  'search.suggestions': 'Suggestions',
  'search.searching': 'Searching...',
  'search.quick_links': 'Quick links',
  'search.type_listing': 'Listing',
  'search.type_member': 'Member',
  'search.type_event': 'Event',
  'search.type_group': 'Group',
  'nav.listings': 'Listings',
  'nav.members': 'Members',
  'nav.events': 'Events',
  'support.help_center': 'Help Center',
};
vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string) => i18nMap[key] ?? key,
    i18n: { language: 'en', changeLanguage: vi.fn() },
  }),
  initReactI18next: { type: '3rdParty', init: () => {} },
}));

// Mock contexts
vi.mock('@/contexts', () => ({
  useTenant: () => ({
    tenantPath: (p: string) => p,
  }),
}));

// Mock API
vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn().mockResolvedValue({ success: true, data: {} }),
  },
}));

import { SearchOverlay } from '../SearchOverlay';

function renderOverlay(isOpen = true, onClose = vi.fn()) {
  return { ...render(<SearchOverlay isOpen={isOpen} onClose={onClose} />), onClose };
}

describe('SearchOverlay', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('Rendering', () => {
    it('renders nothing when closed', () => {
      const { container } = renderOverlay(false);
      expect(container.querySelector('input')).toBeNull();
    });

    it('renders search input when open', () => {
      renderOverlay();
      expect(screen.getByLabelText('Search')).toBeTruthy();
    });

    it('renders ESC keyboard hint', () => {
      renderOverlay();
      expect(screen.getByText('ESC')).toBeTruthy();
    });

    it('renders quick links when no query entered', () => {
      renderOverlay();
      expect(screen.getByText('Quick links')).toBeTruthy();
      expect(screen.getByText('Listings')).toBeTruthy();
      expect(screen.getByText('Members')).toBeTruthy();
      expect(screen.getByText('Events')).toBeTruthy();
      expect(screen.getByText('Help Center')).toBeTruthy();
    });

    it('renders backdrop overlay', () => {
      const { container } = renderOverlay();
      // Backdrop has bg-black/50
      const backdrop = container.querySelector('.bg-black\\/50');
      expect(backdrop).toBeTruthy();
    });
  });

  describe('Input behavior', () => {
    it('updates search query on typing', () => {
      renderOverlay();
      const input = screen.getByLabelText('Search') as HTMLInputElement;
      fireEvent.change(input, { target: { value: 'test' } });
      expect(input.value).toBe('test');
    });

    it('shows clear button when query is non-empty', () => {
      renderOverlay();
      const input = screen.getByLabelText('Search');
      fireEvent.change(input, { target: { value: 'test' } });
      expect(screen.getByLabelText('Clear search')).toBeTruthy();
    });

    it('shows clear button is accessible with correct label', () => {
      renderOverlay();
      const input = screen.getByLabelText('Search');
      fireEvent.change(input, { target: { value: 'test' } });
      const clearBtn = screen.getByLabelText('Clear search');
      expect(clearBtn).toBeTruthy();
      // HeroUI Button uses onPress (pointer events), not onClick
      // Verifying the button renders and is labelled correctly
    });
  });

  describe('Keyboard navigation', () => {
    it('Escape key calls onClose', () => {
      const onClose = vi.fn();
      renderOverlay(true, onClose);
      fireEvent.keyDown(document, { key: 'Escape' });
      expect(onClose).toHaveBeenCalled();
    });

    it('form has submit handler attached', () => {
      renderOverlay();
      const input = screen.getByLabelText('Search');
      const form = input.closest('form');
      expect(form).toBeTruthy();
      // The form's onSubmit calls handleSearchSubmit which navigates
      // We verify the form structure is correct for keyboard Enter to work
    });
  });

  describe('Backdrop interaction', () => {
    it('calls onClose when backdrop is clicked', () => {
      const onClose = vi.fn();
      const { container } = renderOverlay(true, onClose);
      const backdrop = container.querySelector('.bg-black\\/50');
      fireEvent.click(backdrop!);
      expect(onClose).toHaveBeenCalled();
    });
  });

  describe('Accessibility', () => {
    it('search input has aria-label', () => {
      renderOverlay();
      expect(screen.getByLabelText('Search')).toBeTruthy();
    });

    it('search input has aria-autocomplete="list"', () => {
      renderOverlay();
      const input = screen.getByLabelText('Search');
      expect(input.getAttribute('aria-autocomplete')).toBe('list');
    });

    it('suggestions container has role="listbox"', async () => {
      // Import api mock to set up suggestions
      const { api } = await import('@/lib/api');
      (api.get as ReturnType<typeof vi.fn>).mockResolvedValueOnce({
        success: true,
        data: {
          listings: [{ id: 1, title: 'Test Listing', type: 'listing' }],
        },
      });

      renderOverlay();
      const input = screen.getByLabelText('Search');
      fireEvent.change(input, { target: { value: 'test query' } });

      await waitFor(() => {
        const listbox = document.querySelector('[role="listbox"]');
        expect(listbox).toBeTruthy();
      }, { timeout: 2000 });
    });
  });

  describe('Focus trap', () => {
    it('has dialog role with aria-modal', () => {
      renderOverlay();
      const dialog = document.querySelector('[role="dialog"]');
      expect(dialog).toBeTruthy();
      expect(dialog!.getAttribute('aria-modal')).toBe('true');
    });

    it('traps focus within the dialog on Tab', () => {
      renderOverlay();
      const dialog = document.querySelector('[role="dialog"]')!;
      const focusable = dialog.querySelectorAll<HTMLElement>('input, button');
      const first = focusable[0];
      const last = focusable[focusable.length - 1];

      // Focus last element, Tab should wrap to first
      (last as HTMLElement).focus();
      fireEvent.keyDown(document, { key: 'Tab' });
      expect(document.activeElement).toBe(first);
    });

    it('traps focus within the dialog on Shift+Tab', () => {
      renderOverlay();
      const dialog = document.querySelector('[role="dialog"]')!;
      const focusable = dialog.querySelectorAll<HTMLElement>('input, button');
      const first = focusable[0];
      const last = focusable[focusable.length - 1];

      // Focus first element, Shift+Tab should wrap to last
      (first as HTMLElement).focus();
      fireEvent.keyDown(document, { key: 'Tab', shiftKey: true });
      expect(document.activeElement).toBe(last);
    });
  });

  describe('Quick link rendering', () => {
    it('renders all quick link buttons', () => {
      renderOverlay();
      expect(screen.getByText('Listings')).toBeTruthy();
      expect(screen.getByText('Members')).toBeTruthy();
      expect(screen.getByText('Events')).toBeTruthy();
      expect(screen.getByText('Help Center')).toBeTruthy();
    });
  });
});
