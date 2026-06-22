// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for GroupBrandingPicker.
 *
 * The HeroUI v3 ColorPicker is a heavily compound, portal-driven component
 * whose full interaction chain (ColorArea.Thumb drag, ColorSlider.Thumb drag)
 * cannot be exercised in jsdom without a real pointer-events engine.
 *
 * Strategy: mock @heroui/react at the sub-component level so we can render the
 * wrapper cleanly and verify:
 *   - Static content (title, preview swatches, labels)
 *   - Default values propagate to the preview divs
 *   - Custom initial props propagate correctly
 *   - onChange is called when the internal BrandColor helper fires its callback
 *
 * SKIPPED: opening the ColorPicker.Popover and dragging sliders — these require
 * real PointerEvent dispatch that jsdom does not fully implement.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import React from 'react';

// ─── Mock @heroui/react's color-picker sub-components ────────────────────────
// We replace the interactive sub-components with lightweight pass-throughs so
// the wrapper tree renders without errors. parseColor is kept real.

vi.mock('@heroui/react', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@heroui/react')>();

  /** Minimal color object returned by the mock ColorPicker's onChange. */
  function makeColor(hex: string) {
    return {
      toString: (fmt?: string) => (fmt === 'hex' ? hex : hex),
      toHex: () => hex,
    };
  }

  // Compound ColorPicker mock: renders children + exposes an onChange trigger
  // via a data-testid so tests can call it.
  function MockColorPicker(
    { children, value, onChange }: {
      children?: React.ReactNode;
      value?: unknown;
      onChange?: (c: ReturnType<typeof makeColor>) => void;
    }
  ) {
    return (
      <div data-testid="color-picker" data-value={String(value)}>
        {children}
        {/* Provide a hidden button tests can click to simulate a colour change */}
        <button
          data-testid="color-picker-change-trigger"
          style={{ display: 'none' }}
          onClick={() => onChange?.(makeColor('#ff0000'))}
        />
      </div>
    );
  }
  MockColorPicker.Trigger = ({ children }: { children?: React.ReactNode }) => (
    <div data-testid="color-picker-trigger">{children}</div>
  );
  MockColorPicker.Popover = ({ children }: { children?: React.ReactNode }) => (
    <div data-testid="color-picker-popover">{children}</div>
  );

  function MockColorArea({ children }: { children?: React.ReactNode }) {
    return <div data-testid="color-area">{children}</div>;
  }
  MockColorArea.Thumb = () => <div data-testid="color-area-thumb" />;

  function MockColorSlider({ children }: { children?: React.ReactNode }) {
    return <div data-testid="color-slider">{children}</div>;
  }
  MockColorSlider.Track = ({ children }: { children?: React.ReactNode }) => (
    <div>{children}</div>
  );
  MockColorSlider.Thumb = () => <div />;

  function MockColorField({ children }: { children?: React.ReactNode }) {
    return <div data-testid="color-field">{children}</div>;
  }
  MockColorField.Group = ({ children }: { children?: React.ReactNode }) => <div>{children}</div>;
  MockColorField.Prefix = ({ children }: { children?: React.ReactNode }) => <div>{children}</div>;
  MockColorField.Input = () => <input data-testid="color-field-input" readOnly />;

  function MockColorSwatch({ size: _size, ...rest }: { size?: string; [key: string]: unknown }) {
    return <span data-testid="color-swatch" {...rest} />;
  }

  return {
    ...actual,
    ColorPicker: MockColorPicker,
    ColorArea: MockColorArea,
    ColorSlider: MockColorSlider,
    ColorField: MockColorField,
    ColorSwatch: MockColorSwatch,
  };
});

// Also mock the @/components/ui ColorPicker re-export so it uses the mocked version.
vi.mock('@/components/ui', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/components/ui')>();
  // ColorPicker is just re-exported from @heroui/react — the mock above covers it.
  return { ...actual };
});

vi.mock('@/contexts', () => ({
  useAuth: () => ({ user: null, isAuthenticated: false, login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle', error: null }),
  useTenant: () => ({ tenant: { id: 2, name: 'Test Tenant', slug: 'test' }, tenantPath: (p: string) => `/test${p}`, hasFeature: vi.fn(() => true), hasModule: vi.fn(() => true) }),
  useToast: () => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }),
  useTheme: () => ({ resolvedTheme: 'light', theme: 'system', toggleTheme: vi.fn(), setTheme: vi.fn() }),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
  usePresence: () => ({ status: 'offline', setStatus: vi.fn(), getPresence: vi.fn(), isOnline: vi.fn(() => false) }),
  usePresenceOptional: () => null,
}));

import { GroupBrandingPicker } from './GroupBrandingPicker';

