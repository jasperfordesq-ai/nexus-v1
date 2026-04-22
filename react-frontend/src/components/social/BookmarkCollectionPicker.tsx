// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * BookmarkCollectionPicker — Popover/BottomSheet to choose a bookmark collection.
 *
 * Shows the user's collections with radio-style selection, plus a "New Collection"
 * inline form. Used by BookmarkButton on long-press (mobile) or secondary click.
 */

import { useState, useCallback } from 'react';
import { Button, Input, Spinner, Divider } from '@heroui/react';
import Plus from 'lucide-react/icons/plus';
import FolderOpen from 'lucide-react/icons/folder-open';
import Check from 'lucide-react/icons/check';
import { useTranslation } from 'react-i18next';
import { useBookmarkCollections } from '@/hooks/useBookmarkCollections';

interface BookmarkCollectionPickerProps {
  /** Currently selected collection ID (null = no collection) */
  selectedId: number | null;
  /** Called when a collection is selected */
  onSelect: (collectionId: number | null) => void;
  /** Called after the picker should close */
  onClose: () => void;
}

export function BookmarkCollectionPicker({ selectedId, onSelect, onClose }: BookmarkCollectionPickerProps) {
  const { t } = useTranslation('social');
  const { collections, isLoading, createCollection } = useBookmarkCollections();
  const [isCreating, setIsCreating] = useState(false);
  const [newName, setNewName] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleSelect = useCallback((id: number | null) => {
    onSelect(id);
    onClose();
  }, [onSelect, onClose]);

  const handleCreate = useCallback(async () => {
    if (!newName.trim()) return;
    setIsSubmitting(true);
    const col = await createCollection(newName.trim());
    if (col) {
      handleSelect(col.id);
    }
    setIsSubmitting(false);
    setNewName('');
    setIsCreating(false);
  }, [newName, createCollection, handleSelect]);

  if (isLoading) {
    return (
      <div className="flex justify-center p-4">
        <Spinner size="sm" />
      </div>
    );
  }

  return (
    <div className="min-w-[220px] max-w-[280px]">
      <p className="text-xs font-semibold text-[var(--text-muted)] uppercase tracking-wider px-3 pt-2 pb-1">
        {t('bookmark.save_to', 'Save to collection')}
      </p>

      {/* "No collection" option */}
      <Button
        variant="light"
        onPress={() => handleSelect(null)}
        className="w-full flex items-center gap-2 px-3 py-2 text-sm text-[var(--text-primary)] hover:bg-[var(--surface-hover)] rounded-lg transition-colors h-auto justify-start"
      >
        <FolderOpen className="w-4 h-4 text-[var(--text-muted)]" />
        <span className="flex-1 text-left">{t('bookmark.no_collection', 'General (no collection)')}</span>
        {selectedId === null && <Check className="w-4 h-4 text-[var(--color-primary)]" />}
      </Button>

      {/* Existing collections */}
      {collections.map((col) => (
        <Button
          key={col.id}
          variant="light"
          onPress={() => handleSelect(col.id)}
          className="w-full flex items-center gap-2 px-3 py-2 text-sm text-[var(--text-primary)] hover:bg-[var(--surface-hover)] rounded-lg transition-colors h-auto justify-start"
        >
          <FolderOpen className="w-4 h-4 text-amber-500" />
          <span className="flex-1 text-left truncate">{col.name}</span>
          <span className="text-xs text-[var(--text-subtle)]">{col.bookmarks_count}</span>
          {selectedId === col.id && <Check className="w-4 h-4 text-[var(--color-primary)]" />}
        </Button>
      ))}

      <Divider className="my-1" />

      {/* Create new collection */}
      {isCreating ? (
        <div className="flex items-center gap-1 px-2 py-1">
          <Input
            size="sm"
            variant="bordered"
            placeholder={t('bookmark.collection_name', 'Collection name')}
            value={newName}
            onValueChange={setNewName}
            onKeyDown={(e) => { if (e.key === 'Enter') handleCreate(); if (e.key === 'Escape') setIsCreating(false); }}
            autoFocus
            className="flex-1"
          />
          <Button size="sm" isIconOnly color="primary" onPress={handleCreate} isLoading={isSubmitting}>
            <Check className="w-3 h-3" />
          </Button>
        </div>
      ) : (
        <Button
          variant="light"
          onPress={() => setIsCreating(true)}
          className="w-full flex items-center gap-2 px-3 py-2 text-sm text-[var(--color-primary)] hover:bg-[var(--surface-hover)] rounded-lg transition-colors h-auto justify-start"
        >
          <Plus className="w-4 h-4" />
          <span>{t('bookmark.new_collection', 'New collection')}</span>
        </Button>
      )}
    </div>
  );
}
