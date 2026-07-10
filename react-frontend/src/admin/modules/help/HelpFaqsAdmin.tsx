// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Help FAQs Management
 *
 * Full CRUD for the member-facing help centre FAQs.
 * Backend: GET/POST /v2/admin/help/faqs, PUT/DELETE /v2/admin/help/faqs/{id}
 */

import { getFormattingLocale } from '@/lib/helpers';
import { useState, useCallback, useEffect } from 'react';
import { useTranslation } from 'react-i18next';

import Plus from 'lucide-react/icons/plus';
import MoreVertical from 'lucide-react/icons/ellipsis-vertical';
import Edit from 'lucide-react/icons/square-pen';
import Trash2 from 'lucide-react/icons/trash-2';
import HelpCircle from 'lucide-react/icons/help-circle';
import AlertTriangle from 'lucide-react/icons/triangle-alert';

import {
  Button,
  Card,
  CardBody,
  Chip,
  Input,
  Textarea,
  Switch,
  Dropdown,
  DropdownTrigger,
  DropdownMenu,
  DropdownItem,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
} from '@/components/ui';
import { useToast } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { useAdminPageMeta } from '../../AdminMetaContext';
import { adminHelpFaqs, type AdminHelpFaq } from '../../api/adminApi';
import { DataTable, type Column } from '../../components/DataTable';
import { PageHeader } from '../../components/PageHeader';
import { ConfirmModal } from '../../components/ConfirmModal';
import { EmptyState } from '../../components/EmptyState';

interface FaqFormData {
  category: string;
  question: string;
  answer: string;
  sort_order: string;
  is_published: boolean;
}

const EMPTY_FORM: FaqFormData = {
  category: '',
  question: '',
  answer: '',
  sort_order: '0',
  is_published: true,
};

// ─── Actions menu ───

interface FaqActionsMenuProps {
  faq: AdminHelpFaq;
  t: (key: string, options?: Record<string, unknown>) => string;
  openEditModal: (faq: AdminHelpFaq) => void;
  setDeleteTarget: React.Dispatch<React.SetStateAction<AdminHelpFaq | null>>;
}

function FaqActionsMenu({ faq, t, openEditModal, setDeleteTarget }: FaqActionsMenuProps) {
  const handleMenuAction = (key: React.Key) => {
    if (key === 'edit') {
      openEditModal(faq);
    } else if (key === 'delete') {
      setDeleteTarget(faq);
    }
  };

  return (
    <Dropdown>
      <DropdownTrigger>
        <Button isIconOnly size="sm" variant="tertiary" aria-label={t('help_faqs.actions_aria')}>
          <MoreVertical size={16} />
        </Button>
      </DropdownTrigger>
      <DropdownMenu aria-label={t('help_faqs.actions_aria')} onAction={handleMenuAction}>
        <DropdownItem key="edit" id="edit" startContent={<Edit size={14} />}>
          {t('common.edit')}
        </DropdownItem>
        <DropdownItem key="delete" id="delete" startContent={<Trash2 size={14} />} className="text-danger" variant="danger">
          {t('common.delete')}
        </DropdownItem>
      </DropdownMenu>
    </Dropdown>
  );
}

