// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

// ── Stable mock refs ──────────────────────────────────────────────────────────
const mockShowToast = vi.fn();

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => ({
      showToast: mockShowToast,
      success: vi.fn(),
      error: vi.fn(),
      info: vi.fn(),
      warning: vi.fn(),
    }),
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  }),
);

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

import { api } from '@/lib/api';
import IsolatedNodeAdminPage from './IsolatedNodeAdminPage';

// ── Test data ─────────────────────────────────────────────────────────────────

const makeGate = (overrides: Partial<{
  closed: boolean;
  decided_count: number;
  total_count: number;
  blockers: string[];
  status_counts: Record<string, number>;
}> = {}) => ({
  closed: false,
  decided_count: 2,
  total_count: 5,
  blockers: ['deployment_mode'],
  status_counts: { pending: 2, in_progress: 1, decided: 2, blocked: 0 },
  ...overrides,
});

const makeItem = (overrides: Partial<{
  key: string;
  label_code: string;
  type: 'text' | 'enum' | 'url' | 'choice';
  value: string | null;
  status: 'pending' | 'in_progress' | 'decided' | 'blocked';
  choices: string[] | null;
  help_code: string;
  owner: string | null;
  notes: string | null;
  updated_at: string | null;
}> = {}) => ({
  key: 'deployment_mode',
  label_code: 'deployment_mode',
  type: 'enum' as const,
  choices: ['hosted_tenant', 'hosted_custom_domain', 'canton_isolated_node'],
  help_code: 'deployment_mode',
  value: null,
  owner: null,
  status: 'pending' as const,
  notes: null,
  updated_at: null,
  ...overrides,
});

const MOCK_GATE_RESPONSE = {
  items: [
    makeItem({ key: 'deployment_mode', value: null, status: 'pending' }),
    makeItem({
      key: 'incident_runbook_url',
      label_code: 'incident_runbook_url',
      help_code: 'incident_runbook_url',
      type: 'url',
      choices: null,
      value: 'https://example.com',
      status: 'decided',
    }),
  ],
  gate: makeGate({ closed: false, decided_count: 1, total_count: 2 }),
  last_updated_at: '2026-06-22T09:00:00Z',
};

const CLOSED_GATE_RESPONSE = {
  items: [
    makeItem({ key: 'deployment_mode', value: 'hosted_tenant', status: 'decided' }),
  ],
  gate: makeGate({ closed: true, decided_count: 1, total_count: 1, blockers: [] }),
  last_updated_at: '2026-06-22T10:00:00Z',
};

describe('IsolatedNodeAdminPage — loading state', () => {
  beforeEach(() => vi.clearAllMocks());

  it('shows loading spinner while fetching', () => {
    vi.mocked(api.get).mockReturnValue(new Promise(() => {}));
    render(<IsolatedNodeAdminPage />);
    const spinners = screen.getAllByRole('status');
    const busy = spinners.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });
});

describe('IsolatedNodeAdminPage — populated state', () => {
  beforeEach(() => vi.clearAllMocks());

  it('renders decision item labels after load', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: MOCK_GATE_RESPONSE });
    render(<IsolatedNodeAdminPage />);

    await waitFor(() => {
      expect(screen.getByText('Deployment mode')).toBeInTheDocument();
      expect(screen.getByText('Incident runbook URL')).toBeInTheDocument();
    });
  });

  it('renders URL value as a link', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: MOCK_GATE_RESPONSE });
    render(<IsolatedNodeAdminPage />);

    await waitFor(() => {
      const link = screen.getByRole('link', { name: /example\.com/i });
      expect(link).toHaveAttribute('href', 'https://example.com');
    });
  });

  it('renders progress bar when data loads', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: MOCK_GATE_RESPONSE });
    render(<IsolatedNodeAdminPage />);

    await waitFor(() => {
      expect(screen.getByRole('progressbar')).toBeInTheDocument();
    });
  });

  it('hides loading spinner after data loads', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: MOCK_GATE_RESPONSE });
    render(<IsolatedNodeAdminPage />);

    await waitFor(() => {
      const spinners = screen.queryAllByRole('status');
      const busy = spinners.find((el) => el.getAttribute('aria-busy') === 'true');
      expect(busy).toBeUndefined();
    });
  });

  it('shows closed gate indicator when all items decided', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: CLOSED_GATE_RESPONSE });
    render(<IsolatedNodeAdminPage />);

    // The stable deployment choice is translated for display.
    await waitFor(() => {
      expect(screen.getByText('Hosted tenant')).toBeInTheDocument();
    });
    // gate.closed = true → progress should be at 100%
    const progressbar = screen.getByRole('progressbar');
    expect(progressbar).toBeInTheDocument();
  });
});

