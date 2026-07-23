// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeAll, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@/test/test-utils';
import { ConfirmDialogProvider, useConfirm } from './ConfirmDialog';

// Warm the lazy ConfirmDialogSurface chunk once: whichever test opens the
// dialog first otherwise races waitFor's 1s default against the first dynamic
// import, which can exceed it on a cold vitest transform cache.
beforeAll(async () => {
  await import('./ConfirmDialogSurface');
});

// ---------------------------------------------------------------------------
// Helper — a consumer component that calls useConfirm() and triggers the dialog
// ---------------------------------------------------------------------------
interface TriggerProps {
  onResult?: (result: boolean) => void;
  title?: string;
  body?: string;
  status?: 'accent' | 'success' | 'warning' | 'danger';
  confirmLabel?: string;
  cancelLabel?: string;
}

function TriggerButton({
  onResult,
  title = 'Delete item?',
  body,
  status,
  confirmLabel,
  cancelLabel,
}: TriggerProps) {
  const confirm = useConfirm();

  const handleClick = async () => {
    const result = await confirm({ title, body, status, confirmLabel, cancelLabel });
    onResult?.(result);
  };

  return <button onClick={handleClick}>Open dialog</button>;
}

function Setup(props: TriggerProps) {
  return (
    <ConfirmDialogProvider>
      <TriggerButton {...props} />
    </ConfirmDialogProvider>
  );
}

// ---------------------------------------------------------------------------

describe('ConfirmDialogProvider', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders its children', () => {
    render(
      <ConfirmDialogProvider>
        <p>Child content</p>
      </ConfirmDialogProvider>
    );
    expect(screen.getByText('Child content')).toBeInTheDocument();
  });

  it('does not show the dialog before it is triggered', () => {
    render(<Setup title="Are you sure?" />);
    expect(screen.queryByText('Are you sure?')).toBeNull();
  });

  it('shows the dialog title after the trigger button is clicked', async () => {
    render(<Setup title="Delete this?" />);
    fireEvent.click(screen.getByRole('button', { name: 'Open dialog' }));
    await waitFor(() => {
      expect(screen.getByText('Delete this?')).toBeInTheDocument();
    });
  });

  it('shows the body text when body is provided', async () => {
    render(<Setup title="Confirm" body="This action cannot be undone." />);
    fireEvent.click(screen.getByRole('button', { name: 'Open dialog' }));
    await waitFor(() => {
      expect(screen.getByText('This action cannot be undone.')).toBeInTheDocument();
    });
  });

  it('uses custom confirmLabel and cancelLabel when provided', async () => {
    render(<Setup title="Continue?" confirmLabel="Yes, do it" cancelLabel="Never mind" />);
    fireEvent.click(screen.getByRole('button', { name: 'Open dialog' }));
    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Yes, do it' })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: 'Never mind' })).toBeInTheDocument();
    });
  });

  it('resolves TRUE when the confirm button is pressed', async () => {
    // The Confirm button now carries a single resolver (onPress → resolveAndClose(true))
    // and closes via the controlled open state — no competing slot="close"/onOpenChange(false)
    // path. So confirm() deterministically resolves `true`, in both jsdom and a real
    // browser. Regression guard for the bug where the whole app's confirm-gated
    // destructive actions silently no-opped because confirm() resolved false.
    const onResult = vi.fn();
    render(<Setup title="Delete?" onResult={onResult} confirmLabel="Delete" />);
    fireEvent.click(screen.getByRole('button', { name: 'Open dialog' }));

    await waitFor(() => screen.getByRole('button', { name: 'Delete' }));
    fireEvent.click(screen.getByRole('button', { name: 'Delete' }));

    await waitFor(() => expect(onResult).toHaveBeenCalledTimes(1));
    expect(onResult).toHaveBeenCalledWith(true);
    expect(screen.queryByText('Delete?')).toBeNull();
  });

  it('resolves false when the cancel button is pressed', async () => {
    const onResult = vi.fn();
    render(<Setup title="Delete?" onResult={onResult} cancelLabel="Cancel" />);
    fireEvent.click(screen.getByRole('button', { name: 'Open dialog' }));

    await waitFor(() => screen.getByRole('button', { name: 'Cancel' }));
    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));

    await waitFor(() => {
      expect(onResult).toHaveBeenCalledWith(false);
    });
  });

  it('closes (removes title from DOM) after confirm', async () => {
    render(<Setup title="Proceed?" confirmLabel="Proceed" />);
    fireEvent.click(screen.getByRole('button', { name: 'Open dialog' }));

    await waitFor(() => screen.getByRole('button', { name: 'Proceed' }));
    fireEvent.click(screen.getByRole('button', { name: 'Proceed' }));

    await waitFor(() => {
      expect(screen.queryByText('Proceed?')).toBeNull();
    });
  });

  it('closes (removes title from DOM) after cancel', async () => {
    render(<Setup title="Continue?" cancelLabel="No" />);
    fireEvent.click(screen.getByRole('button', { name: 'Open dialog' }));

    await waitFor(() => screen.getByRole('button', { name: 'No' }));
    fireEvent.click(screen.getByRole('button', { name: 'No' }));

    await waitFor(() => {
      expect(screen.queryByText('Continue?')).toBeNull();
    });
  });

  it('can be triggered multiple times in sequence', async () => {
    const onResult = vi.fn();
    render(<Setup title="First?" confirmLabel="OK" onResult={onResult} />);

    // First open + confirm
    fireEvent.click(screen.getByRole('button', { name: 'Open dialog' }));
    await waitFor(() => screen.getByRole('button', { name: 'OK' }));
    fireEvent.click(screen.getByRole('button', { name: 'OK' }));
    await waitFor(() => expect(onResult).toHaveBeenCalledTimes(1));

    // Second open
    fireEvent.click(screen.getByRole('button', { name: 'Open dialog' }));
    await waitFor(() => screen.getByRole('button', { name: 'OK' }));
    fireEvent.click(screen.getByRole('button', { name: 'OK' }));
    await waitFor(() => expect(onResult).toHaveBeenCalledTimes(2));
  });
});

// ---------------------------------------------------------------------------
// useConfirm guard
// ---------------------------------------------------------------------------
describe('useConfirm — outside provider', () => {
  it('throws when called outside <ConfirmDialogProvider>', () => {
    // Suppress the React caught-error console output for this test
    const spy = vi.spyOn(console, 'error').mockImplementation(() => {});

    function BrokenComponent() {
      useConfirm();
      return null;
    }

    expect(() => render(<BrokenComponent />)).toThrow(
      'useConfirm must be used within <ConfirmDialogProvider>'
    );

    spy.mockRestore();
  });
});
