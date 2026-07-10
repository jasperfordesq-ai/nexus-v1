// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import ToastViewport from './ToastViewport';

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key: string) => key }),
}));

describe('ToastViewport', () => {
  it('uses narrow-screen, safe-area, mobile-navigation, and mini-player-aware positioning', () => {
    const { container } = render(
      <ToastViewport
        toasts={[{ id: 'info-1', type: 'info', title: 'Saved' }]}
        onRemove={vi.fn()}
      />,
    );

    const viewport = container.firstElementChild;
    expect(viewport).toHaveClass('inset-x-3', 'w-auto', 'max-w-sm');
    expect(viewport?.className).toContain('5rem+var(--safe-area-bottom)');
    expect(viewport?.className).toContain('var(--miniplayer-offset,0rem)');
    expect(viewport?.className).toContain('sm:w-[calc(100vw-2rem)]');
  });

  it('provides a 44px dismiss target and removes the selected toast', () => {
    const onRemove = vi.fn();
    render(
      <ToastViewport
        toasts={[{ id: 'warning-1', type: 'warning', title: 'Check this' }]}
        onRemove={onRemove}
      />,
    );

    const dismiss = screen.getByRole('button', { name: 'toast.aria_dismiss_notification' });
    expect(dismiss).toHaveClass('h-11', 'w-11');

    fireEvent.click(dismiss);
    expect(onRemove).toHaveBeenCalledWith('warning-1');
  });

  it('keeps urgent and polite notifications in separate live regions', () => {
    render(
      <ToastViewport
        toasts={[
          { id: 'error-1', type: 'error', title: 'Urgent' },
          { id: 'success-1', type: 'success', title: 'Complete' },
        ]}
        onRemove={vi.fn()}
      />,
    );

    expect(screen.getByRole('alert')).toHaveAttribute('aria-live', 'assertive');
    expect(screen.getByRole('status')).toHaveAttribute('aria-live', 'polite');
    expect(screen.getByText('Urgent')).toBeInTheDocument();
    expect(screen.getByText('Complete')).toBeInTheDocument();
  });
});
