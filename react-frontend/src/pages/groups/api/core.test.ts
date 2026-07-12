// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it } from 'vitest';
import type { ApiResponse } from '@/lib/api';
import {
  GROUP_API_MESSAGE_KEYS,
  GroupApiError,
  normalizeGroupApiError,
  unwrapGroupResponse,
} from './core';

function captureError(run: () => unknown): GroupApiError {
  try {
    run();
  } catch (error) {
    expect(error).toBeInstanceOf(GroupApiError);
    return error as GroupApiError;
  }
  throw new Error('Expected operation to throw');
}

describe('unwrapGroupResponse', () => {
  it('unwraps ordinary object, text, blob and void success payloads', () => {
    const object = { id: 7, name: 'Group' };
    const blob = new Blob(['csv'], { type: 'text/csv' });

    expect(unwrapGroupResponse({ success: true, data: object })).toBe(object);
    expect(unwrapGroupResponse({ success: true, data: 'export-data' })).toBe('export-data');
    expect(unwrapGroupResponse({ success: true, data: blob })).toBe(blob);
    expect(unwrapGroupResponse<void>({ success: true })).toBeUndefined();
  });

  it('turns a resolved success:false response into a translated domain error', () => {
    const error = captureError(() => unwrapGroupResponse({
      success: false,
      error: 'Raw backend text must not become display copy',
      code: 'REQUEST_FAILED',
    }));

    expect(error.code).toBe('REQUEST_FAILED');
    expect(error.sourceCode).toBe('REQUEST_FAILED');
    expect(error.messageKey).toBe(GROUP_API_MESSAGE_KEYS.requestFailed);
    expect(error.message).toBe(GROUP_API_MESSAGE_KEYS.requestFailed);
    expect(error.message).not.toContain('Raw backend text');
    expect(error.retryable).toBe(false);
  });

  it.each([
    ['HTTP_401', 401, 'AUTHENTICATION_REQUIRED', GROUP_API_MESSAGE_KEYS.authenticationRequired, false],
    ['HTTP_403', 403, 'FORBIDDEN', GROUP_API_MESSAGE_KEYS.forbidden, false],
    ['HTTP_404', 404, 'NOT_FOUND', GROUP_API_MESSAGE_KEYS.notFound, false],
    ['HTTP_409', 409, 'CONFLICT', GROUP_API_MESSAGE_KEYS.conflict, false],
    ['HTTP_422', 422, 'VALIDATION_FAILED', GROUP_API_MESSAGE_KEYS.validation, false],
    ['HTTP_500', 500, 'SERVER_ERROR', GROUP_API_MESSAGE_KEYS.server, true],
  ] as const)(
    'normalizes %s to a stable status/code/message key',
    (sourceCode, status, code, messageKey, retryable) => {
      const error = captureError(() => unwrapGroupResponse({ success: false, code: sourceCode }));

      expect(error).toMatchObject({ status, code, messageKey, retryable });
    },
  );

  it('normalizes 422 field errors without losing multiple messages per field', () => {
    const response: ApiResponse<never> = {
      success: false,
      code: 'VALIDATION_ERROR',
      errors: [
        { field: 'title', message: 'The title field is required.' },
        { field: 'title', message: 'The title is too short.' },
        { field: 'ends_at', message: 'The end date is invalid.' },
      ],
    };

    const error = captureError(() => unwrapGroupResponse(response));

    expect(error.code).toBe('VALIDATION_FAILED');
    expect(error.status).toBe(422);
    expect(error.fieldErrors).toEqual({
      title: ['The title field is required.', 'The title is too short.'],
      ends_at: ['The end date is invalid.'],
    });
  });

  it('accepts Laravel field_errors maps and retry-after message parameters', () => {
    const error = captureError(() => unwrapGroupResponse({
      success: false,
      status: 429,
      code: 'RATE_LIMITED',
      error: {
        details: {
          field_errors: { email: ['Invalid address'] },
          retry_after_seconds: 30,
        },
      },
    }));

    expect(error.code).toBe('RATE_LIMITED');
    expect(error.retryable).toBe(true);
    expect(error.messageKey).toBe(GROUP_API_MESSAGE_KEYS.rateLimited);
    expect(error.messageParams).toEqual({ retryAfterSeconds: 30 });
    expect(error.fieldErrors).toEqual({ email: ['Invalid address'] });
  });

  it('uses a caller-supplied domain message key and parameters without raw display copy', () => {
    const error = captureError(() => unwrapGroupResponse(
      { success: false, code: 'HTTP_404', error: 'Challenge missing' },
      {
        messageKey: GROUP_API_MESSAGE_KEYS.notFound,
        messageParams: { resource: 'challenge' },
      },
    ));

    expect(error.messageKey).toBe(GROUP_API_MESSAGE_KEYS.notFound);
    expect(error.messageParams).toEqual({ resource: 'challenge' });
    expect(error.message).toBe(GROUP_API_MESSAGE_KEYS.notFound);
  });

  it('rejects malformed envelopes as retryable invalid responses', () => {
    const error = captureError(() => unwrapGroupResponse(
      { data: { id: 1 } } as unknown as ApiResponse<{ id: number }>,
    ));

    expect(error).toMatchObject({
      code: 'INVALID_RESPONSE',
      sourceCode: 'INVALID_RESPONSE',
      messageKey: GROUP_API_MESSAGE_KEYS.invalidResponse,
      retryable: true,
    });
  });
});

describe('normalizeGroupApiError', () => {
  it('normalizes thrown network failures as retryable without exposing the message', () => {
    const error = normalizeGroupApiError(new TypeError('Failed to fetch private data'));

    expect(error).toMatchObject({
      code: 'NETWORK_ERROR',
      sourceCode: 'NETWORK_ERROR',
      status: null,
      messageKey: GROUP_API_MESSAGE_KEYS.network,
      retryable: true,
    });
    expect(error.message).toBe(GROUP_API_MESSAGE_KEYS.network);
    expect(error.message).not.toContain('private data');
  });

  it.each([
    [{ name: 'TimeoutError' }, 'TIMEOUT', GROUP_API_MESSAGE_KEYS.timeout, true],
    [{ code: 'UPLOAD_TIMEOUT' }, 'TIMEOUT', GROUP_API_MESSAGE_KEYS.timeout, true],
    [{ name: 'AbortError' }, 'CANCELLED', GROUP_API_MESSAGE_KEYS.cancelled, false],
    [{ code: 'CANCELLED' }, 'CANCELLED', GROUP_API_MESSAGE_KEYS.cancelled, false],
  ] as const)('normalizes timeout and cancellation control flow', (input, code, messageKey, retryable) => {
    const error = normalizeGroupApiError(input);

    expect(error).toMatchObject({ code, messageKey, retryable });
    expect(error.isCancellation).toBe(code === 'CANCELLED');
  });

  it('uses an explicit HTTP context when the global client omitted status', () => {
    const error = normalizeGroupApiError(
      { code: 'REQUEST_FAILED' },
      { status: 503 },
    );

    expect(error).toMatchObject({
      code: 'SERVER_ERROR',
      status: 503,
      messageKey: GROUP_API_MESSAGE_KEYS.server,
      retryable: true,
    });
  });

  it('returns an existing GroupApiError unchanged', () => {
    const first = normalizeGroupApiError({ code: 'HTTP_403' });
    expect(normalizeGroupApiError(first)).toBe(first);
  });
});
