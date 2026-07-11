// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { beforeEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

const toast = {
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
  showToast: vi.fn(),
};

vi.mock('@/lib/api', () => ({
  default: { get: vi.fn(), post: vi.fn() },
  api: { get: vi.fn(), post: vi.fn() },
}));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/contexts', () => createMockContexts({ useToast: () => toast }));

import { api } from '@/lib/api';
import { SafeguardingTab, type MemberPreference } from './SafeguardingTab';

const mockedGet = api.get as ReturnType<typeof vi.fn>;
const mockedPost = api.post as ReturnType<typeof vi.fn>;

const preference: MemberPreference = {
  preference_id: 1,
  option_id: 10,
  option_key: 'supervised_matching',
  label: 'Supervised matching',
  description: 'Only matched with broker approval',
  selected_value: 'yes',
  consent_given_at: '2026-01-15T10:00:00Z',
  created_at: '2026-01-15T10:00:00Z',
  activations: {
    requires_broker_approval: true,
    restricts_messaging: false,
    restricts_matching: true,
    requires_vetted_interaction: false,
    vetting_type_required: null,
  },
};

const vettingStatus = {
  policy: {
    configured: true,
    contact_policy_available: true,
    jurisdiction: 'england_wales',
    label: 'England and Wales',
    attestation_code: 'dbs_enhanced',
    attestation_label: 'Enhanced DBS',
    purpose_code: 'safeguarded_member_contact',
  },
  decision: 'not_confirmed',
  review_status: null,
  confirmed_at: null,
  revoked_at: null,
};

function mockLoads(statusOverrides: Record<string, unknown> = {}, preferences = [preference]) {
  mockedGet.mockImplementation((url: string) => {
    if (url === '/v2/safeguarding/my-preferences') {
      return Promise.resolve({ success: true, data: { preferences, count: preferences.length } });
    }
    if (url === '/v2/safeguarding/my-vetting-status') {
      return Promise.resolve({ success: true, data: { ...vettingStatus, ...statusOverrides } });
    }
    return Promise.resolve({ success: false });
  });
}

describe('SafeguardingTab', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockLoads();
  });

  it('loads private preferences and community vetting status', async () => {
    render(<SafeguardingTab />);

    await waitFor(() => expect(screen.getByText('Supervised matching')).toBeInTheDocument());
    expect(mockedGet).toHaveBeenCalledWith('/v2/safeguarding/my-preferences');
    expect(mockedGet).toHaveBeenCalledWith('/v2/safeguarding/my-vetting-status');
    expect(screen.getByText('Enhanced DBS')).toBeInTheDocument();
    expect(screen.getByText('Not confirmed')).toBeInTheDocument();
  });

  it('shows a confirmed private community decision', async () => {
    mockLoads({ decision: 'confirmed', confirmed_at: '2026-07-11T10:00:00Z' }, []);
    render(<SafeguardingTab />);

    await waitFor(() => expect(screen.getByText('Confirmed')).toBeInTheDocument());
    expect(screen.queryByRole('button', { name: 'Request broker review' })).toBeNull();
  });

  it('requests broker review with a genuinely empty request body', async () => {
    mockedPost.mockResolvedValue({ success: true, data: { status: 'pending' } });
    render(<SafeguardingTab />);
    await waitFor(() => expect(screen.getByRole('button', { name: 'Request broker review' })).toBeInTheDocument());

    fireEvent.click(screen.getByRole('button', { name: 'Request broker review' }));

    await waitFor(() => expect(mockedPost).toHaveBeenCalledWith('/v2/safeguarding/vetting-review-request'));
    expect(screen.getByRole('button', { name: 'Review requested' })).toBeDisabled();
  });

  it('does not offer document, attachment, reference, or notes inputs', async () => {
    const { container } = render(<SafeguardingTab />);
    await waitFor(() => expect(screen.getByText('Enhanced DBS')).toBeInTheDocument());

    expect(container.querySelector('input[type="file"]')).toBeNull();
    expect(container.querySelector('textarea')).toBeNull();
    expect(screen.queryByText(/certificate number/i)).toBeNull();
    expect(screen.getByText(/Do not upload or send a DBS/i)).toBeInTheDocument();
  });

  it('does not offer review when the jurisdiction policy is unavailable', async () => {
    mockLoads({
      policy: { ...vettingStatus.policy, configured: false, contact_policy_available: false },
    });
    render(<SafeguardingTab />);

    await waitFor(() => expect(screen.getByText(/has not configured a supported safeguarding contact policy/i)).toBeInTheDocument());
    expect(screen.queryByRole('button', { name: 'Request broker review' })).toBeNull();
  });

  it('preserves member preference revocation', async () => {
    mockedPost.mockResolvedValue({ success: true });
    render(<SafeguardingTab />);
    await waitFor(() => expect(screen.getByRole('button', { name: 'Revoke' })).toBeInTheDocument());

    fireEvent.click(screen.getByRole('button', { name: 'Revoke' }));
    fireEvent.click(await screen.findByRole('button', { name: 'Yes, revoke' }));

    await waitFor(() => expect(mockedPost).toHaveBeenCalledWith('/v2/safeguarding/revoke', { option_id: 10 }));
  });
});
