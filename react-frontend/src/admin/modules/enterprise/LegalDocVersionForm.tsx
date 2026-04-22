// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect } from 'react';
import {
  ModalHeader,
  ModalBody,
  ModalFooter,
  Button,
  Input,
  Textarea,
  Switch,
} from '@heroui/react';
import { useToast } from '@/contexts/ToastContext';
import { adminLegalDocs } from '@/admin/api/adminApi';
import type { LegalDocumentVersion } from '@/admin/api/types';
import { AlertCircle } from 'lucide-react';
import { LegalDocEditor } from '@/admin/components';

interface LegalDocVersionFormProps {
  documentId: number;
  /** When provided, the form operates in edit mode for this draft version. */
  editVersion?: LegalDocumentVersion;
  onSuccess: () => void;
  onCancel: () => void;
}

export default function LegalDocVersionForm({
  documentId,
  editVersion,
  onSuccess,
  onCancel,
}: LegalDocVersionFormProps) {
  const { success, error } = useToast();
  const isEditMode = !!editVersion;

  const [formData, setFormData] = useState({
    version_number: '',
    version_label: '',
    content: '',
    summary_of_changes: '',
    effective_date: '',
    is_draft: true,
  });

  const [submitting, setSubmitting] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});

  // Populate form when editing an existing draft
  useEffect(() => {
    if (editVersion) {
      setFormData({
        version_number: editVersion.version_number ?? '',
        version_label: editVersion.version_label ?? '',
        content: editVersion.content ?? '',
        summary_of_changes: editVersion.summary_of_changes ?? '',
        effective_date: editVersion.effective_date ?? '',
        is_draft: editVersion.is_draft,
      });
    }
  }, [editVersion]);

  const validate = () => {
    const newErrors: Record<string, string> = {};

    if (!formData.version_number.trim()) {
      newErrors.version_number = "Version number is required";
    }

    if (!formData.content.trim()) {
      newErrors.content = "Content Required";
    }

    if (!formData.effective_date) {
      newErrors.effective_date = "Effective date is required";
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!validate()) {
      return;
    }

    try {
      setSubmitting(true);

      const payload = {
        version_number: formData.version_number.trim(),
        version_label: formData.version_label.trim() || undefined,
        content: formData.content,
        summary_of_changes: formData.summary_of_changes.trim() || undefined,
        effective_date: formData.effective_date,
      };

      let response;
      if (isEditMode) {
        response = await adminLegalDocs.updateVersion(documentId, editVersion.id, payload);
      } else {
        response = await adminLegalDocs.createVersion(documentId, {
          ...payload,
          is_draft: formData.is_draft,
        });
      }

      if (response.success) {
        success(isEditMode ? "Version Updated" : "Version Created");
        onSuccess();
      } else {
        error(response.error || (isEditMode ? "Failed to update" : "Failed to create"));
      }
    } catch {
      error(isEditMode ? "Failed to update" : "Failed to create");
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <form onSubmit={handleSubmit}>
      <ModalHeader>{isEditMode ? "Title Edit" : "Title Create"}</ModalHeader>
      <ModalBody>
        <div className="space-y-4">
          {/* Info banner */}
          <div className="flex items-start gap-3 p-3 bg-primary-50 dark:bg-primary-900/20 rounded-lg">
            <AlertCircle size={20} className="text-primary shrink-0 mt-0.5" />
            <div className="text-sm">
              <p className="font-medium mb-1">{"Version Management"}</p>
              <p className="text-[var(--color-text-secondary)]">
                {isEditMode
                  ? "Edit Info"
                  : "Create Info"}
              </p>
            </div>
          </div>

          {/* Version Number */}
          <Input
            label={"Version Number"}
            placeholder={"Version Number..."}
            value={formData.version_number}
            onChange={(e) => setFormData({ ...formData, version_number: e.target.value })}
            isInvalid={!!errors.version_number}
            errorMessage={errors.version_number}
            isRequired
          />

          {/* Version Label */}
          <Input
            label={"Version Label"}
            placeholder={"Version Label..."}
            value={formData.version_label}
            onChange={(e) => setFormData({ ...formData, version_label: e.target.value })}
          />

          {/* Effective Date */}
          <Input
            type="date"
            label={"Effective Date"}
            value={formData.effective_date}
            onChange={(e) => setFormData({ ...formData, effective_date: e.target.value })}
            isInvalid={!!errors.effective_date}
            errorMessage={errors.effective_date}
            isRequired
          />

          {/* Summary of Changes */}
          <Textarea
            label={"Summary of Changes"}
            placeholder={"Summary..."}
            value={formData.summary_of_changes}
            onChange={(e) => setFormData({ ...formData, summary_of_changes: e.target.value })}
            minRows={3}
          />

          {/* Content */}
          <LegalDocEditor
            value={formData.content}
            onChange={(html) => setFormData((prev) => ({ ...prev, content: html }))}
            disabled={submitting}
            errorMessage={errors.content}
          />

          {/* Draft Toggle -- only for new versions, editing is always a draft */}
          {!isEditMode && (
            <div className="flex items-center justify-between p-3 border rounded-lg">
              <div>
                <p className="font-medium">{"Save as Draft"}</p>
                <p className="text-sm text-[var(--color-text-secondary)]">
                  {"Draft"}
                </p>
              </div>
              <Switch
                isSelected={formData.is_draft}
                onValueChange={(checked) => setFormData({ ...formData, is_draft: checked })}
              />
            </div>
          )}
        </div>
      </ModalBody>
      <ModalFooter>
        <Button variant="flat" onPress={onCancel}>
          {"Cancel"}
        </Button>
        <Button color="primary" type="submit" isLoading={submitting} isDisabled={submitting}>
          {isEditMode ? "Update" : "Create"}
        </Button>
      </ModalFooter>
    </form>
  );
}
