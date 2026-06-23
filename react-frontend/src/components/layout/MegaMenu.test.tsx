// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';
import type { LucideIcon } from 'lucide-react';

// ─── Contexts ────────────────────────────────────────────────────────────────
vi.mock('@/contexts', () => createMockContexts());

// ─── Stub DesktopNavPanel — it uses Popover/HeroUI which breaks in jsdom ─────
vi.mock('./DesktopNavPanel', () => ({
  DesktopNavPanel: ({
    ariaLabel,
    isOpen,
    onOpenChange,
    onNavigate,
    leftSections,
    rightSections,
    triggerLabel,
    isActive,
  }: {
    ariaLabel: string;
    isOpen: boolean;
    onOpenChange: (open: boolean) => void;
    onNavigate: (path: string) => void;
    leftSections: Array<{ key: string; title: string; items: Array<{ label: string; href: string; icon: LucideIcon }> }>;
    rightSections: Array<{ key: string; title: string; items: Array<{ label: string; href: string; icon: LucideIcon }> }>;
    triggerLabel: string;
    isActive: boolean;
  }) => {
    const allItems = [
      ...leftSections.flatMap((s) => s.items),
      ...rightSections.flatMap((s) => s.items),
    ];
    return (
      <div data-testid="desktop-nav-panel" aria-label={ariaLabel}>
        <button
          type="button"
          data-testid="trigger"
          aria-pressed={isActive}
          onClick={() => onOpenChange(!isOpen)}
        >
          {triggerLabel}
        </button>
        {isOpen && (
          <nav data-testid="panel-content">
            {allItems.map((item) => (
              <button
                key={item.href}
                type="button"
                data-testid={`nav-item-${item.href.replace(/\//g, '-').replace(/^-/, '')}`}
                onClick={() => onNavigate(item.href)}
              >
                {item.label}
              </button>
            ))}
          </nav>
        )}
      </div>
    );
  },
}));

// ─── Fixtures ────────────────────────────────────────────────────────────────
// Use a minimal icon stub — LucideIcon is just a React component type
const FakeIcon = () => <svg aria-hidden="true" />;

const LEFT_SECTIONS = [
  {
    key: 'explore',
    title: 'Explore',
    items: [
      { label: 'Members', href: '/members', icon: FakeIcon as unknown as LucideIcon },
      { label: 'Groups', href: '/groups', icon: FakeIcon as unknown as LucideIcon },
    ],
  },
];

const RIGHT_SECTIONS = [
  {
    key: 'tools',
    title: 'Tools',
    items: [
      { label: 'Search', href: '/search', icon: FakeIcon as unknown as LucideIcon },
    ],
  },
];

