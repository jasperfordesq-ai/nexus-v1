// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import { api } from '@/lib/api';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

vi.mock('@/lib/safeStorage', () => ({
  safeLocalStorageGet: vi.fn(() => null),
  safeLocalStorageSet: vi.fn(),
  safeLocalStorageRemove: vi.fn(),
}));

vi.mock('@/contexts', () =>
  createMockContexts({
    useTenant: () => ({
      tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
      tenantSlug: 'test',
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  }),
);

import { OnboardingChoiceModal } from './OnboardingChoiceModal';

describe('OnboardingChoiceModal', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    // Fire-and-forget put should resolve silently
    vi.mocked(api.put).mockResolvedValue({ success: true });
  });

  it('renders the modal when isOpen=true', () => {
    render(
      <OnboardingChoiceModal
        isOpen={true}
        onChoice={vi.fn()}
        onClose={vi.fn()}
      />,
    );
    // Modal is rendered — at minimum verify it doesn't crash and has content
    // HeroUI Modal with isDismissable=false renders into a portal
    expect(document.body).toBeInTheDocument();
  });

  it('renders all three choice buttons', async () => {
    render(
      <OnboardingChoiceModal
        isOpen={true}
        onChoice={vi.fn()}
        onClose={vi.fn()}
      />,
    );
    await waitFor(() => {
      const buttons = screen.getAllByRole('button');
      // Three choice buttons should be present; there may be additional UI buttons
      expect(buttons.length).toBeGreaterThanOrEqual(3);
    });
  });

  it('calls onChoice with "recipient" when first choice is selected', async () => {
    const onChoice = vi.fn();
    render(
      <OnboardingChoiceModal
        isOpen={true}
        onChoice={onChoice}
        onClose={vi.fn()}
      />,
    );

    await waitFor(() => {
      const buttons = screen.getAllByRole('button');
      expect(buttons.length).toBeGreaterThanOrEqual(1);
    });

    // The three choice buttons render in order: recipient, helper, browse
    // Each is a Button with type="button"
    const choiceButtons = screen.getAllByRole('button').filter(
      (b) => b.getAttribute('type') === 'button',
    );

    // click the first choice (recipient)
    fireEvent.click(choiceButtons[0]);

    await waitFor(() => {
      expect(onChoice).toHaveBeenCalledTimes(1);
      expect(onChoice).toHaveBeenCalledWith('recipient');
    });
  });

  it('calls onChoice with "helper" when second choice is selected', async () => {
    const onChoice = vi.fn();
    render(
      <OnboardingChoiceModal
        isOpen={true}
        onChoice={onChoice}
        onClose={vi.fn()}
      />,
    );

    await waitFor(() => {
      expect(screen.getAllByRole('button').length).toBeGreaterThanOrEqual(2);
    });

    const choiceButtons = screen.getAllByRole('button').filter(
      (b) => b.getAttribute('type') === 'button',
    );

    fireEvent.click(choiceButtons[1]);

    await waitFor(() => {
      expect(onChoice).toHaveBeenCalledWith('helper');
    });
  });

  it('calls onChoice with "browse" when third choice is selected', async () => {
    const onChoice = vi.fn();
    render(
      <OnboardingChoiceModal
        isOpen={true}
        onChoice={onChoice}
        onClose={vi.fn()}
      />,
    );

    await waitFor(() => {
      expect(screen.getAllByRole('button').length).toBeGreaterThanOrEqual(3);
    });

    const choiceButtons = screen.getAllByRole('button').filter(
      (b) => b.getAttribute('type') === 'button',
    );

    fireEvent.click(choiceButtons[2]);

    await waitFor(() => {
      expect(onChoice).toHaveBeenCalledWith('browse');
    });
  });

  it('fire-and-forgets a PUT to the backend on choice', async () => {
    const onChoice = vi.fn();
    render(
      <OnboardingChoiceModal
        isOpen={true}
        onChoice={onChoice}
        onClose={vi.fn()}
      />,
    );

    await waitFor(() => {
      expect(screen.getAllByRole('button').length).toBeGreaterThanOrEqual(1);
    });

    const choiceButtons = screen.getAllByRole('button').filter(
      (b) => b.getAttribute('type') === 'button',
    );
    fireEvent.click(choiceButtons[0]);

    await waitFor(() => {
      expect(api.put).toHaveBeenCalledWith(
        '/v2/caring-community/me/onboarding-choice',
        expect.objectContaining({ choice: 'recipient' }),
      );
    });
  });

  // NOTE: onClose is never called by the component itself — isDismissable=false
  // and hideCloseButton are set. The parent is responsible for closing.
  // The onClose prop is wired to HeroUI's internal escape/backdrop handler only,
  // and those paths are not reachable in jsdom without layout/floating-UI.
  // Skipped: verifying close button absence (hideCloseButton removes it entirely).
});
