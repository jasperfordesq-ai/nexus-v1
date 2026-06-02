// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

const mockSetTheme = jest.fn();
const mockSetColorScheme = jest.fn();

jest.mock('react-native', () => ({
  Appearance: {
    setColorScheme: (...args: unknown[]) => mockSetColorScheme(...args),
  },
}));

jest.mock('uniwind', () => ({
  Uniwind: {
    setTheme: (...args: unknown[]) => mockSetTheme(...args),
  },
}));

import { configureNativeTheme } from './nativeTheme';

describe('configureNativeTheme', () => {
  beforeEach(() => {
    mockSetColorScheme.mockClear();
    mockSetTheme.mockClear();
  });

  it('forces the dark React Native and HeroUI Native theme for the dark-only app shell', () => {
    configureNativeTheme();

    expect(mockSetColorScheme).toHaveBeenCalledWith('dark');
    expect(mockSetTheme).toHaveBeenCalledWith('dark');
  });
});
