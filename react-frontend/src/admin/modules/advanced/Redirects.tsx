// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

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
  useDisclosure,
} from '@heroui/react';
import ArrowRightLeft from 'lucide-react/icons/arrow-right-left';
import Plus from 'lucide-react/icons/plus';
import Trash2 from 'lucide-react/icons/trash-2';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { PageHeader, EmptyState, DataTable, ConfirmModal, type Column } from '../../components';
import { adminTools } from '../../api/adminApi';

interface Redirect {
  id: number;
  source_url: string;
  destination_url: string;
  hits: number;
  created_at: string;
}

export function Redirects() {
  usePageTitle("Advanced");
  const toast = useToast();

  const [redirects, setRedirects] = useState<Redirect[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  // Add modal
  const { isOpen: isAddOpen, onOpen: onAddOpen, onClose: onAddClose } = useDisclosure();
  const [fromUrl, setFromUrl] = useState('');
  const [toUrl, setToUrl] = useState('');

  // Delete confirm
  const [deleteTarget, setDeleteTarget] = useState<Redirect | null>(null);
  const [deleting, setDeleting] = useState(false);

  const fetchRedirects = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminTools.getRedirects();
      setRedirects(res.data ?? []);
    } catch {
      toast.error("Failed to load redirects");
    } finally {
      setLoading(false);
    }
  }, [toast])


  useEffect(() => {
    fetchRedirects();
  }, [fetchRedirects]);

  const handleAdd = async () => {
    if (!fromUrl.trim() || !toUrl.trim()) {
      toast.warning("Both from URL and to URL are Required");
      return;
    }
    setSaving(true);
    try {
      const res = await adminTools.createRedirect({
        source_url: fromUrl.trim(),
        destination_url: toUrl.trim(),
      });

      if (res.success) {
        toast.success("Redirect created");
        setFromUrl('');
        setToUrl('');
        onAddClose();
        await fetchRedirects();
      } else {
        const error = (res as { error?: string }).error || "Failed to create redirect";
        toast.error(error);
      }
    } catch (err) {
      toast.error("Failed to create redirect");
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async () => {
    if (!deleteTarget) return;
    setDeleting(true);
    try {
      const res = await adminTools.deleteRedirect(deleteTarget.id);

      if (res.success) {
        toast.success("Redirect deleted");
        setDeleteTarget(null);
        await fetchRedirects();
      } else {
        const error = (res as { error?: string }).error || "Failed to delete redirect";
        toast.error(error);
      }
    } catch (err) {
      toast.error("Failed to delete redirect");
    } finally {
      setDeleting(false);
    }
  };

  const columns: Column<Redirect>[] = [
    { key: 'source_url', label: "From URL", sortable: true },
    { key: 'destination_url', label: "To URL", sortable: true },
    { key: 'hits', label: "Hits", sortable: true },
    {
      key: 'created_at',
      label: "Created",
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
          aria-label={"Delete Redirect"}
        >
          <Trash2 size={16} />
        </Button>
      ),
    },
  ];

  if (loading) {
    return (
      <div>
        <PageHeader title={"Redirects"} description={"Manage URL redirects to send visitors from old URLs to new ones"} />
        <div className="flex justify-center py-16">
          <Spinner size="lg" />
        </div>
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title={"Redirects"}
        description={"Manage URL redirects to send visitors from old URLs to new ones"}
        actions={
          <Button color="primary" startContent={<Plus size={16} />} onPress={onAddOpen}>
            {"Add Redirect"}
          </Button>
        }
      />

      {redirects.length === 0 ? (
        <EmptyState
          icon={ArrowRightLeft}
          title={"No redirects configured"}
          description={"No redirects have been set up yet. Add one above."}
          actionLabel={"Add Redirect"}
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
          <ModalHeader>{"Add Redirect"}</ModalHeader>
          <ModalBody className="gap-4">
            <Input
              label={"From URL"}
              placeholder="/old-page"
              variant="bordered"
              value={fromUrl}
              onValueChange={setFromUrl}
              description={"The URL path to redirect from"}
            />
            <Input
              label={"To URL"}
              placeholder="/new-page"
              variant="bordered"
              value={toUrl}
              onValueChange={setToUrl}
              description={"The destination URL path to redirect to"}
            />
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onAddClose} isDisabled={saving}>
              {"Cancel"}
            </Button>
            <Button color="primary" onPress={handleAdd} isLoading={saving} isDisabled={saving}>
              {"Create Redirect"}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Delete Confirmation */}
      <ConfirmModal
        isOpen={!!deleteTarget}
        onClose={() => setDeleteTarget(null)}
        onConfirm={handleDelete}
        title={"Delete Redirect"}
        message={`Delete Redirect`}
        confirmLabel={"Delete"}
        confirmColor="danger"
        isLoading={deleting}
      />
    </div>
  );
}

export default Redirects;
