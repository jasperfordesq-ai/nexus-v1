/**
 * Test Utilities
 * Provides wrapper components and helpers for testing
 */

import { ReactElement, ReactNode } from 'react';
import { render, RenderOptions } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import { HeroUIProvider } from '@heroui/react';
import { ToastProvider } from '@/contexts/ToastContext';

/**
 * All providers wrapper for testing
 */
function AllProviders({ children }: { children: ReactNode }) {
  return (
    <HeroUIProvider>
      <BrowserRouter>
        <ToastProvider>
          {children}
        </ToastProvider>
      </BrowserRouter>
    </HeroUIProvider>
  );
}

/**
 * Custom render function that wraps components with all providers
 */
function customRender(
  ui: ReactElement,
  options?: Omit<RenderOptions, 'wrapper'>
) {
  return render(ui, { wrapper: AllProviders, ...options });
}

// Re-export everything from testing-library
export * from '@testing-library/react';
export { default as userEvent } from '@testing-library/user-event';

// Override render with custom render
export { customRender as render };
