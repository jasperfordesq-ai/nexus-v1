// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for api-validation utilities
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { z } from 'zod';
import { validateResponse, validateResponseIfPresent } from './api-validation';

describe('api-validation', () => {
  let consoleGroupCollapsedSpy: ReturnType<typeof vi.spyOn>;
  let consoleWarnSpy: ReturnType<typeof vi.spyOn>;
  let consoleGroupEndSpy: ReturnType<typeof vi.spyOn>;

  beforeEach(() => {
    consoleGroupCollapsedSpy = vi.spyOn(console, 'groupCollapsed').mockImplementation(() => {});
    consoleWarnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {});
    consoleGroupEndSpy = vi.spyOn(console, 'groupEnd').mockImplementation(() => {});
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  const testSchema = z.object({
    id: z.number(),
    name: z.string(),
  });

  describe('validateResponse', () => {
    it('returns data as-is when validation passes', () => {
      const data = { id: 1, name: 'Test' };
      const result = validateResponse(testSchema, data, 'test endpoint');
      expect(result).toBe(data);
    });

    it('returns data as-is when validation fails (diagnostic only)', () => {
      const data = { id: 'not-a-number', name: 123 };
      const result = validateResponse(testSchema, data, 'test endpoint');
      expect(result).toBe(data);
    });

    it('logs warning on validation failure in dev mode', () => {
      const data = { id: 'bad', name: 123 };
      validateResponse(testSchema, data, 'GET /api/test');

      expect(consoleGroupCollapsedSpy).toHaveBeenCalledWith(
        expect.stringContaining('GET /api/test'),
        expect.any(String)
      );
      expect(consoleWarnSpy).toHaveBeenCalled();
      expect(consoleGroupEndSpy).toHaveBeenCalled();
    });

    it('does not log warning when validation passes', () => {
      const data = { id: 1, name: 'Test' };
      validateResponse(testSchema, data, 'test');

      expect(consoleGroupCollapsedSpy).not.toHaveBeenCalled();
    });

    it('handles extra fields gracefully with passthrough schema', () => {
      const passthroughSchema = z.object({ id: z.number() }).passthrough();
      const data = { id: 1, extra: 'field' };
      const result = validateResponse(passthroughSchema, data, 'test');
      expect(result).toBe(data);
      expect(consoleGroupCollapsedSpy).not.toHaveBeenCalled();
    });
  });

  describe('validateResponseIfPresent', () => {
    it('returns null as-is without validation', () => {
      const result = validateResponseIfPresent(testSchema, null, 'test');
      expect(result).toBeNull();
      expect(consoleGroupCollapsedSpy).not.toHaveBeenCalled();
    });

    it('returns undefined as-is without validation', () => {
      const result = validateResponseIfPresent(testSchema, undefined, 'test');
      expect(result).toBeUndefined();
      expect(consoleGroupCollapsedSpy).not.toHaveBeenCalled();
    });

    it('validates non-null data', () => {
      const data = { id: 1, name: 'Test' };
      const result = validateResponseIfPresent(testSchema, data, 'test');
      expect(result).toBe(data);
    });

    it('logs warning for invalid non-null data', () => {
      const data = { id: 'bad' };
      validateResponseIfPresent(testSchema, data, 'test endpoint');
      expect(consoleGroupCollapsedSpy).toHaveBeenCalled();
    });
  });
});
