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
import { ArrowRightLeft, Plus, Trash2 } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { PageHeader, EmptyState, DataTable, ConfirmModal, type Column } from '../../components';
import { adminTools } from '../../api/adminApi';

import { useTranslation } from 'react-i18next';
interface Redirect {
  id: number;
  source_url: string;
  destination_url: string;
  hits: number;
  created_at: string;
}

export function Redirects() {
  const { t } = useTranslation('admin');
  usePageTitle(t('advanced.page_title'));
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
      toast.error(t('advanced.failed_to_load_redirects'));
    } finally {
      setLoading(false);
    }
  }, [toast, t])

  useEffect(() => {
    fetchRedirects();
  }, [fetchRedirects]);

  const handleAdd = async () => {
    if (!fromUrl.trim() || !toUrl.trim()) {
      toast.warning(t('advanced.both_from_u_r_l_and_to_u_r_l_are_required'));
      return;
    }
    setSaving(true);
    try {
      const res = await adminTools.createRedirect({
        source_url: fromUrl.trim(),
        destination_url: toUrl.trim(),
      });

      if (res.success) {
        toast.success(t('advanced.redirect_created'));
        setFromUrl('');
        setToUrl('');
        onAddClose();
        await fetchRedirects();
      } else {
        const error = (res as { error?: string }).error || t('advanced.failed_to_create_redirect');
        toast.error(error);
      }
    } catch (err) {
      toast.error(t('advanced.failed_to_create_redirect'));
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
        toast.success(t('advanced.redirect_deleted'));
        setDeleteTarget(null);
        await fetchRedirects();
      } else {
        const error = (res as { error?: string }).error || t('advanced.failed_to_delete_redirect');
        toast.error(error);
      }
    } catch (err) {
      toast.error(t('advanced.failed_to_delete_redirect'));
    } finally {
      setDeleting(false);
    }
  };

  const columns: Column<Redirect>[] = [
    { key: 'source_url', label: t('advanced.col_from_url'), sortable: true },
    { key: 'destination_url', label: t('advanced.col_to_url'), sortable: true },
    { key: 'hits', label: t('advanced.col_hits'), sortable: true },
    {
      key: 'created_at',
      label: t('advanced.col_created'),
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
          aria-label={t('advanced.label_delete_redirect')}
        >
          <Trash2 size={16} />
        </Button>
      ),
    },
  ];

  if (loading) {
    return (
      <div>
        <PageHeader title={t('advanced.redirects_title')} description={t('advanced.redirects_desc')} />
        <div className="flex justify-center py-16">
          <Spinner size="lg" />
        </div>
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title={t('advanced.redirects_title')}
        description={t('advanced.redirects_desc')}
        actions={
          <Button color="primary" startContent={<Plus size={16} />} onPress={onAddOpen}>
            {t('advanced.add_redirect')}
          </Button>
        }
      />

      {redirects.length === 0 ? (
        <EmptyState
          icon={ArrowRightLeft}
          title={t('advanced.no_redirects')}
          description={t('advanced.no_redirects_desc')}
          actionLabel={t('advanced.add_redirect')}
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
          <ModalHeader>{t('advanced.add_redirect')}</ModalHeader>
          <ModalBody className="gap-4">
            <Input
              label={t('advanced.label_from_u_r_l')}
              placeholder="/old-page"
              variant="bordered"
              value={fromUrl}
              onValueChange={setFromUrl}
              description={t('advanced.desc_the_u_r_l_path_to_redirect_from')}
            />
            <Input
              label={t('advanced.label_to_u_r_l')}
              placeholder="/new-page"
              variant="bordered"
              value={toUrl}
              onValueChange={setToUrl}
              description={t('advanced.desc_the_destination_u_r_l_path')}
            />
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onAddClose} isDisabled={saving}>
              {t('advanced.cancel')}
            </Button>
            <Button color="primary" onPress={handleAdd} isLoading={saving} isDisabled={saving}>
              {t('advanced.create_redirect')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Delete Confirmation */}
      <ConfirmModal
        isOpen={!!deleteTarget}
        onClose={() => setDeleteTarget(null)}
        onConfirm={handleDelete}
        title={t('advanced.delete_redirect_title')}
        message={t('advanced.delete_redirect_message', { url: deleteTarget?.source_url })}
        confirmLabel={t('advanced.delete')}
        confirmColor="danger"
        isLoading={deleting}
      />
    </div>
  );
}

export default Redirects;
