// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── No API calls in this component ──────────────────────────────────────────

// ─── Contexts ────────────────────────────────────────────────────────────────
vi.mock('@/contexts', () => createMockContexts());

// ─── Stub HeroUI Modal family from @/components/ui ───────────────────────────
// ModalContent uses a render-prop pattern: children = (onClose) => ReactNode
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/components/ui')>();
  return {
    ...orig,
    Modal: ({
      isOpen,
      onClose,
      children,
    }: {
      isOpen?: boolean;
      onClose?: () => void;
      children?: React.ReactNode;
      placement?: string;
      size?: string;
      backdrop?: string;
    }) =>
      isOpen ? (
        <div role="dialog" aria-label="Dialog" aria-modal="true" data-testid="modal">
          {children}
        </div>
      ) : null,

    ModalContent: ({
      children,
    }: {
      children?: React.ReactNode | ((onClose: () => void) => React.ReactNode);
    }) => (
      <div data-testid="modal-content">
        {typeof children === 'function' ? children(() => {}) : children}
      </div>
    ),

    ModalHeader: ({ children, ...rest }: React.HTMLAttributes<HTMLDivElement>) => (
      <div data-testid="modal-header" {...rest}>
        {children}
      </div>
    ),

    ModalBody: ({ children, ...rest }: React.HTMLAttributes<HTMLDivElement>) => (
      <div data-testid="modal-body" {...rest}>
        {children}
      </div>
    ),

    ModalFooter: ({ children, ...rest }: React.HTMLAttributes<HTMLDivElement>) => (
      <div data-testid="modal-footer" {...rest}>
        {children}
      </div>
    ),

    Button: ({
      children,
      onPress,
      onClick,
      ...rest
    }: React.ButtonHTMLAttributes<HTMLButtonElement> & { onPress?: () => void }) => (
      <button
        onClick={() => {
          onPress?.();
          onClick?.(undefined as unknown as React.MouseEvent<HTMLButtonElement>);
        }}
        {...rest}
      >
        {children}
      </button>
    ),
  };
});

// ─────────────────────────────────────────────────────────────────────────────
describe('IosInstallModal', () => {
  const mockOnClose = vi.fn();

  beforeEach(() => {
    vi.resetAllMocks();
  });

  // ── Visibility ───────────────────────────────────────────────────────────

  it('renders nothing when isOpen is false', async () => {
    const { IosInstallModal } = await import('./IosInstallModal');
    render(<IosInstallModal isOpen={false} onClose={mockOnClose} />);

    expect(screen.queryByTestId('modal')).not.toBeInTheDocument();
  });

  it('renders the dialog when isOpen is true', async () => {
    const { IosInstallModal } = await import('./IosInstallModal');
    render(<IosInstallModal isOpen={true} onClose={mockOnClose} />);

    expect(screen.getByRole('dialog')).toBeInTheDocument();
  });

  // ── Content structure ────────────────────────────────────────────────────

  it('renders header, body and footer sections', async () => {
    const { IosInstallModal } = await import('./IosInstallModal');
    render(<IosInstallModal isOpen={true} onClose={mockOnClose} />);

    expect(screen.getByTestId('modal-header')).toBeInTheDocument();
    expect(screen.getByTestId('modal-body')).toBeInTheDocument();
    expect(screen.getByTestId('modal-footer')).toBeInTheDocument();
  });

  it('renders exactly 3 numbered steps', async () => {
    const { IosInstallModal } = await import('./IosInstallModal');
    render(<IosInstallModal isOpen={true} onClose={mockOnClose} />);

    // Steps 1, 2, 3 are rendered in numbered spans
    expect(screen.getByText('1')).toBeInTheDocument();
    expect(screen.getByText('2')).toBeInTheDocument();
    expect(screen.getByText('3')).toBeInTheDocument();
  });

  it('renders a list of steps inside an ordered list', async () => {
    const { IosInstallModal } = await import('./IosInstallModal');
    render(<IosInstallModal isOpen={true} onClose={mockOnClose} />);

    const ol = document.querySelector('ol');
    expect(ol).not.toBeNull();
    const listItems = ol!.querySelectorAll('li');
    expect(listItems).toHaveLength(3);
  });

  // ── Dismiss button ───────────────────────────────────────────────────────

  it('renders a dismiss/got-it button in the footer', async () => {
    const { IosInstallModal } = await import('./IosInstallModal');
    render(<IosInstallModal isOpen={true} onClose={mockOnClose} />);

    const footer = screen.getByTestId('modal-footer');
    const buttons = footer.querySelectorAll('button');
    expect(buttons.length).toBeGreaterThan(0);
  });

  it('calls onClose when the dismiss button is clicked', async () => {
    const { IosInstallModal } = await import('./IosInstallModal');
    render(<IosInstallModal isOpen={true} onClose={mockOnClose} />);

    const footer = screen.getByTestId('modal-footer');
    const btn = footer.querySelector('button');
    expect(btn).not.toBeNull();
    fireEvent.click(btn!);

    expect(mockOnClose).toHaveBeenCalledOnce();
  });

  // ── Re-open behaviour ────────────────────────────────────────────────────

  it('mounts modal content again when reopened after being closed', async () => {
    const { IosInstallModal } = await import('./IosInstallModal');
    const { rerender } = render(
      <IosInstallModal isOpen={true} onClose={mockOnClose} />,
    );
    expect(screen.getByRole('dialog')).toBeInTheDocument();

    rerender(<IosInstallModal isOpen={false} onClose={mockOnClose} />);
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();

    rerender(<IosInstallModal isOpen={true} onClose={mockOnClose} />);
    expect(screen.getByRole('dialog')).toBeInTheDocument();
  });

  // ── aria attributes ──────────────────────────────────────────────────────

  it('dialog has aria-modal=true', async () => {
    const { IosInstallModal } = await import('./IosInstallModal');
    render(<IosInstallModal isOpen={true} onClose={mockOnClose} />);

    expect(screen.getByRole('dialog')).toHaveAttribute('aria-modal', 'true');
  });

  // ── onClose is NOT called if modal stays closed ──────────────────────────

  it('does not call onClose when modal is not open', async () => {
    const { IosInstallModal } = await import('./IosInstallModal');
    render(<IosInstallModal isOpen={false} onClose={mockOnClose} />);

    expect(mockOnClose).not.toHaveBeenCalled();
  });

  // ── i18n translation keys render without crash ───────────────────────────

  it('renders without crashing even when translation keys are missing', async () => {
    const { IosInstallModal } = await import('./IosInstallModal');
    // real i18n in test-utils: will fall back to key names — component still mounts
    expect(() =>
      render(<IosInstallModal isOpen={true} onClose={mockOnClose} />),
    ).not.toThrow();
  });
});
