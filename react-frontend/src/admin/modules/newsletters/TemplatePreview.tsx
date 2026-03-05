// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * TemplatePreview — Modal with sandboxed iframe rendering of template HTML.
 * Used from Templates list and TemplateForm for previewing newsletter templates.
 */

import { useState, useEffect, useCallback } from 'react';
import {
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Button,
  Spinner,
} from '@heroui/react';
import { X } from 'lucide-react';
import { adminNewsletters } from '../../api/adminApi';

interface TemplatePreviewProps {
  templateId: number;
  isOpen: boolean;
  onClose: () => void;
}

export function TemplatePreview({ templateId, isOpen, onClose }: TemplatePreviewProps) {
  const [html, setHtml] = useState('');
  const [name, setName] = useState('');
  const [subject, setSubject] = useState('');
  const [loading, setLoading] = useState(true);

  const loadPreview = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminNewsletters.previewTemplate(templateId);
      if (res.success && res.data) {
        const data = res.data as { html?: string; name?: string; subject?: string };
        setHtml(data.html || '<p style="color:#666;text-align:center;padding:40px;">No content</p>');
        setName(data.name || 'Template Preview');
        setSubject(data.subject || '');
      }
    } catch {
      setHtml('<p style="color:#c00;text-align:center;padding:40px;">Failed to load preview</p>');
    } finally {
      setLoading(false);
    }
  }, [templateId]);

  useEffect(() => {
    if (isOpen) {
      loadPreview();
    }
  }, [isOpen, loadPreview]);

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      size="4xl"
      scrollBehavior="inside"
    >
      <ModalContent>
        <ModalHeader className="flex flex-col gap-1">
          <span>{name}</span>
          {subject && (
            <span className="text-sm font-normal text-default-500">
              Subject: {subject}
            </span>
          )}
        </ModalHeader>
        <ModalBody className="p-0">
          {loading ? (
            <div className="flex items-center justify-center py-20">
              <Spinner size="lg" label="Loading preview..." />
            </div>
          ) : (
            <iframe
              title="Template Preview"
              sandbox="allow-same-origin"
              srcDoc={html}
              className="w-full border-0"
              style={{ minHeight: '500px', height: '70vh' }}
            />
          )}
        </ModalBody>
        <ModalFooter>
          <Button
            variant="flat"
            startContent={<X size={16} />}
            onPress={onClose}
          >
            Close
          </Button>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
}

export default TemplatePreview;
