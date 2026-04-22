// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Group Wiki Tab (GR-WIKI)
 * Collaborative knowledge base with hierarchical pages, revision history,
 * and breadcrumb navigation.
 */

import { useState, useEffect, useCallback } from 'react';
import {
  Button,
  Spinner,
  Input,
  Textarea,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Chip,
  useDisclosure,
} from '@heroui/react';
import BookOpen from 'lucide-react/icons/book-open';
import FileText from 'lucide-react/icons/file-text';
import Plus from 'lucide-react/icons/plus';
import Edit from 'lucide-react/icons/square-pen';
import Trash2 from 'lucide-react/icons/trash-2';
import History from 'lucide-react/icons/history';
import ChevronRight from 'lucide-react/icons/chevron-right';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { formatRelativeTime } from '@/lib/helpers';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface WikiPageSummary {
  id: number;
  title: string;
  slug: string;
  parent_id: number | null;
  sort_order: number;
  is_published: boolean;
  author: {
    id: number;
    name: string;
  };
  updated_at: string;
}

interface WikiPageDetail extends WikiPageSummary {
  content: string;
}

interface WikiRevision {
  id: number;
  change_summary: string | null;
  editor: {
    id: number;
    name: string;
  };
  created_at: string;
}

interface GroupWikiTabProps {
  groupId: number;
  isAdmin: boolean;
  isMember?: boolean;
}

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

/** Build a tree structure from flat pages by parent_id. */
function buildPageTree(pages: WikiPageSummary[]): (WikiPageSummary & { depth: number })[] {
  const sorted = [...pages].sort((a, b) => a.sort_order - b.sort_order);
  const result: (WikiPageSummary & { depth: number })[] = [];

  function addChildren(parentId: number | null, depth: number) {
    for (const page of sorted) {
      if (page.parent_id === parentId) {
        result.push({ ...page, depth });
        addChildren(page.id, depth + 1);
      }
    }
  }

  addChildren(null, 0);
  return result;
}

