// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState } from 'react';

import { describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen, userEvent } from '@/test/test-utils';
import { StarRating } from './StarRating';

function ControlledStarRating({ onChange = vi.fn() }: { onChange?: (value: number) => void }) {
  const [value, setValue] = useState(0);

  return (
    <StarRating
      value={value}
      onChange={(nextValue) => {
        setValue(nextValue);
        onChange(nextValue);
      }}
      label="Rate this exchange"
      getOptionLabel={(rating) => `Rate ${rating} out of 5`}
      getValueDescription={(rating) => `Selected ${rating}`}
      isRequired
    />
  );
}

describe('StarRating', () => {
  it('renders an exactly named, required HeroUI radio group with five options', () => {
    render(<ControlledStarRating />);

    expect(screen.getByRole('radiogroup', { name: 'Rate this exchange' })).toHaveAttribute(
      'aria-required',
      'true',
    );
    expect(screen.getAllByRole('radio')).toHaveLength(5);
    expect(screen.getByRole('radio', { name: 'Rate 3 out of 5' })).not.toBeChecked();
  });

  it('uses one tab stop and React Aria arrow-key selection', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(<ControlledStarRating onChange={onChange} />);

    const radios = screen.getAllByRole('radio');
    await user.tab();
    expect(radios[0]).toHaveFocus();
    expect(radios.filter((radio) => radio.tabIndex === 0)).toHaveLength(1);

    await user.keyboard('{ArrowRight}');
    expect(radios[1]).toHaveFocus();
    expect(radios[1]).toBeChecked();
    expect(onChange).toHaveBeenLastCalledWith(2);
    expect(radios.filter((radio) => radio.tabIndex === 0)).toHaveLength(1);
  });

  it('provides 44px targets and selected, hover, and focus-visible styling hooks', () => {
    render(<ControlledStarRating />);

    const thirdRadio = screen.getByRole('radio', { name: 'Rate 3 out of 5' });
    const target = thirdRadio.closest('[data-slot="radio"]');
    expect(target).toHaveClass('size-11', 'min-h-11', 'min-w-11');
    expect(target).toHaveClass(
      'hover:scale-110',
      'data-[selected=true]:bg-warning/10',
      'data-[focus-visible=true]:outline-2',
      '[&:has(input:checked)]:bg-warning/10',
      '[&:has(input:focus-visible)]:outline-2',
    );

    fireEvent.mouseEnter(target!);
    expect(document.querySelectorAll('[data-filled="true"]')).toHaveLength(3);
    fireEvent.mouseLeave(target!);
    expect(document.querySelectorAll('[data-filled="true"]')).toHaveLength(0);

    fireEvent.click(thirdRadio);
    const stars = document.querySelectorAll('[data-filled="true"]');
    expect(stars).toHaveLength(3);
    expect(screen.getByText('Selected 3')).toBeInTheDocument();
  });
});
