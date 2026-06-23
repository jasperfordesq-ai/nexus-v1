// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── Mock api ────────────────────────────────────────────────────────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
    download: vi.fn(),
    upload: vi.fn(),
  },
}));

vi.mock('@/lib/api', () => ({ api: mockApi, default: mockApi }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ─── Toast / Auth / Tenant ───────────────────────────────────────────────────
const mockToast = {
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
  showToast: vi.fn(),
};

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
    useAuth: () => ({
      user: { id: 1, name: 'Jasper' },
      isAuthenticated: true,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      updateUser: vi.fn(),
      refreshUser: vi.fn(),
      status: 'idle' as const,
      error: null,
    }),
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

// ─── Stub UI + motion ────────────────────────────────────────────────────────
vi.mock('@/components/ui', async () => (await import('@/test/uiMock')).uiMock);

vi.mock('@/lib/motion', async () => {
  const { default: React } = await import('react');
  return {
    motion: {
      div: React.forwardRef(
        (props: Record<string, unknown> & { children?: React.ReactNode }, ref: React.Ref<HTMLDivElement>) => {
          const { initial: _i, animate: _a, exit: _e, custom: _c, variants: _v, transition: _t, children, ...domProps } = props;
          return React.createElement('div', { ...domProps, ref }, children as React.ReactNode);
        }
      ),
    },
    AnimatePresence: ({ children }: { children: React.ReactNode }) => React.createElement(React.Fragment, null, children),
  };
});

// ─── Fixtures ────────────────────────────────────────────────────────────────
const makeTemplate = (overrides = {}) => ({
  id: 1,
  title: 'Run 5km',
  description: 'Complete a 5km run within 30 days.',
  target_value: 5,
  category: 'fitness',
  is_public: true,
  duration_days: 30,
  ...overrides,
});

const makeTemplatesResponse = (templates = [makeTemplate()]) => ({
  success: true,
  data: templates,
});

const makeCategoriesResponse = (categories = ['fitness', 'health', 'learning']) => ({
  success: true,
  data: categories,
});

// ─────────────────────────────────────────────────────────────────────────────
describe('GoalTemplatePickerModal', () => {
  const onClose = vi.fn();
  const onTemplateSelected = vi.fn();

  beforeEach(() => {
    vi.resetAllMocks();
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('categories')) return Promise.resolve(makeCategoriesResponse());
      return Promise.resolve(makeTemplatesResponse());
    });
  });

  it('renders nothing when isOpen is false', async () => {
    const { GoalTemplatePickerModal } = await import('./GoalTemplatePickerModal');
    render(
      <GoalTemplatePickerModal
        isOpen={false}
        onClose={onClose}
        onTemplateSelected={onTemplateSelected}
      />
    );

    // The modal should not be in the document
    expect(document.querySelector('[role="dialog"]')).toBeNull();
  });

  it('shows a loading spinner initially when open', async () => {
    mockApi.get.mockImplementation(() => new Promise(() => {}));
    const { GoalTemplatePickerModal } = await import('./GoalTemplatePickerModal');
    render(
      <GoalTemplatePickerModal
        isOpen={true}
        onClose={onClose}
        onTemplateSelected={onTemplateSelected}
      />
    );

    const statuses = screen.getAllByRole('status');
    const busy = statuses.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  it('renders template titles after loading', async () => {
    const { GoalTemplatePickerModal } = await import('./GoalTemplatePickerModal');
    render(
      <GoalTemplatePickerModal
        isOpen={true}
        onClose={onClose}
        onTemplateSelected={onTemplateSelected}
      />
    );

    await waitFor(() => {
      expect(screen.getByText('Run 5km')).toBeInTheDocument();
    });
  });

  it('renders template description', async () => {
    const { GoalTemplatePickerModal } = await import('./GoalTemplatePickerModal');
    render(
      <GoalTemplatePickerModal
        isOpen={true}
        onClose={onClose}
        onTemplateSelected={onTemplateSelected}
      />
    );

    await waitFor(() => {
      expect(screen.getByText('Complete a 5km run within 30 days.')).toBeInTheDocument();
    });
  });

  it('renders category filter buttons', async () => {
    const { GoalTemplatePickerModal } = await import('./GoalTemplatePickerModal');
    render(
      <GoalTemplatePickerModal
        isOpen={true}
        onClose={onClose}
        onTemplateSelected={onTemplateSelected}
      />
    );

    await waitFor(() => {
      const btns = screen.getAllByRole('button');
      const fitnessBtn = btns.find((b) => b.textContent?.toLowerCase().includes('fitness'));
      expect(fitnessBtn).toBeDefined();
    });
  });

  it('filters templates when a category is selected', async () => {
    const templates = [
      makeTemplate({ id: 1, title: 'Run 5km', category: 'fitness' }),
      makeTemplate({ id: 2, title: 'Read 10 books', category: 'learning' }),
    ];
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('categories')) return Promise.resolve(makeCategoriesResponse(['fitness', 'learning']));
      return Promise.resolve(makeTemplatesResponse(templates));
    });

    const { GoalTemplatePickerModal } = await import('./GoalTemplatePickerModal');
    render(
      <GoalTemplatePickerModal
        isOpen={true}
        onClose={onClose}
        onTemplateSelected={onTemplateSelected}
      />
    );

    await waitFor(() => screen.getByText('Run 5km'));

    // Click the 'fitness' category filter
    const btns = screen.getAllByRole('button');
    const fitnessBtn = btns.find((b) => b.textContent === 'fitness');
    expect(fitnessBtn).toBeDefined();
    if (fitnessBtn) fireEvent.click(fitnessBtn);

    await waitFor(() => {
      expect(screen.getByText('Run 5km')).toBeInTheDocument();
      expect(screen.queryByText('Read 10 books')).not.toBeInTheDocument();
    });
  });

  it('shows empty state when no templates exist', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('categories')) return Promise.resolve(makeCategoriesResponse([]));
      return Promise.resolve(makeTemplatesResponse([]));
    });

    const { GoalTemplatePickerModal } = await import('./GoalTemplatePickerModal');
    render(
      <GoalTemplatePickerModal
        isOpen={true}
        onClose={onClose}
        onTemplateSelected={onTemplateSelected}
      />
    );

    await waitFor(() => {
      // The "none_available" i18n key renders as "No templates available yet."
      expect(screen.getByText(/no templates available/i)).toBeInTheDocument();
    });
  });

  it('shows error state and retry button when API fails', async () => {
    mockApi.get.mockRejectedValue(new Error('network'));

    const { GoalTemplatePickerModal } = await import('./GoalTemplatePickerModal');
    render(
      <GoalTemplatePickerModal
        isOpen={true}
        onClose={onClose}
        onTemplateSelected={onTemplateSelected}
      />
    );

    await waitFor(() => {
      // role="alert" is on the error container
      expect(screen.getByRole('alert')).toBeInTheDocument();
    });

    // Retry button should exist
    const btns = screen.getAllByRole('button');
    const retryBtn = btns.find((b) =>
      b.textContent?.toLowerCase().includes('try') ||
      b.textContent?.toLowerCase().includes('again') ||
      b.textContent?.toLowerCase().includes('retry')
    );
    expect(retryBtn).toBeDefined();
  });

  it('calls POST endpoint when template use button is clicked', async () => {
    mockApi.post.mockResolvedValue({ success: true, data: { id: 99 } });

    const { GoalTemplatePickerModal } = await import('./GoalTemplatePickerModal');
    render(
      <GoalTemplatePickerModal
        isOpen={true}
        onClose={onClose}
        onTemplateSelected={onTemplateSelected}
      />
    );

    await waitFor(() => screen.getByText('Run 5km'));

    // The "use template" button has an aria-label
    const btns = screen.getAllByRole('button');
    const useBtn = btns.find((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('run 5km') ||
      b.getAttribute('aria-label')?.toLowerCase().includes('use')
    );
    expect(useBtn).toBeDefined();
    if (useBtn) fireEvent.click(useBtn);

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith('/v2/goals/from-template/1', {});
    });
  });

  it('fires onTemplateSelected and onClose after successful template creation', async () => {
    mockApi.post.mockResolvedValue({ success: true, data: { id: 99 } });

    const { GoalTemplatePickerModal } = await import('./GoalTemplatePickerModal');
    render(
      <GoalTemplatePickerModal
        isOpen={true}
        onClose={onClose}
        onTemplateSelected={onTemplateSelected}
      />
    );

    await waitFor(() => screen.getByText('Run 5km'));

    const btns = screen.getAllByRole('button');
    const useBtn = btns.find((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('run 5km') ||
      b.getAttribute('aria-label')?.toLowerCase().includes('use')
    );
    if (useBtn) fireEvent.click(useBtn);

    await waitFor(() => {
      expect(onTemplateSelected).toHaveBeenCalledTimes(1);
      expect(onClose).toHaveBeenCalledTimes(1);
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('shows error toast when template creation fails', async () => {
    mockApi.post.mockRejectedValue(new Error('creation failed'));

    const { GoalTemplatePickerModal } = await import('./GoalTemplatePickerModal');
    render(
      <GoalTemplatePickerModal
        isOpen={true}
        onClose={onClose}
        onTemplateSelected={onTemplateSelected}
      />
    );

    await waitFor(() => screen.getByText('Run 5km'));

    const btns = screen.getAllByRole('button');
    const useBtn = btns.find((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('run 5km') ||
      b.getAttribute('aria-label')?.toLowerCase().includes('use')
    );
    if (useBtn) fireEvent.click(useBtn);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('renders cancel button in footer', async () => {
    const { GoalTemplatePickerModal } = await import('./GoalTemplatePickerModal');
    render(
      <GoalTemplatePickerModal
        isOpen={true}
        onClose={onClose}
        onTemplateSelected={onTemplateSelected}
      />
    );

    await waitFor(() => {
      const btns = screen.getAllByRole('button');
      const cancelBtn = btns.find((b) =>
        b.textContent?.toLowerCase().includes('cancel')
      );
      expect(cancelBtn).toBeDefined();
    });
  });
});
