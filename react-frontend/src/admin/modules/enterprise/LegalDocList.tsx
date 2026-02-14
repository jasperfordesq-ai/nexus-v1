/**
 * Legal Document List
 * DataTable of legal documents with CRUD.
 */

import { useEffect, useState, useCallback } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Button, Chip } from '@heroui/react';
import { Plus, Pencil, Trash2, FileText } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminLegalDocs } from '../../api/adminApi';
import { PageHeader, DataTable, ConfirmModal, StatusBadge } from '../../components';
import type { Column } from '../../components';
import type { LegalDocument } from '../../api/types';

export function LegalDocList() {
  usePageTitle('Admin - Legal Documents');
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
      toast.error('Failed to load legal documents');
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const handleDelete = async () => {
    if (!deleteTarget) return;
    setDeleting(true);
    try {
      await adminLegalDocs.delete(deleteTarget.id);
      toast.success('Document deleted');
      setDeleteTarget(null);
      loadData();
    } catch {
      toast.error('Failed to delete document');
    } finally {
      setDeleting(false);
    }
  };

  const columns: Column<LegalDocument>[] = [
    {
      key: 'title',
      label: 'Title',
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
      label: 'Type',
      sortable: true,
      render: (doc) => (
        <Chip size="sm" variant="flat" color="primary" className="capitalize">
          {doc.type}
        </Chip>
      ),
    },
    { key: 'version', label: 'Version', render: (doc) => doc.version || '1.0' },
    {
      key: 'status',
      label: 'Status',
      sortable: true,
      render: (doc) => <StatusBadge status={doc.status} />,
    },
    {
      key: 'updated_at',
      label: 'Last Updated',
      sortable: true,
      render: (doc) => doc.updated_at ? new Date(doc.updated_at).toLocaleDateString() : '---',
    },
    {
      key: 'actions',
      label: 'Actions',
      render: (doc) => (
        <div className="flex items-center gap-1">
          <Button
            isIconOnly
            size="sm"
            variant="light"
            onPress={() => navigate(tenantPath(`/admin/legal-documents/${doc.id}/edit`))}
            aria-label="Edit document"
          >
            <Pencil size={14} />
          </Button>
          <Button
            isIconOnly
            size="sm"
            variant="light"
            color="danger"
            onPress={() => setDeleteTarget(doc)}
            aria-label="Delete document"
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
        title="Legal Documents"
        description="Document versioning and compliance management"
        actions={
          <Button
            as={Link}
            to={tenantPath('/admin/legal-documents/create')}
            color="primary"
            startContent={<Plus size={16} />}
            size="sm"
          >
            Create Document
          </Button>
        }
      />

      <DataTable
        columns={columns}
        data={docs}
        isLoading={loading}
        onRefresh={loadData}
        searchable={false}
        emptyContent="No legal documents found. Create your first document."
      />

      <ConfirmModal
        isOpen={!!deleteTarget}
        onClose={() => setDeleteTarget(null)}
        onConfirm={handleDelete}
        title="Delete Document"
        message={`Are you sure you want to delete "${deleteTarget?.title}"? This action cannot be undone.`}
        confirmLabel="Delete"
        confirmColor="danger"
        isLoading={deleting}
      />
    </div>
  );
}

export default LegalDocList;
