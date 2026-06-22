// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

// ── stable mock refs ──────────────────────────────────────────────────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };

vi.mock('@/contexts', () =>
  createMockContexts({ useToast: () => mockToast })
);

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

vi.mock('@/hooks', () => ({
  usePageTitle: vi.fn(),
}));

vi.mock('../../components', () => ({
  PageHeader: ({ title, actions }: { title: string; actions?: React.ReactNode }) => (
    <div data-testid="page-header">
      <h1>{title}</h1>
      {actions}
    </div>
  ),
}));

// PartnerTimebankGuidance is a complex sub-component; stub it
vi.mock('./PartnerTimebankGuidance', () => ({
  PartnerTimebankGuidance: () => <div data-testid="partner-guidance" />,
}));

import { api } from '@/lib/api';
import { CreditCommonsConfig } from './CreditCommonsConfig';

const mockConfig = {
  node_slug: 'test-node',
  display_name: 'Test Node',
  currency_format: '<quantity> hours',
  exchange_rate: 1.0,
  validated_window: 300,
  parent_node_url: 'https://parent.example.com',
  parent_node_slug: 'parent',
  last_hash: 'abc123def456',
  absolute_path: ['root', 'region', 'test-node'],
  stats: {
    trades: 10,
    traders: 5,
    volume: 20.5,
    accounts: 8,
    entries: 30,
  },
};

describe('CreditCommonsConfig', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows loading spinner while fetching config', () => {
    vi.mocked(api.get).mockReturnValue(new Promise(() => {}));
    render(<CreditCommonsConfig />);

    const statusEls = screen.queryAllByRole('status');
    const busy = statusEls.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  it('renders form fields after successful load', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: mockConfig });

    render(<CreditCommonsConfig />);

    await waitFor(() => {
      expect(screen.getByDisplayValue('test-node')).toBeInTheDocument();
    });

    expect(screen.getByDisplayValue('Test Node')).toBeInTheDocument();
    expect(screen.getByDisplayValue('https://parent.example.com')).toBeInTheDocument();
    expect(screen.getByDisplayValue('parent')).toBeInTheDocument();
    expect(screen.getByDisplayValue('1')).toBeInTheDocument(); // exchange_rate
  });

  it('shows error toast when load fails', async () => {
    vi.mocked(api.get).mockRejectedValue(new Error('Network error'));

    render(<CreditCommonsConfig />);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('renders hashchain last_hash value', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: mockConfig });

    render(<CreditCommonsConfig />);

    await waitFor(() => {
      expect(screen.getByText('abc123def456')).toBeInTheDocument();
    });
  });

  it('renders stats section with correct values', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: mockConfig });

    render(<CreditCommonsConfig />);

    await waitFor(() => {
      expect(screen.getByText('10')).toBeInTheDocument(); // trades
      expect(screen.getByText('5')).toBeInTheDocument();  // traders
      expect(screen.getByText('20.50h')).toBeInTheDocument(); // volume formatted
      expect(screen.getByText('8')).toBeInTheDocument();  // accounts
    });
  });

  it('renders absolute_path chips', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: mockConfig });

    render(<CreditCommonsConfig />);

    await waitFor(() => {
      expect(screen.getByText('root')).toBeInTheDocument();
      expect(screen.getByText('region')).toBeInTheDocument();
      expect(screen.getAllByText('test-node').length).toBeGreaterThan(0);
    });
  });

  it('saves config and shows success toast on valid save', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: mockConfig });
    vi.mocked(api.put).mockResolvedValue({ success: true });

    render(<CreditCommonsConfig />);

    await waitFor(() => {
      expect(screen.getByDisplayValue('test-node')).toBeInTheDocument();
    });

    const user = userEvent.setup();
    const saveBtn = screen.getByRole('button', { name: /save/i });
    await user.click(saveBtn);

    await waitFor(() => {
      expect(api.put).toHaveBeenCalledWith(
        '/v2/admin/federation/cc-config',
        expect.objectContaining({ node_slug: 'test-node' })
      );
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('shows error toast when node_slug is empty on save', async () => {
    const configWithEmptySlug = { ...mockConfig, node_slug: '' };
    vi.mocked(api.get).mockResolvedValue({ success: true, data: configWithEmptySlug });

    render(<CreditCommonsConfig />);

    await waitFor(() => {
      expect(screen.getByDisplayValue('<quantity> hours')).toBeInTheDocument();
    });

    const user = userEvent.setup();
    const saveBtn = screen.getByRole('button', { name: /save/i });
    await user.click(saveBtn);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
      // PUT should not be called if slug is empty
      expect(api.put).not.toHaveBeenCalled();
    });
  });

  it('shows error toast for invalid node_slug format', async () => {
    const configBadSlug = { ...mockConfig, node_slug: 'ab' }; // too short
    vi.mocked(api.get).mockResolvedValue({ success: true, data: configBadSlug });

    render(<CreditCommonsConfig />);

    await waitFor(() => {
      expect(screen.getByDisplayValue('ab')).toBeInTheDocument();
    });

    const user = userEvent.setup();
    const saveBtn = screen.getByRole('button', { name: /save/i });
    await user.click(saveBtn);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
      expect(api.put).not.toHaveBeenCalled();
    });
  });

  it('shows error toast when save API returns failure', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: mockConfig });
    vi.mocked(api.put).mockResolvedValue({ success: false, error: 'Server error' });

    render(<CreditCommonsConfig />);

    await waitFor(() => {
      expect(screen.getByDisplayValue('test-node')).toBeInTheDocument();
    });

    const user = userEvent.setup();
    const saveBtn = screen.getByRole('button', { name: /save/i });
    await user.click(saveBtn);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('re-fetches config on Refresh button click', async () => {
    vi.mocked(api.get)
      .mockResolvedValueOnce({ success: true, data: mockConfig })
      .mockResolvedValueOnce({ success: true, data: mockConfig });

    render(<CreditCommonsConfig />);

    await waitFor(() => {
      expect(screen.getByDisplayValue('test-node')).toBeInTheDocument();
    });

    const user = userEvent.setup();
    const refreshBtn = screen.getByRole('button', { name: /refresh/i });
    await user.click(refreshBtn);

    await waitFor(() => {
      expect(vi.mocked(api.get).mock.calls.length).toBeGreaterThanOrEqual(2);
    });
  });

  it('shows cc_no_hash text when last_hash is null', async () => {
    const configNoHash = { ...mockConfig, last_hash: null };
    vi.mocked(api.get).mockResolvedValue({ success: true, data: configNoHash });

    render(<CreditCommonsConfig />);

    await waitFor(() => {
      // t('federation.cc_no_hash') = "No hashchain started yet"
      expect(screen.getByText('No hashchain started yet')).toBeInTheDocument();
    });
  });

  it('renders PartnerTimebankGuidance component', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: mockConfig });

    render(<CreditCommonsConfig />);

    await waitFor(() => {
      expect(screen.getByTestId('partner-guidance')).toBeInTheDocument();
    });
  });
});
