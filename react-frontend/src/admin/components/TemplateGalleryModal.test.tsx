// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@/test/test-utils';

// Deterministic UI primitives: Modal renders children when open; Tabs become
// role="tab" buttons; Button/Card pass through.
vi.mock('@/components/ui', () => ({
  Modal: ({ isOpen, children }: { isOpen: boolean; children: React.ReactNode }) =>
    isOpen ? <div role="dialog">{children}</div> : null,
  ModalContent: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
  ModalHeader: ({ children }: { children: React.ReactNode }) => <h2>{children}</h2>,
  ModalBody: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
  Card: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
  CardBody: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
  Button: ({ children, onPress }: { children: React.ReactNode; onPress?: () => void }) => (
    <button onClick={onPress}>{children}</button>
  ),
  Tabs: ({
    children,
    onSelectionChange,
  }: {
    children: React.ReactNode;
    onSelectionChange?: (key: string) => void;
  }) => (
    <div role="tablist">
      {(Array.isArray(children) ? children : [children]).map((child) => {
        const id = (child as { props: { id: string } }).props.id;
        return (
          <button key={id} role="tab" data-testid={`cat-${id}`} onClick={() => onSelectionChange?.(id)}>
            {id}
          </button>
        );
      })}
    </div>
  ),
  Tab: () => null,
}));

import { TemplateGalleryModal, type GalleryTemplate } from './TemplateGalleryModal';

const TEMPLATES: GalleryTemplate[] = [
  { id: 1, name: 'Announcement', description: 'One clear message', category: 'starter', content_format: 'html', content: '<p>hi</p>' },
  { id: 2, name: 'Welcome', description: 'Onboarding', category: 'starter', content_format: 'html', content: '<p>welcome</p>' },
  { id: 3, name: 'My saved one', category: 'saved', content_format: 'html', content: '<p>saved</p>' },
];

describe('TemplateGalleryModal', () => {
  beforeEach(() => vi.clearAllMocks());

  it('renders nothing when closed', () => {
    render(<TemplateGalleryModal isOpen={false} onClose={vi.fn()} templates={TEMPLATES} onSelect={vi.fn()} />);
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('shows starter templates by default', () => {
    render(<TemplateGalleryModal isOpen onClose={vi.fn()} templates={TEMPLATES} onSelect={vi.fn()} />);
    expect(screen.getByText('Announcement')).toBeInTheDocument();
    expect(screen.getByText('Welcome')).toBeInTheDocument();
    // A 'saved' template is not in the default 'starter' tab.
    expect(screen.queryByText('My saved one')).not.toBeInTheDocument();
  });

  it('switches category tabs', () => {
    render(<TemplateGalleryModal isOpen onClose={vi.fn()} templates={TEMPLATES} onSelect={vi.fn()} />);
    fireEvent.click(screen.getByTestId('cat-saved'));
    expect(screen.getByText('My saved one')).toBeInTheDocument();
    expect(screen.queryByText('Announcement')).not.toBeInTheDocument();
  });

  it('selecting a template calls onSelect then onClose', () => {
    const onSelect = vi.fn();
    const onClose = vi.fn();
    render(<TemplateGalleryModal isOpen onClose={onClose} templates={TEMPLATES} onSelect={onSelect} />);
    // Both starter cards render a "Use this template" button — click the first.
    fireEvent.click(screen.getAllByText('Use this template')[0]);
    expect(onSelect).toHaveBeenCalledWith(expect.objectContaining({ id: 1, name: 'Announcement' }));
    expect(onClose).toHaveBeenCalled();
  });

  it('shows an empty state for a category with no templates', () => {
    render(<TemplateGalleryModal isOpen onClose={vi.fn()} templates={TEMPLATES} onSelect={vi.fn()} />);
    fireEvent.click(screen.getByTestId('cat-custom'));
    expect(screen.getByText('No templates in this category yet.')).toBeInTheDocument();
  });
});
