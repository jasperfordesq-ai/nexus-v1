// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

// ── Hoisted stable refs (must use vi.hoisted to be available inside vi.mock factories) ───────────
const {
  mockToast,
  mockNavigate,
  mockTenantPath,
  mockParams,
  mockAdminNewsletters,
} = vi.hoisted(() => ({
  mockToast: { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() },
  mockNavigate: vi.fn(),
  mockTenantPath: vi.fn((p: string) => `/test${p}`),
  mockParams: { id: undefined as string | undefined },
  mockAdminNewsletters: {
    getTemplate: vi.fn(),
    createTemplate: vi.fn(),
    updateTemplate: vi.fn(),
    duplicateTemplate: vi.fn(),
  },
}));

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: mockTenantPath,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  }),
);

// Mock react-router-dom so useParams returns no id (create mode) by default
vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom');
  return {
    ...actual,
    useParams: () => mockParams,
    useNavigate: () => mockNavigate,
  };
});

// Mock adminApi
vi.mock('@/admin/api/adminApi', () => ({
  adminNewsletters: mockAdminNewsletters,
}));

// Mock the multi-mode content editor (wraps the lazy RichTextEditor + others)
vi.mock('../../components/NewsletterContentEditor', () => ({
  NewsletterContentEditor: ({
    value,
    format,
    onChange,
  }: {
    value: string;
    format: string;
    onChange: (next: { content: string; content_format: string }) => void;
  }) => (
    <textarea
      data-testid="rich-text-editor"
      aria-label="content"
      data-format={format}
      value={value}
      onChange={(e) => onChange({ content: e.target.value, content_format: format })}
    />
  ),
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

import { TemplateForm } from './TemplateForm';

describe('TemplateForm — create mode (no id param)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockParams.id = undefined;
  });

  it('renders the create form without data-loading spinner', async () => {
    render(<TemplateForm />);
    // In create mode no API call is made, so the data-loading spinner disappears quickly.
    // (A Suspense fallback from the lazy RichTextEditor may briefly appear and then resolve.)
    // Wait for the form to be ready by checking a form input is present.
    await waitFor(() => {
      // The name Input from HeroUI renders an <input> element
      const inputs = screen.queryAllByRole('textbox');
      expect(inputs.length).toBeGreaterThan(0);
    });
  });

  it('shows a name input field', () => {
    render(<TemplateForm />);
    // HeroUI Input renders an <input> element
    const inputs = screen.getAllByRole('textbox');
    expect(inputs.length).toBeGreaterThan(0);
  });

  it('shows a save/submit button', () => {
    render(<TemplateForm />);
    const buttons = screen.getAllByRole('button');
    const saveBtn = buttons.find(
      (b) =>
        b.textContent?.toLowerCase().includes('save') ||
        b.textContent?.toLowerCase().includes('template') ||
        b.textContent?.toLowerCase().includes('newsletters.save'),
    );
    expect(saveBtn).toBeDefined();
  });

  it('shows validation error when form submitted without name', async () => {
    render(<TemplateForm />);
    const form = document.querySelector('form');
    expect(form).not.toBeNull();
    fireEvent.submit(form!);
    // The name field should show an error (isInvalid → errorMessage rendered)
    // The createTemplate API should NOT be called
    await waitFor(() => {
      expect(mockAdminNewsletters.createTemplate).not.toHaveBeenCalled();
    });
  });

  it('calls createTemplate and navigates on successful submit', async () => {
    mockAdminNewsletters.createTemplate.mockResolvedValueOnce({ success: true, data: { id: 99, name: 'My Template' } });

    render(<TemplateForm />);

    // Type a name into the first textbox (the name Input)
    const nameInput = screen.getAllByRole('textbox')[0];
    fireEvent.change(nameInput, { target: { value: 'My Newsletter Template' } });

    const form = document.querySelector('form')!;
    fireEvent.submit(form);

    await waitFor(() => {
      expect(mockAdminNewsletters.createTemplate).toHaveBeenCalledWith(
        expect.objectContaining({ name: 'My Newsletter Template' }),
      );
    });
    await waitFor(() => {
      expect(mockToast.success).toHaveBeenCalled();
      expect(mockNavigate).toHaveBeenCalledWith('/test/admin/newsletters/templates');
    });
  });

  it('shows error toast when API returns error', async () => {
    mockAdminNewsletters.createTemplate.mockResolvedValueOnce({
      success: false,
      error: 'Server error',
    });

    render(<TemplateForm />);

    const nameInput = screen.getAllByRole('textbox')[0];
    fireEvent.change(nameInput, { target: { value: 'My Template' } });

    const form = document.querySelector('form')!;
    fireEvent.submit(form);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('navigates back when cancel is pressed', async () => {
    render(<TemplateForm />);
    const buttons = screen.getAllByRole('button');
    const cancelBtn = buttons.find((b) =>
      b.textContent?.toLowerCase().includes('cancel') ||
      b.textContent?.toLowerCase().includes('newsletters.cancel'),
    );
    expect(cancelBtn).toBeDefined();
    fireEvent.click(cancelBtn!);
    expect(mockNavigate).toHaveBeenCalledWith('/test/admin/newsletters/templates');
  });
});

describe('TemplateForm — edit mode (id=5 param)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockParams.id = '5';
  });

  it('shows loading spinner while fetching template', () => {
    mockAdminNewsletters.getTemplate.mockReturnValue(new Promise(() => {}));
    render(<TemplateForm />);
    const spinners = screen.getAllByRole('status');
    const busy = spinners.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  it('populates form with template data after load', async () => {
    mockAdminNewsletters.getTemplate.mockResolvedValueOnce({
      success: true,
      data: {
        id: 5,
        name: 'Existing Template',
        description: 'A test template',
        category: 'custom',
        is_active: 1,
        subject: 'Hello World',
        preview_text: 'Preview',
        content: '<p>Content</p>',
        usage_count: 3,
      },
    });

    render(<TemplateForm />);

    await waitFor(() => {
      const inputs = screen.getAllByRole('textbox');
      const nameInput = inputs.find((el) => (el as HTMLInputElement).value === 'Existing Template');
      expect(nameInput).toBeDefined();
    });
  });

  it('shows error state when template fails to load', async () => {
    mockAdminNewsletters.getTemplate.mockResolvedValueOnce({
      success: false,
      error: 'Not found',
    });

    render(<TemplateForm />);

    await waitFor(() => {
      // Loading spinner should be gone and an error message shown
      const spinners = screen.queryAllByRole('status');
      const busy = spinners.find((el) => el.getAttribute('aria-busy') === 'true');
      expect(busy).toBeUndefined();
    });
    // Should not show a form — should show an error card
    expect(document.querySelector('form')).toBeNull();
  });

  it('calls updateTemplate on submit in edit mode', async () => {
    mockAdminNewsletters.getTemplate.mockResolvedValueOnce({
      success: true,
      data: {
        id: 5,
        name: 'Old Name',
        description: '',
        category: 'custom',
        is_active: 1,
        subject: '',
        preview_text: '',
        content: '',
        usage_count: 0,
      },
    });
    mockAdminNewsletters.updateTemplate.mockResolvedValueOnce({ success: true });

    render(<TemplateForm />);

    await waitFor(() => {
      const inputs = screen.getAllByRole('textbox');
      const nameInput = inputs.find((el) => (el as HTMLInputElement).value === 'Old Name');
      expect(nameInput).toBeDefined();
    });

    const form = document.querySelector('form')!;
    fireEvent.submit(form);

    await waitFor(() => {
      expect(mockAdminNewsletters.updateTemplate).toHaveBeenCalledWith(
        5,
        expect.objectContaining({ name: 'Old Name' }),
      );
    });
  });

  it('shows duplicate button in edit mode', async () => {
    mockAdminNewsletters.getTemplate.mockResolvedValueOnce({
      success: true,
      data: {
        id: 5,
        name: 'Template',
        description: '',
        category: 'custom',
        is_active: 1,
        subject: '',
        preview_text: '',
        content: '',
      },
    });

    render(<TemplateForm />);

    await waitFor(() => {
      const buttons = screen.getAllByRole('button');
      const dupBtn = buttons.find(
        (b) =>
          b.textContent?.toLowerCase().includes('duplic') ||
          b.textContent?.toLowerCase().includes('newsletters.duplicate'),
      );
      expect(dupBtn).toBeDefined();
    });
  });
});
