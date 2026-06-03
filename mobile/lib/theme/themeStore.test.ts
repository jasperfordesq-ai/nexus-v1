// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

const mockSetTheme = jest.fn();
const mockSetColorScheme = jest.fn();
const mockGetColorScheme = jest.fn<'light' | 'dark', []>(() => 'dark');
const mockAddChangeListener = jest.fn(() => ({ remove: jest.fn() }));
const mockStorageGet = jest.fn<Promise<string | null>, [string]>(async () => null);
const mockStorageSet = jest.fn(async () => undefined);

jest.mock('react-native', () => ({
  Appearance: {
    getColorScheme: () => mockGetColorScheme(),
    setColorScheme: (...args: unknown[]) => mockSetColorScheme(...args),
    addChangeListener: (cb: unknown) => mockAddChangeListener(cb as () => void),
  },
}));

jest.mock('uniwind', () => ({
  Uniwind: { setTheme: (...args: unknown[]) => mockSetTheme(...args) },
}));

jest.mock('@/lib/storage', () => ({
  storage: {
    get: (...args: [string]) => mockStorageGet(...args),
    set: (...args: unknown[]) => mockStorageSet(...args),
  },
}));

// Mock constants so the store doesn't pull in expo-constants (which needs the
// real react-native Platform that we replace above).
jest.mock('@/lib/constants', () => ({
  STORAGE_KEYS: { THEME_MODE: 'nexus_theme_mode' },
}));

import { themeStore } from './themeStore';

const flush = () => new Promise((resolve) => setTimeout(resolve, 0));

describe('themeStore', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockGetColorScheme.mockReturnValue('dark');
    mockStorageGet.mockResolvedValue(null);
    themeStore.__resetForTests();
  });

  it('defaults to dark before any wiring (preserves dark-only behaviour)', () => {
    expect(themeStore.getSnapshot()).toBe('dark');
    expect(themeStore.getMode()).toBe('system');
  });

  it('init() adopts the OS scheme and pushes it to Uniwind + Appearance', () => {
    mockGetColorScheme.mockReturnValue('light');
    themeStore.init();

    expect(themeStore.getSnapshot()).toBe('light');
    expect(mockSetTheme).toHaveBeenCalledWith('light');
    // 'system' mode hands colour-scheme control back to the OS.
    expect(mockSetColorScheme).toHaveBeenCalledWith(null);
  });

  it('setMode() forces a scheme, applies it, and persists the choice', () => {
    themeStore.setMode('light');

    expect(themeStore.getSnapshot()).toBe('light');
    expect(themeStore.getMode()).toBe('light');
    expect(mockSetTheme).toHaveBeenCalledWith('light');
    expect(mockSetColorScheme).toHaveBeenCalledWith('light');
    expect(mockStorageSet).toHaveBeenCalledWith('nexus_theme_mode', 'light');
  });

  it('notifies subscribers on change and stops after unsubscribe', () => {
    const listener = jest.fn();
    const unsubscribe = themeStore.subscribe(listener);

    themeStore.setMode('light');
    expect(listener).toHaveBeenCalledTimes(1);

    unsubscribe();
    themeStore.setMode('dark');
    expect(listener).toHaveBeenCalledTimes(1);
  });

  it('ignores a no-op setMode to the current mode', () => {
    themeStore.setMode('system');
    expect(mockSetTheme).not.toHaveBeenCalled();
    expect(mockStorageSet).not.toHaveBeenCalled();
  });

  it('follows OS appearance changes while on system mode', () => {
    themeStore.init();
    const handler = mockAddChangeListener.mock.calls[0][0] as (p: {
      colorScheme: 'light' | 'dark' | null;
    }) => void;

    handler({ colorScheme: 'light' });
    expect(themeStore.getSnapshot()).toBe('light');

    handler({ colorScheme: 'dark' });
    expect(themeStore.getSnapshot()).toBe('dark');
  });

  it('does not follow the OS once the user pins a mode', () => {
    themeStore.setMode('dark');
    themeStore.init();
    const handler = mockAddChangeListener.mock.calls[0][0] as (p: {
      colorScheme: 'light' | 'dark' | null;
    }) => void;

    handler({ colorScheme: 'light' });
    expect(themeStore.getSnapshot()).toBe('dark');
  });

  it('init() restores a persisted preference over the OS scheme', async () => {
    mockGetColorScheme.mockReturnValue('dark');
    mockStorageGet.mockResolvedValue('light');

    themeStore.init();
    await flush();

    expect(themeStore.getMode()).toBe('light');
    expect(themeStore.getSnapshot()).toBe('light');
  });
});
