// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

// Mock transitive deps so auth.ts module can load
jest.mock('@/lib/api/client', () => ({
  api: { get: jest.fn(), post: jest.fn() },
  ApiResponseError: class ApiResponseError extends Error {
    status!: number;
    constructor(status: number, message: string) { super(message); this.status = status; this.name = 'ApiResponseError'; }
  },
  registerUnauthorizedCallback: jest.fn(),
}));
jest.mock('@/lib/constants', () => ({
  API_V2: '/api/v2',
  API_BASE_URL: 'https://test.api',
  STORAGE_KEYS: {
    AUTH_TOKEN: 'auth_token',
    REFRESH_TOKEN: 'refresh_token',
    TENANT_SLUG: 'tenant_slug',
    USER_DATA: 'user_data',
  },
  TIMEOUTS: { API_REQUEST: 15_000 },
  DEFAULT_TENANT: 'test-tenant',
}));

import { extractToken, buildDisplayName } from './auth';
import type { AuthResponse, LoginUser } from './auth';

const baseResponse: AuthResponse = {
  success: true,
  access_token: '',
  refresh_token: 'ref_token',
  token_type: 'Bearer',
  expires_in: 3600,
  user: {
    id: 1,
    first_name: 'Jane',
    last_name: 'Smith',
    email: 'jane@example.com',
    avatar_url: null,
    tenant_id: 1,
    role: 'member',
    is_admin: false,
    onboarding_completed: true,
  },
};

describe('extractToken', () => {
  it('returns access_token when present', () => {
    const response: AuthResponse = { ...baseResponse, access_token: 'tok_primary' };
    expect(extractToken(response)).toBe('tok_primary');
  });

  it('falls back to legacy token field when access_token is absent', () => {
    // The server may omit access_token; ?? only falls back on null/undefined
    const response = { ...baseResponse, access_token: undefined as unknown as string, token: 'tok_legacy' };
    expect(extractToken(response)).toBe('tok_legacy');
  });

  it('returns empty string when neither token field is set', () => {
    const response: AuthResponse = { ...baseResponse, access_token: '' };
    expect(extractToken(response)).toBe('');
  });
});

describe('buildDisplayName', () => {
  const base: LoginUser = {
    id: 1,
    first_name: null,
    last_name: null,
    email: 'jane@example.com',
    avatar_url: null,
    tenant_id: 1,
    role: 'member',
    is_admin: false,
    onboarding_completed: true,
  };

  it('returns full name when both parts are set', () => {
    expect(buildDisplayName({ ...base, first_name: 'Jane', last_name: 'Smith' })).toBe('Jane Smith');
  });

  it('returns first name only when last name is null', () => {
    expect(buildDisplayName({ ...base, first_name: 'Jane', last_name: null })).toBe('Jane');
  });

  it('returns last name only when first name is null', () => {
    expect(buildDisplayName({ ...base, first_name: null, last_name: 'Smith' })).toBe('Smith');
  });

  it('returns "Member" when both names are null', () => {
    expect(buildDisplayName({ ...base, first_name: null, last_name: null })).toBe('Member');
  });
});
