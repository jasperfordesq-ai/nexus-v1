// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, waitFor, fireEvent, act } from '@/test/test-utils';

// ── Mock the install-prompt module ───────────────────────────────────────────
const mockPromptInstall = vi.fn();

const defaultInstallState = {
  canPrompt: false,
  isIos: false,
  isInstalled: false,
  isIosSafari: false,
  browser: 'chrome-desktop' as const,
  promptInstall: mockPromptInstall,
};

let mockInstallState = { ...defaultInstallState };
let mockCookieBannerVisible = false;
let mockTenantContext = {
  branding: {
    name: 'Hour Timebank',
  },
};

vi.mock('@/lib/installPrompt', () => ({
  useInstallPrompt: () => mockInstallState,
  shouldOfferInstall: (state: typeof mockInstallState) =>
    !state.isInstalled && state.browser !== 'ios-other',
}));

vi.mock('@/contexts/TenantContext', () => ({
  useTenant: () => mockTenantContext,
}));

vi.mock('@/contexts/CookieConsentContext', () => ({
  useCookieConsent: () => ({ showBanner: mockCookieBannerVisible }),
}));

// Control localStorage in isolation
const mockStorageMap: Record<string, string> = {};
vi.mock('@/lib/safeStorage', () => ({
  safeLocalStorageGet: (key: string) => mockStorageMap[key] ?? null,
  safeLocalStorageSet: (key: string, value: string) => {
    mockStorageMap[key] = value;
  },
}));

// Stub IosInstallModal to avoid portal rendering complexity
vi.mock('./IosInstallModal', () => ({
  IosInstallModal: ({ isOpen }: { isOpen: boolean }) =>
    isOpen ? <div data-testid="ios-modal">iOS modal open</div> : null,
}));

import { InstallBanner } from './InstallBanner';

// Helper: set first_seen far enough in the past that elapsed >= GRACE_MS (60s)
// so the component's effect immediately calls setVisible(true) without scheduling a timer.
function setFirstSeenLongAgo() {
  mockStorageMap['nexus_install_banner_first_seen'] = String(Date.now() - 70_000);
}

beforeEach(() => {
  mockCookieBannerVisible = false;
});

