// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Test data factories
 *
 * Produce typed test objects with sensible defaults and spread overrides.
 * Usage:
 *
 *   import { createUser, createListing } from '@/test/factories';
 *   const admin = createUser({ role: 'admin', is_admin: true });
 *   const offer = createListing({ type: 'offer', title: 'Dog walking' });
 */

import type { User, Listing } from '@/types/api';

// ─────────────────────────────────────────────────────────────────────────────
// Base fixtures
// ─────────────────────────────────────────────────────────────────────────────

const BASE_USER: User = {
  id: 1,
  email: 'alice@example.com',
  name: 'Alice Test',
  first_name: 'Alice',
  last_name: 'Test',
  role: 'member',
  status: 'active',
  tenant_id: 2,
  balance: 10,
};

const BASE_LISTING: Listing = {
  id: 1,
  user_id: 1,
  title: 'Test Listing',
  description: 'A test listing for unit tests',
  type: 'offer',
  category_id: null,
  status: 'active',
  created_at: '2025-01-01T00:00:00Z',
  updated_at: '2025-01-01T00:00:00Z',
};

// ─────────────────────────────────────────────────────────────────────────────
// Factory functions
// ─────────────────────────────────────────────────────────────────────────────

/** Creates a User with sensible defaults. Spread any overrides. */
export function createUser(overrides: Partial<User> = {}): User {
  return { ...BASE_USER, ...overrides };
}

/** Creates a Listing with sensible defaults. Spread any overrides. */
export function createListing(overrides: Partial<Listing> = {}): Listing {
  return { ...BASE_LISTING, ...overrides };
}

// ─────────────────────────────────────────────────────────────────────────────
// Pre-built personas (optional convenience exports)
// ─────────────────────────────────────────────────────────────────────────────

/** Admin user for permission-gated tests */
export const ADMIN_USER = createUser({
  id: 99,
  email: 'admin@example.com',
  name: 'Admin User',
  first_name: 'Admin',
  last_name: 'User',
  role: 'admin',
  is_admin: true,
});

/** Unauthenticated / anonymous placeholder */
export const ANON_USER = createUser({
  id: 0,
  email: undefined,
  name: 'Guest',
  first_name: 'Guest',
  last_name: '',
  status: 'inactive',
});
