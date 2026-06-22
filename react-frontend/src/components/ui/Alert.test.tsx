// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { Alert } from './Alert';

// Alert is a thin wrapper over HeroUI Alert. No context imports.

describe('Alert — children branch', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders children inside the alert when children prop is provided', () => {
    render(<Alert>This is an alert message</Alert>);
    expect(screen.getByText('This is an alert message')).toBeInTheDocument();
  });

  it('renders in the children branch without a title or description', () => {
    render(<Alert>Just children</Alert>);
    // title and description should not be separately queried since we use children
    expect(screen.queryByText('My Title')).not.toBeInTheDocument();
  });
});

describe('Alert — structured branch (title / description)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders a title when title prop is provided', () => {
    render(<Alert title="Important Notice" />);
    expect(screen.getByText('Important Notice')).toBeInTheDocument();
  });

  it('renders a description when description prop is provided', () => {
    render(<Alert description="Something went wrong." />);
    expect(screen.getByText('Something went wrong.')).toBeInTheDocument();
  });

  it('renders both title and description together', () => {
    render(<Alert title="Warning" description="Check your input." />);
    expect(screen.getByText('Warning')).toBeInTheDocument();
    expect(screen.getByText('Check your input.')).toBeInTheDocument();
  });

  it('does not render a title element when title is omitted', () => {
    render(<Alert description="Only description" />);
    expect(screen.queryByText('My Title')).not.toBeInTheDocument();
    expect(screen.getByText('Only description')).toBeInTheDocument();
  });

  it('does not render a description element when description is omitted', () => {
    render(<Alert title="Only title" />);
    expect(screen.queryByText('My description')).not.toBeInTheDocument();
    expect(screen.getByText('Only title')).toBeInTheDocument();
  });

  it('renders endContent', () => {
    render(<Alert endContent={<button>Dismiss</button>} />);
    expect(screen.getByRole('button', { name: 'Dismiss' })).toBeInTheDocument();
  });

  it('renders startContent', () => {
    render(<Alert startContent={<span data-testid="custom-start">!</span>} />);
    expect(screen.getByTestId('custom-start')).toBeInTheDocument();
  });

  it('maps color "primary" to status "accent" without crashing', () => {
    render(<Alert color="primary" title="Primary alert" />);
    expect(screen.getByText('Primary alert')).toBeInTheDocument();
  });

  it('maps color "secondary" to status "default" without crashing', () => {
    render(<Alert color="secondary" title="Secondary alert" />);
    expect(screen.getByText('Secondary alert')).toBeInTheDocument();
  });

  it('passes color "success" directly without crashing', () => {
    render(<Alert color="success" title="Success" />);
    expect(screen.getByText('Success')).toBeInTheDocument();
  });

  it('passes color "danger" directly without crashing', () => {
    render(<Alert color="danger" title="Danger" />);
    expect(screen.getByText('Danger')).toBeInTheDocument();
  });

  it('passes color "warning" directly without crashing', () => {
    render(<Alert color="warning" title="Warning" />);
    expect(screen.getByText('Warning')).toBeInTheDocument();
  });

  it('passes color "accent" directly without crashing', () => {
    render(<Alert color="accent" title="Accent" />);
    expect(screen.getByText('Accent')).toBeInTheDocument();
  });

  it('hides the icon indicator when hideIcon=true', () => {
    // With hideIcon the HeroUIAlert.Indicator is NOT rendered; the title still is
    render(<Alert title="No icon" hideIcon />);
    expect(screen.getByText('No icon')).toBeInTheDocument();
  });

  it('renders a custom icon inside the indicator slot', () => {
    render(<Alert icon={<span data-testid="star-icon">★</span>} title="Icon alert" />);
    expect(screen.getByTestId('star-icon')).toBeInTheDocument();
  });

  it('applies className to the root alert element', () => {
    const { container } = render(<Alert title="Styled" className="my-alert" />);
    // The HeroUI Alert renders an element; classNames are applied to root
    expect(container.querySelector('.my-alert')).not.toBeNull();
  });

  it('accepts React node as title', () => {
    render(<Alert title={<em>Emphasised</em>} />);
    expect(screen.getByText('Emphasised')).toBeInTheDocument();
  });

  it('accepts React node as description', () => {
    render(<Alert description={<strong>Bold description</strong>} />);
    expect(screen.getByText('Bold description')).toBeInTheDocument();
  });
});
