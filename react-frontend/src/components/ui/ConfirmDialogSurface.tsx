// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { AlertDialog } from './AlertDialog';
import { Button } from './Button';
import type { ConfirmStatus, PendingConfirm } from './ConfirmDialog';

interface ConfirmDialogSurfaceProps {
  pending: PendingConfirm | null;
  onOpenChange: (open: boolean) => void;
  onResolve: (value: boolean) => void;
  cancelLabel: string;
  confirmLabel: string;
}

export function ConfirmDialogSurface({
  pending,
  onOpenChange,
  onResolve,
  cancelLabel,
  confirmLabel,
}: ConfirmDialogSurfaceProps) {
  const status: ConfirmStatus = pending?.status ?? 'danger';
  const confirmVariant = status === 'danger' ? 'danger' : 'primary';

  return (
    <AlertDialog.Backdrop isOpen={pending !== null} onOpenChange={onOpenChange}>
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
            <Button variant="tertiary" onPress={() => onResolve(false)}>
              {pending?.cancelLabel ?? cancelLabel}
            </Button>
            <Button variant={confirmVariant} onPress={() => onResolve(true)}>
              {pending?.confirmLabel ?? confirmLabel}
            </Button>
          </AlertDialog.Footer>
        </AlertDialog.Dialog>
      </AlertDialog.Container>
    </AlertDialog.Backdrop>
  );
}

