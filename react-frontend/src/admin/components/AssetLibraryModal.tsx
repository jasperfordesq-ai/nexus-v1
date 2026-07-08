// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * AssetLibraryModal - browse and reuse tenant builder images, or upload a new
 * one. Picking an image returns its absolute URL; the caller decides whether to
 * replace the current image target or insert a fresh component.
 *
 * A custom HeroUI modal rather than GrapesJS's default AssetManager keeps the
 * toolbar consistent with the React admin UI while still sharing the upload API.
 */

import { useEffect, useRef, useState } from 'react';
import { Button, Modal, ModalBody, ModalContent, ModalHeader, Spinner } from '@/components/ui';
import Upload from 'lucide-react/icons/upload';
import { useToast } from '@/contexts';
import { logError } from '@/lib/logger';
import { responsiveThumbnailProps } from '@/lib/helpers';
import { adminBuilderAssets } from '../api/adminApi';
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
  labels: {
    title: string;
    upload: string;
    empty: string;
    loadFailed: string;
    uploadFailed: string;
  };
}

export function AssetLibraryModal({ isOpen, onClose, onSelect, labels }: AssetLibraryModalProps) {
  const toast = useToast();
  const inputRef = useRef<HTMLInputElement>(null);
  const [images, setImages] = useState<LibraryImage[]>([]);
  const [loading, setLoading] = useState(false);
  const [uploading, setUploading] = useState(false);

  useEffect(() => {
    if (!isOpen) return;
    setLoading(true);
    adminBuilderAssets
      .listImages()
      .then((res) => {
        const imgs = res.success && res.data?.images ? res.data.images : [];
        setImages(imgs);
      })
      .catch((err) => {
        logError('AssetLibraryModal: failed to load images', err);
        toast.error(labels.loadFailed);
      })
      .finally(() => setLoading(false));
  }, [isOpen, labels.loadFailed, toast]);

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
      const url = resolveUploadedUrl(await adminBuilderAssets.uploadImage(file));
      if (url) pick(url);
      else toast.error(labels.uploadFailed);
    } catch (err) {
      logError('AssetLibraryModal: upload failed', err);
      toast.error(labels.uploadFailed);
    } finally {
      setUploading(false);
    }
  };

  return (
    <Modal isOpen={isOpen} onOpenChange={(open) => !open && onClose()} size="3xl" scrollBehavior="inside">
      <ModalContent>
        <ModalHeader className="flex items-center justify-between gap-4">
          <span>{labels.title}</span>
          <Button
            size="sm"
            variant="primary"
            startContent={<Upload size={15} />}
            isLoading={uploading}
            onPress={() => inputRef.current?.click()}
          >
            {labels.upload}
          </Button>
        </ModalHeader>
        <ModalBody className="min-h-[240px]">
          {loading ? (
            <div className="flex h-40 items-center justify-center" role="status" aria-busy="true">
              <Spinner size="sm" />
            </div>
          ) : images.length === 0 ? (
            <p className="px-4 py-10 text-center text-sm text-muted">{labels.empty}</p>
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
                    {...responsiveThumbnailProps(img.url, {
                      width: 240,
                      height: 240,
                      sizes: '(min-width: 768px) 20vw, 30vw',
                    })}
                    alt={img.name}
                    loading="lazy"
                    decoding="async"
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
