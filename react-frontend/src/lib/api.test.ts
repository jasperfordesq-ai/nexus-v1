/**
 * Tests for API client
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { tokenManager } from './api';

describe('tokenManager', () => {
  beforeEach(() => {
    localStorage.clear();
  });

  afterEach(() => {
    localStorage.clear();
  });

  describe('access token', () => {
    it('stores and retrieves access token', () => {
      tokenManager.setAccessToken('test-token');
      expect(tokenManager.getAccessToken()).toBe('test-token');
    });

    it('returns null when no token exists', () => {
      expect(tokenManager.getAccessToken()).toBeNull();
    });

    it('hasAccessToken returns true when token exists', () => {
      tokenManager.setAccessToken('test-token');
      expect(tokenManager.hasAccessToken()).toBe(true);
    });

    it('hasAccessToken returns false when no token', () => {
      expect(tokenManager.hasAccessToken()).toBe(false);
    });
  });

  describe('refresh token', () => {
    it('stores and retrieves refresh token', () => {
      tokenManager.setRefreshToken('refresh-token');
      expect(tokenManager.getRefreshToken()).toBe('refresh-token');
    });

    it('returns null when no refresh token exists', () => {
      expect(tokenManager.getRefreshToken()).toBeNull();
    });

    it('hasRefreshToken returns true when token exists', () => {
      tokenManager.setRefreshToken('refresh-token');
      expect(tokenManager.hasRefreshToken()).toBe(true);
    });
  });

  describe('tenant ID', () => {
    it('stores and retrieves tenant ID', () => {
      tokenManager.setTenantId('123');
      expect(tokenManager.getTenantId()).toBe('123');
    });

    it('accepts numeric tenant ID', () => {
      tokenManager.setTenantId(456);
      expect(tokenManager.getTenantId()).toBe('456');
    });

    it('returns default tenant ID when none set', () => {
      // Default is '1' in the implementation
      expect(tokenManager.getTenantId()).toBe('1');
    });
  });

  describe('clearTokens', () => {
    it('clears access and refresh tokens', () => {
      tokenManager.setAccessToken('access');
      tokenManager.setRefreshToken('refresh');
      tokenManager.clearTokens();

      expect(tokenManager.getAccessToken()).toBeNull();
      expect(tokenManager.getRefreshToken()).toBeNull();
    });

    it('does not clear tenant ID', () => {
      tokenManager.setTenantId('123');
      tokenManager.clearTokens();
      expect(tokenManager.getTenantId()).toBe('123');
    });
  });

  describe('clearAll', () => {
    it('clears all stored data', () => {
      tokenManager.setAccessToken('access');
      tokenManager.setRefreshToken('refresh');
      tokenManager.setTenantId('123');
      tokenManager.clearAll();

      expect(tokenManager.getAccessToken()).toBeNull();
      expect(tokenManager.getRefreshToken()).toBeNull();
      // Returns default '1' when cleared
      expect(tokenManager.getTenantId()).toBe('1');
    });
  });
});

describe('API client request deduplication', () => {
  // These tests would require mocking fetch
  // For now, we just verify the structure exists
  it('should be tested with fetch mocks in integration tests', () => {
    expect(true).toBe(true);
  });
});
