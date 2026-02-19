// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for api-schemas (Zod schemas)
 */

import { describe, it, expect } from 'vitest';
import {
  apiResponseSchema,
  userSchema,
  loginSuccessResponseSchema,
  twoFactorRequiredSchema,
  loginResponseSchema,
  paginationMetaSchema,
  paginatedResponseSchema,
  listingSchema,
  tenantBootstrapSchema,
  walletBalanceSchema,
  transactionSchema,
} from './api-schemas';

describe('api-schemas', () => {
  describe('apiResponseSchema', () => {
    it('validates a success response', () => {
      const data = { success: true, data: { id: 1 } };
      expect(apiResponseSchema.safeParse(data).success).toBe(true);
    });

    it('validates an error response', () => {
      const data = { success: false, error: 'Not found', code: 'HTTP_404' };
      expect(apiResponseSchema.safeParse(data).success).toBe(true);
    });

    it('validates response with meta', () => {
      const data = { success: true, meta: { per_page: 20, has_more: true } };
      expect(apiResponseSchema.safeParse(data).success).toBe(true);
    });

    it('rejects missing success field', () => {
      const data = { data: {} };
      expect(apiResponseSchema.safeParse(data).success).toBe(false);
    });
  });

  describe('userSchema', () => {
    it('validates a minimal user (just id)', () => {
      const data = { id: 1 };
      expect(userSchema.safeParse(data).success).toBe(true);
    });

    it('validates a full user', () => {
      const data = {
        id: 1,
        email: 'test@example.com',
        first_name: 'John',
        last_name: 'Doe',
        role: 'member',
        status: 'active',
        tenant_id: 2,
        balance: 10.5,
        onboarding_completed: true,
      };
      expect(userSchema.safeParse(data).success).toBe(true);
    });

    it('rejects non-numeric id', () => {
      const data = { id: 'abc' };
      expect(userSchema.safeParse(data).success).toBe(false);
    });

    it('allows extra fields via passthrough', () => {
      const data = { id: 1, custom_field: 'value' };
      const result = userSchema.safeParse(data);
      expect(result.success).toBe(true);
    });
  });

  describe('loginSuccessResponseSchema', () => {
    it('validates a login success response', () => {
      const data = {
        success: true,
        user: { id: 1 },
        access_token: 'jwt-token',
        refresh_token: 'refresh-token',
        token: 'jwt-token',
        expires_in: 3600,
      };
      expect(loginSuccessResponseSchema.safeParse(data).success).toBe(true);
    });

    it('validates with minimal fields', () => {
      const data = { user: { id: 1 } };
      expect(loginSuccessResponseSchema.safeParse(data).success).toBe(true);
    });
  });

  describe('twoFactorRequiredSchema', () => {
    it('validates a 2FA required response', () => {
      const data = {
        requires_2fa: true,
        two_factor_token: 'token-123',
        methods: ['totp', 'backup_code'],
      };
      expect(twoFactorRequiredSchema.safeParse(data).success).toBe(true);
    });

    it('rejects when requires_2fa is not true', () => {
      const data = {
        requires_2fa: false,
        two_factor_token: 'token',
        methods: [],
      };
      expect(twoFactorRequiredSchema.safeParse(data).success).toBe(false);
    });
  });

  describe('loginResponseSchema', () => {
    it('validates a success login', () => {
      const data = { user: { id: 1 }, access_token: 'tok' };
      expect(loginResponseSchema.safeParse(data).success).toBe(true);
    });

    it('validates a 2FA response', () => {
      const data = {
        requires_2fa: true,
        two_factor_token: 'tok',
        methods: ['totp'],
      };
      expect(loginResponseSchema.safeParse(data).success).toBe(true);
    });
  });

  describe('paginationMetaSchema', () => {
    it('validates pagination meta', () => {
      const data = { per_page: 20, has_more: true, current_page: 1, total: 100 };
      expect(paginationMetaSchema.safeParse(data).success).toBe(true);
    });

    it('rejects missing per_page', () => {
      const data = { has_more: true };
      expect(paginationMetaSchema.safeParse(data).success).toBe(false);
    });
  });

  describe('paginatedResponseSchema', () => {
    it('validates a paginated listing response', () => {
      const schema = paginatedResponseSchema(listingSchema);
      const data = {
        data: [
          {
            id: 1,
            title: 'Test',
            description: 'Desc',
            type: 'offer',
            user_id: 1,
            created_at: '2026-01-01',
            updated_at: '2026-01-01',
          },
        ],
        meta: { per_page: 20 },
      };
      expect(schema.safeParse(data).success).toBe(true);
    });

    it('rejects when data is not an array', () => {
      const schema = paginatedResponseSchema(listingSchema);
      const data = { data: 'not an array', meta: { per_page: 20 } };
      expect(schema.safeParse(data).success).toBe(false);
    });
  });

  describe('listingSchema', () => {
    it('validates a listing', () => {
      const data = {
        id: 1,
        title: 'Dog Walking',
        description: 'Will walk your dog',
        type: 'offer',
        user_id: 5,
        created_at: '2026-01-01T00:00:00',
        updated_at: '2026-01-01T00:00:00',
      };
      expect(listingSchema.safeParse(data).success).toBe(true);
    });

    it('rejects invalid type', () => {
      const data = {
        id: 1,
        title: 'Test',
        description: 'Desc',
        type: 'invalid',
        user_id: 1,
        created_at: '2026-01-01',
        updated_at: '2026-01-01',
      };
      expect(listingSchema.safeParse(data).success).toBe(false);
    });
  });

  describe('tenantBootstrapSchema', () => {
    it('validates a tenant bootstrap response', () => {
      const data = {
        id: 2,
        name: 'hOUR Timebank',
        slug: 'hour-timebank',
        tagline: 'Community Time Exchange',
        features: { gamification: true, events: true },
        modules: { feed: true, wallet: true },
        branding: {
          name: 'hOUR',
          primaryColor: '#6366f1',
        },
      };
      expect(tenantBootstrapSchema.safeParse(data).success).toBe(true);
    });

    it('validates minimal bootstrap (just id, name, slug)', () => {
      const data = { id: 1, name: 'Test', slug: 'test' };
      expect(tenantBootstrapSchema.safeParse(data).success).toBe(true);
    });

    it('rejects missing id', () => {
      const data = { name: 'Test', slug: 'test' };
      expect(tenantBootstrapSchema.safeParse(data).success).toBe(false);
    });

    it('rejects missing slug', () => {
      const data = { id: 1, name: 'Test' };
      expect(tenantBootstrapSchema.safeParse(data).success).toBe(false);
    });
  });

  describe('walletBalanceSchema', () => {
    it('validates a wallet balance', () => {
      const data = { balance: 10.5, total_earned: 20, total_spent: 9.5 };
      expect(walletBalanceSchema.safeParse(data).success).toBe(true);
    });

    it('validates with optional currency', () => {
      const data = { balance: 0, total_earned: 0, total_spent: 0, currency: 'hours' };
      expect(walletBalanceSchema.safeParse(data).success).toBe(true);
    });
  });

  describe('transactionSchema', () => {
    it('validates a transaction', () => {
      const data = {
        id: 1,
        type: 'credit',
        amount: 2,
        status: 'completed',
        description: 'Dog walking',
        created_at: '2026-01-01',
      };
      expect(transactionSchema.safeParse(data).success).toBe(true);
    });

    it('rejects invalid transaction type', () => {
      const data = {
        id: 1,
        type: 'unknown',
        amount: 2,
        status: 'completed',
        description: 'Test',
        created_at: '2026-01-01',
      };
      expect(transactionSchema.safeParse(data).success).toBe(false);
    });

    it('rejects invalid status', () => {
      const data = {
        id: 1,
        type: 'credit',
        amount: 2,
        status: 'unknown',
        description: 'Test',
        created_at: '2026-01-01',
      };
      expect(transactionSchema.safeParse(data).success).toBe(false);
    });
  });
});
