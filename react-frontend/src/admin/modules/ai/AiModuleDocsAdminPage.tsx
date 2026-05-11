// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * AI Module Docs — admin page.
 *
 * Per-tenant, plain-language "how each module works" content. Each doc is
 * matched against incoming chat messages by keyword; when a doc's keyword
 * appears in the user's message, the doc's body is injected into the AI
 * chat system prompt as canonical grounding (preferred over the model's
 * training-data knowledge).
 *
 * ADMIN IS ENGLISH-ONLY — NO t() calls.
 */

import { useCallback, useEffect, useState } from 'react';
import {
  Button,
  Card,
  CardBody,
  Chip,
  Input,
  Modal,
  ModalBody,
  ModalContent,
  ModalFooter,
  ModalHeader,
  Switch,
  Textarea,
  Table,
  TableBody,
  TableCell,
  TableColumn,
  TableHeader,
  TableRow,
} from '@heroui/react';
import BookOpen from 'lucide-react/icons/book-open';
import Info from 'lucide-react/icons/info';
import Plus from 'lucide-react/icons/plus';
import Pencil from 'lucide-react/icons/pencil';
import Trash2 from 'lucide-react/icons/trash-2';
import Sparkles from 'lucide-react/icons/sparkles';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';

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
  usePageTitle('AI Module Docs');
  const toast = useToast();

  const [docs, setDocs] = useState<ModuleDoc[]>([]);
  const [loading, setLoading] = useState(false);
  const [editorOpen, setEditorOpen] = useState(false);
  const [editing, setEditing] = useState<ModuleDoc | null>(null);
  const [form, setForm] = useState<DocForm>(EMPTY_FORM);
  const [saving, setSaving] = useState(false);
  const [seeding, setSeeding] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get<ModuleDoc[]>('/v2/admin/ai-module-docs');
      setDocs(res.data ?? []);
    } catch {
      toast.error('Failed to load module docs');
    } finally {
      setLoading(false);
    }
  }, [toast]);

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
      if (editing) {
        await api.put(`/v2/admin/ai-module-docs/${editing.id}`, payload);
        toast.success('Doc updated');
      } else {
        await api.post('/v2/admin/ai-module-docs', payload);
        toast.success('Doc created');
      }
      setEditorOpen(false);
      void load();
    } catch (e) {
      const msg = e instanceof Error ? e.message : 'Save failed';
      toast.error(msg);
    } finally {
      setSaving(false);
    }
  }

  async function handleDelete(doc: ModuleDoc) {
    if (!confirm(`Delete "${doc.title}"? This cannot be undone.`)) return;
    try {
      await api.delete(`/v2/admin/ai-module-docs/${doc.id}`);
      toast.success('Doc deleted');
      void load();
    } catch {
      toast.error('Delete failed');
    }
  }

  async function handleSeedDefaults() {
    setSeeding(true);
    try {
      const res = await api.post<{ inserted: number }>('/v2/admin/ai-module-docs/seed-defaults', {});
      toast.success(`Seeded ${res.data?.inserted ?? 0} default doc(s)`);
      void load();
    } catch {
      toast.error('Seed failed');
    } finally {
      setSeeding(false);
    }
  }

  return (
    <div className="space-y-6 p-6">
      <div className="flex items-center gap-3">
        <BookOpen size={28} className="text-primary" />
        <div>
          <h1 className="text-2xl font-bold">AI Module Docs</h1>
          <p className="text-sm text-default-500">
            Plain-language descriptions of each module. The AI chat assistant uses these as canonical
            grounding when answering member questions.
          </p>
        </div>
        <div className="ml-auto flex gap-2">
          <Button
            variant="flat"
            startContent={<Sparkles size={16} />}
            onPress={handleSeedDefaults}
            isLoading={seeding}
          >
            Seed defaults
          </Button>
          <Button color="primary" startContent={<Plus size={16} />} onPress={openCreate}>
            New doc
          </Button>
        </div>
      </div>

      <Card className="border-l-4 border-l-primary bg-primary-50 dark:bg-primary-900/20">
        <CardBody className="flex flex-row gap-3 p-4">
          <Info className="mt-0.5 h-4 w-4 shrink-0 text-primary" aria-hidden="true" />
          <div className="space-y-1 text-sm text-default-600">
            <p>
              When a member's chat message contains any of a doc's keywords (case-insensitive,
              substring match), that doc's body is appended to the AI's system prompt. Up to 3 docs
              are injected per turn. Write in plain language — what the feature does, how to use it,
              and what's <em>not</em> possible.
            </p>
            <p>
              <strong>Tip:</strong> click "Seed defaults" to start with 10 pre-written docs covering
              the core modules. They won't overwrite anything you've customised.
            </p>
          </div>
        </CardBody>
      </Card>

      <Table aria-label="AI module docs" isStriped removeWrapper>
        <TableHeader>
          <TableColumn>Slug</TableColumn>
          <TableColumn>Title</TableColumn>
          <TableColumn>Keywords</TableColumn>
          <TableColumn>Status</TableColumn>
          <TableColumn>Updated</TableColumn>
          <TableColumn>Actions</TableColumn>
        </TableHeader>
        <TableBody
          emptyContent={loading ? 'Loading…' : 'No module docs yet. Click "Seed defaults" or "New doc" to start.'}
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
                    <Chip key={k} size="sm" variant="flat">
                      {k}
                    </Chip>
                  ))}
                  {doc.keywords.length > 6 && (
                    <Chip size="sm" variant="flat" color="default">
                      +{doc.keywords.length - 6}
                    </Chip>
                  )}
                </div>
              </TableCell>
              <TableCell>
                <Chip size="sm" color={doc.is_active ? 'success' : 'default'}>
                  {doc.is_active ? 'active' : 'disabled'}
                </Chip>
              </TableCell>
              <TableCell>
                <span className="text-xs">{new Date(doc.updated_at).toLocaleDateString()}</span>
              </TableCell>
              <TableCell>
                <div className="flex gap-1">
                  <Button size="sm" variant="flat" isIconOnly onPress={() => openEdit(doc)} aria-label="Edit">
                    <Pencil size={14} />
                  </Button>
                  <Button
                    size="sm"
                    color="danger"
                    variant="flat"
                    isIconOnly
                    onPress={() => void handleDelete(doc)}
                    aria-label="Delete"
                  >
                    <Trash2 size={14} />
                  </Button>
                </div>
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>

      <Modal isOpen={editorOpen} onClose={() => setEditorOpen(false)} size="3xl" scrollBehavior="inside">
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader>{editing ? `Edit "${editing.title}"` : 'New module doc'}</ModalHeader>
              <ModalBody>
                <div className="flex flex-col gap-4">
                  <Input
                    label="Module slug"
                    placeholder="e.g. listings, wallet, events"
                    value={form.module_slug}
                    onValueChange={(v) => setForm((f) => ({ ...f, module_slug: v }))}
                    variant="bordered"
                    isDisabled={Boolean(editing)}
                    description="Lowercase letters, numbers, underscores, dashes. Cannot be changed after creation."
                  />
                  <Input
                    label="Title"
                    placeholder="e.g. How time credits work"
                    value={form.title}
                    onValueChange={(v) => setForm((f) => ({ ...f, title: v }))}
                    variant="bordered"
                  />
                  <Input
                    label="Trigger keywords (comma-separated)"
                    placeholder="e.g. timebank, hours, credits, exchange"
                    value={form.keywords}
                    onValueChange={(v) => setForm((f) => ({ ...f, keywords: v }))}
                    variant="bordered"
                    description="When ANY of these words appears in a chat message, this doc will be injected into the AI prompt."
                  />
                  <Textarea
                    label="Body"
                    placeholder="Plain-language description of this module. What it does, how to use it, what's not possible."
                    value={form.body}
                    onValueChange={(v) => setForm((f) => ({ ...f, body: v }))}
                    variant="bordered"
                    minRows={10}
                    maxRows={20}
                  />
                  <div className="flex items-center justify-between rounded-xl border border-default-200 p-3">
                    <div>
                      <p className="text-sm font-medium">Active</p>
                      <p className="text-xs text-default-400">Inactive docs are never injected into the AI prompt.</p>
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
                <Button variant="light" onPress={onClose}>
                  Cancel
                </Button>
                <Button color="primary" onPress={handleSave} isLoading={saving}>
                  {editing ? 'Save changes' : 'Create doc'}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}
