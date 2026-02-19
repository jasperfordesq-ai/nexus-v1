// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for exchange-status utilities
 */

import { describe, it, expect } from 'vitest';
import {
  EXCHANGE_STATUS_CONFIG,
  MAX_EXCHANGE_HOURS,
  getStatusIconBgClass,
} from './exchange-status';

describe('exchange-status', () => {
  describe('EXCHANGE_STATUS_CONFIG', () => {
    it('has config for all exchange statuses', () => {
      const expectedStatuses = [
        'pending_provider',
        'pending_broker',
        'accepted',
        'in_progress',
        'pending_confirmation',
        'completed',
        'disputed',
        'cancelled',
      ];

      for (const status of expectedStatuses) {
        expect(EXCHANGE_STATUS_CONFIG[status as keyof typeof EXCHANGE_STATUS_CONFIG]).toBeDefined();
      }
    });

    it('each config has required fields', () => {
      for (const [, config] of Object.entries(EXCHANGE_STATUS_CONFIG)) {
        expect(config.label).toBeTruthy();
        expect(config.color).toBeTruthy();
        expect(config.icon).toBeDefined();
        expect(config.description).toBeTruthy();
      }
    });

    it('completed status is success color', () => {
      expect(EXCHANGE_STATUS_CONFIG.completed.color).toBe('success');
    });

    it('cancelled status is default color', () => {
      expect(EXCHANGE_STATUS_CONFIG.cancelled.color).toBe('default');
    });

    it('disputed status is danger color', () => {
      expect(EXCHANGE_STATUS_CONFIG.disputed.color).toBe('danger');
    });
  });

  describe('MAX_EXCHANGE_HOURS', () => {
    it('is 100', () => {
      expect(MAX_EXCHANGE_HOURS).toBe(100);
    });
  });

  describe('getStatusIconBgClass', () => {
    it('returns correct classes for success', () => {
      const result = getStatusIconBgClass('success');
      expect(result).toContain('bg-emerald-500/20');
      expect(result).toContain('text-emerald-400');
    });

    it('returns correct classes for warning', () => {
      const result = getStatusIconBgClass('warning');
      expect(result).toContain('bg-amber-500/20');
      expect(result).toContain('text-amber-400');
    });

    it('returns correct classes for danger', () => {
      const result = getStatusIconBgClass('danger');
      expect(result).toContain('bg-red-500/20');
      expect(result).toContain('text-red-400');
    });

    it('returns correct classes for primary', () => {
      const result = getStatusIconBgClass('primary');
      expect(result).toContain('bg-indigo-500/20');
      expect(result).toContain('text-indigo-400');
    });

    it('returns correct classes for secondary', () => {
      const result = getStatusIconBgClass('secondary');
      expect(result).toContain('bg-purple-500/20');
      expect(result).toContain('text-purple-400');
    });

    it('returns default classes for default color', () => {
      const result = getStatusIconBgClass('default');
      expect(result).toContain('bg-theme-elevated');
      expect(result).toContain('text-theme-muted');
    });
  });
});
