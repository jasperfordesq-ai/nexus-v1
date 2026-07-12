// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { ApiErrorDetail, ApiResponse } from '@/lib/api';

export const GROUP_API_MESSAGE_KEYS = {
  authenticationRequired: 'api_errors.authentication_required',
  forbidden: 'api_errors.forbidden',
  notFound: 'api_errors.not_found',
  conflict: 'api_errors.conflict',
  validation: 'api_errors.validation',
  rateLimited: 'api_errors.rate_limited',
  timeout: 'api_errors.timeout',
  cancelled: 'api_errors.cancelled',
  network: 'api_errors.network',
  server: 'api_errors.server',
  invalidResponse: 'api_errors.invalid_response',
  requestFailed: 'api_errors.request_failed',
} as const;

export type GroupApiMessageKey = typeof GROUP_API_MESSAGE_KEYS[keyof typeof GROUP_API_MESSAGE_KEYS];

export type GroupApiErrorCode =
  | 'AUTHENTICATION_REQUIRED'
  | 'FORBIDDEN'
  | 'NOT_FOUND'
  | 'CONFLICT'
  | 'VALIDATION_FAILED'
  | 'RATE_LIMITED'
  | 'TIMEOUT'
  | 'CANCELLED'
  | 'NETWORK_ERROR'
  | 'SERVER_ERROR'
  | 'INVALID_RESPONSE'
  | 'REQUEST_FAILED';

export type GroupApiMessageParam = string | number | boolean;
export type GroupApiMessageParams = Readonly<Record<string, GroupApiMessageParam>>;
export type GroupApiFieldErrors = Readonly<Record<string, readonly string[]>>;

type GroupApiFieldErrorInput = Record<string, string | readonly string[]>;

/**
 * The shared client normally returns ApiResponse<T>. The additional optional
 * fields keep the adapter compatible with tests, future transports, and raw
 * Laravel validation envelopes without weakening the global client contract.
 */
export type GroupApiResponse<T> = Omit<ApiResponse<T>, 'error' | 'errors'> & {
  error?: unknown;
  errors?: ApiErrorDetail[] | GroupApiFieldErrorInput;
  field_errors?: GroupApiFieldErrorInput;
  status?: number;
};

export interface GroupApiErrorContext {
  status?: number;
  messageKey?: GroupApiMessageKey;
  messageParams?: GroupApiMessageParams;
}

interface GroupApiErrorOptions {
  code: GroupApiErrorCode;
  sourceCode: string;
  status: number | null;
  messageKey: GroupApiMessageKey;
  messageParams?: GroupApiMessageParams;
  fieldErrors?: GroupApiFieldErrors;
  retryable: boolean;
  cause?: unknown;
}

/**
 * Domain-safe error for every Groups adapter. `message` intentionally contains
 * the translation key, never an arbitrary backend/transport message.
 */
export class GroupApiError extends Error {
  readonly code: GroupApiErrorCode;
  readonly sourceCode: string;
  readonly status: number | null;
  readonly messageKey: GroupApiMessageKey;
  readonly messageParams?: GroupApiMessageParams;
  readonly fieldErrors?: GroupApiFieldErrors;
  readonly retryable: boolean;
  readonly cause?: unknown;

  constructor(options: GroupApiErrorOptions) {
    super(options.messageKey);
    this.name = 'GroupApiError';
    this.code = options.code;
    this.sourceCode = options.sourceCode;
    this.status = options.status;
    this.messageKey = options.messageKey;
    this.messageParams = options.messageParams;
    this.fieldErrors = options.fieldErrors;
    this.retryable = options.retryable;
    this.cause = options.cause;
    Object.setPrototypeOf(this, GroupApiError.prototype);
  }

  get isCancellation(): boolean {
    return this.code === 'CANCELLED';
  }
}

export type GroupApiRequestState<T> =
  | { status: 'idle' }
  | { status: 'loading' }
  | { status: 'ready'; data: T }
  | { status: 'empty' }
  | { status: 'error'; error: GroupApiError };

type UnknownRecord = Record<string, unknown>;

