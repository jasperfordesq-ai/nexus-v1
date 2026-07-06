// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * BuilderPreviewModal - a device-framed preview of compiled builder HTML.
 *
 * The builder canvas shows editable content; this renders the exported HTML
 * into a sandboxed iframe (no scripts) at a desktop or mobile width.
 */

import { useState } from 'react';
import { Button, Modal, ModalBody, ModalContent, ModalHeader } from '@/components/ui';
import Monitor from 'lucide-react/icons/monitor';
import Smartphone from 'lucide-react/icons/smartphone';

type PreviewDevice = 'desktop' | 'mobile';

interface BuilderPreviewModalProps {
  isOpen: boolean;
  onClose: () => void;
  /** Compiled HTML to render (from the builder's exportHtml). */
  html: string;
  t: (key: string) => string;
  labels?: Partial<{
    title: string;
    deviceLabel: string;
    desktop: string;
    mobile: string;
  }>;
}

const FRAME_WIDTH: Record<PreviewDevice, string> = {
  desktop: 'w-[640px]',
  mobile: 'w-[375px]',
};

export function BuilderPreviewModal({ isOpen, onClose, html, t, labels }: BuilderPreviewModalProps) {
  const [device, setDevice] = useState<PreviewDevice>('desktop');

  return (
    <Modal isOpen={isOpen} onOpenChange={(open) => !open && onClose()} size="5xl" scrollBehavior="inside">
      <ModalContent>
        <ModalHeader className="flex items-center justify-between gap-4">
          <span>{labels?.title ?? t('newsletter_builder.preview_title')}</span>
          <div className="flex items-center gap-1" role="group" aria-label={labels?.deviceLabel ?? t('newsletter_builder.preview_device_label')}>
            <Button
              size="sm"
              variant={device === 'desktop' ? 'primary' : 'light'}
              startContent={<Monitor size={15} />}
              onPress={() => setDevice('desktop')}
              aria-pressed={device === 'desktop'}
            >
              {labels?.desktop ?? t('newsletter_builder.preview_desktop')}
            </Button>
            <Button
              size="sm"
              variant={device === 'mobile' ? 'primary' : 'light'}
              startContent={<Smartphone size={15} />}
              onPress={() => setDevice('mobile')}
              aria-pressed={device === 'mobile'}
            >
              {labels?.mobile ?? t('newsletter_builder.preview_mobile')}
            </Button>
          </div>
        </ModalHeader>
        <ModalBody className="bg-surface-secondary">
          <div className="flex justify-center py-4">
            <iframe
              title={labels?.title ?? t('newsletter_builder.preview_title')}
              srcDoc={html}
              sandbox=""
              className={`h-[70vh] max-w-full rounded-lg border border-border bg-white shadow-sm transition-[width] ${FRAME_WIDTH[device]}`}
            />
          </div>
        </ModalBody>
      </ModalContent>
    </Modal>
  );
}

export default BuilderPreviewModal;
