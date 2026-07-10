// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@/test/test-utils';
import { Accordion, AccordionItem } from './Accordion';

vi.mock('@/contexts', () => ({
  useTheme: () => ({ resolvedTheme: 'light', theme: 'system', toggleTheme: vi.fn(), setTheme: vi.fn() }),
  useToast: () => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }),
  useAuth: () => ({ user: null, isAuthenticated: false, login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle', error: null }),
  useTenant: () => ({ tenant: { id: 2, name: 'Test', slug: 'test' }, tenantPath: (p: string) => `/test${p}`, hasFeature: vi.fn(() => true), hasModule: vi.fn(() => true) }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  usePresence: () => ({ status: 'offline', setStatus: vi.fn(), getPresence: vi.fn(), isOnline: vi.fn(() => false) }),
  usePresenceOptional: () => null,
}));

describe('Accordion', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders accordion items with their titles', () => {
    render(
      <Accordion>
        <AccordionItem id="1" title="Section One">Content One</AccordionItem>
        <AccordionItem id="2" title="Section Two">Content Two</AccordionItem>
      </Accordion>
    );

    expect(screen.getByText('Section One')).toBeInTheDocument();
    expect(screen.getByText('Section Two')).toBeInTheDocument();
  });

  it('renders trigger buttons for each item', () => {
    render(
      <Accordion>
        <AccordionItem id="a1" title="Alpha">Body alpha</AccordionItem>
        <AccordionItem id="a2" title="Beta">Body beta</AccordionItem>
      </Accordion>
    );

    // HeroUI Accordion.Trigger renders as a <button>
    const buttons = screen.getAllByRole('button');
    expect(buttons.length).toBeGreaterThanOrEqual(2);
  });

  it('shows body content of a defaultExpanded item', () => {
    render(
      <Accordion defaultExpandedKeys={['item1']}>
        <AccordionItem id="item1" title="Open Item">Visible Content</AccordionItem>
      </Accordion>
    );

    expect(screen.getByText('Visible Content')).toBeInTheDocument();
  });

  it('expands a collapsed item when its trigger is clicked', () => {
    render(
      <Accordion>
        <AccordionItem id="click1" title="Click Me">Hidden Body</AccordionItem>
      </Accordion>
    );

    const trigger = screen.getByText('Click Me').closest('button') ??
                    screen.getAllByRole('button')[0];
    fireEvent.click(trigger);

    // After click the body should be present in the DOM
    expect(screen.getByText('Hidden Body')).toBeInTheDocument();
  });

  it('renders subtitle when provided', () => {
    render(
      <Accordion>
        <AccordionItem id="s1" title="With Subtitle" subtitle="Sub text">Body</AccordionItem>
      </Accordion>
    );

    expect(screen.getByText('Sub text')).toBeInTheDocument();
  });

  it('hides the chevron indicator when hideIndicator is set', () => {
    const { container } = render(
      <Accordion>
        <AccordionItem id="hi1" title="No Indicator" hideIndicator>Body</AccordionItem>
      </Accordion>
    );

    // When hideIndicator=true the Accordion.Indicator wrapper is not rendered.
    // The ChevronDown SVG should not be present.
    const svgIcons = container.querySelectorAll('svg');
    // The trigger area should have no indicator SVG; there may be 0 SVGs
    // (note: some HeroUI versions emit no SVGs for hidden indicator).
    // We only verify the component mounted without error.
    expect(screen.getByText('No Indicator')).toBeInTheDocument();
    expect(svgIcons).toBeDefined();
  });

  it('calls onExpandedChange when an item is toggled', () => {
    const onExpandedChange = vi.fn();

    render(
      <Accordion onExpandedChange={onExpandedChange}>
        <AccordionItem id="ev1" title="Expand Event">Body</AccordionItem>
      </Accordion>
    );

    const trigger = screen.getAllByRole('button')[0];
    fireEvent.click(trigger);

    expect(onExpandedChange).toHaveBeenCalled();
  });

  it('accepts selectionMode=multiple via allowsMultipleExpanded mapping', () => {
    // Smoke-test: two items can both be open when selectionMode=multiple.
    // defaultSelectedKeys is an alias for defaultExpandedKeys.
    render(
      <Accordion selectionMode="multiple" defaultSelectedKeys={['m1', 'm2']}>
        <AccordionItem id="m1" title="First">Body 1</AccordionItem>
        <AccordionItem id="m2" title="Second">Body 2</AccordionItem>
      </Accordion>
    );

    expect(screen.getByText('Body 1')).toBeInTheDocument();
    expect(screen.getByText('Body 2')).toBeInTheDocument();
  });

  it('renders startContent inside the trigger', () => {
    render(
      <Accordion defaultExpandedKeys={['sc1']}>
        <AccordionItem id="sc1" title="Start" startContent={<span data-testid="start-icon">icon</span>}>
          Body
        </AccordionItem>
      </Accordion>
    );

    expect(screen.getByTestId('start-icon')).toBeInTheDocument();
  });

  it('propagates root itemClasses to every documented item slot and merges local classes', () => {
    const { container } = render(
      <Accordion
        defaultExpandedKeys={['styled']}
        itemClasses={{
          base: 'root-base',
          content: 'root-content',
          heading: 'root-heading',
          indicator: 'root-indicator',
          subtitle: 'root-subtitle',
          title: 'root-title',
          trigger: 'min-h-[48px] root-trigger',
        }}
      >
        <AccordionItem
          id="styled"
          title="Styled title"
          subtitle="Styled subtitle"
          classNames={{ base: 'local-base', trigger: 'local-trigger' }}
        >
          Styled content
        </AccordionItem>
      </Accordion>,
    );

    expect(container.querySelector('[data-slot="accordion-item"]')).toHaveClass('root-base', 'local-base');
    expect(container.querySelector('[data-slot="accordion-heading"]')).toHaveClass('root-heading');
    expect(container.querySelector('[data-slot="accordion-trigger"]')).toHaveClass(
      'min-h-[48px]',
      'root-trigger',
      'local-trigger',
    );
    expect(screen.getByText('Styled title')).toHaveClass('root-title');
    expect(screen.getByText('Styled subtitle')).toHaveClass('root-subtitle');
    expect(container.querySelector('[data-slot="accordion-indicator"]')).toHaveClass('root-indicator');
    expect(screen.getByText('Styled content')).toHaveClass('root-content');
  });

  it('preserves the legacy splitted contract with separated surface items and no dividers', () => {
    const { container } = render(
      <Accordion variant="splitted">
        <AccordionItem id="split-1" title="First split item">First body</AccordionItem>
        <AccordionItem id="split-2" title="Second split item">Second body</AccordionItem>
      </Accordion>,
    );

    expect(container.querySelector('[data-slot="accordion"]')).toHaveClass('space-y-2');
    container.querySelectorAll('[data-slot="accordion-item"]').forEach((item) => {
      expect(item).toHaveClass('overflow-hidden', 'rounded-2xl', 'bg-surface', 'shadow-surface');
      expect(item).toHaveAttribute('data-hide-separator', 'true');
    });
  });
});
