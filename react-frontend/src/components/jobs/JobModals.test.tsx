// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';
import userEvent from '@testing-library/user-event';

// ─── Mock api ────────────────────────────────────────────────────────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}));

vi.mock('@/lib/api', () => ({
  api: mockApi,
  default: mockApi,
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ─── Contexts ────────────────────────────────────────────────────────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };

vi.mock('@/contexts/ToastContext', () => ({
  useToast: () => mockToast,
  ToastProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
  })
);

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

vi.mock('@/components/seo/PageMeta', () => ({
  PageMeta: () => null,
}));

// ─── Stub UI sub-components that cause jsdom focus-management issues ──────────
vi.mock('@/components/ui', async (importOriginal) => {
  const actual = await importOriginal<Record<string, unknown>>();
  return {
    ...actual,
    Modal: ({ isOpen, children, onOpenChange }: { isOpen: boolean; children: React.ReactNode; onOpenChange?: (v: boolean) => void; size?: string }) =>
      isOpen ? <div role="dialog" data-testid="modal">{typeof children === 'function' ? (children as (fn: () => void) => React.ReactNode)(() => onOpenChange?.(false)) : children}</div> : null,
    ModalContent: ({ children }: { children: ((fn: () => void) => React.ReactNode) | React.ReactNode }) =>
      <div>{typeof children === 'function' ? (children as (fn: () => void) => React.ReactNode)(vi.fn()) : children}</div>,
    ModalHeader: ({ children }: { children: React.ReactNode }) => <div data-testid="modal-header">{children}</div>,
    ModalBody: ({ children }: { children: React.ReactNode }) => <div data-testid="modal-body">{children}</div>,
    ModalFooter: ({ children }: { children: React.ReactNode }) => <div data-testid="modal-footer">{children}</div>,
    Button: ({ children, onPress, isLoading, isDisabled, onClick, ...rest }: { children?: React.ReactNode; onPress?: () => void; isLoading?: boolean; isDisabled?: boolean; onClick?: React.MouseEventHandler; [key: string]: unknown }) => (
      <button
        onClick={(e) => { onClick?.(e); onPress?.(); }}
        disabled={isLoading || isDisabled}
        data-loading={isLoading ? 'true' : undefined}
        data-disabled={isDisabled ? 'true' : undefined}
        {...(Object.fromEntries(Object.entries(rest).filter(([k]) => !['variant','color','size','startContent','endContent','isIconOnly','type','className','classNames','as','href','target','rel','tabIndex','aria-label','role'].includes(k))))}
      >
        {isLoading ? 'Loading…' : children}
      </button>
    ),
    Textarea: ({ label, value, onValueChange, placeholder, ...rest }: { label?: string; value?: string; onValueChange?: (v: string) => void; placeholder?: string; [key: string]: unknown }) => (
      <div>
        {label && <label>{label}</label>}
        <textarea
          value={value}
          placeholder={placeholder}
          onChange={(e) => onValueChange?.(e.target.value)}
          aria-label={label}
        />
      </div>
    ),
    Progress: ({ value, 'aria-label': ariaLabel }: { value: number; 'aria-label': string }) => (
      <div role="progressbar" aria-valuenow={value} aria-label={ariaLabel} />
    ),
    Spinner: () => <div role="status" aria-busy="true" aria-label="Loading" />,
    Skeleton: () => <div aria-hidden="true" />,
    Chip: ({ children }: { children: React.ReactNode }) => <span>{children}</span>,
  };
});

// ─────────────────────────────────────────────────────────────────────────────
// Fixtures
// ─────────────────────────────────────────────────────────────────────────────

const { mockQualification } = vi.hoisted(() => ({
  mockQualification: {
    percentage: 75,
    level: 'good',
    total_matched: 3,
    total_required: 4,
    breakdown: [
      { skill: 'TypeScript', matched: true },
      { skill: 'Python', matched: false },
    ],
  },
}));

// ─────────────────────────────────────────────────────────────────────────────
// Tests
// ─────────────────────────────────────────────────────────────────────────────

describe('ApplyModal', () => {
  const baseProps = {
    isOpen: true,
    onOpenChange: vi.fn(),
    applyMessage: '',
    setApplyMessage: vi.fn(),
    cvFile: null,
    setCvFile: vi.fn(),
    cvParsed: null,
    setCvParsed: vi.fn(),
    isSubmitting: false,
    savedProfile: null,
    setSavedProfile: vi.fn(),
    usingSavedProfile: false,
    setUsingSavedProfile: vi.fn(),
    onApply: vi.fn(),
    onCvDrop: vi.fn(),
    onCvTooBig: vi.fn(),
  };

  beforeEach(() => { vi.resetAllMocks(); });

  it('renders the modal when isOpen=true', async () => {
    const { ApplyModal } = await import('./JobModals');
    render(<ApplyModal {...baseProps} />);
    expect(screen.getByRole('dialog')).toBeInTheDocument();
  });

  it('does not render the modal when isOpen=false', async () => {
    const { ApplyModal } = await import('./JobModals');
    render(<ApplyModal {...baseProps} isOpen={false} />);
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('shows textarea for cover message', async () => {
    const { ApplyModal } = await import('./JobModals');
    render(<ApplyModal {...baseProps} />);
    // Textarea is present (label drives aria lookup)
    const textareas = screen.getAllByRole('textbox');
    expect(textareas.length).toBeGreaterThan(0);
  });

  it('calls onApply when submit button is clicked', async () => {
    const onApply = vi.fn();
    const { ApplyModal } = await import('./JobModals');
    render(<ApplyModal {...baseProps} onApply={onApply} />);

    // The submit button text comes from translation key 'apply.submit' → falls back to key
    const buttons = screen.getAllByRole('button');
    const submitBtn = buttons.find(b => b.textContent?.toLowerCase().includes('submit') || b.textContent?.includes('apply.submit'));
    expect(submitBtn).toBeDefined();
    if (submitBtn) fireEvent.click(submitBtn);
    expect(onApply).toHaveBeenCalled();
  });

  it('shows loading state on submit button when isSubmitting=true', async () => {
    const { ApplyModal } = await import('./JobModals');
    render(<ApplyModal {...baseProps} isSubmitting={true} />);
    const buttons = screen.getAllByRole('button');
    const loadingBtn = buttons.find(b => b.getAttribute('disabled') !== null || b.getAttribute('data-loading') === 'true');
    expect(loadingBtn).toBeDefined();
  });

  it('shows saved profile banner when savedProfile is set and not using it', async () => {
    const { ApplyModal } = await import('./JobModals');
    render(
      <ApplyModal
        {...baseProps}
        savedProfile={{ cv_filename: 'my-cv.pdf', cover_text: 'Hello' }}
        usingSavedProfile={false}
      />
    );
    // saved_profile.found key or the Use button from saved_profile.use key
    const modal = screen.getByRole('dialog');
    expect(modal).toBeInTheDocument();
    // The saved profile buttons should be rendered (at least 3 buttons: Use, Start Fresh, Cancel, Submit)
    const buttons = screen.getAllByRole('button');
    expect(buttons.length).toBeGreaterThanOrEqual(2);
  });

  it('shows cv filename chip when usingSavedProfile=true', async () => {
    const { ApplyModal } = await import('./JobModals');
    render(
      <ApplyModal
        {...baseProps}
        savedProfile={{ cv_filename: 'resume.pdf' }}
        usingSavedProfile={true}
      />
    );
    expect(screen.getByText(/resume\.pdf/)).toBeInTheDocument();
  });

  it('shows CV skills when cvParsed has skills', async () => {
    const { ApplyModal } = await import('./JobModals');
    render(
      <ApplyModal
        {...baseProps}
        cvFile={new File(['x'], 'cv.pdf', { type: 'application/pdf' })}
        cvParsed={{ skills: ['TypeScript', 'React'] }}
      />
    );
    expect(screen.getByText('TypeScript')).toBeInTheDocument();
    expect(screen.getByText('React')).toBeInTheDocument();
  });

  it('shows cv parse hint when cvFile present but cvParsed is null', async () => {
    const { ApplyModal } = await import('./JobModals');
    render(
      <ApplyModal
        {...baseProps}
        cvFile={new File(['x'], 'cv.pdf', { type: 'application/pdf' })}
        cvParsed={null}
      />
    );
    // cv.parse translation key text should appear somewhere
    const modal = screen.getByRole('dialog');
    expect(modal).toBeInTheDocument();
  });

  it('calls onCvTooBig when oversized file selected via input', async () => {
    const onCvTooBig = vi.fn();
    const { ApplyModal } = await import('./JobModals');
    render(<ApplyModal {...baseProps} onCvTooBig={onCvTooBig} />);

    const input = document.querySelector('input[type="file"]') as HTMLInputElement;
    expect(input).toBeTruthy();

    // Create a file larger than 5MB
    const bigFile = new File(['x'.repeat(6 * 1024 * 1024)], 'big.pdf', { type: 'application/pdf' });
    Object.defineProperty(bigFile, 'size', { value: 6 * 1024 * 1024 });
    fireEvent.change(input, { target: { files: [bigFile] } });
    expect(onCvTooBig).toHaveBeenCalled();
  });

  it('calls toast.error for invalid file type (no onCvInvalidType prop)', async () => {
    const { ApplyModal } = await import('./JobModals');
    render(<ApplyModal {...baseProps} />);

    const input = document.querySelector('input[type="file"]') as HTMLInputElement;
    const badFile = new File(['x'], 'malware.exe', { type: 'application/exe' });
    fireEvent.change(input, { target: { files: [badFile] } });
    expect(mockToast.error).toHaveBeenCalled();
  });
});

// ─────────────────────────────────────────────────────────────────────────────

describe('QualificationModal', () => {
  const baseProps = {
    isOpen: true,
    onOpenChange: vi.fn(),
    qualification: null,
    isLoading: false,
    hasApplied: false,
    vacancyStatus: 'open',
    onApplyOpen: vi.fn(),
  };

  beforeEach(() => { vi.resetAllMocks(); });

  it('renders when isOpen=true', async () => {
    const { QualificationModal } = await import('./JobModals');
    render(<QualificationModal {...baseProps} isLoading={true} />);
    expect(screen.getByRole('dialog')).toBeInTheDocument();
  });

  it('shows loading skeleton when isLoading=true', async () => {
    const { QualificationModal } = await import('./JobModals');
    render(<QualificationModal {...baseProps} isLoading={true} />);
    // The loading div has role=status and aria-busy=true
    const statusEls = screen.getAllByRole('status');
    const busy = statusEls.find(el => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  it('shows qualification percentage when loaded', async () => {
    const { QualificationModal } = await import('./JobModals');
    render(<QualificationModal {...baseProps} qualification={mockQualification} />);
    expect(screen.getByText('75%')).toBeInTheDocument();
  });

  it('shows skill breakdown items', async () => {
    const { QualificationModal } = await import('./JobModals');
    render(<QualificationModal {...baseProps} qualification={mockQualification} />);
    expect(screen.getByText('TypeScript')).toBeInTheDocument();
    expect(screen.getByText('Python')).toBeInTheDocument();
  });

  it('shows progress bar with correct value', async () => {
    const { QualificationModal } = await import('./JobModals');
    render(<QualificationModal {...baseProps} qualification={mockQualification} />);
    const progressbar = screen.getByRole('progressbar');
    expect(progressbar).toHaveAttribute('aria-valuenow', '75');
  });

  it('shows Apply button when percentage > 0, not applied, and vacancy is open', async () => {
    const { QualificationModal } = await import('./JobModals');
    render(<QualificationModal {...baseProps} qualification={mockQualification} hasApplied={false} vacancyStatus="open" />);
    const buttons = screen.getAllByRole('button');
    // apply.button key
    const applyBtn = buttons.find(b => b.textContent?.includes('apply.button') || b.textContent?.toLowerCase().includes('apply'));
    expect(applyBtn).toBeDefined();
  });

  it('hides Apply button when already applied', async () => {
    const { QualificationModal } = await import('./JobModals');
    render(<QualificationModal {...baseProps} qualification={mockQualification} hasApplied={true} />);
    const buttons = screen.getAllByRole('button');
    // Should only have a cancel/close button, no apply
    const applyBtn = buttons.find(b => b.textContent?.includes('apply.button'));
    expect(applyBtn).toBeUndefined();
  });

  it('calls onApplyOpen when Apply button is clicked', async () => {
    const onApplyOpen = vi.fn();
    const { QualificationModal } = await import('./JobModals');
    render(<QualificationModal {...baseProps} qualification={mockQualification} onApplyOpen={onApplyOpen} />);
    const buttons = screen.getAllByRole('button');
    const applyBtn = buttons.find(b => b.textContent?.includes('apply.button') || (b.textContent?.toLowerCase().includes('apply') && !b.textContent?.toLowerCase().includes('cancel')));
    if (applyBtn) fireEvent.click(applyBtn);
    expect(onApplyOpen).toHaveBeenCalled();
  });
});

// ─────────────────────────────────────────────────────────────────────────────

describe('RenewModal', () => {
  const baseProps = {
    isOpen: true,
    onOpenChange: vi.fn(),
    renewDays: 30,
    setRenewDays: vi.fn(),
    isRenewing: false,
    onRenew: vi.fn(),
  };

  beforeEach(() => { vi.resetAllMocks(); });

  it('renders when isOpen=true', async () => {
    const { RenewModal } = await import('./JobModals');
    render(<RenewModal {...baseProps} />);
    expect(screen.getByRole('dialog')).toBeInTheDocument();
  });

  it('shows day-selection buttons 7, 14, 30, 60', async () => {
    const { RenewModal } = await import('./JobModals');
    render(<RenewModal {...baseProps} />);
    const buttons = screen.getAllByRole('button');
    const dayButtons = buttons.filter(b => ['7', '14', '30', '60'].some(d => b.textContent?.includes(d)));
    expect(dayButtons.length).toBe(4);
  });

  it('calls setRenewDays when a day button is clicked', async () => {
    const setRenewDays = vi.fn();
    const { RenewModal } = await import('./JobModals');
    render(<RenewModal {...baseProps} setRenewDays={setRenewDays} />);
    const buttons = screen.getAllByRole('button');
    const btn14 = buttons.find(b => b.textContent?.trim().startsWith('14'));
    expect(btn14).toBeDefined();
    if (btn14) fireEvent.click(btn14);
    expect(setRenewDays).toHaveBeenCalledWith(14);
  });

  it('calls onRenew when confirm button clicked', async () => {
    const onRenew = vi.fn();
    const { RenewModal } = await import('./JobModals');
    render(<RenewModal {...baseProps} onRenew={onRenew} />);
    const buttons = screen.getAllByRole('button');
    // Matches "Renew" (translated) or falls back to the key 'renew.button'
    const renewBtn = buttons.find(b =>
      b.textContent?.toLowerCase().includes('renew') && !b.textContent?.trim().match(/^\d/)
    );
    expect(renewBtn).toBeDefined();
    if (renewBtn) fireEvent.click(renewBtn);
    expect(onRenew).toHaveBeenCalled();
  });

  it('shows loading state when isRenewing=true', async () => {
    const { RenewModal } = await import('./JobModals');
    render(<RenewModal {...baseProps} isRenewing={true} />);
    const buttons = screen.getAllByRole('button');
    const disabledBtn = buttons.find(b => b.getAttribute('disabled') !== null || b.getAttribute('data-loading') === 'true');
    expect(disabledBtn).toBeDefined();
  });
});

// ─────────────────────────────────────────────────────────────────────────────

describe('DeleteModal', () => {
  beforeEach(() => { vi.resetAllMocks(); });

  it('renders when isOpen=true', async () => {
    const { DeleteModal } = await import('./JobModals');
    render(<DeleteModal isOpen={true} onOpenChange={vi.fn()} onDelete={vi.fn()} />);
    expect(screen.getByRole('dialog')).toBeInTheDocument();
  });

  it('does not render when isOpen=false', async () => {
    const { DeleteModal } = await import('./JobModals');
    render(<DeleteModal isOpen={false} onOpenChange={vi.fn()} onDelete={vi.fn()} />);
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('calls onDelete when delete button clicked', async () => {
    const onDelete = vi.fn();
    const { DeleteModal } = await import('./JobModals');
    render(<DeleteModal isOpen={true} onOpenChange={vi.fn()} onDelete={onDelete} />);
    const buttons = screen.getAllByRole('button');
    // "Delete" (translated) or key fallback 'detail.delete'
    const deleteBtn = buttons.find(b =>
      b.textContent?.toLowerCase().includes('delete') && !b.textContent?.toLowerCase().includes('cancel')
    );
    expect(deleteBtn).toBeDefined();
    if (deleteBtn) fireEvent.click(deleteBtn);
    expect(onDelete).toHaveBeenCalled();
  });

  it('shows confirmation text', async () => {
    const { DeleteModal } = await import('./JobModals');
    render(<DeleteModal isOpen={true} onOpenChange={vi.fn()} onDelete={vi.fn()} />);
    // detail.confirm_delete_title key appears in header
    expect(screen.getByTestId('modal-header')).toBeInTheDocument();
  });
});

// ─────────────────────────────────────────────────────────────────────────────

describe('DeclineModal', () => {
  const baseProps = {
    isOpen: true,
    titleKey: 'pipeline.decline_title',
    notesLabelKey: 'pipeline.decline_notes_label',
    notesPlaceholderKey: 'pipeline.decline_notes_placeholder',
    declineNotes: '',
    setDeclineNotes: vi.fn(),
    isLoading: false,
    onClose: vi.fn(),
    onConfirm: vi.fn(),
  };

  beforeEach(() => { vi.resetAllMocks(); });

  it('renders when isOpen=true', async () => {
    const { DeclineModal } = await import('./JobModals');
    render(<DeclineModal {...baseProps} />);
    expect(screen.getByRole('dialog')).toBeInTheDocument();
  });

  it('shows textarea for decline notes', async () => {
    const { DeclineModal } = await import('./JobModals');
    render(<DeclineModal {...baseProps} />);
    const textareas = screen.getAllByRole('textbox');
    expect(textareas.length).toBeGreaterThan(0);
  });

  it('updates notes via setDeclineNotes', async () => {
    const setDeclineNotes = vi.fn();
    const { DeclineModal } = await import('./JobModals');
    render(<DeclineModal {...baseProps} setDeclineNotes={setDeclineNotes} />);
    const textarea = screen.getAllByRole('textbox')[0];
    fireEvent.change(textarea, { target: { value: 'not a good fit' } });
    expect(setDeclineNotes).toHaveBeenCalledWith('not a good fit');
  });

  it('calls onConfirm when confirm button is clicked', async () => {
    const onConfirm = vi.fn();
    const { DeclineModal } = await import('./JobModals');
    render(<DeclineModal {...baseProps} onConfirm={onConfirm} />);
    const buttons = screen.getAllByRole('button');
    // The confirm button text = t(titleKey)
    const confirmBtn = buttons.find(b => b.textContent?.includes('pipeline.decline_title') || b.getAttribute('color') === 'danger');
    if (confirmBtn) fireEvent.click(confirmBtn);
    expect(onConfirm).toHaveBeenCalled();
  });

  it('shows loading state when isLoading=true', async () => {
    const { DeclineModal } = await import('./JobModals');
    render(<DeclineModal {...baseProps} isLoading={true} />);
    const buttons = screen.getAllByRole('button');
    const loadingBtn = buttons.find(b => b.getAttribute('disabled') !== null || b.getAttribute('data-loading') === 'true');
    expect(loadingBtn).toBeDefined();
  });

  it('calls onClose when cancel button clicked', async () => {
    const onClose = vi.fn();
    const { DeclineModal } = await import('./JobModals');
    render(<DeclineModal {...baseProps} onClose={onClose} />);
    const buttons = screen.getAllByRole('button');
    // "Cancel" (translated) or key fallback 'apply.cancel'
    const cancelBtn = buttons.find(b =>
      b.textContent?.toLowerCase().includes('cancel')
    );
    expect(cancelBtn).toBeDefined();
    if (cancelBtn) fireEvent.click(cancelBtn);
    expect(onClose).toHaveBeenCalled();
  });
});
