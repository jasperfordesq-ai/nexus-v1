/**
 * Redirects
 * Manage URL redirects for the platform (301/302).
 */

import { useState, useEffect, useCallback } from 'react';
import {
  Button,
  Spinner,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Input,
  Select,
  SelectItem,
  useDisclosure,
} from '@heroui/react';
import { ArrowRightLeft, Plus, Trash2 } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { PageHeader, EmptyState, DataTable, ConfirmModal, type Column } from '../../components';
import { adminTools } from '../../api/adminApi';

interface Redirect {
  id: number;
  from_url: string;
  to_url: string;
  status_code: number;
  hits: number;
  created_at: string;
}

export function Redirects() {
  usePageTitle('Admin - Redirects');
  const toast = useToast();

  const [redirects, setRedirects] = useState<Redirect[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  // Add modal
  const { isOpen: isAddOpen, onOpen: onAddOpen, onClose: onAddClose } = useDisclosure();
  const [fromUrl, setFromUrl] = useState('');
  const [toUrl, setToUrl] = useState('');
  const [statusCode, setStatusCode] = useState<string>('301');

  // Delete confirm
  const [deleteTarget, setDeleteTarget] = useState<Redirect | null>(null);
  const [deleting, setDeleting] = useState(false);

  const fetchRedirects = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminTools.getRedirects();
      setRedirects(res.data ?? []);
    } catch {
      toast.error('Failed to load redirects');
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    fetchRedirects();
  }, [fetchRedirects]);

  const handleAdd = async () => {
    if (!fromUrl.trim() || !toUrl.trim()) {
      toast.warning('Both From URL and To URL are required');
      return;
    }
    setSaving(true);
    try {
      await adminTools.createRedirect({
        from_url: fromUrl.trim(),
        to_url: toUrl.trim(),
        status_code: Number(statusCode),
      });
      toast.success('Redirect created');
      setFromUrl('');
      setToUrl('');
      setStatusCode('301');
      onAddClose();
      await fetchRedirects();
    } catch {
      toast.error('Failed to create redirect');
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async () => {
    if (!deleteTarget) return;
    setDeleting(true);
    try {
      await adminTools.deleteRedirect(deleteTarget.id);
      toast.success('Redirect deleted');
      setDeleteTarget(null);
      await fetchRedirects();
    } catch {
      toast.error('Failed to delete redirect');
    } finally {
      setDeleting(false);
    }
  };

  const columns: Column<Redirect>[] = [
    { key: 'from_url', label: 'From URL', sortable: true },
    { key: 'to_url', label: 'To URL', sortable: true },
    {
      key: 'status_code',
      label: 'Status Code',
      sortable: true,
      render: (item) => (
        <span className="font-mono text-sm">{item.status_code}</span>
      ),
    },
    { key: 'hits', label: 'Hits', sortable: true },
    {
      key: 'created_at',
      label: 'Created',
      sortable: true,
      render: (item) => new Date(item.created_at).toLocaleDateString(),
    },
    {
      key: 'actions',
      label: '',
      render: (item) => (
        <Button
          isIconOnly
          variant="light"
          color="danger"
          size="sm"
          onPress={() => setDeleteTarget(item)}
          aria-label="Delete redirect"
        >
          <Trash2 size={16} />
        </Button>
      ),
    },
  ];

  if (loading) {
    return (
      <div>
        <PageHeader title="Redirects" description="Manage URL redirects (301/302)" />
        <div className="flex justify-center py-16">
          <Spinner size="lg" />
        </div>
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title="Redirects"
        description="Manage URL redirects (301/302)"
        actions={
          <Button color="primary" startContent={<Plus size={16} />} onPress={onAddOpen}>
            Add Redirect
          </Button>
        }
      />

      {redirects.length === 0 ? (
        <EmptyState
          icon={ArrowRightLeft}
          title="No Redirects Configured"
          description="Add URL redirects to handle moved or renamed pages. Supports 301 (permanent) and 302 (temporary) redirects."
          actionLabel="Add Redirect"
          onAction={onAddOpen}
        />
      ) : (
        <DataTable
          columns={columns}
          data={redirects}
          searchable={false}
          onRefresh={fetchRedirects}
        />
      )}

      {/* Add Redirect Modal */}
      <Modal isOpen={isAddOpen} onClose={onAddClose} size="lg">
        <ModalContent>
          <ModalHeader>Add Redirect</ModalHeader>
          <ModalBody className="gap-4">
            <Input
              label="From URL"
              placeholder="/old-page"
              variant="bordered"
              value={fromUrl}
              onValueChange={setFromUrl}
              description="The URL path to redirect from"
            />
            <Input
              label="To URL"
              placeholder="/new-page"
              variant="bordered"
              value={toUrl}
              onValueChange={setToUrl}
              description="The destination URL path"
            />
            <Select
              label="Status Code"
              variant="bordered"
              selectedKeys={[statusCode]}
              onSelectionChange={(keys) => {
                const selected = Array.from(keys)[0];
                if (selected) setStatusCode(String(selected));
              }}
            >
              <SelectItem key="301">301 - Permanent Redirect</SelectItem>
              <SelectItem key="302">302 - Temporary Redirect</SelectItem>
            </Select>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onAddClose} isDisabled={saving}>
              Cancel
            </Button>
            <Button color="primary" onPress={handleAdd} isLoading={saving}>
              Create Redirect
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Delete Confirmation */}
      <ConfirmModal
        isOpen={!!deleteTarget}
        onClose={() => setDeleteTarget(null)}
        onConfirm={handleDelete}
        title="Delete Redirect"
        message={`Are you sure you want to delete the redirect from "${deleteTarget?.from_url}"? This action cannot be undone.`}
        confirmLabel="Delete"
        confirmColor="danger"
        isLoading={deleting}
      />
    </div>
  );
}

export default Redirects;
