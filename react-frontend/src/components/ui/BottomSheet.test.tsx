// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { BottomSheet } from './BottomSheet';

// BottomSheet uses @/lib/motion (CSS shim — no framer-motion).
// The HeroUI Modal renders its content into a portal on the document.body;
// screen queries work across portals out of the box.

describe('BottomSheet', () => {
  const onClose = vi.fn();

  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders children when isOpen is true', () => {
    render(
      <BottomSheet isOpen onClose={onClose} title="Sheet title">
        <p>Sheet body content</p>
      </BottomSheet>
    );
    expect(screen.getByText('Sheet body content')).toBeInTheDocument();
  });

  it('does not render children when isOpen is false', () => {
    render(
      <BottomSheet isOpen={false} onClose={onClose} title="Sheet title">
        <p>Hidden content</p>
      </BottomSheet>
    );
    // HeroUI Modal removes content from the DOM when closed
    expect(screen.queryByText('Hidden content')).toBeNull();
  });

  it('renders a title when the title prop is provided', () => {
    render(
      <BottomSheet isOpen onClose={onClose} title="Choose action">
        <p>Options here</p>
      </BottomSheet>
    );
    expect(screen.getByText('Choose action')).toBeInTheDocument();
  });

  it('uses the required visible title as the dialog heading', () => {
    render(
      <BottomSheet isOpen onClose={onClose} title="Required sheet title">
        <p>Content</p>
      </BottomSheet>
    );
    expect(screen.getByRole('heading', { name: 'Required sheet title' })).toBeInTheDocument();
  });

  it('renders with snapPoints="half" without crashing', () => {
    render(
      <BottomSheet isOpen onClose={onClose} title="Half sheet title" snapPoints={['half']}>
        <p>Half sheet</p>
      </BottomSheet>
    );
    expect(screen.getByText('Half sheet')).toBeInTheDocument();
  });

  it('renders with snapPoints="full" without crashing', () => {
    render(
      <BottomSheet isOpen onClose={onClose} title="Full sheet title" snapPoints={['full']}>
        <p>Full sheet</p>
      </BottomSheet>
    );
    expect(screen.getByText('Full sheet')).toBeInTheDocument();
  });

  it('accepts a custom className without crashing', () => {
    render(
      <BottomSheet isOpen onClose={onClose} title="Styled sheet title" className="test-class">
        <p>Styled sheet</p>
      </BottomSheet>
    );
    expect(screen.getByText('Styled sheet')).toBeInTheDocument();
  });

  // NOTE: The drag-to-dismiss logic fires onDragEnd on the internal motion.div.
  // Triggering pointer events in jsdom does not replicate the full pointer-capture
  // + delta math in the CSS-transition motion shim, so the onClose-via-drag path
  // is not reachable in a unit test without synthetic PointerEvent patching.
  // That branch is covered implicitly by the motion shim's own tests.
});
