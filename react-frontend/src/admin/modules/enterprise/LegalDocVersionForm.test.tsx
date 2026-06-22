// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';

// ── stable mock data ──────────────────────────────────────────────────────────
const EDIT_VERSION = vi.hoisted(() => ({
  id: 10,
  document_id: 1,
  version_number: '1.1',
  version_label: 'Minor update',
  content: '<p>existing content</p>',
  content_plain: 'existing content',
  summary_of_changes: 'Fixed a typo',
  effective_date: '2025-01-01',
  is_draft: true,
  is_current: false,
  created_by: 1,
}));

// ── mock adminApi ─────────────────────────────────────────────────────────────
vi.mock('@/admin/api/adminApi', () => ({
  adminLegalDocs: {
    createVersion: vi.fn(),
    updateVersion: vi.fn(),
  },
}));

// ── mock ToastContext (component imports from '@/contexts/ToastContext') ───────
const mockToast = vi.hoisted(() => ({
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
}));

vi.mock('@/contexts/ToastContext', () => ({
  useToast: () => mockToast,
  ToastProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

// ── mock LegalDocEditor (Lexical is heavy & not testable in jsdom) ────────────
// Use vi.hoisted so factory can safely reference the stub
const MockLegalDocEditor = vi.hoisted(() => ({
  __esModule: true,
}));

vi.mock('@/admin/components/LegalDocEditor', () => ({
  LegalDocEditor: (props: { value: string; onChange: (v: string) => void; errorMessage?: string }) => (
    <div>
      <textarea
        data-testid="legal-doc-editor"
        value={props.value}
        onChange={(e) => props.onChange(e.target.value)}
        aria-label="Content editor"
      />
      {props.errorMessage && <span role="alert" data-testid="content-error">{props.errorMessage}</span>}
    </div>
  ),
  default: (props: { value: string; onChange: (v: string) => void; errorMessage?: string }) => (
    <div>
      <textarea
        data-testid="legal-doc-editor"
        value={props.value}
        onChange={(e) => props.onChange(e.target.value)}
        aria-label="Content editor"
      />
      {props.errorMessage && <span role="alert" data-testid="content-error">{props.errorMessage}</span>}
    </div>
  ),
}));

vi.mock('@/admin/components', () => ({
  LegalDocEditor: (props: { value: string; onChange: (v: string) => void; errorMessage?: string }) => (
    <div>
      <textarea
        data-testid="legal-doc-editor"
        value={props.value}
        onChange={(e) => props.onChange(e.target.value)}
        aria-label="Content editor"
      />
      {props.errorMessage && <span role="alert" data-testid="content-error">{props.errorMessage}</span>}
    </div>
  ),
}));

void MockLegalDocEditor; // suppress unused warning

// ── mock ui components that wrap HeroUI ──────────────────────────────────────
vi.mock('@/components/ui', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/components/ui')>();
  return {
    ...actual,
    ModalHeader: ({ children }: { children: React.ReactNode }) => <div role="heading">{children}</div>,
    ModalBody: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
    ModalFooter: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
  };
});

import React from 'react';
import LegalDocVersionForm from './LegalDocVersionForm';
import { adminLegalDocs } from '@/admin/api/adminApi';

const createMock = vi.mocked(adminLegalDocs.createVersion);
const updateMock = vi.mocked(adminLegalDocs.updateVersion);

const defaultProps = {
  documentId: 1,
  onSuccess: vi.fn(),
  onCancel: vi.fn(),
};

describe('LegalDocVersionForm — create mode', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the create title', () => {
    render(<LegalDocVersionForm {...defaultProps} />);
    expect(screen.getByRole('heading')).toHaveTextContent(/create|new version/i);
  });

  it('shows validation errors when submitting empty form', async () => {
    const { container } = render(<LegalDocVersionForm {...defaultProps} />);

    // Submit via native form event to bypass React Aria button pressable interception
    fireEvent.submit(container.querySelector('form')!);

    // Wait for React state to flush — validate() sets errors.content → LegalDocEditor
    // receives errorMessage prop → mock renders role="alert"
    await waitFor(() => {
      const alerts = screen.queryAllByRole('alert');
      expect(alerts.length).toBeGreaterThan(0);
    });

    // API must NOT have been called since validation failed
    expect(createMock).not.toHaveBeenCalled();
  });

  it('calls createVersion with valid form data', async () => {
    createMock.mockResolvedValueOnce({ success: true, data: { id: 99 } } as never);
    const user = userEvent.setup();
    const onSuccess = vi.fn();
    render(<LegalDocVersionForm {...defaultProps} onSuccess={onSuccess} />);

    // Fill required fields
    const inputs = screen.getAllByRole('textbox');
    // First textbox = version_number
    await user.clear(inputs[0]);
    await user.type(inputs[0], '2.0');

    // effective_date is an <input type="date">
    const dateInput = document.querySelector('input[type="date"]') as HTMLInputElement;
    fireEvent.change(dateInput, { target: { value: '2025-06-01' } });

    // Fill content via the mocked editor
    const contentEditor = screen.getByTestId('legal-doc-editor');
    fireEvent.change(contentEditor, { target: { value: '<p>New content</p>' } });

    const submitBtn = screen.getByRole('button', { name: /create|save/i });
    await user.click(submitBtn);

    await waitFor(() => {
      expect(createMock).toHaveBeenCalledWith(
        1,
        expect.objectContaining({ version_number: '2.0', effective_date: '2025-06-01' })
      );
    });
    expect(onSuccess).toHaveBeenCalled();
  });

  it('shows error toast when createVersion fails', async () => {
    createMock.mockResolvedValueOnce({ success: false, error: 'Conflict' } as never);
    const user = userEvent.setup();
    render(<LegalDocVersionForm {...defaultProps} />);

    const inputs = screen.getAllByRole('textbox');
    await user.type(inputs[0], '3.0');

    const dateInput = document.querySelector('input[type="date"]') as HTMLInputElement;
    fireEvent.change(dateInput, { target: { value: '2025-07-01' } });

    const contentEditor = screen.getByTestId('legal-doc-editor');
    fireEvent.change(contentEditor, { target: { value: '<p>content</p>' } });

    await user.click(screen.getByRole('button', { name: /create|save/i }));

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('calls onCancel when cancel button clicked', async () => {
    const user = userEvent.setup();
    const onCancel = vi.fn();
    render(<LegalDocVersionForm {...defaultProps} onCancel={onCancel} />);

    await user.click(screen.getByRole('button', { name: /cancel/i }));
    expect(onCancel).toHaveBeenCalled();
  });

  it('shows draft toggle only in create mode', () => {
    render(<LegalDocVersionForm {...defaultProps} />);
    // Switch for "Save as draft" should be present
    expect(screen.getByRole('switch')).toBeInTheDocument();
  });
});

describe('LegalDocVersionForm — edit mode', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the edit title', () => {
    render(<LegalDocVersionForm {...defaultProps} editVersion={EDIT_VERSION} />);
    expect(screen.getByRole('heading')).toHaveTextContent(/edit|update/i);
  });

  it('populates fields from editVersion', () => {
    render(<LegalDocVersionForm {...defaultProps} editVersion={EDIT_VERSION} />);

    const versionInput = screen.getAllByRole('textbox')[0] as HTMLInputElement;
    expect(versionInput.value).toBe('1.1');
  });

  it('does not show draft toggle in edit mode', () => {
    render(<LegalDocVersionForm {...defaultProps} editVersion={EDIT_VERSION} />);
    // No switch in edit mode
    expect(screen.queryByRole('switch')).not.toBeInTheDocument();
  });

  it('calls updateVersion on submit with valid data', async () => {
    updateMock.mockResolvedValueOnce({ success: true, data: { updated: true } } as never);
    const user = userEvent.setup();
    const onSuccess = vi.fn();
    render(<LegalDocVersionForm {...defaultProps} editVersion={EDIT_VERSION} onSuccess={onSuccess} />);

    // Submit with pre-populated data (content already filled via useEffect)
    const contentEditor = screen.getByTestId('legal-doc-editor');
    expect((contentEditor as HTMLTextAreaElement).value).toBe('<p>existing content</p>');

    await user.click(screen.getByRole('button', { name: /update|save/i }));

    await waitFor(() => {
      expect(updateMock).toHaveBeenCalledWith(
        1,
        10,
        expect.objectContaining({ version_number: '1.1' })
      );
    });
    expect(onSuccess).toHaveBeenCalled();
  });
});
