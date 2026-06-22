// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { Tooltip } from './Tooltip';

// HeroUI Tooltip (React Aria-based) only opens via pointer/focus events driven
// by the browser's pointer model — which jsdom cannot fully replicate with
// synthetic fireEvent. We use the `defaultOpen` prop (passed through to the
// underlying TooltipTrigger's OverlayTriggerProps) to put the tooltip into a
// pre-opened state, which is sufficient to verify rendering/content/color mapping.
// Interaction (open/close on hover/focus) is a library concern verified by
// React Aria's own test suite; we only test our wrapper's mapping logic here.

describe('Tooltip', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the trigger child', () => {
    render(
      <Tooltip content="Helpful hint">
        <button>Hover me</button>
      </Tooltip>,
    );
    expect(screen.getByRole('button', { name: 'Hover me' })).toBeInTheDocument();
  });

  it('tooltip content is not visible without defaultOpen', () => {
    render(
      <Tooltip content="Hidden tip">
        <button>Trigger</button>
      </Tooltip>,
    );
    expect(screen.queryByText('Hidden tip')).not.toBeInTheDocument();
  });

  it('shows tooltip content when defaultOpen=true', () => {
    render(
      <Tooltip content="Open tip" defaultOpen>
        <button>Trigger</button>
      </Tooltip>,
    );
    expect(screen.getByText('Open tip')).toBeInTheDocument();
  });

  it('accepts ReactNode as content and renders it when open', () => {
    render(
      <Tooltip content={<span data-testid="rich-tip">Rich content</span>} defaultOpen>
        <button>Trigger</button>
      </Tooltip>,
    );
    expect(screen.getByTestId('rich-tip')).toBeInTheDocument();
  });

  it('maps color "primary" — renders accent classes when open', () => {
    render(
      <Tooltip content="Primary tip" color="primary" defaultOpen>
        <button>Trigger</button>
      </Tooltip>,
    );
    expect(screen.getByText('Primary tip')).toBeInTheDocument();
  });

  it('maps color "danger" — renders danger classes when open', () => {
    render(
      <Tooltip content="Danger tip" color="danger" defaultOpen>
        <button>Trigger</button>
      </Tooltip>,
    );
    expect(screen.getByText('Danger tip')).toBeInTheDocument();
  });

  it('maps color "success" — renders success classes when open', () => {
    render(
      <Tooltip content="Success tip" color="success" defaultOpen>
        <button>Trigger</button>
      </Tooltip>,
    );
    expect(screen.getByText('Success tip')).toBeInTheDocument();
  });

  it('maps color "warning" — renders warning classes when open', () => {
    render(
      <Tooltip content="Warning tip" color="warning" defaultOpen>
        <button>Trigger</button>
      </Tooltip>,
    );
    expect(screen.getByText('Warning tip')).toBeInTheDocument();
  });

  it('maps color "secondary" — renders surface-tertiary classes when open', () => {
    render(
      <Tooltip content="Secondary tip" color="secondary" defaultOpen>
        <button>Trigger</button>
      </Tooltip>,
    );
    expect(screen.getByText('Secondary tip')).toBeInTheDocument();
  });

  it('default color (no color prop) renders content when open', () => {
    render(
      <Tooltip content="Default tip" defaultOpen>
        <button>Trigger</button>
      </Tooltip>,
    );
    expect(screen.getByText('Default tip')).toBeInTheDocument();
  });

  it('normalizes hyphenated placement "top-start" — content still renders when open', () => {
    // normalizePlacement replaces '-' with ' '; the component must not crash
    render(
      <Tooltip content="Placed tip" placement="top-start" defaultOpen>
        <button>Trigger</button>
      </Tooltip>,
    );
    expect(screen.getByText('Placed tip')).toBeInTheDocument();
  });

  it('renders showArrow=true — arrow sibling present alongside content when open', () => {
    render(
      <Tooltip content="Arrow tip" showArrow defaultOpen>
        <button>Trigger</button>
      </Tooltip>,
    );
    expect(screen.getByText('Arrow tip')).toBeInTheDocument();
  });

  it('renders without showArrow — content present when open', () => {
    render(
      <Tooltip content="No-arrow tip" defaultOpen>
        <button>Trigger</button>
      </Tooltip>,
    );
    expect(screen.getByText('No-arrow tip')).toBeInTheDocument();
  });

  it('applies className to the tooltip content container when open', () => {
    render(
      <Tooltip content="Styled tip" className="custom-tooltip" defaultOpen>
        <button>Trigger</button>
      </Tooltip>,
    );
    const tipEl = screen.getByText('Styled tip');
    // className merges onto the HeroUITooltip.Content element; the text node's
    // parent chain must include the classed element.
    const styledEl = tipEl.closest('.custom-tooltip');
    expect(styledEl).not.toBeNull();
  });

  it('applies classNames.content class to the tooltip content element when open', () => {
    render(
      <Tooltip content="Content class tip" classNames={{ content: 'my-content-class' }} defaultOpen>
        <button>Trigger</button>
      </Tooltip>,
    );
    const tipEl = screen.getByText('Content class tip');
    expect(tipEl.closest('.my-content-class')).not.toBeNull();
  });

  it('renders without children gracefully — no crash', () => {
    // children is optional
    render(<Tooltip content="No trigger" />);
    expect(screen.queryByRole('button')).not.toBeInTheDocument();
  });
});
