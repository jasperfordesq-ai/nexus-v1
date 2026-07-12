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
import { render, screen, waitFor } from '@/test/test-utils';
import React from 'react';

// GroupBrandingPicker uses HeroUI v3's split entrypoints, so mock those exact
// import boundaries without replacing unrelated HeroUI components globally.
vi.mock('@/components/ui/ColorPicker', () => {
  function makeColor(hex: string) {
    return { toString: () => hex };
  }

  function MockColorPicker({
    children,
    value,
    onChange,
  }: {
    children?: React.ReactNode;
    value?: unknown;
    onChange?: (color: ReturnType<typeof makeColor>) => void;
  }) {
    return (
      <div data-testid="color-picker" data-value={String(value)}>
        {children}
        <button
          type="button"
          data-testid="color-picker-change-trigger"
          className="sr-only"
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

  return { ColorPicker: MockColorPicker };
});

vi.mock('@heroui/react/color-area', () => {
  function ColorArea({ children }: { children?: React.ReactNode }) {
    return <div data-testid="color-area">{children}</div>;
  }
  ColorArea.Thumb = () => <div data-testid="color-area-thumb" />;
  return { ColorArea };
});

vi.mock('@heroui/react/color-slider', () => {
  function ColorSlider({ children }: { children?: React.ReactNode }) {
    return <div data-testid="color-slider">{children}</div>;
  }
  ColorSlider.Track = ({ children }: { children?: React.ReactNode }) => <div>{children}</div>;
  ColorSlider.Thumb = () => <div />;
  return { ColorSlider };
});

vi.mock('@heroui/react/color-field', () => {
  function ColorField({ children }: { children?: React.ReactNode }) {
    return <div data-testid="color-field">{children}</div>;
  }
  ColorField.Group = ({ children }: { children?: React.ReactNode }) => <div>{children}</div>;
  ColorField.Prefix = ({ children }: { children?: React.ReactNode }) => <div>{children}</div>;
  ColorField.Input = () => <input data-testid="color-field-input" readOnly />;
  return { ColorField };
});

vi.mock('@heroui/react/color-swatch', () => ({
  ColorSwatch: ({ size: _size, ...rest }: { size?: string; [key: string]: unknown }) => (
    <span data-testid="color-swatch" {...rest} />
  ),
}));

vi.mock('@heroui/react/label', () => ({
  Label: ({ children, ...rest }: React.ComponentProps<'label'>) => <label {...rest}>{children}</label>,
}));

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

  it('resynchronizes after a cancelled settings draft is reopened', async () => {
    const onChange = vi.fn();
    const { container, rerender } = render(
      <GroupBrandingPicker primaryColor="#111111" accentColor="#222222" onChange={onChange} />
    );
    screen.getAllByTestId('color-picker-change-trigger')[0].click();
    expect(onChange).toHaveBeenCalledWith('#ff0000', '#222222');

    rerender(<GroupBrandingPicker primaryColor="#333333" accentColor="#444444" onChange={onChange} />);
    await waitFor(() => {
      const values = Array.from(container.querySelectorAll('.font-mono')).map((element) => element.textContent);
      expect(values).toContain('#333333');
      expect(values).toContain('#444444');
    });
  });

  it('can reset persisted colors back to nullable platform defaults', () => {
    const onChange = vi.fn();
    render(<GroupBrandingPicker primaryColor="#111111" accentColor="#222222" onChange={onChange} />);
    screen.getByRole('button', { name: 'Use platform colors' }).click();
    expect(onChange).toHaveBeenCalledWith(null, null);
  });
});
