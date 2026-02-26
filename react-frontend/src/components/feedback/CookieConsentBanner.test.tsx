// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for CookieConsentBanner component
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, act } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import { HeroUIProvider } from '@heroui/react';
import { CookieConsentBanner } from './CookieConsentBanner';

// Mock consent context
const mockAcceptAll = vi.fn();
const mockAcceptEssentialOnly = vi.fn();
const mockSavePreferences = vi.fn();
let mockShowBanner = true;

vi.mock('@/contexts/CookieConsentContext', () => ({
  useCookieConsent: () => ({
    consent: mockShowBanner ? null : { essential: true, analytics: true, preferences: true, timestamp: new Date().toISOString() },
    showBanner: mockShowBanner,
    acceptAll: mockAcceptAll,
    acceptEssentialOnly: mockAcceptEssentialOnly,
    savePreferences: mockSavePreferences,
    hasConsent: () => false,
    resetConsent: vi.fn(),
  }),
  readStoredConsent: () => null,
}));

// Mock tenant context
vi.mock('@/contexts', () => ({
  useTenant: () => ({
    tenantPath: (path: string) => `/test-tenant${path}`,
    branding: { name: 'Test Community' },
    tenant: { id: 1, slug: 'test-tenant' },
  }),
}));

// Mock i18next
vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, fallback: string) => fallback,
    i18n: { language: 'en' },
  }),
}));

function renderBanner() {
  return render(
    <HeroUIProvider>
      <BrowserRouter>
        <CookieConsentBanner />
      </BrowserRouter>
    </HeroUIProvider>
  );
}

describe('CookieConsentBanner', () => {
  beforeEach(() => {
    mockShowBanner = true;
    mockAcceptAll.mockClear();
    mockAcceptEssentialOnly.mockClear();
    mockSavePreferences.mockClear();
    localStorage.clear();
  });

  it('renders when showBanner is true', () => {
    renderBanner();

    expect(screen.getByRole('dialog', { name: 'Cookie consent' })).toBeInTheDocument();
    expect(screen.getByText('We use cookies')).toBeInTheDocument();
  });

  it('does not render when showBanner is false', () => {
    mockShowBanner = false;
    renderBanner();

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('shows Accept All and Essential Only buttons', () => {
    renderBanner();

    expect(screen.getByRole('button', { name: 'Accept all' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Essential only' })).toBeInTheDocument();
  });

  it('calls acceptAll when Accept All is clicked', () => {
    renderBanner();

    act(() => {
      screen.getByRole('button', { name: 'Accept all' }).click();
    });

    expect(mockAcceptAll).toHaveBeenCalledOnce();
  });

  it('calls acceptEssentialOnly when Essential Only is clicked', () => {
    renderBanner();

    act(() => {
      screen.getByRole('button', { name: 'Essential only' }).click();
    });

    expect(mockAcceptEssentialOnly).toHaveBeenCalledOnce();
  });

  it('shows Manage Preferences button', () => {
    renderBanner();

    expect(screen.getByRole('button', { name: /Manage preferences/i })).toBeInTheDocument();
  });

  it('shows cookie policy link', () => {
    renderBanner();

    expect(screen.getByText('Learn more')).toBeInTheDocument();
  });

  it('has proper ARIA attributes', () => {
    renderBanner();

    const dialog = screen.getByRole('dialog', { name: 'Cookie consent' });
    expect(dialog).toHaveAttribute('aria-modal', 'false');
  });
});
