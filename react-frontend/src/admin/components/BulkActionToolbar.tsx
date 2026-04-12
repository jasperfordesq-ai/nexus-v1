// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * BulkActionToolbar
 * Appears above/below a DataTable when rows are selected.
 * Drives the final-confirm modal flow for destructive actions.
 *
 * Each action:
 *   - label          — i18n resolved label
 *   - onConfirm      — called after final confirmation (receives optional reason)
 *   - color          — HeroUI button color
 *   - destructive    — if true, renders with red styling and stronger wording
 *   - needsReason    — if true, shows a required Textarea in the confirm modal
 *   - confirmTitle / confirmMessage — override default wording
 */

import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
  Button,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Textarea,
} from '@heroui/react';
import { AlertTriangle, X } from 'lucide-react';

export interface BulkAction {
  key: string;
  label: string;
  color?: 'primary' | 'success' | 'warning' | 'danger' | 'default';
  destructive?: boolean;
  needsReason?: boolean;
  reasonLabel?: string;
  reasonPlaceholder?: string;
  confirmTitle?: string;
  confirmMessage?: string;
  confirmLabel?: string;
  icon?: React.ReactNode;
  onConfirm: (reason?: string) => Promise<void> | void;
}

interface BulkActionToolbarProps {
  selectedCount: number;
  actions: BulkAction[];
  onClearSelection: () => void;
  isLoading?: boolean;
}

export function BulkActionToolbar({
  selectedCount,
  actions,
  onClearSelection,
  isLoading = false,
}: BulkActionToolbarProps) {
  const { t } = useTranslation('admin');
  const [pending, setPending] = useState<BulkAction | null>(null);
  const [reason, setReason] = useState('');
  const [running, setRunning] = useState(false);

  if (selectedCount === 0) return null;

  const handleConfirm = async () => {
    if (!pending) return;
    if (pending.needsReason && !reason.trim()) return;
    setRunning(true);
    try {
      await pending.onConfirm(pending.needsReason ? reason.trim() : undefined);
      setPending(null);
      setReason('');
    } finally {
      setRunning(false);
    }
  };

  return (
    <>
      <div className="flex items-center justify-between gap-3 rounded-lg border border-primary/40 bg-primary/10 px-4 py-2 mb-3">
        <div className="flex items-center gap-2">
          <span className="text-sm font-medium">
            {t('bulk.selected_count', { count: selectedCount })}
          </span>
          <Button
            size="sm"
            variant="light"
            isIconOnly
            onPress={onClearSelection}
            aria-label={t('bulk.clear_selection')}
            isDisabled={isLoading || running}
          >
            <X size={14} />
          </Button>
        </div>
        <div className="flex items-center gap-2 flex-wrap">
          {actions.map((action) => (
            <Button
              key={action.key}
              size="sm"
              color={action.color ?? (action.destructive ? 'danger' : 'primary')}
              variant={action.destructive ? 'solid' : 'flat'}
              startContent={action.icon}
              onPress={() => {
                setReason('');
                setPending(action);
              }}
              isDisabled={isLoading || running}
            >
              {action.label}
            </Button>
          ))}
        </div>
      </div>

      {pending && (
        <Modal
          isOpen={!!pending}
          onClose={() => {
            if (!running) {
              setPending(null);
              setReason('');
            }
          }}
          size="md"
        >
          <ModalContent>
            <ModalHeader className="flex items-center gap-2">
              <AlertTriangle
                size={20}
                className={pending.destructive ? 'text-danger' : 'text-warning'}
              />
              {pending.confirmTitle
                ?? t('bulk.confirm_title', { action: pending.label })}
            </ModalHeader>
            <ModalBody>
              <p className="text-default-600">
                {pending.confirmMessage
                  ?? t('bulk.confirm_message', {
                    action: pending.label.toLowerCase(),
                    count: selectedCount,
                  })}
              </p>
              {pending.destructive && (
                <p className="mt-2 text-sm text-danger font-medium">
                  {t('bulk.destructive_warning')}
                </p>
              )}
              {pending.needsReason && (
                <Textarea
                  className="mt-3"
                  label={pending.reasonLabel ?? t('bulk.reason_label')}
                  placeholder={pending.reasonPlaceholder ?? t('bulk.reason_placeholder')}
                  value={reason}
                  onValueChange={setReason}
                  minRows={3}
                  maxRows={6}
                  variant="bordered"
                  isRequired
                />
              )}
            </ModalBody>
            <ModalFooter>
              <Button
                variant="flat"
                onPress={() => {
                  setPending(null);
                  setReason('');
                }}
                isDisabled={running}
              >
                {t('cancel')}
              </Button>
              <Button
                color={pending.destructive ? 'danger' : pending.color ?? 'primary'}
                onPress={handleConfirm}
                isLoading={running}
                isDisabled={
                  running || (pending.needsReason && !reason.trim())
                }
              >
                {pending.confirmLabel ?? pending.label}
              </Button>
            </ModalFooter>
          </ModalContent>
        </Modal>
      )}
    </>
  );
}

export default BulkActionToolbar;
