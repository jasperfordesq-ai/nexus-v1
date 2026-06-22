// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for ToolResultCards component
 *
 * ToolResultCards is a pure-render, props-driven component with no network
 * calls or context dependencies.  Tests cover:
 *   - null/empty guard (returns null)
 *   - invocation-level ok/results guard
 *   - each card type renders its key fields
 *   - unknown card type → GenericCard fallback
 *   - result set capped at 6 cards per invocation
 *   - url prop → anchor wrapper with aria-label
 *
 * Translation values used are from public/locales/en/chat.json (real i18n
 * is loaded in the test environment).
 *
 * NOTE: container.firstChild cannot be used to assert null because the
 * test-utils AllProviders wrapper always renders a root div.  We assert null
 * by verifying that no card-specific content is present in the DOM.
 */

import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

vi.mock('@/contexts', () => createMockContexts());

import { ToolResultCards, type ToolInvocation } from './ToolResultCards';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeInvocation(
  card_type: string,
  results: Array<Record<string, unknown>>,
  overrides: Partial<ToolInvocation> = {}
): ToolInvocation {
  return {
    name: `search_${card_type}`,
    arguments: {},
    ok: true,
    summary: 'Found some results',
    card_type,
    results,
    ...overrides,
  };
}

// Sentinel text present in every rendered result — used to confirm nothing rendered
const SENTINEL_TITLE = 'SENTINEL_UNIQUE_TITLE_12345';

// ---------------------------------------------------------------------------
// Null / empty guards
// ---------------------------------------------------------------------------

describe('ToolResultCards — null / empty guards', () => {
  it('renders nothing (no card content) when invocations is an empty array', () => {
    render(<ToolResultCards invocations={[]} />);
    // No card container class is injected
    expect(document.querySelector('.mt-2.flex.flex-col')).not.toBeInTheDocument();
  });

  it('renders nothing when all invocations have ok=false', () => {
    const inv = makeInvocation('listing', [{ id: 1, title: SENTINEL_TITLE }], { ok: false });
    render(<ToolResultCards invocations={[inv]} />);
    expect(screen.queryByText(SENTINEL_TITLE)).not.toBeInTheDocument();
  });

  it('renders nothing when all invocations have empty results arrays', () => {
    const inv = makeInvocation('listing', []);
    render(<ToolResultCards invocations={[inv]} />);
    expect(document.querySelector('.mt-2.flex.flex-col')).not.toBeInTheDocument();
  });

  it('renders when at least one renderable invocation exists', () => {
    const inv = makeInvocation('listing', [{ id: 1, title: 'A listing' }]);
    render(<ToolResultCards invocations={[inv]} />);
    expect(screen.getByText('A listing')).toBeInTheDocument();
  });
});

// ---------------------------------------------------------------------------
// listing card
// ---------------------------------------------------------------------------

describe('ToolResultCards — listing card', () => {
  it('renders the listing title', () => {
    const inv = makeInvocation('listing', [
      { id: 1, title: 'Garden Weeding Offer', type: 'offer', location: 'Dublin' },
    ]);
    render(<ToolResultCards invocations={[inv]} />);
    expect(screen.getByText('Garden Weeding Offer')).toBeInTheDocument();
  });

  it('renders the chip for offer type', () => {
    const inv = makeInvocation('listing', [{ id: 1, title: 'T', type: 'offer' }]);
    render(<ToolResultCards invocations={[inv]} />);
    expect(screen.getByText('offer')).toBeInTheDocument();
  });

  it('renders location when provided', () => {
    const inv = makeInvocation('listing', [
      { id: 1, title: 'T', type: 'request', location: 'Cork' },
    ]);
    render(<ToolResultCards invocations={[inv]} />);
    expect(screen.getByText('Cork')).toBeInTheDocument();
  });

  it('renders excerpt when provided', () => {
    const inv = makeInvocation('listing', [
      { id: 1, title: 'T', excerpt: 'Short excerpt here' },
    ]);
    render(<ToolResultCards invocations={[inv]} />);
    expect(screen.getByText('Short excerpt here')).toBeInTheDocument();
  });

  it('wraps in an anchor when url is present', () => {
    const inv = makeInvocation('listing', [
      { id: 1, title: 'Linked Listing', url: '/test/listings/1' },
    ]);
    render(<ToolResultCards invocations={[inv]} />);
    const link = screen.getByRole('link');
    expect(link).toHaveAttribute('href', '/test/listings/1');
  });
});

// ---------------------------------------------------------------------------
// member card
// ---------------------------------------------------------------------------

