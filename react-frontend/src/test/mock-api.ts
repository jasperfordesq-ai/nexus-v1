// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Shared mock factory for @/lib/api
 *
 * Replaces the vi.mock('@/lib/api', ...) block duplicated across test files.
 * Usage:
 *
 *   import { createMockApi } from '@/test/mock-api';
 *   vi.mock('@/lib/api', () => createMockApi());
 *
 * Override individual methods or add default responses:
 *
 *   vi.mock('@/lib/api', () => createMockApi({
 *     api: { get: vi.fn().mockResolvedValue({ success: true, data: [] }) },
 *   }));
 */

import { vi } from 'vitest';

interface MockApiOverrides {
  api?: Partial<{
    get: ReturnType<typeof vi.fn>;
    post: ReturnType<typeof vi.fn>;
    put: ReturnType<typeof vi.fn>;
    patch: ReturnType<typeof vi.fn>;
    delete: ReturnType<typeof vi.fn>;
  }>;
  tokenManager?: Partial<{
    getAccessToken: ReturnType<typeof vi.fn>;
    setAccessToken: ReturnType<typeof vi.fn>;
    getRefreshToken: ReturnType<typeof vi.fn>;
    setRefreshToken: ReturnType<typeof vi.fn>;
    getTenantId: ReturnType<typeof vi.fn>;
    setTenantId: ReturnType<typeof vi.fn>;
    clearTokens: ReturnType<typeof vi.fn>;
  }>;
}

/**
 * Creates a mock module object for vi.mock('@/lib/api', () => ...).
 */
export function createMockApi(overrides: MockApiOverrides = {}) {
  return {
    api: {
      get: vi.fn(),
      post: vi.fn(),
      put: vi.fn(),
      patch: vi.fn(),
      delete: vi.fn(),
      ...overrides.api,
    },
    tokenManager: {
      getAccessToken: vi.fn(),
      setAccessToken: vi.fn(),
      getRefreshToken: vi.fn(),
      setRefreshToken: vi.fn(),
      getTenantId: vi.fn(),
      setTenantId: vi.fn(),
      clearTokens: vi.fn(),
      ...overrides.tokenManager,
    },
    default: {
      get: vi.fn(),
      post: vi.fn(),
      put: vi.fn(),
      patch: vi.fn(),
      delete: vi.fn(),
      ...overrides.api,
    },
  };
}
