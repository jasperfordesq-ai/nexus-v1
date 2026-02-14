/**
 * Legal Document Form
 * Create/Edit legal document form.
 */

import { useEffect, useState, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
  Card,
  CardBody,
  Input,
  Textarea,
  Button,
  Select,
  SelectItem,
  Spinner,
} from '@heroui/react';
import { Save, ArrowLeft } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminLegalDocs } from '../../api/adminApi';
import { PageHeader } from '../../components';

const DOC_TYPES = [
  { value: 'terms', label: 'Terms of Service' },
  { value: 'privacy', label: 'Privacy Policy' },
  { value: 'cookies', label: 'Cookie Policy' },
  { value: 'acceptable_use', label: 'Acceptable Use Policy' },
  { value: 'data_processing', label: 'Data Processing Agreement' },
  { value: 'other', label: 'Other' },
];

const STATUS_OPTIONS = [
  { value: 'draft', label: 'Draft' },
  { value: 'published', label: 'Published' },
  { value: 'archived', label: 'Archived' },
];

export function LegalDocForm() {
  const { id } = useParams();
  const isEdit = !!id;
  usePageTitle(`Admin - ${isEdit ? 'Edit' : 'Create'} Legal Document`);
  const { tenantPath } = useTenant();
  const toast = useToast();
  const navigate = useNavigate();

  const [title, setTitle] = useState('');
  const [content, setContent] = useState('');
  const [type, setType] = useState('terms');
  const [version, setVersion] = useState('1.0');
  const [status, setStatus] = useState('draft');
  const [loading, setLoading] = useState(isEdit);
  const [saving, setSaving] = useState(false);

  const loadData = useCallback(async () => {
    if (!isEdit || !id) return;
    setLoading(true);
    try {
      const res = await adminLegalDocs.get(parseInt(id));
      if (res.success && res.data) {
        const doc = res.data as unknown as {
          title: string;
          content: string;
          type: string;
          version: string;
          status: string;
        };
        setTitle(doc.title || '');
        setContent(doc.content || '');
        setType(doc.type || 'terms');
        setVersion(doc.version || '1.0');
        setStatus(doc.status || 'draft');
      }
    } catch {
      toast.error('Failed to load document');
    } finally {
      setLoading(false);
    }
  }, [id, isEdit, toast]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const handleSubmit = async () => {
    if (!title.trim()) {
      toast.error('Title is required');
      return;
    }

    setSaving(true);
    try {
      const payload = {
        title: title.trim(),
        content,
        type,
        version,
        status,
      };

      if (isEdit && id) {
        await adminLegalDocs.update(parseInt(id), payload);
        toast.success('Document updated');
      } else {
        await adminLegalDocs.create(payload);
        toast.success('Document created');
      }
      navigate(tenantPath('/admin/legal-documents'));
    } catch {
      toast.error(`Failed to ${isEdit ? 'update' : 'create'} document`);
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div className="flex justify-center py-16">
        <Spinner size="lg" />
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title={isEdit ? 'Edit Legal Document' : 'Create Legal Document'}
        description={isEdit ? 'Update document content and settings' : 'Create a new legal document'}
        actions={
          <Button
            variant="flat"
            startContent={<ArrowLeft size={16} />}
            onPress={() => navigate(tenantPath('/admin/legal-documents'))}
            size="sm"
          >
            Back to Documents
          </Button>
        }
      />

      <div className="space-y-6">
        {/* Metadata */}
        <Card shadow="sm">
          <CardBody className="p-4 space-y-4">
            <h3 className="text-lg font-semibold">Document Details</h3>
            <Input
              label="Title"
              value={title}
              onValueChange={setTitle}
              variant="bordered"
              isRequired
              placeholder="e.g. Terms of Service"
            />
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
              <Select
                label="Type"
                selectedKeys={new Set([type])}
                onSelectionChange={(keys) => {
                  const selected = Array.from(keys)[0] as string;
                  if (selected) setType(selected);
                }}
                variant="bordered"
              >
                {DOC_TYPES.map((t) => (
                  <SelectItem key={t.value}>{t.label}</SelectItem>
                ))}
              </Select>
              <Input
                label="Version"
                value={version}
                onValueChange={setVersion}
                variant="bordered"
                placeholder="1.0"
              />
              <Select
                label="Status"
                selectedKeys={new Set([status])}
                onSelectionChange={(keys) => {
                  const selected = Array.from(keys)[0] as string;
                  if (selected) setStatus(selected);
                }}
                variant="bordered"
              >
                {STATUS_OPTIONS.map((s) => (
                  <SelectItem key={s.value}>{s.label}</SelectItem>
                ))}
              </Select>
            </div>
          </CardBody>
        </Card>

        {/* Content */}
        <Card shadow="sm">
          <CardBody className="p-4">
            <h3 className="text-lg font-semibold mb-3">Content</h3>
            <Textarea
              label="Document Content"
              value={content}
              onValueChange={setContent}
              variant="bordered"
              minRows={12}
              placeholder="Enter the legal document content here..."
            />
          </CardBody>
        </Card>

        {/* Actions */}
        <div className="flex justify-end gap-3">
          <Button
            variant="flat"
            onPress={() => navigate(tenantPath('/admin/legal-documents'))}
          >
            Cancel
          </Button>
          <Button
            color="primary"
            startContent={<Save size={16} />}
            onPress={handleSubmit}
            isLoading={saving}
          >
            {isEdit ? 'Update Document' : 'Create Document'}
          </Button>
        </div>
      </div>
    </div>
  );
}

export default LegalDocForm;
