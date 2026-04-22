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
  const [loadError, setLoadError] = useState(false);

  const loadPreview = useCallback(async () => {
    setLoading(true);
    setLoadError(false);
    try {
      const res = await adminNewsletters.previewTemplate(templateId);
      if (res.success && res.data) {
        const data = res.data as { html?: string; name?: string; subject?: string };
        setHtml(data.html || '');
        setName(data.name || "Template Preview");
        setSubject(data.subject || '');
      }
    } catch {
      setLoadError(true);
    } finally {
      setLoading(false);
    }
  }, [templateId, t]);

  useEffect(() => {
    if (isOpen) {
      loadPreview();
    }
  }, [isOpen, loadPreview]);

  const renderBody = () => {
    if (loading) {
      return (
        <div className="flex items-center justify-center py-20">
          <Spinner size="lg" label={"Loading preview..."} />
        </div>
      );
    }
    if (loadError) {
      return (
        <div className="flex items-center justify-center py-20 text-danger">
          {"Load failed"}
        </div>
      );
    }
    if (!html) {
      return (
        <div className="flex items-center justify-center py-20 text-default-400">
          {"No content found"}
        </div>
      );
    }
    return (
      <iframe
        title={"Template Preview"}
        sandbox="allow-same-origin"
        srcDoc={html}
        className="w-full border-0"
        style={{ minHeight: '500px', height: '70vh' }}
      />
    );
  };

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
              {"Subject"} {subject}
            </span>
          )}
        </ModalHeader>
        <ModalBody className="p-0">
          {renderBody()}
        </ModalBody>
        <ModalFooter>
          <Button
            variant="flat"
            startContent={<X size={16} />}
            onPress={onClose}
          >
            {"Close"}
          </Button>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
}

export default TemplatePreview;
