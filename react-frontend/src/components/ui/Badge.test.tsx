// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { Badge } from './Badge';

// Badge is a thin wrapper over HeroUI Badge. No context imports.

describe('Badge', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders children (the anchor element) without crashing', () => {
    render(
      <Badge content="3">
        <button>Inbox</button>
      </Badge>,
    );
    expect(screen.getByRole('button', { name: 'Inbox' })).toBeInTheDocument();
  });

  it('renders badge content label when content is a non-empty string', () => {
    render(
      <Badge content="5">
        <button>Messages</button>
      </Badge>,
    );
    expect(screen.getByText('5')).toBeInTheDocument();
  });

  it('renders badge content label when content is a number', () => {
    render(
      <Badge content={99}>
        <button>Alerts</button>
      </Badge>,
    );
    expect(screen.getByText('99')).toBeInTheDocument();
  });

  it('does not render a label when content is empty string', () => {
    const { container } = render(
      <Badge content="">
        <button>Empty</button>
      </Badge>,
    );
    // badgeContent is '' → label is skipped; the badge element renders with no
    // child content. Verify the badge span has no child elements.
    const badgeEl = container.querySelector('[data-slot="badge"]');
    expect(badgeEl).not.toBeNull();
    expect(badgeEl!.children.length).toBe(0);
  });

  it('does not render a label when isDot=true (content suppressed)', () => {
    render(
      <Badge content="10" isDot>
        <button>Dot badge</button>
      </Badge>,
    );
    // isDot sets badgeContent = undefined → no label rendered
    expect(screen.queryByText('10')).not.toBeInTheDocument();
  });

  it('renders children without content prop', () => {
    render(
      <Badge>
        <span data-testid="avatar">A</span>
      </Badge>,
    );
    expect(screen.getByTestId('avatar')).toBeInTheDocument();
  });

  it('maps color "primary" to "accent" (renders without crash)', () => {
    render(
      <Badge content="1" color="primary">
        <button>Primary</button>
      </Badge>,
    );
    expect(screen.getByRole('button', { name: 'Primary' })).toBeInTheDocument();
  });

  it('maps color "secondary" to "default" (renders without crash)', () => {
    render(
      <Badge content="1" color="secondary">
        <button>Secondary</button>
      </Badge>,
    );
    expect(screen.getByRole('button', { name: 'Secondary' })).toBeInTheDocument();
  });

  it('maps color "success" passthrough (renders without crash)', () => {
    render(
      <Badge content="1" color="success">
        <button>Success</button>
      </Badge>,
    );
    expect(screen.getByRole('button', { name: 'Success' })).toBeInTheDocument();
  });

  it('maps variant "flat" to "soft" (renders without crash)', () => {
    render(
      <Badge content="2" variant="flat">
        <button>Flat</button>
      </Badge>,
    );
    expect(screen.getByRole('button', { name: 'Flat' })).toBeInTheDocument();
  });

  it('maps variant "faded" to "secondary" (renders without crash)', () => {
    render(
      <Badge content="2" variant="faded">
        <button>Faded</button>
      </Badge>,
    );
    expect(screen.getByRole('button', { name: 'Faded' })).toBeInTheDocument();
  });

  it('maps variant "shadow" to "primary" (renders without crash)', () => {
    render(
      <Badge content="2" variant="shadow">
        <button>Shadow</button>
      </Badge>,
    );
    expect(screen.getByRole('button', { name: 'Shadow' })).toBeInTheDocument();
  });

  it('maps variant "solid" to "primary" (renders without crash)', () => {
    render(
      <Badge content="2" variant="solid">
        <button>Solid</button>
      </Badge>,
    );
    expect(screen.getByRole('button', { name: 'Solid' })).toBeInTheDocument();
  });

  it('sets opacity-0 and pointer-events-none class when isInvisible=true', () => {
    render(
      <Badge content="7" isInvisible>
        <button>Invisible</button>
      </Badge>,
    );
    // The badge element should carry opacity-0 class
    const badge = document.querySelector('[data-invisible="true"]');
    expect(badge).not.toBeNull();
  });

  it('does NOT set data-invisible when isInvisible is false/omitted', () => {
    render(
      <Badge content="7">
        <button>Visible</button>
      </Badge>,
    );
    expect(document.querySelector('[data-invisible="true"]')).toBeNull();
  });

  it('adds outline classes when showOutline=true', () => {
    render(
      <Badge content="3" showOutline>
        <button>Outlined</button>
      </Badge>,
    );
    // showOutline appends 'border-2 border-background' to badge className
    const badgeEl = document.querySelector('.border-2');
    expect(badgeEl).not.toBeNull();
  });

  it('accepts a React node as content and renders it as a label', () => {
    render(
      <Badge content={<span data-testid="icon-content">★</span>}>
        <button>With icon</button>
      </Badge>,
    );
    expect(screen.getByTestId('icon-content')).toBeInTheDocument();
  });

  it('applies classNames.base to the anchor wrapper', () => {
    const { container } = render(
      <Badge content="1" classNames={{ base: 'anchor-class' }}>
        <button>Classed</button>
      </Badge>,
    );
    expect(container.querySelector('.anchor-class')).not.toBeNull();
  });
});
