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
    hasFeature: (feature: string) => ['events', 'groups', 'goals'].includes(feature),
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
    const { getByText, queryByText } = render(<QuickCreateRoute />);

    expect(getByText('New exchange')).toBeTruthy();
    expect(getByText('New event')).toBeTruthy();
    expect(getByText('New group')).toBeTruthy();
    expect(getByText('New goal')).toBeTruthy();
    expect(queryByText('Offer time')).toBeNull();
  });

  it('opens the selected create flow', () => {
    const { getByText } = render(<QuickCreateRoute />);

    fireEvent.press(getByText('New event'));

    expect(mockRouterPush).toHaveBeenCalledWith('/(modals)/new-event');
  });
});
