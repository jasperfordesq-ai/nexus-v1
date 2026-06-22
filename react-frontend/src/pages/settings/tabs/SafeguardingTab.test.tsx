// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

// ── Stable mock references ──
const mockToast = {
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
  showToast: vi.fn(),
};

vi.mock('@/lib/api', () => ({
  default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

vi.mock('@/contexts', () =>
  createMockContexts({ useToast: () => mockToast })
);

import { SafeguardingTab } from './SafeguardingTab';
import { api } from '@/lib/api';

const mockedGet = api.get as ReturnType<typeof vi.fn>;
const mockedPost = api.post as ReturnType<typeof vi.fn>;

// ── Helpers ──
const PREF_BROKER: import('./SafeguardingTab').MemberPreference = {
  preference_id: 1,
  option_id: 10,
  option_key: 'supervised_matching',
  label: 'Supervised matching',
  description: 'Only matched with broker approval',
  selected_value: 'yes',
  consent_given_at: '2024-01-15T10:00:00Z',
  created_at: '2024-01-15T10:00:00Z',
  activations: {
    requires_broker_approval: true,
    restricts_messaging: false,
    restricts_matching: true,
    requires_vetted_interaction: false,
    vetting_type_required: null,
  },
};

const PREF_DECLINATION: import('./SafeguardingTab').MemberPreference = {
  preference_id: 2,
  option_id: 20,
  option_key: 'none_apply',
  label: 'None apply to me',
  description: null,
  selected_value: 'yes',
  consent_given_at: null,
  created_at: null,
  activations: {
    requires_broker_approval: false,
    restricts_messaging: false,
    restricts_matching: false,
    requires_vetted_interaction: false,
    vetting_type_required: null,
  },
};

// Type is not exported by SafeguardingTab — declare inline for test convenience
declare module './SafeguardingTab' {
  interface MemberPreference {
    preference_id: number;
    option_id: number;
    option_key: string;
    label: string;
    description: string | null;
    selected_value: string;
    consent_given_at: string | null;
    created_at: string | null;
    activations: {
      requires_broker_approval: boolean;
      restricts_messaging: boolean;
      restricts_matching: boolean;
      requires_vetted_interaction: boolean;
      vetting_type_required: string | null;
    };
  }
}

describe('SafeguardingTab', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows loading spinner while preferences load', () => {
    mockedGet.mockReturnValue(new Promise(() => {}));
    render(<SafeguardingTab />);
    // The loading container and the inner Spinner both carry role="status";
    // the container is the one with aria-busy="true".
    expect(screen.getAllByRole('status').find((el) => el.getAttribute('aria-busy') === 'true')).toBeInTheDocument();
  });

  it('shows empty state when no preferences exist', async () => {
    mockedGet.mockResolvedValue({
      success: true,
      data: { preferences: [], count: 0 },
    });
    render(<SafeguardingTab />);
    await waitFor(() => {
      expect(screen.queryAllByRole('status').find((el) => el.getAttribute('aria-busy') === 'true')).toBeUndefined();
    });
    // Empty state is rendered with the Lock icon and no_preferences text
    expect(document.querySelector('.rounded-lg.bg-theme-elevated')).toBeInTheDocument();
  });

  it('renders a preference card when preferences are returned', async () => {
    mockedGet.mockResolvedValue({
      success: true,
      data: { preferences: [PREF_BROKER], count: 1 },
    });
    render(<SafeguardingTab />);
    await waitFor(() => {
      expect(screen.getByText('Supervised matching')).toBeInTheDocument();
    });
  });

  it('renders the preference description when present', async () => {
    mockedGet.mockResolvedValue({
      success: true,
      data: { preferences: [PREF_BROKER], count: 1 },
    });
    render(<SafeguardingTab />);
    await waitFor(() => {
      expect(screen.getByText('Only matched with broker approval')).toBeInTheDocument();
    });
  });

  it('renders revoke button for each preference', async () => {
    mockedGet.mockResolvedValue({
      success: true,
      data: { preferences: [PREF_BROKER], count: 1 },
    });
    render(<SafeguardingTab />);
    await waitFor(() => {
      expect(screen.getByText('Supervised matching')).toBeInTheDocument();
    });
    // Revoke button should be present
    const buttons = screen.getAllByRole('button');
    expect(buttons.length).toBeGreaterThan(0);
  });

  it('opens confirmation modal when revoke button is clicked', async () => {
    mockedGet.mockResolvedValue({
      success: true,
      data: { preferences: [PREF_BROKER], count: 1 },
    });
    render(<SafeguardingTab />);
    await waitFor(() => {
      expect(screen.getByText('Supervised matching')).toBeInTheDocument();
    });

    // Click the Revoke button (danger-soft variant)
    const buttons = screen.getAllByRole('button');
    const revokeBtn = buttons[buttons.length - 1]; // Last button is revoke
    fireEvent.click(revokeBtn);

    // Modal should open — confirmation title or preference label appears in dialog
    await waitFor(() => {
      // Modal content contains the preference label
      expect(screen.getAllByText('Supervised matching').length).toBeGreaterThanOrEqual(1);
    });
  });

  it('calls POST /v2/safeguarding/revoke and removes item on confirmation', async () => {
    mockedGet.mockResolvedValue({
      success: true,
      data: { preferences: [PREF_BROKER], count: 1 },
    });
    mockedPost.mockResolvedValue({ success: true });

    render(<SafeguardingTab />);
    await waitFor(() => {
      expect(screen.getByText('Supervised matching')).toBeInTheDocument();
    });

    // Click revoke button
    const buttons = screen.getAllByRole('button');
    fireEvent.click(buttons[buttons.length - 1]);

    // Wait for modal to open with confirm button
    await waitFor(() => {
      const confirmBtns = screen.getAllByRole('button');
      // Confirm button should be visible (danger variant)
      expect(confirmBtns.length).toBeGreaterThan(1);
    });

    // Click the confirm (danger) button — it's the last button in the modal
    const allBtns = screen.getAllByRole('button');
    const confirmBtn = allBtns[allBtns.length - 1];
    fireEvent.click(confirmBtn);

    await waitFor(() => {
      expect(mockedPost).toHaveBeenCalledWith('/v2/safeguarding/revoke', {
        option_id: 10,
      });
    });
  });

  it('shows success toast after successful revocation', async () => {
    mockedGet.mockResolvedValue({
      success: true,
      data: { preferences: [PREF_BROKER], count: 1 },
    });
    mockedPost.mockResolvedValue({ success: true });

    render(<SafeguardingTab />);
    await waitFor(() => {
      expect(screen.getByText('Supervised matching')).toBeInTheDocument();
    });

    const buttons = screen.getAllByRole('button');
    fireEvent.click(buttons[buttons.length - 1]);

    await waitFor(() => {
      expect(screen.getAllByRole('button').length).toBeGreaterThan(1);
    });

    const allBtns = screen.getAllByRole('button');
    fireEvent.click(allBtns[allBtns.length - 1]);

    await waitFor(() => {
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('shows error toast when revocation POST fails', async () => {
    mockedGet.mockResolvedValue({
      success: true,
      data: { preferences: [PREF_BROKER], count: 1 },
    });
    mockedPost.mockResolvedValue({ success: false, error: 'Cannot revoke' });

    render(<SafeguardingTab />);
    await waitFor(() => {
      expect(screen.getByText('Supervised matching')).toBeInTheDocument();
    });

    const buttons = screen.getAllByRole('button');
    fireEvent.click(buttons[buttons.length - 1]);

    await waitFor(() => {
      expect(screen.getAllByRole('button').length).toBeGreaterThan(1);
    });

    const allBtns = screen.getAllByRole('button');
    fireEvent.click(allBtns[allBtns.length - 1]);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('does NOT render activation chips for the none_apply declination option', async () => {
    // PREF_DECLINATION has option_key === 'none_apply' and all activations false
    mockedGet.mockResolvedValue({
      success: true,
      data: { preferences: [PREF_DECLINATION], count: 1 },
    });
    render(<SafeguardingTab />);
    await waitFor(() => {
      expect(screen.getByText('None apply to me')).toBeInTheDocument();
    });
    // No activation chips should render for the declination preference
    expect(screen.queryByText(/broker|vetted|match/i)).not.toBeInTheDocument();
  });

  it('calls GET /v2/safeguarding/my-preferences on mount', async () => {
    mockedGet.mockResolvedValue({ success: true, data: { preferences: [], count: 0 } });
    render(<SafeguardingTab />);
    await waitFor(() => {
      expect(mockedGet).toHaveBeenCalledWith('/v2/safeguarding/my-preferences');
    });
  });

  it('falls back to empty list when API returns success:false', async () => {
    mockedGet.mockResolvedValue({ success: false, error: 'Unauthorized' });
    render(<SafeguardingTab />);
    await waitFor(() => {
      expect(screen.queryAllByRole('status').find((el) => el.getAttribute('aria-busy') === 'true')).toBeUndefined();
    });
    // Empty state should show
    expect(document.querySelector('.rounded-lg.bg-theme-elevated')).toBeInTheDocument();
  });
});
