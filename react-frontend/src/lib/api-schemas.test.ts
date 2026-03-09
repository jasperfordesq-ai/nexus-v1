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
  transferResponseSchema,
  eventSchema,
  rsvpResponseSchema,
  groupSchema,
  feedPostSchema,
  messageSchema,
  conversationSchema,
  unreadCountSchema,
  exchangeSchema,
  notificationSchema,
  uploadResponseSchema,
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
  // API Contract Validation Tests
  // Representative payloads mirror PHP backend responses.
  // If a PHP controller changes response shape without updating TypeScript
  // types and Zod schemas, these tests fail and block the CI pipeline.

  describe('transferResponseSchema: POST /api/v2/wallet/transfer', () => {
    it('validates a successful transfer response', () => {
      const data = { transaction_id: 42, amount: 2, new_balance: 8,
        recipient: { id: 7, first_name: 'Alice', last_name: 'Murphy' }, message: 'Transfer successful' };
      expect(transferResponseSchema.safeParse(data).success).toBe(true);
    });
    it('rejects when transaction_id is missing', () => {
      const data = { amount: 2, new_balance: 8, recipient: { id: 7, first_name: 'A', last_name: 'M' }, message: 'OK' };
      expect(transferResponseSchema.safeParse(data).success).toBe(false);
    });
    it('rejects when recipient is missing last_name', () => {
      const data = { transaction_id: 1, amount: 1, new_balance: 9, recipient: { id: 7, first_name: 'Alice' }, message: 'OK' };
      expect(transferResponseSchema.safeParse(data).success).toBe(false);
    });
  });

  describe('eventSchema: GET /api/v2/events/:id', () => {
    it('validates a full event object', () => {
      const data = { id: 10, title: 'Community Cleanup', description: 'Join us',
        start_date: '2026-04-15', is_online: false,
        organizer: { id: 3, first_name: 'Bob', last_name: 'Smith' },
        attendees_count: 12, created_at: '2026-03-01T10:00:00' };
      expect(eventSchema.safeParse(data).success).toBe(true);
    });
    it('validates a minimal event (required fields only)', () => {
      const data = { id: 1, title: 'Test', description: 'A test', start_date: '2026-04-15',
        is_online: true, organizer: { id: 1, first_name: 'Jane', last_name: 'Doe' },
        attendees_count: 0, created_at: '2026-03-01T00:00:00' };
      expect(eventSchema.safeParse(data).success).toBe(true);
    });
    it('rejects when organizer is missing first_name', () => {
      const data = { id: 1, title: 'T', description: 'D', start_date: '2026-04-15', is_online: false,
        organizer: { id: 1, last_name: 'Doe' }, attendees_count: 0, created_at: '2026-03-01T00:00:00' };
      expect(eventSchema.safeParse(data).success).toBe(false);
    });
    it('rejects when is_online is not a boolean (catches PHP integer-to-bool drift)', () => {
      const data = { id: 1, title: 'T', description: 'D', start_date: '2026-04-15', is_online: 'yes',
        organizer: { id: 1, first_name: 'J', last_name: 'D' }, attendees_count: 0, created_at: '2026-01-01' };
      expect(eventSchema.safeParse(data).success).toBe(false);
    });
  });

  describe('rsvpResponseSchema: POST /api/v2/events/:id/rsvp', () => {
    it('validates attending', () => {
      expect(rsvpResponseSchema.safeParse({ status: 'attending', rsvp_counts: { going: 5, interested: 2 } }).success).toBe(true);
    });
    it('validates maybe', () => {
      expect(rsvpResponseSchema.safeParse({ status: 'maybe', rsvp_counts: { going: 3, interested: 4 } }).success).toBe(true);
    });
    it('validates not_attending', () => {
      expect(rsvpResponseSchema.safeParse({ status: 'not_attending', rsvp_counts: { going: 5, interested: 0 } }).success).toBe(true);
    });
    it('validates waitlisted with position', () => {
      expect(rsvpResponseSchema.safeParse({ status: 'waitlisted', rsvp_counts: { going: 5, interested: 2 }, waitlist_position: 3 }).success).toBe(true);
    });
    it('rejects unknown RSVP status', () => {
      expect(rsvpResponseSchema.safeParse({ status: 'yes_please', rsvp_counts: { going: 5, interested: 2 } }).success).toBe(false);
    });
    it('rejects when rsvp_counts is missing', () => {
      expect(rsvpResponseSchema.safeParse({ status: 'attending' }).success).toBe(false);
    });
  });

  describe('groupSchema: GET /api/v2/groups/:id', () => {
    const base = { id: 1, name: 'G', description: 'D', members_count: 0, created_at: '2026-01-01T00:00:00' };
    it('validates public', () => { expect(groupSchema.safeParse({ ...base, visibility: 'public' }).success).toBe(true); });
    it('validates private', () => { expect(groupSchema.safeParse({ ...base, visibility: 'private' }).success).toBe(true); });
    it('validates secret', () => { expect(groupSchema.safeParse({ ...base, visibility: 'secret' }).success).toBe(true); });
    it('rejects invalid visibility', () => { expect(groupSchema.safeParse({ ...base, visibility: 'open' }).success).toBe(false); });
    it('rejects missing members_count', () => {
      expect(groupSchema.safeParse({ id: 1, name: 'G', description: 'D', visibility: 'public', created_at: '2026-01-01' }).success).toBe(false);
    });
  });

  describe('feedPostSchema: GET /api/v2/feed', () => {
    it('validates a feed post', () => {
      const data = { id: 99, user_id: 3, content: 'Hello!', likes_count: 4, comments_count: 1,
        author: { id: 3, name: 'Alice B' }, created_at: '2026-03-09T08:00:00', updated_at: '2026-03-09T08:00:00' };
      expect(feedPostSchema.safeParse(data).success).toBe(true);
    });
    it('rejects when author is missing', () => {
      const data = { id: 1, user_id: 1, content: 'Hi', likes_count: 0, comments_count: 0,
        created_at: '2026-01-01T00:00:00', updated_at: '2026-01-01T00:00:00' };
      expect(feedPostSchema.safeParse(data).success).toBe(false);
    });
    it('rejects non-numeric likes_count', () => {
      const data = { id: 1, user_id: 1, content: 'Hi', likes_count: 'many', comments_count: 0,
        author: { id: 1, name: 'X' }, created_at: '2026-01-01T00:00:00', updated_at: '2026-01-01T00:00:00' };
      expect(feedPostSchema.safeParse(data).success).toBe(false);
    });
  });

  describe('messageSchema: GET /api/v2/conversations/:id/messages', () => {
    it('validates a message with body field', () => {
      const data = { id: 55, body: 'Can you help Tuesday?', sender_id: 3, is_own: false, created_at: '2026-03-09T09:00:00' };
      expect(messageSchema.safeParse(data).success).toBe(true);
    });
    it('rejects when only deprecated content alias is present (no body field)', () => {
      // body is the canonical PHP backend field - its absence signals backend schema drift
      const data = { id: 1, content: 'Hello', sender_id: 3, created_at: '2026-01-01' };
      expect(messageSchema.safeParse(data).success).toBe(false);
    });
  });

  describe('conversationSchema: GET /api/v2/conversations', () => {
    it('validates a conversation object', () => {
      const data = { id: 12, other_user: { id: 7, name: 'Carol D', avatar_url: null }, unread_count: 3 };
      expect(conversationSchema.safeParse(data).success).toBe(true);
    });
    it('rejects when other_user.name is missing', () => {
      const data = { id: 1, other_user: { id: 7 }, unread_count: 0 };
      expect(conversationSchema.safeParse(data).success).toBe(false);
    });
    it('rejects when unread_count is missing', () => {
      const data = { id: 1, other_user: { id: 7, name: 'Carol' } };
      expect(conversationSchema.safeParse(data).success).toBe(false);
    });
  });

  describe('unreadCountSchema: GET /api/v2/messages/unread-count', () => {
    it('validates non-zero counts', () => {
      expect(unreadCountSchema.safeParse({ count: 5, conversations_with_unread: 2 }).success).toBe(true);
    });
    it('validates zero counts', () => {
      expect(unreadCountSchema.safeParse({ count: 0, conversations_with_unread: 0 }).success).toBe(true);
    });
    it('rejects when conversations_with_unread is missing', () => {
      expect(unreadCountSchema.safeParse({ count: 5 }).success).toBe(false);
    });
  });

  describe('exchangeSchema: GET /api/v2/exchanges/:id', () => {
    const base = { id: 1, listing_id: 1, requester_id: 1, provider_id: 2, proposed_hours: 1, created_at: '2026-01-01T00:00:00' };
    it('validates pending_provider', () => { expect(exchangeSchema.safeParse({ ...base, status: 'pending_provider' }).success).toBe(true); });
    it('validates accepted', () => { expect(exchangeSchema.safeParse({ ...base, status: 'accepted' }).success).toBe(true); });
    it('validates completed', () => { expect(exchangeSchema.safeParse({ ...base, status: 'completed' }).success).toBe(true); });
    it('validates disputed', () => { expect(exchangeSchema.safeParse({ ...base, status: 'disputed' }).success).toBe(true); });
    it('validates cancelled', () => { expect(exchangeSchema.safeParse({ ...base, status: 'cancelled' }).success).toBe(true); });
    it('rejects unknown exchange status', () => {
      expect(exchangeSchema.safeParse({ ...base, status: 'unknown_status' }).success).toBe(false);
    });
    it('rejects when proposed_hours is missing', () => {
      expect(exchangeSchema.safeParse({ id: 1, listing_id: 1, requester_id: 1, provider_id: 2, status: 'accepted', created_at: '2026-01-01T00:00:00' }).success).toBe(false);
    });
  });

  describe('notificationSchema: GET /api/v2/notifications', () => {
    it('validates a notification object', () => {
      const data = { id: 201, type: 'exchange_accepted', title: 'Exchange Accepted', body: 'Bob accepted', read_at: null, created_at: '2026-03-09T07:00:00' };
      expect(notificationSchema.safeParse(data).success).toBe(true);
    });
    it('rejects when body is missing', () => {
      expect(notificationSchema.safeParse({ id: 1, type: 'test', title: 'Test', created_at: '2026-01-01' }).success).toBe(false);
    });
    it('rejects when title is missing', () => {
      expect(notificationSchema.safeParse({ id: 1, type: 'test', body: 'msg', created_at: '2026-01-01' }).success).toBe(false);
    });
  });

  describe('uploadResponseSchema: POST /api/v2/upload', () => {
    it('validates a successful upload response', () => {
      const data = { path: 'uploads/avatar.webp', url: 'https://api.project-nexus.ie/uploads/avatar.webp', mime_type: 'image/webp', size_bytes: 45678 };
      expect(uploadResponseSchema.safeParse(data).success).toBe(true);
    });
    it('rejects when url is missing', () => {
      expect(uploadResponseSchema.safeParse({ path: 'foo.png', mime_type: 'image/png', size_bytes: 1000 }).success).toBe(false);
    });
    it('rejects when size_bytes is not a number', () => {
      expect(uploadResponseSchema.safeParse({ path: 'foo.png', url: 'https://example.com/foo.png', mime_type: 'image/png', size_bytes: '1kb' }).success).toBe(false);
    });
  });

  describe('paginatedResponseSchema: collection contract checks', () => {
    it('validates a paginated events list', () => {
      const schema = paginatedResponseSchema(eventSchema);
      const data = {
        data: [{ id: 1, title: 'Party', description: 'Fun', start_date: '2026-05-01', is_online: false,
          organizer: { id: 1, first_name: 'A', last_name: 'B' }, attendees_count: 5, created_at: '2026-03-01T00:00:00' }],
        meta: { per_page: 20, has_more: false },
      };
      expect(schema.safeParse(data).success).toBe(true);
    });
    it('validates a paginated groups list', () => {
      const schema = paginatedResponseSchema(groupSchema);
      const data = {
        data: [{ id: 1, name: 'Knitters', description: 'Knitting group', visibility: 'public', members_count: 5, created_at: '2026-01-01T00:00:00' }],
        meta: { per_page: 20, has_more: true, cursor: 'abc123' },
      };
      expect(schema.safeParse(data).success).toBe(true);
    });
    it('validates a paginated exchange list', () => {
      const schema = paginatedResponseSchema(exchangeSchema);
      const data = {
        data: [{ id: 1, listing_id: 5, requester_id: 2, provider_id: 3, proposed_hours: 1, status: 'completed', created_at: '2026-02-01T00:00:00' }],
        meta: { per_page: 10, has_more: false },
      };
      expect(schema.safeParse(data).success).toBe(true);
    });
    it('validates a paginated notifications list', () => {
      const schema = paginatedResponseSchema(notificationSchema);
      const data = {
        data: [{ id: 1, type: 'message', title: 'New message', body: 'You have a message', created_at: '2026-01-01T00:00:00' }],
        meta: { per_page: 20, has_more: false },
      };
      expect(schema.safeParse(data).success).toBe(true);
    });
  });
});
