// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React, { useCallback, useState } from 'react';

import ConfirmDialog from '@/components/ui/ConfirmDialog';

export interface ConfirmOptions {
  title: string;
  message?: string;
  confirmLabel: string;
  cancelLabel: string;
  confirmAccessibilityLabel?: string;
  cancelAccessibilityLabel?: string;
  confirmTestID?: string;
  cancelTestID?: string;
  /** 'danger' (default) for destructive actions, 'primary' otherwise. */
  variant?: 'primary' | 'danger';
  onConfirm: () => void | Promise<void>;
}

/**
 * Branded replacement for the confirmation form of `Alert.alert(title, msg,
 * [cancel, confirm])`. Returns an imperative `confirm(options)` opener plus a
 * `confirmDialog` element to render once in the screen tree:
 *
 *   const { confirm, confirmDialog } = useConfirm();
 *   ...
 *   confirm({ title, message, confirmLabel, cancelLabel, variant: 'danger',
 *             onConfirm: () => doDelete() });
 *   ...
 *   return (<>{screen}{confirmDialog}</>);
 *
 * The dialog shows a spinner while an async `onConfirm` resolves, then closes.
 * Errors thrown by `onConfirm` still close the dialog — surface them with a
 * toast inside the action itself.
 */
export function useConfirm() {
  const [options, setOptions] = useState<ConfirmOptions | null>(null);
  const [isConfirming, setIsConfirming] = useState(false);

  const confirm = useCallback((opts: ConfirmOptions) => {
    setOptions(opts);
  }, []);

  const close = useCallback(() => {
    if (isConfirming) return;
    setOptions(null);
  }, [isConfirming]);

  const handleConfirm = useCallback(async () => {
    if (!options) return;
    const action = options.onConfirm;
    setIsConfirming(true);
    try {
      await action();
    } finally {
      setIsConfirming(false);
      setOptions(null);
    }
  }, [options]);

  const confirmDialog = (
    <ConfirmDialog
      visible={options !== null}
      title={options?.title ?? ''}
      message={options?.message}
      cancelLabel={options?.cancelLabel ?? ''}
      confirmLabel={options?.confirmLabel ?? ''}
      cancelAccessibilityLabel={options?.cancelAccessibilityLabel}
      confirmAccessibilityLabel={options?.confirmAccessibilityLabel}
      cancelTestID={options?.cancelTestID}
      confirmTestID={options?.confirmTestID}
      variant={options?.variant ?? 'danger'}
      isConfirming={isConfirming}
      onClose={close}
      onConfirm={handleConfirm}
    />
  );

  return { confirm, confirmDialog };
}
