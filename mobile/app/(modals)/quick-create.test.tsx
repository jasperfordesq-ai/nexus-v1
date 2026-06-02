// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { fireEvent, render } from '@testing-library/react-native';

const mockRouterPush = jest.fn();

jest.mock('expo-router', () => ({
  router: {
    push: (...args: unknown[]) => mockRouterPush(...args),
    replace: jest.fn(),
    back: jest.fn(),
    canGoBack: () => false,
  },
}));

jest.mock('@expo/vector-icons', () => ({
  Ionicons: 'View',
}));

jest.mock('@/lib/hooks/useTenant', () => ({
  usePrimaryColor: () => '#006FEE',
  useTenant: () => ({
    hasFeature: (feature: string) => ['events', 'groups', 'goals', 'marketplace', 'polls'].includes(feature),
    hasModule: (module: string) => module === 'listings',
  }),
}));

jest.mock('@/lib/hooks/useTheme', () => ({
  useTheme: () => ({
    text: '#000000',
    textSecondary: '#666666',
    textMuted: '#999999',
    border: '#dddddd',
  }),
}));

import QuickCreateRoute from './quick-create';

describe('QuickCreateRoute', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('renders source-of-truth quick-create options without caring community', () => {
    const { getByText } = render(<QuickCreateRoute />);

    expect(getByText('New listing')).toBeTruthy();
    expect(getByText('Sell item')).toBeTruthy();
    expect(getByText('New message')).toBeTruthy();
    expect(getByText('New event')).toBeTruthy();
    expect(getByText('New poll')).toBeTruthy();
    expect(getByText('New group')).toBeTruthy();
    expect(getByText('New goal')).toBeTruthy();
  });

  it('opens the selected create flow', () => {
    const { getByText } = render(<QuickCreateRoute />);

    fireEvent.press(getByText('New event'));

    expect(mockRouterPush).toHaveBeenCalledWith('/(modals)/new-event');
  });

  it('opens the native message composer from quick-create', () => {
    const { getByText } = render(<QuickCreateRoute />);

    fireEvent.press(getByText('New message'));

    expect(mockRouterPush).toHaveBeenCalledWith('/(modals)/new-message');
  });

  it('opens the marketplace listing creator from quick-create', () => {
    const { getByText } = render(<QuickCreateRoute />);

    fireEvent.press(getByText('Sell item'));

    expect(mockRouterPush).toHaveBeenCalledWith('/(modals)/new-marketplace-listing');
  });

  it('opens the native poll composer from quick-create', () => {
    const { getByText } = render(<QuickCreateRoute />);

    fireEvent.press(getByText('New poll'));

    expect(mockRouterPush).toHaveBeenCalledWith('/(modals)/polls?create=1');
  });
});
