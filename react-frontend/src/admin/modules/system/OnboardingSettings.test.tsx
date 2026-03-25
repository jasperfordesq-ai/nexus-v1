// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for OnboardingSettings admin component
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';

// Mock API module
const mockGet = vi.fn();
const mockPut = vi.fn();
const mockPost = vi.fn();

vi.mock('@/lib/api', () => ({
  api: {
    get: (...args: unknown[]) => mockGet(...args),
    put: (...args: unknown[]) => mockPut(...args),
    post: (...args: unknown[]) => mockPost(...args),
  },
  tokenManager: { getTenantId: vi.fn() },
}));

const stableToastValue = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };
const stableTenantValue = {
  tenant: { id: 2, name: 'Test Community', slug: 'test', branding: { name: 'Test Community' } },
  tenantPath: (p: string) => `/test${p}`,
  hasFeature: vi.fn(() => true),
  hasModule: vi.fn(() => true),
};

vi.mock('@/contexts', () => ({
  useToast: vi.fn(() => stableToastValue),
  useAuth: vi.fn(() => ({ user: { id: 1 }, isAuthenticated: true, refreshUser: vi.fn() })),
  useTenant: vi.fn(() => stableTenantValue),
  ToastProvider: ({ children }: { children: React.ReactNode }) => children,
  useTheme: () => ({ resolvedTheme: 'light', toggleTheme: vi.fn(), theme: 'system', setTheme: vi.fn() }),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
}));

vi.mock('@/contexts/ToastContext', () => ({
  useToast: vi.fn(() => stableToastValue),
  ToastProvider: ({ children }: { children: React.ReactNode }) => children,
}));

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// Mock the admin PageHeader component
vi.mock('../../components', () => ({
  PageHeader: ({ title, description }: { title: string; description: string }) => (
    <div>
      <h1>{title}</h1>
      <p>{description}</p>
    </div>
  ),
}));

vi.mock('framer-motion', () => ({
  motion: {
    div: ({ children, ...props }: Record<string, unknown>) => <div>{children}</div>,
  },
  AnimatePresence: ({ children }: { children: React.ReactNode }) => children,
}));

import { OnboardingSettings } from './OnboardingSettings';

// ── Test data ─────────────────────────────────────────────────────────────────

const MOCK_CONFIG = {
  enabled: true,
  mandatory: false,
  step_welcome_enabled: true,
  step_profile_enabled: true,
  step_profile_required: true,
  step_interests_enabled: true,
  step_interests_required: false,
  step_skills_enabled: true,
  step_skills_required: false,
  step_safeguarding_enabled: true,
  step_safeguarding_required: false,
  step_confirm_enabled: true,
  avatar_required: true,
  bio_required: true,
  bio_min_length: 20,
  listing_creation_mode: 'disabled',
  listing_max_auto: 3,
  require_completion_for_visibility: false,
  require_avatar_for_visibility: false,
  require_bio_for_visibility: false,
  welcome_text: null,
  help_text: null,
  safeguarding_intro_text: null,
  country_preset: 'ireland',
};

const MOCK_SAFEGUARDING_OPTIONS = [
  {
    id: 1,
    option_key: 'is_vulnerable_adult',
    option_type: 'checkbox',
    label: 'I consider myself a vulnerable adult',
    description: 'A coordinator will help you.',
    is_active: true,
    is_required: false,
    triggers: null,
    preset_source: 'ireland',
  },
  {
    id: 2,
    option_key: 'requires_vetted_partners',
    option_type: 'checkbox',
    label: 'I prefer vetted members',
    description: null,
    is_active: true,
    is_required: false,
    triggers: null,
    preset_source: 'ireland',
  },
];

// ── Tests ─────────────────────────────────────────────────────────────────────