/** Build breadcrumb trail from a page up to root. */
function buildBreadcrumbs(
  pages: WikiPageSummary[],
  currentId: number,
): WikiPageSummary[] {
  const trail: WikiPageSummary[] = [];
  let current = pages.find((p) => p.id === currentId);
  while (current) {
    trail.unshift(current);
    current = current.parent_id
      ? pages.find((p) => p.id === current!.parent_id)
      : undefined;
  }
  return trail;
}

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function GroupWikiTab({ groupId, isAdmin, isMember = true }: GroupWikiTabProps) {
  const { t } = useTranslation('groups');
  const toast = useToast();
  const createModal = useDisclosure();
  const deleteModal = useDisclosure();

  // ─── Page list state ───
  const [pages, setPages] = useState<WikiPageSummary[]>([]);
  const [loading, setLoading] = useState(true);

  // ─── Selected page state ───
  const [selectedPage, setSelectedPage] = useState<WikiPageDetail | null>(null);
  const [pageLoading, setPageLoading] = useState(false);

  // ─── Edit mode state ───
  const [editing, setEditing] = useState(false);
  const [editContent, setEditContent] = useState('');
  const [changeSummary, setChangeSummary] = useState('');
  const [saving, setSaving] = useState(false);

  // ─── Create modal state ───
  const [newTitle, setNewTitle] = useState('');
  const [newContent, setNewContent] = useState('');
  const [newParentId, setNewParentId] = useState<number | null>(null);
  const [creating, setCreating] = useState(false);

  // ─── Delete state ───
  const [deleteTarget, setDeleteTarget] = useState<WikiPageSummary | null>(null);
  const [deleting, setDeleting] = useState(false);

  // ─── Revision history state ───
  const [revisions, setRevisions] = useState<WikiRevision[]>([]);
  const [revisionsOpen, setRevisionsOpen] = useState(false);
  const [revisionsLoading, setRevisionsLoading] = useState(false);

  // ─── Load page list ───
  const loadPages = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get(`/v2/groups/${groupId}/wiki`);
      const raw = res.data as WikiPageSummary[] | { pages?: WikiPageSummary[] } | undefined;
      const items = Array.isArray(raw) ? raw : (raw as { pages?: WikiPageSummary[] })?.pages ?? [];
      setPages(items);
    } catch (err) {
      logError('GroupWikiTab.loadPages', err);
      toast.error(t('wiki.load_failed', 'Failed to load wiki pages'));
    } finally {
      setLoading(false);
    }
  }, [groupId, toast, t]);

  useEffect(() => {
    loadPages();
  }, [loadPages]);

  // ─── Load single page ───
  const loadPage = useCallback(
    async (slug: string) => {
      setPageLoading(true);
      setEditing(false);
      setRevisionsOpen(false);
      setRevisions([]);
      try {
        const res = await api.get(`/v2/groups/${groupId}/wiki/${slug}`);
        setSelectedPage(res.data as WikiPageDetail);
      } catch (err) {
        logError('GroupWikiTab.loadPage', err);
        toast.error(t('wiki.page_load_failed', 'Failed to load page'));
      } finally {
        setPageLoading(false);
      }
    },
    [groupId, toast, t],
  );

  // ─── Create page ───
  const handleCreate = useCallback(async () => {
    if (!newTitle.trim() || !newContent.trim()) return;
    setCreating(true);
    try {
      const body: Record<string, unknown> = {
        title: newTitle.trim(),
        content: newContent.trim(),
      };
      if (newParentId) body.parent_id = newParentId;

      const res = await api.post<{ slug?: string }>(`/v2/groups/${groupId}/wiki`, body);
      if (res.success) {
        toast.success(t('wiki.created', 'Page created'));
        setNewTitle('');
        setNewContent('');
        setNewParentId(null);
        createModal.onClose();
        await loadPages();
        // Open the newly created page
        if (res.data?.slug) {
          loadPage(res.data.slug);
        }
      }
    } catch (err) {
      logError('GroupWikiTab.create', err);
      toast.error(t('wiki.create_failed', 'Failed to create page'));
    } finally {
      setCreating(false);
    }
  }, [groupId, newTitle, newContent, newParentId, toast, t, createModal, loadPages, loadPage]);

  // ─── Save edit ───
  const handleSave = useCallback(async () => {
    if (!selectedPage || !editContent.trim()) return;
    setSaving(true);
    try {
      const body: Record<string, unknown> = { content: editContent.trim() };
      if (changeSummary.trim()) body.change_summary = changeSummary.trim();

      const res = await api.put(`/v2/groups/${groupId}/wiki/${selectedPage.id}`, body);
      if (res.success) {
        toast.success(t('wiki.saved', 'Page saved'));
        setEditing(false);
        setChangeSummary('');
        // Reload the page to reflect the saved content
        loadPage(selectedPage.slug);
        loadPages();
      }
    } catch (err) {
      logError('GroupWikiTab.save', err);
      toast.error(t('wiki.save_failed', 'Failed to save page'));
    } finally {
      setSaving(false);
    }
  }, [groupId, selectedPage, editContent, changeSummary, toast, t, loadPage, loadPages]);

  // ─── Delete page ───
  const handleDelete = useCallback(async () => {
    if (!deleteTarget) return;
    setDeleting(true);
    try {
      await api.delete(`/v2/groups/${groupId}/wiki/${deleteTarget.id}`);
      toast.success(t('wiki.deleted', 'Page deleted'));
      setDeleteTarget(null);
      deleteModal.onClose();
      if (selectedPage?.id === deleteTarget.id) {
        setSelectedPage(null);
      }
      loadPages();
    } catch (err) {
      logError('GroupWikiTab.delete', err);
      toast.error(t('wiki.delete_failed', 'Failed to delete page'));
    } finally {
      setDeleting(false);
    }
  }, [groupId, deleteTarget, selectedPage, toast, t, deleteModal, loadPages]);

  // ─── Load revisions ───
  const loadRevisions = useCallback(
    async (pageId: number) => {
      setRevisionsLoading(true);
      try {
        const res = await api.get(`/v2/groups/${groupId}/wiki/${pageId}/revisions`);
        const rawRevisions = res.data as WikiRevision[] | { revisions?: WikiRevision[] } | undefined;
        const items = Array.isArray(rawRevisions) ? rawRevisions : (rawRevisions as { revisions?: WikiRevision[] })?.revisions ?? [];
        setRevisions(items);
        setRevisionsOpen(true);
      } catch (err) {
        logError('GroupWikiTab.loadRevisions', err);
        toast.error(t('wiki.revisions_failed', 'Failed to load revision history'));
      } finally {
        setRevisionsLoading(false);
      }
    },
    [groupId, toast, t],
  );

  // ─── Enter edit mode ───
  const startEditing = useCallback(() => {
    if (selectedPage) {
      setEditContent(selectedPage.content);
      setChangeSummary('');
      setEditing(true);
    }
  }, [selectedPage]);

  // ─── Derived data ───
  const treePages = buildPageTree(pages);
  const breadcrumbs = selectedPage ? buildBreadcrumbs(pages, selectedPage.id) : [];

  // ─── Render: loading spinner ───
  if (loading && pages.length === 0) {
    return (
      <div
        className="flex justify-center py-12"
        aria-label={t('wiki.loading', 'Loading wiki')}
        aria-busy="true"
      >
        <Spinner size="lg" />
      </div>
    );
  }

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <h2 className="text-lg font-semibold text-theme-primary flex items-center gap-2">
          <BookOpen className="w-5 h-5" aria-hidden="true" />
          {t('wiki.heading', 'Wiki')}
        </h2>
        {isMember && (
          <Button
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
            size="sm"
            startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
            onPress={createModal.onOpen}
          >
            {t('wiki.new_page', 'New Page')}
          </Button>
        )}
      </div>

      <div className="flex flex-col lg:flex-row gap-4">
        {/* Sidebar: page list */}
        <GlassCard className="p-4 lg:w-72 lg:flex-shrink-0">
          <h3 className="text-sm font-semibold text-theme-secondary mb-3">
            {t('wiki.pages', 'Pages')}
          </h3>
          {treePages.length === 0 ? (
            <p className="text-sm text-theme-subtle py-2">
              {t('wiki.no_pages_sidebar', 'No pages yet')}
            </p>
          ) : (
            <nav aria-label={t('wiki.page_nav_aria', 'Wiki page navigation')}>
              <ul className="space-y-1">
                {treePages.map((page) => (
                  <li key={page.id}>
                    <Button
                      variant="light"
                      className={`w-full text-left px-3 py-2 rounded-lg text-sm transition-colors flex items-center gap-2 h-auto min-w-0 justify-start ${
                        selectedPage?.id === page.id
                          ? 'bg-primary/10 text-primary font-medium'
                          : 'text-theme-secondary hover:bg-theme-hover'
                      }`}
                      style={{ paddingLeft: `${page.depth * 16 + 12}px` }}
                      onPress={() => loadPage(page.slug)}
                      aria-current={selectedPage?.id === page.id ? 'page' : undefined}
                    >
                      <FileText className="w-4 h-4 flex-shrink-0" aria-hidden="true" />
                      <span className="truncate">{page.title}</span>
                      {!page.is_published && (
                        <Chip size="sm" variant="flat" color="warning" className="ml-auto text-xs">
                          {t('wiki.draft', 'Draft')}
                        </Chip>
                      )}
                    </Button>
                  </li>
                ))}
              </ul>
            </nav>
          )}
        </GlassCard>

        {/* Main content area */}
        <div className="flex-1 min-w-0">
          {pageLoading ? (
            <GlassCard className="p-6">
              <div className="flex justify-center py-8" aria-busy="true">
                <Spinner size="lg" />
              </div>
            </GlassCard>
          ) : selectedPage ? (
            <GlassCard className="p-6">
              {/* Breadcrumbs */}
              {breadcrumbs.length > 1 && (
                <nav
                  className="flex items-center gap-1 text-sm text-theme-subtle mb-4 flex-wrap"
                  aria-label={t('wiki.breadcrumb_aria', 'Wiki page breadcrumb')}
                >
                  {breadcrumbs.map((crumb, idx) => (
                    <span key={crumb.id} className="flex items-center gap-1">
                      {idx > 0 && (
                        <ChevronRight className="w-3.5 h-3.5 flex-shrink-0" aria-hidden="true" />
                      )}
                      {idx < breadcrumbs.length - 1 ? (
                        <Button
                          variant="light"
                          className="hover:text-primary transition-colors underline-offset-2 hover:underline h-auto min-w-0 p-0"
                          onPress={() => loadPage(crumb.slug)}
                        >
                          {crumb.title}
                        </Button>
                      ) : (
                        <span className="text-theme-primary font-medium">{crumb.title}</span>
                      )}
                    </span>
                  ))}
                </nav>
              )}

              {/* Page header */}
              <div className="flex items-start justify-between gap-3 mb-4">
                <div className="min-w-0">
                  <h3 className="text-xl font-bold text-theme-primary">{selectedPage.title}</h3>
                  <div className="flex items-center gap-2 mt-1 text-xs text-theme-subtle">
                    <span>{selectedPage.author.name}</span>
                    <span aria-hidden="true">&#183;</span>
                    <span>{formatRelativeTime(selectedPage.updated_at)}</span>
                    {!selectedPage.is_published && (
                      <Chip size="sm" variant="flat" color="warning" className="text-xs">
                        {t('wiki.draft', 'Draft')}
                      </Chip>
                    )}
                  </div>
                </div>

                {/* Action buttons */}
                <div className="flex items-center gap-1 flex-shrink-0">
                  {isMember && !editing && (
                    <Button
                      variant="light"
                      size="sm"
                      startContent={<Edit className="w-4 h-4" aria-hidden="true" />}
                      onPress={startEditing}
                      aria-label={t('wiki.edit_aria', 'Edit page')}
                    >
                      {t('wiki.edit', 'Edit')}
                    </Button>
                  )}
                  <Button
                    variant="light"
                    size="sm"
                    startContent={<History className="w-4 h-4" aria-hidden="true" />}
                    onPress={() => loadRevisions(selectedPage.id)}
                    isLoading={revisionsLoading}
                    aria-label={t('wiki.history_aria', 'View revision history')}
                  >
                    {t('wiki.history', 'History')}
                  </Button>
                  {isAdmin && (
                    <Button
                      variant="light"
                      size="sm"
                      color="danger"
                      startContent={<Trash2 className="w-4 h-4" aria-hidden="true" />}
                      onPress={() => {
                        setDeleteTarget(selectedPage);
                        deleteModal.onOpen();
                      }}
                      aria-label={t('wiki.delete_aria', 'Delete page')}
                    >
                      {t('wiki.delete', 'Delete')}
                    </Button>
                  )}
                </div>
              </div>

              {/* Page content or edit mode */}
              {editing ? (
                <div className="space-y-4">
                  <Textarea
                    value={editContent}
                    onValueChange={setEditContent}
                    minRows={10}
                    maxRows={30}
                    placeholder={t('wiki.content_placeholder', 'Write page content...')}
                    aria-label={t('wiki.edit_content_aria', 'Edit page content')}
                    classNames={{
                      input: 'bg-transparent text-theme-primary',
                      inputWrapper: 'bg-theme-elevated border-theme-default',
                    }}
                  />
                  <Input
                    label={t('wiki.change_summary_label', 'Change summary (optional)')}
                    placeholder={t('wiki.change_summary_placeholder', 'Briefly describe your changes')}
                    value={changeSummary}
                    onValueChange={setChangeSummary}
                    size="sm"
                    classNames={{
                      input: 'bg-transparent text-theme-primary',
                      inputWrapper: 'bg-theme-elevated border-theme-default',
                      label: 'text-theme-muted',
                    }}
                  />
                  <div className="flex items-center gap-2 justify-end">
                    <Button
                      variant="flat"
                      size="sm"
                      onPress={() => setEditing(false)}
                    >
                      {t('wiki.cancel', 'Cancel')}
                    </Button>
                    <Button
                      className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                      size="sm"
                      onPress={handleSave}
                      isLoading={saving}
                      isDisabled={!editContent.trim()}
                    >
                      {t('wiki.save', 'Save')}
                    </Button>
                  </div>
                </div>
              ) : (
                <div className="prose prose-sm dark:prose-invert max-w-none text-theme-secondary whitespace-pre-wrap">
                  {selectedPage.content}
                </div>
              )}

              {/* Revision history (expandable) */}
              {revisionsOpen && (
                <div className="mt-6 border-t border-theme-default pt-4">
                  <h4 className="text-sm font-semibold text-theme-primary flex items-center gap-2 mb-3">
                    <History className="w-4 h-4" aria-hidden="true" />
                    {t('wiki.revision_history', 'Revision History')}
                  </h4>
                  {revisions.length === 0 ? (
                    <p className="text-sm text-theme-subtle">
                      {t('wiki.no_revisions', 'No revisions recorded yet')}
                    </p>
                  ) : (
                    <ul className="space-y-2" aria-label={t('wiki.revisions_list_aria', 'Page revisions')}>
                      {revisions.map((rev) => (
                        <li
                          key={rev.id}
                          className="flex items-start gap-3 p-3 rounded-lg bg-theme-elevated/50"
                        >
                          <div className="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0">
                            <Edit className="w-4 h-4 text-primary" aria-hidden="true" />
                          </div>
                          <div className="flex-1 min-w-0">
                            <div className="flex items-center gap-2 text-sm">
                              <span className="font-medium text-theme-primary">
                                {rev.editor.name}
                              </span>
                              <span className="text-theme-subtle">
                                {formatRelativeTime(rev.created_at)}
                              </span>
                            </div>
                            {rev.change_summary && (
                              <p className="text-xs text-theme-muted mt-0.5">
                                {rev.change_summary}
                              </p>
                            )}
                          </div>
                        </li>
                      ))}
                    </ul>
                  )}
                  <div className="mt-3">
                    <Button
                      variant="flat"
                      size="sm"
                      onPress={() => setRevisionsOpen(false)}
                    >
                      {t('wiki.close_history', 'Close History')}
                    </Button>
                  </div>
                </div>
              )}
            </GlassCard>
          ) : (
            <GlassCard className="p-6">
              <EmptyState
                icon={<BookOpen className="w-12 h-12" aria-hidden="true" />}
                title={t('wiki.empty_title', 'No page selected')}
                description={
                  pages.length === 0
                    ? t(
                        'wiki.empty_no_pages',
                        'Create your first wiki page to start building a knowledge base',
                      )
                    : t('wiki.empty_select', 'Select a page from the sidebar to view its content')
                }
                action={
                  isMember &&
                  pages.length === 0 && (
                    <Button
                      className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                      startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
                      onPress={createModal.onOpen}
                    >
                      {t('wiki.create_first', 'Create First Page')}
                    </Button>
                  )
                }
              />
            </GlassCard>
          )}
        </div>
      </div>

      {/* ─── Create Page Modal ─── */}
      <Modal
        isOpen={createModal.isOpen}
        onOpenChange={(open) => !open && createModal.onClose()}
        size="lg"
        classNames={{
          base: 'bg-content1 border border-theme-default',
          header: 'border-b border-theme-default',
          footer: 'border-t border-theme-default',
        }}
      >
        <ModalContent>
          {(onModalClose) => (
            <>
              <ModalHeader className="text-theme-primary flex items-center gap-2">
                <FileText className="w-5 h-5 text-purple-400" aria-hidden="true" />
                {t('wiki.create_title', 'New Wiki Page')}
              </ModalHeader>
              <ModalBody className="gap-4">
                <Input
                  label={t('wiki.title_label', 'Page Title')}
                  placeholder={t('wiki.title_placeholder', 'Enter page title')}
                  value={newTitle}
                  onValueChange={setNewTitle}
                  isRequired
                  classNames={{
                    input: 'bg-transparent text-theme-primary',
                    inputWrapper: 'bg-theme-elevated border-theme-default',
                    label: 'text-theme-muted',
                  }}
                />
                {pages.length > 0 && (
                  <div>
                    <label className="text-sm text-theme-muted mb-2 block">
                      {t('wiki.parent_label', 'Parent Page (optional)')}
                    </label>
                    <div className="flex flex-wrap gap-2">
                      <Chip
                        variant={newParentId === null ? 'solid' : 'bordered'}
                        color="primary"
                        className="cursor-pointer"
                        onClick={() => setNewParentId(null)}
                      >
                        {t('wiki.no_parent', 'Top Level')}
                      </Chip>
                      {pages.map((page) => (
                        <Chip
                          key={page.id}
                          variant={newParentId === page.id ? 'solid' : 'bordered'}
                          color="primary"
                          className="cursor-pointer"
                          onClick={() => setNewParentId(page.id)}
                        >
                          {page.title}
                        </Chip>
                      ))}
                    </div>
                  </div>
                )}
                <Textarea
                  label={t('wiki.content_label', 'Content')}
                  placeholder={t('wiki.content_placeholder', 'Write page content...')}
                  value={newContent}
                  onValueChange={setNewContent}
                  minRows={6}
                  isRequired
                  classNames={{
                    input: 'bg-transparent text-theme-primary',
                    inputWrapper: 'bg-theme-elevated border-theme-default',
                    label: 'text-theme-muted',
                  }}
                />
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onModalClose}>
                  {t('wiki.cancel', 'Cancel')}
                </Button>
                <Button
                  className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                  isLoading={creating}
                  isDisabled={!newTitle.trim() || !newContent.trim()}
                  onPress={handleCreate}
                >
                  {t('wiki.create', 'Create Page')}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>

      {/* ─── Delete Confirmation Modal ─── */}
      <Modal
        isOpen={deleteModal.isOpen}
        onOpenChange={(open) => {
          if (!open) {
            setDeleteTarget(null);
            deleteModal.onClose();
          }
        }}
        classNames={{
          base: 'bg-content1 border border-theme-default',
          header: 'border-b border-theme-default',
          footer: 'border-t border-theme-default',
        }}
      >
        <ModalContent>
          {(onModalClose) => (
            <>
              <ModalHeader className="text-theme-primary">
                {t('wiki.delete_title', 'Delete Wiki Page')}
              </ModalHeader>
              <ModalBody>
                <div className="flex items-start gap-3">
                  <Trash2 className="w-5 h-5 text-danger flex-shrink-0 mt-0.5" aria-hidden="true" />
                  <p className="text-theme-secondary">
                    {t(
                      'wiki.delete_confirm',
                      'Are you sure you want to delete "{{name}}"? This action cannot be undone.',
                      { name: deleteTarget?.title },
                    )}
                  </p>
                </div>
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onModalClose}>
                  {t('wiki.cancel', 'Cancel')}
                </Button>
                <Button color="danger" isLoading={deleting} onPress={handleDelete}>
                  {t('wiki.delete', 'Delete')}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}

export default GroupWikiTab;
