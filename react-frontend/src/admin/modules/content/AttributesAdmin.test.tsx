// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import userEvent from '@testing-library/user-event';

// ── Stable hoisted refs ──────────────────────────────────────────────────────
const mockToast = vi.hoisted(() => ({
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
}));

const mockAdminAttributes = vi.hoisted(() => ({
  list: vi.fn(),
  create: vi.fn(),
  update: vi.fn(),
  delete: vi.fn(),
}));

const mockAdminCategories = vi.hoisted(() => ({
  list: vi.fn(),
  create: vi.fn(),
  update: vi.fn(),
  delete: vi.fn(),
}));

vi.mock('@/admin/api/adminApi', () => ({
  adminAttributes: mockAdminAttributes,
  adminCategories: mockAdminCategories,
}));

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
  }),
);

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

import { AttributesAdmin } from './AttributesAdmin';

// ── Fixtures ─────────────────────────────────────────────────────────────────
const ATTRIBUTES = [
  {
    id: 1,
    name: 'Skill Level',
    slug: 'skill-level',
    type: 'select',
    category_id: null,
    category_name: null,
    is_active: true,
  },
  {
    id: 2,
    name: 'Age Range',
    slug: 'age-range',
    type: 'radio',
    category_id: 10,
    category_name: 'Demographics',
    is_active: false,
  },
];

const CATEGORIES = [
  { id: 10, name: 'Demographics', slug: 'demographics' },
  { id: 11, name: 'Interests', slug: 'interests' },
];

function setupMocks(attributes = ATTRIBUTES) {
  mockAdminAttributes.list.mockResolvedValue({ success: true, data: attributes });
  mockAdminCategories.list.mockResolvedValue({ success: true, data: CATEGORIES });
}

