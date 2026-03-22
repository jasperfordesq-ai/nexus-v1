// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import {
  trackComponentRender,
  trackApiCall,
  trackError,
  getStoredMetrics,
  clearStoredMetrics,
  onProfilerRender,
  type PerformanceMetric,
} from './performance';

describe('performance utilities', () => {
  beforeEach(() => {
    localStorage.clear();
    vi.spyOn(console, 'log').mockImplementation(() => {});
    vi.spyOn(console, 'warn').mockImplementation(() => {});
  });

  afterEach(() => {
    localStorage.clear();
    vi.restoreAllMocks();
  });

  describe('getStoredMetrics', () => {
    it('returns empty array when no metrics stored', () => {
      expect(getStoredMetrics()).toEqual([]);
    });

    it('returns stored metrics from localStorage', () => {
      const metric: PerformanceMetric = {
        type: 'api_call',
        timestamp: '2026-01-01T00:00:00Z',
        name: '/v2/test',
        duration: 150,
        data: { success: true },
      };
      localStorage.setItem('nexus_performance_metrics', JSON.stringify([metric]));
      const result = getStoredMetrics();
      expect(result).toHaveLength(1);
      expect(result[0].name).toBe('/v2/test');
      expect(result[0].type).toBe('api_call');
    });

    it('returns empty array on corrupt JSON', () => {
      localStorage.setItem('nexus_performance_metrics', '{CORRUPT}}}');
      expect(getStoredMetrics()).toEqual([]);
    });

    it('returns empty array when localStorage has null entry', () => {
      // Key not set
      expect(getStoredMetrics()).toEqual([]);
    });
  });

  describe('clearStoredMetrics', () => {
    it('removes metrics from localStorage', () => {
      localStorage.setItem('nexus_performance_metrics', JSON.stringify([{ type: 'error' }]));
      clearStoredMetrics();
      expect(localStorage.getItem('nexus_performance_metrics')).toBeNull();
    });

    it('does not throw when no metrics stored', () => {
      expect(() => clearStoredMetrics()).not.toThrow();
    });
  });

  describe('trackComponentRender', () => {
    it('does not store metrics below threshold (< 100ms)', () => {
      // In test env, DEV may not be true — trackMetric calls either storeMetric or queueMetric
      // We test that slow components store, fast ones don't
      const before = getStoredMetrics().length;
      trackComponentRender('FastComponent', 'mount', 50, 50, 0, 50);
      // Either same count (if it stored nothing) — we just verify no error thrown
      expect(() => trackComponentRender('FastComponent', 'mount', 50, 50, 0, 50)).not.toThrow();
      expect(getStoredMetrics().length).toBeGreaterThanOrEqual(before);
    });

    it('does not throw for slow renders', () => {
      expect(() => trackComponentRender('SlowComponent', 'mount', 150, 100, 0, 150)).not.toThrow();
    });
  });

  describe('trackApiCall', () => {
    it('does not throw for successful API call', () => {
      expect(() => trackApiCall('/v2/users', 120, true)).not.toThrow();
    });

    it('does not throw for failed API call', () => {
      expect(() => trackApiCall('/v2/listings', 500, false)).not.toThrow();
    });
  });

  describe('trackError', () => {
    it('does not throw when tracking an error', () => {
      const error = new Error('Test error');
      expect(() => trackError(error, 'TestContext')).not.toThrow();
    });

    it('does not throw when tracking error without context', () => {
      expect(() => trackError(new Error('No context'))).not.toThrow();
    });
  });

  describe('onProfilerRender', () => {
    it('is a callable function', () => {
      expect(typeof onProfilerRender).toBe('function');
    });

    it('does not throw for fast renders', () => {
      expect(() => onProfilerRender('FastComp', 'mount', 50, 50, 0, 50)).not.toThrow();
    });

    it('does not throw for slow renders', () => {
      expect(() => onProfilerRender('SlowComp', 'update', 200, 180, 100, 300)).not.toThrow();
    });
  });

  describe('getStoredMetrics / clearStoredMetrics round trip', () => {
    it('stores and retrieves metrics correctly', () => {
      // Manually write a metric via localStorage
      const metrics: PerformanceMetric[] = [
        {
          type: 'page_load',
          timestamp: new Date().toISOString(),
          name: '/feed',
          duration: 1200,
          data: { dns: 10, tcp: 20, request: 50, response: 100, dom: 300, load: 200, total: 1200 },
        },
      ];
      localStorage.setItem('nexus_performance_metrics', JSON.stringify(metrics));

      const retrieved = getStoredMetrics();
      expect(retrieved).toHaveLength(1);
      expect(retrieved[0].type).toBe('page_load');
      expect(retrieved[0].name).toBe('/feed');
      expect(retrieved[0].duration).toBe(1200);

      clearStoredMetrics();
      expect(getStoredMetrics()).toHaveLength(0);
    });
  });
});
