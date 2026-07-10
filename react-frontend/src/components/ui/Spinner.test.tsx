// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { Spinner } from './Spinner';

describe('Spinner', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('exposes one named status while keeping the HeroUI animation decorative', () => {
    const { container } = render(<Spinner />);
    const status = screen.getByRole('status');
    const animation = container.querySelector('[data-slot="spinner"]');

    expect(status).toHaveAccessibleName('Loading...');
    expect(animation).toHaveAttribute('aria-hidden', 'true');
    expect(animation).not.toHaveAttribute('aria-label');
  });

  it('uses the aria-label prop when provided', () => {
    render(<Spinner aria-label="Saving changes" />);
    expect(screen.getByRole('status')).toHaveAttribute('aria-label', 'Saving changes');
  });

  it('renders the label text and uses it as the accessible name', () => {
    render(<Spinner label="Please wait" />);
    expect(screen.getByText('Please wait')).toBeInTheDocument();
    expect(screen.getByRole('status')).toHaveAccessibleName('Please wait');
  });

  it('does not render visible label text when label is not provided', () => {
    render(<Spinner />);
    expect(screen.queryByText('Please wait')).toBeNull();
  });

  it.each(['primary', 'danger', 'default'] as const)(
    'maps the %s color without changing the status semantics',
    (color) => {
      render(<Spinner color={color} className="custom-spinner" />);
      expect(screen.getByRole('status')).toBeInTheDocument();
    },
  );
});
