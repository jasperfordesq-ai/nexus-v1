// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for DevelopersEndpointsPage — static table of partner API endpoints.
 * No network calls; the page is purely data-driven from a local ENDPOINTS array.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

vi.mock('@/contexts', () => createMockContexts());

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// usePageTitle uses the hooks barrel — mock it so jsdom doesn't throw.
vi.mock('@/hooks', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/hooks')>();
  return { ...actual, usePageTitle: vi.fn() };
});

import DevelopersEndpointsPage from './DevelopersEndpointsPage';

describe('DevelopersEndpointsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders without crashing', () => {
    render(<DevelopersEndpointsPage />);
    // Page root should be in the document
    expect(document.body).toBeTruthy();
  });

  it('renders the page heading for endpoints', () => {
    render(<DevelopersEndpointsPage />);
    // The h1 contains the nav.endpoints translation key value
    const heading = screen.getByRole('heading', { level: 1 });
    expect(heading).toBeInTheDocument();
  });

  it('renders all 10 endpoint rows in the table', () => {
    render(<DevelopersEndpointsPage />);
    // All GET and POST chip labels are rendered; there are 7 GET and 3 POST
    // (10 total chips for methods).
    const getChips = screen.getAllByText('GET');
    const postChips = screen.getAllByText('POST');
    expect(getChips.length + postChips.length).toBe(10);
  });

  it('renders endpoint paths in mono text', () => {
    render(<DevelopersEndpointsPage />);
    expect(screen.getByText('/api/partner/v1/oauth/token')).toBeInTheDocument();
    expect(screen.getByText('/api/partner/v1/users')).toBeInTheDocument();
    expect(screen.getByText('/api/partner/v1/wallet/credit')).toBeInTheDocument();
  });

  it('renders scope chips for scoped endpoints', () => {
    render(<DevelopersEndpointsPage />);
    // Some scopes appear multiple times (e.g. webhooks.manage on two rows).
    // Use getAllByText and assert at least one match for each expected scope.
    expect(screen.getAllByText('wallet.write').length).toBeGreaterThanOrEqual(1);
    expect(screen.getAllByText('webhooks.manage').length).toBeGreaterThanOrEqual(1);
    expect(screen.getAllByText('users.read').length).toBeGreaterThanOrEqual(1);
    expect(screen.getAllByText('aggregates.read').length).toBeGreaterThanOrEqual(1);
  });

  it('renders table column header labels', () => {
    render(<DevelopersEndpointsPage />);
    // Column headers come from i18n keys; in English fallback they are the key paths.
    // We just confirm 4 column headers exist.
    const columnHeaders = screen.getAllByRole('columnheader');
    expect(columnHeaders).toHaveLength(4);
  });
});
