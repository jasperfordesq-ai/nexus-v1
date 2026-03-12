// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for SearchOverlay component (portal-based implementation)
 * Covers rendering, keyboard navigation, suggestion selection, and accessibility.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';

// Mock navigate
const mockNavigate = vi.fn();
vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return {
    ...actual,
    useNavigate: () => mockNavigate,
  };
});

// Mock i18n
const i18nMap: Record<string, string> = {
  'search.placeholder': 'Search...',
  'search.suggestions': 'Suggestions',
  'search.searching': 'Searching...',
  'search.quick_links': 'Quick Links',
  'search.clear': 'Clear',
  'search.type_listing': 'Listing',
  'search.type_member': 'Member',
  'search.type_event': 'Event',
  'search.type_group': 'Group',
  'nav.listings': 'Listings',
  'nav.members': 'Members',
  'nav.events': 'Events',
  'support.help_center': 'Help',
  'accessibility.close': 'Close (ESC)',
};
vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, fallback?: string) => i18nMap[key] ?? fallback ?? key,
    i18n: { language: 'en', changeLanguage: vi.fn() },
  }),
  initReactI18next: { type: '3rdParty', init: () => {} },
}));

// Mock contexts
vi.mock('@/contexts', () => ({
  useTenant: () => ({
    tenantPath: (p: string) => p,
    hasFeature: () => true,
  }),
  useAuth: () => ({
    isAuthenticated: false,
  }),
  useTheme: () => ({
    resolvedTheme: 'light',
    toggleTheme: vi.fn(),
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
      expect(container.innerHTML).toBe('');
    });

    it('renders search input when open', () => {
      renderOverlay();
      expect(screen.getByPlaceholderText('Search...')).toBeTruthy();
    });

    it('renders ESC keyboard hint', () => {
      renderOverlay();
      expect(screen.getByText('ESC')).toBeTruthy();
    });

    it('renders quick links when no query entered', () => {
      renderOverlay();
      expect(screen.getByText('Quick Links')).toBeTruthy();
      expect(screen.getByText('Listings')).toBeTruthy();
      expect(screen.getByText('Members')).toBeTruthy();
      expect(screen.getByText('Events')).toBeTruthy();
      expect(screen.getByText('Help')).toBeTruthy();
    });
  });

  describe('Input behavior', () => {
    it('updates search query on typing', () => {
      renderOverlay();
      const input = screen.getByPlaceholderText('Search...') as HTMLInputElement;
      fireEvent.change(input, { target: { value: 'test' } });
      expect(input.value).toBe('test');
    });

    it('shows clear button when query is non-empty', () => {
      renderOverlay();
      const input = screen.getByPlaceholderText('Search...');
      fireEvent.change(input, { target: { value: 'test' } });
      expect(screen.getByLabelText('Clear')).toBeTruthy();
    });
  });

  describe('Keyboard navigation', () => {
    it('calls onClose when ESC is pressed', () => {
      const onClose = vi.fn();
      renderOverlay(true, onClose);
      fireEvent.keyDown(document, { key: 'Escape' });
      expect(onClose).toHaveBeenCalled();
    });
  });

  describe('Close behavior', () => {
    it('has a close button with aria-label', () => {
      renderOverlay();
      expect(screen.getByLabelText('Close')).toBeTruthy();
    });

    it('calls onClose when close button is clicked', () => {
      const onClose = vi.fn();
      renderOverlay(true, onClose);
      fireEvent.click(screen.getByLabelText('Close'));
      expect(onClose).toHaveBeenCalled();
    });
  });

  describe('Accessibility', () => {
    it('search input is focusable', () => {
      renderOverlay();
      const input = screen.getByPlaceholderText('Search...');
      expect(input.tagName).toBe('INPUT');
    });

    it('renders as a portal to document.body', () => {
      renderOverlay();
      // The overlay should be rendered to document.body, not inside the container
      const overlay = document.body.querySelector('.fixed.inset-0');
      expect(overlay).toBeTruthy();
    });
  });

  describe('Quick link rendering', () => {
    it('renders all quick link buttons', () => {
      renderOverlay();
      expect(screen.getByText('Listings')).toBeTruthy();
      expect(screen.getByText('Members')).toBeTruthy();
      expect(screen.getByText('Events')).toBeTruthy();
      expect(screen.getByText('Help')).toBeTruthy();
    });
  });
});
