// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── API mock ────────────────────────────────────────────────────────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
    download: vi.fn(),
    upload: vi.fn(),
  },
}));

vi.mock('@/lib/api', () => ({ api: mockApi, default: mockApi }));

// ─── Contexts ────────────────────────────────────────────────────────────────
vi.mock('@/contexts', () =>
  createMockContexts({
    useTenant: () => ({
      tenant: { id: 2, name: 'hOUR Timebank', slug: 'hour-timebank' },
      tenantPath: (p: string) => `/hour-timebank${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

// ─── Hooks ────────────────────────────────────────────────────────────────────
vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// ─── Stub admin layout components ────────────────────────────────────────────
vi.mock('../../components/PageHeader', () => ({
  PageHeader: ({ title, description }: { title?: string; description?: string }) => (
    <div data-testid="page-header">
      {title && <h1>{title}</h1>}
      {description && <p>{description}</p>}
    </div>
  ),
}));

// ─────────────────────────────────────────────────────────────────────────────
describe('GroupGeocode', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders the page header with the geocode title', async () => {
    const { GroupGeocode } = await import('./GroupGeocode');
    render(<GroupGeocode />);
    expect(screen.getByTestId('page-header')).toBeInTheDocument();
    expect(screen.getByText('Geocode')).toBeInTheDocument();
  });

  it('renders the geocode description in the page header', async () => {
    const { GroupGeocode } = await import('./GroupGeocode');
    render(<GroupGeocode />);
    expect(
      screen.getByText(/geocode group locations to enable map display/i)
    ).toBeInTheDocument();
  });

  it('renders the "not migrated" heading', async () => {
    const { GroupGeocode } = await import('./GroupGeocode');
    render(<GroupGeocode />);
    expect(screen.getByText('Geocode Not Migrated')).toBeInTheDocument();
  });

  it('renders the explanation text about the artisan command', async () => {
    const { GroupGeocode } = await import('./GroupGeocode');
    render(<GroupGeocode />);
    expect(
      screen.getByText(/batch geocoding admin screen has not yet been rebuilt in React/i)
    ).toBeInTheDocument();
  });

  it('renders the artisan command in a code element', async () => {
    const { GroupGeocode } = await import('./GroupGeocode');
    render(<GroupGeocode />);
    expect(screen.getByText('php artisan groups:geocode')).toBeInTheDocument();
  });

  it('does not render any interactive buttons (read-only info page)', async () => {
    const { GroupGeocode } = await import('./GroupGeocode');
    render(<GroupGeocode />);
    // This is a stub page — no form buttons
    const buttons = screen.queryAllByRole('button');
    expect(buttons).toHaveLength(0);
  });

  it('does not render a geocode form or input fields', async () => {
    const { GroupGeocode } = await import('./GroupGeocode');
    render(<GroupGeocode />);
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument();
  });

  it('renders the warning card (border-warning styling)', async () => {
    const { GroupGeocode } = await import('./GroupGeocode');
    const { container } = render(<GroupGeocode />);
    // The card has border-warning/30 styling applied
    const card = container.querySelector('.border-warning\\/30');
    expect(card).toBeInTheDocument();
  });

  it('does not render any navigation links', async () => {
    const { GroupGeocode } = await import('./GroupGeocode');
    render(<GroupGeocode />);
    // Info-only stub: no anchor tags leading away
    expect(screen.queryByRole('link')).not.toBeInTheDocument();
  });

  it('renders a single heading for the not-migrated notice', async () => {
    const { GroupGeocode } = await import('./GroupGeocode');
    render(<GroupGeocode />);
    const headings = screen.getAllByRole('heading');
    // PageHeader h1 + card h3
    const cardHeading = headings.find((h) =>
      h.textContent?.includes('Geocode Not Migrated')
    );
    expect(cardHeading).toBeInTheDocument();
  });
});
