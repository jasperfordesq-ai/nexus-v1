// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { render, waitFor } from '@testing-library/react-native';

const mockGetUserVerificationBadges = jest.fn();

jest.mock('@/lib/api/verification', () => ({
  getUserVerificationBadges: (...args: unknown[]) => mockGetUserVerificationBadges(...args),
}));

jest.mock('@/lib/hooks/useTheme', () => ({
  useTheme: () => ({
    textMuted: '#71717a',
    success: '#16a34a',
    warning: '#f59e0b',
    info: '#2563eb',
  }),
}));

jest.mock('@expo/vector-icons', () => ({
  Ionicons: 'View',
}));

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string) => {
      const map: Record<string, string> = {
        'aria.verification_badges': 'Verification badges',
        'verification.not_id_verified': 'Not ID verified',
        'verification.badge.id_verified': 'ID verified',
        'verification.badge.email_verified': 'Email verified',
        'verification.badge.unknown': 'Verified',
      };
      return map[key] ?? key;
    },
  }),
}));

import VerificationBadgeRow from './VerificationBadgeRow';

describe('VerificationBadgeRow', () => {
  beforeEach(() => {
    mockGetUserVerificationBadges.mockReset();
  });

  it('does not turn a plain user verification flag into an ID verified label', async () => {
    mockGetUserVerificationBadges.mockResolvedValue([]);

    const { getByText, queryByText } = render(<VerificationBadgeRow userId={42} showUnverified />);

    await waitFor(() => expect(getByText('Not ID verified')).toBeTruthy());
    expect(queryByText('ID verified')).toBeNull();
  });

  it('shows ID verified only when the id_verified badge is returned', async () => {
    mockGetUserVerificationBadges.mockResolvedValue([{ badge_type: 'id_verified', label: 'ID verified' }]);

    const { getByText, queryByText } = render(<VerificationBadgeRow userId={42} showUnverified />);

    await waitFor(() => expect(getByText('ID verified')).toBeTruthy());
    expect(queryByText('Not ID verified')).toBeNull();
  });

  it('can suppress the unverified label for dense layouts', async () => {
    mockGetUserVerificationBadges.mockResolvedValue([]);

    const { queryByText } = render(<VerificationBadgeRow userId={42} showUnverified={false} />);

    await waitFor(() => expect(mockGetUserVerificationBadges).toHaveBeenCalledWith(42));
    expect(queryByText('Not ID verified')).toBeNull();
  });
});
