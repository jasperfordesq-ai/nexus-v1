// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Group Announcements Tab (GR3)
 * Announcements with pinning, creation for admins.
 */

import { useState, useEffect, useCallback } from 'react';
import {
  Button,
  Spinner,
  Chip,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Input,
  Textarea,
  Dropdown,
  DropdownTrigger,
  DropdownMenu,
  DropdownItem,
  useDisclosure,
} from '@heroui/react';
import {
  Megaphone,
  Pin,
  PinOff,
  Plus,
  Trash2,
  MoreVertical,
  AlertCircle,
} from 'lucide-react';
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

interface Announcement {
  id: number;
  title: string;
  content: string;
  is_pinned: boolean;
  author: {
    id: number;
    name: string;
  };
  created_at: string;
  updated_at?: string;
}

interface GroupAnnouncementsTabProps {
  groupId: number;
  isAdmin: boolean;
  isMember?: boolean;
}

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function GroupAnnouncementsTab({ groupId, isAdmin }: GroupAnnouncementsTabProps) {
  const { t } = useTranslation('groups');
  const toast = useToast();
  const { isOpen, onOpen, onClose } = useDisclosure();

  const [announcements, setAnnouncements] = useState<Announcement[]>([]);
  const [loading, setLoading] = useState(true);
  const [creating, setCreating] = useState(false);
  const [title, setTitle] = useState('');
  const [content, setContent] = useState('');
  const [isPinned, setIsPinned] = useState(false);
  const [deleteTarget, setDeleteTarget] = useState<Announcement | null>(null);
  const [deleting, setDeleting] = useState(false);

  // ─── Load announcements ───
  const loadAnnouncements = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get(`/v2/groups/${groupId}/announcements`);
      if (res.success) {
        const payload = res.data;
        const items = Array.isArray(payload)
          ? payload
          : (payload as { announcements?: Announcement[] })?.announcements ?? [];
        // Sort: pinned first, then by date
        items.sort((a: Announcement, b: Announcement) => {
          if (a.is_pinned && !b.is_pinned) return -1;
          if (!a.is_pinned && b.is_pinned) return 1;
          return new Date(b.created_at).getTime() - new Date(a.created_at).getTime();
        });
        setAnnouncements(items);
      }
    } catch (err) {
      logError('GroupAnnouncementsTab.load', err);
      toast.error(t('announcements.load_failed', 'Failed to load announcements'));
    }
    setLoading(false);
  }, [groupId, toast]);

  useEffect(() => { loadAnnouncements(); }, [loadAnnouncements]);

  // ─── Create announcement ───
  const handleCreate = useCallback(async () => {
    if (!title.trim() || !content.trim()) return;
    setCreating(true);
    try {
      const res = await api.post(`/v2/groups/${groupId}/announcements`, {
        title: title.trim(),
        content: content.trim(),
        is_pinned: isPinned,
      });
      if (res.success) {
        toast.success(t('announcements.created', 'Announcement created'));
        setTitle('');
        setContent('');
        setIsPinned(false);
        onClose();
        loadAnnouncements();
      }
    } catch (err) {
      logError('GroupAnnouncementsTab.create', err);
      toast.error(t('announcements.create_failed', 'Failed to create announcement'));
    }
    setCreating(false);
  }, [groupId, title, content, isPinned, toast, onClose, loadAnnouncements]);

  // ─── Toggle pin ───
  const handleTogglePin = useCallback(async (announcement: Announcement) => {
    try {
      await api.put(`/v2/groups/${groupId}/announcements/${announcement.id}`, {
        is_pinned: !announcement.is_pinned,
      });
      setAnnouncements((prev) =>
        prev.map((a) =>
          a.id === announcement.id ? { ...a, is_pinned: !a.is_pinned } : a
        ).sort((a, b) => {
          if (a.is_pinned && !b.is_pinned) return -1;
          if (!a.is_pinned && b.is_pinned) return 1;
          return new Date(b.created_at).getTime() - new Date(a.created_at).getTime();
        })
      );
      toast.success(announcement.is_pinned ? t('announcements.unpinned', 'Unpinned') : t('announcements.pinned_success', 'Pinned'));
    } catch (err) {
      logError('GroupAnnouncementsTab.togglePin', err);
      toast.error(t('announcements.update_failed', 'Failed to update announcement'));
    }
  }, [groupId, toast]);

  // ─── Delete ───
  const handleDelete = useCallback(async () => {
    if (!deleteTarget) return;
    setDeleting(true);
    try {
      await api.delete(`/v2/groups/${groupId}/announcements/${deleteTarget.id}`);
      toast.success(t('announcements.deleted', 'Announcement deleted'));
      setAnnouncements((prev) => prev.filter((a) => a.id !== deleteTarget.id));
      setDeleteTarget(null);
    } catch (err) {
      logError('GroupAnnouncementsTab.delete', err);
      toast.error(t('announcements.delete_failed', 'Failed to delete announcement'));
    }
    setDeleting(false);
  }, [groupId, deleteTarget, toast]);

  // ─── Render ───
  return (
    <div className="space-y-4">
      {/* Header */}
      <GlassCard className="p-6">
        <div className="flex justify-between items-center mb-4">
          <h2 className="text-lg font-semibold text-theme-primary flex items-center gap-2">
            <Megaphone className="w-5 h-5" aria-hidden="true" />
            {t('announcements.heading', 'Announcements')}
          </h2>
          {isAdmin && (
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              size="sm"
              startContent={<Plus className="w-4 h-4" />}
              onPress={onOpen}
            >
              {t('announcements.new', 'New Announcement')}
            </Button>
          )}
        </div>

        {loading ? (
          <div className="flex justify-center py-8">
            <Spinner size="lg" />
          </div>
        ) : announcements.length === 0 ? (
          <EmptyState
            icon={<Megaphone className="w-12 h-12" />}
            title={t('announcements.no_announcements_title', 'No announcements')}
            description={isAdmin ? t('announcements.no_announcements_admin_desc', 'Create an announcement to share with the group') : t('announcements.no_announcements_desc', 'No announcements have been posted yet')}
          />
        ) : (
          <div className="space-y-3">
            {announcements.map((announcement) => (
              <div
                key={announcement.id}
                className={`p-4 rounded-lg border transition-colors ${
                  announcement.is_pinned
                    ? 'border-primary/30 bg-primary/5'
                    : 'border-theme-default bg-theme-elevated'
                }`}
              >
                <div className="flex items-start justify-between gap-3">
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 mb-1">
                      {announcement.is_pinned && (
                        <Pin className="w-3.5 h-3.5 text-primary flex-shrink-0" />
                      )}
                      <h3 className="font-semibold text-theme-primary truncate">
                        {announcement.title}
                      </h3>
                      {announcement.is_pinned && (
                        <Chip size="sm" variant="flat" color="primary" className="flex-shrink-0">
                          {t('announcements.pinned', 'Pinned')}
                        </Chip>
                      )}
                    </div>
                    <p className="text-sm text-theme-secondary whitespace-pre-wrap">
                      {announcement.content}
                    </p>
                    <div className="flex items-center gap-2 mt-2 text-xs text-theme-subtle">
                      <span>{announcement.author.name}</span>
                      <span className="text-theme-muted">·</span>
                      <span>{formatRelativeTime(announcement.created_at)}</span>
                    </div>
                  </div>

                  {isAdmin && (
                    <Dropdown>
                      <DropdownTrigger>
                        <Button isIconOnly variant="light" size="sm" aria-label="Actions">
                          <MoreVertical className="w-4 h-4" />
                        </Button>
                      </DropdownTrigger>
                      <DropdownMenu aria-label="Announcement actions">
                        <DropdownItem
                          key="pin"
                          startContent={announcement.is_pinned ? <PinOff className="w-4 h-4" /> : <Pin className="w-4 h-4" />}
                          onPress={() => handleTogglePin(announcement)}
                        >
                          {announcement.is_pinned ? t('announcements.unpin', 'Unpin') : t('announcements.pin', 'Pin')}
                        </DropdownItem>
                        <DropdownItem
                          key="delete"
                          startContent={<Trash2 className="w-4 h-4" />}
                          className="text-danger"
                          color="danger"
                          onPress={() => setDeleteTarget(announcement)}
                        >
                          {t('announcements.delete', 'Delete')}
                        </DropdownItem>
                      </DropdownMenu>
                    </Dropdown>
                  )}
                </div>
              </div>
            ))}
          </div>
        )}
      </GlassCard>

      {/* Create announcement modal */}
      <Modal
        isOpen={isOpen}
        onOpenChange={(open) => !open && onClose()}
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
                <Megaphone className="w-5 h-5 text-purple-400" />
                {t('announcements.new', 'New Announcement')}
              </ModalHeader>
              <ModalBody className="gap-4">
                <Input
                  label={t('announcements.title_label', 'Title')}
                  placeholder={t('announcements.title_placeholder', 'Announcement title')}
                  value={title}
                  onChange={(e) => setTitle(e.target.value)}
                  classNames={{
                    input: 'bg-transparent text-theme-primary',
                    inputWrapper: 'bg-theme-elevated border-theme-default',
                    label: 'text-theme-muted',
                  }}
                />
                <Textarea
                  label={t('announcements.content_label', 'Content')}
                  placeholder={t('announcements.content_placeholder', 'Write your announcement...')}
                  value={content}
                  onChange={(e) => setContent(e.target.value)}
                  minRows={4}
                  classNames={{
                    input: 'bg-transparent text-theme-primary',
                    inputWrapper: 'bg-theme-elevated border-theme-default',
                    label: 'text-theme-muted',
                  }}
                />
                <div className="flex items-center gap-3">
                  <Button
                    size="sm"
                    variant={isPinned ? 'solid' : 'flat'}
                    color={isPinned ? 'primary' : 'default'}
                    startContent={<Pin className="w-4 h-4" />}
                    onPress={() => setIsPinned(!isPinned)}
                  >
                    {isPinned ? t('announcements.pinned', 'Pinned') : t('announcements.pin_this', 'Pin this announcement')}
                  </Button>
                </div>
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onModalClose}>{t('announcements.cancel', 'Cancel')}</Button>
                <Button
                  className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                  isLoading={creating}
                  isDisabled={!title.trim() || !content.trim()}
                  onPress={handleCreate}
                >
                  {t('announcements.post', 'Post Announcement')}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>

      {/* Delete confirmation modal */}
      <Modal
        isOpen={!!deleteTarget}
        onOpenChange={(open) => !open && setDeleteTarget(null)}
        classNames={{
          base: 'bg-content1 border border-theme-default',
          header: 'border-b border-theme-default',
          footer: 'border-t border-theme-default',
        }}
      >
        <ModalContent>
          {(onModalClose) => (
            <>
              <ModalHeader className="text-theme-primary">{t('announcements.delete_title', 'Delete Announcement')}</ModalHeader>
              <ModalBody>
                <div className="flex items-start gap-3">
                  <AlertCircle className="w-5 h-5 text-danger flex-shrink-0 mt-0.5" />
                  <p className="text-theme-secondary">
                    {t('announcements.delete_confirm', 'Are you sure you want to delete "{{name}}"? This action cannot be undone.', { name: deleteTarget?.title })}
                  </p>
                </div>
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onModalClose}>{t('announcements.cancel', 'Cancel')}</Button>
                <Button color="danger" isLoading={deleting} onPress={handleDelete}>{t('announcements.delete', 'Delete')}</Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}

export default GroupAnnouncementsTab;
