// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── API mock ─────────────────────────────────────────────────────────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
    download: vi.fn(),
    upload: vi.fn(),
  },
}));
vi.mock('@/lib/api', () => ({ api: mockApi, default: mockApi }));

// ─── installPrompt lib mock ───────────────────────────────────────────────────
// Hoist mock data so vi.mock factory can reference them safely without
// per-render arrow functions (avoids infinite-loop / max-update-depth).
const { mockState, mockShouldOffer, mockRequestInstall, mockUseInstallPrompt } =
  vi.hoisted(() => {
    const mockState = {
      canPrompt: false,
      isIos: false,
      isInstalled: false,
      isIosSafari: false,
      browser: 'chrome-desktop' as const,
    };
    const mockShouldOffer = vi.fn(() => true);
    const mockRequestInstall = vi.fn();
    const mockUseInstallPrompt = vi.fn(() => ({ ...mockState }));
    return { mockState, mockShouldOffer, mockRequestInstall, mockUseInstallPrompt };
  });

vi.mock('@/lib/installPrompt', () => ({
  useInstallPrompt: mockUseInstallPrompt,
  shouldOfferInstall: mockShouldOffer,
  requestInstall: mockRequestInstall,
}));

// ─── Contexts ─────────────────────────────────────────────────────────────────
const mockToast = {
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
  showToast: vi.fn(),
};

vi.mock('@/contexts', () =>
  createMockContexts({
    useAuth: () => ({
      user: { id: 1, name: 'Test User' },
      isAuthenticated: true,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      updateUser: vi.fn(),
      refreshUser: vi.fn(),
      status: 'idle' as const,
      error: null,
    }),
    useToast: () => mockToast,
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  }),
);

// ─── Helper render ─────────────────────────────────────────────────────────
/** A simple children render-prop implementation used across all tests */
function renderButton({
  onClick,
  label,
  sublabel,
}: {
  onClick: () => void;
  label: string;
  sublabel: string;
}) {
  return (
    <button onClick={onClick} data-testid="install-btn">
      <span data-testid="label">{label}</span>
      <span data-testid="sublabel">{sublabel}</span>
    </button>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
describe('InstallAppButton', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    // Default: install is offerable, not iOS Safari
    mockShouldOffer.mockReturnValue(true);
    mockUseInstallPrompt.mockReturnValue({
      canPrompt: false,
      isIos: false,
      isInstalled: false,
      isIosSafari: false,
      browser: 'chrome-desktop' as const,
    });
  });

  it('renders the children render-prop when install should be offered', async () => {
    const { InstallAppButton } = await import('./InstallAppButton');
    render(<InstallAppButton>{renderButton}</InstallAppButton>);
    expect(screen.getByTestId('install-btn')).toBeInTheDocument();
  });

  it('renders nothing (returns null) when shouldOfferInstall is false', async () => {
    mockShouldOffer.mockReturnValue(false);
    const { InstallAppButton } = await import('./InstallAppButton');
    render(<InstallAppButton>{renderButton}</InstallAppButton>);
    expect(screen.queryByTestId('install-btn')).toBeNull();
  });

  it('passes the translated label "Install app" to the render-prop', async () => {
    const { InstallAppButton } = await import('./InstallAppButton');
    render(<InstallAppButton>{renderButton}</InstallAppButton>);
    expect(screen.getByTestId('label')).toHaveTextContent('Install app');
  });

  it('passes the non-iOS sublabel "Faster access, works offline" by default', async () => {
    const { InstallAppButton } = await import('./InstallAppButton');
    render(<InstallAppButton>{renderButton}</InstallAppButton>);
    expect(screen.getByTestId('sublabel')).toHaveTextContent('Faster access, works offline');
  });

  it('passes the iOS sublabel when isIosSafari is true', async () => {
    mockUseInstallPrompt.mockReturnValue({
      canPrompt: false,
      isIos: true,
      isInstalled: false,
      isIosSafari: true,
      browser: 'ios-safari' as const,
    });
    const { InstallAppButton } = await import('./InstallAppButton');
    render(<InstallAppButton>{renderButton}</InstallAppButton>);
    expect(screen.getByTestId('sublabel')).toHaveTextContent('Add NEXUS to your home screen');
  });

  it('calls requestInstall with the prompt state when button is clicked', async () => {
    const promptState = {
      canPrompt: true,
      isIos: false,
      isInstalled: false,
      isIosSafari: false,
      browser: 'chrome-desktop' as const,
    };
    mockUseInstallPrompt.mockReturnValue(promptState);
    const { InstallAppButton } = await import('./InstallAppButton');
    render(<InstallAppButton>{renderButton}</InstallAppButton>);

    fireEvent.click(screen.getByTestId('install-btn'));
    expect(mockRequestInstall).toHaveBeenCalledTimes(1);
    expect(mockRequestInstall).toHaveBeenCalledWith(promptState);
  });

  it('does not call requestInstall before the button is clicked', async () => {
    const { InstallAppButton } = await import('./InstallAppButton');
    render(<InstallAppButton>{renderButton}</InstallAppButton>);
    expect(mockRequestInstall).not.toHaveBeenCalled();
  });

  it('calls requestInstall again on a second click', async () => {
    const { InstallAppButton } = await import('./InstallAppButton');
    render(<InstallAppButton>{renderButton}</InstallAppButton>);
    const btn = screen.getByTestId('install-btn');
    fireEvent.click(btn);
    fireEvent.click(btn);
    expect(mockRequestInstall).toHaveBeenCalledTimes(2);
  });

  it('renders nothing when isInstalled is true (shouldOfferInstall returns false)', async () => {
    mockUseInstallPrompt.mockReturnValue({
      canPrompt: false,
      isIos: false,
      isInstalled: true,
      isIosSafari: false,
      browser: 'chrome-desktop' as const,
    });
    mockShouldOffer.mockReturnValue(false);
    const { InstallAppButton } = await import('./InstallAppButton');
    render(<InstallAppButton>{renderButton}</InstallAppButton>);
    expect(screen.queryByTestId('install-btn')).toBeNull();
  });

  it('renders nothing for ios-other browser (shouldOfferInstall returns false)', async () => {
    mockUseInstallPrompt.mockReturnValue({
      canPrompt: false,
      isIos: true,
      isInstalled: false,
      isIosSafari: false,
      browser: 'ios-other' as const,
    });
    mockShouldOffer.mockReturnValue(false);
    const { InstallAppButton } = await import('./InstallAppButton');
    render(<InstallAppButton>{renderButton}</InstallAppButton>);
    expect(screen.queryByTestId('install-btn')).toBeNull();
  });

  it('accepts a custom render-prop returning a link element', async () => {
    const { InstallAppButton } = await import('./InstallAppButton');
    render(
      <InstallAppButton>
        {({ onClick, label }) => (
          <a role="link" onClick={onClick} data-testid="install-link">
            {label}
          </a>
        )}
      </InstallAppButton>,
    );
    expect(screen.getByTestId('install-link')).toBeInTheDocument();
    expect(screen.getByTestId('install-link')).toHaveTextContent('Install app');
  });
});
