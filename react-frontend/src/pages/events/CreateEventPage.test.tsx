// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { fireEvent, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { renderEventRoute } from '@/test/events-test-harness';
import { createMockContexts } from '@/test/mock-contexts';

const { mockApi, mockToast } = vi.hoisted(() => ({
  mockApi: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
    upload: vi.fn(),
  },
  mockToast: {
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
    warning: vi.fn(),
  },
}));

vi.mock('@/lib/api', () => ({ api: mockApi }));

vi.mock('@/contexts', () =>
  createMockContexts({
    useTenant: () => ({
      tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
      tenantPath: (path: string) => `/test${path}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
    useToast: () => mockToast,
  }),
);

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

vi.mock('@/lib/motion', async () => {
  const { framerMotionMock } = await import('@/test/mocks');
  return framerMotionMock;
});

vi.mock('@/components/navigation', () => ({
  Breadcrumbs: ({ items }: { items: Array<{ label: string }> }) => (
    <nav aria-label="Breadcrumb">
      {items.map((item) => <span key={item.label}>{item.label}</span>)}
    </nav>
  ),
}));

vi.mock('@/components/feedback', () => ({
  LoadingScreen: ({ message }: { message: string }) => (
    <div role="status" aria-label={message} aria-busy="true" />
  ),
}));

vi.mock('@/components/ui/DatePicker', async () => ({
  DatePicker: (await import('@/test/events-test-harness')).EventDateOrTimeInputStub,
}));

vi.mock('@/components/ui/TimeInput', async () => ({
  TimeInput: (await import('@/test/events-test-harness')).EventDateOrTimeInputStub,
}));

vi.mock('@/components/location/PlaceAutocompleteInput', () => ({
  PlaceAutocompleteInput: ({
    label,
    value,
    onChange,
  }: {
    label: string;
    value: string;
    onChange: (value: string) => void;
  }) => (
    <label>
      {label}
      <input
        aria-label={label}
        value={value}
        onChange={(event) => onChange(event.target.value)}
      />
    </label>
  ),
}));

import { CreateEventPage } from './CreateEventPage';

async function renderCreateEventPage() {
  renderEventRoute(<CreateEventPage />, {
    route: '/test/events/create',
    path: '/:tenantSlug/events/create',
  });

  await screen.findByRole('heading', { level: 1, name: 'Create New Event' });
  await waitFor(() => {
    expect(mockApi.get).toHaveBeenCalledWith('/v2/polls?status=all&limit=100');
  });
}

describe('CreateEventPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockApi.get.mockResolvedValue({ success: true, data: [] });
    Object.defineProperty(HTMLElement.prototype, 'scrollIntoView', {
      configurable: true,
      value: vi.fn(),
    });
  });

  it('renders the create-event heading', async () => {
    await renderCreateEventPage();

    expect(screen.getByRole('heading', { level: 1, name: 'Create New Event' })).toBeInTheDocument();
  });

  it('renders an accessible title input', async () => {
    await renderCreateEventPage();

    expect(screen.getByRole('textbox', { name: 'Event Title' })).toBeInTheDocument();
  });

  it('renders a tenant-aware Cancel link', async () => {
    await renderCreateEventPage();

    expect(screen.getByRole('link', { name: 'Cancel' })).toHaveAttribute('href', '/test/events');
  });

  it('renders an accessible description textarea', async () => {
    await renderCreateEventPage();

    expect(screen.getByRole('textbox', { name: 'Description' })).toBeInTheDocument();
  });

  it('renders an accessible start-date control', async () => {
    await renderCreateEventPage();

    expect(screen.getByRole('textbox', { name: 'Start Date' })).toBeInTheDocument();
  });

  it('renders the max-attendees number input', async () => {
    await renderCreateEventPage();

    expect(screen.getByRole('spinbutton', { name: 'Max Attendees (optional)' })).toBeInTheDocument();
  });

  it('shows concrete validation errors and blocks the mutation for an empty form', async () => {
    await renderCreateEventPage();

    const titleInput = screen.getByRole('textbox', { name: 'Event Title' });
    const form = titleInput.closest('form');
    expect(form).not.toBeNull();
    fireEvent.submit(form!);

    expect(await screen.findByText('Title is required')).toBeInTheDocument();
    expect(screen.getByText('Description is required')).toBeInTheDocument();
    expect(screen.getByText('Start date is required')).toBeInTheDocument();
    expect(screen.getByText('Start time is required')).toBeInTheDocument();
    expect(mockToast.error).toHaveBeenCalledWith(
      'Please fix the highlighted fields before saving',
    );
    expect(mockApi.post).not.toHaveBeenCalled();
  });
});
