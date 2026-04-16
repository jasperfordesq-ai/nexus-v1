// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Legal Document List
 * DataTable of legal documents with CRUD.
 */

import { useEffect, useState, useCallback } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Button, Chip } from '@heroui/react';
import { Plus, Pencil, Trash2, FileText, GitBranch } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminLegalDocs } from '../../api/adminApi';
import { PageHeader, DataTable, ConfirmModal, StatusBadge } from '../../components';
import type { Column } from '../../components';
import type { LegalDocument } from '../../api/types';

import { useTranslation } from 'react-i18next';
/** Human-friendly labels for legal document types */
const DOC_TYPE_KEYS: Record<string, string> = {
  terms: 'enterprise.legal_doc_form.type_terms',
  privacy: 'enterprise.legal_doc_form.type_privacy',
  cookies: 'enterprise.legal_doc_form.type_cookies',
  accessibility: 'enterprise.legal_doc_form.type_accessibility',
  community_guidelines: 'enterprise.legal_doc_form.type_community_guidelines',
  acceptable_use: 'enterprise.legal_doc_form.type_acceptable_use',
};

export function LegalDocList() {
  const { t } = useTranslation('admin');
  usePageTitle(t('enterprise.page_title'));
  const { tenantPath } = useTenant();
  const toast = useToast();
  const navigate = useNavigate();

  const [docs, setDocs] = useState<LegalDocument[]>([]);
  const [loading, setLoading] = useState(true);
  const [deleteTarget, setDeleteTarget] = useState<LegalDocument | null>(null);
  const [deleting, setDeleting] = useState(false);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminLegalDocs.list();
      if (res.success && res.data) {
        const data = res.data as unknown;
        setDocs(Array.isArray(data) ? data : []);
      }
    } catch {
      toast.error(t('enterprise.failed_to_load_legal_documents'));
    } finally {
      setLoading(false);
    }
  }, [toast, t])

  useEffect(() => {
    loadData();
  }, [loadData]);

  const handleDelete = async () => {
    if (!deleteTarget) return;
    setDeleting(true);
    try {
      const res = await adminLegalDocs.delete(deleteTarget.id);

      if (res.success) {
        toast.success(t('enterprise.document_deleted'));
        setDeleteTarget(null);
        loadData();
      } else {
        const error = (res as { error?: string }).error || t('enterprise.failed_to_delete_document');
        toast.error(error);
      }
    } catch (err) {
      toast.error(t('enterprise.failed_to_delete_document'));
    } finally {
      setDeleting(false);
    }
  };

  const columns: Column<LegalDocument>[] = [
    {
      key: 'title',
      label: t('enterprise.label_title'),
      sortable: true,
      render: (doc) => (
        <div className="flex items-center gap-2">
          <FileText size={16} className="text-primary" />
          <span className="font-medium">{doc.title}</span>
        </div>
      ),
    },
    {
      key: 'type',
      label: t('enterprise.col_type'),
      sortable: true,
      render: (doc) => (
        <Chip size="sm" variant="flat" color="primary">
          {DOC_TYPE_KEYS[doc.type] ? t(DOC_TYPE_KEYS[doc.type] ?? '') : doc.type}
        </Chip>
      ),
    },
    { key: 'version', label: t('enterprise.col_version'), render: (doc) => doc.version || '1.0' },
    {
      key: 'status',
      label: t('enterprise.col_status'),
      sortable: true,
      render: (doc) => <StatusBadge status={doc.status} />,
    },
    {
      key: 'updated_at',
      label: t('enterprise.col_last_updated'),
      sortable: true,
      render: (doc) => doc.updated_at ? new Date(doc.updated_at).toLocaleDateString() : '---',
    },
    {
      key: 'actions',
      label: t('enterprise.col_actions'),
      render: (doc) => (
        <div className="flex items-center gap-1">
          <Button
            isIconOnly
            size="sm"
            variant="light"
            onPress={() => navigate(tenantPath(`/admin/legal-documents/${doc.id}/versions`))}
            aria-label={t('enterprise.label_manage_versions')}
          >
            <GitBranch size={14} />
          </Button>
          <Button
            isIconOnly
            size="sm"
            variant="light"
            onPress={() => navigate(tenantPath(`/admin/legal-documents/${doc.id}/edit`))}
            aria-label={t('enterprise.label_edit_document')}
          >
            <Pencil size={14} />
          </Button>
          <Button
            isIconOnly
            size="sm"
            variant="light"
            color="danger"
            onPress={() => setDeleteTarget(doc)}
            aria-label={t('enterprise.label_delete_document')}
          >
            <Trash2 size={14} />
          </Button>
        </div>
      ),
    },
  ];

  return (
    <div>
      <PageHeader
        title={t('enterprise.legal_doc_list_title')}
        description={t('enterprise.legal_doc_list_desc')}
        actions={
          <Button
            as={Link}
            to={tenantPath('/admin/legal-documents/create')}
            color="primary"
            startContent={<Plus size={16} />}
            size="sm"
          >
            {t('enterprise.create_document')}
          </Button>
        }
      />

      <DataTable
        columns={columns}
        data={docs}
        isLoading={loading}
        onRefresh={loadData}
        searchable={false}
        emptyContent={t('enterprise.no_legal_documents')}
      />

      <ConfirmModal
        isOpen={!!deleteTarget}
        onClose={() => setDeleteTarget(null)}
        onConfirm={handleDelete}
        title={t('enterprise.delete_document_title')}
        message={t('enterprise.delete_document_confirm', { title: deleteTarget?.title })}
        confirmLabel={t('common.delete')}
        confirmColor="danger"
        isLoading={deleting}
      />
    </div>
  );
}

export default LegalDocList;
