// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * runConfirmedMutation
 *
 * The API client (`api.post` / `api.delete` / …) resolves `{ success: false }`
 * WITHOUT throwing for a 4xx/5xx response — a thrown error only happens for a true
 * network/timeout failure. Handlers that `await` a mutation and then unconditionally
 * apply an optimistic UI update + a success toast therefore *fake success* whenever
 * the server rejects the request: the item visibly disappears / the toast says "done"
 * while the server is unchanged, until the next refresh re-syncs.
 *
 * This helper centralises the correct handling: it runs `onConfirmed` (the optimistic
 * update + success toast) ONLY when the server confirmed the change, runs `onRejected`
 * (the error toast) on a `{ success: false }` response OR a thrown error, and returns
 * whether the mutation actually succeeded.
 */
export async function runConfirmedMutation(
  request: () => Promise<{ success: boolean }>,
  handlers: {
    /** Optimistic UI update + success feedback — runs ONLY on a confirmed success. */
    onConfirmed: () => void;
    /** Error feedback — runs on a `{ success: false }` response OR a thrown error. */
    onRejected: () => void;
    /** Optional logging of a thrown (network/unexpected) error. */
    onError?: (error: unknown) => void;
  },
): Promise<boolean> {
  try {
    const response = await request();
    if (response.success) {
      handlers.onConfirmed();
      return true;
    }
    handlers.onRejected();
    return false;
  } catch (error) {
    handlers.onError?.(error);
    handlers.onRejected();
    return false;
  }
}
