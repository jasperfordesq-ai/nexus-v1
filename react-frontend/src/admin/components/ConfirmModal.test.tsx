// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import userEvent from '@testing-library/user-event';
import React from 'react';

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

// ─── Mock @/contexts ─────────────────────────────────────────────────────────
vi.mock('@/contexts', () =>
  createMockContexts({
    useAuth: () => ({
      user: { id: 1, name: 'Admin' },
      isAuthenticated: true,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      updateUser: vi.fn(),
      refreshUser: vi.fn(),
      status: 'idle' as const,
      error: null,
    }),
  })
);

// ─── Stub HeroUI Modal family so jsdom renders dialog ───────────────────────
vi.mock('@/components/ui', async (importOriginal) => {
  const real = await importOriginal<typeof import('@/components/ui')>();
  return {
    ...real,
    Modal: ({ isOpen, children, onClose }: { isOpen: boolean; children: React.ReactNode; onClose?: () => void }) =>
      isOpen ? (
        <div role="dialog" aria-label="Dialog" aria-modal="true" data-testid="modal-root">
          {children}
        </div>
      ) : null,
    ModalContent: ({ children }: { children: React.ReactNode | ((onClose: () => void) => React.ReactNode) }) => (
      <div data-testid="modal-content">
        {typeof children === 'function' ? children(() => {}) : children}
      </div>
    ),
    ModalHeader: ({ children, className }: { children: React.ReactNode; className?: string }) => (
      <div data-testid="modal-header" className={className}>
        {children}
      </div>
    ),
    ModalBody: ({ children }: { children: React.ReactNode }) => (
      <div data-testid="modal-body">{children}</div>
    ),
    ModalFooter: ({ children }: { children: React.ReactNode }) => (
      <div data-testid="modal-footer">{children}</div>
    ),
    Button: ({
      children,
      onPress,
      isDisabled,
      isLoading,
      autoFocus,
      variant,
    }: {
      children: React.ReactNode;
      onPress?: () => void;
      isDisabled?: boolean;
      isLoading?: boolean;
      autoFocus?: boolean;
      variant?: string;
    }) => (
      <button
        onClick={onPress}
        disabled={isDisabled || isLoading}
        data-variant={variant}
        data-loading={isLoading ? 'true' : undefined}
        // eslint-disable-next-line jsx-a11y/no-autofocus
        autoFocus={autoFocus}
      >
        {children}
      </button>
    ),
  };
});

