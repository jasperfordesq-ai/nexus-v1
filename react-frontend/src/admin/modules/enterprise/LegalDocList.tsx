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
import Plus from 'lucide-react/icons/plus';
import Pencil from 'lucide-react/icons/pencil';
import Trash2 from 'lucide-react/icons/trash-2';
import FileText from 'lucide-react/icons/file-text';
import GitBranch from 'lucide-react/icons/git-branch';
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
  usePageTitle("Enterprise");
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
      toast.error("Failed to load legal documents");
    } finally {
      setLoading(false);
    }
  }, [toast])


  useEffect(() => {
    loadData();
  }, [loadData]);

  const handleDelete = async () => {
    if (!deleteTarget) return;
    setDeleting(true);
    try {
      const res = await adminLegalDocs.delete(deleteTarget.id);

      if (res.success) {
        toast.success("Document Deleted");
        setDeleteTarget(null);
        loadData();
      } else {
        const error = (res as { error?: string }).error || "Failed to delete document";
        toast.error(error);
      }
    } catch (err) {
      toast.error("Failed to delete document");
    } finally {
      setDeleting(false);
    }
  };

  const columns: Column<LegalDocument>[] = [
    {
      key: 'title',
      label: "Title",
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
      label: "Type",
      sortable: true,
      render: (doc) => (
        <Chip size="sm" variant="flat" color="primary">
          {DOC_TYPE_KEYS[doc.type] ? t(DOC_TYPE_KEYS[doc.type] ?? '') : doc.type}
        </Chip>
      ),
    },
    { key: 'version', label: "Version", render: (doc) => doc.version || '1.0' },
    {
      key: 'status',
      label: "Status",
      sortable: true,
      render: (doc) => <StatusBadge status={doc.status} />,
    },
    {
      key: 'updated_at',
      label: "Last Updated",
      sortable: true,
      render: (doc) => doc.updated_at ? new Date(doc.updated_at).toLocaleDateString() : '---',
    },
    {
      key: 'actions',
      label: "Actions",
      render: (doc) => (
        <div className="flex items-center gap-1">
          <Button
            isIconOnly
            size="sm"
            variant="light"
            onPress={() => navigate(tenantPath(`/admin/legal-documents/${doc.id}/versions`))}
            aria-label={"Manage Versions"}
          >
            <GitBranch size={14} />
          </Button>
          <Button
            isIconOnly
            size="sm"
            variant="light"
            onPress={() => navigate(tenantPath(`/admin/legal-documents/${doc.id}/edit`))}
            aria-label={"Edit Document"}
          >
            <Pencil size={14} />
          </Button>
          <Button
            isIconOnly
            size="sm"
            variant="light"
            color="danger"
            onPress={() => setDeleteTarget(doc)}
            aria-label={"Delete Document"}
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
        title={"Legal Doc List"}
        description={"Manage legal documents including Terms, Privacy Policy, and Cookie Policy"}
        actions={
          <Button
            as={Link}
            to={tenantPath('/admin/legal-documents/create')}
            color="primary"
            startContent={<Plus size={16} />}
            size="sm"
          >
            {"Create Document"}
          </Button>
        }
      />

      <DataTable
        columns={columns}
        data={docs}
        isLoading={loading}
        onRefresh={loadData}
        searchable={false}
        emptyContent={"No legal documents"}
      />

      <ConfirmModal
        isOpen={!!deleteTarget}
        onClose={() => setDeleteTarget(null)}
        onConfirm={handleDelete}
        title={"Delete Document"}
        message={`Are you sure you want to delete this document? This cannot be undone.`}
        confirmLabel={"Delete"}
        confirmColor="danger"
        isLoading={deleting}
      />
    </div>
  );
}

export default LegalDocList;
