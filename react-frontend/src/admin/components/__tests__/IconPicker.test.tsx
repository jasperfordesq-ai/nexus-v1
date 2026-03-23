// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for IconPicker — icon selection modal with search
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { HeroUIProvider } from '@heroui/react';

// ─── Stable mock references ─────────────────────────────────────────────────

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    user: { id: 1, name: 'Admin User', role: 'admin' },
    isAuthenticated: true,
    logout: vi.fn(),
  })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Community', slug: 'test', configuration: {} },
    tenantSlug: 'test',
    branding: { name: 'Test Community' },
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
    tenantPath: (p: string) => `/test${p}`,
  })),
  useToast: vi.fn(() => ({
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
    showToast: vi.fn(),
  })),
  useTheme: () => ({ resolvedTheme: 'light', toggleTheme: vi.fn(), theme: 'system', setTheme: vi.fn() }),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
}));

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// Mock the DynamicIcon and ICON_MAP/ICON_NAMES from @/components/ui
// vi.mock is hoisted, so we cannot reference top-level variables. Define everything inline.
vi.mock('@/components/ui', () => {
  const IconStub = (props: Record<string, unknown>) => <span data-testid="mock-icon" {...props} />;
  return {
    ICON_MAP: {
      Home: IconStub,
      Users: IconStub,
      Settings: IconStub,
      Search: IconStub,
    } as Record<string, typeof IconStub>,
    ICON_NAMES: ['Home', 'Users', 'Settings', 'Search'],
    DynamicIcon: ({ name, ...props }: { name: string } & Record<string, unknown>) => (
      <span data-testid={`dynamic-icon-${name}`} {...props} />
    ),
  };
});

import { IconPicker } from '../IconPicker';

// ─── Wrapper ─────────────────────────────────────────────────────────────────

function W({ children }: { children: React.ReactNode }) {
  return (
    <HeroUIProvider>
      <MemoryRouter initialEntries={['/test/admin']}>
        {children}
      </MemoryRouter>
    </HeroUIProvider>
  );
}

// ─── Tests ───────────────────────────────────────────────────────────────────

describe('IconPicker', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders without crashing', () => {
    const onChange = vi.fn();
    const { container } = render(
      <W><IconPicker value={null} onChange={onChange} /></W>
    );
    expect(container).toBeTruthy();
  });

  it('renders the label', () => {
    const onChange = vi.fn();
    render(<W><IconPicker value={null} onChange={onChange} label="Choose Icon" /></W>);
    expect(screen.getByText('Choose Icon')).toBeTruthy();
  });

  it('renders default label "Icon" when no label provided', () => {
    const onChange = vi.fn();
    render(<W><IconPicker value={null} onChange={onChange} /></W>);
    expect(screen.getByText('Icon')).toBeTruthy();
  });

  it('shows placeholder text when no value selected', () => {
    const onChange = vi.fn();
    render(<W><IconPicker value={null} onChange={onChange} /></W>);
    // t('icon_picker.choose_icon_placeholder') returns fallback key
    expect(screen.getByText('icon_picker.choose_icon_placeholder')).toBeTruthy();
  });

  it('shows icon name when a value is selected', () => {
    const onChange = vi.fn();
    render(<W><IconPicker value="Home" onChange={onChange} /></W>);
    expect(screen.getByText('Home')).toBeTruthy();
  });

  it('shows clear button when a value is selected', () => {
    const onChange = vi.fn();
    render(<W><IconPicker value="Home" onChange={onChange} /></W>);
    // t('icon_picker.clear_icon') returns fallback key
    const clearBtn = screen.getByLabelText('icon_picker.clear_icon');
    expect(clearBtn).toBeTruthy();
  });

  it('does not show clear button when no value', () => {
    const onChange = vi.fn();
    render(<W><IconPicker value={null} onChange={onChange} /></W>);
    expect(screen.queryByLabelText('icon_picker.clear_icon')).toBeNull();
  });

  it('calls onChange with null when clear button is clicked', () => {
    const onChange = vi.fn();
    render(<W><IconPicker value="Home" onChange={onChange} /></W>);
    fireEvent.click(screen.getByLabelText('icon_picker.clear_icon'));
    expect(onChange).toHaveBeenCalledWith(null);
  });

  it('renders DynamicIcon when value is set', () => {
    const onChange = vi.fn();
    render(<W><IconPicker value="Home" onChange={onChange} /></W>);
    expect(screen.getByTestId('dynamic-icon-Home')).toBeTruthy();
  });
});
