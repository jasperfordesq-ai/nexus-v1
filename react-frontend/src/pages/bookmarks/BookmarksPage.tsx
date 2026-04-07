// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * BookmarksPage — View and manage saved/bookmarked items.
 * Tabs for content types, collection management, grid/list view.
 */

import { useState, useEffect, useCallback } from 'react';
import { Link } from 'react-router-dom';
import {
  Button,
  Chip,
  Tabs,
  Tab,
  Card,
  CardBody,
  Input,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Spinner,
  Dropdown,
  DropdownTrigger,
  DropdownMenu,
  DropdownItem,
  Divider,
  useDisclosure,
} from '@heroui/react';
import {
  Bookmark,
  BookmarkCheck,
  FolderPlus,
  Pencil,
  Trash2,
  MoreHorizontal,
  BookOpen,
  Calendar,
  Briefcase,
  MessageSquare,
  ShoppingBag,
  Inbox,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { useTenant, useToast } from '@/contexts';
import { usePageTitle } from '@/hooks/usePageTitle';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { formatRelativeTime } from '@/lib/helpers';

interface BookmarkItem {
  id: number;
  bookmarkable_type: string;
  bookmarkable_id: number;
  collection_id: number | null;
  created_at: string;
}

interface BookmarkCollectionData {
  id: number;
  name: string;
  description: string | null;
  is_default: boolean;
  bookmarks_count?: number;
}

type ContentTab = 'all' | 'post' | 'listing' | 'event' | 'job' | 'blog' | 'discussion';

const TAB_ICONS: Record<ContentTab, React.ElementType> = {
  all: BookmarkCheck,
  post: MessageSquare,
  listing: ShoppingBag,
  event: Calendar,
  job: Briefcase,
  blog: BookOpen,
  discussion: MessageSquare,
};

export default function BookmarksPage() {
  const { t } = useTranslation('social');
  const { tenantPath } = useTenant();
  const toast = useToast();
  usePageTitle(t('bookmarks.page_title', 'Saved'));

  const [activeTab, setActiveTab] = useState<ContentTab>('all');
  const [bookmarks, setBookmarks] = useState<BookmarkItem[]>([]);
  const [collections, setCollections] = useState<BookmarkCollectionData[]>([]);
  const [selectedCollection, setSelectedCollection] = useState<number | null>(null);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [hasMore, setHasMore] = useState(false);
  const [total, setTotal] = useState(0);

  // Collection modal state
  const { isOpen: isCreateOpen, onOpen: onCreateOpen, onClose: onCreateClose } = useDisclosure();
  const [newCollName, setNewCollName] = useState('');
  const [newCollDesc, setNewCollDesc] = useState('');
  const [editingColl, setEditingColl] = useState<BookmarkCollectionData | null>(null);
  const [collSaving, setCollSaving] = useState(false);

  const loadBookmarks = useCallback(async (p = 1, type?: ContentTab, collId?: number | null) => {
    setLoading(true);
    try {
      const params = new URLSearchParams({ page: String(p), per_page: '20' });
      if (type && type !== 'all') params.set('type', type);
      if (collId) params.set('collection_id', String(collId));

      const res = await api.get<BookmarkItem[]>(`/v2/bookmarks?${params}`);
      if (res.success && res.data) {
        setBookmarks(p === 1 ? res.data : (prev) => [...prev, ...res.data!]);
        setTotal(res.meta?.total ?? 0);
        setHasMore(res.meta?.has_more ?? false);
        setPage(p);
      }
    } catch (err) {
      logError('Failed to load bookmarks', err);
    } finally {
      setLoading(false);
    }
  }, []);

  const loadCollections = useCallback(async () => {
    try {
      const res = await api.get<BookmarkCollectionData[]>('/v2/bookmark-collections');
      if (res.success && res.data) {
        setCollections(res.data);
      }
    } catch (err) {
      logError('Failed to load collections', err);
    }
  }, []);

  useEffect(() => {
    loadBookmarks(1, activeTab, selectedCollection);
    loadCollections();
  }, [activeTab, selectedCollection, loadBookmarks, loadCollections]);

  const handleRemoveBookmark = async (bookmark: BookmarkItem) => {
    try {
      const res = await api.post('/v2/bookmarks', {
        type: bookmark.bookmarkable_type,
        id: bookmark.bookmarkable_id,
      });
      if (res.success) {
        setBookmarks((prev) => prev.filter((b) => b.id !== bookmark.id));
        setTotal((prev) => prev - 1);
        toast.success(t('bookmarks.removed', 'Removed from saved'));
      }
    } catch (err) {
      logError('Failed to remove bookmark', err);
      toast.error(t('bookmarks.remove_failed', 'Failed to remove'));
    }
  };

  const handleSaveCollection = async () => {
    if (!newCollName.trim()) return;
    setCollSaving(true);

    try {
      if (editingColl) {
        await api.patch(`/v2/bookmark-collections/${editingColl.id}`, {
          name: newCollName.trim(),
          description: newCollDesc.trim() || null,
        });
        toast.success(t('bookmarks.collection_updated', 'Collection updated'));
      } else {
        await api.post('/v2/bookmark-collections', {
          name: newCollName.trim(),
          description: newCollDesc.trim() || null,
        });
        toast.success(t('bookmarks.collection_created', 'Collection created'));
      }
      onCreateClose();
      setNewCollName('');
      setNewCollDesc('');
      setEditingColl(null);
      loadCollections();
    } catch (err) {
      logError('Failed to save collection', err);
      toast.error(t('bookmarks.collection_save_failed', 'Failed to save collection'));
    } finally {
      setCollSaving(false);
    }
  };

  const handleDeleteCollection = async (coll: BookmarkCollectionData) => {
    if (!window.confirm(t('bookmarks.delete_collection_confirm', 'Delete this collection? Bookmarks will be kept.'))) return;

    try {
      await api.delete(`/v2/bookmark-collections/${coll.id}`);
      toast.success(t('bookmarks.collection_deleted', 'Collection deleted'));
      if (selectedCollection === coll.id) setSelectedCollection(null);
      loadCollections();
    } catch (err) {
      logError('Failed to delete collection', err);
      toast.error(t('bookmarks.collection_delete_failed', 'Failed to delete collection'));
    }
  };

  const getDetailPath = (bookmark: BookmarkItem): string => {
    const { bookmarkable_type: type, bookmarkable_id: id } = bookmark;
    switch (type) {
      case 'post': return `/feed/posts/${id}`;
      case 'listing': return `/listings/${id}`;
      case 'event': return `/events/${id}`;
      case 'job': return `/jobs/${id}`;
      case 'blog': return `/blog/${id}`;
      case 'discussion': return `/feed/posts/${id}`;
      default: return `/feed/posts/${id}`;
    }
  };

  const getTypeLabel = (type: string): string => {
    const labels: Record<string, string> = {
      post: t('bookmarks.type_post', 'Post'),
      listing: t('bookmarks.type_listing', 'Listing'),
      event: t('bookmarks.type_event', 'Event'),
      job: t('bookmarks.type_job', 'Job'),
      blog: t('bookmarks.type_blog', 'Blog'),
      discussion: t('bookmarks.type_discussion', 'Discussion'),
    };
    return labels[type] || type;
  };

  const getTypeColor = (type: string) => {
    const colors: Record<string, 'primary' | 'success' | 'warning' | 'secondary' | 'default'> = {
      post: 'default',
      listing: 'primary',
      event: 'success',
      job: 'primary',
      blog: 'secondary',
      discussion: 'secondary',
    };
    return colors[type] || 'default';
  };

  return (
    <div className="max-w-4xl mx-auto px-4 sm:px-6 py-8">
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-[var(--text-primary)] flex items-center gap-2">
            <Bookmark className="w-6 h-6 text-amber-500" aria-hidden="true" />
            {t('bookmarks.title', 'Saved Items')}
          </h1>
          <p className="text-sm text-[var(--text-muted)] mt-1">
            {t('bookmarks.subtitle', 'Your bookmarked content, organized your way.')}
          </p>
        </div>
        <Button
          color="primary"
          variant="flat"
          size="sm"
          startContent={<FolderPlus className="w-4 h-4" aria-hidden="true" />}
          onPress={onCreateOpen}
        >
          {t('bookmarks.new_collection', 'New Collection')}
        </Button>
      </div>

      {/* Collections Bar */}
      {collections.length > 0 && (
        <div className="flex items-center gap-2 mb-4 overflow-x-auto pb-2">
          <Chip
            variant={selectedCollection === null ? 'solid' : 'bordered'}
            color="primary"
            className="cursor-pointer shrink-0"
            onClick={() => setSelectedCollection(null)}
          >
            {t('bookmarks.all_items', 'All')}
          </Chip>
          {collections.map((coll) => (
            <div key={coll.id} className="flex items-center gap-1 shrink-0">
              <Chip
                variant={selectedCollection === coll.id ? 'solid' : 'bordered'}
                color="primary"
                className="cursor-pointer"
                onClick={() => setSelectedCollection(selectedCollection === coll.id ? null : coll.id)}
              >
                {coll.name} {coll.bookmarks_count != null && `(${coll.bookmarks_count})`}
              </Chip>
              <Dropdown placement="bottom-end">
                <DropdownTrigger>
                  <Button isIconOnly size="sm" variant="light" className="min-w-0 w-6 h-6">
                    <MoreHorizontal className="w-3 h-3" />
                  </Button>
                </DropdownTrigger>
                <DropdownMenu>
                  <DropdownItem
                    key="edit"
                    startContent={<Pencil className="w-3.5 h-3.5" />}
                    onPress={() => {
                      setEditingColl(coll);
                      setNewCollName(coll.name);
                      setNewCollDesc(coll.description || '');
                      onCreateOpen();
                    }}
                  >
                    {t('bookmarks.edit_collection', 'Edit')}
                  </DropdownItem>
                  <DropdownItem
                    key="delete"
                    startContent={<Trash2 className="w-3.5 h-3.5" />}
                    className="text-danger"
                    color="danger"
                    onPress={() => handleDeleteCollection(coll)}
                  >
                    {t('bookmarks.delete_collection', 'Delete')}
                  </DropdownItem>
                </DropdownMenu>
              </Dropdown>
            </div>
          ))}
        </div>
      )}

      {/* Content Type Tabs */}
      <Tabs
        selectedKey={activeTab}
        onSelectionChange={(key) => { setActiveTab(key as ContentTab); setPage(1); }}
        variant="underlined"
        classNames={{ tabList: 'gap-1', tab: 'text-sm' }}
        className="mb-6"
      >
        {(['all', 'post', 'listing', 'event', 'job', 'blog'] as ContentTab[]).map((tab) => {
          const Icon = TAB_ICONS[tab];
          return (
            <Tab
              key={tab}
              title={
                <div className="flex items-center gap-1.5">
                  <Icon className="w-4 h-4" aria-hidden="true" />
                  {tab === 'all' ? t('bookmarks.tab_all', 'All') : getTypeLabel(tab)}
                </div>
              }
            />
          );
        })}
      </Tabs>

      {/* Count */}
      {!loading && (
        <p className="text-xs text-[var(--text-muted)] mb-4">
          {t('bookmarks.count', '{{count}} saved items', { count: total })}
        </p>
      )}

      {/* Bookmarks List */}
      {loading && bookmarks.length === 0 ? (
        <div className="flex justify-center py-16">
          <Spinner size="lg" />
        </div>
      ) : bookmarks.length === 0 ? (
        <GlassCard className="text-center py-16">
          <Inbox className="w-12 h-12 mx-auto text-[var(--text-subtle)] mb-4" aria-hidden="true" />
          <h3 className="text-lg font-semibold text-[var(--text-primary)] mb-2">
            {t('bookmarks.empty_title', 'No saved items yet')}
          </h3>
          <p className="text-sm text-[var(--text-muted)] max-w-sm mx-auto">
            {t('bookmarks.empty_desc', 'Tap the bookmark icon on posts, listings, events, and more to save them here.')}
          </p>
        </GlassCard>
      ) : (
        <div className="space-y-3">
          {bookmarks.map((bookmark) => (
            <Card key={bookmark.id} shadow="none" className="border border-[var(--border-default)] hover:border-[var(--color-primary)]/30 transition-colors">
              <CardBody className="p-4">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-3 min-w-0">
                    <Chip size="sm" variant="flat" color={getTypeColor(bookmark.bookmarkable_type)} className="shrink-0">
                      {getTypeLabel(bookmark.bookmarkable_type)}
                    </Chip>
                    <Link
                      to={tenantPath(getDetailPath(bookmark))}
                      className="text-sm font-medium text-[var(--text-primary)] hover:text-[var(--color-primary)] transition-colors truncate"
                    >
                      {getTypeLabel(bookmark.bookmarkable_type)} #{bookmark.bookmarkable_id}
                    </Link>
                    <span className="text-xs text-[var(--text-subtle)] shrink-0">
                      {formatRelativeTime(bookmark.created_at)}
                    </span>
                  </div>
                  <Button
                    isIconOnly
                    size="sm"
                    variant="light"
                    className="text-[var(--text-muted)] hover:text-danger shrink-0"
                    onPress={() => handleRemoveBookmark(bookmark)}
                    aria-label={t('bookmarks.remove', 'Remove from saved')}
                  >
                    <Trash2 className="w-4 h-4" />
                  </Button>
                </div>
              </CardBody>
            </Card>
          ))}

          {/* Load More */}
          {hasMore && (
            <div className="text-center pt-4">
              <Button
                variant="bordered"
                onPress={() => loadBookmarks(page + 1, activeTab, selectedCollection)}
                isLoading={loading}
              >
                {t('bookmarks.load_more', 'Load More')}
              </Button>
            </div>
          )}
        </div>
      )}

      {/* Create/Edit Collection Modal */}
      <Modal isOpen={isCreateOpen} onClose={() => { onCreateClose(); setEditingColl(null); setNewCollName(''); setNewCollDesc(''); }}>
        <ModalContent>
          <ModalHeader>
            {editingColl
              ? t('bookmarks.edit_collection_title', 'Edit Collection')
              : t('bookmarks.create_collection_title', 'Create Collection')
            }
          </ModalHeader>
          <ModalBody>
            <Input
              label={t('bookmarks.collection_name', 'Name')}
              value={newCollName}
              onChange={(e) => setNewCollName(e.target.value)}
              maxLength={100}
              isRequired
              variant="bordered"
            />
            <Input
              label={t('bookmarks.collection_description', 'Description (optional)')}
              value={newCollDesc}
              onChange={(e) => setNewCollDesc(e.target.value)}
              variant="bordered"
            />
          </ModalBody>
          <ModalFooter>
            <Button variant="light" onPress={() => { onCreateClose(); setEditingColl(null); }}>
              {t('bookmarks.cancel', 'Cancel')}
            </Button>
            <Button
              color="primary"
              onPress={handleSaveCollection}
              isLoading={collSaving}
              isDisabled={!newCollName.trim()}
            >
              {editingColl ? t('bookmarks.save', 'Save') : t('bookmarks.create', 'Create')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}
