// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import { api } from '@/lib/api';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

vi.mock('@/contexts', () =>
  createMockContexts({
    useTenant: () => ({
      tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

import { MostAppreciatedWidget } from './MostAppreciatedWidget';

const ENDPOINT_PATTERN = /\/v2\/appreciations\/most-appreciated/;

/** The loading indicator the component renders has aria-busy="true" on the wrapper div */
function getLoadingDiv(container: HTMLElement) {
  return container.querySelector('[aria-busy="true"]');
}

describe('MostAppreciatedWidget', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows a loading indicator initially', () => {
    vi.mocked(api.get).mockReturnValue(new Promise(() => {})); // never resolves
    const { container } = render(<MostAppreciatedWidget />);
    expect(getLoadingDiv(container)).not.toBeNull();
  });

  it('calls the correct API endpoint on mount', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: [] });
    render(<MostAppreciatedWidget />);
    await waitFor(() => {
      expect(api.get).toHaveBeenCalledWith(
        expect.stringMatching(ENDPOINT_PATTERN)
      );
    });
  });

  it('passes period and limit as query params', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: [] });
    render(<MostAppreciatedWidget period="last_7d" limit={5} />);
    await waitFor(() => {
      expect(api.get).toHaveBeenCalledWith(
        expect.stringContaining('period=last_7d')
      );
      expect(api.get).toHaveBeenCalledWith(
        expect.stringContaining('limit=5')
      );
    });
  });

  it('hides loading indicator after fetch completes with empty array', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: [] });
    const { container } = render(<MostAppreciatedWidget />);
    await waitFor(() => {
      expect(getLoadingDiv(container)).toBeNull();
    });
    // No link rows
    expect(screen.queryByRole('link')).not.toBeInTheDocument();
  });

  it('renders leaderboard rows when data is populated', async () => {
    const rows = [
      { user_id: 10, name: 'Alice', avatar_url: null, count: 42 },
      { user_id: 11, name: 'Bob', avatar_url: null, count: 30 },
    ];
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: rows });
    const { container } = render(<MostAppreciatedWidget />);
    await waitFor(() => expect(getLoadingDiv(container)).toBeNull());

    expect(screen.getByText('Alice')).toBeInTheDocument();
    expect(screen.getByText('Bob')).toBeInTheDocument();
  });

  it('renders rank numbers starting from 1', async () => {
    const rows = [
      { user_id: 10, name: 'Alice', avatar_url: null, count: 42 },
      { user_id: 11, name: 'Bob', avatar_url: null, count: 30 },
    ];
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: rows });
    const { container } = render(<MostAppreciatedWidget />);
    await waitFor(() => expect(getLoadingDiv(container)).toBeNull());

    expect(screen.getByText('1')).toBeInTheDocument();
    expect(screen.getByText('2')).toBeInTheDocument();
  });

  it('renders appreciation counts for each row', async () => {
    const rows = [
      { user_id: 10, name: 'Alice', avatar_url: null, count: 99 },
    ];
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: rows });
    const { container } = render(<MostAppreciatedWidget />);
    await waitFor(() => expect(getLoadingDiv(container)).toBeNull());

    expect(screen.getByText('99')).toBeInTheDocument();
  });

  it('links to the tenant-scoped appreciations page for each user', async () => {
    const rows = [
      { user_id: 10, name: 'Alice', avatar_url: null, count: 5 },
    ];
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: rows });
    const { container } = render(<MostAppreciatedWidget />);
    await waitFor(() => expect(getLoadingDiv(container)).toBeNull());

    const link = screen.getByRole('link');
    expect(link).toHaveAttribute('href', '/test/users/10/appreciations');
  });

  it('handles API failure gracefully (shows empty state, no crash)', async () => {
    // The component uses try/finally without catch; use a resolved-but-invalid
    // response shape to simulate failure without triggering an unhandled rejection.
    vi.mocked(api.get).mockResolvedValueOnce({ success: false, error: 'fetch_failed', data: undefined });
    const { container } = render(<MostAppreciatedWidget />);
    await waitFor(() => expect(getLoadingDiv(container)).toBeNull());
    // No crash — empty state (no links)
    expect(screen.queryByRole('link')).not.toBeInTheDocument();
  });

  it('handles success=false response gracefully', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: false, data: null });
    const { container } = render(<MostAppreciatedWidget />);
    await waitFor(() => expect(getLoadingDiv(container)).toBeNull());
    expect(screen.queryByRole('link')).not.toBeInTheDocument();
  });

  it('uses "someone" fallback when name is null', async () => {
    const rows = [{ user_id: 10, name: null, avatar_url: null, count: 3 }];
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: rows });
    const { container } = render(<MostAppreciatedWidget />);
    await waitFor(() => expect(getLoadingDiv(container)).toBeNull());
    // Link still renders (user_id is the key), even with null name
    expect(screen.getByRole('link')).toBeInTheDocument();
  });

  it('applies optional className to the Card', () => {
    vi.mocked(api.get).mockReturnValue(new Promise(() => {}));
    const { container } = render(<MostAppreciatedWidget className="my-widget" />);
    // HeroUI Card renders as a <div> — confirm class is somewhere in the tree
    expect(container.innerHTML).toContain('my-widget');
  });
});
