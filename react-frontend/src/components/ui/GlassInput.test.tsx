// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { GlassInput } from './GlassInput';

describe('GlassInput', () => {
  it('renders an input element', () => {
    render(<GlassInput placeholder="Enter text" />);
    expect(screen.getByPlaceholderText('Enter text')).toBeInTheDocument();
  });

  it('renders label when provided', () => {
    render(<GlassInput label="Email" />);
    expect(screen.getByLabelText('Email')).toBeInTheDocument();
  });

  it('renders error message', () => {
    render(<GlassInput label="Email" error="Invalid email" />);
    expect(screen.getByRole('alert')).toHaveTextContent('Invalid email');
  });

  it('renders helper text when no error', () => {
    render(<GlassInput label="Email" helperText="We will never share your email" />);
    expect(screen.getByText('We will never share your email')).toBeInTheDocument();
  });

  it('hides helper text when error is shown', () => {
    render(<GlassInput label="Email" error="Required" helperText="Helper" />);
    expect(screen.getByRole('alert')).toHaveTextContent('Required');
    expect(screen.queryByText('Helper')).not.toBeInTheDocument();
  });

  it('sets aria-invalid when error exists', () => {
    render(<GlassInput label="Email" error="Bad" />);
    expect(screen.getByLabelText('Email')).toHaveAttribute('aria-invalid', 'true');
  });

  it('applies glass-input class', () => {
    render(<GlassInput data-testid="input" />);
    expect(screen.getByTestId('input')).toHaveClass('glass-input');
  });

  it('applies error class when error exists', () => {
    render(<GlassInput label="Name" error="Required" />);
    expect(screen.getByLabelText('Name')).toHaveClass('glass-input-error');
  });

  it('can be disabled', () => {
    render(<GlassInput label="Name" disabled />);
    expect(screen.getByLabelText('Name')).toBeDisabled();
  });
});
