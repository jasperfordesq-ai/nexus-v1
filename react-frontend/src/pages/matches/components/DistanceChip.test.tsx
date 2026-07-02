// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for DistanceChip
 */

import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@/test/test-utils';

vi.mock('@/components/ui', async () => (await import('@/test/uiMock')).uiMock);

import { DistanceChip } from './DistanceChip';

describe('DistanceChip', () => {
  it('renders a distance chip with one decimal place when distanceKm is set', () => {
    render(<DistanceChip distanceKm={3.456} />);
    expect(screen.getByText('3.5 km')).toBeInTheDocument();
  });

  it('renders a remote chip when isRemote is true and no distance is known', () => {
    render(<DistanceChip isRemote />);
    expect(screen.getByText('Remote')).toBeInTheDocument();
  });

  it('prefers distance over remote when both are provided', () => {
    render(<DistanceChip distanceKm={10} isRemote />);
    expect(screen.getByText('10.0 km')).toBeInTheDocument();
    expect(screen.queryByText('Remote')).not.toBeInTheDocument();
  });

  it('renders nothing when neither distanceKm nor isRemote is set', () => {
    const { container } = render(<DistanceChip />);
    expect(container).toBeEmptyDOMElement();
  });
});