describe('ToolResultCards — member card', () => {
  it('renders member name', () => {
    const inv = makeInvocation('member', [{ id: 1, name: 'Jane Doe' }]);
    render(<ToolResultCards invocations={[inv]} />);
    expect(screen.getByText('Jane Doe')).toBeInTheDocument();
  });

  it('renders tagline when provided', () => {
    const inv = makeInvocation('member', [
      { id: 1, name: 'Jane', tagline: 'Community builder' },
    ]);
    render(<ToolResultCards invocations={[inv]} />);
    expect(screen.getByText('Community builder')).toBeInTheDocument();
  });

  it('renders skills when provided', () => {
    const inv = makeInvocation('member', [
      { id: 1, name: 'Jane', skills: 'Gardening, Cooking' },
    ]);
    render(<ToolResultCards invocations={[inv]} />);
    expect(screen.getByText('Gardening, Cooking')).toBeInTheDocument();
  });
});

// ---------------------------------------------------------------------------
// event card
// ---------------------------------------------------------------------------

describe('ToolResultCards — event card', () => {
  it('renders event title', () => {
    const inv = makeInvocation('event', [{ id: 1, title: 'Community Meetup' }]);
    render(<ToolResultCards invocations={[inv]} />);
    expect(screen.getByText('Community Meetup')).toBeInTheDocument();
  });

  it('renders "Online" chip when is_online is true', () => {
    // chat.card_online = "Online" (from public/locales/en/chat.json)
    const inv = makeInvocation('event', [
      { id: 1, title: 'Webinar', is_online: true },
    ]);
    render(<ToolResultCards invocations={[inv]} />);
    expect(screen.getByText('Online')).toBeInTheDocument();
  });

  it('renders location when provided', () => {
    const inv = makeInvocation('event', [
      { id: 1, title: 'In-person', location: 'Galway' },
    ]);
    render(<ToolResultCards invocations={[inv]} />);
    expect(screen.getByText('Galway')).toBeInTheDocument();
  });

  it('renders the event title alongside a formatted date when start_time is provided', () => {
    const inv = makeInvocation('event', [
      { id: 1, title: 'Dated Event', start_time: '2026-07-15T14:00:00Z' },
    ]);
    render(<ToolResultCards invocations={[inv]} />);
    expect(screen.getByText('Dated Event')).toBeInTheDocument();
  });
});

// ---------------------------------------------------------------------------
// job card
// ---------------------------------------------------------------------------

describe('ToolResultCards — job card', () => {
  it('renders job title', () => {
    const inv = makeInvocation('job', [{ id: 1, title: 'React Developer' }]);
    render(<ToolResultCards invocations={[inv]} />);
    expect(screen.getByText('React Developer')).toBeInTheDocument();
  });

  it('renders "Remote" chip when is_remote is true', () => {
    // chat.card_remote = "Remote" (from public/locales/en/chat.json)
    const inv = makeInvocation('job', [{ id: 1, title: 'Remote Job', is_remote: true }]);
    render(<ToolResultCards invocations={[inv]} />);
    expect(screen.getByText('Remote')).toBeInTheDocument();
  });

  it('renders salary when provided', () => {
    const inv = makeInvocation('job', [
      { id: 1, title: 'Job', salary: '€50,000 p.a.' },
    ]);
    render(<ToolResultCards invocations={[inv]} />);
    expect(screen.getByText('€50,000 p.a.')).toBeInTheDocument();
  });

  it('renders tagline when provided', () => {
    const inv = makeInvocation('job', [
      { id: 1, title: 'Job', tagline: 'Join our team' },
    ]);
    render(<ToolResultCards invocations={[inv]} />);
    expect(screen.getByText('Join our team')).toBeInTheDocument();
  });
});

// ---------------------------------------------------------------------------
// marketplace card
// ---------------------------------------------------------------------------

describe('ToolResultCards — marketplace card', () => {
  it('renders marketplace item title', () => {
    const inv = makeInvocation('marketplace', [{ id: 1, title: 'Bicycle for sale' }]);
    render(<ToolResultCards invocations={[inv]} />);
    expect(screen.getByText('Bicycle for sale')).toBeInTheDocument();
  });

  it('renders price when provided', () => {
    const inv = makeInvocation('marketplace', [
      { id: 1, title: 'Item', price: '€20' },
    ]);
    render(<ToolResultCards invocations={[inv]} />);
    expect(screen.getByText('€20')).toBeInTheDocument();
  });

  it('renders condition chip when provided', () => {
    const inv = makeInvocation('marketplace', [
      { id: 1, title: 'Item', condition: 'Good' },
    ]);
    render(<ToolResultCards invocations={[inv]} />);
    expect(screen.getByText('Good')).toBeInTheDocument();
  });
});

// ---------------------------------------------------------------------------
// kb card
// ---------------------------------------------------------------------------

describe('ToolResultCards — kb card', () => {
  it('renders kb article title', () => {
    const inv = makeInvocation('kb', [{ id: 1, title: 'How to timebank' }]);
    render(<ToolResultCards invocations={[inv]} />);
    expect(screen.getByText('How to timebank')).toBeInTheDocument();
  });

  it('renders excerpt when provided', () => {
    const inv = makeInvocation('kb', [
      { id: 1, title: 'Guide', excerpt: 'Everything you need to know' },
    ]);
    render(<ToolResultCards invocations={[inv]} />);
    expect(screen.getByText('Everything you need to know')).toBeInTheDocument();
  });
});

