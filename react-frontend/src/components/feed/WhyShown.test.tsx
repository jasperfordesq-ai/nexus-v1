// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import { WhyShown } from './WhyShown';
import type { FeedItem } from './types';

vi.mock('@/contexts', () => createMockContexts());

/** Minimal FeedItem fixture — only fields WhyShown cares about */
function makeFeedItem(overrides: Partial<FeedItem> = {}): FeedItem {
  return {
    id: 1,
    content: 'Hello world',
    type: 'post',
    likes_count: 0,
    comments_count: 0,
    is_liked: false,
    created_at: '2026-01-01T00:00:00Z',
    ...overrides,
  };
}

describe('WhyShown — recent feed mode', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders nothing when feedMode is "recent"', () => {
    render(<WhyShown item={makeFeedItem()} feedMode="recent" />);
    // WhyShown returns null — no button should appear in the DOM
    expect(screen.queryByRole('button')).not.toBeInTheDocument();
  });
});

describe('WhyShown — ranking feed mode', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the info trigger button in ranking mode', () => {
    render(<WhyShown item={makeFeedItem()} feedMode="ranking" />);
    // The info icon button carries an aria-label from "why_shown.label" i18n key
    expect(screen.getByRole('button')).toBeInTheDocument();
  });

  it('button has an aria-label', () => {
    render(<WhyShown item={makeFeedItem()} feedMode="ranking" />);
    const btn = screen.getByRole('button');
    expect(btn).toHaveAttribute('aria-label');
  });

  it('popover content is not visible before trigger is clicked', () => {
    render(<WhyShown item={makeFeedItem()} feedMode="ranking" />);
    // The title rendered inside the popover comes from "why_shown.title" key;
    // before opening it should not appear in the DOM (or at least not be visible).
    // We query for the bullet-list character that only appears when reasons are listed.
    expect(screen.queryByRole('list')).not.toBeInTheDocument();
  });

  it('opens popover and shows title when trigger is clicked', () => {
    render(<WhyShown item={makeFeedItem()} feedMode="ranking" />);
    fireEvent.click(screen.getByRole('button'));
    // After click the popover renders — find the paragraph with the heading text.
    // The i18n key "why_shown.title" resolves to the key in test env; we assert the
    // element is present rather than pinning to the English string.
    expect(screen.getByRole('list')).toBeInTheDocument();
  });

  it('shows backend ranking_reasons when provided', () => {
    const item = makeFeedItem({ ranking_reasons: ['Highly rated in your area', 'Matches your skills'] });
    render(<WhyShown item={item} feedMode="ranking" />);
    fireEvent.click(screen.getByRole('button'));
    expect(screen.getByText('Highly rated in your area')).toBeInTheDocument();
    expect(screen.getByText('Matches your skills')).toBeInTheDocument();
  });

  it('shows a single bullet when ranking_reasons is empty array', () => {
    const item = makeFeedItem({ ranking_reasons: [] });
    render(<WhyShown item={item} feedMode="ranking" />);
    fireEvent.click(screen.getByRole('button'));
    const bullets = screen.getAllByRole('listitem');
    expect(bullets).toHaveLength(1);
  });

  it('shows a single bullet when ranking_reasons is absent', () => {
    const item = makeFeedItem({ ranking_reasons: undefined });
    render(<WhyShown item={item} feedMode="ranking" />);
    fireEvent.click(screen.getByRole('button'));
    const bullets = screen.getAllByRole('listitem');
    expect(bullets).toHaveLength(1);
  });

  it('renders as many list items as there are backend reasons', () => {
    const reasons = ['Reason A', 'Reason B', 'Reason C'];
    const item = makeFeedItem({ ranking_reasons: reasons });
    render(<WhyShown item={item} feedMode="ranking" />);
    fireEvent.click(screen.getByRole('button'));
    expect(screen.getAllByRole('listitem')).toHaveLength(3);
  });
});
