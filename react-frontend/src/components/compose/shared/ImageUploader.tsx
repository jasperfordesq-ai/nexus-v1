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
import { useTranslation } from 'react-i18next';

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
  const { t } = useTranslation('feed');
  const inputRef = useRef<HTMLInputElement>(null);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const f = e.target.files?.[0];
    if (!f) return;

    if (!f.type.startsWith('image/')) {
      onError?.(t('compose.image_select_error'));
      return;
    }

    if (f.size > maxSizeMb * 1024 * 1024) {
      onError?.(t('compose.image_size_error', { size: maxSizeMb }));
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
          <img src={preview} alt="Upload preview" className="w-full max-h-60 object-cover" loading="eager" />
          <Button
            isIconOnly
            variant="flat"
            className="absolute top-2 right-2 bg-black/60 text-white min-w-11 w-11 h-11 backdrop-blur-sm"
            onPress={handleRemove}
            aria-label={t('compose.image_remove_aria')}
          >
            <X className="w-4 h-4" />
          </Button>
        </div>
      )}

      <div className="flex items-center gap-2">
        <Button
          size="sm"
          variant="flat"
          className="bg-[var(--surface-elevated)] text-[var(--text-muted)] hover:text-[var(--color-primary)] min-h-[44px]"
          startContent={<ImagePlus className="w-4 h-4" aria-hidden="true" />}
          onPress={() => inputRef.current?.click()}
        >
          {file ? t('compose.image_change') : t('compose.image_add')}
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
