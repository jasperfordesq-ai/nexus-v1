// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import { ExploreSection } from './ExploreSection';

vi.mock('@/contexts', () => createMockContexts());

describe('ExploreSection', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the section title', () => {
    render(
      <ExploreSection title="Top Listings">
        <p>child content</p>
      </ExploreSection>
    );
    expect(screen.getByRole('heading', { level: 2, name: /top listings/i })).toBeInTheDocument();
  });

  it('renders children', () => {
    render(
      <ExploreSection title="Events">
        <div>event card</div>
      </ExploreSection>
    );
    expect(screen.getByText('event card')).toBeInTheDocument();
  });

  it('renders subtitle when provided', () => {
    render(
      <ExploreSection title="Members" subtitle="Active this week">
        <span />
      </ExploreSection>
    );
    expect(screen.getByText('Active this week')).toBeInTheDocument();
  });

  it('does not render subtitle when omitted', () => {
    render(
      <ExploreSection title="Members">
        <span />
      </ExploreSection>
    );
    // No <p> aside from any potential children
    expect(screen.queryByText(/active this week/i)).not.toBeInTheDocument();
  });

  it('renders the "See All" link when seeAllLink is provided', () => {
    render(
      <ExploreSection title="Groups" seeAllLink="/groups">
        <span />
      </ExploreSection>
    );
    const link = screen.getByRole('link');
    expect(link).toBeInTheDocument();
    expect(link).toHaveAttribute('href', '/groups');
  });

  it('uses custom seeAllLabel when provided', () => {
    render(
      <ExploreSection title="Groups" seeAllLink="/groups" seeAllLabel="View all groups">
        <span />
      </ExploreSection>
    );
    expect(screen.getByRole('link', { name: /view all groups/i })).toBeInTheDocument();
  });

  it('falls back to i18n "see_all" label when seeAllLabel is omitted but seeAllLink is set', () => {
    render(
      <ExploreSection title="Groups" seeAllLink="/groups">
        <span />
      </ExploreSection>
    );
    // The real i18n key "see_all" will resolve to the key string in test (or "See all")
    // We just assert a link is present with some text
    expect(screen.getByRole('link')).toBeInTheDocument();
  });

  it('does not render a link when seeAllLink is omitted', () => {
    render(
      <ExploreSection title="Groups">
        <span />
      </ExploreSection>
    );
    expect(screen.queryByRole('link')).not.toBeInTheDocument();
  });

  it('applies extra className to the section', () => {
    const { container } = render(
      <ExploreSection title="X" className="my-custom-class">
        <span />
      </ExploreSection>
    );
    // motion.section renders as a <section>
    const section = container.querySelector('section');
    expect(section?.className).toContain('my-custom-class');
  });
});
