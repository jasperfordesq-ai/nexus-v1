// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

// ── Mocks ──────────────────────────────────────────────────────────────────────
vi.mock('../../api/adminApi', () => ({
  adminConfig: {
    get: vi.fn(),
    updateModule: vi.fn(),
    updateFeature: vi.fn(),
  },
}));

const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn(), showToast: vi.fn() };
const mockRefreshTenant = vi.fn();

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
    useTenant: () => ({
      tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
      refreshTenant: mockRefreshTenant,
    }),
  })
);

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// Heavy sub-components — stub them so the tree stays fast
vi.mock('./ModuleCard', () => ({
  default: ({ module, enabled, onToggle }: {
    module: { id: string; name: string };
    enabled: boolean;
    onToggle: (id: string, val: boolean) => void;
  }) => (
    <div data-testid={`module-card-${module.id}`}>
      <span>{module.name}</span>
      <button onClick={() => onToggle(module.id, !enabled)}>
        {enabled ? 'Disable' : 'Enable'}
      </button>
    </div>
  ),
}));

vi.mock('./ModuleConfigModal', () => ({
  default: () => null,
}));

vi.mock('./PlatformInfrastructure', () => ({
  default: () => <div data-testid="platform-infrastructure" />,
}));

// Stub moduleRegistry to return deterministic data
vi.mock('./moduleRegistry', () => ({
  getCoreModules: () => [
    { id: 'listings', name: 'Listings', description: 'Browse listings', configOptions: [] },
    { id: 'wallet', name: 'Wallet', description: 'Time credits', configOptions: [] },
  ],
  getFeatureModules: () => [
    { id: 'events', name: 'Events', description: 'Community events', configOptions: [] },
    { id: 'gamification', name: 'Gamification', description: 'Badges & XP', configOptions: [] },
    { id: 'two_factor_authentication', name: 'two_factor_authentication', description: 'two_factor_authentication', configOptions: [] },
    { id: 'biometric_login', name: 'biometric_login', description: 'biometric_login', configOptions: [] },
  ],
}));

import { adminConfig } from '../../api/adminApi';
import ModuleConfiguration from './ModuleConfiguration';
import type { TenantConfig } from '../../api/types';

const MOCK_CONFIG: TenantConfig = {
  tenant_id: 2,
  modules: { listings: true, wallet: false },
  features: {
    events: true,
    gamification: false,
    two_factor_authentication: true,
    biometric_login: true,
  },
};

describe('ModuleConfiguration', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders a loading spinner while config loads', () => {
    vi.mocked(adminConfig.get).mockReturnValue(new Promise(() => {}));
    render(<ModuleConfiguration />);
    const spinners = screen.getAllByRole('status');
    const busy = spinners.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeInTheDocument();
  });

  it('renders module cards after load', async () => {
    vi.mocked(adminConfig.get).mockResolvedValue({ success: true, data: MOCK_CONFIG });
    render(<ModuleConfiguration />);
    await waitFor(() => {
      expect(screen.getByText('Listings')).toBeInTheDocument();
    });
    expect(screen.getByText('Wallet')).toBeInTheDocument();
    expect(screen.getByText('Events')).toBeInTheDocument();
    expect(screen.getByText('Gamification')).toBeInTheDocument();
  });

  it('shows error toast when config load fails', async () => {
    vi.mocked(adminConfig.get).mockRejectedValue(new Error('Server error'));
    render(<ModuleConfiguration />);
    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('filters modules by search query', async () => {
    vi.mocked(adminConfig.get).mockResolvedValue({ success: true, data: MOCK_CONFIG });
    render(<ModuleConfiguration />);
    await waitFor(() => {
      expect(screen.getByText('Listings')).toBeInTheDocument();
    });

    const searchInput = screen.getByPlaceholderText(/search modules/i);
    await userEvent.type(searchInput, 'Wallet');

    // Only Wallet should appear; Listings should be gone
    expect(screen.queryByText('Listings')).not.toBeInTheDocument();
    expect(screen.getByText('Wallet')).toBeInTheDocument();
  });

  it('shows no-results message when search matches nothing', async () => {
    vi.mocked(adminConfig.get).mockResolvedValue({ success: true, data: MOCK_CONFIG });
    render(<ModuleConfiguration />);
    await waitFor(() => {
      expect(screen.getByText('Listings')).toBeInTheDocument();
    });

    const searchInput = screen.getByPlaceholderText(/search modules/i);
    await userEvent.type(searchInput, 'xyznonexistent');

    await waitFor(() => {
      expect(screen.getByText(/no modules match/i)).toBeInTheDocument();
    });
  });

  it('filters to core-only when Core filter button is clicked', async () => {
    vi.mocked(adminConfig.get).mockResolvedValue({ success: true, data: MOCK_CONFIG });
    render(<ModuleConfiguration />);
    await waitFor(() => {
      expect(screen.getByText('Events')).toBeInTheDocument();
    });

    const coreBtn = screen.getByRole('button', { name: /core/i });
    await userEvent.click(coreBtn);

    expect(screen.queryByText('Events')).not.toBeInTheDocument();
    expect(screen.queryByText('Gamification')).not.toBeInTheDocument();
    expect(screen.getByText('Listings')).toBeInTheDocument();
  });

  it('calls updateModule and refreshTenant when a core module is toggled', async () => {
    vi.mocked(adminConfig.get).mockResolvedValue({ success: true, data: MOCK_CONFIG });
    vi.mocked(adminConfig.updateModule).mockResolvedValue({ success: true });
    render(<ModuleConfiguration />);
    await waitFor(() => {
      expect(screen.getByText('Listings')).toBeInTheDocument();
    });

    // ModuleCard stub renders Disable (since listings=true in MOCK_CONFIG)
    const disableBtn = screen.getAllByRole('button', { name: /Disable/i })[0];
    await userEvent.click(disableBtn);

    await waitFor(() => {
      expect(adminConfig.updateModule).toHaveBeenCalledWith('listings', false);
      expect(mockRefreshTenant).toHaveBeenCalled();
    });
  });

  it('updates the two-factor enrollment feature from its module card', async () => {
    vi.mocked(adminConfig.get).mockResolvedValue({ success: true, data: MOCK_CONFIG });
    vi.mocked(adminConfig.updateFeature).mockResolvedValue({ success: true });
    render(<ModuleConfiguration />);

    const card = await screen.findByTestId('module-card-two_factor_authentication');
    const toggle = card.querySelector('button');
    expect(toggle).not.toBeNull();
    if (toggle) await userEvent.click(toggle);

    await waitFor(() => {
      expect(adminConfig.updateFeature).toHaveBeenCalledWith('two_factor_authentication', false);
      expect(mockRefreshTenant).toHaveBeenCalled();
    });
  });
});
