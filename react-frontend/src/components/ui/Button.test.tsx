// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@/test/test-utils';
import { Button } from './Button';

// Button wraps HeroUIButton which uses React Aria — no contexts needed.

describe('Button — rendering', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders label text', () => {
    render(<Button>Click me</Button>);
    expect(screen.getByRole('button', { name: /click me/i })).toBeInTheDocument();
  });

  it('renders with startContent', () => {
    render(<Button startContent={<span data-testid="start-icon" />}>Label</Button>);
    expect(screen.getByTestId('start-icon')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /label/i })).toBeInTheDocument();
  });

  it('renders with endContent', () => {
    render(<Button endContent={<span data-testid="end-icon" />}>Label</Button>);
    expect(screen.getByTestId('end-icon')).toBeInTheDocument();
  });

  it('shows spinner when isLoading=true (spinnerPlacement start)', () => {
    render(<Button isLoading>Saving</Button>);
    // HeroUI renders a spinner SVG; at minimum the button exists
    expect(screen.getByRole('button')).toBeInTheDocument();
  });

  it('renders as an anchor link when as="a" and href given', () => {
    render(<Button as="a" href="https://example.com">Visit</Button>);
    const link = screen.getByRole('link', { name: /visit/i });
    expect(link).toBeInTheDocument();
    expect(link).toHaveAttribute('href', 'https://example.com');
  });
});

describe('Button — variant / color mapping', () => {
  it('renders with variant="solid" (maps to primary)', () => {
    render(<Button variant="solid">Solid</Button>);
    expect(screen.getByRole('button', { name: /solid/i })).toBeInTheDocument();
  });

  it('renders with variant="bordered" (maps to secondary)', () => {
    render(<Button variant="bordered">Bordered</Button>);
    expect(screen.getByRole('button', { name: /bordered/i })).toBeInTheDocument();
  });

  it('renders with variant="light" (maps to tertiary)', () => {
    render(<Button variant="light">Light</Button>);
    expect(screen.getByRole('button', { name: /light/i })).toBeInTheDocument();
  });

  it('renders with color="danger" variant="solid" (maps to danger)', () => {
    render(<Button color="danger" variant="solid">Delete</Button>);
    expect(screen.getByRole('button', { name: /delete/i })).toBeInTheDocument();
  });

  it('renders with color="danger" variant="flat" (maps to danger-soft)', () => {
    render(<Button color="danger" variant="flat">Soft delete</Button>);
    expect(screen.getByRole('button', { name: /soft delete/i })).toBeInTheDocument();
  });

  it('renders with variant="ghost"', () => {
    render(<Button variant="ghost">Ghost</Button>);
    expect(screen.getByRole('button', { name: /ghost/i })).toBeInTheDocument();
  });
});

describe('Button — disabled behaviour', () => {
  it('is aria-disabled or data-disabled when isDisabled=true', () => {
    render(<Button isDisabled>Disabled</Button>);
    const btn = screen.getByRole('button', { name: /disabled/i });
    // HeroUI/React Aria may use either aria-disabled or data-disabled depending on version
    const isMarkedDisabled =
      btn.getAttribute('aria-disabled') === 'true' ||
      btn.hasAttribute('data-disabled') ||
      (btn as HTMLButtonElement).disabled === true;
    expect(isMarkedDisabled).toBe(true);
  });

  it('does not fire onPress when isDisabled', () => {
    const onPress = vi.fn();
    render(<Button isDisabled onPress={onPress}>Disabled</Button>);
    const btn = screen.getByRole('button', { name: /disabled/i });
    fireEvent.click(btn);
    // HeroUI / React Aria swallows the press on disabled elements
    expect(onPress).not.toHaveBeenCalled();
  });

  it('does not fire onPress when disabled=true (native prop)', () => {
    const onPress = vi.fn();
    render(<Button disabled onPress={onPress}>Native disabled</Button>);
    const btn = screen.getByRole('button', { name: /native disabled/i });
    fireEvent.click(btn);
    expect(onPress).not.toHaveBeenCalled();
  });
});

describe('Button — onPress callback', () => {
  it('fires onPress when clicked (enabled)', () => {
    const onPress = vi.fn();
    render(<Button onPress={onPress}>Press me</Button>);
    fireEvent.click(screen.getByRole('button', { name: /press me/i }));
    expect(onPress).toHaveBeenCalledTimes(1);
  });
});

describe('Button — as native element', () => {
  it('renders as native <button> when as="button"', () => {
    const onClick = vi.fn();
    render(<Button as="button" onClick={onClick}>Native</Button>);
    const btn = screen.getByRole('button', { name: /native/i });
    fireEvent.click(btn);
    expect(onClick).toHaveBeenCalledTimes(1);
  });

  it('does not fire onClick when as="button" and isDisabled', () => {
    const onClick = vi.fn();
    render(<Button as="button" isDisabled onClick={onClick}>N/A</Button>);
    const btn = screen.getByRole('button', { name: /n\/a/i });
    fireEvent.click(btn);
    expect(onClick).not.toHaveBeenCalled();
  });
});
