// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { navigateToLegacyAdmin } from './nav-helpers';

vi.mock('@/lib/api', () => ({
  tokenManager: {
    getAccessToken: vi.fn(),
  },
  API_BASE: 'http://localhost:8090',
}));

import { tokenManager } from '@/lib/api';
const mockTokenManager = tokenManager as unknown as { getAccessToken: ReturnType<typeof vi.fn> };

describe('navigateToLegacyAdmin', () => {
  let appendChildSpy: ReturnType<typeof vi.spyOn>;
  let submitSpy: ReturnType<typeof vi.fn>;
  let createdForm: HTMLFormElement | null = null;

  beforeEach(() => {
    vi.clearAllMocks();
    submitSpy = vi.fn();

    // Mock document.createElement to intercept form creation
    const originalCreateElement = document.createElement.bind(document);
    appendChildSpy = vi.spyOn(document.body, 'appendChild').mockImplementation((node) => {
      createdForm = node as HTMLFormElement;
      return node;
    });

    vi.spyOn(document, 'createElement').mockImplementation((tag: string) => {
      const el = originalCreateElement(tag);
      if (tag === 'form') {
        el.submit = submitSpy;
      }
      return el;
    });
  });

  afterEach(() => {
    vi.restoreAllMocks();
    createdForm = null;
  });

  it('creates a form POST when token exists', () => {
    mockTokenManager.getAccessToken.mockReturnValue('test-token-123');
    navigateToLegacyAdmin();

    expect(appendChildSpy).toHaveBeenCalled();
    expect(submitSpy).toHaveBeenCalled();
    expect(createdForm?.method).toBe('post');
    expect(createdForm?.action).toContain('/api/auth/admin-session');
  });

  it('includes token in form data', () => {
    mockTokenManager.getAccessToken.mockReturnValue('my-jwt-token');
    navigateToLegacyAdmin();

    const inputs = createdForm?.querySelectorAll('input');
    const tokenInput = Array.from(inputs ?? []).find((i) => i.name === 'token');
    expect(tokenInput?.value).toBe('my-jwt-token');
  });

  it('includes redirect to /admin-legacy in form data', () => {
    mockTokenManager.getAccessToken.mockReturnValue('token');
    navigateToLegacyAdmin();

    const inputs = createdForm?.querySelectorAll('input');
    const redirectInput = Array.from(inputs ?? []).find((i) => i.name === 'redirect');
    expect(redirectInput?.value).toBe('/admin-legacy');
  });

  it('redirects directly when no token exists', () => {
    mockTokenManager.getAccessToken.mockReturnValue(null);

    const hrefSetter = vi.fn();
    Object.defineProperty(window, 'location', {
      value: { href: '', ...window.location },
      writable: true,
    });
    vi.spyOn(window, 'location', 'get').mockReturnValue({
      ...window.location,
      href: '',
    } as Location);

    // Can't easily test window.location.href assignment in vitest/jsdom
    // but we can verify no form was submitted
    navigateToLegacyAdmin();
    expect(submitSpy).not.toHaveBeenCalled();
  });

  it('uses correct PHP origin from API_BASE', () => {
    mockTokenManager.getAccessToken.mockReturnValue('token');
    navigateToLegacyAdmin();
    expect(createdForm?.action).toContain('localhost:8090');
  });
});
