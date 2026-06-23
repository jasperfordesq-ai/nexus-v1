// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@/test/test-utils';
import React from 'react';

// No API calls in this module — no api mock needed.
// No @/contexts usage either.

// ─────────────────────────────────────────────────────────────────────────────
// Helper: a consumer component that exercises the context
// ─────────────────────────────────────────────────────────────────────────────

function TestConsumer() {
  // Dynamic import happens at describe level; here we just import statically
  // at the top level of each test via dynamic import.
  return null;
}
// suppress lint — TestConsumer not used directly below, we use dynamic imports per test
void TestConsumer;

// ─────────────────────────────────────────────────────────────────────────────
describe('ComposeSubmitContext / useComposeSubmit', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('provides null registration by default inside the Provider', async () => {
    const { ComposeSubmitProvider, useComposeSubmit } = await import('./ComposeSubmitContext');

    function Probe() {
      const { registration } = useComposeSubmit();
      return <div data-testid="reg">{registration === null ? 'null' : 'set'}</div>;
    }

    render(
      <ComposeSubmitProvider>
        <Probe />
      </ComposeSubmitProvider>
    );

    expect(screen.getByTestId('reg')).toHaveTextContent('null');
  });

  it('registers a submit payload and exposes it to consumers', async () => {
    const { ComposeSubmitProvider, useComposeSubmit } = await import('./ComposeSubmitContext');
    const onSubmit = vi.fn();

    function Registrar() {
      const { register } = useComposeSubmit();
      React.useEffect(() => {
        register({
          canSubmit: true,
          isSubmitting: false,
          onSubmit,
          buttonLabel: 'Post',
          gradientClass: 'from-blue-500',
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
      }, []);
      return null;
    }

    function Reader() {
      const { registration } = useComposeSubmit();
      if (!registration) return <div data-testid="status">unregistered</div>;
      return (
        <div>
          <div data-testid="status">registered</div>
          <div data-testid="label">{registration.buttonLabel}</div>
          <div data-testid="can-submit">{registration.canSubmit ? 'yes' : 'no'}</div>
        </div>
      );
    }

    render(
      <ComposeSubmitProvider>
        <Registrar />
        <Reader />
      </ComposeSubmitProvider>
    );

    await waitFor(() => {
      expect(screen.getByTestId('status')).toHaveTextContent('registered');
    });
    expect(screen.getByTestId('label')).toHaveTextContent('Post');
    expect(screen.getByTestId('can-submit')).toHaveTextContent('yes');
  });

  it('unregisters the submit payload, restoring registration to null', async () => {
    const { ComposeSubmitProvider, useComposeSubmit } = await import('./ComposeSubmitContext');

    function Controller() {
      const { register, unregister } = useComposeSubmit();
      return (
        <div>
          <button
            onClick={() =>
              register({
                canSubmit: true,
                isSubmitting: false,
                onSubmit: vi.fn(),
                buttonLabel: 'Publish',
                gradientClass: '',
              })
            }
            data-testid="register-btn"
          >
            Register
          </button>
          <button onClick={unregister} data-testid="unregister-btn">
            Unregister
          </button>
        </div>
      );
    }

    function Display() {
      const { registration } = useComposeSubmit();
      return <div data-testid="status">{registration ? registration.buttonLabel : 'none'}</div>;
    }

    render(
      <ComposeSubmitProvider>
        <Controller />
        <Display />
      </ComposeSubmitProvider>
    );

    expect(screen.getByTestId('status')).toHaveTextContent('none');

    fireEvent.click(screen.getByTestId('register-btn'));
    await waitFor(() => expect(screen.getByTestId('status')).toHaveTextContent('Publish'));

    fireEvent.click(screen.getByTestId('unregister-btn'));
    await waitFor(() => expect(screen.getByTestId('status')).toHaveTextContent('none'));
  });

  it('calling onSubmit from registration triggers the provided callback', async () => {
    const { ComposeSubmitProvider, useComposeSubmit } = await import('./ComposeSubmitContext');
    const handleSubmit = vi.fn();

    function Reg() {
      const { register } = useComposeSubmit();
      React.useEffect(() => {
        register({
          canSubmit: true,
          isSubmitting: false,
          onSubmit: handleSubmit,
          buttonLabel: 'Save',
          gradientClass: '',
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
      }, []);
      return null;
    }

    function Trigger() {
      const { registration } = useComposeSubmit();
      return (
        <button onClick={() => registration?.onSubmit()} data-testid="submit-btn">
          Submit
        </button>
      );
    }

    render(
      <ComposeSubmitProvider>
        <Reg />
        <Trigger />
      </ComposeSubmitProvider>
    );

    await waitFor(() => expect(screen.getByTestId('submit-btn')).toBeInTheDocument());
    fireEvent.click(screen.getByTestId('submit-btn'));
    expect(handleSubmit).toHaveBeenCalledTimes(1);
  });

  it('overwriting registration replaces the previous one', async () => {
    const { ComposeSubmitProvider, useComposeSubmit } = await import('./ComposeSubmitContext');

    function Controller() {
      const { register } = useComposeSubmit();
      return (
        <div>
          <button
            onClick={() =>
              register({ canSubmit: true, isSubmitting: false, onSubmit: vi.fn(), buttonLabel: 'First', gradientClass: '' })
            }
            data-testid="btn-first"
          >
            First
          </button>
          <button
            onClick={() =>
              register({ canSubmit: false, isSubmitting: true, onSubmit: vi.fn(), buttonLabel: 'Second', gradientClass: '' })
            }
            data-testid="btn-second"
          >
            Second
          </button>
        </div>
      );
    }

    function Display() {
      const { registration } = useComposeSubmit();
      return (
        <div>
          <div data-testid="label">{registration?.buttonLabel ?? 'none'}</div>
          <div data-testid="is-submitting">{registration?.isSubmitting ? 'yes' : 'no'}</div>
        </div>
      );
    }

    render(
      <ComposeSubmitProvider>
        <Controller />
        <Display />
      </ComposeSubmitProvider>
    );

    fireEvent.click(screen.getByTestId('btn-first'));
    await waitFor(() => expect(screen.getByTestId('label')).toHaveTextContent('First'));

    fireEvent.click(screen.getByTestId('btn-second'));
    await waitFor(() => {
      expect(screen.getByTestId('label')).toHaveTextContent('Second');
      expect(screen.getByTestId('is-submitting')).toHaveTextContent('yes');
    });
  });

  it('useComposeSubmit returns no-op stubs when used outside Provider', async () => {
    const { useComposeSubmit } = await import('./ComposeSubmitContext');

    function Orphan() {
      const { registration, register, unregister } = useComposeSubmit();
      // Calling register/unregister must not throw
      register({ canSubmit: true, isSubmitting: false, onSubmit: vi.fn(), buttonLabel: 'X', gradientClass: '' });
      unregister();
      return <div data-testid="orphan-reg">{registration === null ? 'null' : 'set'}</div>;
    }

    // Render WITHOUT the Provider — should not throw
    render(<Orphan />);
    expect(screen.getByTestId('orphan-reg')).toHaveTextContent('null');
  });

  it('exposes gradientClass from the registration payload', async () => {
    const { ComposeSubmitProvider, useComposeSubmit } = await import('./ComposeSubmitContext');

    function Reg() {
      const { register } = useComposeSubmit();
      React.useEffect(() => {
        register({
          canSubmit: true,
          isSubmitting: false,
          onSubmit: vi.fn(),
          buttonLabel: 'Post',
          gradientClass: 'from-purple-500 to-pink-500',
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
      }, []);
      return null;
    }

    function Reader() {
      const { registration } = useComposeSubmit();
      return <div data-testid="gradient">{registration?.gradientClass ?? ''}</div>;
    }

    render(
      <ComposeSubmitProvider>
        <Reg />
        <Reader />
      </ComposeSubmitProvider>
    );

    await waitFor(() => {
      expect(screen.getByTestId('gradient')).toHaveTextContent('from-purple-500 to-pink-500');
    });
  });

  it('isSubmitting flag is reflected accurately via registration', async () => {
    const { ComposeSubmitProvider, useComposeSubmit } = await import('./ComposeSubmitContext');

    function Controller() {
      const { register } = useComposeSubmit();
      return (
        <button
          onClick={() =>
            register({ canSubmit: false, isSubmitting: true, onSubmit: vi.fn(), buttonLabel: 'Saving…', gradientClass: '' })
          }
          data-testid="start-submit"
        >
          Start
        </button>
      );
    }

    function Display() {
      const { registration } = useComposeSubmit();
      return <div data-testid="submitting">{registration?.isSubmitting ? 'busy' : 'idle'}</div>;
    }

    render(
      <ComposeSubmitProvider>
        <Controller />
        <Display />
      </ComposeSubmitProvider>
    );

    expect(screen.getByTestId('submitting')).toHaveTextContent('idle');
    fireEvent.click(screen.getByTestId('start-submit'));
    await waitFor(() => expect(screen.getByTestId('submitting')).toHaveTextContent('busy'));
  });

  it('multiple consumers share the same registration state', async () => {
    const { ComposeSubmitProvider, useComposeSubmit } = await import('./ComposeSubmitContext');

    function Reg() {
      const { register } = useComposeSubmit();
      React.useEffect(() => {
        register({ canSubmit: true, isSubmitting: false, onSubmit: vi.fn(), buttonLabel: 'Shared', gradientClass: '' });
        // eslint-disable-next-line react-hooks/exhaustive-deps
      }, []);
      return null;
    }

    function ConsumerA() {
      const { registration } = useComposeSubmit();
      return <div data-testid="consumer-a">{registration?.buttonLabel ?? 'none'}</div>;
    }

    function ConsumerB() {
      const { registration } = useComposeSubmit();
      return <div data-testid="consumer-b">{registration?.canSubmit ? 'can' : 'cannot'}</div>;
    }

    render(
      <ComposeSubmitProvider>
        <Reg />
        <ConsumerA />
        <ConsumerB />
      </ComposeSubmitProvider>
    );

    await waitFor(() => {
      expect(screen.getByTestId('consumer-a')).toHaveTextContent('Shared');
      expect(screen.getByTestId('consumer-b')).toHaveTextContent('can');
    });
  });
});
