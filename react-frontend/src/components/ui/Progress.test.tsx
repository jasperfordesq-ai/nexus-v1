// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { Progress } from './Progress';

// Progress is a thin wrapper over HeroUI ProgressBar. No context imports.

describe('Progress', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders without crashing with minimal props', () => {
    render(<Progress value={50} aria-label="Loading" />);
    // HeroUI ProgressBar renders a progressbar role
    expect(screen.getByRole('progressbar')).toBeInTheDocument();
  });

  it('renders a label when label prop is provided', () => {
    render(<Progress value={30} label="Upload progress" aria-label="Upload progress" />);
    expect(screen.getByText('Upload progress')).toBeInTheDocument();
  });

  it('does not render a label element when label prop is omitted', () => {
    render(<Progress value={30} aria-label="Loading" />);
    expect(screen.queryByText('Upload progress')).not.toBeInTheDocument();
  });

  it('applies custom className to the root element', () => {
    render(<Progress value={50} aria-label="Loading" className="my-custom-class" />);
    const progressbar = screen.getByRole('progressbar');
    // className is merged onto the root ProgressBar element
    expect(progressbar.className).toMatch(/my-custom-class/);
  });

  it('applies classNames.base alongside className', () => {
    render(
      <Progress
        value={50}
        aria-label="Loading"
        className="class-a"
        classNames={{ base: 'class-b' }}
      />,
    );
    const progressbar = screen.getByRole('progressbar');
    expect(progressbar.className).toMatch(/class-a/);
    expect(progressbar.className).toMatch(/class-b/);
  });

  it('maps legacy color "primary" to "accent" (does not crash)', () => {
    // The color mapping is internal; verify the component renders correctly
    render(<Progress value={40} color="primary" aria-label="Primary" />);
    expect(screen.getByRole('progressbar')).toBeInTheDocument();
  });

  it('maps legacy color "secondary" to "default" (does not crash)', () => {
    render(<Progress value={40} color="secondary" aria-label="Secondary" />);
    expect(screen.getByRole('progressbar')).toBeInTheDocument();
  });

  it('maps color "success" unchanged (does not crash)', () => {
    render(<Progress value={40} color="success" aria-label="Success" />);
    expect(screen.getByRole('progressbar')).toBeInTheDocument();
  });

  it('maps color "danger" unchanged (does not crash)', () => {
    render(<Progress value={40} color="danger" aria-label="Danger" />);
    expect(screen.getByRole('progressbar')).toBeInTheDocument();
  });

  it('renders isStriped without crashing', () => {
    render(<Progress value={60} isStriped aria-label="Striped" />);
    expect(screen.getByRole('progressbar')).toBeInTheDocument();
  });

  it('ignores disableAnimation prop gracefully (no crash)', () => {
    render(<Progress value={70} disableAnimation aria-label="No animation" />);
    expect(screen.getByRole('progressbar')).toBeInTheDocument();
  });

  it('ignores isDisabled prop gracefully (no crash)', () => {
    render(<Progress value={70} isDisabled aria-label="Disabled" />);
    expect(screen.getByRole('progressbar')).toBeInTheDocument();
  });

  it('forwards minValue and maxValue props', () => {
    render(<Progress value={5} minValue={0} maxValue={10} aria-label="Steps" />);
    const progressbar = screen.getByRole('progressbar');
    expect(progressbar).toHaveAttribute('aria-valuemin', '0');
    expect(progressbar).toHaveAttribute('aria-valuemax', '10');
    expect(progressbar).toHaveAttribute('aria-valuenow', '5');
  });

  it('renders aria-label on the progressbar', () => {
    render(<Progress value={50} aria-label="File upload" />);
    expect(screen.getByRole('progressbar')).toHaveAttribute('aria-label', 'File upload');
  });
});
