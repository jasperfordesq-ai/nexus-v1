// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * ImageUploader — reusable image selection with preview, extracted from FeedPage.
 */

import { useRef } from 'react';
import { Button } from '@heroui/react';
import { ImagePlus, X } from 'lucide-react';

interface ImageUploaderProps {
  file: File | null;
  preview: string | null;
  onSelect: (file: File, preview: string) => void;
  onRemove: () => void;
  maxSizeMb?: number;
  onError?: (msg: string) => void;
}

export function ImageUploader({
  file,
  preview,
  onSelect,
  onRemove,
  maxSizeMb = 5,
  onError,
}: ImageUploaderProps) {
  const inputRef = useRef<HTMLInputElement>(null);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const f = e.target.files?.[0];
    if (!f) return;

    if (!f.type.startsWith('image/')) {
      onError?.('Please select an image file');
      return;
    }

    if (f.size > maxSizeMb * 1024 * 1024) {
      onError?.(`Image must be smaller than ${maxSizeMb}MB`);
      return;
    }

    const reader = new FileReader();
    reader.onload = (ev) => onSelect(f, ev.target?.result as string);
    reader.readAsDataURL(f);
  };

  const handleRemove = () => {
    onRemove();
    if (inputRef.current) inputRef.current.value = '';
  };

  return (
    <>
      <input
        ref={inputRef}
        type="file"
        accept="image/jpeg,image/png,image/gif,image/webp"
        className="hidden"
        onChange={handleChange}
      />

      {preview && (
        <div className="relative rounded-xl overflow-hidden border border-[var(--border-default)]">
          <img src={preview} alt="Upload preview" className="w-full max-h-60 object-cover" />
          <Button
            isIconOnly
            variant="flat"
            className="absolute top-2 right-2 bg-black/60 text-white min-w-8 w-9 h-9 backdrop-blur-sm"
            onPress={handleRemove}
            aria-label="Remove image"
          >
            <X className="w-4 h-4" />
          </Button>
        </div>
      )}

      <div className="flex items-center gap-2">
        <Button
          size="sm"
          variant="flat"
          className="bg-[var(--surface-elevated)] text-[var(--text-muted)] hover:text-[var(--color-primary)]"
          startContent={<ImagePlus className="w-4 h-4" aria-hidden="true" />}
          onPress={() => inputRef.current?.click()}
        >
          {file ? 'Change Image' : 'Add Image'}
        </Button>
        {file && (
          <span className="text-xs text-[var(--text-subtle)]">
            {file.name} ({(file.size / 1024 / 1024).toFixed(1)}MB)
          </span>
        )}
      </div>
    </>
  );
}
