import { getFormattingLocale } from '@/lib/helpers';
import { CardBody, Card, Button, Chip, Spinner, Input, Textarea, Modal, ModalBody, ModalContent, ModalFooter, ModalHeader, Switch, Table, TableBody, TableCell, TableColumn, TableHeader, TableRow } from '@/components/ui';
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import BookOpen from 'lucide-react/icons/book-open';
import Info from 'lucide-react/icons/info';
import Plus from 'lucide-react/icons/plus';
import Pencil from 'lucide-react/icons/pencil';
import Trash2 from 'lucide-react/icons/trash-2';
import Sparkles from 'lucide-react/icons/sparkles';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { ConfirmModal } from '../../components/ConfirmModal';

interface ModuleDoc {
  id: number;
  module_slug: string;
  title: string;
  body: string;
  keywords: string[];
  is_active: boolean;
  updated_at: string;
}

interface DocForm {
  module_slug: string;
  title: string;
  body: string;
  keywords: string;
  is_active: boolean;
}

const EMPTY_FORM: DocForm = {
  module_slug: '',
  title: '',
  body: '',
  keywords: '',
  is_active: true,
};

export default function AiModuleDocsAdminPage() {
  const { t } = useTranslation('admin_ai');
  usePageTitle(t('ai.module_docs.meta.title'));
  const toast = useToast();

  const [docs, setDocs] = useState<ModuleDoc[]>([]);
  const [loading, setLoading] = useState(false);
  const [editorOpen, setEditorOpen] = useState(false);
  const [editing, setEditing] = useState<ModuleDoc | null>(null);
  const [form, setForm] = useState<DocForm>(EMPTY_FORM);
  const [saving, setSaving] = useState(false);
  const [seeding, setSeeding] = useState(false);
  const [deleteTarget, setDeleteTarget] = useState<ModuleDoc | null>(null);
  const [deleting, setDeleting] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get<ModuleDoc[]>('/v2/admin/ai-module-docs');
      setDocs(res.data ?? []);
    } catch {
      toast.error(t('ai.module_docs.toasts.load_failed'));
    } finally {
      setLoading(false);
    }
  }, [t, toast]);

  useEffect(() => {
    void load();
  }, [load]);

  function openCreate() {
    setEditing(null);
    setForm(EMPTY_FORM);
    setEditorOpen(true);
  }

  function openEdit(doc: ModuleDoc) {
    setEditing(doc);
    setForm({
      module_slug: doc.module_slug,
      title: doc.title,
      body: doc.body,
      keywords: doc.keywords.join(', '),
      is_active: doc.is_active,
    });
    setEditorOpen(true);
  }

  async function handleSave() {
    setSaving(true);
    try {
      const payload = {
        module_slug: form.module_slug.trim(),
        title: form.title.trim(),
        body: form.body.trim(),
        keywords: form.keywords.split(',').map((s) => s.trim()).filter(Boolean),
        is_active: form.is_active,
      };
      const res = editing
        ? await api.put(`/v2/admin/ai-module-docs/${editing.id}`, payload)
        : await api.post('/v2/admin/ai-module-docs', payload);
      if (res.success) {
        toast.success(t(editing ? 'ai.module_docs.toasts.updated' : 'ai.module_docs.toasts.created'));
        setEditorOpen(false);
        void load();
      } else {
        toast.error(res.error || t('ai.module_docs.toasts.save_failed'));
      }
    } catch (e) {
      const msg = e instanceof Error ? e.message : t('ai.module_docs.toasts.save_failed');
      toast.error(msg);
    } finally {
      setSaving(false);
    }
  }

  async function handleDelete() {
    if (!deleteTarget) return;
    setDeleting(true);
    try {
      const res = await api.delete(`/v2/admin/ai-module-docs/${deleteTarget.id}`);
      if (res.success) {
        toast.success(t('ai.module_docs.toasts.deleted'));
        void load();
      } else {
        toast.error(res.error || t('ai.module_docs.toasts.delete_failed'));
      }
    } catch {
      toast.error(t('ai.module_docs.toasts.delete_failed'));
    } finally {
      setDeleting(false);
      setDeleteTarget(null);
    }
  }

  async function handleSeedDefaults() {
    setSeeding(true);
    try {
      const res = await api.post<{ inserted: number }>('/v2/admin/ai-module-docs/seed-defaults', {});
      toast.success(t('ai.module_docs.toasts.seeded', { count: res.data?.inserted ?? 0 }));
      void load();
    } catch {
      toast.error(t('ai.module_docs.toasts.seed_failed'));
    } finally {
      setSeeding(false);
    }
  }

  return (
    <div className="space-y-6 p-6">
      <div className="flex items-center gap-3">
        <BookOpen size={28} className="text-accent" aria-hidden="true" />
        <div>
          <h1 className="text-2xl font-bold">{t('ai.module_docs.meta.title')}</h1>
          <p className="text-sm text-muted">
            {t('ai.module_docs.meta.description')}
          </p>
        </div>
        <div className="ml-auto flex gap-2">
          <Button
            variant="tertiary"
            startContent={<Sparkles size={16} />}
            onPress={handleSeedDefaults}
            isLoading={seeding}
          >
            {t('ai.module_docs.actions.seed_defaults')}
          </Button>
          <Button startContent={<Plus size={16} />} onPress={openCreate}>
            {t('ai.module_docs.actions.new_doc')}
          </Button>
        </div>
      </div>

      <Card className="border-l-4 border-l-accent bg-accent-soft dark:bg-accent-soft">
        <CardBody className="flex flex-row gap-3 p-4">
          <Info className="mt-0.5 h-4 w-4 shrink-0 text-accent" aria-hidden="true" />
          <div className="space-y-1 text-sm text-muted">
            <p>
              {t('ai.module_docs.about.body')}
            </p>
            <p>
              <strong>{t('ai.module_docs.about.tip_label')}</strong> {t('ai.module_docs.about.tip_body')}
            </p>
          </div>
        </CardBody>
      </Card>

      <Table aria-label={t('ai.module_docs.table_aria')} isStriped removeWrapper>
        <TableHeader>
          <TableColumn>{t('ai.module_docs.columns.slug')}</TableColumn>
          <TableColumn>{t('ai.module_docs.columns.title')}</TableColumn>
          <TableColumn>{t('ai.module_docs.columns.keywords')}</TableColumn>
          <TableColumn>{t('ai.module_docs.columns.status')}</TableColumn>
          <TableColumn>{t('ai.module_docs.columns.updated')}</TableColumn>
          <TableColumn>{t('ai.module_docs.columns.actions')}</TableColumn>
        </TableHeader>
        <TableBody
          emptyContent={loading ? (
            <span className="inline-flex items-center gap-2">
              <div role="status" aria-busy="true" aria-label={t('common.loading')} className="flex justify-center py-4"><Spinner size="sm" /></div>
              {t('ai.common.loading')}
            </span>
          ) : t('ai.module_docs.empty.no_docs')}
        >
          {docs.map((doc) => (
            <TableRow key={doc.id}>
              <TableCell>
                <span className="font-mono text-xs">{doc.module_slug}</span>
              </TableCell>
              <TableCell>{doc.title}</TableCell>
              <TableCell>
                <div className="flex flex-wrap gap-1 max-w-[20rem]">
                  {doc.keywords.slice(0, 6).map((k) => (
                    <Chip key={k} size="sm" variant="soft">
                      {k}
                    </Chip>
                  ))}
                  {doc.keywords.length > 6 && (
                    <Chip size="sm" variant="soft">
                      +{doc.keywords.length - 6}
                    </Chip>
                  )}
                </div>
              </TableCell>
              <TableCell>
                <Chip size="sm" color={doc.is_active ? 'success' : 'default'}>
                  {doc.is_active ? t('ai.module_docs.status.active') : t('ai.module_docs.status.disabled')}
                </Chip>
              </TableCell>
              <TableCell>
                <span className="text-xs">{new Date(doc.updated_at).toLocaleDateString(getFormattingLocale())}</span>
              </TableCell>
              <TableCell>
                <div className="flex gap-1">
                  <Button size="sm" variant="tertiary" isIconOnly onPress={() => openEdit(doc)} aria-label={t('ai.module_docs.actions.edit')}>
                    <Pencil size={14} />
                  </Button>
                  <Button
                    size="sm"
                    variant="danger"
                    isIconOnly
                    onPress={() => setDeleteTarget(doc)}
                    aria-label={t('ai.module_docs.actions.delete')}
                  >
                    <Trash2 size={14} />
                  </Button>
                </div>
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>

      <ConfirmModal
        isOpen={deleteTarget !== null}
        onClose={() => setDeleteTarget(null)}
        onConfirm={() => void handleDelete()}
        title={t('ai.module_docs.actions.delete')}
        message={deleteTarget ? t('ai.module_docs.confirm_delete', { title: deleteTarget.title }) : ''}
        confirmLabel={t('ai.module_docs.actions.delete')}
        cancelLabel={t('ai.common.cancel')}
        confirmColor="danger"
        isLoading={deleting}
      />

      <Modal isOpen={editorOpen} onClose={() => setEditorOpen(false)} size="3xl" scrollBehavior="inside">
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader>{editing ? t('ai.module_docs.editor.edit_title', { title: editing.title }) : t('ai.module_docs.editor.create_title')}</ModalHeader>
              <ModalBody>
                <div className="flex flex-col gap-4">
                  <Input
                    label={t('ai.module_docs.editor.fields.module_slug')}
                    placeholder={t('ai.module_docs.editor.placeholders.module_slug')}
                    value={form.module_slug}
                    onValueChange={(v) => setForm((f) => ({ ...f, module_slug: v }))}
                    variant="secondary"
                    isDisabled={Boolean(editing)}
                    description={t('ai.module_docs.editor.hints.module_slug')}
                  />
                  <Input
                    label={t('ai.module_docs.editor.fields.title')}
                    placeholder={t('ai.module_docs.editor.placeholders.title')}
                    value={form.title}
                    onValueChange={(v) => setForm((f) => ({ ...f, title: v }))}
                    variant="secondary"
                  />
                  <Input
                    label={t('ai.module_docs.editor.fields.keywords')}
                    placeholder={t('ai.module_docs.editor.placeholders.keywords')}
                    value={form.keywords}
                    onValueChange={(v) => setForm((f) => ({ ...f, keywords: v }))}
                    variant="secondary"
                    description={t('ai.module_docs.editor.hints.keywords')}
                  />
                  <Textarea
                    label={t('ai.module_docs.editor.fields.body')}
                    placeholder={t('ai.module_docs.editor.placeholders.body')}
                    value={form.body}
                    onValueChange={(v) => setForm((f) => ({ ...f, body: v }))}
                    variant="secondary"
                    minRows={10}
                    maxRows={20}
                  />
                  <div className="flex items-center justify-between rounded-xl border border-border p-3">
                    <div>
                      <p className="text-sm font-medium">{t('ai.module_docs.editor.fields.active')}</p>
                      <p className="text-xs text-muted">{t('ai.module_docs.editor.hints.active')}</p>
                    </div>
                    <Switch
                      isSelected={form.is_active}
                      onValueChange={(v) => setForm((f) => ({ ...f, is_active: v }))}
                      color="success"
                    />
                  </div>
                </div>
              </ModalBody>
              <ModalFooter>
                <Button variant="tertiary" onPress={onClose}>
                  {t('ai.common.cancel')}
                </Button>
                <Button onPress={handleSave} isLoading={saving}>
                  {editing ? t('ai.common.save_changes') : t('ai.module_docs.actions.create_doc')}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}
