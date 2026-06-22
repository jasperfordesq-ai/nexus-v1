// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

// ── mock @/contexts ───────────────────────────────────────────────────────────
vi.mock('@/contexts', () => createMockContexts());

// ── stable article fixture ────────────────────────────────────────────────────
const FULL_ARTICLE = vi.hoisted(() => ({
  title: 'Legal Documents Hub',
  summary: 'Manage your tenant legal documents from this screen.',
  steps: [
    { label: 'Create a document', detail: 'Use the + button to add a new document.' },
    { label: 'Publish the document', detail: 'Set status to published when ready.' },
  ],
  tips: ['Keep your terms up to date.', 'Always review before publishing.'],
  caution: 'Deleting a document is permanent and cannot be undone.',
  relatedPaths: [
    { label: 'Privacy Policy', path: '/admin/privacy' },
    { label: 'Settings', path: '/admin/settings' },
  ],
}));

const MINIMAL_ARTICLE = vi.hoisted(() => ({
  title: 'Simple Article',
  summary: 'Just a summary, nothing else.',
}));

import { AdminHelpDrawer } from './AdminHelpDrawer';

describe('AdminHelpDrawer — open state', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the dialog panel when isOpen=true', () => {
    render(<AdminHelpDrawer article={FULL_ARTICLE} isOpen onClose={vi.fn()} />);

    const dialog = screen.getByRole('dialog');
    expect(dialog).toBeInTheDocument();
  });

  it('renders article title and summary', () => {
    render(<AdminHelpDrawer article={FULL_ARTICLE} isOpen onClose={vi.fn()} />);

    expect(screen.getByText('Legal Documents Hub')).toBeInTheDocument();
    expect(screen.getByText('Manage your tenant legal documents from this screen.')).toBeInTheDocument();
  });

  it('renders numbered steps', () => {
    render(<AdminHelpDrawer article={FULL_ARTICLE} isOpen onClose={vi.fn()} />);

    expect(screen.getByText('Create a document')).toBeInTheDocument();
    expect(screen.getByText('Publish the document')).toBeInTheDocument();
    expect(screen.getByText('Use the + button to add a new document.')).toBeInTheDocument();
  });

  it('renders tips list', () => {
    render(<AdminHelpDrawer article={FULL_ARTICLE} isOpen onClose={vi.fn()} />);

    expect(screen.getByText('Keep your terms up to date.')).toBeInTheDocument();
    expect(screen.getByText('Always review before publishing.')).toBeInTheDocument();
  });

  it('renders caution block', () => {
    render(<AdminHelpDrawer article={FULL_ARTICLE} isOpen onClose={vi.fn()} />);

    expect(
      screen.getByText('Deleting a document is permanent and cannot be undone.')
    ).toBeInTheDocument();
  });

  it('renders related page chips as links', () => {
    render(<AdminHelpDrawer article={FULL_ARTICLE} isOpen onClose={vi.fn()} />);

    expect(screen.getByText('Privacy Policy')).toBeInTheDocument();
    expect(screen.getByText('Settings')).toBeInTheDocument();
  });

  it('calls onClose when close button clicked', async () => {
    const user = userEvent.setup();
    const onClose = vi.fn();
    render(<AdminHelpDrawer article={FULL_ARTICLE} isOpen onClose={onClose} />);

    const closeBtn = screen.getByRole('button', { name: /close/i });
    await user.click(closeBtn);

    expect(onClose).toHaveBeenCalled();
  });

  it('calls onClose when Escape key pressed', async () => {
    const user = userEvent.setup();
    const onClose = vi.fn();
    render(<AdminHelpDrawer article={FULL_ARTICLE} isOpen onClose={onClose} />);

    await user.keyboard('{Escape}');

    await waitFor(() => {
      expect(onClose).toHaveBeenCalled();
    });
  });

  it('calls onClose when backdrop clicked', async () => {
    const user = userEvent.setup();
    const onClose = vi.fn();
    const { container } = render(
      <AdminHelpDrawer article={FULL_ARTICLE} isOpen onClose={onClose} />
    );

    // The backdrop is the first div inside the fragment (aria-hidden)
    const backdrop = container.querySelector('[aria-hidden="true"]') as HTMLElement;
    expect(backdrop).not.toBeNull();
    await user.click(backdrop);

    expect(onClose).toHaveBeenCalled();
  });
});

describe('AdminHelpDrawer — closed state', () => {
  it('still renders the panel (CSS hides it via translate-x-full) but no backdrop clicks fire', () => {
    // When closed, the dialog panel is in the DOM but shifted off-screen
    render(<AdminHelpDrawer article={FULL_ARTICLE} isOpen={false} onClose={vi.fn()} />);

    // role=dialog is still present
    const dialog = screen.getByRole('dialog', { hidden: true });
    expect(dialog).toBeInTheDocument();
    // panel has inert attribute set
    expect(dialog).toHaveAttribute('inert');
  });
});

describe('AdminHelpDrawer — minimal article (no optional fields)', () => {
  it('renders without crashing when steps/tips/caution/relatedPaths are absent', () => {
    render(<AdminHelpDrawer article={MINIMAL_ARTICLE} isOpen onClose={vi.fn()} />);

    expect(screen.getByText('Simple Article')).toBeInTheDocument();
    expect(screen.getByText('Just a summary, nothing else.')).toBeInTheDocument();
    // No numbered steps, tips, or caution
    expect(screen.queryByText(/how to use/i)).not.toBeInTheDocument();
    expect(screen.queryByText(/tips/i)).not.toBeInTheDocument();
  });
});
