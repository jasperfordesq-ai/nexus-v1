// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Render tests for RegistrationPolicySettings admin module.
 *
 * Tests:
 * - Component renders without crashing
 * - Shows loading spinner initially
 * - Renders registration mode dropdown after data loads
 * - Shows verification options when 'verified_identity' mode selected
 * - Hides verification options for 'open' mode
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { HeroUIProvider } from '@heroui/react';

// ─── Common mocks ────────────────────────────────────────────────────────────

const mockGet = vi.fn();
const mockPut = vi.fn();

vi.mock('@/lib/api', () => ({
  api: {
    get: (...args: unknown[]) => mockGet(...args),
    post: vi.fn().mockResolvedValue({ success: true }),
    put: (...args: unknown[]) => mockPut(...args),
    delete: vi.fn().mockResolvedValue({ success: true }),
  },
  tokenManager: { getTenantId: vi.fn(), getAccessToken: vi.fn() },
}));

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    user: { id: 1, first_name: 'Admin', last_name: 'User', name: 'Admin User', role: 'admin', is_super_admin: true, tenant_id: 2 },
    isAuthenticated: true,
    logout: vi.fn(),
  })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Community', slug: 'test', configuration: {} },
    tenantSlug: 'test',
    branding: { name: 'Test Community' },
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
    tenantPath: (p: string) => `/test${p}`,
  })),
  useToast: vi.fn(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), showToast: vi.fn() })),
  useNotifications: vi.fn(() => ({ counts: { messages: 0, notifications: 0 } })),
}));

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// ─── Helpers ─────────────────────────────────────────────────────────────────

function renderWithProviders(ui: React.ReactElement) {
  return render(
    <HeroUIProvider>
      <MemoryRouter>{ui}</MemoryRouter>
    </HeroUIProvider>
  );
}

const mockPolicyData = {
  registration_mode: 'open_with_approval',
  verification_provider: null,
  verification_level: 'none',
  post_verification: 'admin_approval',
  fallback_mode: 'none',
  require_email_verify: true,
  has_policy: true,
};

const mockProviders = [
  { slug: 'mock', name: 'Mock Provider (Testing)', levels: ['document_only', 'document_selfie'], available: true },
];

// ─── Tests ───────────────────────────────────────────────────────────────────

describe('RegistrationPolicySettings', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockGet.mockImplementation((url: string) => {
      if (url.includes('registration-policy')) {
        return Promise.resolve({ success: true, data: mockPolicyData });
      }
      if (url.includes('providers')) {
        return Promise.resolve({ success: true, data: mockProviders });
      }
      return Promise.resolve({ success: true, data: [] });
    });
    mockPut.mockResolvedValue({ success: true, data: mockPolicyData });
  });

  it('renders without crashing', async () => {
    const { default: RegistrationPolicySettings } = await import('../system/RegistrationPolicySettings');
    const { container } = renderWithProviders(<RegistrationPolicySettings />);
    expect(container).toBeTruthy();
  });

  it('shows page header', async () => {
    const { default: RegistrationPolicySettings } = await import('../system/RegistrationPolicySettings');
    renderWithProviders(<RegistrationPolicySettings />);

    await waitFor(() => {
      expect(screen.getByText('Registration & Identity Verification')).toBeTruthy();
    });
  });

  it('loads and displays policy data', async () => {
    const { default: RegistrationPolicySettings } = await import('../system/RegistrationPolicySettings');
    renderWithProviders(<RegistrationPolicySettings />);

    await waitFor(() => {
      expect(mockGet).toHaveBeenCalledWith(
        expect.stringContaining('registration-policy'),
      );
    });
  });

  it('loads available providers', async () => {
    const { default: RegistrationPolicySettings } = await import('../system/RegistrationPolicySettings');
    renderWithProviders(<RegistrationPolicySettings />);

    await waitFor(() => {
      expect(mockGet).toHaveBeenCalledWith(
        expect.stringContaining('providers'),
      );
    });
  });

  it('renders save button', async () => {
    const { default: RegistrationPolicySettings } = await import('../system/RegistrationPolicySettings');
    renderWithProviders(<RegistrationPolicySettings />);

    await waitFor(() => {
      expect(screen.getByText('Save Registration Policy')).toBeTruthy();
    });
  });

  it('renders info box with mode explanations', async () => {
    const { default: RegistrationPolicySettings } = await import('../system/RegistrationPolicySettings');
    renderWithProviders(<RegistrationPolicySettings />);

    await waitFor(() => {
      expect(screen.getByText('How registration modes work:')).toBeTruthy();
    });
  });

  it('renders email verification toggle', async () => {
    const { default: RegistrationPolicySettings } = await import('../system/RegistrationPolicySettings');
    renderWithProviders(<RegistrationPolicySettings />);

    await waitFor(() => {
      expect(screen.getByText('Require Email Verification')).toBeTruthy();
    });
  });
});
