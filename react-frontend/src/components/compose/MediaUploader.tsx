// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * MediaUploader — Multi-file upload component for the post composer.
 *
 * Features:
 * - "Add Photos" button with drag-and-drop zone
 * - Thumbnail preview grid with remove/reorder
 * - Alt text input per image (via overlay button)
 * - Client-side image compression before handoff
 * - File validation: type (image/*), size (max 10MB), count (max 10)
 * - Upload progress (visual feedback during compression)
 */

import { useRef, useState, useCallback, type DragEvent } from 'react';
import { Button, Input } from '@heroui/react';
import { ImagePlus, X, GripVertical, Type } from 'lucide-react';
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

export interface MediaFile {
  file: File;
  preview: string;
  altText: string;
}

export interface MediaUploaderProps {
  onMediaChange: (files: MediaFile[]) => void;
  mediaFiles: MediaFile[];
  maxFiles?: number;
  maxSizeMb?: number;
}

/* ------------------------------------------------------------------ */
/*  Sortable thumbnail with alt text                                   */
/* ------------------------------------------------------------------ */

interface SortableMediaItemProps {
  id: string;
  item: MediaFile;
  index: number;
  onRemove: (index: number) => void;
  onAltTextChange: (index: number, altText: string) => void;
}

function SortableMediaItem({
  id,
  item,
  index,
  onRemove,
  onAltTextChange,
}: SortableMediaItemProps) {
  const [showAltInput, setShowAltInput] = useState(false);
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
      className={`relative rounded-xl overflow-hidden border border-[var(--border-default)] group/item ${
        isDragging ? 'opacity-50 scale-105 z-10' : ''
      } transition-all`}
    >
      {/* Drag handle */}
      <Button
        isIconOnly
        size="sm"
        variant="flat"
        className="absolute top-1.5 left-1.5 z-10 bg-black/60 text-white rounded-lg p-1.5 opacity-0 group-hover/item:opacity-100 focus:opacity-100 transition-opacity cursor-grab active:cursor-grabbing backdrop-blur-sm min-w-0 w-auto h-auto"
        aria-label="Drag to reorder"
        {...attributes}
        {...listeners}
      >
        <GripVertical className="w-4 h-4" />
      </Button>

      {/* Order badge */}
      <div className="absolute top-1.5 left-1/2 -translate-x-1/2 z-10 bg-black/60 text-white text-[10px] font-bold rounded-full w-5 h-5 flex items-center justify-center backdrop-blur-sm pointer-events-none">
        {index + 1}
      </div>

      {/* Image preview */}
      <img
        src={item.preview}
        alt={item.altText || `Upload preview ${index + 1}`}
        className="w-full aspect-square object-cover"
        draggable={false}
      />

      {/* Remove button */}
      <Button
        isIconOnly
        variant="flat"
        size="sm"
        className="absolute top-1.5 right-1.5 bg-black/60 text-white min-w-8 w-8 h-8 backdrop-blur-sm z-10"
        onPress={() => onRemove(index)}
        aria-label={`Remove image ${index + 1}`}
      >
        <X className="w-4 h-4" />
      </Button>

      {/* Alt text toggle button */}
      <Button
        isIconOnly
        variant="flat"
        size="sm"
        className={`absolute bottom-1.5 right-1.5 min-w-8 w-8 h-8 backdrop-blur-sm z-10 ${
          item.altText
            ? 'bg-[var(--color-primary)]/80 text-white'
            : 'bg-black/60 text-white opacity-0 group-hover/item:opacity-100 focus:opacity-100'
        } transition-opacity`}
        onPress={() => setShowAltInput(!showAltInput)}
        aria-label={item.altText ? 'Edit alt text' : 'Add alt text'}
      >
        <Type className="w-3.5 h-3.5" />
      </Button>

      {/* Alt text input overlay */}
      {showAltInput && (
        <div
          className="absolute inset-x-0 bottom-0 bg-black/80 backdrop-blur-sm p-2 z-20"
          onClick={(e) => e.stopPropagation()}
        >
          <Input
            size="sm"
            variant="bordered"
            placeholder="Describe this image..."
            value={item.altText}
            onChange={(e) => onAltTextChange(index, e.target.value)}
            classNames={{
              input: 'text-white text-xs',
              inputWrapper: 'border-white/30 bg-transparent min-h-8 h-8',
            }}
            aria-label="Alt text for image"
            maxLength={500}
            onKeyDown={(e) => {
              if (e.key === 'Enter') setShowAltInput(false);
            }}
          />
        </div>
      )}
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Main component                                                     */
/* ------------------------------------------------------------------ */

export function MediaUploader({
  onMediaChange,
  mediaFiles,
  maxFiles = 10,
  maxSizeMb = 10,
}: MediaUploaderProps) {
  const { t } = useTranslation('feed');
  const inputRef = useRef<HTMLInputElement>(null);
  const [compressing, setCompressing] = useState(false);
  const [isDragOver, setIsDragOver] = useState(false);

  const itemIds = mediaFiles.map((_, i) => `media-${i}`);

  /* ---- File processing ---- */

  const processFiles = useCallback(
    async (fileList: FileList | File[]) => {
      const availableSlots = maxFiles - mediaFiles.length;
      if (availableSlots <= 0) return;

      const filesToProcess = Array.from(fileList).slice(0, availableSlots);
      const validFiles = filesToProcess.filter((f) => {
        if (!f.type.startsWith('image/')) return false;
        if (f.size > maxSizeMb * 1024 * 1024) return false;
        return true;
      });

      if (validFiles.length === 0) return;

      setCompressing(true);
      try {
        const newItems: MediaFile[] = [];

        for (const file of validFiles) {
          const compressed = await compressImage(file);
          const preview = await readFileAsDataUrl(compressed);
          newItems.push({
            file: compressed,
            preview,
            altText: '',
          });
        }

        onMediaChange([...mediaFiles, ...newItems]);
      } catch {
        // Silently fail — individual file errors shouldn't block others
      } finally {
        setCompressing(false);
      }
    },
    [maxFiles, maxSizeMb, mediaFiles, onMediaChange],
  );

  const handleFileChange = useCallback(
    async (e: React.ChangeEvent<HTMLInputElement>) => {
      if (e.target.files) {
        await processFiles(e.target.files);
      }
      // Reset the input so the same files can be re-selected
      if (inputRef.current) inputRef.current.value = '';
    },
    [processFiles],
  );

  /* ---- Drag and drop ---- */

  const handleDragOver = useCallback((e: DragEvent) => {
    e.preventDefault();
    setIsDragOver(true);
  }, []);

  const handleDragLeave = useCallback((e: DragEvent) => {
    e.preventDefault();
    setIsDragOver(false);
  }, []);

  const handleDrop = useCallback(
    async (e: DragEvent) => {
      e.preventDefault();
      setIsDragOver(false);
      if (e.dataTransfer.files) {
        await processFiles(e.dataTransfer.files);
      }
    },
    [processFiles],
  );

  /* ---- Reorder ---- */

  const handleDragEnd = useCallback(
    (event: DragEndEvent) => {
      const { active, over } = event;
      if (!over || active.id === over.id) return;

      const oldIndex = itemIds.indexOf(active.id as string);
      const newIndex = itemIds.indexOf(over.id as string);

      if (oldIndex === -1 || newIndex === -1) return;

      const reordered = arrayMove(mediaFiles, oldIndex, newIndex);
      onMediaChange(reordered);
    },
    [mediaFiles, itemIds, onMediaChange],
  );

  /* ---- Handlers ---- */

  const handleRemove = useCallback(
    (index: number) => {
      const updated = mediaFiles.filter((_, i) => i !== index);
      onMediaChange(updated);
    },
    [mediaFiles, onMediaChange],
  );

  const handleAltTextChange = useCallback(
    (index: number, altText: string) => {
      const updated = mediaFiles.map((item, i) =>
        i === index ? { ...item, altText } : item,
      );
      onMediaChange(updated);
    },
    [mediaFiles, onMediaChange],
  );

  /* ---- Render ---- */

  const canAddMore = mediaFiles.length < maxFiles;
  const hasFiles = mediaFiles.length > 0;

  return (
    <div className="space-y-2">
      {/* Hidden file input */}
      <input
        ref={inputRef}
        type="file"
        accept="image/jpeg,image/png,image/gif,image/webp"
        multiple
        className="hidden"
        onChange={handleFileChange}
      />

      {/* Drag-and-drop zone (shown when no files yet) */}
      {!hasFiles && (
        <div
          className={`border-2 border-dashed rounded-xl p-6 text-center cursor-pointer transition-all ${
            isDragOver
              ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/5'
              : 'border-gray-300 dark:border-gray-600 hover:border-[var(--color-primary)]/50'
          }`}
          onClick={() => inputRef.current?.click()}
          onDragOver={handleDragOver}
          onDragLeave={handleDragLeave}
          onDrop={handleDrop}
          role="button"
          tabIndex={0}
          aria-label="Click or drag to upload images"
          onKeyDown={(e) => {
            if (e.key === 'Enter' || e.key === ' ') {
              e.preventDefault();
              inputRef.current?.click();
            }
          }}
        >
          <ImagePlus className="w-8 h-8 mx-auto mb-2 text-[var(--text-subtle)]" aria-hidden="true" />
          <p className="text-sm text-[var(--text-muted)]">
            {t('compose.media_drag_drop', 'Click or drag photos here')}
          </p>
          <p className="text-xs text-[var(--text-subtle)] mt-1">
            {t('compose.media_formats', 'JPEG, PNG, GIF, WebP up to 10MB each')}
          </p>
        </div>
      )}

      {/* Image grid with drag-and-drop reorder */}
      {hasFiles && (
        <div
          onDragOver={handleDragOver}
          onDragLeave={handleDragLeave}
          onDrop={handleDrop}
        >
          <DndContext collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
            <SortableContext items={itemIds} strategy={rectSortingStrategy}>
              <div className="grid grid-cols-3 gap-2 sm:grid-cols-4">
                {mediaFiles.map((item, index) => (
                  <SortableMediaItem
                    key={itemIds[index]}
                    id={itemIds[index]}
                    item={item}
                    index={index}
                    onRemove={handleRemove}
                    onAltTextChange={handleAltTextChange}
                  />
                ))}

                {/* Add more button (inline in grid) */}
                {canAddMore && (
                  <button
                    type="button"
                    className={`aspect-square rounded-xl border-2 border-dashed flex flex-col items-center justify-center gap-1 cursor-pointer transition-all ${
                      isDragOver
                        ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/5'
                        : 'border-gray-300 dark:border-gray-600 hover:border-[var(--color-primary)]/50'
                    }`}
                    onClick={() => inputRef.current?.click()}
                    disabled={compressing}
                    aria-label="Add more photos"
                  >
                    <ImagePlus className="w-6 h-6 text-[var(--text-subtle)]" aria-hidden="true" />
                    <span className="text-[10px] text-[var(--text-subtle)]">
                      {compressing ? t('compose.image_compressing', 'Processing...') : '+'}
                    </span>
                  </button>
                )}
              </div>
            </SortableContext>
          </DndContext>
        </div>
      )}

      {/* Controls row */}
      {hasFiles && (
        <div className="flex items-center gap-2 flex-wrap">
          {/* File count */}
          <span className="text-xs text-[var(--text-subtle)]">
            {t('compose.images_max', {
              count: mediaFiles.length,
              max: maxFiles,
              defaultValue: `${mediaFiles.length}/${maxFiles} photos`,
            })}
          </span>

          {/* Reorder hint */}
          {mediaFiles.length > 1 && (
            <span className="text-xs text-[var(--text-subtle)] hidden sm:inline">
              {t('compose.images_reorder', 'Drag to reorder')}
            </span>
          )}

          {compressing && (
            <span className="text-xs text-[var(--color-primary)] animate-pulse">
              {t('compose.image_compressing', 'Processing...')}
            </span>
          )}
        </div>
      )}
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Helpers                                                            */
/* ------------------------------------------------------------------ */

function readFileAsDataUrl(file: File): Promise<string> {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = (e) => resolve(e.target?.result as string);
    reader.onerror = reject;
    reader.readAsDataURL(file);
  });
}
