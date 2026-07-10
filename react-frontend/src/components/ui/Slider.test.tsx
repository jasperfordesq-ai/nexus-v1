// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for the Slider wrapper component.
 *
 * Design notes
 * ─────────────
 * • The Slider wraps HeroUI v3 Slider which is built on React Aria.
 * • React Aria Slider renders a visually-hidden <input type="range"> with
 *   implicit role="slider". The current value is stored in the `value`
 *   attribute of this input, not as `aria-valuenow`. React Aria uses
 *   `aria-valuetext` (the formatted label string) rather than `aria-valuenow`
 *   per the ARIA best-practice for sliders with custom value formatting.
 * • React Aria requires an accessible label (label prop or aria-label);
 *   without it the thumb may not render correctly in jsdom. All tests supply
 *   a label.
 * • `value` attribute on the <input type="range"> holds the numeric value.
 * • Keyboard navigation: ArrowRight/ArrowLeft work on the hidden input.
 * • HeroUI Slider.Output renders as <output> (implicit role "status"); other
 *   infrastructure (ToastProvider, live regions) also carries role="status",
 *   so we use getAllByRole and filter by element tag.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@/test/test-utils';
import { Slider } from './Slider';

vi.mock('@/contexts', () => ({
  useAuth: () => ({ user: null, isAuthenticated: false, login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle', error: null }),
  useTenant: () => ({ tenant: { id: 2, name: 'Test', slug: 'test' }, branding: { name: 'Test' }, tenantPath: (p: string) => `/test${p}`, hasFeature: vi.fn(() => true), hasModule: vi.fn(() => true) }),
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

describe('Slider component', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders a slider element (role="slider" / input[type="range"])', () => {
    render(<Slider label="Volume" defaultValue={50} minValue={0} maxValue={100} />);
    expect(screen.getByRole('slider')).toBeInTheDocument();
  });

  it('reflects the defaultValue via aria-valuetext', () => {
    // React Aria Slider exposes the current value as a formatted string in
    // aria-valuetext (e.g. "40"). The raw numeric value is tracked via React
    // state in useSliderState and is NOT reflected back onto the hidden
    // <input type="range">.value in jsdom (a jsdom/uncontrolled-input limitation).
    render(<Slider label="Volume" defaultValue={40} minValue={0} maxValue={100} />);
    const thumb = screen.getByRole('slider');
    expect(thumb).toHaveAttribute('aria-valuetext', '40');
  });

  it('sets correct min/max on the native range input', () => {
    render(<Slider label="Volume" defaultValue={5} minValue={1} maxValue={10} />);
    const thumb = screen.getByRole('slider');
    expect(thumb).toHaveAttribute('min', '1');
    expect(thumb).toHaveAttribute('max', '10');
  });

  it('provides aria-valuetext (formatted label) on the thumb', () => {
    render(<Slider label="Volume" defaultValue={40} minValue={0} maxValue={100} />);
    const thumb = screen.getByRole('slider');
    // aria-valuetext is the accessible representation React Aria uses
    expect(thumb).toHaveAttribute('aria-valuetext');
  });

  it('renders a label when the label prop is supplied', () => {
    render(<Slider label="Volume" defaultValue={50} minValue={0} maxValue={100} />);
    expect(screen.getByText('Volume')).toBeInTheDocument();
  });

  it('does not render a label element when label prop is absent', () => {
    render(<Slider aria-label="no-label slider" defaultValue={50} minValue={0} maxValue={100} />);
    expect(screen.queryByText('Volume')).not.toBeInTheDocument();
  });

  it('renders a value <output> element by default (hideValue=false)', () => {
    render(<Slider label="Volume" defaultValue={25} minValue={0} maxValue={100} />);
    // HeroUI Slider.Output renders as <output> which has implicit role="status".
    // Other roles="status" may also exist; find the <output> element specifically.
    const statusEls = screen.getAllByRole('status');
    const outputEl = statusEls.find((el) => el.tagName.toLowerCase() === 'output');
    expect(outputEl).toBeInTheDocument();
  });

  it('does not render an <output> element when hideValue is true', () => {
    render(<Slider label="Volume" defaultValue={25} minValue={0} maxValue={100} hideValue />);
    const statusEls = screen.queryAllByRole('status');
    const outputEl = statusEls.find((el) => el.tagName.toLowerCase() === 'output');
    expect(outputEl).toBeUndefined();
  });

  it('updates aria-valuetext when ArrowRight increments the value', () => {
    // React Aria updates aria-valuetext synchronously on keyboard events.
    // The native input .value is not reflected in jsdom after state changes
    // (uncontrolled input limitation), but aria-valuetext is.
    render(<Slider label="Volume" defaultValue={50} minValue={0} maxValue={100} step={1} />);
    const thumb = screen.getByRole('slider');
    fireEvent.keyDown(thumb, { key: 'ArrowRight' });
    expect(thumb).toHaveAttribute('aria-valuetext', '51');
  });

  it('updates aria-valuetext when ArrowLeft decrements the value', () => {
    render(<Slider label="Volume" defaultValue={50} minValue={0} maxValue={100} step={1} />);
    const thumb = screen.getByRole('slider');
    fireEvent.keyDown(thumb, { key: 'ArrowLeft' });
    expect(thumb).toHaveAttribute('aria-valuetext', '49');
  });

  it('calls onChange with updated value on arrow key', () => {
    const onChange = vi.fn();
    render(<Slider label="Volume" defaultValue={50} minValue={0} maxValue={100} step={1} onChange={onChange} />);
    const thumb = screen.getByRole('slider');
    fireEvent.keyDown(thumb, { key: 'ArrowRight' });
    expect(onChange).toHaveBeenCalledWith(51);
  });

  it('renders marks when marks prop is supplied', () => {
    render(
      <Slider
        label="Steps"
        defaultValue={0}
        minValue={0}
        maxValue={10}
        marks={[{ value: 0, label: 'Min' }, { value: 10, label: 'Max' }]}
      />,
    );
    expect(screen.getByText('Min')).toBeInTheDocument();
    expect(screen.getByText('Max')).toBeInTheDocument();
  });

  it('does not render marks when no marks are passed', () => {
    render(<Slider label="Simple" defaultValue={5} minValue={0} maxValue={10} />);
    expect(screen.queryByText('Min')).not.toBeInTheDocument();
  });

  it('renders custom label via renderLabel', () => {
    render(
      <Slider
        defaultValue={50}
        minValue={0}
        maxValue={100}
        label="Base label"
        renderLabel={({ children }) => <strong data-testid="custom-label">{children}</strong>}
      />,
    );
    expect(screen.getByTestId('custom-label')).toBeInTheDocument();
    expect(screen.getByTestId('custom-label')).toHaveTextContent('Base label');
  });

  it('renders an indicator for each discrete value when showSteps is enabled', () => {
    const { container } = render(
      <Slider label="Score" defaultValue={3} minValue={1} maxValue={5} step={1} showSteps />,
    );

    const steps = container.querySelectorAll('[data-slot="slider-step"]');
    expect(steps).toHaveLength(5);
    expect(Array.from(steps, (step) => step.getAttribute('data-value'))).toEqual(['1', '2', '3', '4', '5']);
    expect(steps[0]).toHaveClass('bg-surface', 'ring-border');
  });

  it('renders numerically stable fractional step indicators', () => {
    const { container } = render(
      <Slider label="Score" defaultValue={0.5} minValue={0} maxValue={1} step={0.25} showSteps />,
    );

    expect(Array.from(
      container.querySelectorAll('[data-slot="slider-step"]'),
      (step) => step.getAttribute('data-value'),
    )).toEqual(['0', '0.25', '0.5', '0.75', '1']);
  });

  it.each([
    ['sm', 'h-4', 'border-x-[0.75rem]', 'w-6'],
    ['md', 'slider__track', null, 'slider__thumb'],
    ['lg', 'h-6', 'border-x-[1rem]', 'w-8'],
  ] as const)(
    'applies explicit %s sizing to the v3 track and thumb anatomy',
    (size, trackClass, trackBorderClass, thumbClass) => {
      const { container } = render(
        <Slider label="Volume" defaultValue={50} minValue={0} maxValue={100} size={size} />,
      );

      expect(container.querySelector('[data-slot="slider"]')).toHaveAttribute('data-size', size);
      expect(container.querySelector('[data-slot="slider-track"]')).toHaveClass(trackClass);
      if (trackBorderClass) {
        expect(container.querySelector('[data-slot="slider-track"]')).toHaveClass(trackBorderClass);
      }
      expect(container.querySelector('[data-slot="slider-thumb"]')).toHaveClass(thumbClass);
    },
  );

  it('applies explicit vertical sizing to the v3 track and thumb anatomy', () => {
    const { container } = render(
      <Slider
        aria-label="Vertical volume"
        defaultValue={50}
        minValue={0}
        maxValue={100}
        orientation="vertical"
        size="lg"
      />,
    );

    expect(container.querySelector('[data-slot="slider-track"]')).toHaveClass('w-6', 'border-y-[1rem]');
    expect(container.querySelector('[data-slot="slider-thumb"]')).toHaveClass('h-8');
  });

  it('uses getTooltipValue for a focus, hover, and drag-visible thumb tooltip', () => {
    const getTooltipValue = vi.fn((value: number | number[]) => `Formatted ${value}`);
    const { container } = render(
      <Slider
        label="Volume"
        defaultValue={25}
        minValue={0}
        maxValue={100}
        showTooltip
        getTooltipValue={getTooltipValue}
      />,
    );

    expect(getTooltipValue).toHaveBeenCalledWith(25);
    const tooltip = screen.getByRole('tooltip');
    expect(tooltip).toHaveTextContent('Formatted 25');
    expect(tooltip).toHaveClass(
      'invisible',
      'group-hover/slider-thumb:visible',
      'group-focus-within/slider-thumb:visible',
      'group-data-[dragging=true]/slider-thumb:visible',
      'opacity-0',
      'group-hover/slider-thumb:opacity-100',
      'group-focus-within/slider-thumb:opacity-100',
      'group-data-[dragging=true]/slider-thumb:opacity-100',
    );

    fireEvent.keyDown(screen.getByRole('slider'), { key: 'ArrowRight' });
    expect(getTooltipValue).toHaveBeenLastCalledWith(26);
    expect(tooltip).toHaveTextContent('Formatted 26');
  });

  // Range slider (two thumbs)
  it('renders two sliders for a range value array', () => {
    render(<Slider label="Range" defaultValue={[20, 80]} minValue={0} maxValue={100} />);
    expect(screen.getAllByRole('slider')).toHaveLength(2);
  });

  // hideThumb: skipped — React Aria still exposes role="slider" (the hidden
  // range input) regardless of whether the visual thumb element is rendered.
  // The hideThumb prop controls the outer Thumb container, but the internal
  // <input type="range"> managed by useSliderThumb persists in the DOM for
  // accessibility. There is no reliable way to test CSS visibility in jsdom.
  it.skip('does not expose slider role when hideThumb is true', () => {
    render(<Slider label="Volume" defaultValue={50} minValue={0} maxValue={100} hideThumb />);
    expect(screen.queryByRole('slider')).not.toBeInTheDocument();
  });
});
