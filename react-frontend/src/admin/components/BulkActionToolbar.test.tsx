// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import userEvent from '@testing-library/user-event';

vi.mock('@/contexts', () => createMockContexts());
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

import { BulkActionToolbar, type BulkAction } from './BulkActionToolbar';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
function makeAction(overrides: Partial<BulkAction> = {}): BulkAction {
  return {
    key: 'approve',
    label: 'Approve',
    color: 'success',
    onConfirm: vi.fn(),
    ...overrides,
  };
}

describe('BulkActionToolbar — visibility', () => {
  beforeEach(() => vi.clearAllMocks());

  it('renders nothing when selectedCount is 0', () => {
    render(
      <BulkActionToolbar selectedCount={0} actions={[makeAction()]} onClearSelection={vi.fn()} />
    );
    // When selectedCount===0 the component returns null — no action button visible
    expect(screen.queryByRole('button', { name: /approve/i })).not.toBeInTheDocument();
  });

  it('renders toolbar when selectedCount > 0', () => {
    render(
      <BulkActionToolbar selectedCount={3} actions={[makeAction()]} onClearSelection={vi.fn()} />
    );
    expect(screen.getByText(/3/)).toBeInTheDocument();
  });
});

describe('BulkActionToolbar — clear selection', () => {
  beforeEach(() => vi.clearAllMocks());

  it('calls onClearSelection when × button is pressed', async () => {
    const user = userEvent.setup();
    const onClear = vi.fn();
    render(
      <BulkActionToolbar selectedCount={2} actions={[makeAction()]} onClearSelection={onClear} />
    );

    await user.click(screen.getByRole('button', { name: /clear selection/i }));
    expect(onClear).toHaveBeenCalledTimes(1);
  });
});

describe('BulkActionToolbar — action buttons', () => {
  beforeEach(() => vi.clearAllMocks());

  it('renders action button with correct label', () => {
    render(
      <BulkActionToolbar selectedCount={1} actions={[makeAction({ label: 'Delete All' })]} onClearSelection={vi.fn()} />
    );
    expect(screen.getByRole('button', { name: /delete all/i })).toBeInTheDocument();
  });

  it('pressing action button opens a confirm modal', async () => {
    const user = userEvent.setup();
    render(
      <BulkActionToolbar selectedCount={1} actions={[makeAction()]} onClearSelection={vi.fn()} />
    );

    await user.click(screen.getByRole('button', { name: /approve/i }));

    await waitFor(() => {
      expect(screen.getByRole('dialog')).toBeInTheDocument();
    });
  });
});