// ─── Test suite ─────────────────────────────────────────────────────────────
describe('ConfirmModal', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders the modal when isOpen=true', async () => {
    const { ConfirmModal } = await import('./ConfirmModal');
    render(
      <ConfirmModal
        isOpen={true}
        onClose={vi.fn()}
        onConfirm={vi.fn()}
        title="Delete record"
        message="Are you sure you want to delete this record?"
      />
    );
    expect(screen.getByRole('dialog')).toBeInTheDocument();
  });

  it('renders the title in the modal header', async () => {
    const { ConfirmModal } = await import('./ConfirmModal');
    render(
      <ConfirmModal
        isOpen={true}
        onClose={vi.fn()}
        onConfirm={vi.fn()}
        title="Delete record"
        message="Are you sure?"
      />
    );
    expect(screen.getByTestId('modal-header')).toHaveTextContent('Delete record');
  });

  it('renders the message in the modal body', async () => {
    const { ConfirmModal } = await import('./ConfirmModal');
    render(
      <ConfirmModal
        isOpen={true}
        onClose={vi.fn()}
        onConfirm={vi.fn()}
        title="Delete"
        message="This action cannot be undone."
      />
    );
    expect(screen.getByTestId('modal-body')).toHaveTextContent('This action cannot be undone.');
  });

  it('does not render dialog when isOpen=false', async () => {
    const { ConfirmModal } = await import('./ConfirmModal');
    render(
      <ConfirmModal
        isOpen={false}
        onClose={vi.fn()}
        onConfirm={vi.fn()}
        title="Hidden"
        message="Should not appear"
      />
    );
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('calls onConfirm when Confirm button is clicked', async () => {
    const onConfirm = vi.fn();
    const { ConfirmModal } = await import('./ConfirmModal');
    render(
      <ConfirmModal
        isOpen={true}
        onClose={vi.fn()}
        onConfirm={onConfirm}
        title="Delete"
        message="Sure?"
      />
    );
    const confirmBtn = screen.getByText('Confirm');
    await userEvent.click(confirmBtn);
    expect(onConfirm).toHaveBeenCalledOnce();
  });

  it('calls onClose when Cancel button is clicked', async () => {
    const onClose = vi.fn();
    const { ConfirmModal } = await import('./ConfirmModal');
    render(
      <ConfirmModal
        isOpen={true}
        onClose={onClose}
        onConfirm={vi.fn()}
        title="Delete"
        message="Sure?"
      />
    );
    const cancelBtn = screen.getByText('Cancel');
    await userEvent.click(cancelBtn);
    expect(onClose).toHaveBeenCalledOnce();
  });

  it('renders custom confirmLabel and cancelLabel', async () => {
    const { ConfirmModal } = await import('./ConfirmModal');
    render(
      <ConfirmModal
        isOpen={true}
        onClose={vi.fn()}
        onConfirm={vi.fn()}
        title="Remove"
        message="Sure?"
        confirmLabel="Yes, remove it"
        cancelLabel="Keep it"
      />
    );
    expect(screen.getByText('Yes, remove it')).toBeInTheDocument();
    expect(screen.getByText('Keep it')).toBeInTheDocument();
  });

  it('disables both buttons when isLoading=true', async () => {
    const { ConfirmModal } = await import('./ConfirmModal');
    render(
      <ConfirmModal
        isOpen={true}
        onClose={vi.fn()}
        onConfirm={vi.fn()}
        title="Delete"
        message="Sure?"
        isLoading={true}
      />
    );
    const buttons = screen.getAllByRole('button');
    // both buttons should be disabled
    const allDisabled = buttons.every((btn) => btn.hasAttribute('disabled'));
    expect(allDisabled).toBe(true);
  });

  it('does not call onConfirm again if already loading (double-click gate)', async () => {
    const onConfirm = vi.fn();
    const { ConfirmModal } = await import('./ConfirmModal');

    function Wrapper() {
      const [loading, setLoading] = React.useState(false);
      return (
        <ConfirmModal
          isOpen={true}
          onClose={vi.fn()}
          onConfirm={() => {
            setLoading(true);
            onConfirm();
          }}
          title="Delete"
          message="Sure?"
          isLoading={loading}
        />
      );
    }

    render(<Wrapper />);
    const confirmBtn = screen.getByText('Confirm');
    await userEvent.click(confirmBtn);
    // After first click, button is disabled — second click should do nothing
    await userEvent.click(confirmBtn);
    expect(onConfirm).toHaveBeenCalledOnce();
  });

  it('renders children inside the modal body', async () => {
    const { ConfirmModal } = await import('./ConfirmModal');
    render(
      <ConfirmModal
        isOpen={true}
        onClose={vi.fn()}
        onConfirm={vi.fn()}
        title="Delete"
        message="Sure?"
      >
        <p data-testid="extra-content">Extra warning text</p>
      </ConfirmModal>
    );
    expect(screen.getByTestId('extra-content')).toBeInTheDocument();
  });

  it('renders danger variant by default on Confirm button', async () => {
    const { ConfirmModal } = await import('./ConfirmModal');
    render(
      <ConfirmModal
        isOpen={true}
        onClose={vi.fn()}
        onConfirm={vi.fn()}
        title="Delete"
        message="Sure?"
      />
    );
    // The confirm button uses danger variant by default
    const confirmBtn = screen.getByText('Confirm');
    expect(confirmBtn).toHaveAttribute('data-variant', 'danger');
  });

  it('renders primary variant when confirmColor="primary"', async () => {
    const { ConfirmModal } = await import('./ConfirmModal');
    render(
      <ConfirmModal
        isOpen={true}
        onClose={vi.fn()}
        onConfirm={vi.fn()}
        title="Submit"
        message="Are you ready?"
        confirmColor="primary"
      />
    );
    const confirmBtn = screen.getByText('Confirm');
    expect(confirmBtn).toHaveAttribute('data-variant', 'primary');
  });

  it('renders warning variant (secondary) when confirmColor="warning"', async () => {
    const { ConfirmModal } = await import('./ConfirmModal');
    render(
      <ConfirmModal
        isOpen={true}
        onClose={vi.fn()}
        onConfirm={vi.fn()}
        title="Warning"
        message="Proceed with caution"
        confirmColor="warning"
      />
    );
    const confirmBtn = screen.getByText('Confirm');
    expect(confirmBtn).toHaveAttribute('data-variant', 'secondary');
  });
});
