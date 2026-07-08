// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── Contexts ────────────────────────────────────────────────────────────────
vi.mock('@/contexts', () => createMockContexts());

// ─── Stub HeroUI Link so we get a clean anchor to assert on ─────────────────
vi.mock('@heroui/react/link', () => {
  const MockHeroUILink = ({
    children,
    href,
    target,
    rel,
    className,
    ...rest
  }: React.AnchorHTMLAttributes<HTMLAnchorElement> & { children?: React.ReactNode }) => (
    <a href={href} target={target} rel={rel} className={className} {...rest}>
      {children}
    </a>
  );

  // Attach Icon sub-component to mirror HeroUILink.Icon
  const MockIcon = ({ children }: { children?: React.ReactNode }) => (
    <span data-testid="link-icon">{children}</span>
  );
  MockHeroUILink.Icon = MockIcon;

  return {
    Link: MockHeroUILink,
  };
});

// ─────────────────────────────────────────────────────────────────────────────
describe('ui/Link', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders an anchor with the given href', async () => {
    const { Link } = await import('./Link');
    render(<Link href="https://example.com">Visit site</Link>);
    const anchor = screen.getByRole('link', { name: 'Visit site' });
    expect(anchor).toHaveAttribute('href', 'https://example.com');
  });

  it('renders children correctly', async () => {
    const { Link } = await import('./Link');
    render(<Link href="/about">About Us</Link>);
    expect(screen.getByText('About Us')).toBeInTheDocument();
  });

  it('adds target="_blank" and rel="noopener noreferrer" for external links', async () => {
    const { Link } = await import('./Link');
    render(
      <Link href="https://external.com" isExternal>
        External
      </Link>,
    );
    const anchor = screen.getByRole('link', { name: 'External' });
    expect(anchor).toHaveAttribute('target', '_blank');
    expect(anchor).toHaveAttribute('rel', 'noopener noreferrer');
  });

  it('respects explicit target override over isExternal default', async () => {
    const { Link } = await import('./Link');
    render(
      <Link href="https://example.com" isExternal target="_self">
        Custom target
      </Link>,
    );
    const anchor = screen.getByRole('link', { name: 'Custom target' });
    expect(anchor).toHaveAttribute('target', '_self');
  });

  it('respects explicit rel override', async () => {
    const { Link } = await import('./Link');
    render(
      <Link href="https://example.com" isExternal rel="nofollow">
        Custom rel
      </Link>,
    );
    const anchor = screen.getByRole('link', { name: 'Custom rel' });
    expect(anchor).toHaveAttribute('rel', 'nofollow');
  });

  it('does not set target or rel for internal links', async () => {
    const { Link } = await import('./Link');
    render(<Link href="/internal">Internal</Link>);
    const anchor = screen.getByRole('link', { name: 'Internal' });
    expect(anchor).not.toHaveAttribute('target');
    expect(anchor).not.toHaveAttribute('rel');
  });

  it('applies custom className via combineClasses', async () => {
    const { Link } = await import('./Link');
    render(
      <Link href="/styled" className="my-custom-class">
        Styled
      </Link>,
    );
    const anchor = screen.getByRole('link', { name: 'Styled' });
    expect(anchor.className).toContain('my-custom-class');
  });

  it('adds "underline" class when underline="always"', async () => {
    const { Link } = await import('./Link');
    render(
      <Link href="/underlined" underline="always">
        Underlined
      </Link>,
    );
    const anchor = screen.getByRole('link', { name: 'Underlined' });
    expect(anchor.className).toContain('underline');
  });

  it('adds "block" class when isBlock=true', async () => {
    const { Link } = await import('./Link');
    render(
      <Link href="/block" isBlock>
        Block link
      </Link>,
    );
    const anchor = screen.getByRole('link', { name: 'Block link' });
    expect(anchor.className).toContain('block');
  });

  it('renders anchor icon when showAnchorIcon=true', async () => {
    const { Link } = await import('./Link');
    render(
      <Link href="/icon" showAnchorIcon anchorIcon={<span>→</span>}>
        With icon
      </Link>,
    );
    expect(screen.getByTestId('link-icon')).toBeInTheDocument();
  });

  it('does not render anchor icon when showAnchorIcon is omitted', async () => {
    const { Link } = await import('./Link');
    render(<Link href="/no-icon">No icon</Link>);
    expect(screen.queryByTestId('link-icon')).not.toBeInTheDocument();
  });

  it('Link.Icon is accessible as a static property', async () => {
    const { Link } = await import('./Link');
    expect(Link.Icon).toBeDefined();
  });
});
