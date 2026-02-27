// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * ComposeSubmitContext — lets tab components register their submit capabilities
 * so ComposeHub can render a header-level submit button on mobile.
 *
 * Scoped to the ComposeHub subtree only (not a global provider).
 */

import { createContext, useCallback, useContext, useState } from 'react';
import type { ComposeSubmitRegistration } from './types';

interface ComposeSubmitContextValue {
  registration: ComposeSubmitRegistration | null;
  register: (reg: ComposeSubmitRegistration) => void;
  unregister: () => void;
}

const ComposeSubmitCtx = createContext<ComposeSubmitContextValue | null>(null);

export function ComposeSubmitProvider({ children }: { children: React.ReactNode }) {
  const [registration, setRegistration] = useState<ComposeSubmitRegistration | null>(null);

  const register = useCallback((reg: ComposeSubmitRegistration) => {
    setRegistration(reg);
  }, []);

  const unregister = useCallback(() => {
    setRegistration(null);
  }, []);

  return (
    <ComposeSubmitCtx.Provider value={{ registration, register, unregister }}>
      {children}
    </ComposeSubmitCtx.Provider>
  );
}

/** Hook for tabs to register their submit state, and for mobile header to read it. */
export function useComposeSubmit(): ComposeSubmitContextValue {
  const ctx = useContext(ComposeSubmitCtx);
  if (!ctx) {
    // Outside provider — return no-op stubs so tabs work in isolation (e.g., tests)
    return {
      registration: null,
      register: () => {},
      unregister: () => {},
    };
  }
  return ctx;
}
