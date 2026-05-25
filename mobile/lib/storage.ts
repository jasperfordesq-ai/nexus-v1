// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import * as SecureStore from 'expo-secure-store';
import * as Sentry from '@sentry/react-native';

/**
 * Secure key-value storage backed by expo-secure-store.
 * Values are encrypted at rest on both iOS (Keychain) and Android (Keystore).
 * All methods are async and return null on missing/error rather than throwing.
 */
export const storage = {
  async get(key: string): Promise<string | null> {
    try {
      return await SecureStore.getItemAsync(key);
    } catch {
      return null;
    }
  },

  async set(key: string, value: string): Promise<void> {
    try {
      await SecureStore.setItemAsync(key, value);
    } catch (err) {
      // Log to Sentry so "random logouts" can be diagnosed
      Sentry.captureException(err, { tags: { storage_op: 'set', key } });
    }
  },

  async remove(key: string): Promise<void> {
    try {
      await SecureStore.deleteItemAsync(key);
    } catch {
      // Already absent or unavailable — not an error
    }
  },

  /** Store a JSON-serialisable object */
  async setJson<T>(key: string, value: T): Promise<void> {
    await storage.set(key, JSON.stringify(value));
  },

  /** Retrieve and parse a previously stored JSON object */
  async getJson<T>(key: string): Promise<T | null> {
    const raw = await storage.get(key);
    if (!raw) return null;
    try {
      return JSON.parse(raw) as T;
    } catch {
      return null;
    }
  },
};
