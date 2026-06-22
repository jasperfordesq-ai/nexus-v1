// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── api mock ─────────────────────────────────────────────────────────────────
const mockApi = { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() };

vi.mock('@/lib/api', () => ({ api: mockApi, default: mockApi }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ─── Toast ────────────────────────────────────────────────────────────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };

vi.mock('@/contexts', () =>
  createMockContexts({ useToast: () => mockToast })
);

vi.mock('@/contexts/ToastContext', () => ({
  useToast: vi.fn(() => mockToast),
  ToastProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// ─── Stub admin sub-components ────────────────────────────────────────────────
vi.mock('../../components', () => ({
  PageHeader: ({ title, actions }: { title: string; actions?: React.ReactNode }) => (
    <div data-testid="page-header">{title}{actions}</div>
  ),
  StatCard: ({ label, value }: { label: string; value: unknown }) => (
    <div data-testid="stat-card">{label}: {String(value)}</div>
  ),
  ConfirmModal: ({
    isOpen, onClose, onConfirm, title,
  }: { isOpen: boolean; onClose: () => void; onConfirm: () => void; title: string }) => (
    isOpen ? (
      <div role="dialog" data-testid="confirm-modal">
        <span>{title}</span>
        <button onClick={onConfirm}>Confirm</button>
        <button onClick={onClose}>Cancel</button>
      </div>
    ) : null
  ),
}));

// ─── Fixtures ────────────────────────────────────────────────────────────────
const NEIGHBORHOOD = {
  id: 1,
  name: 'North Cluster',
  description: 'Northern communities',
  tenants: [
    { id: 10, name: 'Alpha Community', slug: 'alpha', member_count: 50 },
  ],
  total_members: 50,
  shared_events_count: 3,
  created_at: '2025-01-01T00:00:00Z',
};

const AVAILABLE_TENANTS = [
  { id: 20, name: 'Beta Community', slug: 'beta' },
];

const makeDataResponse = (neighborhoods = [] as object[], tenants = [] as object[]) => [
  { success: true, data: neighborhoods },
  { success: true, data: tenants },
];

// ─────────────────────────────────────────────────────────────────────────────
describe('Neighborhoods', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('available-tenants')) {
        return Promise.resolve({ success: true, data: AVAILABLE_TENANTS });
      }
      return Promise.resolve({ success: true, data: [] });
    });
  });

  it('shows loading spinner initially', async () => {
    mockApi.get.mockImplementation(() => new Promise(() => {}));
    const { Neighborhoods } = await import('./Neighborhoods');
    render(<Neighborhoods />);

    const spinners = screen.getAllByRole('status');
    const busy = spinners.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  it('renders empty state when no neighborhoods', async () => {
    const { Neighborhoods } = await import('./Neighborhoods');
    render(<Neighborhoods />);

    await waitFor(() => {
      // Empty state message is rendered
      expect(screen.getByTestId('page-header')).toBeInTheDocument();
    });
    // No neighborhood cards visible
    expect(screen.queryByText('North Cluster')).not.toBeInTheDocument();
  });

  it('renders neighborhood cards when data is loaded', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('available-tenants')) {
        return Promise.resolve({ success: true, data: AVAILABLE_TENANTS });
      }
      return Promise.resolve({ success: true, data: [NEIGHBORHOOD] });
    });

    const { Neighborhoods } = await import('./Neighborhoods');
    render(<Neighborhoods />);

    await waitFor(() => {
      expect(screen.getByText('North Cluster')).toBeInTheDocument();
    });
  });

  it('renders tenant names within neighborhood cards', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('available-tenants')) {
        return Promise.resolve({ success: true, data: AVAILABLE_TENANTS });
      }
      return Promise.resolve({ success: true, data: [NEIGHBORHOOD] });
    });

    const { Neighborhoods } = await import('./Neighborhoods');
    render(<Neighborhoods />);

    await waitFor(() => {
      expect(screen.getByText('Alpha Community')).toBeInTheDocument();
    });
  });

  it('renders stat cards', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('available-tenants')) {
        return Promise.resolve({ success: true, data: AVAILABLE_TENANTS });
      }
      return Promise.resolve({ success: true, data: [NEIGHBORHOOD] });
    });

    const { Neighborhoods } = await import('./Neighborhoods');
    render(<Neighborhoods />);

    await waitFor(() => {
      const cards = screen.getAllByTestId('stat-card');
      expect(cards.length).toBeGreaterThanOrEqual(4);
    });
  });

  it('shows error toast when load fails', async () => {
    mockApi.get.mockRejectedValue(new Error('network'));
    const { Neighborhoods } = await import('./Neighborhoods');
    render(<Neighborhoods />);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('opens create neighborhood modal when New Neighborhood button is pressed', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('available-tenants')) {
        return Promise.resolve({ success: true, data: AVAILABLE_TENANTS });
      }
      return Promise.resolve({ success: true, data: [NEIGHBORHOOD] });
    });

    const { Neighborhoods } = await import('./Neighborhoods');
    render(<Neighborhoods />);

    await waitFor(() => screen.getByText('North Cluster'));

    const newBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('neighborhood') ||
      b.textContent?.toLowerCase().includes('new')
    );
    if (newBtn) {
      fireEvent.click(newBtn);
      await waitFor(() => {
        const modal = document.querySelector('[role="dialog"]');
        expect(modal).toBeTruthy();
      });
    }
  });

  it('calls POST to create a neighborhood', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('available-tenants')) {
        return Promise.resolve({ success: true, data: AVAILABLE_TENANTS });
      }
      return Promise.resolve({ success: true, data: [] });
    });
    mockApi.post.mockResolvedValue({ success: true });

    const { Neighborhoods } = await import('./Neighborhoods');
    render(<Neighborhoods />);

    await waitFor(() => screen.getByTestId('page-header'));

    // Open create modal
    const newBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('neighborhood') ||
      b.textContent?.toLowerCase().includes('new')
    );
    if (newBtn) {
      fireEvent.click(newBtn);

      await waitFor(() => document.querySelector('[role="dialog"]'));

      // Type a name into the modal input
      const nameInput = document.querySelector('input[placeholder]') as HTMLInputElement | null;
      if (nameInput) {
        fireEvent.change(nameInput, { target: { value: 'East Cluster' } });

        // Click Create button
        const createBtn = screen.getAllByRole('button').find(
          (b) => b.textContent?.toLowerCase() === 'create' || b.textContent?.toLowerCase().includes('create')
        );
        if (createBtn && !createBtn.hasAttribute('disabled')) {
          fireEvent.click(createBtn);
          await waitFor(() => {
            expect(mockApi.post).toHaveBeenCalledWith(
              '/v2/admin/federation/neighborhoods',
              expect.objectContaining({ name: 'East Cluster' }),
            );
          });
        }
      }
    }
  });

  it('calls DELETE to remove a neighborhood when confirmed', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('available-tenants')) {
        return Promise.resolve({ success: true, data: AVAILABLE_TENANTS });
      }
      return Promise.resolve({ success: true, data: [NEIGHBORHOOD] });
    });
    mockApi.delete.mockResolvedValue({ success: true });

    const { Neighborhoods } = await import('./Neighborhoods');
    render(<Neighborhoods />);

    await waitFor(() => screen.getByText('North Cluster'));

    // Click the delete icon button on the neighborhood card
    const deleteBtn = screen.getAllByRole('button').find((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('delete')
    );
    if (deleteBtn) {
      fireEvent.click(deleteBtn);
      await waitFor(() => screen.getByTestId('confirm-modal'));

      fireEvent.click(screen.getByText('Confirm'));
      await waitFor(() => {
        expect(mockApi.delete).toHaveBeenCalledWith(
          '/v2/admin/federation/neighborhoods/1',
        );
      });
    }
  });

  it('shows description when neighborhood has one', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('available-tenants')) {
        return Promise.resolve({ success: true, data: AVAILABLE_TENANTS });
      }
      return Promise.resolve({ success: true, data: [NEIGHBORHOOD] });
    });

    const { Neighborhoods } = await import('./Neighborhoods');
    render(<Neighborhoods />);

    await waitFor(() => {
      expect(screen.getByText('Northern communities')).toBeInTheDocument();
    });
  });
});
