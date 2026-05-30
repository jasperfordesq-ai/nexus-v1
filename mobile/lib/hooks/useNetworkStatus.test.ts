// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { act, renderHook, waitFor } from '@testing-library/react-native';

let netInfoListener: ((state: { isConnected: boolean | null; isInternetReachable: boolean | null }) => void) | null = null;

jest.mock('@react-native-community/netinfo', () => ({
  addEventListener: jest.fn((listener) => {
    netInfoListener = listener;
    return jest.fn();
  }),
}));

import NetInfo from '@react-native-community/netinfo';
import { useNetworkStatus } from './useNetworkStatus';

describe('useNetworkStatus', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    netInfoListener = null;
    global.fetch = jest.fn().mockResolvedValue({ ok: true, status: 200 }) as jest.Mock;
  });

  it('updates immediately when native connectivity reports offline', async () => {
    const { result } = renderHook(() => useNetworkStatus());

    expect(NetInfo.addEventListener).toHaveBeenCalled();
    expect(result.current.isOnline).toBe(true);

    act(() => {
      netInfoListener?.({ isConnected: false, isInternetReachable: false });
    });

    await waitFor(() => {
      expect(result.current.isOnline).toBe(false);
    });
  });

  it('does not show online when the device has local network but no internet reachability', async () => {
    const { result } = renderHook(() => useNetworkStatus());

    act(() => {
      netInfoListener?.({ isConnected: true, isInternetReachable: false });
    });

    await waitFor(() => {
      expect(result.current.isOnline).toBe(false);
    });
  });
});
