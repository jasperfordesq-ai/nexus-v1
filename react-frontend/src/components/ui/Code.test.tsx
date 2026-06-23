// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── Mock contexts ────────────────────────────────────────────────────────────
vi.mock('@/contexts', () => createMockContexts());

// ─────────────────────────────────────────────────────────────────────────────
describe('Code', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders children inside a <code> element', async () => {
    const { Code } = await import('./Code');
    render(<Code>hello world</Code>);
    const el = screen.getByText('hello world');
    expect(el.tagName).toBe('CODE');
  });

  it('applies font-mono class', async () => {
    const { Code } = await import('./Code');
    render(<Code>snippet</Code>);
    const el = screen.getByText('snippet');
    expect(el.className).toMatch(/font-mono/);
  });

  it('defaults to size="sm" (text-sm class)', async () => {
    const { Code } = await import('./Code');
    render(<Code>default size</Code>);
    const el = screen.getByText('default size');
    expect(el.className).toMatch(/text-sm/);
  });

  it('applies text-base for size="md"', async () => {
    const { Code } = await import('./Code');
    render(<Code size="md">medium</Code>);
    const el = screen.getByText('medium');
    expect(el.className).toMatch(/text-base/);
  });

  it('applies text-lg for size="lg"', async () => {
    const { Code } = await import('./Code');
    render(<Code size="lg">large</Code>);
    const el = screen.getByText('large');
    expect(el.className).toMatch(/text-lg/);
  });

  it('merges custom className', async () => {
    const { Code } = await import('./Code');
    render(<Code className="my-custom-class">code</Code>);
    const el = screen.getByText('code');
    expect(el.className).toMatch(/my-custom-class/);
    expect(el.className).toMatch(/font-mono/);
  });

  it('forwards extra HTML attributes', async () => {
    const { Code } = await import('./Code');
    render(<Code data-testid="my-code" aria-label="code block">value</Code>);
    const el = screen.getByTestId('my-code');
    expect(el).toBeInTheDocument();
    expect(el).toHaveAttribute('aria-label', 'code block');
  });

  it('renders React node children (not just strings)', async () => {
    const { Code } = await import('./Code');
    render(
      <Code>
        <span data-testid="inner">nested</span>
      </Code>,
    );
    expect(screen.getByTestId('inner')).toBeInTheDocument();
  });
});
