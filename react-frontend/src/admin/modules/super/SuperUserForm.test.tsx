// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

// ── Hoist all mocks so vi.mock factories can reference them ──────────────────
const { mockAdminSuper, mockToast, mockNavigate, mockParams } = vi.hoisted(() => ({
  mockAdminSuper: {
    listTenants: vi.fn(),
    getUser: vi.fn(),
    createUser: vi.fn(),
    updateUser: vi.fn(),
    moveUserTenant: vi.fn(),
    moveAndPromote: vi.fn(),
  },
  mockToast: { success: vi.fn(), error: vi.fn(), warning: vi.fn(), info: vi.fn() },
  mockNavigate: vi.fn(),
  // Mutable object so tests can switch between create/edit mode by mutating .value
  mockParams: { value: {} as Record<string, string> },
}));

vi.mock('@/admin/api/adminApi', () => ({
  adminSuper: mockAdminSuper,
}));

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

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom');
  return {
    ...actual,
    useNavigate: () => mockNavigate,
    // Reads from mutable mockParams.value so tests can control id without re-mocking
    useParams: () => mockParams.value,
  };
});

// ── fixtures ─────────────────────────────────────────────────────────────────
const TENANTS = [
  { id: 1, name: 'Alpha Tenant', slug: 'alpha', allows_subtenants: false },
  { id: 2, name: 'Beta Tenant', slug: 'beta', allows_subtenants: false },
  { id: 3, name: 'Hub Tenant', slug: 'hub', allows_subtenants: true },
];

const USER_DETAIL = {
  id: 7,
  tenant_id: 1,
  tenant_name: 'Alpha Tenant',
  first_name: 'Carol',
  last_name: 'Smith',
  email: 'carol@example.com',
  role: 'member',
  location: 'Dublin',
  phone: '+35312345678',
  status: 'active',
  is_tenant_super_admin: false,
  is_super_admin: false,
  created_at: '2024-01-15T10:00:00Z',
};

import { SuperUserForm } from './SuperUserForm';

// ── CREATE MODE ───────────────────────────────────────────────────────────────
describe('SuperUserForm — create mode (no id)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    // Create mode: no id in params
    mockParams.value = {};
    mockAdminSuper.listTenants.mockResolvedValue({ success: true, data: TENANTS });
    // Safety: getUser should never be called in create mode, but avoid TypeError if it is
    mockAdminSuper.getUser.mockResolvedValue({ success: true, data: null });
  });

  it('renders the create user form', async () => {
    render(<SuperUserForm />);
    await waitFor(() => {
      // Button type=submit with text "Create User"
      expect(screen.getByRole('button', { name: /create user/i })).toBeInTheDocument();
    });
  });

  it('renders first name and email inputs', async () => {
    render(<SuperUserForm />);
    await waitFor(() => {
      expect(screen.getByRole('textbox', { name: /first name/i })).toBeInTheDocument();
    });
    expect(screen.getByRole('textbox', { name: /email/i })).toBeInTheDocument();
  });

  it('calls adminSuper.listTenants on mount', async () => {
    render(<SuperUserForm />);
    await waitFor(() => {
      expect(mockAdminSuper.listTenants).toHaveBeenCalled();
    });
  });

  it('submits create payload on form submit', async () => {
    mockAdminSuper.createUser.mockResolvedValue({ success: true, data: { user_id: 42 } });
    render(<SuperUserForm />);

    await waitFor(() => {
      expect(screen.getByRole('textbox', { name: /first name/i })).toBeInTheDocument();
    });

    // Fill in first name and email
    fireEvent.change(screen.getByRole('textbox', { name: /first name/i }), {
      target: { value: 'Alice' },
    });
    fireEvent.change(screen.getByRole('textbox', { name: /email/i }), {
      target: { value: 'alice@example.com' },
    });

    // Submit via form element
    const form = document.querySelector('form');
    expect(form).toBeInTheDocument();
    fireEvent.submit(form!);

    await waitFor(() => {
      expect(mockAdminSuper.createUser).toHaveBeenCalledWith(
        expect.objectContaining({
          first_name: 'Alice',
          email: 'alice@example.com',
        })
      );
    });
  });

  it('shows success toast and navigates after successful create', async () => {
    mockAdminSuper.createUser.mockResolvedValue({ success: true, data: { user_id: 42 } });
    render(<SuperUserForm />);

    await waitFor(() => {
      expect(screen.getByRole('textbox', { name: /first name/i })).toBeInTheDocument();
    });

    fireEvent.change(screen.getByRole('textbox', { name: /first name/i }), {
      target: { value: 'Bob' },
    });
    fireEvent.change(screen.getByRole('textbox', { name: /email/i }), {
      target: { value: 'bob@example.com' },
    });

    const form = document.querySelector('form');
    fireEvent.submit(form!);

    await waitFor(() => {
      expect(mockToast.success).toHaveBeenCalled();
      expect(mockNavigate).toHaveBeenCalled();
    });
  });

  it('shows error toast when create fails', async () => {
    mockAdminSuper.createUser.mockResolvedValue({ success: false, error: 'Email taken' });
    render(<SuperUserForm />);

    await waitFor(() => {
      expect(screen.getByRole('textbox', { name: /first name/i })).toBeInTheDocument();
    });

    // Submit without filling in required fields
    const form = document.querySelector('form');
    fireEvent.submit(form!);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('renders breadcrumb nav with aria-label', async () => {
    render(<SuperUserForm />);
    await waitFor(() => {
      // The nav element has aria-label="Breadcrumb"
      const nav = screen.getByRole('navigation', { name: /breadcrumb/i });
      expect(nav).toBeInTheDocument();
    });
  });
});

// ── EDIT MODE ─────────────────────────────────────────────────────────────────
describe('SuperUserForm — edit mode (with id)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    // Edit mode: id = '7'
    mockParams.value = { id: '7' };
    mockAdminSuper.listTenants.mockResolvedValue({ success: true, data: TENANTS });
    mockAdminSuper.getUser.mockResolvedValue({ success: true, data: USER_DETAIL });
  });

  it('shows loading text initially (before getUser resolves)', () => {
    // Keep getUser pending forever to observe loading state
    mockAdminSuper.getUser.mockReturnValue(new Promise(() => {}));

    render(<SuperUserForm />);
    // Loading div is shown while loading=true
    expect(screen.getByText(/loading/i)).toBeInTheDocument();
  });

  it('calls listTenants on mount', async () => {
    render(<SuperUserForm />);
    await waitFor(() => {
      expect(mockAdminSuper.listTenants).toHaveBeenCalled();
    });
  });

  it('calls getUser with the id from params', async () => {
    render(<SuperUserForm />);
    await waitFor(() => {
      expect(mockAdminSuper.getUser).toHaveBeenCalledWith(7);
    });
  });

  it('shows Update User button after loading', async () => {
    render(<SuperUserForm />);
    await waitFor(() => {
      expect(screen.getByRole('button', { name: /update user/i })).toBeInTheDocument();
    });
  });
});