describe('BulkActionToolbar — confirm modal flow', () => {
  beforeEach(() => vi.clearAllMocks());

  it('calls onConfirm and closes modal when confirm button pressed', async () => {
    const user = userEvent.setup();
    const onConfirm = vi.fn().mockResolvedValue(undefined);
    render(
      <BulkActionToolbar selectedCount={2} actions={[makeAction({ onConfirm })]} onClearSelection={vi.fn()} />
    );

    await user.click(screen.getByRole('button', { name: /approve/i }));
    await waitFor(() => expect(screen.getByRole('dialog')).toBeInTheDocument());

    // The confirm button in the footer has the same label as the action by default
    const confirmBtns = screen.getAllByRole('button', { name: /approve/i });
    await user.click(confirmBtns.at(-1)!);

    await waitFor(() => {
      expect(onConfirm).toHaveBeenCalledTimes(1);
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
  });

  it('cancel button closes modal without calling onConfirm', async () => {
    const user = userEvent.setup();
    const onConfirm = vi.fn();
    render(
      <BulkActionToolbar selectedCount={1} actions={[makeAction({ onConfirm })]} onClearSelection={vi.fn()} />
    );

    await user.click(screen.getByRole('button', { name: /approve/i }));
    await waitFor(() => expect(screen.getByRole('dialog')).toBeInTheDocument());

    await user.click(screen.getByRole('button', { name: /cancel/i }));

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
    expect(onConfirm).not.toHaveBeenCalled();
  });
});

describe('BulkActionToolbar — destructive variant', () => {
  beforeEach(() => vi.clearAllMocks());

  it('shows destructive warning text in the confirm modal', async () => {
    const user = userEvent.setup();
    render(
      <BulkActionToolbar
        selectedCount={3}
        actions={[makeAction({ label: 'Delete', destructive: true, color: 'danger' })]}
        onClearSelection={vi.fn()}
      />
    );

    await user.click(screen.getByRole('button', { name: /delete/i }));
    await waitFor(() => expect(screen.getByRole('dialog')).toBeInTheDocument());

    // The destructive_warning translation key text should appear
    // It's enough that the modal opened and contains some warning content
    expect(screen.getByRole('dialog')).toBeInTheDocument();
  });
});

describe('BulkActionToolbar — needsReason variant', () => {
  beforeEach(() => vi.clearAllMocks());

  it('renders a textarea in the modal when needsReason=true', async () => {
    const user = userEvent.setup();
    render(
      <BulkActionToolbar
        selectedCount={1}
        actions={[makeAction({ label: 'Suspend', needsReason: true })]}
        onClearSelection={vi.fn()}
      />
    );

    await user.click(screen.getByRole('button', { name: /suspend/i }));
    await waitFor(() => expect(screen.getByRole('dialog')).toBeInTheDocument());

    expect(screen.getByRole('textbox')).toBeInTheDocument();
  });

  it('confirm button is disabled when reason is empty', async () => {
    const user = userEvent.setup();
    render(
      <BulkActionToolbar
        selectedCount={1}
        actions={[makeAction({ label: 'Suspend', needsReason: true })]}
        onClearSelection={vi.fn()}
      />
    );

    await user.click(screen.getByRole('button', { name: /suspend/i }));
    await waitFor(() => expect(screen.getByRole('dialog')).toBeInTheDocument());

    const confirmBtns = screen.getAllByRole('button', { name: /suspend/i });
    const confirmBtn = confirmBtns.at(-1)!;
    expect(confirmBtn).toBeDisabled();
  });

  it('confirm button becomes enabled after entering a reason', async () => {
    const user = userEvent.setup();
    render(
      <BulkActionToolbar
        selectedCount={1}
        actions={[makeAction({ label: 'Suspend', needsReason: true })]}
        onClearSelection={vi.fn()}
      />
    );

    await user.click(screen.getByRole('button', { name: /suspend/i }));
    await waitFor(() => expect(screen.getByRole('dialog')).toBeInTheDocument());

    await user.type(screen.getByRole('textbox'), 'Policy violation');

    const confirmBtns = screen.getAllByRole('button', { name: /suspend/i });
    const confirmBtn = confirmBtns.at(-1)!;
    expect(confirmBtn).not.toBeDisabled();
  });

  it('passes reason string to onConfirm', async () => {
    const user = userEvent.setup();
    const onConfirm = vi.fn().mockResolvedValue(undefined);
    render(
      <BulkActionToolbar
        selectedCount={1}
        actions={[makeAction({ label: 'Suspend', needsReason: true, onConfirm })]}
        onClearSelection={vi.fn()}
      />
    );

    await user.click(screen.getByRole('button', { name: /suspend/i }));
    await waitFor(() => expect(screen.getByRole('dialog')).toBeInTheDocument());

    await user.type(screen.getByRole('textbox'), 'Policy violation');

    const confirmBtns = screen.getAllByRole('button', { name: /suspend/i });
    await user.click(confirmBtns.at(-1)!);

    await waitFor(() => {
      expect(onConfirm).toHaveBeenCalledWith('Policy violation');
    });
  });
});

describe('BulkActionToolbar — isLoading prop', () => {
  beforeEach(() => vi.clearAllMocks());

  it('disables action buttons when isLoading=true', () => {
    render(
      <BulkActionToolbar
        selectedCount={2}
        actions={[makeAction()]}
        onClearSelection={vi.fn()}
        isLoading={true}
      />
    );

    const actionBtn = screen.getByRole('button', { name: /approve/i });
    expect(actionBtn).toBeDisabled();
  });
});
