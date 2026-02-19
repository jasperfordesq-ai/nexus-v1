// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for tenant-routing utilities
 */

import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import {
  RESERVED_SUBDOMAINS,
  RESERVED_PATHS,
  detectTenantFromUrl,
  tenantPath,
  stripTenantSlug,
} from './tenant-routing';

describe('tenant-routing', () => {
  // Save original location
  const originalLocation = window.location;

  function mockLocation(hostname: string, pathname: string) {
    Object.defineProperty(window, 'location', {
      writable: true,
      value: { ...originalLocation, hostname, pathname },
    });
  }

  afterEach(() => {
    Object.defineProperty(window, 'location', {
      writable: true,
      value: originalLocation,
    });
  });

  describe('RESERVED_SUBDOMAINS', () => {
    it('contains expected subdomains', () => {
      expect(RESERVED_SUBDOMAINS.has('app')).toBe(true);
      expect(RESERVED_SUBDOMAINS.has('api')).toBe(true);
      expect(RESERVED_SUBDOMAINS.has('www')).toBe(true);
      expect(RESERVED_SUBDOMAINS.has('admin')).toBe(true);
      expect(RESERVED_SUBDOMAINS.has('staging')).toBe(true);
    });

    it('does not contain tenant slugs', () => {
      expect(RESERVED_SUBDOMAINS.has('hour-timebank')).toBe(false);
      expect(RESERVED_SUBDOMAINS.has('my-community')).toBe(false);
    });
  });

  describe('RESERVED_PATHS', () => {
    it('contains expected paths', () => {
      expect(RESERVED_PATHS.has('login')).toBe(true);
      expect(RESERVED_PATHS.has('register')).toBe(true);
      expect(RESERVED_PATHS.has('dashboard')).toBe(true);
      expect(RESERVED_PATHS.has('admin')).toBe(true);
      expect(RESERVED_PATHS.has('admin-legacy')).toBe(true);
      expect(RESERVED_PATHS.has('api')).toBe(true);
    });

    it('does not contain tenant slugs', () => {
      expect(RESERVED_PATHS.has('hour-timebank')).toBe(false);
      expect(RESERVED_PATHS.has('my-community')).toBe(false);
    });
  });

  describe('detectTenantFromUrl', () => {
    it('returns slug from path on localhost', () => {
      mockLocation('localhost', '/hour-timebank/dashboard');
      const result = detectTenantFromUrl();
      expect(result.slug).toBe('hour-timebank');
      expect(result.source).toBe('path');
    });

    it('returns null for reserved path on localhost', () => {
      mockLocation('localhost', '/dashboard');
      const result = detectTenantFromUrl();
      expect(result.slug).toBeNull();
      expect(result.source).toBeNull();
    });

    it('returns null for empty path on localhost', () => {
      mockLocation('localhost', '/');
      const result = detectTenantFromUrl();
      expect(result.slug).toBeNull();
      expect(result.source).toBeNull();
    });

    it('returns slug from path on 127.0.0.1', () => {
      mockLocation('127.0.0.1', '/my-community/feed');
      const result = detectTenantFromUrl();
      expect(result.slug).toBe('my-community');
      expect(result.source).toBe('path');
    });

    it('detects subdomain on project-nexus.ie', () => {
      mockLocation('hour-timebank.project-nexus.ie', '/dashboard');
      const result = detectTenantFromUrl();
      expect(result.slug).toBe('hour-timebank');
      expect(result.source).toBe('subdomain');
    });

    it('skips reserved subdomain (app) and falls through to path', () => {
      mockLocation('app.project-nexus.ie', '/my-tenant/feed');
      const result = detectTenantFromUrl();
      expect(result.slug).toBe('my-tenant');
      expect(result.source).toBe('path');
    });

    it('returns null for reserved subdomain (api)', () => {
      mockLocation('api.project-nexus.ie', '/');
      const result = detectTenantFromUrl();
      expect(result.slug).toBeNull();
      expect(result.source).toBeNull();
    });

    it('returns null for custom domain (R1)', () => {
      mockLocation('my-custom-domain.com', '/dashboard');
      const result = detectTenantFromUrl();
      expect(result.slug).toBeNull();
      expect(result.source).toBeNull();
    });

    it('lowercases subdomain slugs', () => {
      mockLocation('HOUR-TIMEBANK.project-nexus.ie', '/');
      const result = detectTenantFromUrl();
      expect(result.slug).toBe('hour-timebank');
    });

    it('rejects multi-level subdomains', () => {
      mockLocation('foo.bar.project-nexus.ie', '/');
      const result = detectTenantFromUrl();
      expect(result.slug).toBeNull();
      expect(result.source).toBeNull();
    });
  });

  describe('tenantPath', () => {
    it('prefixes path with tenant slug', () => {
      expect(tenantPath('/dashboard', 'hour-timebank')).toBe('/hour-timebank/dashboard');
    });

    it('returns path as-is when no slug', () => {
      expect(tenantPath('/dashboard', null)).toBe('/dashboard');
      expect(tenantPath('/dashboard', undefined)).toBe('/dashboard');
    });

    it('normalizes path without leading slash', () => {
      expect(tenantPath('dashboard', 'my-tenant')).toBe('/my-tenant/dashboard');
    });

    it('handles root path', () => {
      expect(tenantPath('/', 'my-tenant')).toBe('/my-tenant/');
    });

    it('handles empty string slug', () => {
      expect(tenantPath('/dashboard', '')).toBe('/dashboard');
    });
  });

  describe('stripTenantSlug', () => {
    it('strips slug prefix from pathname', () => {
      expect(stripTenantSlug('/hour-timebank/dashboard', 'hour-timebank')).toBe('/dashboard');
    });

    it('returns / when pathname is just the slug', () => {
      expect(stripTenantSlug('/hour-timebank', 'hour-timebank')).toBe('/');
    });

    it('returns pathname as-is when no slug prefix', () => {
      expect(stripTenantSlug('/dashboard', 'hour-timebank')).toBe('/dashboard');
    });

    it('is case-insensitive', () => {
      expect(stripTenantSlug('/HOUR-TIMEBANK/dashboard', 'hour-timebank')).toBe('/dashboard');
    });

    it('handles nested paths', () => {
      expect(stripTenantSlug('/my-tenant/listings/42', 'my-tenant')).toBe('/listings/42');
    });
  });
});
