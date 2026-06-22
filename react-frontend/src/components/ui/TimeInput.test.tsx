// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { TimeInput } from './TimeInput';

describe('TimeInput', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders at least one group element (the input wrapper)', () => {
    // HeroUI TimeField renders nested group elements (outer field group + inner input group)
    render(<TimeInput />);
    const groups = screen.getAllByRole('group');
    expect(groups.length).toBeGreaterThanOrEqual(1);
  });

  it('renders a visible label when the label prop is supplied', () => {
    render(<TimeInput label="Start time" />);
    expect(screen.getByText('Start time')).toBeInTheDocument();
  });

  it('does not render a label element when label is omitted', () => {
    render(<TimeInput />);
    // No label text present
    expect(screen.queryByText('Start time')).toBeNull();
  });

  it('renders a description when provided and not invalid', () => {
    render(<TimeInput description="Pick a time" />);
    expect(screen.getByText('Pick a time')).toBeInTheDocument();
  });

  it('renders errorMessage instead of description when isInvalid is true', () => {
    render(
      <TimeInput
        isInvalid
        errorMessage="Time is required"
        description="Pick a time"
      />
    );
    expect(screen.getByText('Time is required')).toBeInTheDocument();
    // description should not appear alongside the error
    expect(screen.queryByText('Pick a time')).toBeNull();
  });

  it('does not render errorMessage when isInvalid is false', () => {
    render(<TimeInput isInvalid={false} errorMessage="Error text" />);
    expect(screen.queryByText('Error text')).toBeNull();
  });

  it('renders startContent when supplied', () => {
    render(<TimeInput startContent={<span data-testid="prefix">⏰</span>} />);
    expect(screen.getByTestId('prefix')).toBeInTheDocument();
  });

  it('renders endContent when supplied', () => {
    render(<TimeInput endContent={<span data-testid="suffix">→</span>} />);
    expect(screen.getByTestId('suffix')).toBeInTheDocument();
  });

  it('accepts variant prop without crashing', () => {
    render(<TimeInput variant="bordered" label="Time" />);
    expect(screen.getByText('Time')).toBeInTheDocument();
  });

  it('applies w-full via fullWidth prop without crashing', () => {
    render(<TimeInput fullWidth label="Full width time" />);
    expect(screen.getByText('Full width time')).toBeInTheDocument();
  });
});
