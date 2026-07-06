// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';

const mockNavigate = vi.fn();

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, unknown>) =>
      (opts?.fallbackValue as string | undefined) ?? key,
  }),
  initReactI18next: { type: '3rdParty', init: () => {} },
}));

vi.mock('react-router-dom', () => {
  return {
    BrowserRouter: ({ children }: { children?: ReactNode }) => <>{children}</>,
    MemoryRouter: ({ children }: { children?: ReactNode }) => <>{children}</>,
    useNavigate: () => mockNavigate,
    Link: ({ children, to, ...rest }: { children: ReactNode; to: string; [k: string]: unknown }) =>
      <a href={String(to)} {...rest}>{children}</a>,
    useSearchParams: () => [new URLSearchParams(), vi.fn()],
  };
});

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn().mockResolvedValue({ success: true, data: null, meta: {} }),
    post: vi.fn().mockResolvedValue({ success: true }),
    put: vi.fn().mockResolvedValue({ success: true }),
    delete: vi.fn().mockResolvedValue({ success: true }),
  },
  tokenManager: { getTenantId: vi.fn() },
}));

const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };
const mockTenant = {
  tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
  tenantPath: (p: string) => `/test${p}`,
  hasFeature: vi.fn(() => true),
  hasModule: vi.fn(() => true),
};

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    user: { id: 1, first_name: 'Test', name: 'Test User', organization: null },
    isAuthenticated: true,
  })),
  useTenant: vi.fn(() => mockTenant),
  useToast: vi.fn(() => mockToast),
  useTheme: () => ({ resolvedTheme: 'light', toggleTheme: vi.fn(), theme: 'system', setTheme: vi.fn() }),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
}));

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

vi.mock('@/components/ui', () => {
  let currentNumberField: {
    value?: number;
    onChange?: (n: number | undefined) => void;
    isDisabled?: boolean;
  } = {};
  const Box = ({ children, label, title, description }: Record<string, unknown>) => (
    <div>
      {label as ReactNode}
      {title as ReactNode}
      {description as ReactNode}
      {children as ReactNode}
    </div>
  );
  const Button = ({ children, onPress, onClick }: Record<string, unknown>) => (
    <button type="button" onClick={(onPress ?? onClick) as (() => void) | undefined}>{children as ReactNode}</button>
  );
  const Input = ({ label, placeholder, value, onValueChange }: Record<string, unknown>) => {
    const input = (
      <input
        placeholder={placeholder as string | undefined}
        value={(value as string | undefined) ?? ''}
        onChange={(event) => typeof onValueChange === 'function' && (onValueChange as (value: string) => void)(event.target.value)}
      />
    );
    return label ? <label>{label as ReactNode}{input}</label> : input;
  };
  const Textarea = Input;
  const Select = ({ children, label }: Record<string, unknown>) => (
    <div>
      {label as ReactNode}
      {children as ReactNode}
    </div>
  );
  const Switch = ({ children, onValueChange }: Record<string, unknown>) => (
    <label>
      <input
        type="checkbox"
        onChange={(event) => typeof onValueChange === 'function' && (onValueChange as (value: boolean) => void)(event.target.checked)}
      />
      {children as ReactNode}
    </label>
  );
  const NumberFieldRoot = ({ value, onChange, isDisabled, children }: Record<string, unknown>) => {
    currentNumberField = {
      value: value as number | undefined,
      onChange: onChange as ((n: number | undefined) => void) | undefined,
      isDisabled: isDisabled as boolean | undefined,
    };
    return <div>{children as ReactNode}</div>;
  };
  const NumberField = Object.assign(NumberFieldRoot, {
    Group: ({ children }: { children?: ReactNode }) => <div>{children}</div>,
    Input: () => (
      <input
        type="number"
        value={currentNumberField.value ?? ''}
        disabled={currentNumberField.isDisabled || undefined}
        onChange={(event) => currentNumberField.onChange?.(event.target.value === '' ? undefined : Number(event.target.value))}
      />
    ),
    DecrementButton: () => null,
    IncrementButton: () => null,
  });

  return {
    Select,
    SelectItem: Box,
    GlassCard: Box,
    Progress: ({ value }: Record<string, unknown>) => <progress value={value as number | undefined} max={100} />,
    Button,
    Input,
    Textarea,
    Switch,
    NumberField,
    Label: ({ children }: { children?: ReactNode }) => <label>{children}</label>,
  };
});

vi.mock('@/components/seo', () => ({
  PageMeta: () => null,
}));

vi.mock('@/components/feedback', () => ({
  EmptyState: ({ title, description }: { title: string; description?: string }) => (
    <div data-testid="empty-state">
      <div>{title}</div>
      {description && <div>{description}</div>}
    </div>
  ),
}));

vi.mock('@/lib/motion', () => ({
  motion: {
    div: ({ children, ...rest }: Record<string, unknown>) => (
      <div {...(rest as object)}>{children as ReactNode}</div>
    ),
  },
  AnimatePresence: ({ children }: { children: ReactNode }) => <>{children}</>,
}));

import { EmployerOnboardingPage } from './EmployerOnboardingPage';

describe('EmployerOnboardingPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    // Clear localStorage to ensure clean wizard state
    try { localStorage.removeItem('nexus_employer_onboarding'); } catch { /* ignore */ }
  });

  it('renders without crashing', () => {
    render(<EmployerOnboardingPage />);
    expect(document.body).toBeTruthy();
  });

  it('renders the welcome step heading by default', () => {
    render(<EmployerOnboardingPage />);
    expect(screen.getByText('onboarding.welcome_title')).toBeInTheDocument();
  });

  it('renders the Get Started button on welcome step', () => {
    render(<EmployerOnboardingPage />);
    expect(screen.getByText('onboarding.get_started')).toBeInTheDocument();
  });

  it('renders the progress indicator', () => {
    render(<EmployerOnboardingPage />);
    // Step counter text
    const stepText = screen.getByText(/1 \/ 4/);
    expect(stepText).toBeInTheDocument();
  });

  it('renders the welcome description', () => {
    render(<EmployerOnboardingPage />);
    expect(screen.getByText('onboarding.welcome_desc')).toBeInTheDocument();
  });

  it('renders back link to browse vacancies', () => {
    render(<EmployerOnboardingPage />);
    expect(screen.getByText('detail.browse_vacancies')).toBeInTheDocument();
  });

  it('navigates to step 2 when Get Started is clicked', async () => {
    const { userEvent } = await import('@testing-library/user-event');
    render(<EmployerOnboardingPage />);
    await userEvent.click(screen.getByText('onboarding.get_started'));
    expect(screen.getByText('onboarding.org_title')).toBeInTheDocument();
  });

  it('renders step 2 organization form fields', async () => {
    const { userEvent } = await import('@testing-library/user-event');
    render(<EmployerOnboardingPage />);
    await userEvent.click(screen.getByText('onboarding.get_started'));
    expect(screen.getByText('onboarding.org_title')).toBeInTheDocument();
    expect(screen.getByText('onboarding.org_desc')).toBeInTheDocument();
  });
});
