// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for the ui/Textarea wrapper component.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import { Textarea } from './Textarea';

vi.mock('@/contexts', () => createMockContexts());

beforeEach(() => {
  vi.clearAllMocks();
});

// ─── Basic rendering ─────────────────────────────────────────────────────────

describe('Textarea — basic rendering', () => {
  it('renders a textarea element', () => {
    render(<Textarea />);
    expect(screen.getByRole('textbox')).toBeInTheDocument();
  });

  it('uses the mobile no-zoom font baseline', () => {
    render(<Textarea />);
    expect(screen.getByRole('textbox')).toHaveClass('text-base', 'sm:text-sm');
  });

  it('renders with placeholder text', () => {
    render(<Textarea placeholder="Type here…" />);
    expect(screen.getByPlaceholderText('Type here…')).toBeInTheDocument();
  });

  it('renders a label when provided', () => {
    render(<Textarea label="Bio" />);
    expect(screen.getByText('Bio')).toBeInTheDocument();
  });

  it('renders description text when provided', () => {
    render(<Textarea label="Notes" description="Write your notes here" />);
    expect(screen.getByText('Write your notes here')).toBeInTheDocument();
  });

  it('renders an error message when provided', () => {
    render(<Textarea label="Field" errorMessage="This field is required" isInvalid />);
    expect(screen.getByText('This field is required')).toBeInTheDocument();
  });

  it('renders error message from function', () => {
    render(
      <Textarea
        label="Field"
        value="bad"
        errorMessage={(val) => `Invalid: ${val}`}
        isInvalid
      />,
    );
    expect(screen.getByText('Invalid: bad')).toBeInTheDocument();
  });
});

// ─── Controlled value ────────────────────────────────────────────────────────

describe('Textarea — controlled value', () => {
  it('displays the controlled value', () => {
    render(<Textarea value="Hello world" onChange={vi.fn()} />);
    expect(screen.getByRole('textbox')).toHaveValue('Hello world');
  });
});

// ─── onChange / onValueChange ────────────────────────────────────────────────

describe('Textarea — onChange / onValueChange', () => {
  it('fires onChange with the native event', () => {
    const onChange = vi.fn();
    render(<Textarea onChange={onChange} />);
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'typed' } });
    expect(onChange).toHaveBeenCalledTimes(1);
    expect(onChange.mock.calls[0][0].target.value).toBe('typed');
  });

  it('fires onValueChange with the string value', () => {
    const onValueChange = vi.fn();
    render(<Textarea onValueChange={onValueChange} />);
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'hello' } });
    expect(onValueChange).toHaveBeenCalledWith('hello');
  });

  it('fires both onChange and onValueChange when both are provided', () => {
    const onChange = vi.fn();
    const onValueChange = vi.fn();
    render(<Textarea onChange={onChange} onValueChange={onValueChange} />);
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'both' } });
    expect(onChange).toHaveBeenCalledTimes(1);
    expect(onValueChange).toHaveBeenCalledWith('both');
  });
});

// ─── Disabled / read-only ────────────────────────────────────────────────────

describe('Textarea — disabled / read-only', () => {
  it('is disabled when isDisabled is true', () => {
    render(<Textarea isDisabled />);
    expect(screen.getByRole('textbox')).toBeDisabled();
  });

  it('is read-only when isReadOnly is true', () => {
    render(<Textarea isReadOnly value="immutable" />);
    expect(screen.getByRole('textbox')).toHaveAttribute('readonly');
  });
});

// ─── startContent / endContent ───────────────────────────────────────────────

describe('Textarea — startContent / endContent', () => {
  it('renders startContent slot', () => {
    render(<Textarea startContent={<span data-testid="start-icon" />} label="With icon" />);
    expect(screen.getByTestId('start-icon')).toBeInTheDocument();
  });

  it('renders endContent slot', () => {
    render(<Textarea endContent={<span data-testid="end-icon" />} label="With icon" />);
    expect(screen.getByTestId('end-icon')).toBeInTheDocument();
  });
});
