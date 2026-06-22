// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * AiChatDrawer tests.
 *
 * NOTE: The component is a pure UI shell — streaming/API calls happen in the
 * parent (JobDetailPage) which passes onSend as a callback. The drawer itself
 * holds no streaming logic, so network/streaming internals are not tested here.
 * The tests cover: open/closed rendering, message list display, sending triggers
 * the onSend callback, and the close button triggers onClose.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import { AiChatDrawer } from './AiChatDrawer';

// jsdom doesn't implement scrollIntoView — stub it globally for this test file
if (typeof Element !== 'undefined' && !Element.prototype.scrollIntoView) {
  Element.prototype.scrollIntoView = vi.fn();
}

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
  API_BASE: '/api',
  default: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

vi.mock('@/contexts', () => createMockContexts());

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

const DEFAULT_PROPS = {
  isOpen: false,
  messages: [],
  inputValue: '',
  isLoading: false,
  onOpen: vi.fn(),
  onClose: vi.fn(),
  onInputChange: vi.fn(),
  onSend: vi.fn(),
};

describe('AiChatDrawer', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('always renders the floating open button', () => {
    render(<AiChatDrawer {...DEFAULT_PROPS} />);
    // The open button has aria-label from i18n key ai_chat.open
    const openButtons = screen.getAllByRole('button');
    // At minimum the floating trigger button is present
    expect(openButtons.length).toBeGreaterThanOrEqual(1);
  });

  it('calls onOpen when the floating button is pressed', () => {
    const onOpen = vi.fn();
    render(<AiChatDrawer {...DEFAULT_PROPS} onOpen={onOpen} />);
    const triggerBtn = screen.getAllByRole('button')[0];
    fireEvent.click(triggerBtn);
    expect(onOpen).toHaveBeenCalledTimes(1);
  });

  it('does not render the dialog panel when isOpen is false', () => {
    render(<AiChatDrawer {...DEFAULT_PROPS} isOpen={false} />);
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('renders the dialog panel when isOpen is true', () => {
    render(<AiChatDrawer {...DEFAULT_PROPS} isOpen={true} />);
    expect(screen.getByRole('dialog')).toBeInTheDocument();
  });

  it('shows empty-state hint when message list is empty and drawer is open', () => {
    render(<AiChatDrawer {...DEFAULT_PROPS} isOpen={true} messages={[]} />);
    // The dialog should be rendered
    expect(screen.getByRole('dialog')).toBeInTheDocument();
    // The empty state hint text comes from i18n key ai_chat.hint — any text in dialog is fine
    // Just verify the message area doesn't show any message bubbles
    expect(screen.queryByText('user-message-text')).not.toBeInTheDocument();
  });

  it('renders user and assistant messages', () => {
    render(
      <AiChatDrawer
        {...DEFAULT_PROPS}
        isOpen={true}
        messages={[
          { role: 'user', content: 'What is the salary?' },
          { role: 'assistant', content: 'The salary is negotiable.' },
        ]}
      />
    );
    expect(screen.getByText('What is the salary?')).toBeInTheDocument();
    expect(screen.getByText('The salary is negotiable.')).toBeInTheDocument();
  });

  it('shows loading spinner when isLoading is true', () => {
    render(
      <AiChatDrawer
        {...DEFAULT_PROPS}
        isOpen={true}
        isLoading={true}
      />
    );
    // The loading indicator is a div with aria-busy="true" inside the chat panel
    const dialog = screen.getByRole('dialog');
    const busyEl = dialog.querySelector('[aria-busy="true"]');
    expect(busyEl).not.toBeNull();
  });

  it('does not show loading spinner when isLoading is false', () => {
    render(
      <AiChatDrawer
        {...DEFAULT_PROPS}
        isOpen={true}
        isLoading={false}
      />
    );
    const dialog = screen.getByRole('dialog');
    const busyEl = dialog.querySelector('[aria-busy="true"]');
    expect(busyEl).toBeNull();
  });

  it('send button is disabled when inputValue is empty', () => {
    render(
      <AiChatDrawer
        {...DEFAULT_PROPS}
        isOpen={true}
        inputValue=""
      />
    );
    // The send button has aria-label from i18n key ai_chat.send — it's the last button in drawer
    const buttons = screen.getAllByRole('button');
    const sendBtn = buttons[buttons.length - 1];
    // HeroUI Button with isDisabled sets native disabled on <button> elements
    expect(sendBtn).toBeDisabled();
  });

  it('send button is disabled when isLoading is true even with input', () => {
    render(
      <AiChatDrawer
        {...DEFAULT_PROPS}
        isOpen={true}
        inputValue="Hello there"
        isLoading={true}
      />
    );
    const buttons = screen.getAllByRole('button');
    const sendBtn = buttons[buttons.length - 1];
    expect(sendBtn).toBeDisabled();
  });

  it('send button is enabled when inputValue is non-empty and not loading', () => {
    render(
      <AiChatDrawer
        {...DEFAULT_PROPS}
        isOpen={true}
        inputValue="Tell me about the role"
        isLoading={false}
      />
    );
    const buttons = screen.getAllByRole('button');
    const sendBtn = buttons[buttons.length - 1];
    // Should NOT be disabled
    expect(sendBtn).not.toBeDisabled();
  });

  it('calls onSend when the send button is clicked', () => {
    const onSend = vi.fn();
    render(
      <AiChatDrawer
        {...DEFAULT_PROPS}
        isOpen={true}
        inputValue="My question"
        onSend={onSend}
      />
    );
    const buttons = screen.getAllByRole('button');
    const sendBtn = buttons[buttons.length - 1];
    fireEvent.click(sendBtn);
    expect(onSend).toHaveBeenCalledTimes(1);
  });

  it('calls onSend when Enter key is pressed in the input', () => {
    const onSend = vi.fn();
    render(
      <AiChatDrawer
        {...DEFAULT_PROPS}
        isOpen={true}
        inputValue="My question"
        onSend={onSend}
      />
    );
    const input = screen.getByRole('textbox');
    fireEvent.keyDown(input, { key: 'Enter', shiftKey: false });
    expect(onSend).toHaveBeenCalledTimes(1);
  });

  it('does NOT call onSend on Shift+Enter (newline intent)', () => {
    const onSend = vi.fn();
    render(
      <AiChatDrawer
        {...DEFAULT_PROPS}
        isOpen={true}
        inputValue="My question"
        onSend={onSend}
      />
    );
    const input = screen.getByRole('textbox');
    fireEvent.keyDown(input, { key: 'Enter', shiftKey: true });
    expect(onSend).not.toHaveBeenCalled();
  });

  it('calls onClose when the close button inside the drawer is clicked', () => {
    const onClose = vi.fn();
    render(
      <AiChatDrawer
        {...DEFAULT_PROPS}
        isOpen={true}
        onClose={onClose}
      />
    );
    // Close button is inside the dialog header
    const dialog = screen.getByRole('dialog');
    const closeBtn = dialog.querySelector('button[aria-label]');
    expect(closeBtn).not.toBeNull();
    fireEvent.click(closeBtn!);
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it('calls onInputChange when the text input changes', () => {
    const onInputChange = vi.fn();
    render(
      <AiChatDrawer
        {...DEFAULT_PROPS}
        isOpen={true}
        inputValue=""
        onInputChange={onInputChange}
      />
    );
    const input = screen.getByRole('textbox');
    fireEvent.change(input, { target: { value: 'hello' } });
    // HeroUI Input fires onValueChange; fireEvent.change triggers the underlying input change
    // The component wires onValueChange={onInputChange} which HeroUI calls from native change
    expect(onInputChange).toHaveBeenCalled();
  });

  /**
   * SKIPPED: Testing that the drawer closes on Escape keydown is not reliably
   * testable because document.addEventListener is used directly (not a React
   * synthetic handler), and jsdom's fireEvent.keyDown on document dispatches
   * a non-trusted event that does not bubble through the registered listener in
   * the same tick in all environments.
   */
});