describe('OnboardingSettings', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockGet.mockResolvedValue({
      success: true,
      data: { config: MOCK_CONFIG, safeguarding_options: MOCK_SAFEGUARDING_OPTIONS },
    });
    mockPut.mockResolvedValue({ success: true, data: {} });
    mockPost.mockResolvedValue({ success: true, data: { options_created: ['opt_1'] } });
  });

  // ── 1. Loading state ────────────────────────────────────────────────────

  it('renders loading state initially', () => {
    // Never-resolving promise keeps component in loading state
    mockGet.mockReturnValue(new Promise(() => {}));
    render(<OnboardingSettings />);

    // The component renders a Spinner while loading — detect via role="progressbar"
    // or the HeroUI spinner class
    const spinner = document.querySelector('[role="progressbar"]') ||
                    document.querySelector('.animate-spinner-ease-spin');
    expect(spinner).toBeTruthy();

    // Settings content should NOT be visible yet
    expect(screen.queryByText('Module Control')).toBeNull();
  });

  // ── 2. Renders settings after config loads ──────────────────────────────

  it('renders settings after config loads', async () => {
    render(<OnboardingSettings />);

    await waitFor(() => {
      expect(screen.getByText('Module Control')).toBeInTheDocument();
    });

    // Verify all section headings are present
    expect(screen.getByText('Step Configuration')).toBeInTheDocument();
    expect(screen.getByText('Profile Requirements')).toBeInTheDocument();
    expect(screen.getByText('Listing Creation')).toBeInTheDocument();
    expect(screen.getByText('Public Visibility Gating')).toBeInTheDocument();
    expect(screen.getByText('Safeguarding Configuration')).toBeInTheDocument();
    expect(screen.getByText('Custom Text')).toBeInTheDocument();

    // Verify page header
    expect(screen.getByText('Onboarding Settings')).toBeInTheDocument();
    expect(screen.getByText(/Configure the onboarding wizard for Test Community/)).toBeInTheDocument();

    // Verify safeguarding options are displayed
    expect(screen.getByText('I consider myself a vulnerable adult')).toBeInTheDocument();
    expect(screen.getByText('I prefer vetted members')).toBeInTheDocument();

    // Verify save button
    expect(screen.getByText('Save Settings')).toBeInTheDocument();

    // Verify the API was called
    expect(mockGet).toHaveBeenCalledWith('/v2/admin/config/onboarding');
  });

  // ── 3. Toggle onboarding enabled switch ─────────────────────────────────

  it('toggles onboarding enabled switch', async () => {
    render(<OnboardingSettings />);

    await waitFor(() => {
      expect(screen.getByText('Module Control')).toBeInTheDocument();
    });

    // Find the "Onboarding enabled" switch — the component renders Switch children
    // with text "Onboarding enabled". The actual input is inside the Switch.
    const enabledSwitch = screen.getByText('Onboarding enabled')
      .closest('label')
      ?.querySelector('input[type="checkbox"]') as HTMLInputElement;
    expect(enabledSwitch).toBeTruthy();

    // The config starts with enabled: true
    expect(enabledSwitch.checked).toBe(true);

    // Toggle off
    fireEvent.click(enabledSwitch);

    // Now click Save to trigger the API call
    const { userEvent } = await import('@/test/test-utils');
    const user = userEvent.setup();
    await user.click(screen.getByText('Save Settings'));

    await waitFor(() => {
      expect(mockPut).toHaveBeenCalledWith(
        '/v2/admin/config/onboarding',
        expect.objectContaining({ enabled: false }),
      );
    });
  });

  // ── 4. Save bio min length setting ──────────────────────────────────────

  it('saves bio min length setting', async () => {
    render(<OnboardingSettings />);

    await waitFor(() => {
      expect(screen.getByText('Profile Requirements')).toBeInTheDocument();
    });

    // Find the bio min length input by its label
    const bioInput = screen.getByLabelText(/Minimum bio length/i) as HTMLInputElement;
    expect(bioInput).toBeTruthy();
    expect(bioInput.value).toBe('20');

    // Clear and type a new value
    fireEvent.change(bioInput, { target: { value: '50' } });

    // Click Save
    const { userEvent } = await import('@/test/test-utils');
    const user = userEvent.setup();
    await user.click(screen.getByText('Save Settings'));

    await waitFor(() => {
      expect(mockPut).toHaveBeenCalledWith(
        '/v2/admin/config/onboarding',
        expect.objectContaining({ bio_min_length: 50 }),
      );
    });
  });

  // ── 5. Shows preset confirmation modal ──────────────────────────────────

  it('shows preset confirmation modal', async () => {
    render(<OnboardingSettings />);

    await waitFor(() => {
      expect(screen.getByText('Safeguarding Configuration')).toBeInTheDocument();
    });

    // The country_preset is 'ireland' (not 'custom'), so "Apply Preset" should be enabled.
    // There is only one "Apply Preset" button visible initially (in the safeguarding card).
    const applyButton = screen.getByRole('button', { name: /Apply Preset/i });
    expect(applyButton).not.toBeDisabled();

    // Click "Apply Preset" to open the modal — use fireEvent for HeroUI onPress compatibility
    fireEvent.click(applyButton);

    // Modal should appear with confirmation text
    await waitFor(() => {
      expect(screen.getByText('Apply Country Preset')).toBeInTheDocument();
    });

    await waitFor(() => {
      expect(screen.getByText(/Existing custom options will not be overwritten/)).toBeInTheDocument();
    });

    // Cancel button should be visible inside the modal
    expect(screen.getByText('Cancel')).toBeInTheDocument();
  });

  // ── 6. Applies preset on confirm ────────────────────────────────────────

  it('applies preset on confirm', async () => {
    render(<OnboardingSettings />);

    await waitFor(() => {
      expect(screen.getByText('Safeguarding Configuration')).toBeInTheDocument();
    });

    // Open the preset modal
    fireEvent.click(screen.getByRole('button', { name: /Apply Preset/i }));

    await waitFor(() => {
      expect(screen.getByText('Apply Country Preset')).toBeInTheDocument();
    });

    // The modal footer has two buttons: Cancel and Apply Preset.
    // We need the Apply Preset button inside the modal (not the one in the card).
    const modalButtons = screen.getAllByRole('button', { name: /Apply Preset/i });
    // The last one is the confirm button in the modal footer
    const confirmButton = modalButtons[modalButtons.length - 1];
    fireEvent.click(confirmButton);

    // Verify api.post was called with the apply-preset endpoint
    await waitFor(() => {
      expect(mockPost).toHaveBeenCalledWith(
        '/v2/admin/config/onboarding/apply-preset',
        { preset: 'ireland' },
      );
    });

    // After success, a toast should be shown and config should be refetched
    await waitFor(() => {
      expect(stableToastValue.success).toHaveBeenCalledWith(
        'Preset applied',
        expect.stringContaining('1'),
      );
    });

    // Config should be refetched (second call to mockGet)
    expect(mockGet).toHaveBeenCalledTimes(2);
  });
});
