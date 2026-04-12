// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Confirmation Modal for Dangerous Actions
 * Used before delete, ban, suspend, and other destructive operations
 */

import { useTranslation } from 'react-i18next';
import {
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Button,
} from '@heroui/react';
import { AlertTriangle } from 'lucide-react';

interface ConfirmModalProps {
  isOpen: boolean;
  onClose: () => void;
  onConfirm: () => void;
  title: string;
  message: string;
  confirmLabel?: string;
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
  confirmColor = 'danger',
  isLoading = false,
  children,
}: ConfirmModalProps) {
  const { t } = useTranslation('admin');
  const resolvedConfirmLabel = confirmLabel ?? t('common.confirm');
  return (
    <Modal isOpen={isOpen} onClose={onClose} size="sm">
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
            {t('cancel')}
          </Button>
          <Button
            autoFocus
            color={confirmColor}
            onPress={onConfirm}
            isLoading={isLoading}
            isDisabled={isLoading}
          >
            {resolvedConfirmLabel}
          </Button>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
}

export default ConfirmModal;
