// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import userEvent from '@testing-library/user-event';
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

// ─── Contexts ─────────────────────────────────────────────────────────────────
const mockToast = {
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
  showToast: vi.fn(),
};

vi.mock('@/contexts', () =>
  createMockContexts({
    useAuth: () => ({
      user: { id: 1, name: 'Test User' },
      isAuthenticated: true,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      updateUser: vi.fn(),
      refreshUser: vi.fn(),
      status: 'idle' as const,
      error: null,
    }),
    useToast: () => mockToast,
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

// Stub heavy UI primitives that may not render well in jsdom
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/components/ui')>();
  return {
    ...orig,
    Tooltip: ({ children }: { children: React.ReactNode }) => <>{children}</>,
  };
});

// ─── Tests ───────────────────────────────────────────────────────────────────
describe('AiAssistButton', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders the AI assist button', async () => {
    const { AiAssistButton } = await import('./AiAssistButton');
    render(
      <AiAssistButton type="listing" title="Handmade candle" onGenerated={vi.fn()} />
    );
    // Button should exist — i18n key compose.ai_button
    const btn = screen.getByRole('button');
    expect(btn).toBeInTheDocument();
  });

  it('button is disabled when title is empty', async () => {
    const { AiAssistButton } = await import('./AiAssistButton');
    render(
      <AiAssistButton type="listing" title="" onGenerated={vi.fn()} />
    );
    const btn = screen.getByRole('button');
    // HeroUI isDisabled sets disabled attribute or aria-disabled depending on version
    const isDisabled =
      btn.hasAttribute('disabled') ||
      btn.getAttribute('aria-disabled') === 'true' ||
      btn.getAttribute('data-disabled') === 'true';
    expect(isDisabled).toBe(true);
  });

  it('button is enabled when title has content', async () => {
    const { AiAssistButton } = await import('./AiAssistButton');
    render(
      <AiAssistButton type="listing" title="Handmade candle" onGenerated={vi.fn()} />
    );
    const btn = screen.getByRole('button');
    expect(btn).not.toHaveAttribute('aria-disabled', 'true');
  });

  it('calls POST /ai/generate/listing with title on click', async () => {
    mockApi.post.mockResolvedValue({ success: true, data: { content: 'AI text here' } });
    const onGenerated = vi.fn();
    const { AiAssistButton } = await import('./AiAssistButton');
    render(
      <AiAssistButton type="listing" title="Handmade candle" onGenerated={onGenerated} />
    );
    await userEvent.click(screen.getByRole('button'));
    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith(
        '/ai/generate/listing',
        expect.objectContaining({ title: 'Handmade candle' })
      );
    });
  });

  it('calls onGenerated with content returned from API', async () => {
    const generatedText = 'Beautiful handmade soy candle.';
    mockApi.post.mockResolvedValue({ success: true, data: { content: generatedText } });
    const onGenerated = vi.fn();
    const { AiAssistButton } = await import('./AiAssistButton');
    render(
      <AiAssistButton type="listing" title="Candle" onGenerated={onGenerated} />
    );
    await userEvent.click(screen.getByRole('button'));
    await waitFor(() => {
      expect(onGenerated).toHaveBeenCalledWith(generatedText);
    });
  });

  it('shows success toast when AI generates content', async () => {
    mockApi.post.mockResolvedValue({ success: true, data: { content: 'AI content' } });
    const { AiAssistButton } = await import('./AiAssistButton');
    render(
      <AiAssistButton type="event" title="Community meeting" onGenerated={vi.fn()} />
    );
    await userEvent.click(screen.getByRole('button'));
    await waitFor(() => {
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('does not call API when title is whitespace-only (button disabled)', async () => {
    const { AiAssistButton } = await import('./AiAssistButton');
    render(
      <AiAssistButton type="listing" title="   " onGenerated={vi.fn()} />
    );
    const btn = screen.getByRole('button');
    // whitespace-only → isDisabled (HeroUI may use disabled, aria-disabled, or data-disabled)
    const isDisabled =
      btn.hasAttribute('disabled') ||
      btn.getAttribute('aria-disabled') === 'true' ||
      btn.getAttribute('data-disabled') === 'true';
    expect(isDisabled).toBe(true);
    expect(mockApi.post).not.toHaveBeenCalled();
  });

  it('shows error toast when API returns success=false', async () => {
    mockApi.post.mockResolvedValue({ success: false, data: null });
    const { AiAssistButton } = await import('./AiAssistButton');
    render(
      <AiAssistButton type="listing" title="Valid title" onGenerated={vi.fn()} />
    );
    await userEvent.click(screen.getByRole('button'));
    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('shows rate-limited toast on 429 error', async () => {
    mockApi.post.mockRejectedValue({ status: 429 });
    const { AiAssistButton } = await import('./AiAssistButton');
    render(
      <AiAssistButton type="listing" title="Valid title" onGenerated={vi.fn()} />
    );
    await userEvent.click(screen.getByRole('button'));
    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('shows unavailable toast on 403 error', async () => {
    mockApi.post.mockRejectedValue({ status: 403 });
    const { AiAssistButton } = await import('./AiAssistButton');
    render(
      <AiAssistButton type="listing" title="Valid title" onGenerated={vi.fn()} />
    );
    await userEvent.click(screen.getByRole('button'));
    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('shows generic error toast on unknown error', async () => {
    mockApi.post.mockRejectedValue(new Error('Network error'));
    const { AiAssistButton } = await import('./AiAssistButton');
    render(
      <AiAssistButton type="listing" title="Valid title" onGenerated={vi.fn()} />
    );
    await userEvent.click(screen.getByRole('button'));
    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('sends context when provided', async () => {
    mockApi.post.mockResolvedValue({ success: true, data: { content: 'text' } });
    const ctx = { category: 'home', duration: 2 };
    const { AiAssistButton } = await import('./AiAssistButton');
    render(
      <AiAssistButton
        type="event"
        title="Workshop"
        context={ctx}
        onGenerated={vi.fn()}
      />
    );
    await userEvent.click(screen.getByRole('button'));
    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith(
        '/ai/generate/event',
        expect.objectContaining({ context: ctx })
      );
    });
  });

  it('does not call onGenerated when API returns no content', async () => {
    mockApi.post.mockResolvedValue({ success: true, data: { content: '' } });
    const onGenerated = vi.fn();
    const { AiAssistButton } = await import('./AiAssistButton');
    render(
      <AiAssistButton type="listing" title="Valid title" onGenerated={onGenerated} />
    );
    await userEvent.click(screen.getByRole('button'));
    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
    expect(onGenerated).not.toHaveBeenCalled();
  });
});