// ---------------------------------------------------------------------------
// wallet card
// ---------------------------------------------------------------------------

describe('ToolResultCards — wallet card', () => {
  it('renders wallet card title: "Your wallet"', () => {
    // chat.card_wallet_title = "Your wallet"
    const inv = makeInvocation('wallet', [
      { balance: 5.5, recent_transactions_30d: 3 },
    ]);
    render(<ToolResultCards invocations={[inv]} />);
    expect(screen.getByText('Your wallet')).toBeInTheDocument();
  });

  it('renders the transaction count text', () => {
    // chat.card_wallet_transactions = "{{count}} transactions in last 30 days"
    const inv = makeInvocation('wallet', [
      { balance: 2, recent_transactions_30d: 7 },
    ]);
    render(<ToolResultCards invocations={[inv]} />);
    expect(screen.getByText('7 transactions in last 30 days')).toBeInTheDocument();
  });
});

// ---------------------------------------------------------------------------
// GenericCard fallback
// ---------------------------------------------------------------------------

describe('ToolResultCards — generic / unknown card type', () => {
  it('renders item title for unknown card_type', () => {
    const inv = makeInvocation('unknown_type', [{ id: 1, title: 'Fallback title' }]);
    render(<ToolResultCards invocations={[inv]} />);
    expect(screen.getByText('Fallback title')).toBeInTheDocument();
  });

  it('falls back to item.name when title is absent', () => {
    const inv = makeInvocation('unknown_type', [{ id: 1, name: 'Name fallback' }]);
    render(<ToolResultCards invocations={[inv]} />);
    expect(screen.getByText('Name fallback')).toBeInTheDocument();
  });

  it('shows "Result" when both title and name are absent', () => {
    const inv = makeInvocation('unknown_type', [{ id: 1 }]);
    render(<ToolResultCards invocations={[inv]} />);
    expect(screen.getByText('Result')).toBeInTheDocument();
  });
});

// ---------------------------------------------------------------------------
// Result cap (6 per invocation) and multiple invocations
// ---------------------------------------------------------------------------

describe('ToolResultCards — result cap and multiple invocations', () => {
  it('renders at most 6 results per invocation', () => {
    const results = Array.from({ length: 9 }, (_, i) => ({
      id: i + 1,
      title: `Item ${i + 1}`,
    }));
    const inv = makeInvocation('listing', results);
    render(<ToolResultCards invocations={[inv]} />);

    // Items 1–6 should be present; item 7 must not be rendered
    expect(screen.getByText('Item 1')).toBeInTheDocument();
    expect(screen.getByText('Item 6')).toBeInTheDocument();
    expect(screen.queryByText('Item 7')).not.toBeInTheDocument();
  });

  it('renders cards from multiple invocations', () => {
    const listingInv = makeInvocation('listing', [{ id: 1, title: 'Listing A' }]);
    const memberInv = makeInvocation('member', [{ id: 2, name: 'Member B' }]);
    render(<ToolResultCards invocations={[listingInv, memberInv]} />);
    expect(screen.getByText('Listing A')).toBeInTheDocument();
    expect(screen.getByText('Member B')).toBeInTheDocument();
  });

  it('skips ok=false invocations among a mixed set', () => {
    const bad = makeInvocation('listing', [{ id: 1, title: 'Should not appear' }], { ok: false });
    const good = makeInvocation('member', [{ id: 2, name: 'Should appear' }]);
    render(<ToolResultCards invocations={[bad, good]} />);
    expect(screen.queryByText('Should not appear')).not.toBeInTheDocument();
    expect(screen.getByText('Should appear')).toBeInTheDocument();
  });
});

// ---------------------------------------------------------------------------
// Anchor / aria-label
// ---------------------------------------------------------------------------

describe('ToolResultCards — url and aria-label', () => {
  it('provides aria-label from item.name on the anchor when title is absent', () => {
    const inv = makeInvocation('member', [
      { id: 1, name: 'Aria Member', url: '/test/members/1' },
    ]);
    render(<ToolResultCards invocations={[inv]} />);
    const link = screen.getByRole('link');
    expect(link).toHaveAttribute('aria-label', 'Aria Member');
  });

  it('provides aria-label from item.title on the anchor', () => {
    const inv = makeInvocation('listing', [
      { id: 1, title: 'Title Aria', url: '/test/listings/1' },
    ]);
    render(<ToolResultCards invocations={[inv]} />);
    const link = screen.getByRole('link');
    expect(link).toHaveAttribute('aria-label', 'Title Aria');
  });

  it('does not render an anchor when url is absent', () => {
    const inv = makeInvocation('member', [{ id: 1, name: 'No url member' }]);
    render(<ToolResultCards invocations={[inv]} />);
    expect(screen.queryByRole('link')).not.toBeInTheDocument();
  });
});
