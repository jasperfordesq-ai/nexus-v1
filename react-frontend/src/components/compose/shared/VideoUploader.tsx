// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * VideoUploader — video file upload component for the compose modal.
 */

import { useRef, useState } from 'react';
import { Button } from '@heroui/react';
import { Video, X, AlertCircle } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface VideoUploaderProps {
  onVideoSelect: (file: File) => void;
  onVideoRemove: () => void;
  selectedVideo: File | null;
}

const ACCEPTED_TYPES = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'];
const MAX_SIZE_MB = 100;
const MAX_SIZE_BYTES = MAX_SIZE_MB * 1024 * 1024;

function formatFileSize(bytes: number): string {
  if (bytes < 1024 * 1024) {
    return `${(bytes / 1024).toFixed(1)} KB`;
  }
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export function VideoUploader({ onVideoSelect, onVideoRemove, selectedVideo }: VideoUploaderProps) {
  const { t } = useTranslation('feed');
  const inputRef = useRef<HTMLInputElement>(null);
  const [error, setError] = useState<string | null>(null);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    setError(null);

    if (!ACCEPTED_TYPES.includes(file.type)) {
      setError(t('compose.video_invalid_type', 'Invalid video format. Use MP4, WebM, OGG, or MOV.'));
      if (inputRef.current) inputRef.current.value = '';
      return;
    }

    if (file.size > MAX_SIZE_BYTES) {
      setError(
        t('compose.video_too_large', 'Video must be under {{size}}MB.', { size: MAX_SIZE_MB })
      );
      if (inputRef.current) inputRef.current.value = '';
      return;
    }

    onVideoSelect(file);
  };

  const handleRemove = () => {
    setError(null);
    onVideoRemove();
    if (inputRef.current) inputRef.current.value = '';
  };

  const handleTrigger = () => {
    inputRef.current?.click();
  };

  return (
    <div>
      <input
        ref={inputRef}
        type="file"
        accept="video/mp4,video/webm,video/ogg,video/quicktime"
        className="hidden"
        onChange={handleChange}
      />

      {!selectedVideo && (
        <Button
          size="sm"
          variant="light"
          startContent={<Video className="w-4 h-4" />}
          onPress={handleTrigger}
          className="text-[var(--text-muted)]"
        >
          {t('compose.video', 'Video')}
        </Button>
      )}

      {selectedVideo && (
        <div className="flex items-center gap-3 p-3 rounded-xl border border-[var(--border-default)] bg-[var(--surface-elevated)]">
          <div className="flex items-center justify-center w-10 h-10 rounded-lg bg-indigo-500/10">
            <Video className="w-5 h-5 text-indigo-500" />
          </div>
          <div className="flex-1 min-w-0">
            <p
              className="text-sm font-medium truncate"
              style={{ color: 'var(--text-primary)' }}
            >
              {selectedVideo.name}
            </p>
            <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
              {formatFileSize(selectedVideo.size)}
            </p>
          </div>
          <Button
            isIconOnly
            size="sm"
            variant="light"
            onPress={handleRemove}
            aria-label={t('compose.video_remove', 'Remove video')}
          >
            <X className="w-4 h-4" />
          </Button>
        </div>
      )}

      {error && (
        <div className="flex items-center gap-2 mt-2 text-xs text-red-500">
          <AlertCircle className="w-3.5 h-3.5 flex-shrink-0" />
          <span>{error}</span>
        </div>
      )}
    </div>
  );
}

export default VideoUploader;