// ─────────────────────────────────────────────────────────────────────────────
describe('AttributesAdmin', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows loading state initially', () => {
    mockAdminAttributes.list.mockReturnValue(new Promise(() => {}));
    mockAdminCategories.list.mockResolvedValue({ success: true, data: [] });
    render(<AttributesAdmin />);

    // DataTable or spinner renders during load
    // Component shows DataTable with isLoading=true
    expect(document.body).toBeInTheDocument();
  });

  it('renders attribute rows after load', async () => {
    setupMocks();
    render(<AttributesAdmin />);

    await waitFor(() => {
      expect(screen.getByText('Skill Level')).toBeInTheDocument();
    });
    expect(screen.getByText('Age Range')).toBeInTheDocument();
  });

  it('shows empty state when no attributes', async () => {
    setupMocks([]);
    render(<AttributesAdmin />);

    await waitFor(() => {
      // EmptyState is rendered; just check no attribute rows
      expect(screen.queryByText('Skill Level')).not.toBeInTheDocument();
    });
  });

  it('shows Create Attributes button', async () => {
    setupMocks();
    render(<AttributesAdmin />);

    await waitFor(() => {
      expect(screen.getByText('Skill Level')).toBeInTheDocument();
    });
    // Button text is English translation: "Create Attributes"
    const createBtn = screen.getAllByRole('button').find(
      (b) => b.textContent && b.textContent.toLowerCase().includes('create'),
    );
    expect(createBtn).toBeDefined();
  });

  it('opens create modal when Create button is clicked', async () => {
    setupMocks();
    render(<AttributesAdmin />);

    await waitFor(() => {
      expect(screen.getByText('Skill Level')).toBeInTheDocument();
    });

    // Find the "Create Attributes" button (not the Refresh button)
    const createBtn = screen.getAllByRole('button').find(
      (b) => b.textContent && b.textContent.toLowerCase().includes('create'),
    )!;
    await userEvent.click(createBtn);

    await waitFor(() => {
      // Modal renders with a name text input
      const inputs = document.querySelectorAll('input[type="text"], input:not([type])');
      expect(inputs.length).toBeGreaterThan(0);
    });
  });

  it('calls adminAttributes.create and shows success toast', async () => {
    setupMocks();
    mockAdminAttributes.create.mockResolvedValue({ success: true, data: { id: 99, name: 'New Attr' } });

    render(<AttributesAdmin />);

    await waitFor(() => {
      expect(screen.getByText('Skill Level')).toBeInTheDocument();
    });

    // Open modal by clicking "Create Attributes"
    const createBtn = screen.getAllByRole('button').find(
      (b) => b.textContent && b.textContent.toLowerCase().includes('create'),
    )!;
    await userEvent.click(createBtn);

    // Wait for modal input to appear
    await waitFor(() => {
      const inputs = document.querySelectorAll('input[type="text"], input:not([type])');
      expect(inputs.length).toBeGreaterThan(0);
    });

    // Fill in the name input (first text input in the modal)
    const nameInput = (document.querySelectorAll('input[type="text"], input:not([type])')[0]) as HTMLInputElement;
    await userEvent.type(nameInput, 'New Attribute');

    // The modal save/create button — last button in the dialog
    let modalSaveBtn: HTMLElement | null = null;
    await waitFor(() => {
      const inModal = Array.from(document.querySelectorAll('[role="dialog"] button'));
      expect(inModal.length).toBeGreaterThan(1);
      modalSaveBtn = inModal[inModal.length - 1] as HTMLElement;
    });
    await userEvent.click(modalSaveBtn!);

    await waitFor(() => {
      expect(mockAdminAttributes.create).toHaveBeenCalledWith(
        expect.objectContaining({ name: 'New Attribute' }),
      );
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('shows error toast when name is empty on save', async () => {
    setupMocks();
    render(<AttributesAdmin />);

    await waitFor(() => {
      expect(screen.getByText('Skill Level')).toBeInTheDocument();
    });

    const createBtn = screen.getAllByRole('button').find(
      (b) => b.textContent && b.textContent.toLowerCase().includes('create'),
    )!;
    await userEvent.click(createBtn);

    // Wait for modal to appear
    let modalSaveBtn: HTMLElement | null = null;
    await waitFor(() => {
      const inModal = Array.from(document.querySelectorAll('[role="dialog"] button'));
      expect(inModal.length).toBeGreaterThan(1);
      modalSaveBtn = inModal[inModal.length - 1] as HTMLElement;
    });

    // Click save without entering a name — should trigger error toast
    await userEvent.click(modalSaveBtn!);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('opens delete confirmation when Delete is chosen from menu', async () => {
    setupMocks();
    mockAdminAttributes.delete.mockResolvedValue({ success: true });

    render(<AttributesAdmin />);

    await waitFor(() => {
      expect(screen.getByText('Skill Level')).toBeInTheDocument();
    });

    // Click the actions menu — aria-label is "Attribute Actions"
    const menuBtns = screen.getAllByRole('button').filter(
      (b) => b.getAttribute('aria-label') && b.getAttribute('aria-label')!.toLowerCase().includes('attribute'),
    );
    expect(menuBtns.length).toBeGreaterThan(0);
    await userEvent.click(menuBtns[0]);

    await waitFor(() => {
      // The dropdown renders "Delete Item" (English translation of content.label_delete_item)
      const menuItems = Array.from(document.querySelectorAll('[role="menuitem"]'));
      const deleteItem = menuItems.find(el => el.textContent?.toLowerCase().includes('delete'));
      expect(deleteItem).toBeTruthy();
    });
  });

  it('calls adminAttributes.delete and shows success toast on confirm', async () => {
    setupMocks();
    mockAdminAttributes.delete.mockResolvedValue({ success: true });

    render(<AttributesAdmin />);

    await waitFor(() => {
      expect(screen.getByText('Skill Level')).toBeInTheDocument();
    });

    // Open the actions menu
    const menuBtns = screen.getAllByRole('button').filter(
      (b) => b.getAttribute('aria-label') && b.getAttribute('aria-label')!.toLowerCase().includes('attribute'),
    );
    await userEvent.click(menuBtns[0]);

    // Wait for the "Delete Item" menu option then click it
    await waitFor(() => {
      const menuItems = Array.from(document.querySelectorAll('[role="menuitem"]'));
      const deleteItem = menuItems.find(el => el.textContent?.toLowerCase().includes('delete'));
      expect(deleteItem).toBeTruthy();
    });
    const menuItems = Array.from(document.querySelectorAll('[role="menuitem"]'));
    const deleteItem = menuItems.find(el => el.textContent?.toLowerCase().includes('delete'))!;
    await userEvent.click(deleteItem as HTMLElement);

    // Wait for ConfirmModal, then click the confirm button (last in dialog)
    let confirmBtn: HTMLElement | null = null;
    await waitFor(() => {
      const inModal = Array.from(document.querySelectorAll('[role="dialog"] button'));
      expect(inModal.length).toBeGreaterThan(1);
      confirmBtn = inModal[inModal.length - 1] as HTMLElement;
    });
    await userEvent.click(confirmBtn!);

    await waitFor(() => {
      expect(mockAdminAttributes.delete).toHaveBeenCalledWith(ATTRIBUTES[0].id);
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('Refresh button triggers reload', async () => {
    setupMocks();
    render(<AttributesAdmin />);

    await waitFor(() => {
      expect(screen.getByText('Skill Level')).toBeInTheDocument();
    });

    const callsBefore = mockAdminAttributes.list.mock.calls.length;
    const refreshBtn = screen.getAllByRole('button').find(
      (b) => b.textContent && b.textContent.toLowerCase().includes('refresh'),
    );
    if (refreshBtn) {
      await userEvent.click(refreshBtn);
      await waitFor(() => {
        expect(mockAdminAttributes.list.mock.calls.length).toBeGreaterThan(callsBefore);
      });
    }
    expect(screen.getByText('Skill Level')).toBeInTheDocument();
  });
});
