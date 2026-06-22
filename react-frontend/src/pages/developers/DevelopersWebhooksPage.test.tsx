// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

vi.mock('@/contexts', () => createMockContexts());

import DevelopersWebhooksPage from './DevelopersWebhooksPage';

describe('DevelopersWebhooksPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders without crashing', () => {
    render(<DevelopersWebhooksPage />);
    expect(document.body).toBeTruthy();
  });

  it('renders the page heading (webhooks nav key)', () => {
    render(<DevelopersWebhooksPage />);
    // i18n returns key; heading contains "webhooks" in the key
    const headings = screen.getAllByRole('heading');
    expect(headings.length).toBeGreaterThan(0);
  });

  it('renders the Node.js code snippet', () => {
    render(<DevelopersWebhooksPage />);
    expect(screen.getByText(/verifySignature/)).toBeInTheDocument();
  });

  it('renders the PHP tab (PHP code is in the inactive panel — just check the tab button exists)', () => {
    render(<DevelopersWebhooksPage />);
    // The PHP tab button is always rendered even if its panel content is deferred
    const phpTab = screen.getByRole('tab', { name: 'PHP' });
    expect(phpTab).toBeInTheDocument();
  });

  it('renders a Tabs component with Node.js and PHP tabs', () => {
    render(<DevelopersWebhooksPage />);
    expect(screen.getByText('Node.js')).toBeInTheDocument();
    expect(screen.getByText('PHP')).toBeInTheDocument();
  });

  it('renders the X-Partner-Signature header reference', () => {
    render(<DevelopersWebhooksPage />);
    // Appears in intro paragraph, signing-body paragraph, and code — use getAllByText
    expect(screen.getAllByText(/X-Partner-Signature/).length).toBeGreaterThan(0);
  });

  it('renders the HMAC-SHA256 reference', () => {
    render(<DevelopersWebhooksPage />);
    expect(screen.getAllByText(/sha256/i).length).toBeGreaterThan(0);
  });

  it('renders the wallet.credited event reference', () => {
    render(<DevelopersWebhooksPage />);
    expect(screen.getAllByText(/wallet\.credited/).length).toBeGreaterThan(0);
  });

  it('renders the NEXUS_WEBHOOK_SECRET env var reference', () => {
    render(<DevelopersWebhooksPage />);
    expect(screen.getAllByText(/NEXUS_WEBHOOK_SECRET/).length).toBeGreaterThan(0);
  });

  it('renders at least one code pre block (active tab)', () => {
    render(<DevelopersWebhooksPage />);
    // HeroUI Tabs only renders the active panel; Node.js is default-selected
    const preBlocks = document.querySelectorAll('pre');
    expect(preBlocks.length).toBeGreaterThanOrEqual(1);
  });
});
