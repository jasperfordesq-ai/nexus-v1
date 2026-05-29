// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';

import { ComboBox } from './ComboBox';
import { ListBoxItem as ComboBoxItem } from './ListBox';

describe('ComboBox', () => {
  it('mounts with all compound parts and renders a combobox input', () => {
    render(
      <ComboBox label="Animal" placeholder="Search animals" useDefaultFilter>
        <ComboBoxItem id="cat">Cat</ComboBoxItem>
        <ComboBoxItem id="dog">Dog</ComboBoxItem>
      </ComboBox>
    );

    expect(screen.getByText('Animal')).toBeTruthy();
    // RAC ComboBox renders its text input with role="combobox".
    expect(screen.getByRole('combobox')).toBeTruthy();
  });

  it('supports async-style controlled input without a client filter', () => {
    const items = [{ id: '1', name: 'Result one' }];
    expect(() =>
      render(
        <ComboBox
          aria-label="Search"
          items={items}
          inputValue="res"
          onInputChange={() => {}}
          allowsEmptyCollection
        >
          {(item: { id: string; name: string }) => (
            <ComboBoxItem id={item.id}>{item.name}</ComboBoxItem>
          )}
        </ComboBox>
      )
    ).not.toThrow();
  });
});
