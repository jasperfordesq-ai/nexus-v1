// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Member Notes
 * Admin CRM page for managing private notes about members.
 * Supports add, edit, pin, delete, category filtering, and user search.
 */

import { useState, useCallback, useEffect } from 'react';
import {
  Card, CardBody, CardHeader, Button, Input, Textarea, Select, SelectItem,
  Modal, ModalContent, ModalHeader, ModalBody, ModalFooter, useDisclosure,
  Chip, Spinner, Pagination, Avatar, Dropdown, DropdownTrigger, DropdownMenu,
  DropdownItem,
} from '@heroui/react';
import { StickyNote, Plus, Pin, Trash2, Edit3, Filter, MoreVertical, Search } from 'lucide-react';
import { useSearchParams, Link } from 'react-router-dom';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminCrm } from '../../api/adminApi';
import { PageHeader, ConfirmModal } from '../../components';

interface Note {
  id: number;
  tenant_id: number;
  user_id: number;
  author_id: number;
  content: string;
  category: string;
  is_pinned: number;
  created_at: string;
  updated_at: string;
  user_name: string;
  user_avatar: string | null;
  author_name: string;
}

interface NotesMeta {
  total: number;
  page: number;
  limit: number;
  pages: number;
}

const CATEGORIES = [
  { key: 'general', label: 'General' },
  { key: 'outreach', label: 'Outreach' },
  { key: 'support', label: 'Support' },
  { key: 'onboarding', label: 'Onboarding' },
  { key: 'concern', label: 'Concern' },
  { key: 'follow_up', label: 'Follow Up' },
] as const;

type CategoryKey = typeof CATEGORIES[number]['key'];

const CATEGORY_COLORS: Record<string, 'default' | 'primary' | 'warning' | 'success' | 'danger' | 'secondary'> = {
  general: 'default',
  outreach: 'primary',
  support: 'warning',
  onboarding: 'success',
  concern: 'danger',
  follow_up: 'secondary',
};

const ITEMS_PER_PAGE = 20;