const CANCELLED_CODES = new Set(['ABORTED', 'CANCELED', 'CANCELLED', 'ERR_CANCELED', 'UPLOAD_ABORTED']);
const TIMEOUT_CODES = new Set(['ETIMEDOUT', 'REQUEST_TIMEOUT', 'TIMEOUT', 'UPLOAD_TIMEOUT']);
const NETWORK_CODES = new Set(['ERR_NETWORK', 'FETCH_ERROR', 'NETWORK_ERROR']);
const AUTHENTICATION_CODES = new Set([
  'AUTHENTICATION_REQUIRED',
  'INVALID_TOKEN',
  'SESSION_EXPIRED',
  'TOKEN_EXPIRED',
  'UNAUTHENTICATED',
  'UNAUTHORIZED',
]);
const FORBIDDEN_CODES = new Set(['ACCESS_DENIED', 'FORBIDDEN']);
const NOT_FOUND_CODES = new Set(['GROUP_NOT_FOUND', 'NOT_FOUND']);
const CONFLICT_CODES = new Set(['ALREADY_EXISTS', 'CONFLICT', 'DUPLICATE']);
const VALIDATION_CODES = new Set(['INVALID_INPUT', 'UNPROCESSABLE_ENTITY', 'VALIDATION_ERROR', 'VALIDATION_FAILED']);
const RATE_LIMIT_CODES = new Set(['RATE_LIMITED', 'TOO_MANY_REQUESTS']);
const INVALID_RESPONSE_CODES = new Set(['INVALID_RESPONSE', 'PARSE_ERROR']);
const SERVER_CODES = new Set([
  'INTERNAL_ERROR',
  'MAINTENANCE_MODE',
  'REFRESH_UNAVAILABLE',
  'SERVER_ERROR',
  'SERVICE_UNAVAILABLE',
]);

function asRecord(value: unknown): UnknownRecord | null {
  return typeof value === 'object' && value !== null ? value as UnknownRecord : null;
}

function readString(value: unknown): string | null {
  return typeof value === 'string' && value.trim() !== '' ? value.trim() : null;
}

function readStatus(value: unknown): number | null {
  if (typeof value !== 'number' || !Number.isInteger(value) || value < 100 || value > 599) {
    return null;
  }
  return value;
}

function normalizeSourceCode(value: unknown): string | null {
  const code = readString(value);
  return code ? code.toUpperCase().replace(/[\s-]+/g, '_') : null;
}

function inferStatus(sourceCode: string): number | null {
  const httpMatch = /^HTTP_(\d{3})$/.exec(sourceCode);
  if (httpMatch?.[1]) return readStatus(Number(httpMatch[1]));
  if (AUTHENTICATION_CODES.has(sourceCode)) return 401;
  if (FORBIDDEN_CODES.has(sourceCode)) return 403;
  if (NOT_FOUND_CODES.has(sourceCode)) return 404;
  if (CONFLICT_CODES.has(sourceCode)) return 409;
  if (VALIDATION_CODES.has(sourceCode)) return 422;
  if (RATE_LIMIT_CODES.has(sourceCode)) return 429;
  if (SERVER_CODES.has(sourceCode)) return 503;
  return null;
}

function appendFieldErrors(
  target: Record<string, string[]>,
  input: unknown,
): void {
  const record = asRecord(input);
  if (!record) return;

  for (const [field, value] of Object.entries(record)) {
    const messages = Array.isArray(value) ? value : [value];
    for (const message of messages) {
      const normalized = readString(message);
      if (!normalized) continue;
      const current = target[field] ?? [];
      if (!current.includes(normalized)) current.push(normalized);
      target[field] = current;
    }
  }
}

function extractFieldErrors(record: UnknownRecord | null): GroupApiFieldErrors | undefined {
  if (!record) return undefined;

  const collected: Record<string, string[]> = {};
  appendFieldErrors(collected, record.field_errors);

  const nestedError = asRecord(record.error);
  const details = asRecord(nestedError?.details) ?? asRecord(record.details);
  appendFieldErrors(collected, details?.field_errors);

  if (Array.isArray(record.errors)) {
    for (const item of record.errors) {
      const detail = asRecord(item);
      const field = readString(detail?.field);
      const message = readString(detail?.message);
      if (!field || !message) continue;
      const current = collected[field] ?? [];
      if (!current.includes(message)) current.push(message);
      collected[field] = current;
    }
  } else {
    appendFieldErrors(collected, record.errors);
  }

  if (Object.keys(collected).length === 0) return undefined;

  const frozen = Object.fromEntries(
    Object.entries(collected).map(([field, messages]) => [field, Object.freeze([...messages])]),
  );
  return Object.freeze(frozen);
}

function extractSourceCode(record: UnknownRecord | null): string {
  if (!record) return 'REQUEST_FAILED';

  const nestedError = asRecord(record.error);
  const firstError = Array.isArray(record.errors) ? asRecord(record.errors[0]) : null;
  const explicit = normalizeSourceCode(record.code)
    ?? normalizeSourceCode(nestedError?.code)
    ?? normalizeSourceCode(firstError?.code);
  if (explicit) return explicit;

  const name = normalizeSourceCode(record.name);
  if (name === 'ABORTERROR') return 'CANCELLED';
  if (name === 'TIMEOUTERROR') return 'TIMEOUT';
  if (name === 'TYPEERROR') return 'NETWORK_ERROR';
  return 'REQUEST_FAILED';
}