// ─────────────────────────────────────────────────────────────────────────────
// Group A: tests that should never show the banner (no timer dependency)
// ─────────────────────────────────────────────────────────────────────────────
describe('InstallBanner — permanently hidden', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    Object.keys(mockStorageMap).forEach((k) => delete mockStorageMap[k]);
  });

  it('renders nothing when app is already installed', () => {
    mockInstallState = { ...defaultInstallState, isInstalled: true };
    render(<InstallBanner />);
    expect(screen.queryByRole('region')).not.toBeInTheDocument();
  });

  it('renders nothing when browser is ios-other (cannot install via that browser)', () => {
    mockInstallState = { ...defaultInstallState, browser: 'ios-other', isIos: true };
    render(<InstallBanner />);
    expect(screen.queryByRole('region')).not.toBeInTheDocument();
  });

  it('renders nothing when banner was previously dismissed', () => {
    mockStorageMap['nexus_install_banner_dismissed'] = '1';
    mockInstallState = { ...defaultInstallState, canPrompt: true };
    setFirstSeenLongAgo(); // make sure elapsed check passes, only dismissed blocks it
    render(<InstallBanner />);
    expect(screen.queryByRole('region')).not.toBeInTheDocument();
  });

  it('waits until cookie consent is resolved before starting the install prompt', () => {
    mockInstallState = { ...defaultInstallState, canPrompt: true };
    setFirstSeenLongAgo();
    mockCookieBannerVisible = true;
    const { rerender } = render(<InstallBanner />);

    expect(screen.queryByRole('region')).not.toBeInTheDocument();

    mockCookieBannerVisible = false;
    rerender(<InstallBanner />);
    expect(screen.getByRole('region')).toBeInTheDocument();
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// Group B: grace-period timer tests (fake timers, no waitFor)
// ─────────────────────────────────────────────────────────────────────────────
describe('InstallBanner — grace period (fake timers only, no waitFor)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    Object.keys(mockStorageMap).forEach((k) => delete mockStorageMap[k]);
    mockInstallState = { ...defaultInstallState, canPrompt: true };
    vi.useFakeTimers();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('does not show banner immediately on first visit (within grace period)', () => {
    // No first_seen key → elapsed = 0 → must wait for GRACE_MS
    render(<InstallBanner />);
    expect(screen.queryByRole('region')).not.toBeInTheDocument();
  });

  it('shows banner after timer fires when first_seen is recent', () => {
    // 30 seconds ago → 30 more seconds needed
    mockStorageMap['nexus_install_banner_first_seen'] = String(Date.now() - 30_000);
    render(<InstallBanner />);
    expect(screen.queryByRole('region')).not.toBeInTheDocument();

    // Advance clock past the remaining grace (30_001 ms enough)
    act(() => { vi.advanceTimersByTime(31_000); });
    expect(screen.getByRole('region')).toBeInTheDocument();
  });

  it('shows banner immediately when first_seen was long ago', () => {
    // Pre-set first_seen > GRACE_MS ago: effect sets visible synchronously, no timer
    setFirstSeenLongAgo();
    render(<InstallBanner />);
    // No timer involved — visible is set in the synchronous part of the effect
    expect(screen.getByRole('region')).toBeInTheDocument();
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// Group C: visible banner interactions (real timers; first_seen pre-set long ago)
// ─────────────────────────────────────────────────────────────────────────────
describe('InstallBanner — visible banner interactions (real timers)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    Object.keys(mockStorageMap).forEach((k) => delete mockStorageMap[k]);
    // Pre-set first_seen far in the past → effect calls setVisible(true) synchronously
    setFirstSeenLongAgo();
    mockInstallState = { ...defaultInstallState, canPrompt: true };
    mockTenantContext = {
      branding: {
        name: 'Hour Timebank',
      },
    };
  });

  it('renders the install banner region', () => {
    render(<InstallBanner />);
    expect(screen.getByRole('region')).toBeInTheDocument();
  });

  it('uses the tenant branding name in the install banner title', () => {
    render(<InstallBanner />);
    expect(screen.getByText('Install Hour Timebank for faster access')).toBeInTheDocument();
    expect(screen.queryByText('Install NEXUS for faster access')).not.toBeInTheDocument();
  });

  it('dismiss button hides the banner synchronously', () => {
    render(<InstallBanner />);
    expect(screen.getByRole('region')).toBeInTheDocument();

    const dismissBtn = screen.getByRole('button', { name: /dismiss/i });
    fireEvent.click(dismissBtn);

    expect(screen.queryByRole('region')).not.toBeInTheDocument();
  });

  it('dismiss button sets localStorage dismissed flag', () => {
    render(<InstallBanner />);
    fireEvent.click(screen.getByRole('button', { name: /dismiss/i }));
    expect(mockStorageMap['nexus_install_banner_dismissed']).toBe('1');
  });

  it('install CTA calls promptInstall() when canPrompt=true', async () => {
    mockPromptInstall.mockResolvedValue('dismissed');
    render(<InstallBanner />);
    expect(screen.getByRole('region')).toBeInTheDocument();

    // CTA button is the one without an aria-label (dismiss button has aria-label)
    const ctaBtn = screen.getAllByRole('button').find((b) => !b.hasAttribute('aria-label'));
    fireEvent.click(ctaBtn!);

    await waitFor(() => expect(mockPromptInstall).toHaveBeenCalled());
  });

  it('dismisses banner when native prompt outcome is accepted', async () => {
    mockPromptInstall.mockResolvedValue('accepted');
    render(<InstallBanner />);

    const ctaBtn = screen.getAllByRole('button').find((b) => !b.hasAttribute('aria-label'));
    fireEvent.click(ctaBtn!);

    await waitFor(() => {
      expect(screen.queryByRole('region')).not.toBeInTheDocument();
    });
  });

  it('banner stays visible when prompt outcome is dismissed', async () => {
    mockPromptInstall.mockResolvedValue('dismissed');
    render(<InstallBanner />);

    const ctaBtn = screen.getAllByRole('button').find((b) => !b.hasAttribute('aria-label'));
    fireEvent.click(ctaBtn!);

    await waitFor(() => expect(mockPromptInstall).toHaveBeenCalled());
    expect(screen.getByRole('region')).toBeInTheDocument();
  });

  it('opens iOS modal when isIosSafari=true and canPrompt=false', async () => {
    mockInstallState = {
      ...defaultInstallState,
      canPrompt: false,
      isIos: true,
      isIosSafari: true,
      browser: 'ios-safari',
      promptInstall: mockPromptInstall,
    };
    render(<InstallBanner />);
    expect(screen.getByRole('region')).toBeInTheDocument();

    const ctaBtn = screen.getAllByRole('button').find((b) => !b.hasAttribute('aria-label'));
    fireEvent.click(ctaBtn!);

    await waitFor(() => expect(screen.getByTestId('ios-modal')).toBeInTheDocument());
  });
});
