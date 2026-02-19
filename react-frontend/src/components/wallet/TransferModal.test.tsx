// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@/test/test-utils';
import { TransferModal } from './TransferModal';

// Mock framer-motion
vi.mock('framer-motion', () => {
  const handler = {
    get: (_: any, tag: string) => {
      return ({
        children,
        initial,
        animate,
        exit,
        transition,
        variants,
        whileHover,
        whileTap,
        whileInView,
        ...rest
      }: any) => {
        const Tag = typeof tag === 'string' ? tag : 'div';
        return <Tag {...rest}>{children}</Tag>;
      };
    },
  };
  return {
    motion: new Proxy({}, handler),
    AnimatePresence: ({ children }: any) => children,
  };
});

// Mock the API module
vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn().mockResolvedValue({ success: true, data: { users: [] } }),
    post: vi.fn().mockResolvedValue({ success: true, data: { id: 1 } }),
  },
}));

// Mock logger
vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

const defaultProps = {
  isOpen: true,
  onClose: vi.fn(),
  currentBalance: 50,
  onTransferComplete: vi.fn(),
  initialRecipientId: null,
};

describe('TransferModal', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders modal when isOpen is true', () => {
    render(<TransferModal {...defaultProps} />);
    // "Send Credits" appears in both the h2 heading and the submit button
    const elements = screen.getAllByText('Send Credits');
    expect(elements.length).toBeGreaterThanOrEqual(1);
    expect(screen.getByRole('dialog')).toBeInTheDocument();
  });

  it('does not render modal content when isOpen is false', () => {
    render(<TransferModal {...defaultProps} isOpen={false} />);
    expect(screen.queryByText('Send Credits')).not.toBeInTheDocument();
  });

  it('displays the current balance', () => {
    render(<TransferModal {...defaultProps} />);
    expect(screen.getByText('50 hours')).toBeInTheDocument();
    expect(screen.getByText('Available Balance')).toBeInTheDocument();
  });

  it('shows recipient search input', () => {
    render(<TransferModal {...defaultProps} />);
    expect(
      screen.getByPlaceholderText('Search by name or username...')
    ).toBeInTheDocument();
  });

  it('shows amount input', () => {
    render(<TransferModal {...defaultProps} />);
    expect(screen.getByPlaceholderText('0')).toBeInTheDocument();
  });

  it('shows description textarea', () => {
    render(<TransferModal {...defaultProps} />);
    expect(
      screen.getByPlaceholderText('What is this transfer for?')
    ).toBeInTheDocument();
  });

  it('shows "Exceeds available balance" when amount exceeds balance', () => {
    render(<TransferModal {...defaultProps} currentBalance={10} />);
    const amountInput = screen.getByPlaceholderText('0');
    fireEvent.change(amountInput, { target: { value: '20' } });
    expect(screen.getByText('Exceeds available balance')).toBeInTheDocument();
  });

  it('has a disabled submit button when no recipient selected', () => {
    render(<TransferModal {...defaultProps} />);
    const sendButton = screen.getByRole('button', { name: /send credits/i });
    expect(sendButton).toBeDisabled();
  });

  it('calls onClose when Cancel button is clicked', () => {
    const onClose = vi.fn();
    render(<TransferModal {...defaultProps} onClose={onClose} />);
    const cancelButton = screen.getByRole('button', { name: /cancel/i });
    fireEvent.click(cancelButton);
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it('calls onClose when close (X) button is clicked', () => {
    const onClose = vi.fn();
    render(<TransferModal {...defaultProps} onClose={onClose} />);
    const closeButton = screen.getByLabelText('Close modal');
    fireEvent.click(closeButton);
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it('shows the description character count', () => {
    render(<TransferModal {...defaultProps} />);
    expect(screen.getByText('0/255')).toBeInTheDocument();
  });

  it('has correct ARIA attributes for the modal dialog', () => {
    render(<TransferModal {...defaultProps} />);
    const dialog = screen.getByRole('dialog');
    expect(dialog).toHaveAttribute('aria-modal', 'true');
    expect(dialog).toHaveAttribute('aria-labelledby', 'transfer-modal-title');
  });
});
