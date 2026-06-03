// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { fireEvent, render } from '@testing-library/react-native';
import * as Linking from 'expo-linking';

import SupportRoute from './support';

let mockSearchParams: Record<string, string> = {};

jest.mock('expo-router', () => ({
  router: { push: jest.fn() },
  useLocalSearchParams: () => mockSearchParams,
}));

jest.mock('@expo/vector-icons', () => {
  const { Text } = require('react-native');
  return {
    Ionicons: ({ name }: { name: string }) => <Text>{name}</Text>,
  };
});

jest.mock('expo-linking', () => ({
  openURL: jest.fn().mockResolvedValue(undefined),
}));

jest.mock('@/components/ModalErrorBoundary', () => ({ children }: { children: React.ReactNode }) => children);
jest.mock('@/components/ui/AppTopBar', () => {
  const { Text } = require('react-native');
  return function MockAppTopBar({ title }: { title: string }) {
    return <Text>{title}</Text>;
  };
});

describe('SupportRoute', () => {
  beforeEach(() => {
    mockSearchParams = {};
    jest.clearAllMocks();
  });

  it('renders support and legal destinations', () => {
    const { getByText } = render(<SupportRoute />);

    expect(getByText('Support & legal')).toBeTruthy();
    expect(getByText('Help center')).toBeTruthy();
    expect(getByText('Resources')).toBeTruthy();
    expect(getByText('Privacy')).toBeTruthy();
    expect(getByText('Accessibility')).toBeTruthy();
    expect(getByText('Trust & safety')).toBeTruthy();
  });

  it('opens selected web support pages externally', () => {
    const { getAllByText } = render(<SupportRoute />);

    fireEvent.press(getAllByText('Open web')[0]);

    expect(Linking.openURL).toHaveBeenCalledWith('https://app.project-nexus.ie/help');
  });

  it('renders native legal summaries and keeps canonical web links available', () => {
    const { getAllByText, getByText } = render(<SupportRoute />);

    fireEvent.press(getAllByText('Read in app')[3]);

    expect(getByText('Privacy summary')).toBeTruthy();
    expect(getByText('Data you provide')).toBeTruthy();

    fireEvent.press(getAllByText('Open web')[0]);

    expect(Linking.openURL).toHaveBeenCalledWith('https://app.project-nexus.ie/privacy');
  });

  it('opens a requested legal document from native route params', () => {
    mockSearchParams = { doc: 'terms' };

    const { getByText } = render(<SupportRoute />);

    expect(getByText('Terms of use summary')).toBeTruthy();
    expect(getByText('Use the platform responsibly')).toBeTruthy();
  });
});
