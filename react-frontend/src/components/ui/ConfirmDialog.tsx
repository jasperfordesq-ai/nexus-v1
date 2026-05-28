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
 * Cancelling (Escape, backdrop click, or Cancel button) resolves false.
 * Confirming resolves true and auto-closes via the v3 slot="close" behavior.
 */

import {
  createContext,
  useCallback,
  useContext,
  useState,
  type ReactNode,
} from 'react';
import { useTranslation } from 'react-i18next';
import { AlertDialog, Button } from '@heroui/react';

type ConfirmStatus = 'accent' | 'success' | 'warning' | 'danger';

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

interface PendingConfirm extends ConfirmOptions {
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

  const status: ConfirmStatus = pending?.status ?? 'danger';
  const confirmVariant = status === 'danger' ? 'danger' : 'primary';

  return (
    <ConfirmContext.Provider value={confirm}>
      {children}
      <AlertDialog.Backdrop isOpen={pending !== null} onOpenChange={handleOpenChange}>
        <AlertDialog.Container>
          <AlertDialog.Dialog className="sm:max-w-[420px]">
            <AlertDialog.CloseTrigger />
            <AlertDialog.Header>
              <AlertDialog.Icon status={status} />
              <AlertDialog.Heading>{pending?.title}</AlertDialog.Heading>
            </AlertDialog.Header>
            {pending?.body && (
              <AlertDialog.Body>
                {typeof pending.body === 'string' ? <p>{pending.body}</p> : pending.body}
              </AlertDialog.Body>
            )}
            <AlertDialog.Footer>
              <Button slot="close" variant="tertiary" onPress={() => resolveAndClose(false)}>
                {pending?.cancelLabel ?? t('cancel')}
              </Button>
              <Button slot="close" variant={confirmVariant} onPress={() => resolveAndClose(true)}>
                {pending?.confirmLabel ?? t('confirm')}
              </Button>
            </AlertDialog.Footer>
          </AlertDialog.Dialog>
        </AlertDialog.Container>
      </AlertDialog.Backdrop>
    </ConfirmContext.Provider>
  );
}

export function useConfirm(): ConfirmFn {
  const ctx = useContext(ConfirmContext);
  if (!ctx) {
    throw new Error('useConfirm must be used within <ConfirmDialogProvider>');
  }
  return ctx;
}
