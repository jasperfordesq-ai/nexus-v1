// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { HelmetProvider } from 'react-helmet-async';
import { createMockContexts } from '@/test/mock-contexts';

const mockNavigate = vi.hoisted(() => vi.fn());
const mockHasFeature = vi.hoisted(() => vi.fn(() => true));

vi.mock('react-router-dom', async (importOriginal) => {
  const actual = await importOriginal<typeof import('react-router-dom')>();
  return { ...actual, useNavigate: () => mockNavigate };
});

vi.mock('@/contexts', () =>
  createMockContexts({
    useTenant: () => ({
      tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: mockHasFeature,
      hasModule: vi.fn(() => true),
    }),
  }),
);

import { BrokerCommandPalette } from './BrokerCommandPalette';

function renderPalette(isOpen = true, onClose = vi.fn()) {
  render(
    <HelmetProvider>
      <MemoryRouter>
        <BrokerCommandPalette isOpen={isOpen} onClose={onClose} />
      </MemoryRouter>
    </HelmetProvider>
  );
  return onClose;
}

describe('BrokerCommandPalette', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockHasFeature.mockReturnValue(true);
  });

  it('lists all broker destinations when open with no query', async () => {
    renderPalette();
    await waitFor(() => {
      expect(screen.getByRole('option', { name: /dashboard/i })).toBeInTheDocument();
    });
    expect(screen.getByRole('option', { name: /match approvals/i })).toBeInTheDocument();
    expect(screen.getByRole('option', { name: /vetting/i })).toBeInTheDocument();
  });

  it('filters destinations as the user types', async () => {
    const user = userEvent.setup();
    renderPalette();
    const input = await screen.findByRole('combobox');

    await user.type(input, 'vett');

    await waitFor(() => {
      expect(screen.getByRole('option', { name: /vetting/i })).toBeInTheDocument();
      expect(screen.queryByRole('option', { name: /dashboard/i })).not.toBeInTheDocument();
    });
  });

  it('navigates to the active destination on Enter and closes', async () => {
    const user = userEvent.setup();
    const onClose = renderPalette();
    const input = await screen.findByRole('combobox');

    await user.type(input, 'members');
    await user.keyboard('{Enter}');

    await waitFor(() => {
      expect(mockNavigate).toHaveBeenCalledWith('/test/broker/members');
      expect(onClose).toHaveBeenCalled();
    });
  });

  it('hides exchange-gated destinations when the feature is off', async () => {
    mockHasFeature.mockImplementation((f: string) => f !== 'exchange_workflow');
    renderPalette();
    await waitFor(() => {
      expect(screen.getByRole('option', { name: /dashboard/i })).toBeInTheDocument();
    });
    expect(screen.queryByRole('option', { name: /match approvals/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('option', { name: /exchanges/i })).not.toBeInTheDocument();
  });

  it('shows a no-results message for a nonsense query', async () => {
    const user = userEvent.setup();
    renderPalette();
    const input = await screen.findByRole('combobox');

    await user.type(input, 'zzzzzz');

    await waitFor(() => {
      expect(screen.getByText(/nothing matches/i)).toBeInTheDocument();
    });
  });
});