// ─────────────────────────────────────────────────────────────────────────────
describe('MegaMenu', () => {
  const onOpenChange = vi.fn();
  const onNavigate = vi.fn();

  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders the DesktopNavPanel wrapper', async () => {
    const { MegaMenu } = await import('./MegaMenu');
    render(
      <MegaMenu
        isOpen={false}
        onOpenChange={onOpenChange}
        isActive={false}
        leftSections={LEFT_SECTIONS}
        rightSections={RIGHT_SECTIONS}
        onNavigate={onNavigate}
      />,
    );
    expect(screen.getByTestId('desktop-nav-panel')).toBeInTheDocument();
  });

  it('passes triggerLabel translated from i18n "nav.more" key', async () => {
    const { MegaMenu } = await import('./MegaMenu');
    render(
      <MegaMenu
        isOpen={false}
        onOpenChange={onOpenChange}
        isActive={false}
        leftSections={LEFT_SECTIONS}
        rightSections={RIGHT_SECTIONS}
        onNavigate={onNavigate}
      />,
    );
    // In test env the i18n key "nav.more" resolves to the key itself or a fixture string
    const trigger = screen.getByTestId('trigger');
    expect(trigger.textContent).toBeTruthy();
  });

  it('does not render panel content when isOpen=false', async () => {
    const { MegaMenu } = await import('./MegaMenu');
    render(
      <MegaMenu
        isOpen={false}
        onOpenChange={onOpenChange}
        isActive={false}
        leftSections={LEFT_SECTIONS}
        rightSections={RIGHT_SECTIONS}
        onNavigate={onNavigate}
      />,
    );
    expect(screen.queryByTestId('panel-content')).not.toBeInTheDocument();
  });

  it('renders nav items when isOpen=true', async () => {
    const { MegaMenu } = await import('./MegaMenu');
    render(
      <MegaMenu
        isOpen={true}
        onOpenChange={onOpenChange}
        isActive={false}
        leftSections={LEFT_SECTIONS}
        rightSections={RIGHT_SECTIONS}
        onNavigate={onNavigate}
      />,
    );
    expect(screen.getByTestId('panel-content')).toBeInTheDocument();
    expect(screen.getByText('Members')).toBeInTheDocument();
    expect(screen.getByText('Groups')).toBeInTheDocument();
    expect(screen.getByText('Search')).toBeInTheDocument();
  });

  it('clicking trigger calls onOpenChange', async () => {
    const { MegaMenu } = await import('./MegaMenu');
    render(
      <MegaMenu
        isOpen={false}
        onOpenChange={onOpenChange}
        isActive={false}
        leftSections={LEFT_SECTIONS}
        rightSections={RIGHT_SECTIONS}
        onNavigate={onNavigate}
      />,
    );
    fireEvent.click(screen.getByTestId('trigger'));
    expect(onOpenChange).toHaveBeenCalledWith(true);
  });

  it('clicking a nav item calls onNavigate with the item href', async () => {
    const { MegaMenu } = await import('./MegaMenu');
    render(
      <MegaMenu
        isOpen={true}
        onOpenChange={onOpenChange}
        isActive={false}
        leftSections={LEFT_SECTIONS}
        rightSections={RIGHT_SECTIONS}
        onNavigate={onNavigate}
      />,
    );
    fireEvent.click(screen.getByText('Members'));
    expect(onNavigate).toHaveBeenCalledWith('/members');
  });

  it('clicking a right-section nav item calls onNavigate', async () => {
    const { MegaMenu } = await import('./MegaMenu');
    render(
      <MegaMenu
        isOpen={true}
        onOpenChange={onOpenChange}
        isActive={false}
        leftSections={LEFT_SECTIONS}
        rightSections={RIGHT_SECTIONS}
        onNavigate={onNavigate}
      />,
    );
    fireEvent.click(screen.getByText('Search'));
    expect(onNavigate).toHaveBeenCalledWith('/search');
  });

  it('renders sr-only live region for accessibility announcement', async () => {
    const { MegaMenu } = await import('./MegaMenu');
    render(
      <MegaMenu
        isOpen={false}
        onOpenChange={onOpenChange}
        isActive={false}
        leftSections={LEFT_SECTIONS}
        rightSections={RIGHT_SECTIONS}
        onNavigate={onNavigate}
      />,
    );
    const liveRegion = document.querySelector('[aria-live="polite"]');
    expect(liveRegion).toBeInTheDocument();
  });

  it('live region is empty when isOpen=false', async () => {
    const { MegaMenu } = await import('./MegaMenu');
    render(
      <MegaMenu
        isOpen={false}
        onOpenChange={onOpenChange}
        isActive={false}
        leftSections={LEFT_SECTIONS}
        rightSections={RIGHT_SECTIONS}
        onNavigate={onNavigate}
      />,
    );
    const liveRegion = document.querySelector('[aria-live="polite"]');
    expect(liveRegion?.textContent).toBe('');
  });

  it('live region is populated when isOpen=true', async () => {
    const { MegaMenu } = await import('./MegaMenu');
    render(
      <MegaMenu
        isOpen={true}
        onOpenChange={onOpenChange}
        isActive={false}
        leftSections={LEFT_SECTIONS}
        rightSections={RIGHT_SECTIONS}
        onNavigate={onNavigate}
      />,
    );
    const liveRegion = document.querySelector('[aria-live="polite"]');
    // i18n resolves "accessibility.menu_opened" to the key or a string in test env
    expect(liveRegion?.textContent).not.toBe('');
  });

  it('isActive prop is forwarded to the panel trigger', async () => {
    const { MegaMenu } = await import('./MegaMenu');
    render(
      <MegaMenu
        isOpen={false}
        onOpenChange={onOpenChange}
        isActive={true}
        leftSections={LEFT_SECTIONS}
        rightSections={RIGHT_SECTIONS}
        onNavigate={onNavigate}
      />,
    );
    const trigger = screen.getByTestId('trigger');
    expect(trigger).toHaveAttribute('aria-pressed', 'true');
  });

  it('renders correctly with empty sections', async () => {
    const { MegaMenu } = await import('./MegaMenu');
    render(
      <MegaMenu
        isOpen={true}
        onOpenChange={onOpenChange}
        isActive={false}
        leftSections={[]}
        rightSections={[]}
        onNavigate={onNavigate}
      />,
    );
    expect(screen.getByTestId('panel-content')).toBeInTheDocument();
    // no nav items rendered
    expect(screen.queryByRole('button', { name: 'Members' })).not.toBeInTheDocument();
  });
});
