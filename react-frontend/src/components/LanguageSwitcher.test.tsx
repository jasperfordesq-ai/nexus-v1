// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import userEvent from '@testing-library/user-event';
import React from 'react';

// ─── Mock api + helpers ───────────────────────────────────────────────────────
const { mockApi, mockTokenManager, mockSafeLocalStorageSet, mockLogError } = vi.hoisted(() => ({
  mockApi: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn().mockResolvedValue({}),
    patch: vi.fn(),
    delete: vi.fn(),
    download: vi.fn(),
    upload: vi.fn(),
  },
  mockTokenManager: {
    hasAccessToken: vi.fn(() => false),
    getAccessToken: vi.fn(() => null),
    setTokens: vi.fn(),
    clearTokens: vi.fn(),
  },
  mockSafeLocalStorageSet: vi.fn(),
  mockLogError: vi.fn(),
}));

vi.mock('@/lib/api', () => ({
  api: mockApi,
  default: mockApi,
  tokenManager: mockTokenManager,
}));

vi.mock('@/lib/logger', () => ({ logError: mockLogError }));
vi.mock('@/lib/safeStorage', () => ({ safeLocalStorageSet: mockSafeLocalStorageSet }));

// ─── Mock useTenantLanguages from TenantContext ───────────────────────────────
// LanguageSwitcher imports useTenantLanguages from '@/contexts/TenantContext' directly.
const mockSupportedLanguages = vi.fn(() => ['en', 'ga', 'fr']);

vi.mock('@/contexts/TenantContext', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/contexts/TenantContext')>();
  return {
    ...orig,
    useTenantLanguages: () => mockSupportedLanguages(),
  };
});

// ─── Contexts ────────────────────────────────────────────────────────────────
const mockToast = {
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
  showToast: vi.fn(),
};

const mockChangeLanguage = vi.fn();

vi.mock('@/contexts', () =>
  createMockContexts({
    useAuth: () => ({
      user: null,
      isAuthenticated: false,
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
  })
);

// ─── Mock react-i18next so we control i18n.language + changeLanguage ──────────
vi.mock('react-i18next', async (importOriginal) => {
  const orig = await importOriginal<typeof import('react-i18next')>();
  return {
    ...orig,
    useTranslation: (_ns?: string) => ({
      t: (key: string, opts?: Record<string, unknown>) => {
        // Minimal i18n stubs for keys used by LanguageSwitcher
        if (key === 'aria.current_language') return `Current language: ${opts?.language ?? ''}`;
        if (key === 'aria.select_language') return 'Select language';
        return key;
      },
      i18n: {
        language: 'en',
        changeLanguage: mockChangeLanguage,
      },
    }),
  };
});

// ─── Stub @/components/ui ────────────────────────────────────────────────────
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/components/ui')>();
  return {
    ...orig,
    Button: ({
      children,
      onPress,
      'aria-label': ariaLabel,
      startContent,
    }: React.ButtonHTMLAttributes<HTMLButtonElement> & {
      onPress?: () => void;
      children?: React.ReactNode;
      startContent?: React.ReactNode;
      variant?: string;
      size?: string;
    }) => (
      <button aria-label={ariaLabel} onClick={onPress}>
        {startContent}
        {children}
      </button>
    ),
    Dropdown: ({ children }: { children: React.ReactNode }) => (
      <div data-testid="dropdown">{children}</div>
    ),
    DropdownTrigger: ({ children }: { children: React.ReactNode }) => (
      <div data-testid="dropdown-trigger">{children}</div>
    ),
    DropdownMenu: ({
      children,
      onAction,
      'aria-label': ariaLabel,
    }: {
      children: React.ReactNode;
      onAction?: (key: string) => void;
      'aria-label'?: string;
      selectedKeys?: Set<string>;
      selectionMode?: string;
      classNames?: Record<string, string>;
    }) => (
      <div
        role="listbox"
        aria-label={ariaLabel}
        data-testid="dropdown-menu"
        onClick={(e) => {
          const key = (e.target as HTMLElement).getAttribute('data-key');
          if (key && onAction) onAction(key);
        }}
      >
        {children}
      </div>
    ),
    DropdownItem: ({
      children,
      id,
    }: {
      children: React.ReactNode;
      id?: string;
      className?: string;
    }) => (
      <div
        role="option"
        data-testid={`lang-option-${id}`}
        data-key={id}
        aria-selected={false}
      >
        {children}
      </div>
    ),
  };
});

// ─────────────────────────────────────────────────────────────────────────────