function extractRetryAfter(record: UnknownRecord | null): number | null {
  if (!record) return null;
  const nestedError = asRecord(record.error);
  const details = asRecord(nestedError?.details) ?? asRecord(record.details);
  const value = details?.retry_after_seconds ?? record.retry_after_seconds;
  return typeof value === 'number' && Number.isFinite(value) && value >= 0 ? value : null;
}

function classifyError(sourceCode: string, status: number | null): {
  code: GroupApiErrorCode;
  messageKey: GroupApiMessageKey;
  retryable: boolean;
} {
  if (CANCELLED_CODES.has(sourceCode)) {
    return { code: 'CANCELLED', messageKey: GROUP_API_MESSAGE_KEYS.cancelled, retryable: false };
  }
  if (TIMEOUT_CODES.has(sourceCode) || status === 408) {
    return { code: 'TIMEOUT', messageKey: GROUP_API_MESSAGE_KEYS.timeout, retryable: true };
  }
  if (NETWORK_CODES.has(sourceCode)) {
    return { code: 'NETWORK_ERROR', messageKey: GROUP_API_MESSAGE_KEYS.network, retryable: true };
  }
  if (status === 401 || AUTHENTICATION_CODES.has(sourceCode)) {
    return { code: 'AUTHENTICATION_REQUIRED', messageKey: GROUP_API_MESSAGE_KEYS.authenticationRequired, retryable: false };
  }
  if (status === 403 || FORBIDDEN_CODES.has(sourceCode)) {
    return { code: 'FORBIDDEN', messageKey: GROUP_API_MESSAGE_KEYS.forbidden, retryable: false };
  }
  if (status === 404 || NOT_FOUND_CODES.has(sourceCode)) {
    return { code: 'NOT_FOUND', messageKey: GROUP_API_MESSAGE_KEYS.notFound, retryable: false };
  }
  if (status === 409 || CONFLICT_CODES.has(sourceCode)) {
    return { code: 'CONFLICT', messageKey: GROUP_API_MESSAGE_KEYS.conflict, retryable: false };
  }
  if (status === 422 || VALIDATION_CODES.has(sourceCode)) {
    return { code: 'VALIDATION_FAILED', messageKey: GROUP_API_MESSAGE_KEYS.validation, retryable: false };
  }
  if (status === 429 || RATE_LIMIT_CODES.has(sourceCode)) {
    return { code: 'RATE_LIMITED', messageKey: GROUP_API_MESSAGE_KEYS.rateLimited, retryable: true };
  }
  if (INVALID_RESPONSE_CODES.has(sourceCode)) {
    return { code: 'INVALID_RESPONSE', messageKey: GROUP_API_MESSAGE_KEYS.invalidResponse, retryable: true };
  }
  if ((status !== null && status >= 500) || SERVER_CODES.has(sourceCode)) {
    return { code: 'SERVER_ERROR', messageKey: GROUP_API_MESSAGE_KEYS.server, retryable: true };
  }
  return { code: 'REQUEST_FAILED', messageKey: GROUP_API_MESSAGE_KEYS.requestFailed, retryable: false };
}

/** Convert resolved API failures and thrown transport errors to one stable shape. */
export function normalizeGroupApiError(
  error: unknown,
  context: GroupApiErrorContext = {},
): GroupApiError {
  if (error instanceof GroupApiError) return error;

  const record = asRecord(error);
  const sourceCode = extractSourceCode(record);
  const nestedResponse = asRecord(record?.response);
  const status = readStatus(context.status)
    ?? readStatus(record?.status)
    ?? readStatus(nestedResponse?.status)
    ?? inferStatus(sourceCode);
  const classification = classifyError(sourceCode, status);
  const retryAfterSeconds = extractRetryAfter(record);
  const messageParams = {
    ...(retryAfterSeconds === null ? {} : { retryAfterSeconds }),
    ...context.messageParams,
  };

  return new GroupApiError({
    code: classification.code,
    sourceCode,
    status,
    messageKey: context.messageKey ?? classification.messageKey,
    messageParams: Object.keys(messageParams).length > 0 ? Object.freeze(messageParams) : undefined,
    fieldErrors: extractFieldErrors(record),
    retryable: classification.retryable,
    cause: error,
  });
}

/**
 * Unwrap the existing client's response contract. Failures are always thrown as
 * GroupApiError, including HTTP-200 envelopes with `success: false`.
 */
export function unwrapGroupResponse<T>(
  response: GroupApiResponse<T>,
  context: GroupApiErrorContext = {},
): T {
  const record = asRecord(response);
  if (!record || typeof record.success !== 'boolean') {
    throw normalizeGroupApiError({ code: 'INVALID_RESPONSE' }, context);
  }
  if (record.success === false) {
    throw normalizeGroupApiError(response, context);
  }
  return record.data as T;
}
