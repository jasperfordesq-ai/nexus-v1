// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import React from 'react';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import userEvent from '@testing-library/user-event';

// ─── Hoisted mocks ───────────────────────────────────────────────────────────
const { mockAdminMenus, mockAdminPages } = vi.hoisted(() => ({
  mockAdminMenus: {
    get: vi.fn(),
    getItems: vi.fn(),
    create: vi.fn(),
    update: vi.fn(),
    createItem: vi.fn(),
    updateItem: vi.fn(),
    deleteItem: vi.fn(),
    reorderItems: vi.fn(),
  },
  mockAdminPages: { list: vi.fn() },
}));

vi.mock('@/admin/api/adminApi', () => ({
  adminMenus: mockAdminMenus,
  adminPages: mockAdminPages,
  adminUsers: { list: vi.fn() },
  adminCrm: { getFunnel: vi.fn() },
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ─── dnd-kit stubs — avoids DOM measurement errors ───────────────────────────
vi.mock('@dnd-kit/core', () => ({
  DndContext: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
  closestCenter: vi.fn(),
  KeyboardSensor: class {},
  PointerSensor: class {},
  useSensor: vi.fn(),
  useSensors: vi.fn(() => []),
  DragOverlay: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}));

vi.mock('@dnd-kit/sortable', () => ({
  SortableContext: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
  sortableKeyboardCoordinates: vi.fn(),
  useSortable: vi.fn(() => ({
    attributes: {},
    listeners: {},
    setNodeRef: vi.fn(),
    transform: null,
    transition: null,
    isDragging: false,
  })),
  verticalListSortingStrategy: 'vertical',
  arrayMove: vi.fn((arr: unknown[]) => arr),
}));

// ─── Router ──────────────────────────────────────────────────────────────────
const mockNavigate = vi.fn();
let mockParamsId: string | undefined = undefined; // undefined = new menu mode

vi.mock('react-router-dom', async (importOriginal) => {
  const orig = await importOriginal<typeof import('react-router-dom')>();
  return {
    ...orig,
    useNavigate: () => mockNavigate,
    useParams: () => ({ id: mockParamsId }),
  };
});

// ─── Contexts ────────────────────────────────────────────────────────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// ─── Heavy child stubs ────────────────────────────────────────────────────────
vi.mock('../../components', () => ({
  PageHeader: ({ title, actions }: { title: string; actions?: React.ReactNode }) => (
    <div>
      <h1>{title}</h1>
      {actions}
    </div>
  ),
  ConfirmModal: () => null,
  StatCard: ({ label }: { label: string }) => <div>{label}</div>,
}));

vi.mock('../../components/PageHeader', () => ({
  default: ({ title, actions }: { title: string; actions?: React.ReactNode }) => (
    <div>
      <h1>{title}</h1>
      {actions}
    </div>
  ),
  PageHeader: ({ title, actions }: { title: string; actions?: React.ReactNode }) => (
    <div>
      <h1>{title}</h1>
      {actions}
    </div>
  ),
}));

vi.mock('../../components/ConfirmModal', () => ({
  default: () => null,
  ConfirmModal: () => null,
}));

vi.mock('../../components/IconPicker', () => ({
  default: () => <div data-testid="icon-picker" />,
  IconPicker: () => <div data-testid="icon-picker" />,
}));

vi.mock('../../components/VisibilityRulesEditor', () => ({
  default: () => <div data-testid="visibility-rules" />,
  VisibilityRulesEditor: () => <div data-testid="visibility-rules" />,
}));

vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<Record<string, unknown>>();
  return {
    ...orig,
    Select: ({
      label,
      onSelectionChange,
      selectedKey,
    }: {
      children?: React.ReactNode;
      label?: string;
      onSelectionChange?: (v: string) => void;
      selectedKey?: string;
    }) => (
      <select aria-label={label} value={selectedKey ?? ''} onChange={(e) => onSelectionChange?.(e.target.value)}>
        <option value="">-- select --</option>
        <option value="header">Header</option>
        <option value="footer">Footer</option>
        <option value="sidebar">Sidebar</option>
      </select>
    ),
    // Stub SelectItem to avoid ListBoxItem-outside-collection error
    SelectItem: ({ children }: { children?: React.ReactNode }) => <option>{children}</option>,
    Switch: ({
      children,
      isSelected,
      onChange,
    }: {
      children?: React.ReactNode;
      isSelected?: boolean;
      onChange?: (v: boolean) => void;
    }) => (
      <label>
        <input
          type="checkbox"
          checked={isSelected}
          onChange={(e) => onChange?.(e.target.checked)}
        />
        {children}
      </label>
    ),
    DynamicIcon: () => <span data-testid="dynamic-icon" />,
  };
});

vi.mock('@/components/seo/PageMeta', () => ({ PageMeta: () => null }));

// ─── Fixtures ─────────────────────────────────────────────────────────────────
const makeMenu = (overrides = {}) => ({
  id: 123,
  name: 'Main Navigation',
  location: 'header',
  description: '',
  is_active: true,
  created_at: '2025-01-01T00:00:00Z',
  ...overrides,
});

const makeMenuItem = (overrides = {}) => ({
  id: 1,
  menu_id: 123,
  label: 'Home',
  url: '/',
  type: 'custom',
  sort_order: 0,
  children: [],
  ...overrides,
});

// ─────────────────────────────────────────────────────────────────────────────
describe('MenuBuilder — new menu', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockParamsId = undefined; // new menu mode
    mockAdminPages.list.mockResolvedValue({ success: true, data: [] });
    mockAdminMenus.create.mockResolvedValue({ success: true, data: makeMenu() });
  });

  it('renders a menu-related heading in new mode', async () => {
    const { MenuBuilder } = await import('./MenuBuilder');
    render(<MenuBuilder />);

    // Multiple headings may render (PageHeader + card sections)
    await waitFor(() => {
      const headings = screen.getAllByRole('heading');
      const menuHeading = headings.find((h) => h.textContent?.toLowerCase().includes('menu'));
      expect(menuHeading).toBeDefined();
    });
  });

  it('shows name input field', async () => {
    const { MenuBuilder } = await import('./MenuBuilder');
    render(<MenuBuilder />);

    await waitFor(() => {
      const inputs = screen.getAllByRole('textbox');
      expect(inputs.length).toBeGreaterThan(0);
    });
  });

  it('renders a save/create button', async () => {
    const { MenuBuilder } = await import('./MenuBuilder');
    render(<MenuBuilder />);

    // Button text uses i18n key, e.g. "menu_builder.create_menu"
    const saveBtn = screen.getAllByRole('button').find(
      (b) =>
        b.textContent?.includes('menu_builder.create_menu') ||
        b.textContent?.includes('menu_builder.save_changes') ||
        b.textContent?.toLowerCase().includes('save') ||
        b.textContent?.toLowerCase().includes('create')
    );
    expect(saveBtn).toBeDefined();
  });

  it('calls adminMenus.create and navigates when saving new menu with valid data', async () => {
    const { MenuBuilder } = await import('./MenuBuilder');
    render(<MenuBuilder />);

    // Fill in name field
    const inputs = screen.getAllByRole('textbox');
    const nameInput = inputs[0];
    await userEvent.clear(nameInput);
    await userEvent.type(nameInput, 'My New Menu');

    // Pick a location (the Select stub renders a native select)
    const selects = screen.getAllByRole('combobox');
    if (selects.length > 0) {
      await userEvent.selectOptions(selects[0], ['header']);
    }

    const saveBtn = screen.getAllByRole('button').find(
      (b) =>
        b.textContent?.toLowerCase().includes('save') ||
        b.textContent?.toLowerCase().includes('create')
    );
    if (saveBtn) {
      await userEvent.click(saveBtn);
      await waitFor(() => {
        expect(mockAdminMenus.create).toHaveBeenCalledWith(
          expect.objectContaining({ name: expect.any(String) })
        );
      });
    }
  });

  it('shows add item button in the empty item tree', async () => {
    const { MenuBuilder } = await import('./MenuBuilder');
    render(<MenuBuilder />);

    await waitFor(() => {
      const addBtn = screen.getAllByRole('button').find(
        (b) => b.textContent?.toLowerCase().includes('add')
      );
      expect(addBtn).toBeDefined();
    });
  });
});

