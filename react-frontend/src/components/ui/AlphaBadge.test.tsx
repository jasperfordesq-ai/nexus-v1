// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';

beforeEach(() => {
  vi.resetAllMocks();
});

describe('AlphaBadge', () => {
  it('renders the default translated "Alpha" label', async () => {
    const { AlphaBadge } = await import('./AlphaBadge');
    render(<AlphaBadge />);
    expect(screen.getByText('Alpha')).toBeInTheDocument();
  });

  it('uses the translated "Alpha" for aria-label by default', async () => {
    const { AlphaBadge } = await import('./AlphaBadge');
    render(<AlphaBadge />);
    const chip = screen.getByLabelText('Alpha');
    expect(chip).toBeInTheDocument();
  });

  it('renders a custom label when label prop is provided', async () => {
    const { AlphaBadge } = await import('./AlphaBadge');
    render(<AlphaBadge label="Preview" />);
    expect(screen.getByText('Preview')).toBeInTheDocument();
    expect(screen.queryByText('Alpha')).not.toBeInTheDocument();
  });

  it('applies extra className to the Chip', async () => {
    const { AlphaBadge } = await import('./AlphaBadge');
    const { container } = render(<AlphaBadge className="my-custom-class" />);
    expect(container.querySelector('.my-custom-class')).toBeInTheDocument();
  });

  it('renders with size "sm" by default (no size prop)', async () => {
    const { AlphaBadge } = await import('./AlphaBadge');
    render(<AlphaBadge />);
    // The badge renders and the text is visible — size is applied as prop to Chip
    expect(screen.getByText('Alpha')).toBeInTheDocument();
  });

  it('accepts size="md" without error and still renders the label', async () => {
    const { AlphaBadge } = await import('./AlphaBadge');
    render(<AlphaBadge size="md" />);
    expect(screen.getByText('Alpha')).toBeInTheDocument();
  });

  it('renders a custom label with size="md"', async () => {
    const { AlphaBadge } = await import('./AlphaBadge');
    render(<AlphaBadge label="Beta" size="md" />);
    expect(screen.getByText('Beta')).toBeInTheDocument();
  });

  it('still shows aria-label="Alpha" even when a custom label is supplied', async () => {
    const { AlphaBadge } = await import('./AlphaBadge');
    // aria-label comes from t('alpha_badge') regardless of the label prop
    render(<AlphaBadge label="Experimental" />);
    const chip = screen.getByLabelText('Alpha');
    expect(chip).toBeInTheDocument();
  });
});
