import { useState } from 'react';
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
import { AlertCircle } from 'lucide-react';

interface LegalDocVersionFormProps {
  documentId: number;
  onSuccess: () => void;
  onCancel: () => void;
}

export default function LegalDocVersionForm({
  documentId,
  onSuccess,
  onCancel,
}: LegalDocVersionFormProps) {
  const { success, error } = useToast();

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

  const validate = () => {
    const newErrors: Record<string, string> = {};

    if (!formData.version_number.trim()) {
      newErrors.version_number = 'Version number is required';
    }

    if (!formData.content.trim()) {
      newErrors.content = 'Content is required';
    }

    if (!formData.effective_date) {
      newErrors.effective_date = 'Effective date is required';
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
      const response = await adminLegalDocs.createVersion(documentId, {
        version_number: formData.version_number.trim(),
        version_label: formData.version_label.trim() || undefined,
        content: formData.content,
        summary_of_changes: formData.summary_of_changes.trim() || undefined,
        effective_date: formData.effective_date,
        is_draft: formData.is_draft,
      });

      if (response.success) {
        success('Version created successfully');
        onSuccess();
      } else {
        error(response.error || 'Failed to create version');
      }
    } catch (err) {
      error('Failed to create version');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <form onSubmit={handleSubmit}>
      <ModalHeader>Create New Version</ModalHeader>
      <ModalBody>
        <div className="space-y-4">
          {/* Info banner */}
          <div className="flex items-start gap-3 p-3 bg-primary-50 dark:bg-primary-900/20 rounded-lg">
            <AlertCircle size={20} className="text-primary flex-shrink-0 mt-0.5" />
            <div className="text-sm">
              <p className="font-medium mb-1">Version Management</p>
              <p className="text-[var(--color-text-secondary)]">
                Create a draft version first. You can publish it later when ready. Publishing will make it
                the current version and trigger user re-acceptance if needed.
              </p>
            </div>
          </div>

          {/* Version Number */}
          <Input
            label="Version Number"
            placeholder="e.g., 2.0, 2024.1"
            value={formData.version_number}
            onChange={(e) => setFormData({ ...formData, version_number: e.target.value })}
            isInvalid={!!errors.version_number}
            errorMessage={errors.version_number}
            isRequired
          />

          {/* Version Label */}
          <Input
            label="Version Label (Optional)"
            placeholder="e.g., GDPR Update, Major Revision"
            value={formData.version_label}
            onChange={(e) => setFormData({ ...formData, version_label: e.target.value })}
          />

          {/* Effective Date */}
          <Input
            type="date"
            label="Effective Date"
            value={formData.effective_date}
            onChange={(e) => setFormData({ ...formData, effective_date: e.target.value })}
            isInvalid={!!errors.effective_date}
            errorMessage={errors.effective_date}
            isRequired
          />

          {/* Summary of Changes */}
          <Textarea
            label="Summary of Changes"
            placeholder="Describe what changed in this version"
            value={formData.summary_of_changes}
            onChange={(e) => setFormData({ ...formData, summary_of_changes: e.target.value })}
            minRows={3}
          />

          {/* Content */}
          <Textarea
            label="Content (HTML)"
            placeholder="<h2>Section Title</h2><p>Content...</p>"
            value={formData.content}
            onChange={(e) => setFormData({ ...formData, content: e.target.value })}
            minRows={10}
            isInvalid={!!errors.content}
            errorMessage={errors.content}
            description="HTML content for the legal document. Use <h2> for sections."
            isRequired
          />

          {/* Draft Toggle */}
          <div className="flex items-center justify-between p-3 border rounded-lg">
            <div>
              <p className="font-medium">Save as Draft</p>
              <p className="text-sm text-[var(--color-text-secondary)]">
                Drafts are not visible to users and can be edited
              </p>
            </div>
            <Switch
              isSelected={formData.is_draft}
              onValueChange={(checked) => setFormData({ ...formData, is_draft: checked })}
            />
          </div>
        </div>
      </ModalBody>
      <ModalFooter>
        <Button variant="flat" onPress={onCancel}>
          Cancel
        </Button>
        <Button color="primary" type="submit" isLoading={submitting}>
          Create Version
        </Button>
      </ModalFooter>
    </form>
  );
}
