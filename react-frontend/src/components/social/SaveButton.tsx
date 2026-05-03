// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * SaveButton — SOC10 bookmarks/saved-collections.
 *
 * Click: opens HeroUI Popover with "Save to..." collection list + inline create.
 * Filled bookmark icon = saved. Optimistic UI.
 *
 * Parents typically issue a bulk-check on mount to set initial `isSaved`.
 */

import { useState, useCallback, useEffect, useRef } from 'react';
import {
  Button,
  Popover,
  PopoverTrigger,
  PopoverContent,
  Input,
  Spinner,
  Divider,
} from '@heroui/react';
import Bookmark from 'lucide-react/icons/bookmark';
import BookmarkPlus from 'lucide-react/icons/bookmark-plus';
import Plus from 'lucide-react/icons/plus';
import Check from 'lucide-react/icons/check';
import { useTranslation } from 'react-i18next';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

interface CollectionLite {
  id: number;
  name: string;
  color?: string;
  icon?: string;
  items_count: number;
}

interface SaveButtonProps {
  itemType: string;
  itemId: number | string;
  initialSaved?: boolean;
  size?: 'sm' | 'md';
  className?: string;
  onChange?: (saved: boolean) => void;
}

export function SaveButton({
  itemType,
  itemId,
  initialSaved = false,
  size = 'sm',
  className = '',
  onChange,
}: SaveButtonProps) {
  const { t } = useTranslation('common');
  const [saved, setSaved] = useState(initialSaved);
  const [isOpen, setIsOpen] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [collections, setCollections] = useState<CollectionLite[]>([]);
  const [collectionsLoaded, setCollectionsLoaded] = useState(false);
  const [creating, setCreating] = useState(false);
  const [newName, setNewName] = useState('');
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    setSaved(initialSaved);
  }, [initialSaved]);

  const loadCollections = useCallback(async () => {
    try {
      const res = await api.get<CollectionLite[]>('/v2/me/collections');
      if (res.success && Array.isArray(res.data)) {
        setCollections(res.data);
      }
    } catch (err) {
      logError('SaveButton: failed to load collections', err);
    } finally {
      setCollectionsLoaded(true);
    }
  }, []);

  const numericId = typeof itemId === 'string' ? parseInt(itemId, 10) : itemId;

  const saveTo = useCallback(async (collectionId: number | null) => {
    if (busy || !Number.isFinite(numericId)) return;
    setBusy(true);
    const prev = saved;
    setSaved(true);
    try {
      const res = await api.post('/v2/me/saved-items', {
        item_type: itemType,
        item_id: numericId,
        collection_id: collectionId,
      });
      if (res.success) {
        onChange?.(true);
      } else {
        setSaved(prev);
      }
    } catch (err) {
      logError('SaveButton: save failed', err);
      setSaved(prev);
    } finally {
      setBusy(false);
      setIsOpen(false);
    }
  }, [busy, numericId, itemType, saved, onChange]);

  const unsave = useCallback(async () => {
    if (busy || !Number.isFinite(numericId)) return;
    setBusy(true);
    const prev = saved;
    setSaved(false);
    try {
      // The bulk DELETE-by-pair endpoint isn't exposed; we re-call save with same item which
      // is idempotent — instead we look up the saved item via check then DELETE its id is too
      // chatty. The cleanest UX path: navigate to /me/collections/:id to remove.
      // For now we just optimistically call save again (no-op) and let collections page do removal.
      // We try the dedicated unsave: POST with negative collection wouldn't work; use new endpoint.
      // To keep things simple we send POST again which is idempotent for the row, but to actually
      // remove we need an item-id. So we trigger unsave-by-pair:
      const res = await api.delete(`/v2/me/saved-items?item_type=${encodeURIComponent(itemType)}&item_id=${numericId}`);
      if (res.success) {
        onChange?.(false);
      } else {
        setSaved(prev);
      }
    } catch (err) {
      logError('SaveButton: unsave failed', err);
      setSaved(prev);
    } finally {
      setBusy(false);
    }
  }, [busy, numericId, itemType, saved, onChange]);

  const handleOpen = useCallback((open: boolean) => {
    setIsOpen(open);
    if (open && !collectionsLoaded) {
      setIsLoading(true);
      loadCollections().finally(() => setIsLoading(false));
    }
  }, [collectionsLoaded, loadCollections]);

  const handleCreate = useCallback(async () => {
    const name = newName.trim();
    if (!name) return;
    try {
      const res = await api.post<CollectionLite>('/v2/me/collections', { name });
      if (res.success && res.data) {
        const newCol = res.data;
        setCollections((prev) => [...prev, newCol]);
        setNewName('');
        setCreating(false);
        await saveTo(newCol.id);
      }
    } catch (err) {
      logError('SaveButton: create collection failed', err);
    }
  }, [newName, saveTo]);

  const iconSize = size === 'sm' ? 'w-[18px] h-[18px]' : 'w-5 h-5';

  // If already saved, single click removes; otherwise open picker.
  const handleTriggerPress = useCallback(() => {
    if (saved) {
      unsave();
    } else {
      handleOpen(true);
    }
  }, [saved, unsave, handleOpen]);

  return (
    <Popover placement="bottom-end" isOpen={isOpen} onOpenChange={handleOpen}>
      <PopoverTrigger>
        <Button
          isIconOnly
          size={size}
          variant="light"
          aria-label={saved ? t('collections.remove') : t('collections.save')}
          onPress={handleTriggerPress}
          className={`min-w-0 ${className}`}
        >
          {saved ? (
            <Bookmark className={`${iconSize} fill-current text-[var(--color-warning)]`} />
          ) : (
            <BookmarkPlus className={`${iconSize} text-[var(--text-muted)] hover:text-[var(--color-warning)]`} />
          )}
        </Button>
      </PopoverTrigger>

      <PopoverContent className="p-2 min-w-[240px]">
        <div className="w-full">
          <p className="text-xs font-semibold uppercase tracking-wider px-2 pt-1 pb-2 text-[var(--text-muted)]">
            {t('collections.save_to')}
          </p>

          {isLoading ? (
            <div className="flex justify-center py-4"><Spinner size="sm" /></div>
          ) : (
            <>
              <Button
                variant="light"
                size="sm"
                className="w-full justify-start"
                onPress={() => saveTo(null)}
              >
                <Bookmark className="w-4 h-4 mr-2" />
                {t('collections.default')}
              </Button>

              {collections.map((col) => (
                <Button
                  key={col.id}
                  variant="light"
                  size="sm"
                  className="w-full justify-start"
                  onPress={() => saveTo(col.id)}
                >
                  <span
                    className="w-3 h-3 rounded-full mr-2"
                    style={{ backgroundColor: col.color || '#6366f1' }}
                  />
                  <span className="flex-1 truncate text-left">{col.name}</span>
                  <span className="text-xs text-[var(--text-subtle)]">{col.items_count}</span>
                </Button>
              ))}

              <Divider className="my-1" />

              {creating ? (
                <div className="flex items-center gap-1 px-1 py-1">
                  <Input
                    size="sm"
                    variant="bordered"
                    placeholder={t('collections.name_placeholder')}
                    value={newName}
                    onValueChange={setNewName}
                    autoFocus
                    onKeyDown={(e) => {
                      if (e.key === 'Enter') handleCreate();
                      if (e.key === 'Escape') setCreating(false);
                    }}
                    className="flex-1"
                  />
                  <Button isIconOnly size="sm" color="primary" onPress={handleCreate}>
                    <Check className="w-3 h-3" />
                  </Button>
                </div>
              ) : (
                <Button
                  variant="light"
                  size="sm"
                  className="w-full justify-start text-[var(--color-primary)]"
                  onPress={() => setCreating(true)}
                >
                  <Plus className="w-4 h-4 mr-2" />
                  {t('collections.new')}
                </Button>
              )}
            </>
          )}
        </div>
      </PopoverContent>
    </Popover>
  );
}

export default SaveButton;
