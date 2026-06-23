// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import React from 'react';

vi.mock('@/contexts', () => {
  const { createMockContexts } = require('@/test/mock-contexts');
  return createMockContexts();
});

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// Stub HeroUI ButtonGroup to a simple div so jsdom doesn't choke on complex
// React Aria internals, while still propagating children and data attributes.
vi.mock('@heroui/react', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@heroui/react')>();
  const FakeButtonGroup = ({
    children,
    variant,
    isDisabled,
    orientation,
    ...rest
  }: {
    children?: React.ReactNode;
    variant?: string;
    isDisabled?: boolean;
    orientation?: string;
    [key: string]: unknown;
  }) => (
    <div
      role="group"
      data-variant={variant}
      data-disabled={isDisabled ? 'true' : undefined}
      data-orientation={orientation}
      {...rest}
    >
      {children}
    </div>
  );
  FakeButtonGroup.Separator = () => <hr data-testid="button-group-separator" />;

  return {
    ...orig,
    ButtonGroup: FakeButtonGroup,
  };
});

describe('ButtonGroup', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders children inside a group', async () => {
    const { ButtonGroup } = await import('./ButtonGroup');
    render(
      <ButtonGroup>
        <button>A</button>
        <button>B</button>
      </ButtonGroup>,
    );
    expect(screen.getByText('A')).toBeInTheDocument();
    expect(screen.getByText('B')).toBeInTheDocument();
  });

  it('renders with role=group', async () => {
    const { ButtonGroup } = await import('./ButtonGroup');
    render(<ButtonGroup><button>X</button></ButtonGroup>);
    expect(screen.getByRole('group')).toBeInTheDocument();
  });

  it('maps variant "flat" → "secondary" on the underlying group', async () => {
    const { ButtonGroup } = await import('./ButtonGroup');
    render(<ButtonGroup variant="flat"><button>Flat</button></ButtonGroup>);
    expect(screen.getByRole('group')).toHaveAttribute('data-variant', 'secondary');
  });

  it('maps variant "bordered" → "outline"', async () => {
    const { ButtonGroup } = await import('./ButtonGroup');
    render(<ButtonGroup variant="bordered"><button>Bordered</button></ButtonGroup>);
    expect(screen.getByRole('group')).toHaveAttribute('data-variant', 'outline');
  });

  it('maps variant "light" → "tertiary"', async () => {
    const { ButtonGroup } = await import('./ButtonGroup');
    render(<ButtonGroup variant="light"><button>Light</button></ButtonGroup>);
    expect(screen.getByRole('group')).toHaveAttribute('data-variant', 'tertiary');
  });

  it('maps variant "solid" → "primary"', async () => {
    const { ButtonGroup } = await import('./ButtonGroup');
    render(<ButtonGroup variant="solid"><button>Solid</button></ButtonGroup>);
    expect(screen.getByRole('group')).toHaveAttribute('data-variant', 'primary');
  });

  it('maps variant "shadow" → "primary"', async () => {
    const { ButtonGroup } = await import('./ButtonGroup');
    render(<ButtonGroup variant="shadow"><button>Shadow</button></ButtonGroup>);
    expect(screen.getByRole('group')).toHaveAttribute('data-variant', 'primary');
  });

  it('maps variant "faded" → "secondary"', async () => {
    const { ButtonGroup } = await import('./ButtonGroup');
    render(<ButtonGroup variant="faded"><button>Faded</button></ButtonGroup>);
    expect(screen.getByRole('group')).toHaveAttribute('data-variant', 'secondary');
  });

  it('passes through unknown variants unchanged', async () => {
    const { ButtonGroup } = await import('./ButtonGroup');
    // e.g. a native v3 variant like "primary" is already valid – passthrough
    render(<ButtonGroup variant="primary"><button>Primary</button></ButtonGroup>);
    expect(screen.getByRole('group')).toHaveAttribute('data-variant', 'primary');
  });

  it('passes through isDisabled prop', async () => {
    const { ButtonGroup } = await import('./ButtonGroup');
    render(<ButtonGroup isDisabled><button>D</button></ButtonGroup>);
    expect(screen.getByRole('group')).toHaveAttribute('data-disabled', 'true');
  });

  it('passes through orientation prop', async () => {
    const { ButtonGroup } = await import('./ButtonGroup');
    render(
      <ButtonGroup orientation="vertical"><button>V</button></ButtonGroup>,
    );
    expect(screen.getByRole('group')).toHaveAttribute('data-orientation', 'vertical');
  });

  it('renders the Separator sub-component', async () => {
    const { ButtonGroup } = await import('./ButtonGroup');
    render(
      <ButtonGroup>
        <button>A</button>
        <ButtonGroup.Separator />
        <button>B</button>
      </ButtonGroup>,
    );
    expect(screen.getByTestId('button-group-separator')).toBeInTheDocument();
  });

  it('strips private v2 props (color, disableAnimation, disableRipple, radius) without crashing', async () => {
    const { ButtonGroup } = await import('./ButtonGroup');
    render(
      <ButtonGroup
        color="primary"
        disableAnimation
        disableRipple
        radius="sm"
      >
        <button>Z</button>
      </ButtonGroup>,
    );
    // Should render successfully — the group exists
    expect(screen.getByRole('group')).toBeInTheDocument();
    // Ensure these v2-only props were NOT forwarded to the DOM element
    expect(screen.getByRole('group')).not.toHaveAttribute('data-color');
    expect(screen.getByRole('group')).not.toHaveAttribute('disableAnimation');
  });
});
