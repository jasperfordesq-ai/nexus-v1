// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

const mockInit = jest.fn();

jest.mock('@/lib/theme/themeStore', () => ({
  themeStore: {
    init: (...args: unknown[]) => mockInit(...args),
  },
}));

import { configureNativeTheme } from './nativeTheme';

describe('configureNativeTheme', () => {
  beforeEach(() => {
    mockInit.mockClear();
  });

  it('delegates startup theme wiring to the theme store', () => {
    configureNativeTheme();

    expect(mockInit).toHaveBeenCalledTimes(1);
  });
});
