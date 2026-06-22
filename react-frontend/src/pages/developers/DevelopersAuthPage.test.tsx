// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for DevelopersAuthPage — OAuth2 documentation with code snippet tabs.
 * No network calls; content is static i18n + constant snippets.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

vi.mock('@/contexts', () => createMockContexts());

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

vi.mock('@/hooks', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/hooks')>();
  return { ...actual, usePageTitle: vi.fn() };
});

import DevelopersAuthPage from './DevelopersAuthPage';

describe('DevelopersAuthPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders without crashing', () => {
    render(<DevelopersAuthPage />);
    expect(document.body).toBeTruthy();
  });

  it('renders the page h1 for auth', () => {
    render(<DevelopersAuthPage />);
    const heading = screen.getByRole('heading', { level: 1 });
    expect(heading).toBeInTheDocument();
  });

  it('renders all 3 step cards as h3 headings', () => {
    render(<DevelopersAuthPage />);
    const h3s = screen.getAllByRole('heading', { level: 3 });
    expect(h3s).toHaveLength(3);
  });

  it('renders the tabs for code examples', () => {
    render(<DevelopersAuthPage />);
    // Tabs component renders tab elements; we look for the two tab triggers.
    const tabs = screen.getAllByRole('tab');
    expect(tabs.length).toBeGreaterThanOrEqual(2);
  });

  it('renders the curl snippet content in a code block', () => {
    render(<DevelopersAuthPage />);
    // The curl snippet contains a known unique string.
    expect(screen.getByText(/client_credentials/)).toBeInTheDocument();
  });

  it('renders the API endpoint URL in the snippet', () => {
    render(<DevelopersAuthPage />);
    expect(screen.getByText(/api\.project-nexus\.ie\/api\/partner\/v1\/oauth\/token/)).toBeInTheDocument();
  });
});
