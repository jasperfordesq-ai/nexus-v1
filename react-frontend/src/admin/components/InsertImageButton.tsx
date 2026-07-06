// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * InsertImageButton - upload an image to our own domain and hand back an
 * <img> tag to insert at the caller's cursor. Reused by raw HTML editors so
 * image hosting has a single path: POST /v2/upload.
 */

import { useRef, useState } from 'react';
import { Button, Tooltip } from '@/components/ui';
import ImagePlus from 'lucide-react/icons/image-plus';
import { useToast } from '@/contexts';
import { adminBuilderAssets } from '../api/adminApi';
import { resolveUploadedUrl } from './builderImage';
import { logError } from '@/lib/logger';

interface InsertImageButtonProps {
  /** Called with a ready-to-insert <img> tag using the uploaded same-domain URL. */
  onInsert: (imgTag: string) => void;
  isDisabled?: boolean;
  labels: {
    insertImage: string;
    uploadFailed: string;
  };
}

const ACCEPT = 'image/png,image/jpeg,image/gif,image/webp';

export function InsertImageButton({ onInsert, isDisabled, labels }: InsertImageButtonProps) {
  const toast = useToast();
  const inputRef = useRef<HTMLInputElement>(null);
  const [uploading, setUploading] = useState(false);

  const handleFile = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    e.target.value = ''; // allow re-selecting the same file
    if (!file) return;

    setUploading(true);
    try {
      // Only the absolute URL is inserted; relative upload paths break outside
      // the editor's current route context.
      const url = resolveUploadedUrl(await adminBuilderAssets.uploadImage(file));
      if (url) {
        onInsert(`<img src="${url}" alt="" style="max-width:100%;height:auto;" />`);
      } else {
        toast.error(labels.uploadFailed);
      }
    } catch (err) {
      logError('InsertImageButton: upload failed', err);
      toast.error(labels.uploadFailed);
    } finally {
      setUploading(false);
    }
  };

  return (
    <>
      <Tooltip content={labels.insertImage} size="sm" delay={500}>
        <Button
          size="sm"
          variant="tertiary"
          isDisabled={isDisabled || uploading}
          isLoading={uploading}
          onPress={() => inputRef.current?.click()}
          startContent={!uploading ? <ImagePlus size={15} /> : undefined}
          className="h-8 gap-1 text-xs px-2"
        >
          {labels.insertImage}
        </Button>
      </Tooltip>
      <input
        ref={inputRef}
        type="file"
        accept={ACCEPT}
        className="hidden"
        onChange={handleFile}
        aria-hidden="true"
        tabIndex={-1}
      />
    </>
  );
}

export default InsertImageButton;
