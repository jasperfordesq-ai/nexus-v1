// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';
import type { ComposeTabConfig } from './types';

// ─── Mock api ────────────────────────────────────────────────────────────────
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

// ─── Contexts ────────────────────────────────────────────────────────────────
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
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

// ─── Mock @/lib/motion (Framer Motion shim) ──────────────────────────────────
vi.mock('@/lib/motion', () => ({
  motion: {
    div: ({ children, role, 'aria-modal': ariaModal, 'aria-label': ariaLabel, className, style }: {
      children: React.ReactNode;
      role?: string;
      'aria-modal'?: string;
      'aria-label'?: string;
      className?: string;
      style?: React.CSSProperties;
    }) => (
      <div role={role} aria-modal={ariaModal} aria-label={ariaLabel} className={className} style={style}>
        {children}
      </div>
    ),
  },
  AnimatePresence: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

// ─── Mock @react-aria/focus ───────────────────────────────────────────────────
vi.mock('@react-aria/focus', () => ({
  FocusScope: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

// ─── Mock heavy HeroUI components ────────────────────────────────────────────
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<Record<string, unknown>>();
  return {
    ...orig,
    ScrollShadow: ({ children, className, style }: { children: React.ReactNode; className?: string; style?: React.CSSProperties }) => (
      <div data-testid="scroll-shadow" className={className} style={style}>{children}</div>
    ),
    Button: ({ children, onPress, isLoading, isDisabled, 'aria-label': ariaLabel, isIconOnly, size, variant, className }: {
      children?: React.ReactNode;
      onPress?: () => void;
      isLoading?: boolean;
      isDisabled?: boolean;
      'aria-label'?: string;
      isIconOnly?: boolean;
      size?: string;
      variant?: string;
      className?: string;
    }) => (
      <button
        onClick={onPress}
        disabled={isDisabled || isLoading}
        aria-label={ariaLabel}
        className={className}
        data-loading={isLoading ? 'true' : undefined}
      >
        {children}
      </button>
    ),
    Tabs: ({ children, 'aria-label': ariaLabel, selectedKey, onSelectionChange }: {
      children: React.ReactNode;
      'aria-label'?: string;
      selectedKey?: string;
      onSelectionChange?: (key: string) => void;
    }) => (
      <div role="tablist" aria-label={ariaLabel} data-selected={selectedKey}>
        {React.Children.map(children, (child) => {
          if (React.isValidElement(child)) {
            return React.cloneElement(child as React.ReactElement<{ onClick?: () => void }>, {
              onClick: () => onSelectionChange?.((child as React.ReactElement<{ children?: { key?: string }; 'data-key'?: string }>).props['data-key'] || ''),
            });
          }
          return child;
        })}
      </div>
    ),
    Tab: ({ title, children, 'data-key': dataKey }: { title?: React.ReactNode; children?: React.ReactNode; 'data-key'?: string }) => (
      <div role="tab" data-key={dataKey}>
        {title || children}
      </div>
    ),
  };
});

// ─── Fixtures ────────────────────────────────────────────────────────────────
const FileTextIcon = () => <svg data-testid="icon-file-text" />;
const CalendarIcon = () => <svg data-testid="icon-calendar" />;

const mockTabs: ComposeTabConfig[] = [
  { key: 'post', label: 'Post', icon: FileTextIcon as unknown as import('lucide-react').LucideIcon },
  { key: 'event', label: 'Event', icon: CalendarIcon as unknown as import('lucide-react').LucideIcon },
];

function renderOverlay(props: Partial<React.ComponentProps<typeof import('./MobileComposeOverlay').MobileComposeOverlay>> = {}) {
  const defaults = {
    isOpen: true,
    onClose: vi.fn(),
    activeTab: 'post' as const,
    onTabChange: vi.fn(),
    tabs: mockTabs,
    headerTitle: 'Create Post',
    children: <div data-testid="body-content">Body</div>,
  };
  return { ...defaults, ...props };
}

// ─────────────────────────────────────────────────────────────────────────────
describe('MobileComposeOverlay', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders the dialog with aria-modal when isOpen=true', async () => {
    const props = renderOverlay();
    const { MobileComposeOverlay } = await import('./MobileComposeOverlay');
    render(<MobileComposeOverlay {...props} />);

    await waitFor(() => {
      expect(screen.getByRole('dialog')).toBeInTheDocument();
      expect(screen.getByRole('dialog')).toHaveAttribute('aria-modal', 'true');
    });
  });

  it('renders header title text', async () => {
    const props = renderOverlay({ headerTitle: 'Write Something' });
    const { MobileComposeOverlay } = await import('./MobileComposeOverlay');
    render(<MobileComposeOverlay {...props} />);

    await waitFor(() => {
      expect(screen.getByText('Write Something')).toBeInTheDocument();
    });
  });

  it('renders the close button with accessible label', async () => {
    const props = renderOverlay();
    const { MobileComposeOverlay } = await import('./MobileComposeOverlay');
    render(<MobileComposeOverlay {...props} />);

    await waitFor(() => {
      // The close button has aria-label from i18n key compose.close_compose
      const closeBtn = screen.getByRole('button', { name: /close/i });
      expect(closeBtn).toBeInTheDocument();
    });
  });

  it('calls onClose when close button is pressed', async () => {
    const onClose = vi.fn();
    const props = renderOverlay({ onClose });
    const { MobileComposeOverlay } = await import('./MobileComposeOverlay');
    render(<MobileComposeOverlay {...props} />);

    await waitFor(() => screen.getByRole('dialog'));

    const closeBtn = screen.getByRole('button', { name: /close/i });
    fireEvent.click(closeBtn);
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it('calls onClose when Escape key is pressed', async () => {
    const onClose = vi.fn();
    const props = renderOverlay({ onClose });
    const { MobileComposeOverlay } = await import('./MobileComposeOverlay');
    render(<MobileComposeOverlay {...props} />);

    await waitFor(() => screen.getByRole('dialog'));

    fireEvent.keyDown(document, { key: 'Escape', code: 'Escape' });
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it('does not call onClose for non-Escape keys', async () => {
    const onClose = vi.fn();
    const props = renderOverlay({ onClose });
    const { MobileComposeOverlay } = await import('./MobileComposeOverlay');
    render(<MobileComposeOverlay {...props} />);

    await waitFor(() => screen.getByRole('dialog'));

    fireEvent.keyDown(document, { key: 'Enter', code: 'Enter' });
    expect(onClose).not.toHaveBeenCalled();
  });

  it('renders children in the body', async () => {
    const props = renderOverlay({
      children: <div data-testid="custom-body">Custom content</div>,
    });
    const { MobileComposeOverlay } = await import('./MobileComposeOverlay');
    render(<MobileComposeOverlay {...props} />);

    await waitFor(() => {
      expect(screen.getByTestId('custom-body')).toBeInTheDocument();
      expect(screen.getByText('Custom content')).toBeInTheDocument();
    });
  });

  it('renders tab list with tabs', async () => {
    const props = renderOverlay();
    const { MobileComposeOverlay } = await import('./MobileComposeOverlay');
    render(<MobileComposeOverlay {...props} />);

    await waitFor(() => {
      expect(screen.getByRole('tablist')).toBeInTheDocument();
      // Two tabs rendered
      const tabs = screen.getAllByRole('tab');
      expect(tabs.length).toBe(2);
    });
  });

  it('renders submit button when registration is provided via ComposeSubmitProvider', async () => {
    const { MobileComposeOverlay } = await import('./MobileComposeOverlay');
    const { ComposeSubmitProvider, useComposeSubmit } = await import('./ComposeSubmitContext');

    function TestRegistrar() {
      const { register } = useComposeSubmit();
      // Register on mount via effect
      React.useEffect(() => {
        register({
          canSubmit: true,
          isSubmitting: false,
          onSubmit: vi.fn(),
          buttonLabel: 'Post',
          gradientClass: 'from-accent to-accent-gradient-end',
        });
      }, [register]);
      return null;
    }

    render(
      <ComposeSubmitProvider>
        <TestRegistrar />
        <MobileComposeOverlay
          isOpen
          onClose={vi.fn()}
          activeTab="post"
          onTabChange={vi.fn()}
          tabs={mockTabs}
          headerTitle="Test"
        >
          <div />
        </MobileComposeOverlay>
      </ComposeSubmitProvider>
    );

    await waitFor(() => {
      // The registration button is rendered as a <button> element with 'Post' text
      // Look for it specifically as a button (not just a tab label span)
      const buttons = screen.getAllByRole('button');
      const submitBtn = buttons.find((b) => b.textContent?.trim() === 'Post');
      expect(submitBtn).toBeTruthy();
    });
  });

  it('does not render dialog when isOpen=false', async () => {
    const props = renderOverlay({ isOpen: false });
    const { MobileComposeOverlay } = await import('./MobileComposeOverlay');
    render(<MobileComposeOverlay {...props} />);

    // AnimatePresence controls mount; with our stub children are not rendered when isOpen=false
    // The dialog should not be in document
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('does not attach Escape handler when isOpen=false', async () => {
    const onClose = vi.fn();
    const props = renderOverlay({ isOpen: false, onClose });
    const { MobileComposeOverlay } = await import('./MobileComposeOverlay');
    render(<MobileComposeOverlay {...props} />);

    fireEvent.keyDown(document, { key: 'Escape', code: 'Escape' });
    expect(onClose).not.toHaveBeenCalled();
  });

  it('renders scroll shadow wrapper around body content', async () => {
    const props = renderOverlay();
    const { MobileComposeOverlay } = await import('./MobileComposeOverlay');
    render(<MobileComposeOverlay {...props} />);

    await waitFor(() => {
      expect(screen.getByTestId('scroll-shadow')).toBeInTheDocument();
      // Body content is inside the scroll shadow
      expect(screen.getByTestId('body-content')).toBeInTheDocument();
    });
  });
});