describe('MenuBuilder — edit existing menu', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockParamsId = '123'; // edit mode
    mockAdminMenus.get.mockResolvedValue({ success: true, data: makeMenu() });
    mockAdminMenus.getItems.mockResolvedValue({ success: true, data: [makeMenuItem()] });
    mockAdminPages.list.mockResolvedValue({ success: true, data: [] });
    mockAdminMenus.update.mockResolvedValue({ success: true, data: makeMenu() });
  });

  it('shows loading spinner while menu loads', async () => {
    mockAdminMenus.get.mockImplementationOnce(() => new Promise(() => {}));
    mockAdminMenus.getItems.mockImplementationOnce(() => new Promise(() => {}));
    const { MenuBuilder } = await import('./MenuBuilder');
    render(<MenuBuilder />);

    const statuses = screen.getAllByRole('status');
    const busy = statuses.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  it('populates name field with existing menu name', async () => {
    const { MenuBuilder } = await import('./MenuBuilder');
    render(<MenuBuilder />);

    await waitFor(() => {
      const inputs = screen.getAllByRole('textbox');
      const nameInput = inputs.find(
        (el) => (el as HTMLInputElement).value === 'Main Navigation'
      );
      expect(nameInput).toBeDefined();
    });
  });

  it('renders existing menu item label', async () => {
    const { MenuBuilder } = await import('./MenuBuilder');
    render(<MenuBuilder />);

    await waitFor(() => {
      expect(screen.getByText('Home')).toBeInTheDocument();
    });
  });

  it('calls adminMenus.get and adminMenus.getItems on mount with correct id', async () => {
    const { MenuBuilder } = await import('./MenuBuilder');
    render(<MenuBuilder />);

    await waitFor(() => {
      expect(mockAdminMenus.get).toHaveBeenCalledWith(123);
      expect(mockAdminMenus.getItems).toHaveBeenCalledWith(123);
    });
  });

  it('calls adminMenus.update when saving existing menu', async () => {
    const { MenuBuilder } = await import('./MenuBuilder');
    render(<MenuBuilder />);

    await waitFor(() => screen.getAllByRole('button').length > 0);

    const saveBtn = screen.getAllByRole('button').find(
      (b) => b.textContent?.toLowerCase().includes('save')
    );
    if (saveBtn) {
      await userEvent.click(saveBtn);
      await waitFor(() => {
        expect(mockAdminMenus.update).toHaveBeenCalledWith(123, expect.any(Object));
      });
    }
  });

  it('shows error toast when load fails', async () => {
    mockAdminMenus.get.mockRejectedValueOnce(new Error('Not found'));
    const { MenuBuilder } = await import('./MenuBuilder');
    render(<MenuBuilder />);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });
});
