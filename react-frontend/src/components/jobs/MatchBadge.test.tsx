// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import type { MatchResult } from './JobDetailTypes';

beforeEach(() => {
  vi.resetAllMocks();
});

/** Build a MatchResult with defaults that produce a visible badge */
function makeMatch(overrides: Partial<MatchResult> = {}): MatchResult {
  return {
    percentage: 75,
    matched: ['TypeScript'],
    missing: [],
    user_skills: ['TypeScript'],
    required_skills: ['TypeScript'],
    ...overrides,
  };
}

describe('MatchBadge', () => {
  it('renders nothing when required_skills is empty', async () => {
    const { MatchBadge } = await import('./MatchBadge');
    render(<MatchBadge match={makeMatch({ required_skills: [] })} />);
    // Component returns null — neither percentage text nor label should appear
    expect(screen.queryByText(/Skills Match/i)).not.toBeInTheDocument();
    expect(screen.queryByText(/%/)).not.toBeInTheDocument();
  });

  it('shows the percentage when required_skills are present', async () => {
    const { MatchBadge } = await import('./MatchBadge');
    render(<MatchBadge match={makeMatch({ percentage: 75 })} />);
    expect(screen.getByText(/75%/)).toBeInTheDocument();
  });

  it('shows the "Skills Match" title text', async () => {
    const { MatchBadge } = await import('./MatchBadge');
    render(<MatchBadge match={makeMatch()} />);
    expect(screen.getByText(/Skills Match/i)).toBeInTheDocument();
  });

  it('shows "Excellent Match" label for percentage >= 80', async () => {
    const { MatchBadge } = await import('./MatchBadge');
    render(<MatchBadge match={makeMatch({ percentage: 90 })} />);
    expect(screen.getByText('Excellent Match')).toBeInTheDocument();
  });

  it('shows "Good Match" label for percentage 60–79', async () => {
    const { MatchBadge } = await import('./MatchBadge');
    render(<MatchBadge match={makeMatch({ percentage: 65 })} />);
    expect(screen.getByText('Good Match')).toBeInTheDocument();
  });

  it('shows "Moderate Match" label for percentage 40–59', async () => {
    const { MatchBadge } = await import('./MatchBadge');
    render(<MatchBadge match={makeMatch({ percentage: 50 })} />);
    expect(screen.getByText('Moderate Match')).toBeInTheDocument();
  });

  it('shows "Low Match" label for percentage < 40', async () => {
    const { MatchBadge } = await import('./MatchBadge');
    render(<MatchBadge match={makeMatch({ percentage: 20 })} />);
    expect(screen.getByText('Low Match')).toBeInTheDocument();
  });

  it('renders the Target icon (SVG)', async () => {
    const { MatchBadge } = await import('./MatchBadge');
    const { container } = render(<MatchBadge match={makeMatch()} />);
    expect(container.querySelector('svg')).toBeInTheDocument();
  });

  it('does not render label text when required_skills is empty', async () => {
    const { MatchBadge } = await import('./MatchBadge');
    render(<MatchBadge match={makeMatch({ required_skills: [] })} />);
    expect(screen.queryByText('Excellent Match')).not.toBeInTheDocument();
    expect(screen.queryByText('Good Match')).not.toBeInTheDocument();
    expect(screen.queryByText('Moderate Match')).not.toBeInTheDocument();
    expect(screen.queryByText('Low Match')).not.toBeInTheDocument();
  });

  it('shows exact percentage in chip text at boundary 80%', async () => {
    const { MatchBadge } = await import('./MatchBadge');
    render(<MatchBadge match={makeMatch({ percentage: 80 })} />);
    expect(screen.getByText(/80%/)).toBeInTheDocument();
    expect(screen.getByText('Excellent Match')).toBeInTheDocument();
  });
});
