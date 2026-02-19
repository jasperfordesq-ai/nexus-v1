// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for ToastContext
 */

import { describe, it, expect, vi } from 'vitest';
import { render, screen, act } from '@testing-library/react';
import { ToastProvider, useToast } from './ToastContext';

// Test component that uses the toast hook
function TestComponent() {
  const { success, error, warning, info, toasts } = useToast();

  return (
    <div>
      <button onClick={() => success('Success!', 'It worked')}>Show Success</button>
      <button onClick={() => error('Error!', 'Something failed')}>Show Error</button>
      <button onClick={() => warning('Warning!', 'Be careful')}>Show Warning</button>
      <button onClick={() => info('Info', 'FYI')}>Show Info</button>
      <div data-testid="toast-count">{toasts.length}</div>
    </div>
  );
}

describe('ToastContext', () => {
  it('provides toast functions', () => {
    render(
      <ToastProvider>
        <TestComponent />
      </ToastProvider>
    );

    expect(screen.getByRole('button', { name: 'Show Success' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Show Error' })).toBeInTheDocument();
  });

  it('adds toast when success is called', async () => {
    render(
      <ToastProvider>
        <TestComponent />
      </ToastProvider>
    );

    act(() => {
      screen.getByRole('button', { name: 'Show Success' }).click();
    });

    expect(screen.getByText('Success!')).toBeInTheDocument();
    expect(screen.getByText('It worked')).toBeInTheDocument();
  });

  it('adds toast when error is called', async () => {
    render(
      <ToastProvider>
        <TestComponent />
      </ToastProvider>
    );

    act(() => {
      screen.getByRole('button', { name: 'Show Error' }).click();
    });

    expect(screen.getByText('Error!')).toBeInTheDocument();
    expect(screen.getByText('Something failed')).toBeInTheDocument();
  });

  it('throws error when useToast is used outside provider', () => {
    // Suppress console.error for this test
    const spy = vi.spyOn(console, 'error').mockImplementation(() => {});

    expect(() => {
      render(<TestComponent />);
    }).toThrow('useToast must be used within a ToastProvider');

    spy.mockRestore();
  });

  it('increments toast count when adding toasts', () => {
    render(
      <ToastProvider>
        <TestComponent />
      </ToastProvider>
    );

    expect(screen.getByTestId('toast-count')).toHaveTextContent('0');

    act(() => {
      screen.getByRole('button', { name: 'Show Success' }).click();
    });

    expect(screen.getByTestId('toast-count')).toHaveTextContent('1');

    act(() => {
      screen.getByRole('button', { name: 'Show Error' }).click();
    });

    expect(screen.getByTestId('toast-count')).toHaveTextContent('2');
  });
});
