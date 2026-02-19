// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * App Update Modal
 * Shown when a newer version of the native app is available.
 * Force updates cannot be dismissed.
 */

import { Modal, ModalContent, ModalHeader, ModalBody, ModalFooter, Button } from '@heroui/react';
import { Download, Sparkles } from 'lucide-react';
import type { AppUpdateInfo } from '@/hooks/useAppUpdate';

interface AppUpdateModalProps {
  updateInfo: AppUpdateInfo;
  onDismiss: () => void;
}

export function AppUpdateModal({ updateInfo, onDismiss }: AppUpdateModalProps) {
  const notes = Object.values(updateInfo.releaseNotes).flat();

  const handleUpdate = () => {
    window.open(updateInfo.updateUrl, '_system');
  };

  return (
    <Modal
      isOpen
      onClose={updateInfo.forceUpdate ? undefined : onDismiss}
      isDismissable={!updateInfo.forceUpdate}
      hideCloseButton={updateInfo.forceUpdate}
      size="md"
    >
      <ModalContent>
        <ModalHeader className="flex items-center gap-2">
          <Sparkles size={20} className="text-primary" />
          Update Available
        </ModalHeader>
        <ModalBody>
          <p className="text-default-600">{updateInfo.updateMessage}</p>

          <p className="text-sm text-default-400">
            Version {updateInfo.currentVersion} is now available (you have {updateInfo.clientVersion})
          </p>

          {notes.length > 0 && (
            <div className="mt-2">
              <p className="text-sm font-medium text-default-700 mb-1">What's new:</p>
              <ul className="list-disc list-inside text-sm text-default-500 space-y-0.5">
                {notes.map((note, i) => (
                  <li key={i}>{note}</li>
                ))}
              </ul>
            </div>
          )}
        </ModalBody>
        <ModalFooter>
          {!updateInfo.forceUpdate && (
            <Button variant="flat" onPress={onDismiss}>
              Later
            </Button>
          )}
          <Button
            color="primary"
            startContent={<Download size={16} />}
            onPress={handleUpdate}
          >
            Download Update
          </Button>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
}