describe('IsolatedNodeAdminPage — edit modal', () => {
  beforeEach(() => vi.clearAllMocks());

  it('opens edit modal when Edit button is clicked', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: MOCK_GATE_RESPONSE });
    render(<IsolatedNodeAdminPage />);

    await waitFor(() => screen.getByText('Deployment mode'));

    const editBtns = screen.getAllByRole('button');
    const editBtn = editBtns.find(
      (b) =>
        b.textContent?.toLowerCase().includes('edit') ||
        b.textContent?.toLowerCase().includes('isolated_node.actions.edit'),
    );
    expect(editBtn).toBeDefined();
    fireEvent.click(editBtn!);

    // Modal header includes the item label
    await waitFor(() => {
      expect(screen.getAllByText('Deployment mode').length).toBeGreaterThan(1);
    });
  });

  it('calls PUT when save is clicked in modal', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: MOCK_GATE_RESPONSE });
    const updatedItem = makeItem({ key: 'deployment_mode', value: 'hosted_tenant', status: 'decided' });
    vi.mocked(api.put).mockResolvedValueOnce({
      success: true,
      data: { item: updatedItem, gate: makeGate() },
    });

    render(<IsolatedNodeAdminPage />);
    await waitFor(() => screen.getByText('Deployment mode'));

    // Open modal for first item
    const editBtns = screen.getAllByRole('button');
    const editBtn = editBtns.find(
      (b) =>
        b.textContent?.toLowerCase().includes('edit') ||
        b.textContent?.toLowerCase().includes('isolated_node.actions.edit'),
    );
    fireEvent.click(editBtn!);

    // Wait for modal to open
    await waitFor(() => {
      expect(screen.getAllByText('Deployment mode').length).toBeGreaterThan(1);
    });

    // Click save in the modal
    const saveBtn = screen.getAllByRole('button').find(
      (b) =>
        b.textContent?.toLowerCase().includes('save') ||
        b.textContent?.toLowerCase().includes('isolated_node.actions.save'),
    );
    expect(saveBtn).toBeDefined();
    fireEvent.click(saveBtn!);

    await waitFor(() => {
      expect(api.put).toHaveBeenCalledWith(
        expect.stringContaining('/isolated-node/items/'),
        expect.any(Object),
      );
    });
  });

  it('shows success toast after save', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: MOCK_GATE_RESPONSE });
    const updatedItem = makeItem({ key: 'deployment_mode', value: 'hosted_custom_domain', status: 'decided' });
    vi.mocked(api.put).mockResolvedValueOnce({
      success: true,
      data: { item: updatedItem, gate: makeGate() },
    });

    render(<IsolatedNodeAdminPage />);
    await waitFor(() => screen.getByText('Deployment mode'));

    const editBtns = screen.getAllByRole('button');
    const editBtn = editBtns.find(
      (b) =>
        b.textContent?.toLowerCase().includes('edit') ||
        b.textContent?.toLowerCase().includes('isolated_node.actions.edit'),
    );
    fireEvent.click(editBtn!);

    await waitFor(() => {
      expect(screen.getAllByText('Deployment mode').length).toBeGreaterThan(1);
    });

    const saveBtn = screen.getAllByRole('button').find(
      (b) =>
        b.textContent?.toLowerCase().includes('save') ||
        b.textContent?.toLowerCase().includes('isolated_node.actions.save'),
    );
    fireEvent.click(saveBtn!);

    await waitFor(() => {
      expect(mockShowToast).toHaveBeenCalledWith(expect.any(String), 'success');
    });
  });
});

describe('IsolatedNodeAdminPage — error state', () => {
  beforeEach(() => vi.clearAllMocks());

  it('shows error toast when load fails', async () => {
    vi.mocked(api.get).mockRejectedValueOnce(new Error('network'));
    render(<IsolatedNodeAdminPage />);
    await waitFor(() => {
      expect(mockShowToast).toHaveBeenCalledWith(expect.any(String), 'error');
    });
  });
});
