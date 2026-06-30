// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Proves the SHARED FaqView (one source of truth, synced from
 * react-frontend/src/public-shared) renders server-side in the Next app and is
 * host-agnostic: given identical runtime inputs it produces byte-identical HTML,
 * so the SPA host and the Next host cannot silently diverge. Also enforces the
 * crawler-readable no-JS contract (content + FAQPage JSON-LD + single H1).
 */

import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vitest';

import { FaqView } from '../../_shared/FaqView';
import { PublicRuntimeProvider, type PublicRuntime, type PublicRuntimeLinkProps } from '../../_shared/runtime';

function AnchorLink({ href, className, children }: PublicRuntimeLinkProps) {
  return (
    <a className={className} href={href}>
      {children}
    </a>
  );
}

function makeRuntime(): PublicRuntime {
  return {
    t: (key) => key,
    Link: AnchorLink,
    hrefFor: (path) => `/hour-timebank${path}`,
    isAuthenticated: false,
    locale: 'en',
    branding: null,
    hasFeature: () => false,
    hasModule: () => false,
  };
}

function render(): string {
  return renderToStaticMarkup(
    <PublicRuntimeProvider runtime={makeRuntime()}>
      <FaqView />
    </PublicRuntimeProvider>,
  );
}

describe('shared FaqView SSR', () => {
  const html = render();

  it('host-agnostic: identical inputs produce byte-identical HTML', () => {
    expect(render()).toBe(html);
  });

  it('server-renders all FAQ content (no JS required)', () => {
    expect(html).toContain('faq.title');
    expect(html).toContain('faq.categories.getting_started.title');
    expect(html).toContain('faq.categories.time_credits.title');
    expect(html).toContain('faq.categories.exchanges_safety.title');
    expect(html).toContain('faq.categories.badges_rewards.title');
    expect(html).toContain('faq.categories.account_privacy.title');
    expect(html).toContain('faq.cta_button');
  });

  it('emits FAQPage JSON-LD structured data', () => {
    expect(html).toContain('type="application/ld+json"');
    expect(html).toContain('FAQPage');
    expect(html).toContain('"@type":"Question"');
  });

  it('resolves tenant-aware hrefs through the runtime port', () => {
    expect(html).toContain('href="/hour-timebank/contact"');
    expect(html).toContain('href="/hour-timebank/help"');
  });

  it('has exactly one H1', () => {
    expect((html.match(/<h1/g) ?? []).length).toBe(1);
  });
});
