// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for TemplatePicker component
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@/test/test-utils';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

import { TemplatePicker } from '../TemplatePicker';

describe('TemplatePicker', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the Template button for the post tab', () => {
    render(<TemplatePicker tab="post" onSelect={vi.fn()} />);
    expect(screen.getByRole('button')).toBeInTheDocument();
  });

  it('renders the Template button for the listing tab', () => {
    render(<TemplatePicker tab="listing" onSelect={vi.fn()} />);
    expect(screen.getByRole('button')).toBeInTheDocument();
  });

  it('renders the Template button for the event tab', () => {
    render(<TemplatePicker tab="event" onSelect={vi.fn()} />);
    expect(screen.getByRole('button')).toBeInTheDocument();
  });

  it('renders nothing for a tab with no templates', () => {
    // Using an unknown tab that has no templates defined
    render(<TemplatePicker tab={'unknown' as 'post'} onSelect={vi.fn()} />);
    // Component returns null, so no button should be rendered
    // (container.firstChild is the HeroUI provider wrapper, not the component)
    expect(screen.queryByRole('button')).not.toBeInTheDocument();
  });

  it('opens dropdown menu when Template button is clicked', async () => {
    render(<TemplatePicker tab="post" onSelect={vi.fn()} />);
    const btn = screen.getByRole('button');
    fireEvent.click(btn);

    // After opening, dropdown items should appear
    await waitFor(() => {
      // post tab has templates: achievement, help, recommend
      expect(screen.getAllByRole('menuitem').length).toBeGreaterThan(0);
    });
  });

  it('calls onSelect with correct content when a template is chosen', async () => {
    const onSelect = vi.fn();
    render(<TemplatePicker tab="listing" onSelect={onSelect} />);

    const btn = screen.getByRole('button');
    fireEvent.click(btn);

    await waitFor(() => {
      const items = screen.getAllByRole('menuitem');
      expect(items.length).toBeGreaterThan(0);
    });

    const items = screen.getAllByRole('menuitem');
    fireEvent.click(items[0]);

    await waitFor(() => {
      expect(onSelect).toHaveBeenCalledWith(
        expect.objectContaining({ content: expect.any(String) })
      );
    });
  });

  it('passes title along with content for listing templates', async () => {
    const onSelect = vi.fn();
    render(<TemplatePicker tab="listing" onSelect={onSelect} />);

    const btn = screen.getByRole('button');
    fireEvent.click(btn);

    await waitFor(() => {
      expect(screen.getAllByRole('menuitem').length).toBeGreaterThan(0);
    });

    const items = screen.getAllByRole('menuitem');
    fireEvent.click(items[0]);

    await waitFor(() => {
      expect(onSelect).toHaveBeenCalledWith(
        expect.objectContaining({ title: expect.any(String) })
      );
    });
  });

  it('renders poll tab templates', () => {
    render(<TemplatePicker tab="poll" onSelect={vi.fn()} />);
    expect(screen.getByRole('button')).toBeInTheDocument();
  });

  it('renders goal tab templates', () => {
    render(<TemplatePicker tab="goal" onSelect={vi.fn()} />);
    expect(screen.getByRole('button')).toBeInTheDocument();
  });
});
