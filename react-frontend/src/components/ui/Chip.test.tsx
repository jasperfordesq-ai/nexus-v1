// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import { Chip } from './Chip';

vi.mock('@/contexts', () => createMockContexts());

describe('Chip', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders children text', () => {
    render(<Chip>Active</Chip>);
    expect(screen.getByText('Active')).toBeInTheDocument();
  });

  it('renders with success color without throwing', () => {
    expect(() => render(<Chip color="success">Verified</Chip>)).not.toThrow();
  });

  it('renders with danger color without throwing', () => {
    expect(() => render(<Chip color="danger">Error</Chip>)).not.toThrow();
  });

  it('renders with warning color without throwing', () => {
    expect(() => render(<Chip color="warning">Pending</Chip>)).not.toThrow();
  });

  it('renders with primary color (maps to accent) without throwing', () => {
    expect(() => render(<Chip color="primary">Primary</Chip>)).not.toThrow();
  });

  it('renders with solid variant (maps to primary) without throwing', () => {
    expect(() => render(<Chip variant="solid">Solid</Chip>)).not.toThrow();
  });

  it('renders with bordered variant (maps to secondary) without throwing', () => {
    expect(() => render(<Chip variant="bordered">Bordered</Chip>)).not.toThrow();
  });

  it('maps the legacy light variant to the documented transparent tertiary style', () => {
    render(<Chip variant="light">Light</Chip>);
    expect(screen.getByText('Light').closest('[data-slot="chip"]')).toHaveClass('chip--tertiary');
  });

  it('maps the legacy flat variant to the documented muted-background soft style', () => {
    render(<Chip variant="flat">Flat</Chip>);
    expect(screen.getByText('Flat').closest('[data-slot="chip"]')).toHaveClass('chip--soft');
  });

  it('renders dot indicator when variant=dot and no startContent', () => {
    const { container } = render(<Chip variant="dot">Dot Chip</Chip>);
    // The dot span has aria-hidden="true" and size classes
    const dot = container.querySelector('[aria-hidden="true"]');
    expect(dot).not.toBeNull();
  });

  it('does NOT render dot when variant=dot but startContent is provided', () => {
    const { container } = render(
      <Chip variant="dot" startContent={<span data-testid="custom-start" />}>
        Custom
      </Chip>
    );
    expect(screen.getByTestId('custom-start')).toBeInTheDocument();
    // The auto-generated dot aria-hidden span should not appear when startContent is set
    const hiddenSpans = container.querySelectorAll(
      'span[aria-hidden="true"].inline-block',
    );
    expect(hiddenSpans).toHaveLength(0);
  });

  it('renders startContent when provided', () => {
    render(
      <Chip startContent={<span data-testid="start">S</span>}>With start</Chip>
    );
    expect(screen.getByTestId('start')).toBeInTheDocument();
  });

  it('renders endContent when provided', () => {
    render(
      <Chip endContent={<span data-testid="end">E</span>}>With end</Chip>
    );
    expect(screen.getByTestId('end')).toBeInTheDocument();
  });

  it('renders avatar when provided', () => {
    render(
      <Chip avatar={<img alt="avatar" data-testid="avatar-img" src="a.jpg" />}>
        With avatar
      </Chip>
    );
    expect(screen.getByTestId('avatar-img')).toBeInTheDocument();
  });

  it('renders a close button when onClose is provided', () => {
    const onClose = vi.fn();
    render(<Chip onClose={onClose}>Closeable</Chip>);
    // CloseButton is rendered with an aria-label from i18n
    const closeBtn = screen.getByRole('button');
    expect(closeBtn).toBeInTheDocument();
  });

  it('calls onClose when close button is pressed', () => {
    const onClose = vi.fn();
    render(<Chip onClose={onClose}>Closeable</Chip>);
    fireEvent.click(screen.getByRole('button'));
    expect(onClose).toHaveBeenCalledOnce();
  });

  it('does NOT render a close button when onClose is not provided', () => {
    render(<Chip>No close</Chip>);
    expect(screen.queryByRole('button')).toBeNull();
  });

  it('applies disabled opacity class when isDisabled=true', () => {
    const { container } = render(<Chip isDisabled>Disabled</Chip>);
    const chip = container.firstChild as HTMLElement;
    // Walk descendants to find element with opacity-50
    const disabledEl = chip?.querySelector('.opacity-50') ?? chip;
    // The class may be on the root chip element itself
    const hasOpacity =
      chip?.className?.includes('opacity-50') ||
      disabledEl?.className?.includes('opacity-50');
    expect(hasOpacity).toBe(true);
  });

  it('applies shadow class when variant=shadow', () => {
    const { container } = render(<Chip variant="shadow">Shadow</Chip>);
    const shadowEl = container.querySelector('.shadow-md');
    expect(shadowEl).not.toBeNull();
  });

  it('applies rounded-full class when radius=full', () => {
    const { container } = render(<Chip radius="full">Pill</Chip>);
    const pillEl = container.querySelector('.rounded-full');
    expect(pillEl).not.toBeNull();
  });

  it('applies rounded-none class when radius=none', () => {
    const { container } = render(<Chip radius="none">Square</Chip>);
    const squareEl = container.querySelector('.rounded-none');
    expect(squareEl).not.toBeNull();
  });

  it('accepts a custom className without throwing', () => {
    expect(() =>
      render(<Chip className="my-chip">Styled</Chip>)
    ).not.toThrow();
  });

  it('renders with an `as` component without throwing', () => {
    expect(() =>
      render(
        <Chip as="a" href="/some-link">
          Link Chip
        </Chip>
      )
    ).not.toThrow();
  });
});
