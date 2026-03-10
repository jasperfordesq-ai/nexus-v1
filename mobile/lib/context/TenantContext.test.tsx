// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { renderHook, waitFor, act } from '@testing-library/react-native';

// --- Mocks ---

jest.mock('@/lib/constants', () => ({
  DEFAULT_TENANT: 'hour-timebank',
  STORAGE_KEYS: {
    AUTH_TOKEN: 'auth_token',
    REFRESH_TOKEN: 'refresh_token',
    TENANT_SLUG: 'tenant_slug',
    USER_DATA: 'user_data',
  },
  API_V2: '/api/v2',
  API_BASE_URL: 'https://test.api',
  TIMEOUTS: { API_REQUEST: 15_000 },
}));

const mockStorageGet = jest.fn();
const mockStorageSet = jest.fn().mockResolvedValue(undefined);

jest.mock('@/lib/storage', () => ({
  storage: {
    get: (...args: unknown[]) => mockStorageGet(...args),
    set: (...args: unknown[]) => mockStorageSet(...args),
    remove: jest.fn().mockResolvedValue(undefined),
    getJson: jest.fn().mockResolvedValue(null),
    setJson: jest.fn().mockResolvedValue(undefined),
  },
}));

const mockGetTenantConfig = jest.fn();

jest.mock('@/lib/api/tenant', () => ({
  getTenantConfig: (...args: unknown[]) => mockGetTenantConfig(...args),
}));

// --- Test data ---

const mockTenant = {
  id: 1,
  name: 'Hour Timebank',
  slug: 'hour-timebank',
  branding: { primary_color: '#4CAF50', logo_url: null },
  features: { events: true, marketplace: false, blog: true },
  modules: { wallet: true, blog: false },
};

// --- Tests ---

import { TenantProvider, useTenantContext } from './TenantContext';

const wrapper = ({ children }: { children: React.ReactNode }) => (
  <TenantProvider>{children}</TenantProvider>
);

describe('TenantContext', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockStorageGet.mockResolvedValue(null);
    mockGetTenantConfig.mockResolvedValue({ data: mockTenant });
  });

  it('loads default tenant when no slug is stored', async () => {
    mockStorageGet.mockResolvedValue(null);
    const { result } = renderHook(() => useTenantContext(), { wrapper });

    await waitFor(() => expect(result.current.isLoading).toBe(false));

    expect(mockStorageSet).toHaveBeenCalledWith('tenant_slug', 'hour-timebank');
    expect(result.current.tenant).toEqual(mockTenant);
    expect(result.current.tenantSlug).toBe('hour-timebank');
  });

  it('restores tenant from stored slug', async () => {
    mockStorageGet.mockResolvedValue('my-community');
    const { result } = renderHook(() => useTenantContext(), { wrapper });

    await waitFor(() => expect(result.current.isLoading).toBe(false));

    expect(result.current.tenantSlug).toBe('my-community');
    expect(mockStorageSet).toHaveBeenCalledWith('tenant_slug', 'my-community');
  });

  it('hasFeature returns true for an enabled feature', async () => {
    const { result } = renderHook(() => useTenantContext(), { wrapper });
    await waitFor(() => expect(result.current.isLoading).toBe(false));

    expect(result.current.hasFeature('events')).toBe(true);
  });

  it('hasFeature returns false for a disabled feature', async () => {
    const { result } = renderHook(() => useTenantContext(), { wrapper });
    await waitFor(() => expect(result.current.isLoading).toBe(false));

    expect(result.current.hasFeature('marketplace')).toBe(false);
  });

  it('hasFeature returns false when tenant is null (graceful degradation)', async () => {
    mockGetTenantConfig.mockRejectedValue(new Error('Network error'));
    const { result } = renderHook(() => useTenantContext(), { wrapper });

    await waitFor(() => expect(result.current.isLoading).toBe(false));

    expect(result.current.tenant).toBeNull();
    expect(result.current.hasFeature('events')).toBe(false);
  });

  it('setTenantSlug updates the slug and reloads config', async () => {
    const newTenant = { ...mockTenant, slug: 'new-bank', name: 'New Bank' };
    mockGetTenantConfig
      .mockResolvedValueOnce({ data: mockTenant })
      .mockResolvedValueOnce({ data: newTenant });

    const { result } = renderHook(() => useTenantContext(), { wrapper });
    await waitFor(() => expect(result.current.isLoading).toBe(false));

    await act(async () => {
      await result.current.setTenantSlug('new-bank');
    });

    await waitFor(() => expect(result.current.isLoading).toBe(false));

    expect(result.current.tenantSlug).toBe('new-bank');
    expect(result.current.tenant?.name).toBe('New Bank');
    expect(mockGetTenantConfig).toHaveBeenCalledTimes(2);
  });
});