describe('LanguageSwitcher', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockTokenManager.hasAccessToken.mockReturnValue(false);
    mockSupportedLanguages.mockReturnValue(['en', 'ga', 'fr']);
    mockApi.put.mockResolvedValue({});
  });

  it('renders a trigger button with the current language code', async () => {
    const { LanguageSwitcher } = await import('./LanguageSwitcher');
    render(<LanguageSwitcher />);

    // In compact mode (default), shows short code 'EN' — appears in trigger + option, use getAllByText
    const enNodes = screen.getAllByText('EN');
    // At least one 'EN' must exist
    expect(enNodes.length).toBeGreaterThanOrEqual(1);
    // The trigger button should carry the aria-label showing the current language
    expect(screen.getByRole('button')).toHaveAttribute('aria-label', expect.stringContaining('English'));
  });

  it('renders language options for all tenant-supported languages', async () => {
    const { LanguageSwitcher } = await import('./LanguageSwitcher');
    render(<LanguageSwitcher />);

    // The 3 supported languages should appear as dropdown items
    expect(screen.getByTestId('lang-option-en')).toBeInTheDocument();
    expect(screen.getByTestId('lang-option-ga')).toBeInTheDocument();
    expect(screen.getByTestId('lang-option-fr')).toBeInTheDocument();
  });

  it('does not render language options for unsupported languages', async () => {
    const { LanguageSwitcher } = await import('./LanguageSwitcher');
    render(<LanguageSwitcher />);

    // 'de' is not in the tenant supported list
    expect(screen.queryByTestId('lang-option-de')).toBeNull();
    expect(screen.queryByTestId('lang-option-es')).toBeNull();
  });

  it('shows full language name in non-compact mode', async () => {
    const { LanguageSwitcher } = await import('./LanguageSwitcher');
    render(<LanguageSwitcher compact={false} />);

    // Non-compact shows 'English' in the trigger button — 'EN' short code is absent from the button
    const triggerBtn = screen.getByRole('button');
    // The trigger button body shows 'English' not 'EN' in non-compact mode
    expect(triggerBtn.textContent).toContain('English');
    expect(triggerBtn.textContent).not.toContain('EN');
  });

  it('calls i18n.changeLanguage when a language option is clicked', async () => {
    const user = userEvent.setup();
    const { LanguageSwitcher } = await import('./LanguageSwitcher');
    render(<LanguageSwitcher />);

    const gaOption = screen.getByTestId('lang-option-ga');
    await user.click(gaOption);

    expect(mockChangeLanguage).toHaveBeenCalledWith('ga');
  });

  it('persists language preference to localStorage on selection', async () => {
    const user = userEvent.setup();
    const { LanguageSwitcher } = await import('./LanguageSwitcher');
    render(<LanguageSwitcher />);

    await user.click(screen.getByTestId('lang-option-fr'));

    expect(mockSafeLocalStorageSet).toHaveBeenCalledWith('nexus_language_user_chosen', 'true');
  });

  it('does NOT call api.put when user is not authenticated', async () => {
    const user = userEvent.setup();
    mockTokenManager.hasAccessToken.mockReturnValue(false);

    const { LanguageSwitcher } = await import('./LanguageSwitcher');
    render(<LanguageSwitcher />);

    await user.click(screen.getByTestId('lang-option-ga'));

    expect(mockApi.put).not.toHaveBeenCalled();
  });

  it('calls api.put to persist language when user is authenticated', async () => {
    const user = userEvent.setup();
    mockTokenManager.hasAccessToken.mockReturnValue(true);

    const { LanguageSwitcher } = await import('./LanguageSwitcher');
    render(<LanguageSwitcher />);

    await user.click(screen.getByTestId('lang-option-fr'));

    expect(mockApi.put).toHaveBeenCalledWith(
      '/v2/users/me/language',
      { language: 'fr' }
    );
  });

  it('renders the trigger button with globe aria-label', async () => {
    const { LanguageSwitcher } = await import('./LanguageSwitcher');
    render(<LanguageSwitcher />);

    // aria-label set from i18n key 'aria.current_language'
    const triggerBtn = screen.getByRole('button');
    expect(triggerBtn).toHaveAttribute('aria-label');
    expect(triggerBtn.getAttribute('aria-label')).toContain('English');
  });

  it('renders only tenant-supported languages when the list is a single language', async () => {
    mockSupportedLanguages.mockReturnValue(['de']);
    const { LanguageSwitcher } = await import('./LanguageSwitcher');
    render(<LanguageSwitcher />);

    expect(screen.getByTestId('lang-option-de')).toBeInTheDocument();
    expect(screen.queryByTestId('lang-option-en')).toBeNull();
    expect(screen.queryByTestId('lang-option-fr')).toBeNull();
  });

  it('displays language labels (full name) in dropdown items', async () => {
    const { LanguageSwitcher } = await import('./LanguageSwitcher');
    render(<LanguageSwitcher />);

    expect(screen.getByText('English')).toBeInTheDocument();
    expect(screen.getByText('Gaeilge')).toBeInTheDocument();
    expect(screen.getByText('Français')).toBeInTheDocument();
  });
});
