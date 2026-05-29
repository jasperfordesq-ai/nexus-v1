// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';

import { ListBox, ListBoxItem, ListBoxSection } from './ListBox';

describe('ListBox', () => {
  it('renders selectable options', () => {
    render(
      <ListBox aria-label="Fruits" selectionMode="single">
        <ListBoxItem id="apple">Apple</ListBoxItem>
        <ListBoxItem id="banana">Banana</ListBoxItem>
        <ListBoxItem id="cherry">Cherry</ListBoxItem>
      </ListBox>
    );

    expect(screen.getByRole('listbox', { name: 'Fruits' })).toBeTruthy();
    expect(screen.getAllByRole('option')).toHaveLength(3);
  });

  it('fires onSelectionChange when an option is activated', () => {
    const onSelectionChange = vi.fn();
    render(
      <ListBox aria-label="Fruits" selectionMode="single" onSelectionChange={onSelectionChange}>
        <ListBoxItem id="apple">Apple</ListBoxItem>
        <ListBoxItem id="banana">Banana</ListBoxItem>
      </ListBox>
    );

    fireEvent.click(screen.getByRole('option', { name: 'Banana' }));
    expect(onSelectionChange).toHaveBeenCalled();
  });

  it('renders section headers and items', () => {
    render(
      <ListBox aria-label="Grouped" selectionMode="single">
        <ListBoxSection title="Citrus">
          <ListBoxItem id="lemon">Lemon</ListBoxItem>
        </ListBoxSection>
      </ListBox>
    );

    expect(screen.getByRole('option', { name: 'Lemon' })).toBeTruthy();
  });
});
