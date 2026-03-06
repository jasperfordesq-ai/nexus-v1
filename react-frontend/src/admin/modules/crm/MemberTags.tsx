// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Member Tags
 * Admin CRM page for managing member tags.
 * Supports tag summary view, per-tag member drill-down, add/remove tags,
 * and autocomplete from existing tags.
 */

import { useState, useCallback, useEffect, useMemo } from 'react';
import {
  Card, CardBody, CardHeader, Button, Input,
  Modal, ModalContent, ModalHeader, ModalBody, ModalFooter, useDisclosure,
  Chip, Spinner, Avatar,
} from '@heroui/react';
import { Tag, Plus, Trash2, Search, Users, ArrowLeft } from 'lucide-react';
import { Link } from 'react-router-dom';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminCrm } from '../../api/adminApi';
import { PageHeader, ConfirmModal } from '../../components';

interface MemberTag {
  id: number;
  tenant_id: number;
  user_id: number;
  tag: string;
  created_by: number;
  created_at: string;
  user_name?: string;
  user_avatar?: string | null;
}

interface TagSummary {
  tag: string;
  member_count: number;
}

type ViewMode = 'summary' | 'members';

export function MemberTags() {
  usePageTitle('Admin - Member Tags');
  const { tenantPath } = useTenant();
  const toast = useToast();

  // View state
  const [viewMode, setViewMode] = useState<ViewMode>('summary');
  const [activeTag, setActiveTag] = useState<string>('');

  // Summary state
  const [tagSummaries, setTagSummaries] = useState<TagSummary[]>([]);
  const [summaryLoading, setSummaryLoading] = useState(true);
  const [summarySearch, setSummarySearch] = useState('');

  // Members state (when viewing a specific tag)
  const [memberTags, setMemberTags] = useState<MemberTag[]>([]);
  const [membersLoading, setMembersLoading] = useState(false);

  // Add tag modal state
  const addModal = useDisclosure();
  const [formUserId, setFormUserId] = useState('');
  const [formTag, setFormTag] = useState('');
  const [formTagSearch, setFormTagSearch] = useState('');
  const [saving, setSaving] = useState(false);

  // Delete state
  const [deleteTarget, setDeleteTarget] = useState<MemberTag | null>(null);
  const [deleteSummaryTarget, setDeleteSummaryTarget] = useState<TagSummary | null>(null);
  const [deleting, setDeleting] = useState(false);

  // ----- Data loading -----

  const loadTagSummaries = useCallback(async () => {
    setSummaryLoading(true);
    try {
      const res = await adminCrm.getTags();
      if (res.success && res.data) {
        const payload = res.data as unknown;
        if (Array.isArray(payload)) {
          setTagSummaries(payload as TagSummary[]);
        }
      }
    } catch {
      setTagSummaries([]);
    }
    setSummaryLoading(false);
  }, []);

  const loadMembersByTag = useCallback(async (tag: string) => {
    setMembersLoading(true);
    try {
      const res = await adminCrm.getTags({ tag });
      if (res.success && res.data) {
        const payload = res.data as unknown;
        if (Array.isArray(payload)) {
          setMemberTags(payload as MemberTag[]);
        }
      }
    } catch {
      setMemberTags([]);
    }
    setMembersLoading(false);
  }, []);

  useEffect(() => {
    loadTagSummaries();
  }, [loadTagSummaries]);

  // ----- Navigation -----

  const openTagMembers = (tag: string) => {
    setActiveTag(tag);
    setViewMode('members');
    loadMembersByTag(tag);
  };

  const backToSummary = () => {
    setViewMode('summary');
    setActiveTag('');
    setMemberTags([]);
    loadTagSummaries();
  };

  // ----- Add Tag -----

  const openAddModal = () => {
    setFormUserId('');
    setFormTag('');
    setFormTagSearch('');
    addModal.onOpen();
  };

  const handleAddTag = async () => {
    if (!formUserId || isNaN(Number(formUserId))) {
      toast.error('Valid user ID is required');
      return;
    }
    const tagValue = formTag.trim() || formTagSearch.trim();
    if (!tagValue) {
      toast.error('Tag name is required');
      return;
    }
    setSaving(true);
    try {
      const res = await adminCrm.addTag({
        user_id: Number(formUserId),
        tag: tagValue,
      });
      if (res.success) {
        toast.success('Tag added');
        addModal.onClose();
        if (viewMode === 'members' && tagValue === activeTag) {
          loadMembersByTag(activeTag);
        } else {
          loadTagSummaries();
        }
      } else {
        toast.error('Failed to add tag');
      }
    } catch {
      toast.error('Failed to add tag');
    }
    setSaving(false);
  };

  // ----- Delete Member Tag -----

  const handleDeleteMemberTag = async () => {
    if (!deleteTarget) return;
    setDeleting(true);
    try {
      const res = await adminCrm.removeTag(deleteTarget.id);
      if (res.success) {
        toast.success('Tag removed from member');
        setDeleteTarget(null);
        if (viewMode === 'members') {
          loadMembersByTag(activeTag);
        }
        loadTagSummaries();
      } else {
        toast.error('Failed to remove tag');
      }
    } catch {
      toast.error('Failed to remove tag');
    }
    setDeleting(false);
  };

  // ----- Delete entire tag (all members) -----

  const handleDeleteSummaryTag = async () => {
    if (!deleteSummaryTarget) return;
    setDeleting(true);
    try {
      const res = await adminCrm.bulkRemoveTag(deleteSummaryTarget.tag);
      if (res.success) {
        toast.success(`Tag "${deleteSummaryTarget.tag}" removed from all members`);
        setDeleteSummaryTarget(null);
        loadTagSummaries();
      } else {
        toast.error('Failed to remove tag');
      }
    } catch {
      toast.error('Failed to remove tag');
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

  // ----- Filtered data -----

  const filteredSummaries = useMemo(() => {
    if (!summarySearch.trim()) return tagSummaries;
    const query = summarySearch.toLowerCase().trim();
    return tagSummaries.filter(ts => ts.tag.toLowerCase().includes(query));
  }, [tagSummaries, summarySearch]);

  // Autocomplete suggestions for the add modal
  const tagSuggestions = useMemo(() => {
    if (!formTagSearch.trim()) return [];
    const query = formTagSearch.toLowerCase().trim();
    return tagSummaries
      .filter(ts => ts.tag.toLowerCase().includes(query))
      .slice(0, 8);
  }, [tagSummaries, formTagSearch]);

  // ----- Render: Summary View -----

  const renderSummaryView = () => (
    <>
      {/* Search */}
      <div className="flex flex-wrap items-end gap-3 mb-6">
        <Input
          label="Search Tags"
          placeholder="Filter by tag name"
          className="w-64"
          size="sm"
          startContent={<Search size={14} />}
          value={summarySearch}
          onValueChange={setSummarySearch}
        />
        {summarySearch && (
          <Button
            size="sm"
            variant="flat"
            onPress={() => setSummarySearch('')}
          >
            Clear
          </Button>
        )}
      </div>

      {/* Tag Grid */}
      {summaryLoading ? (
        <div className="flex justify-center py-16">
          <Spinner size="lg" label="Loading tags..." />
        </div>
      ) : filteredSummaries.length === 0 ? (
        <Card>
          <CardBody className="flex flex-col items-center py-16 text-center">
            <Tag size={48} className="text-default-300 mb-4" />
            <p className="text-default-500 text-lg font-medium">No tags found</p>
            <p className="text-default-400 text-sm mt-1">
              {summarySearch
                ? 'Try adjusting your search'
                : 'Create the first tag for a member'}
            </p>
          </CardBody>
        </Card>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
          {filteredSummaries.map(ts => (
            <Card
              key={ts.tag}
              isPressable
              onPress={() => openTagMembers(ts.tag)}
              className="hover:border-primary transition-colors"
            >
              <CardHeader className="flex items-center justify-between pb-1">
                <div className="flex items-center gap-2 min-w-0">
                  <Tag size={16} className="text-primary shrink-0" />
                  <span className="font-semibold text-foreground truncate">{ts.tag}</span>
                </div>
                <Button
                  isIconOnly
                  size="sm"
                  variant="light"
                  color="danger"
                  onPress={() => {
                    setDeleteSummaryTarget(ts);
                  }}
                  aria-label={`Delete tag ${ts.tag}`}
                >
                  <Trash2 size={14} />
                </Button>
              </CardHeader>
              <CardBody className="pt-0">
                <div className="flex items-center gap-2">
                  <Users size={14} className="text-default-400" />
                  <Chip size="sm" variant="flat" color="primary">
                    {ts.member_count} {ts.member_count === 1 ? 'member' : 'members'}
                  </Chip>
                </div>
              </CardBody>
            </Card>
          ))}
        </div>
      )}
    </>
  );

  // ----- Render: Members View -----

  const renderMembersView = () => (
    <>
      {/* Back button */}
      <div className="mb-4">
        <Button
          variant="light"
          startContent={<ArrowLeft size={16} />}
          onPress={backToSummary}
        >
          Back to All Tags
        </Button>
      </div>

      <div className="flex items-center gap-3 mb-6">
        <Tag size={20} className="text-primary" />
        <h2 className="text-xl font-semibold">
          Members tagged &ldquo;{activeTag}&rdquo;
        </h2>
      </div>

      {membersLoading ? (
        <div className="flex justify-center py-16">
          <Spinner size="lg" label="Loading members..." />
        </div>
      ) : memberTags.length === 0 ? (
        <Card>
          <CardBody className="flex flex-col items-center py-16 text-center">
            <Users size={48} className="text-default-300 mb-4" />
            <p className="text-default-500 text-lg font-medium">No members with this tag</p>
            <p className="text-default-400 text-sm mt-1">
              Add this tag to members using the Add Tag button
            </p>
          </CardBody>
        </Card>
      ) : (
        <div className="flex flex-col gap-3">
          {memberTags.map(mt => (
            <Card key={mt.id}>
              <CardBody className="flex flex-row items-center justify-between gap-4">
                <div className="flex items-center gap-3 min-w-0">
                  <Avatar
                    src={mt.user_avatar || undefined}
                    name={mt.user_name || `User #${mt.user_id}`}
                    size="sm"
                    className="shrink-0"
                  />
                  <div className="min-w-0">
                    <Link
                      to={tenantPath(`/admin/users/${mt.user_id}/edit`)}
                      className="font-semibold text-foreground hover:text-primary transition-colors truncate block"
                    >
                      {mt.user_name || `User #${mt.user_id}`}
                    </Link>
                    <p className="text-xs text-default-400">
                      User #{mt.user_id}
                    </p>
                  </div>
                  <Chip size="sm" variant="flat" color="primary">
                    <Tag size={12} className="inline mr-1" />
                    {mt.tag}
                  </Chip>
                  <span className="text-xs text-default-400 hidden sm:inline">
                    {formatDate(mt.created_at)}
                  </span>
                </div>
                <Button
                  isIconOnly
                  size="sm"
                  variant="light"
                  color="danger"
                  onPress={() => setDeleteTarget(mt)}
                  aria-label={`Remove tag from ${mt.user_name || `User #${mt.user_id}`}`}
                >
                  <Trash2 size={16} />
                </Button>
              </CardBody>
            </Card>
          ))}
        </div>
      )}
    </>
  );

  // ----- Main Render -----

  return (
    <div className="max-w-6xl mx-auto">
      <PageHeader
        title="Member Tags"
        description="Organize members with tags for CRM segmentation"
        actions={
          <Button color="primary" startContent={<Plus size={16} />} onPress={openAddModal}>
            Add Tag
          </Button>
        }
      />

      {viewMode === 'summary' ? renderSummaryView() : renderMembersView()}

      {/* Add Tag Modal */}
      <Modal isOpen={addModal.isOpen} onClose={addModal.onClose} size="lg">
        <ModalContent>
          <ModalHeader className="flex items-center gap-2">
            <Tag size={20} />
            Add Tag
          </ModalHeader>
          <ModalBody className="flex flex-col gap-4">
            <Input
              label="User ID"
              placeholder="Enter the member's user ID"
              type="number"
              isRequired
              value={formUserId}
              onValueChange={setFormUserId}
            />
            <div className="flex flex-col gap-2">
              <Input
                label="Tag"
                placeholder="Type a tag name or select from suggestions"
                isRequired
                value={formTag || formTagSearch}
                onValueChange={(val) => {
                  setFormTag('');
                  setFormTagSearch(val);
                }}
              />
              {tagSuggestions.length > 0 && !formTag && (
                <div className="flex flex-wrap gap-2">
                  {tagSuggestions.map(ts => (
                    <Chip
                      key={ts.tag}
                      variant="flat"
                      color="primary"
                      className="cursor-pointer hover:opacity-80 transition-opacity"
                      onClick={() => {
                        setFormTag(ts.tag);
                        setFormTagSearch('');
                      }}
                    >
                      {ts.tag} ({ts.member_count})
                    </Chip>
                  ))}
                </div>
              )}
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={addModal.onClose} isDisabled={saving}>
              Cancel
            </Button>
            <Button color="primary" onPress={handleAddTag} isLoading={saving}>
              Add Tag
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Delete Member Tag Confirmation */}
      <ConfirmModal
        isOpen={!!deleteTarget}
        onClose={() => setDeleteTarget(null)}
        onConfirm={handleDeleteMemberTag}
        title="Remove Tag"
        message={`Are you sure you want to remove the tag "${deleteTarget?.tag}" from ${deleteTarget?.user_name || `User #${deleteTarget?.user_id}`}? This action cannot be undone.`}
        confirmLabel="Remove"
        confirmColor="danger"
        isLoading={deleting}
      />

      {/* Delete Summary Tag Confirmation */}
      <ConfirmModal
        isOpen={!!deleteSummaryTarget}
        onClose={() => setDeleteSummaryTarget(null)}
        onConfirm={handleDeleteSummaryTag}
        title="Remove Tag Entirely"
        message={`Are you sure you want to remove the tag "${deleteSummaryTarget?.tag}" from all ${deleteSummaryTarget?.member_count} ${deleteSummaryTarget?.member_count === 1 ? 'member' : 'members'}? This action cannot be undone.`}
        confirmLabel="Remove All"
        confirmColor="danger"
        isLoading={deleting}
      />
    </div>
  );
}

export default MemberTags;
