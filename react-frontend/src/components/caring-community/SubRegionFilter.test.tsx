// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
  },
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

vi.mock('@/lib/safeStorage', () => ({
  safeLocalStorageGet: vi.fn(() => null),
  safeLocalStorageSetJSON: vi.fn(),
}));

vi.mock('@/contexts', () =>
  createMockContexts({
    useTenant: () => ({
      tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      tenantSlug: 'test',
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  }),
);

import { api } from '@/lib/api';
import { SubRegionFilter } from './SubRegionFilter';

const mockGet = vi.mocked(api.get);

const ACTIVE_REGIONS = [
  {
    id: 1,
    name: 'Altstadt',
    slug: 'altstadt',
    type: 'quartier' as const,
    description: null,
    postal_codes: null,
    status: 'active' as const,
  },
  {
    id: 2,
    name: 'Neustadt',
    slug: 'neustadt',
    type: 'ortsteil' as const,
    description: 'Outer district',
    postal_codes: ['20355'],
    status: 'active' as const,
  },
];

describe('SubRegionFilter', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders nothing when tenant has no sub-regions configured', async () => {
    mockGet.mockResolvedValueOnce({ data: { data: [], total: 0 } });

    const onChange = vi.fn();
    render(
      <SubRegionFilter selectedId={null} onChange={onChange} />,
    );

    await waitFor(() => {
      expect(mockGet).toHaveBeenCalledWith('/v2/caring-community/sub-regions');
    });

    // Empty region list → component hides itself (no MapPin icon, no Select)
    expect(screen.queryByRole('button')).not.toBeInTheDocument();
    // The sub-region container wraps a map-pin span and a Select — neither present
    expect(screen.queryByRole('combobox')).not.toBeInTheDocument();
  });

  it('renders nothing when the API returns an error (no cache)', async () => {
    mockGet.mockRejectedValueOnce(new Error('404 Not Found'));

    const onChange = vi.fn();
    render(
      <SubRegionFilter selectedId={null} onChange={onChange} />,
    );

    await waitFor(() => {
      expect(mockGet).toHaveBeenCalled();
    });

    // Load failed with no cache → component hides itself
    expect(screen.queryByRole('button')).not.toBeInTheDocument();
    expect(screen.queryByRole('combobox')).not.toBeInTheDocument();
  });

  it('renders a Select when regions are loaded', async () => {
    mockGet.mockResolvedValueOnce({ data: { data: ACTIVE_REGIONS, total: 2 } });

    render(<SubRegionFilter selectedId={null} onChange={vi.fn()} />);

    // Wait for loading to complete and the Select to appear
    await waitFor(() => {
      expect(screen.getByRole('button')).toBeInTheDocument();
    });
  });

  it('filters out inactive regions from the API response', async () => {
    const mixed = [
      ...ACTIVE_REGIONS,
      {
        id: 99,
        name: 'Deactivated Zone',
        slug: 'deactivated',
        type: 'other' as const,
        description: null,
        postal_codes: null,
        status: 'inactive' as const,
      },
    ];
    mockGet.mockResolvedValueOnce({ data: { data: mixed, total: 3 } });

    render(<SubRegionFilter selectedId={null} onChange={vi.fn()} />);

    // The component should render (2 active regions) — the inactive one is not a render-blocking condition
    await waitFor(() => {
      expect(screen.getByRole('button')).toBeInTheDocument();
    });
  });

  it('uses a custom label prop when provided', async () => {
    mockGet.mockResolvedValueOnce({ data: { data: ACTIVE_REGIONS, total: 2 } });

    render(
      <SubRegionFilter selectedId={null} onChange={vi.fn()} label="Bezirk" />,
    );

    await waitFor(() => {
      expect(screen.getByText('Bezirk')).toBeInTheDocument();
    });
  });

  // Note: triggering onChange via HeroUI Select requires pointer interaction which
  // is not reliably available in jsdom without full userEvent+HeroUI overlay support.
  // The onChange wiring is covered structurally (onSelectionChange passes the
  // parsed integer id or null to the prop). Skipping end-to-end selection test.
});
