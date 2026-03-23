// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for AdvancedSearchFilters component.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { HeroUIProvider } from '@heroui/react';

// ─── Mocks ──────────────────────────────────────────────────────────────────

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, fallback?: string) => fallback ?? key,
    i18n: { language: 'en', changeLanguage: vi.fn() },
  }),
  initReactI18next: { type: '3rdParty', init: () => {} },
}));

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({ user: { id: 1 }, isAuthenticated: true })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test', slug: 'test' },
    tenantSlug: 'test',
    branding: { name: 'Test' },
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
    tenantPath: (p: string) => `/test${p}`,
  })),
  useToast: vi.fn(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn() })),
  useTheme: vi.fn(() => ({ resolvedTheme: 'light', theme: 'light', setTheme: vi.fn() })),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
}));

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn().mockResolvedValue({ success: true, data: [] }),
  },
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

import { AdvancedSearchFilters, defaultFilters } from '../AdvancedSearchFilters';
import type { SearchFilters } from '../AdvancedSearchFilters';

function W({ children }: { children: React.ReactNode }) {
  return (
    <HeroUIProvider>
      <MemoryRouter>{children}</MemoryRouter>
    </HeroUIProvider>
  );
}

const baseFilters: SearchFilters = { ...defaultFilters };

describe('AdvancedSearchFilters', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders without crashing', () => {
    const { container } = render(
      <W>
        <AdvancedSearchFilters
          filters={baseFilters}
          onChange={vi.fn()}
          onApply={vi.fn()}
          onReset={vi.fn()}
        />
      </W>,
    );
    expect(container).toBeTruthy();
  });

  it('renders toggle button with "advanced_filters" text', () => {
    render(
      <W>
        <AdvancedSearchFilters
          filters={baseFilters}
          onChange={vi.fn()}
          onApply={vi.fn()}
          onReset={vi.fn()}
        />
      </W>,
    );
    expect(screen.getByText('advanced_filters')).toBeInTheDocument();
  });

  it('does not show filter panel initially', () => {
    render(
      <W>
        <AdvancedSearchFilters
          filters={baseFilters}
          onChange={vi.fn()}
          onApply={vi.fn()}
          onReset={vi.fn()}
        />
      </W>,
    );
    expect(screen.queryByText('filter_apply')).not.toBeInTheDocument();
  });

  it('expands filter panel on button click', () => {
    render(
      <W>
        <AdvancedSearchFilters
          filters={baseFilters}
          onChange={vi.fn()}
          onApply={vi.fn()}
          onReset={vi.fn()}
        />
      </W>,
    );
    fireEvent.click(screen.getByText('advanced_filters'));
    expect(screen.getByText('filter_apply')).toBeInTheDocument();
    expect(screen.getByText('filter_reset')).toBeInTheDocument();
  });

  it('shows active filter count badge when filters differ from defaults', () => {
    const activeFilters: SearchFilters = {
      ...baseFilters,
      type: 'listings',
      sort: 'newest',
    };
    render(
      <W>
        <AdvancedSearchFilters
          filters={activeFilters}
          onChange={vi.fn()}
          onApply={vi.fn()}
          onReset={vi.fn()}
        />
      </W>,
    );
    expect(screen.getByText('2')).toBeInTheDocument();
  });

  it('calls onApply when Apply button is clicked', () => {
    const onApply = vi.fn();
    render(
      <W>
        <AdvancedSearchFilters
          filters={baseFilters}
          onChange={vi.fn()}
          onApply={onApply}
          onReset={vi.fn()}
        />
      </W>,
    );
    fireEvent.click(screen.getByText('advanced_filters'));
    fireEvent.click(screen.getByText('filter_apply'));
    expect(onApply).toHaveBeenCalled();
  });

  it('calls onReset when Reset button is clicked', () => {
    const onReset = vi.fn();
    const onChange = vi.fn();
    render(
      <W>
        <AdvancedSearchFilters
          filters={baseFilters}
          onChange={onChange}
          onApply={vi.fn()}
          onReset={onReset}
        />
      </W>,
    );
    fireEvent.click(screen.getByText('advanced_filters'));
    fireEvent.click(screen.getByText('filter_reset'));
    expect(onReset).toHaveBeenCalled();
    expect(onChange).toHaveBeenCalledWith(defaultFilters);
  });

  it('has correct aria-label on toggle button', () => {
    render(
      <W>
        <AdvancedSearchFilters
          filters={baseFilters}
          onChange={vi.fn()}
          onApply={vi.fn()}
          onReset={vi.fn()}
        />
      </W>,
    );
    expect(screen.getByLabelText('advanced_filters')).toBeInTheDocument();
  });
});
