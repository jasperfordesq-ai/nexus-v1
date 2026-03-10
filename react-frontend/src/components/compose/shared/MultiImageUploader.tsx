// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * MultiImageUploader — supports up to N images with drag-to-reorder via @dnd-kit.
 *
 * Replaces the single-image ImageUploader pattern for post composition.
 * Images are compressed client-side before being handed to the parent.
 */

import { useRef, useState, useCallback } from 'react';
import { Button } from '@heroui/react';
import { ImagePlus, X, GripVertical } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import {
  DndContext,
  closestCenter,
  type DragEndEvent,
} from '@dnd-kit/core';
import {
  SortableContext,
  rectSortingStrategy,
  useSortable,
  arrayMove,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { compressImage } from '@/lib/compress-image';

/* ------------------------------------------------------------------ */
/*  Types                                                              */
/* ------------------------------------------------------------------ */

export interface MultiImageUploaderProps {
  files: File[];
  previews: string[];
  onAdd: (file: File, preview: string) => void;
  onRemove: (index: number) => void;
  onReorder: (files: File[], previews: string[]) => void;
  maxImages?: number;
  maxSizeMb?: number;
  onError?: (msg: string) => void;
}

/* ------------------------------------------------------------------ */
/*  Sortable thumbnail                                                 */
/* ------------------------------------------------------------------ */

interface SortableImageProps {
  id: string;
  preview: string;
  index: number;
  onRemove: (index: number) => void;
  removeAriaLabel: string;
}

function SortableImage({
  id,
  preview,
  index,
  onRemove,
  removeAriaLabel,
}: SortableImageProps) {
  const {
    attributes,
    listeners,
    setNodeRef,
    transform,
    transition,
    isDragging,
  } = useSortable({ id });

  const style: React.CSSProperties = {
    transform: CSS.Transform.toString(transform),
    transition,
  };

  return (
    <div
      ref={setNodeRef}
      style={style}
      className={`relative rounded-xl overflow-hidden border border-[var(--border-default)] group ${
        isDragging ? 'opacity-50 scale-105' : ''
      } transition-all`}
    >
      {/* Drag handle */}
      <Button
        isIconOnly
        size="sm"
        variant="flat"
        className="absolute top-1.5 left-1.5 z-10 bg-black/60 text-white rounded-lg p-1.5 opacity-0 group-hover:opacity-100 focus:opacity-100 transition-opacity cursor-grab active:cursor-grabbing backdrop-blur-sm min-w-0 w-auto h-auto"
        aria-label="Drag to reorder"
        {...attributes}
        {...listeners}
      >
        <GripVertical className="w-4 h-4" />
      </Button>

      {/* Image preview */}
      <img
        src={preview}
        alt={`Upload preview ${index + 1}`}
        className="w-full aspect-square object-cover"
        draggable={false}
      />

      {/* Remove button */}
      <Button
        isIconOnly
        variant="flat"
        className="absolute top-1.5 right-1.5 bg-black/60 text-white min-w-11 w-11 h-11 backdrop-blur-sm z-10"
        onPress={() => onRemove(index)}
        aria-label={removeAriaLabel}
      >
        <X className="w-4 h-4" />
      </Button>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Main component                                                     */
/* ------------------------------------------------------------------ */

export function MultiImageUploader({
  files,
  previews,
  onAdd,
  onRemove,
  onReorder,
  maxImages = 4,
  maxSizeMb = 5,
  onError,
}: MultiImageUploaderProps) {
  const { t } = useTranslation('feed');
  const inputRef = useRef<HTMLInputElement>(null);
  const [compressing, setCompressing] = useState(false);

  // Stable IDs for each slot — keyed by index since items can be reordered
  // Using a prefix + index combo regenerated on each render is fine because
  // DndContext tracks by the ids array reference from SortableContext.
  const itemIds = files.map((_, i) => `img-${i}`);

  /* ---- File selection ---- */

  const handleFileChange = useCallback(
    async (e: React.ChangeEvent<HTMLInputElement>) => {
      const f = e.target.files?.[0];
      // Reset the input so the same file can be re-selected
      if (inputRef.current) inputRef.current.value = '';

      if (!f) return;

      if (!f.type.startsWith('image/')) {
        onError?.(t('compose.image_select_error'));
        return;
      }

      if (f.size > maxSizeMb * 1024 * 1024) {
        onError?.(t('compose.image_size_error', { size: maxSizeMb }));
        return;
      }

      try {
        setCompressing(true);
        const compressed = await compressImage(f);

        // Generate a data-URL preview
        const reader = new FileReader();
        reader.onload = (ev) => {
          const dataUrl = ev.target?.result as string;
          onAdd(compressed, dataUrl);
        };
        reader.readAsDataURL(compressed);
      } catch {
        onError?.(t('compose.image_select_error'));
      } finally {
        setCompressing(false);
      }
    },
    [maxSizeMb, onAdd, onError, t],
  );

  /* ---- Drag end ---- */

  const handleDragEnd = useCallback(
    (event: DragEndEvent) => {
      const { active, over } = event;
      if (!over || active.id === over.id) return;

      const oldIndex = itemIds.indexOf(active.id as string);
      const newIndex = itemIds.indexOf(over.id as string);

      if (oldIndex === -1 || newIndex === -1) return;

      const reorderedFiles = arrayMove(files, oldIndex, newIndex);
      const reorderedPreviews = arrayMove(previews, oldIndex, newIndex);
      onReorder(reorderedFiles, reorderedPreviews);
    },
    [files, previews, itemIds, onReorder],
  );

  /* ---- Render ---- */

  const canAddMore = files.length < maxImages;

  return (
    <div className="space-y-2">
      {/* Hidden file input */}
      <input
        ref={inputRef}
        type="file"
        accept="image/jpeg,image/png,image/gif,image/webp"
        className="hidden"
        onChange={handleFileChange}
      />

      {/* Image grid with drag-and-drop */}
      {files.length > 0 && (
        <DndContext collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
          <SortableContext items={itemIds} strategy={rectSortingStrategy}>
            <div className="grid grid-cols-2 gap-2 sm:flex sm:gap-2">
              {previews.map((preview, index) => (
                <SortableImage
                  key={itemIds[index]}
                  id={itemIds[index]}
                  preview={preview}
                  index={index}
                  onRemove={onRemove}
                  removeAriaLabel={t('compose.image_remove_aria')}
                />
              ))}
            </div>
          </SortableContext>
        </DndContext>
      )}

      {/* Controls row */}
      <div className="flex items-center gap-2 flex-wrap">
        {canAddMore && (
          <Button
            size="sm"
            variant="flat"
            className="bg-[var(--surface-elevated)] text-[var(--text-muted)] hover:text-[var(--color-primary)] min-h-[44px]"
            startContent={<ImagePlus className="w-4 h-4" aria-hidden="true" />}
            onPress={() => inputRef.current?.click()}
            isDisabled={compressing}
            isLoading={compressing}
          >
            {compressing
              ? t('compose.image_compressing')
              : t('compose.image_add')}
          </Button>
        )}

        {/* Image count badge */}
        {files.length > 0 && (
          <span className="text-xs text-[var(--text-subtle)]">
            {t('compose.images_max', { count: files.length, max: maxImages })}
          </span>
        )}

        {/* Reorder hint */}
        {files.length > 1 && (
          <span className="text-xs text-[var(--text-subtle)] hidden sm:inline">
            {t('compose.images_reorder')}
          </span>
        )}
      </div>
    </div>
  );
}
