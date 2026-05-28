// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { fireEvent, render, waitFor } from '@testing-library/react-native';
import { Image as ExpoImage } from 'expo-image';

const mockReplace = jest.fn();
const mockSetTenantSlug = jest.fn().mockResolvedValue(undefined);
const mockRefresh = jest.fn();
let mockIsAuthenticated = false;
let mockApiState: {
  data: { data: Array<{ id: number; slug: string; name: string; logo_url: string | null }> } | null;
  isLoading: boolean;
  error: string | null;
} = {
  data: {
      data: [
        { id: 1, slug: 'hour-timebank', name: 'hOUR Timebank', logo_url: null },
        { id: 2, slug: 'west-cork', name: 'West Cork Timebank', logo_url: '/uploads/tenants/west-cork.png' },
      ],
  },
  isLoading: false,
  error: null,
};

jest.mock('expo-router', () => ({
  useRouter: () => ({ replace: mockReplace }),
}));

jest.mock('expo-image', () => ({
  Image: 'Image',
}));

jest.mock('@/lib/hooks/useApi', () => ({
  useApi: () => ({ ...mockApiState, refresh: mockRefresh }),
}));

jest.mock('@/lib/context/AuthContext', () => ({
  useAuthContext: () => ({ isAuthenticated: mockIsAuthenticated }),
}));

jest.mock('@/lib/hooks/useTenant', () => ({
  usePrimaryColor: () => '#6366f1',
  useTenant: () => ({
    tenantSlug: 'hour-timebank',
    setTenantSlug: mockSetTenantSlug,
  }),
}));

jest.mock('@/lib/api/tenant', () => ({
  listTenants: jest.fn(),
}));

import SelectTenantScreen from './select-tenant';

describe('SelectTenantScreen', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockIsAuthenticated = false;
    mockApiState = {
      data: {
        data: [
          { id: 1, slug: 'hour-timebank', name: 'hOUR Timebank', logo_url: null },
          { id: 2, slug: 'west-cork', name: 'West Cork Timebank', logo_url: '/uploads/tenants/west-cork.png' },
        ],
      },
      isLoading: false,
      error: null,
    };
  });

  it('renders communities and the selected state', () => {
    const { getByText } = render(<SelectTenantScreen />);

    expect(getByText('Select your timebank')).toBeTruthy();
    expect(getByText('hOUR Timebank')).toBeTruthy();
    expect(getByText('West Cork Timebank')).toBeTruthy();
    expect(getByText('Current community: hOUR Timebank')).toBeTruthy();
    expect(getByText('Selected community')).toBeTruthy();
  });

  it('selects a community and returns to login', async () => {
    const { getByLabelText } = render(<SelectTenantScreen />);

    fireEvent.press(getByLabelText('West Cork Timebank'));

    await waitFor(() => expect(mockSetTenantSlug).toHaveBeenCalledWith('west-cork'));
    expect(mockReplace).toHaveBeenCalledWith('/login');
  });

  it('returns authenticated users to home after switching community', async () => {
    mockIsAuthenticated = true;
    const { getByLabelText } = render(<SelectTenantScreen />);

    fireEvent.press(getByLabelText('West Cork Timebank'));

    await waitFor(() => expect(mockSetTenantSlug).toHaveBeenCalledWith('west-cork'));
    expect(mockReplace).toHaveBeenCalledWith('/home');
  });

  it('shows a retry state when communities fail to load', () => {
    mockApiState = { data: null, isLoading: false, error: 'Network unavailable' };
    const { getByText } = render(<SelectTenantScreen />);

    expect(getByText('Could not load communities')).toBeTruthy();
    expect(getByText('Network unavailable')).toBeTruthy();

    fireEvent.press(getByText('Retry'));
    expect(mockRefresh).toHaveBeenCalled();
  });

  it('resolves relative tenant logo URLs', () => {
    const { UNSAFE_getAllByType } = render(<SelectTenantScreen />);

    expect(UNSAFE_getAllByType(ExpoImage)[0].props.source.uri).toBe(
      'https://api.project-nexus.ie/uploads/tenants/west-cork.png',
    );
  });
});
