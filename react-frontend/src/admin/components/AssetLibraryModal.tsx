// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * AssetLibraryModal — browse + reuse the tenant's previously-uploaded images (and
 * upload a new one) for the newsletter builder. Picking an image hands its
 * absolute, email-safe URL back to the caller, which applies it to the current
 * target (hero background / image src / new mj-image) via the shared pipeline.
 *
 * A custom HeroUI modal rather than GrapesJS's default AssetManager (which depends
 * on Font Awesome glyphs this app doesn't ship — the same reason the toolbar is ours).
 */

import { useEffect, useRef, useState } from 'react';
import { Button, Modal, ModalBody, ModalContent, ModalHeader, Spinner } from '@/components/ui';
import Upload from 'lucide-react/icons/upload';
import { useToast } from '@/contexts';
import { logError } from '@/lib/logger';
import { adminNewsletters } from '../api/adminApi';
import { resolveUploadedUrl } from './builderImage';

interface LibraryImage {
  url: string;
  path: string;
  name: string;
}

interface AssetLibraryModalProps {
  isOpen: boolean;
  onClose: () => void;
  /** Called with the chosen image's absolute URL. */
  onSelect: (url: string) => void;
  t: (key: string) => string;
}

export function AssetLibraryModal({ isOpen, onClose, onSelect, t }: AssetLibraryModalProps) {
  const toast = useToast();
  const inputRef = useRef<HTMLInputElement>(null);
  const [images, setImages] = useState<LibraryImage[]>([]);
  const [loading, setLoading] = useState(false);
  const [uploading, setUploading] = useState(false);

  useEffect(() => {
    if (!isOpen) return;
    setLoading(true);
    adminNewsletters
      .listImages()
      .then((res) => {
        const imgs = res.success && res.data?.images ? res.data.images : [];
        setImages(imgs);
      })
      .catch((err) => {
        logError('AssetLibraryModal: failed to load images', err);
        toast.error(t('newsletter_builder.library_failed'));
      })
      .finally(() => setLoading(false));
  }, [isOpen, t, toast]);

  const pick = (url: string) => {
    onSelect(url);
    onClose();
  };

  const handleUpload = async (ev: React.ChangeEvent<HTMLInputElement>) => {
    const file = ev.target.files?.[0];
    ev.target.value = '';
    if (!file) return;
    setUploading(true);
    try {
      const url = resolveUploadedUrl(await adminNewsletters.uploadImage(file));
      if (url) pick(url);
      else toast.error(t('newsletter_content_editor.image_upload_failed'));
    } catch (err) {
      logError('AssetLibraryModal: upload failed', err);
      toast.error(t('newsletter_content_editor.image_upload_failed'));
    } finally {
      setUploading(false);
    }
  };

  return (
    <Modal isOpen={isOpen} onOpenChange={(open) => !open && onClose()} size="3xl" scrollBehavior="inside">
      <ModalContent>
        <ModalHeader className="flex items-center justify-between gap-4">
          <span>{t('newsletter_builder.library_title')}</span>
          <Button
            size="sm"
            variant="primary"
            startContent={<Upload size={15} />}
            isLoading={uploading}
            onPress={() => inputRef.current?.click()}
          >
            {t('newsletter_builder.library_upload')}
          </Button>
        </ModalHeader>
        <ModalBody className="min-h-[240px]">
          {loading ? (
            <div className="flex h-40 items-center justify-center" role="status" aria-busy="true">
              <Spinner size="sm" />
            </div>
          ) : images.length === 0 ? (
            <p className="px-4 py-10 text-center text-sm text-muted">{t('newsletter_builder.library_empty')}</p>
          ) : (
            <div className="grid grid-cols-3 gap-2 py-2 sm:grid-cols-4 md:grid-cols-5">
              {images.map((img) => (
                <button
                  key={img.path}
                  type="button"
                  onClick={() => pick(img.url)}
                  aria-label={img.name}
                  className="group aspect-square overflow-hidden rounded-lg border border-border bg-surface-secondary transition hover:border-accent focus:border-accent focus:outline-none"
                >
                  <img
                    src={img.url}
                    alt={img.name}
                    loading="lazy"
                    className="h-full w-full object-cover transition group-hover:scale-105"
                  />
                </button>
              ))}
            </div>
          )}
        </ModalBody>
      </ModalContent>
      <input
        ref={inputRef}
        type="file"
        accept="image/png,image/jpeg,image/gif,image/webp"
        className="hidden"
        onChange={handleUpload}
        aria-hidden="true"
        tabIndex={-1}
      />
    </Modal>
  );
}

export default AssetLibraryModal;