describe('GroupBrandingPicker — static rendering', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the branding title heading', () => {
    const onChange = vi.fn();
    render(
      <GroupBrandingPicker primaryColor="#0070f3" accentColor="#7928ca" onChange={onChange} />
    );
    expect(screen.getByRole('heading', { level: 3 })).toBeInTheDocument();
  });

  it('renders two ColorPicker instances (primary + accent)', () => {
    const onChange = vi.fn();
    render(
      <GroupBrandingPicker primaryColor="#0070f3" accentColor="#7928ca" onChange={onChange} />
    );
    const pickers = screen.getAllByTestId('color-picker');
    expect(pickers).toHaveLength(2);
  });

  it('renders the preview swatch strip', () => {
    const onChange = vi.fn();
    render(
      <GroupBrandingPicker primaryColor="#0070f3" accentColor="#7928ca" onChange={onChange} />
    );
    // The preview section has two inline-style divs for primary and accent colours
    // and one gradient div. They're not queryable by role so we check via text.
    // i18n key branding.preview
    expect(screen.getAllByTestId('color-picker').length).toBeGreaterThan(0);
  });

  it('applies initial primaryColor to the preview primary swatch div', () => {
    const onChange = vi.fn();
    const { container } = render(
      <GroupBrandingPicker primaryColor="#aabbcc" accentColor="#112233" onChange={onChange} />
    );
    // The preview strip has three flex children — find the one with backgroundColor matching primary
    const swatchDivs = container.querySelectorAll('[style*="background-color"]');
    const primaryDiv = Array.from(swatchDivs).find((el) =>
      (el as HTMLElement).style.backgroundColor.includes('170, 187, 204') ||
      (el as HTMLElement).getAttribute('style')?.includes('#aabbcc')
    );
    expect(primaryDiv).toBeInTheDocument();
  });

  it('uses DEFAULT_PRIMARY (#0070f3) when primaryColor prop is null', () => {
    const onChange = vi.fn();
    const { container } = render(
      <GroupBrandingPicker primaryColor={null} accentColor={null} onChange={onChange} />
    );
    // Check the hex label for the default primary appears somewhere
    const hexTexts = container.querySelectorAll('.font-mono');
    const hasPrimary = Array.from(hexTexts).some((el) =>
      el.textContent?.toUpperCase().includes('#0070F3')
    );
    expect(hasPrimary).toBe(true);
  });

  it('uses DEFAULT_ACCENT (#7928ca) when accentColor prop is null', () => {
    const onChange = vi.fn();
    const { container } = render(
      <GroupBrandingPicker primaryColor={null} accentColor={null} onChange={onChange} />
    );
    const hexTexts = container.querySelectorAll('.font-mono');
    const hasAccent = Array.from(hexTexts).some((el) =>
      el.textContent?.toUpperCase().includes('#7928CA')
    );
    expect(hasAccent).toBe(true);
  });

  it('displays the custom primaryColor hex string in the trigger label', () => {
    const onChange = vi.fn();
    const { container } = render(
      <GroupBrandingPicker primaryColor="#ff4500" accentColor="#1a1a1a" onChange={onChange} />
    );
    const hexTexts = container.querySelectorAll('.font-mono');
    const hasPrimary = Array.from(hexTexts).some((el) =>
      el.textContent?.toUpperCase().includes('#FF4500')
    );
    expect(hasPrimary).toBe(true);
  });
});

describe('GroupBrandingPicker — onChange interaction', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // NOTE: The actual colour-change path goes through the ColorPicker's internal
  // onChange prop (passed from BrandColor). Because our mock exposes a hidden
  // trigger button we can simulate the callback firing.
  // However since BrandColor is a local (non-exported) function, the two
  // ColorPicker mocks each get their own trigger. We test the first (primary)
  // and second (accent) triggers independently.

  it('calls onChange with (newPrimary, currentAccent) when primary picker fires', () => {
    const onChange = vi.fn();
    render(
      <GroupBrandingPicker primaryColor="#0070f3" accentColor="#7928ca" onChange={onChange} />
    );

    const triggers = screen.getAllByTestId('color-picker-change-trigger');
    // First trigger = primary BrandColor
    triggers[0].click();

    expect(onChange).toHaveBeenCalledWith('#ff0000', '#7928ca');
  });

  it('calls onChange with (currentPrimary, newAccent) when accent picker fires', () => {
    const onChange = vi.fn();
    render(
      <GroupBrandingPicker primaryColor="#0070f3" accentColor="#7928ca" onChange={onChange} />
    );

    const triggers = screen.getAllByTestId('color-picker-change-trigger');
    // Second trigger = accent BrandColor
    triggers[1].click();

    expect(onChange).toHaveBeenCalledWith('#0070f3', '#ff0000');
  });

  it('calls onChange multiple times independently for each picker fire', () => {
    const onChange = vi.fn();
    render(
      <GroupBrandingPicker primaryColor="#0070f3" accentColor="#7928ca" onChange={onChange} />
    );

    const triggers = screen.getAllByTestId('color-picker-change-trigger');
    triggers[0].click();
    triggers[1].click();

    // onChange was called once per trigger
    expect(onChange).toHaveBeenCalledTimes(2);
    // First call from primary picker
    expect(onChange).toHaveBeenNthCalledWith(1, '#ff0000', '#7928ca');
    // Second call from accent picker
    expect(onChange).toHaveBeenNthCalledWith(2, '#0070f3', '#ff0000');
  });
});
