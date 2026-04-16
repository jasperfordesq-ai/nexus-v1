// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

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
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminLegalDocs } from '../../api/adminApi';
import { PageHeader } from '../../components';


export function LegalDocForm() {
  const { t } = useTranslation('admin');
  const { id } = useParams();
  const isEdit = !!id;

  const DOC_TYPES = [
    { value: 'terms', label: t('legal_doc_form.doc_type_terms') },
    { value: 'privacy', label: t('legal_doc_form.doc_type_privacy') },
    { value: 'cookies', label: t('legal_doc_form.doc_type_cookies') },
    { value: 'accessibility', label: t('legal_doc_form.doc_type_accessibility') },
    { value: 'community_guidelines', label: t('legal_doc_form.doc_type_community_guidelines') },
    { value: 'acceptable_use', label: t('legal_doc_form.doc_type_acceptable_use') },
  ];

  const STATUS_OPTIONS = [
    { value: 'draft', label: t('legal_doc_form.status_draft') },
    { value: 'published', label: t('legal_doc_form.status_published') },
    { value: 'archived', label: t('legal_doc_form.status_archived') },
  ];

  usePageTitle(isEdit ? t('legal_doc_form.page_title_edit') : t('legal_doc_form.page_title_create'));
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
      toast.error(t('enterprise.failed_to_load_document'));
    } finally {
      setLoading(false);
    }
  }, [id, isEdit, toast, t])

  useEffect(() => {
    loadData();
  }, [loadData]);

  const handleSubmit = async () => {
    if (!title.trim()) {
      toast.error(t('enterprise.title_is_required'));
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

      let res;
      if (isEdit && id) {
        res = await adminLegalDocs.update(parseInt(id), payload);
      } else {
        res = await adminLegalDocs.create(payload);
      }

      if (res.success) {
        toast.success(isEdit ? t('enterprise.document_updated') : t('enterprise.document_created'));
        navigate(tenantPath('/admin/legal-documents'));
      } else {
        const error = (res as { error?: string }).error || t('legal_doc_form.save_failed_generic');
        toast.error(error);
      }
    } catch (err) {
      toast.error(t('enterprise.failed_to_save_document'));
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
        title={isEdit ? t('legal_doc_form.edit_title') : t('legal_doc_form.create_title')}
        description={isEdit ? t('legal_doc_form.edit_description') : t('legal_doc_form.create_description')}
        actions={
          <Button
            variant="flat"
            startContent={<ArrowLeft size={16} />}
            onPress={() => navigate(tenantPath('/admin/legal-documents'))}
            size="sm"
          >
            {t('legal_doc_form.back_to_documents')}
          </Button>
        }
      />

      <div className="space-y-6">
        {/* Metadata */}
        <Card shadow="sm">
          <CardBody className="p-4 space-y-4">
            <h3 className="text-lg font-semibold">{t('shared.document_details')}</h3>
            <Input
              label={t('enterprise.label_title')}
              value={title}
              onValueChange={setTitle}
              variant="bordered"
              isRequired
              placeholder={t('enterprise.placeholder_title')}
            />
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
              <Select
                label={t('enterprise.label_type')}
                selectedKeys={new Set([type])}
                onSelectionChange={(keys) => {
                  const selected = Array.from(keys)[0] as string;
                  if (selected) setType(selected);
                }}
                variant="bordered"
              >
                {DOC_TYPES.map((dt) => (
                  <SelectItem key={dt.value}>{dt.label}</SelectItem>
                ))}
              </Select>
              <Input
                label={t('enterprise.label_version')}
                value={version}
                onValueChange={setVersion}
                variant="bordered"
                placeholder="1.0"
              />
              <Select
                label={t('enterprise.label_status')}
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
            <h3 className="text-lg font-semibold mb-3">{t('shared.content')}</h3>
            <Textarea
              label={t('enterprise.label_document_content')}
              value={content}
              onValueChange={setContent}
              variant="bordered"
              minRows={12}
              placeholder={t('enterprise.placeholder_document_content')}
            />
          </CardBody>
        </Card>

        {/* Actions */}
        <div className="flex justify-end gap-3">
          <Button
            variant="flat"
            onPress={() => navigate(tenantPath('/admin/legal-documents'))}
          >
            {t('legal_doc_form.cancel')}
          </Button>
          <Button
            color="primary"
            startContent={<Save size={16} />}
            onPress={handleSubmit}
            isLoading={saving}
          >
            {isEdit ? t('legal_doc_form.update_document') : t('legal_doc_form.create_document')}
          </Button>
        </div>
      </div>
    </div>
  );
}

export default LegalDocForm;
