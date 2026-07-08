// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * ConfirmDialog — Themed confirmation dialog backed by HeroUI v3 AlertDialog.
 *
 * Replaces the native `window.confirm()` pattern with a Promise-based imperative API
 * that renders the project's themed AlertDialog. Mount <ConfirmDialogProvider>
 * once near the app root, then call useConfirm() from any component:
 *
 *   const confirm = useConfirm();
 *   const handleDelete = async () => {
 *     const ok = await confirm({
 *       title: t('reviews.delete_confirm_title'),
 *       body: t('reviews.delete_confirm_body'),
 *       status: 'danger',
 *       confirmLabel: t('common:delete'),
 *     });
 *     if (!ok) return;
 *     await deleteItem();
 *   };
 *
 * Cancelling (Escape, backdrop click, close ✕, or Cancel button) resolves false.
 * Confirming resolves true via the Confirm button's own onPress; the dialog
 * closes through the controlled open state (pending → null). Each button has
 * exactly ONE resolver — the action buttons must NOT carry slot="close", because
 * in a controlled dialog slot="close" fires onOpenChange(false) → resolveAndClose(false),
 * which races the Confirm button's resolveAndClose(true) and can make confirm()
 * wrongly resolve false in a real browser.
 */

import {
  createContext,
  lazy,
  Suspense,
  useCallback,
  use,
  useState,
  type ReactNode,
} from 'react';
import { useTranslation } from 'react-i18next';

const ConfirmDialogSurface = lazy(() =>
  import('./ConfirmDialogSurface').then((module) => ({
    default: module.ConfirmDialogSurface,
  })),
);

export type ConfirmStatus = 'accent' | 'success' | 'warning' | 'danger';

export interface ConfirmOptions {
  title: ReactNode;
  body?: ReactNode;
  confirmLabel?: ReactNode;
  cancelLabel?: ReactNode;
  /** Controls icon + confirm button variant. Defaults to 'danger' since most replaced window.confirm() calls are destructive. */
  status?: ConfirmStatus;
}

type ConfirmFn = (options: ConfirmOptions) => Promise<boolean>;

const ConfirmContext = createContext<ConfirmFn | null>(null);

export interface PendingConfirm extends ConfirmOptions {
  resolve: (value: boolean) => void;
}

export function ConfirmDialogProvider({ children }: { children: ReactNode }) {
  const { t } = useTranslation('common');
  const [pending, setPending] = useState<PendingConfirm | null>(null);

  const confirm = useCallback<ConfirmFn>((options) => {
    return new Promise<boolean>((resolve) => {
      setPending({ ...options, resolve });
    });
  }, []);

  const resolveAndClose = useCallback((value: boolean) => {
    setPending((current) => {
      current?.resolve(value);
      return null;
    });
  }, []);

  const handleOpenChange = useCallback((open: boolean) => {
    if (!open) {
      resolveAndClose(false);
    }
  }, [resolveAndClose]);

  return (
    <ConfirmContext.Provider value={confirm}>
      {children}
      {pending !== null ? (
        <Suspense fallback={null}>
          <ConfirmDialogSurface
            pending={pending}
            onOpenChange={handleOpenChange}
            onResolve={resolveAndClose}
            cancelLabel={t('cancel')}
            confirmLabel={t('confirm')}
          />
        </Suspense>
      ) : null}
    </ConfirmContext.Provider>
  );
}

export function useConfirm(): ConfirmFn {
  const ctx = use(ConfirmContext);
  if (!ctx) {
    throw new Error('useConfirm must be used within <ConfirmDialogProvider>');
  }
  return ctx;
}
