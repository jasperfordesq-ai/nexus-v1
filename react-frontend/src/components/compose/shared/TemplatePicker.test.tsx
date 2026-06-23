// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';
import userEvent from '@testing-library/user-event';

// ─── No api calls in this component ──────────────────────────────────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn(), download: vi.fn(), upload: vi.fn() },
}));
vi.mock('@/lib/api', () => ({ api: mockApi, default: mockApi }));

// ─── Contexts ────────────────────────────────────────────────────────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn(), showToast: vi.fn() };

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
  })
);

// ─── Stub @/components/ui Dropdown family to avoid jsdom HeroUI issues ───────
// TemplatePicker uses Dropdown, DropdownTrigger, DropdownMenu, DropdownItem, Button
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/components/ui')>();
  return {
    ...orig,
    Dropdown: ({ children }: { children: React.ReactNode }) => <div data-testid="dropdown">{children}</div>,
    DropdownTrigger: ({ children }: { children: React.ReactNode }) => <div data-testid="dropdown-trigger">{children}</div>,
    DropdownMenu: ({
      children,
      onAction,
    }: {
      children: React.ReactNode;
      onAction?: (key: React.Key) => void;
      'aria-label'?: string;
    }) => (
      <div data-testid="dropdown-menu">
        {React.Children.map(children, (child) => {
          if (!React.isValidElement(child)) return child;
          // Give each item a clickable button that fires onAction(item.key)
          const itemKey = (child as React.ReactElement<{ id?: string }>).props.id ?? (child.key as string);
          return (
            <button
              key={itemKey}
              data-testid={`menu-item-${itemKey}`}
              onClick={() => onAction?.(itemKey)}
            >
              {(child as React.ReactElement<{ children?: React.ReactNode }>).props.children}
            </button>
          );
        })}
      </div>
    ),
    DropdownItem: ({ children, id }: { children: React.ReactNode; id?: string }) => (
      <span data-item-id={id}>{children}</span>
    ),
    Button: ({ children, startContent: _startContent, ...props }: React.ButtonHTMLAttributes<HTMLButtonElement> & { startContent?: React.ReactNode }) => (
      <button {...props} data-testid="template-button">
        {children}
      </button>
    ),
  };
});

describe('TemplatePicker', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders the template button for a tab that has templates (post)', async () => {
    const { TemplatePicker } = await import('./TemplatePicker');
    render(<TemplatePicker tab="post" onSelect={vi.fn()} />);
    // The button rendered by the stub should be in the document
    expect(screen.getByTestId('template-button')).toBeInTheDocument();
  });

  it('renders nothing for a tab with no templates (message)', async () => {
    const { TemplatePicker } = await import('./TemplatePicker');
    const { container } = render(<TemplatePicker tab={'message' as 'post'} onSelect={vi.fn()} />);
    // No dropdown or button — component returns null
    expect(container.querySelector('[data-testid="template-button"]')).toBeNull();
  });

  it('renders template menu items for the "post" tab', async () => {
    const { TemplatePicker } = await import('./TemplatePicker');
    render(<TemplatePicker tab="post" onSelect={vi.fn()} />);
    // Our stub renders items as data-testid="menu-item-{key}"
    expect(screen.getByTestId('menu-item-achievement')).toBeInTheDocument();
    expect(screen.getByTestId('menu-item-help')).toBeInTheDocument();
    expect(screen.getByTestId('menu-item-recommend')).toBeInTheDocument();
  });

  it('renders template menu items for the "listing" tab', async () => {
    const { TemplatePicker } = await import('./TemplatePicker');
    render(<TemplatePicker tab="listing" onSelect={vi.fn()} />);
    expect(screen.getByTestId('menu-item-offer')).toBeInTheDocument();
    expect(screen.getByTestId('menu-item-request')).toBeInTheDocument();
  });

  it('renders template menu items for the "event" tab', async () => {
    const { TemplatePicker } = await import('./TemplatePicker');
    render(<TemplatePicker tab="event" onSelect={vi.fn()} />);
    expect(screen.getByTestId('menu-item-gathering')).toBeInTheDocument();
    expect(screen.getByTestId('menu-item-workshop')).toBeInTheDocument();
    expect(screen.getByTestId('menu-item-social')).toBeInTheDocument();
  });

  it('renders template menu items for the "goal" tab', async () => {
    const { TemplatePicker } = await import('./TemplatePicker');
    render(<TemplatePicker tab="goal" onSelect={vi.fn()} />);
    expect(screen.getByTestId('menu-item-hours')).toBeInTheDocument();
    expect(screen.getByTestId('menu-item-skills')).toBeInTheDocument();
  });

  it('renders template menu items for the "poll" tab', async () => {
    const { TemplatePicker } = await import('./TemplatePicker');
    render(<TemplatePicker tab="poll" onSelect={vi.fn()} />);
    expect(screen.getByTestId('menu-item-opinion')).toBeInTheDocument();
    expect(screen.getByTestId('menu-item-preference')).toBeInTheDocument();
  });

  it('calls onSelect with content when a post achievement template is clicked', async () => {
    const onSelect = vi.fn();
    const { TemplatePicker } = await import('./TemplatePicker');
    render(<TemplatePicker tab="post" onSelect={onSelect} />);

    await userEvent.click(screen.getByTestId('menu-item-achievement'));

    await waitFor(() => {
      expect(onSelect).toHaveBeenCalledOnce();
      expect(onSelect).toHaveBeenCalledWith(
        expect.objectContaining({ content: expect.stringContaining('completed') })
      );
    });
  });

  it('calls onSelect with title and content when a listing offer template is clicked', async () => {
    const onSelect = vi.fn();
    const { TemplatePicker } = await import('./TemplatePicker');
    render(<TemplatePicker tab="listing" onSelect={onSelect} />);

    await userEvent.click(screen.getByTestId('menu-item-offer'));

    await waitFor(() => {
      expect(onSelect).toHaveBeenCalledOnce();
      const arg = onSelect.mock.calls[0][0] as { title?: string; content: string };
      expect(arg.title).toBe('I can help with...');
      expect(arg.content).toContain('experience');
    });
  });

  it('calls onSelect with content (no title) for a poll opinion template', async () => {
    const onSelect = vi.fn();
    const { TemplatePicker } = await import('./TemplatePicker');
    render(<TemplatePicker tab="poll" onSelect={onSelect} />);

    await userEvent.click(screen.getByTestId('menu-item-opinion'));

    await waitFor(() => {
      expect(onSelect).toHaveBeenCalledOnce();
      const arg = onSelect.mock.calls[0][0] as { title?: string; content: string };
      expect(arg.content).toContain('think about');
      // poll templates have no title
      expect(arg.title).toBeUndefined();
    });
  });

  it('renders the dropdown wrapper', async () => {
    const { TemplatePicker } = await import('./TemplatePicker');
    render(<TemplatePicker tab="event" onSelect={vi.fn()} />);
    expect(screen.getByTestId('dropdown')).toBeInTheDocument();
    expect(screen.getByTestId('dropdown-trigger')).toBeInTheDocument();
    expect(screen.getByTestId('dropdown-menu')).toBeInTheDocument();
  });
});
