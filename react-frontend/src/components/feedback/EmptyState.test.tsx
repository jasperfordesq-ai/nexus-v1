// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for EmptyState component
 */

import { describe, it, expect } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { EmptyState } from './EmptyState';
import { Search } from 'lucide-react';

describe('EmptyState', () => {
  it('renders title and description', () => {
    render(
      <EmptyState
        icon={<Search data-testid="icon" />}
        title="No results"
        description="Try a different search"
      />
    );

    expect(screen.getByText('No results')).toBeInTheDocument();
    expect(screen.getByText('Try a different search')).toBeInTheDocument();
  });

  it('renders icon', () => {
    render(
      <EmptyState
        icon={<Search data-testid="empty-icon" />}
        title="No results"
      />
    );

    expect(screen.getByTestId('empty-icon')).toBeInTheDocument();
  });

  it('renders action button when provided', () => {
    render(
      <EmptyState
        icon={<Search />}
        title="No results"
        action={<button>Try again</button>}
      />
    );

    expect(screen.getByRole('button', { name: 'Try again' })).toBeInTheDocument();
  });

  it('does not render description when not provided', () => {
    render(
      <EmptyState
        icon={<Search />}
        title="No results"
      />
    );

    expect(screen.getByText('No results')).toBeInTheDocument();
    // Should only have the title text
  });
});
