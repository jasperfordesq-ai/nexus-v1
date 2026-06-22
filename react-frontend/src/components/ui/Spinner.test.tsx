// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { Spinner } from './Spinner';

describe('Spinner', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // HeroUI's Spinner component itself also emits role="status" internally, so
  // there are always two: one from the HeroUI spinner, one from our wrapper span.
  // We query with getAllByRole and assert the wrapper is the first element.
  it('renders at least one status element', () => {
    render(<Spinner />);
    const statuses = screen.getAllByRole('status');
    expect(statuses.length).toBeGreaterThanOrEqual(1);
  });

  it('the outermost status element has an aria-label', () => {
    render(<Spinner />);
    // The first role="status" in the DOM is our wrapper span
    const statuses = screen.getAllByRole('status');
    expect(statuses[0]).toHaveAttribute('aria-label');
  });

  it('uses the aria-label prop when provided', () => {
    render(<Spinner aria-label="Saving changes" />);
    const statuses = screen.getAllByRole('status');
    // Our wrapper span carries the resolved aria-label
    expect(statuses[0]).toHaveAttribute('aria-label', 'Saving changes');
  });

  it('renders the label text when a label prop is given', () => {
    render(<Spinner label="Please wait" />);
    expect(screen.getByText('Please wait')).toBeInTheDocument();
  });

  it('uses the label string as the aria-label on the wrapper when no explicit aria-label supplied', () => {
    render(<Spinner label="Uploading" />);
    const statuses = screen.getAllByRole('status');
    expect(statuses[0]).toHaveAttribute('aria-label', 'Uploading');
  });

  it('does not render any visible label text when label is not provided', () => {
    render(<Spinner />);
    // No label span — only the status elements themselves
    expect(screen.queryByText('Please wait')).toBeNull();
  });

  it('accepts a className prop without crashing', () => {
    render(<Spinner className="custom-spinner" />);
    expect(screen.getAllByRole('status').length).toBeGreaterThanOrEqual(1);
  });

  it('maps the "primary" color without crashing', () => {
    render(<Spinner color="primary" />);
    expect(screen.getAllByRole('status').length).toBeGreaterThanOrEqual(1);
  });

  it('maps the "danger" color without crashing', () => {
    render(<Spinner color="danger" />);
    expect(screen.getAllByRole('status').length).toBeGreaterThanOrEqual(1);
  });

  it('maps unknown/default color without crashing', () => {
    render(<Spinner color="default" />);
    expect(screen.getAllByRole('status').length).toBeGreaterThanOrEqual(1);
  });
});
