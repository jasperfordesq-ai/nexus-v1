// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── Contexts ────────────────────────────────────────────────────────────────
vi.mock('@/contexts', () => createMockContexts());

// ─── Stub ToggleButtonGroup/ToggleButton so HeroUI internals don't bite ──────
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/components/ui')>();
  return {
    ...orig,
    ToggleButtonGroup: ({
      children,
      onSelectionChange,
      selectedKeys,
      'aria-label': ariaLabel,
    }: {
      children: React.ReactNode;
      onSelectionChange?: (keys: Set<string>) => void;
      selectedKeys?: string[];
      'aria-label'?: string;
    }) => (
      <div role="group" aria-label={ariaLabel} data-selected={selectedKeys?.join(',')}>
        {React.Children.map(children, (child) => {
          if (!React.isValidElement(child)) return child;
          const id = (child.props as { id?: string }).id ?? '';
          const isSelected = selectedKeys?.includes(id);
          return (
            <button
              type="button"
              data-key={id}
              data-selected={isSelected ? 'true' : 'false'}
              aria-pressed={isSelected}
              onClick={() => {
                if (onSelectionChange) {
                  onSelectionChange(new Set([id]));
                }
              }}
            >
              {(child.props as { children?: React.ReactNode }).children}
            </button>
          );
        })}
      </div>
    ),
    ToggleButton: ({
      children,
      id,
    }: {
      children: React.ReactNode;
      id?: string;
    }) => <span data-toggle-id={id}>{children}</span>,
  };
});

// ─── Fixtures ────────────────────────────────────────────────────────────────
const CATEGORIES = [
  { id: 1, name: 'Clothing', slug: 'clothing', listing_count: 5 },
  { id: 2, name: 'Electronics', slug: 'electronics', listing_count: 12 },
  { id: 3, name: 'Books', slug: 'books', listing_count: 3 },
];

// ─────────────────────────────────────────────────────────────────────────────
describe('CategoryChips', () => {
  const onSelect = vi.fn();

  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders the All button', async () => {
    const { CategoryChips } = await import('./CategoryChips');
    render(<CategoryChips categories={CATEGORIES} onSelect={onSelect} />);
    const allBtn = screen.getByRole('button', { name: /all/i });
    expect(allBtn).toBeInTheDocument();
  });

  it('renders a button for each category', async () => {
    const { CategoryChips } = await import('./CategoryChips');
    render(<CategoryChips categories={CATEGORIES} onSelect={onSelect} />);
    expect(screen.getByRole('button', { name: /clothing/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /electronics/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /books/i })).toBeInTheDocument();
  });

  it('renders exactly categories.length + 1 buttons (including All)', async () => {
    const { CategoryChips } = await import('./CategoryChips');
    render(<CategoryChips categories={CATEGORIES} onSelect={onSelect} />);
    const buttons = screen.getAllByRole('button');
    expect(buttons.length).toBe(CATEGORIES.length + 1);
  });

  it('clicking a category chip calls onSelect with the category id', async () => {
    const { CategoryChips } = await import('./CategoryChips');
    render(<CategoryChips categories={CATEGORIES} onSelect={onSelect} />);
    fireEvent.click(screen.getByRole('button', { name: /clothing/i }));
    expect(onSelect).toHaveBeenCalledWith(1);
  });

  it('clicking the All button calls onSelect with null', async () => {
    const { CategoryChips } = await import('./CategoryChips');
    render(<CategoryChips categories={CATEGORIES} activeId={1} onSelect={onSelect} />);
    fireEvent.click(screen.getByRole('button', { name: /all/i }));
    expect(onSelect).toHaveBeenCalledWith(null);
  });

  it('clicking Electronics calls onSelect with id 2', async () => {
    const { CategoryChips } = await import('./CategoryChips');
    render(<CategoryChips categories={CATEGORIES} onSelect={onSelect} />);
    fireEvent.click(screen.getByRole('button', { name: /electronics/i }));
    expect(onSelect).toHaveBeenCalledWith(2);
  });

  it('All button is marked as selected when no activeId is given', async () => {
    const { CategoryChips } = await import('./CategoryChips');
    render(<CategoryChips categories={CATEGORIES} onSelect={onSelect} />);
    const group = screen.getByRole('group');
    expect(group).toHaveAttribute('data-selected', 'all');
  });

  it('active category button is marked as selected when activeId matches', async () => {
    const { CategoryChips } = await import('./CategoryChips');
    render(<CategoryChips categories={CATEGORIES} activeId={2} onSelect={onSelect} />);
    const group = screen.getByRole('group');
    expect(group).toHaveAttribute('data-selected', '2');
  });

  it('renders with empty categories list without crashing', async () => {
    const { CategoryChips } = await import('./CategoryChips');
    render(<CategoryChips categories={[]} onSelect={onSelect} />);
    expect(screen.getByRole('button', { name: /all/i })).toBeInTheDocument();
  });

  it('group has accessible aria-label from i18n', async () => {
    const { CategoryChips } = await import('./CategoryChips');
    render(<CategoryChips categories={CATEGORIES} onSelect={onSelect} />);
    const group = screen.getByRole('group');
    // The i18n key resolves to the key in test env ("categories.label") or similar
    expect(group).toHaveAttribute('aria-label');
    expect(group.getAttribute('aria-label')).not.toBe('');
  });

  it('onSelect is not called on initial render', async () => {
    const { CategoryChips } = await import('./CategoryChips');
    render(<CategoryChips categories={CATEGORIES} onSelect={onSelect} />);
    expect(onSelect).not.toHaveBeenCalled();
  });
});