export function HelpFaqsAdmin() {
  const { t } = useTranslation('admin_help_module');
  usePageTitle(t('help_faqs.page_title'));
  useAdminPageMeta({ title: t('help_faqs.page_title') });
  const toast = useToast();

  // Data state
  const [faqs, setFaqs] = useState<AdminHelpFaq[]>([]);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);

  // Modal state
  const [modalOpen, setModalOpen] = useState(false);
  const [editingFaq, setEditingFaq] = useState<AdminHelpFaq | null>(null);
  const [formData, setFormData] = useState<FaqFormData>(EMPTY_FORM);
  const [saving, setSaving] = useState(false);

  // Delete confirm state
  const [deleteTarget, setDeleteTarget] = useState<AdminHelpFaq | null>(null);
  const [deleting, setDeleting] = useState(false);

  // ─── Data loading ───

  const loadFaqs = useCallback(async () => {
    setLoading(true);
    setLoadError(null);
    try {
      const res = await adminHelpFaqs.list();
      if (res.success && Array.isArray(res.data)) {
        setFaqs(res.data);
      } else if (res.success) {
        setFaqs([]);
      } else {
        setLoadError(res.error || t('help_faqs.load_failed'));
        toast.error(res.error || t('help_faqs.load_failed'));
      }
    } catch {
      setLoadError(t('help_faqs.load_failed'));
      toast.error(t('help_faqs.load_failed'));
    } finally {
      setLoading(false);
    }
  }, [t, toast]);

  useEffect(() => {
    loadFaqs();
  }, [loadFaqs]);

  // ─── Create / Edit ───

  const openCreateModal = () => {
    setEditingFaq(null);
    setFormData(EMPTY_FORM);
    setModalOpen(true);
  };

  const openEditModal = (faq: AdminHelpFaq) => {
    setEditingFaq(faq);
    setFormData({
      category: faq.category || '',
      question: faq.question,
      answer: faq.answer,
      sort_order: String(faq.sort_order ?? 0),
      is_published: !!faq.is_published,
    });
    setModalOpen(true);
  };

  const closeModal = () => {
    setModalOpen(false);
    setEditingFaq(null);
    setFormData(EMPTY_FORM);
  };

  const handleSave = async () => {
    if (!formData.question.trim()) {
      toast.error(t('help_faqs.question_required'));
      return;
    }
    if (!formData.answer.trim()) {
      toast.error(t('help_faqs.answer_required'));
      return;
    }

    setSaving(true);

    const payload = {
      category: formData.category.trim() || 'General',
      question: formData.question.trim(),
      answer: formData.answer.trim(),
      sort_order: parseInt(formData.sort_order, 10) || 0,
      is_published: formData.is_published,
    };

    const res = editingFaq
      ? await adminHelpFaqs.update(editingFaq.id, payload)
      : await adminHelpFaqs.create(payload);

    if (res.success) {
      toast.success(editingFaq ? t('help_faqs.updated_toast') : t('help_faqs.created_toast'));
      closeModal();
      loadFaqs();
    } else {
      // Preserve form state so the admin can correct and retry.
      toast.error(res.error || t('help_faqs.save_failed'));
    }

    setSaving(false);
  };

  // ─── Publish toggle ───

  const togglePublished = async (faq: AdminHelpFaq, isPublished: boolean) => {
    // Optimistic flip, reverted on failure.
    setFaqs((prev) => prev.map((f) => (f.id === faq.id ? { ...f, is_published: isPublished } : f)));
    const res = await adminHelpFaqs.update(faq.id, { is_published: isPublished });
    if (res.success) {
      toast.success(t('help_faqs.updated_toast'));
    } else {
      setFaqs((prev) => prev.map((f) => (f.id === faq.id ? { ...f, is_published: !isPublished } : f)));
      toast.error(res.error || t('help_faqs.save_failed'));
    }
  };

  // ─── Delete ───

  const handleDelete = async () => {
    if (!deleteTarget) return;
    setDeleting(true);

    const res = await adminHelpFaqs.delete(deleteTarget.id);
    if (res.success) {
      toast.success(t('help_faqs.deleted_toast'));
      setDeleteTarget(null);
      loadFaqs();
    } else {
      toast.error(res.error || t('help_faqs.delete_failed'));
    }

    setDeleting(false);
  };

  // ─── Table columns ───

  const columns: Column<AdminHelpFaq>[] = [
    {
      key: 'question',
      label: t('help_faqs.question'),
      sortable: true,
      isRowHeader: true,
      render: (faq) => (
        <div className="max-w-md">
          <p className="font-medium text-foreground line-clamp-2">{faq.question}</p>
        </div>
      ),
    },
    {
      key: 'category',
      label: t('help_faqs.category'),
      sortable: true,
      render: (faq) => (
        <Chip size="sm" variant="soft" color="primary">
          {faq.category}
        </Chip>
      ),
    },
    {
      key: 'sort_order',
      label: t('help_faqs.sort_order'),
      sortable: true,
      render: (faq) => <span className="text-sm text-muted">{faq.sort_order}</span>,
    },
    {
      key: 'is_published',
      label: t('help_faqs.status'),
      sortable: true,
      render: (faq) => (
        <Switch
          size="sm"
          isSelected={!!faq.is_published}
          onValueChange={(val) => togglePublished(faq, val)}
          aria-label={t('help_faqs.publish_toggle_aria', { question: faq.question })}
        >
          <span className="text-xs text-muted">
            {faq.is_published ? t('help_faqs.published') : t('help_faqs.draft')}
          </span>
        </Switch>
      ),
    },
    {
      key: 'created_at',
      label: t('help_faqs.created'),
      sortable: true,
      render: (faq) => (
        <span className="text-sm text-muted">
          {faq.created_at ? new Date(faq.created_at).toLocaleDateString(getFormattingLocale()) : '—'}
        </span>
      ),
    },
    {
      key: 'actions',
      label: t('common.actions'),
      render: (faq) => (
        <FaqActionsMenu
          faq={faq}
          t={t}
          openEditModal={openEditModal}
          setDeleteTarget={setDeleteTarget}
        />
      ),
    },
  ];

  // ─── Render ───

  return (
    <div>
      <PageHeader
        title={t('help_faqs.page_title')}
        description={t('help_faqs.description')}
        actions={
          <Button startContent={<Plus aria-hidden="true" size={16} />} onPress={openCreateModal}>
            {t('help_faqs.add_faq')}
          </Button>
        }
      />

      {loadError && !loading ? (
        <Card role="alert">
          <CardBody className="flex flex-col items-center gap-3 py-10 text-center">
            <AlertTriangle aria-hidden="true" size={32} className="text-danger" />
            <div className="text-base font-semibold">{t('common.error_loading_data')}</div>
            <div className="text-sm text-muted">{loadError}</div>
            <Button variant="tertiary" onPress={loadFaqs}>{t('common.retry')}</Button>
          </CardBody>
        </Card>
      ) : faqs.length === 0 && !loading ? (
        <EmptyState
          icon={HelpCircle}
          title={t('help_faqs.empty_title')}
          description={t('help_faqs.empty_description')}
          actionLabel={t('help_faqs.add_faq')}
          onAction={openCreateModal}
        />
      ) : (
        <DataTable
          columns={columns}
          data={faqs}
          isLoading={loading}
          searchPlaceholder={t('help_faqs.search_placeholder')}
          onRefresh={loadFaqs}
          emptyContent={t('help_faqs.empty_title')}
        />
      )}

      {/* ─── Create / Edit Modal ─── */}
      <Modal isOpen={modalOpen} onClose={closeModal} size="lg">
        <ModalContent>
          <ModalHeader className="flex items-center gap-2">
            <HelpCircle aria-hidden="true" size={20} />
            {editingFaq ? t('help_faqs.edit_faq') : t('help_faqs.add_faq')}
          </ModalHeader>
          <ModalBody className="gap-4">
            <Input
              label={t('help_faqs.question')}
              placeholder={t('help_faqs.question_placeholder')}
              value={formData.question}
              onValueChange={(v) => setFormData((prev) => ({ ...prev, question: v }))}
              isRequired
              variant="secondary"
              autoFocus
            />
            <Textarea
              label={t('help_faqs.answer')}
              placeholder={t('help_faqs.answer_placeholder')}
              value={formData.answer}
              onValueChange={(v) => setFormData((prev) => ({ ...prev, answer: v }))}
              isRequired
              variant="secondary"
              rows={6}
            />
            <div className="grid gap-4 sm:grid-cols-2">
              <Input
                label={t('help_faqs.category')}
                placeholder={t('help_faqs.category_placeholder')}
                value={formData.category}
                onValueChange={(v) => setFormData((prev) => ({ ...prev, category: v }))}
                variant="secondary"
              />
              <Input
                type="number"
                label={t('help_faqs.sort_order')}
                value={formData.sort_order}
                onValueChange={(v) => setFormData((prev) => ({ ...prev, sort_order: v }))}
                variant="secondary"
              />
            </div>
            <Switch
              isSelected={formData.is_published}
              onValueChange={(v) => setFormData((prev) => ({ ...prev, is_published: v }))}
            >
              {t('help_faqs.published')}
            </Switch>
          </ModalBody>
          <ModalFooter>
            <Button variant="tertiary" onPress={closeModal} isDisabled={saving}>
              {t('common.cancel')}
            </Button>
            <Button onPress={handleSave} isLoading={saving} isDisabled={saving}>
              {editingFaq ? t('help_faqs.save') : t('help_faqs.create')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* ─── Delete Confirmation ─── */}
      {deleteTarget && (
        <ConfirmModal
          isOpen={!!deleteTarget}
          onClose={() => setDeleteTarget(null)}
          onConfirm={handleDelete}
          title={t('help_faqs.delete_faq')}
          message={t('help_faqs.delete_confirm', { question: deleteTarget.question })}
          confirmLabel={t('common.delete')}
          cancelLabel={t('common.cancel')}
          confirmColor="danger"
          isLoading={deleting}
        />
      )}
    </div>
  );
}

export default HelpFaqsAdmin;
