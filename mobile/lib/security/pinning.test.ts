// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { PINNED_HOSTS, isPinnedHost } from './pinning';

describe('pinning', () => {
  describe('PINNED_HOSTS', () => {
    it('contains api.project-nexus.ie', () => {
      expect(PINNED_HOSTS).toContain('api.project-nexus.ie');
    });
  });

  describe('isPinnedHost', () => {
    it('returns true for api.project-nexus.ie URLs', () => {
      expect(isPinnedHost('https://api.project-nexus.ie')).toBe(true);
      expect(isPinnedHost('https://api.project-nexus.ie/')).toBe(true);
    });

    it('returns false for app.project-nexus.ie', () => {
      expect(isPinnedHost('https://app.project-nexus.ie')).toBe(false);
    });

    it('returns false for example.com', () => {
      expect(isPinnedHost('https://example.com')).toBe(false);
    });

    it('returns false for malformed URLs', () => {
      expect(isPinnedHost('')).toBe(false);
      expect(isPinnedHost('not-a-url')).toBe(false);
      expect(isPinnedHost('://missing-scheme')).toBe(false);
    });

    it('handles URLs with paths and query strings', () => {
      expect(isPinnedHost('https://api.project-nexus.ie/v2/me')).toBe(true);
      expect(isPinnedHost('https://api.project-nexus.ie/v2/me?foo=bar')).toBe(true);
      expect(isPinnedHost('https://api.project-nexus.ie:443/v2/me#section')).toBe(true);
    });
  });
});
