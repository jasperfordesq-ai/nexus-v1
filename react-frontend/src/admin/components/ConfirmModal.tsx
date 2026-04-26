// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Confirmation Modal for Dangerous Actions
 * Used before delete, ban, suspend, and other destructive operations
 */

import { useRef } from 'react';
import {
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Button,
} from '@heroui/react';
import AlertTriangle from 'lucide-react/icons/triangle-alert';

interface ConfirmModalProps {
  isOpen: boolean;
  onClose: () => void;
  onConfirm: () => void;
  title: string;
  message: string;
  confirmLabel?: string;
  /**
   * Label for the Cancel button. Pass a translated string from the
   * caller's i18n namespace — the broker panel uses `t('common.cancel')`.
   * Defaults to "Cancel" for backward compatibility with existing admin
   * call sites.
   */
  cancelLabel?: string;
  confirmColor?: 'danger' | 'warning' | 'primary';
  isLoading?: boolean;
  children?: React.ReactNode;
}

export function ConfirmModal({
  isOpen,
  onClose,
  onConfirm,
  title,
  message,
  confirmLabel,
  cancelLabel,
  confirmColor = 'danger',
  isLoading = false,
  children,
}: ConfirmModalProps) {
  const resolvedConfirmLabel = confirmLabel ?? "Confirm";
  const resolvedCancelLabel = cancelLabel ?? "Cancel";
  // Synchronous double-click gate. `isLoading` becomes true after the parent
  // re-renders; in the microsecond window between the two clicks, the second
  // press still fires onConfirm. The ref blocks re-entry within the same
  // render tick. Reset whenever the modal closes.
  const inFlightRef = useRef(false);
  if (!isOpen && inFlightRef.current) {
    inFlightRef.current = false;
  }
  const handleConfirm = () => {
    if (inFlightRef.current || isLoading) return;
    inFlightRef.current = true;
    onConfirm();
  };
  return (
    <Modal
      isOpen={isOpen}
      onClose={() => {
        inFlightRef.current = false;
        onClose();
      }}
      size="sm"
    >
      <ModalContent>
        <ModalHeader className="flex items-center gap-2">
          <AlertTriangle size={20} className="text-warning" />
          {title}
        </ModalHeader>
        <ModalBody>
          <p className="text-default-600">{message}</p>
          {children}
        </ModalBody>
        <ModalFooter>
          <Button variant="flat" onPress={onClose} isDisabled={isLoading}>
            {resolvedCancelLabel}
          </Button>
          <Button
            autoFocus
            color={confirmColor}
            onPress={handleConfirm}
            isLoading={isLoading}
            isDisabled={isLoading || inFlightRef.current}
          >
            {resolvedConfirmLabel}
          </Button>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
}

export default ConfirmModal;
