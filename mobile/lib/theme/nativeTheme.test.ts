// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

const mockSetTheme = jest.fn();

jest.mock('uniwind', () => ({
  Uniwind: {
    setTheme: (...args: unknown[]) => mockSetTheme(...args),
  },
}));

import { configureNativeTheme } from './nativeTheme';

describe('configureNativeTheme', () => {
  beforeEach(() => {
    mockSetTheme.mockClear();
  });

  it('forces the dark HeroUI Native theme for the dark-only app shell', () => {
    configureNativeTheme();

    expect(mockSetTheme).toHaveBeenCalledWith('dark');
  });
});