export function MemberNotes() {
  usePageTitle('Admin - Member Notes');
  const { tenantPath } = useTenant();
  const toast = useToast();
  const [searchParams] = useSearchParams();

  // List state
  const [notes, setNotes] = useState<Note[]>([]);
  const [meta, setMeta] = useState<NotesMeta>({ total: 0, page: 1, limit: ITEMS_PER_PAGE, pages: 1 });
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [filterCategory, setFilterCategory] = useState<string>('');
  const [filterUserId, setFilterUserId] = useState<string>(searchParams.get('user_id') || '');
  const [searchQuery, setSearchQuery] = useState('');

  // Form modal state
  const formModal = useDisclosure();
  const [editingNote, setEditingNote] = useState<Note | null>(null);
  const [formUserId, setFormUserId] = useState('');
  const [formContent, setFormContent] = useState('');
  const [formCategory, setFormCategory] = useState<CategoryKey>('general');
  const [formPinned, setFormPinned] = useState(false);
  const [saving, setSaving] = useState(false);

  // Delete state
  const [deleteTarget, setDeleteTarget] = useState<Note | null>(null);
  const [deleting, setDeleting] = useState(false);

  // ----- Data loading -----

  const loadNotes = useCallback(async () => {
    setLoading(true);
    try {
      const params: Record<string, string | number> = { page, limit: ITEMS_PER_PAGE };
      if (filterCategory) params.category = filterCategory;
      if (filterUserId) params.user_id = filterUserId;
      if (searchQuery.trim().length >= 2) params.search = searchQuery.trim();

      const res = await adminCrm.getNotes(params);
      if (res.success && res.data) {
        const payload = res.data as unknown;
        if (payload && typeof payload === 'object') {
          const p = payload as { data?: Note[]; meta?: NotesMeta };
          setNotes(p.data || []);
          if (p.meta) setMeta(p.meta);
        }
      }
    } catch {
      setNotes([]);
    }
    setLoading(false);
  }, [page, filterCategory, filterUserId, searchQuery]);

  useEffect(() => { loadNotes(); }, [loadNotes]);

  // ----- Form helpers -----

  const openCreateModal = () => {
    setEditingNote(null);
    setFormUserId(filterUserId || '');
    setFormContent('');
    setFormCategory('general');
    setFormPinned(false);
    formModal.onOpen();
  };

  const openEditModal = (note: Note) => {
    setEditingNote(note);
    setFormUserId(String(note.user_id));
    setFormContent(note.content);
    setFormCategory(note.category as CategoryKey);
    setFormPinned(note.is_pinned === 1);
    formModal.onOpen();
  };

  const handleSave = async () => {
    if (!formContent.trim()) {
      toast.error('Note content is required');
      return;
    }
    setSaving(true);
    try {
      if (editingNote) {
        const res = await adminCrm.updateNote(editingNote.id, {
          content: formContent.trim(),
          category: formCategory,
          is_pinned: formPinned,
        });
        if (res.success) {
          toast.success('Note updated');
          formModal.onClose();
          loadNotes();
        } else {
          toast.error('Failed to update note');
        }
      } else {
        if (!formUserId || isNaN(Number(formUserId))) {
          toast.error('Valid user ID is required');
          setSaving(false);
          return;
        }
        const res = await adminCrm.createNote({
          user_id: Number(formUserId),
          content: formContent.trim(),
          category: formCategory,
          is_pinned: formPinned,
        });
        if (res.success) {
          toast.success('Note created');
          formModal.onClose();
          loadNotes();
        } else {
          toast.error('Failed to create note');
        }
      }
    } catch {
      toast.error('Failed to save note');
    }
    setSaving(false);
  };

  // ----- Pin toggle -----

  const handleTogglePin = async (note: Note) => {
    try {
      const res = await adminCrm.updateNote(note.id, {
        content: note.content,
        category: note.category,
        is_pinned: note.is_pinned !== 1,
      });
      if (res.success) {
        toast.success(note.is_pinned === 1 ? 'Note unpinned' : 'Note pinned');
        loadNotes();
      } else {
        toast.error('Failed to update pin status');
      }
    } catch {
      toast.error('Failed to update pin status');
    }
  };

  // ----- Delete -----

  const handleDelete = async () => {
    if (!deleteTarget) return;
    setDeleting(true);
    try {
      const res = await adminCrm.deleteNote(deleteTarget.id);
      if (res.success) {
        toast.success('Note deleted');
        setDeleteTarget(null);
        loadNotes();
      } else {
        toast.error('Failed to delete note');
      }
    } catch {
      toast.error('Failed to delete note');
    }
    setDeleting(false);
  };

  // ----- Formatting -----

  const formatDate = (dateStr: string) => {
    const date = new Date(dateStr);
    return date.toLocaleDateString(undefined, {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  const getCategoryLabel = (key: string) => {
    const cat = CATEGORIES.find(c => c.key === key);
    return cat ? cat.label : key;
  };

  // ----- Render -----

  return (
    <div className="max-w-6xl mx-auto">
      <PageHeader
        title="Member Notes"
        description="Private notes about members for CRM tracking"
        actions={
          <Button color="primary" startContent={<Plus size={16} />} onPress={openCreateModal}>
            Add Note
          </Button>
        }
      />

      {/* Filters */}
      <div className="flex flex-wrap items-end gap-3 mb-6">
        <Input
          label="Search"
          placeholder="Search notes..."
          className="w-56"
          size="sm"
          startContent={<Search size={14} />}
          value={searchQuery}
          onValueChange={(val) => {
            setSearchQuery(val);
            setPage(1);
          }}
          isClearable
          onClear={() => { setSearchQuery(''); setPage(1); }}
        />

        <Select
          label="Category"
          placeholder="All categories"
          className="w-48"
          size="sm"
          startContent={<Filter size={14} />}
          selectedKeys={filterCategory ? [filterCategory] : []}
          onSelectionChange={(keys) => {
            const val = Array.from(keys)[0] as string || '';
            setFilterCategory(val);
            setPage(1);
          }}
        >
          {CATEGORIES.map(cat => (
            <SelectItem key={cat.key}>{cat.label}</SelectItem>
          ))}
        </Select>

        <Input
          label="User ID"
          placeholder="Filter by user ID"
          className="w-40"
          size="sm"
          type="number"
          startContent={<Search size={14} />}
          value={filterUserId}
          onValueChange={(val) => {
            setFilterUserId(val);
            setPage(1);
          }}
        />

        {(filterCategory || filterUserId || searchQuery) && (
          <Button
            size="sm"
            variant="flat"
            onPress={() => {
              setFilterCategory('');
              setFilterUserId('');
              setSearchQuery('');
              setPage(1);
            }}
          >
            Clear Filters
          </Button>
        )}
      </div>

      {/* Content */}
      {loading ? (
        <div className="flex justify-center py-16">
          <Spinner size="lg" label="Loading notes..." />
        </div>
      ) : notes.length === 0 ? (
        <Card>
          <CardBody className="flex flex-col items-center py-16 text-center">
            <StickyNote size={48} className="text-default-300 mb-4" />
            <p className="text-default-500 text-lg font-medium">No notes found</p>
            <p className="text-default-400 text-sm mt-1">
              {filterCategory || filterUserId
                ? 'Try adjusting your filters'
                : 'Create the first note for a member'}
            </p>
          </CardBody>
        </Card>
      ) : (
        <div className="flex flex-col gap-3">
          {notes.map(note => (
            <Card key={note.id} className={note.is_pinned === 1 ? 'border-l-4 border-l-warning' : ''}>
              <CardHeader className="flex items-start justify-between gap-3 pb-1">
                <div className="flex items-center gap-3 min-w-0">
                  <Avatar
                    src={note.user_avatar || undefined}
                    name={note.user_name}
                    size="sm"
                    className="shrink-0"
                  />
                  <div className="min-w-0">
                    <Link
                      to={tenantPath(`/admin/users/${note.user_id}/edit`)}
                      className="font-semibold text-foreground hover:text-primary transition-colors"
                    >
                      {note.user_name}
                    </Link>
                    <p className="text-xs text-default-400">
                      User #{note.user_id}
                    </p>
                  </div>
                  <Chip
                    size="sm"
                    variant="flat"
                    color={CATEGORY_COLORS[note.category] || 'default'}
                  >
                    {getCategoryLabel(note.category)}
                  </Chip>
                  {note.is_pinned === 1 && (
                    <Pin size={14} className="text-warning shrink-0" />
                  )}
                </div>
                <Dropdown>
                  <DropdownTrigger>
                    <Button isIconOnly size="sm" variant="light" aria-label="Note actions">
                      <MoreVertical size={16} />
                    </Button>
                  </DropdownTrigger>
                  <DropdownMenu
                    aria-label="Note actions"
                    onAction={(key) => {
                      if (key === 'edit') openEditModal(note);
                      else if (key === 'pin') handleTogglePin(note);
                      else if (key === 'delete') setDeleteTarget(note);
                    }}
                  >
                    <DropdownItem key="edit" startContent={<Edit3 size={14} />}>
                      Edit
                    </DropdownItem>
                    <DropdownItem key="pin" startContent={<Pin size={14} />}>
                      {note.is_pinned === 1 ? 'Unpin' : 'Pin'}
                    </DropdownItem>
                    <DropdownItem
                      key="delete"
                      startContent={<Trash2 size={14} />}
                      className="text-danger"
                      color="danger"
                    >
                      Delete
                    </DropdownItem>
                  </DropdownMenu>
                </Dropdown>
              </CardHeader>
              <CardBody className="pt-0">
                <p className="text-default-700 whitespace-pre-wrap">{note.content}</p>
                <div className="flex items-center gap-2 mt-3 text-xs text-default-400">
                  <span>By {note.author_name}</span>
                  <span>·</span>
                  <span>{formatDate(note.created_at)}</span>
                  {note.updated_at !== note.created_at && (
                    <>
                      <span>·</span>
                      <span>edited {formatDate(note.updated_at)}</span>
                    </>
                  )}
                </div>
              </CardBody>
            </Card>
          ))}

          {/* Pagination */}
          {meta.pages > 1 && (
            <div className="flex justify-center mt-4">
              <Pagination
                total={meta.pages}
                page={page}
                onChange={setPage}
                showControls
              />
            </div>
          )}
        </div>
      )}

      {/* Create / Edit Modal */}
      <Modal isOpen={formModal.isOpen} onClose={formModal.onClose} size="lg">
        <ModalContent>
          <ModalHeader className="flex items-center gap-2">
            <StickyNote size={20} />
            {editingNote ? 'Edit Note' : 'Add Note'}
          </ModalHeader>
          <ModalBody className="flex flex-col gap-4">
            {!editingNote && (
              <Input
                label="User ID"
                placeholder="Enter the member's user ID"
                type="number"
                isRequired
                value={formUserId}
                onValueChange={setFormUserId}
              />
            )}
            <Textarea
              label="Content"
              placeholder="Write your note about this member..."
              isRequired
              minRows={4}
              maxRows={10}
              value={formContent}
              onValueChange={setFormContent}
            />
            <Select
              label="Category"
              selectedKeys={[formCategory]}
              onSelectionChange={(keys) => {
                const val = Array.from(keys)[0] as CategoryKey;
                if (val) setFormCategory(val);
              }}
            >
              {CATEGORIES.map(cat => (
                <SelectItem key={cat.key}>{cat.label}</SelectItem>
              ))}
            </Select>
            <div className="flex items-center gap-2">
              <Button
                size="sm"
                variant={formPinned ? 'solid' : 'flat'}
                color={formPinned ? 'warning' : 'default'}
                startContent={<Pin size={14} />}
                onPress={() => setFormPinned(!formPinned)}
              >
                {formPinned ? 'Pinned' : 'Pin this note'}
              </Button>
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={formModal.onClose} isDisabled={saving}>
              Cancel
            </Button>
            <Button color="primary" onPress={handleSave} isLoading={saving}>
              {editingNote ? 'Update Note' : 'Create Note'}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Delete Confirmation */}
      <ConfirmModal
        isOpen={!!deleteTarget}
        onClose={() => setDeleteTarget(null)}
        onConfirm={handleDelete}
        title="Delete Note"
        message={`Are you sure you want to delete this note about ${deleteTarget?.user_name || 'this member'}? This action cannot be undone.`}
        confirmLabel="Delete"
        confirmColor="danger"
        isLoading={deleting}
      />
    </div>
  );
}

export default MemberNotes;
