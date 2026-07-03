// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * InsertImageButton — upload an image to our own domain and hand back an
 * <img> tag to insert at the caller's cursor. Reused by the HTML source editor
 * (Phase 1) and the GrapesJS asset manager (Phase 2), so image hosting has a
 * single path: POST /v2/upload (same-domain URL, server-side html/svg block).
 */

import { useRef, useState } from 'react';
import { Button, Tooltip } from '@/components/ui';
import ImagePlus from 'lucide-react/icons/image-plus';
import { useTranslation } from 'react-i18next';
import { useToast } from '@/contexts';
import { adminNewsletters } from '../api/adminApi';
import { logError } from '@/lib/logger';

interface InsertImageButtonProps {
  /** Called with a ready-to-insert <img> tag using the uploaded same-domain URL. */
  onInsert: (imgTag: string) => void;
  isDisabled?: boolean;
}

const ACCEPT = 'image/png,image/jpeg,image/gif,image/webp';

export function InsertImageButton({ onInsert, isDisabled }: InsertImageButtonProps) {
  const { t } = useTranslation('admin');
  const toast = useToast();
  const inputRef = useRef<HTMLInputElement>(null);
  const [uploading, setUploading] = useState(false);

  const handleFile = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    e.target.value = ''; // allow re-selecting the same file
    if (!file) return;

    setUploading(true);
    try {
      const res = await adminNewsletters.uploadImage(file);
      if (res.success && res.data) {
        const data = res.data as { url?: string; path?: string };
        const src = data.url || data.path;
        if (src) {
          onInsert(`<img src="${src}" alt="" style="max-width:100%;height:auto;" />`);
        } else {
          toast.error(t('newsletter_content_editor.image_upload_failed'));
        }
      } else {
        toast.error(t('newsletter_content_editor.image_upload_failed'));
      }
    } catch (err) {
      logError('InsertImageButton: upload failed', err);
      toast.error(t('newsletter_content_editor.image_upload_failed'));
    } finally {
      setUploading(false);
    }
  };

  return (
    <>
      <Tooltip content={t('newsletter_content_editor.insert_image')} size="sm" delay={500}>
        <Button
          size="sm"
          variant="tertiary"
          isDisabled={isDisabled || uploading}
          isLoading={uploading}
          onPress={() => inputRef.current?.click()}
          startContent={!uploading ? <ImagePlus size={15} /> : undefined}
          className="h-8 gap-1 text-xs px-2"
        >
          {t('newsletter_content_editor.insert_image')}
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
