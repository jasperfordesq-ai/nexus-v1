// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * TemplateGalleryModal — a thumbnailed picker for starting a newsletter from a
 * ready-made design. Replaces the plain template <Select>. Each card shows the
 * template's `thumbnail` when present, otherwise a scaled sandboxed-iframe live
 * mini-preview of its HTML (so the gallery works before thumbnails exist).
 *
 * Selecting a template hands back its content + content_format (and subject /
 * preview-text defaults), so a starter's raw HTML is loaded into the right
 * editor mode — never fed to the Lexical rich-text editor.
 */

import { useMemo, useState } from 'react';
import {
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  Card,
  CardBody,
  Button,
  Tabs,
  Tab,
} from '@/components/ui';
import { useTranslation } from 'react-i18next';
import type { ContentFormat } from './contentFormat';

export interface GalleryTemplate {
  id: number;
  name: string;
  description?: string;
  content?: string;
  content_format?: string;
  thumbnail?: string | null;
  subject?: string;
  preview_text?: string;
  category?: string;
}

interface TemplateGalleryModalProps {
  isOpen: boolean;
  onClose: () => void;
  templates: GalleryTemplate[];
  onSelect: (tpl: GalleryTemplate) => void;
}

type CategoryTab = 'starter' | 'saved' | 'custom';

export function TemplateGalleryModal({ isOpen, onClose, templates, onSelect }: TemplateGalleryModalProps) {
  const { t } = useTranslation('admin');
  const [tab, setTab] = useState<CategoryTab>('starter');

  const byCategory = useMemo(() => {
    const map: Record<CategoryTab, GalleryTemplate[]> = { starter: [], saved: [], custom: [] };
    for (const tpl of templates) {
      const cat = (tpl.category as CategoryTab) || 'custom';
      (map[cat] ?? map.custom).push(tpl);
    }
    return map;
  }, [templates]);

  const visible = byCategory[tab] ?? [];

  const handlePick = (tpl: GalleryTemplate) => {
    onSelect(tpl);
    onClose();
  };

  return (
    <Modal isOpen={isOpen} onOpenChange={(open) => !open && onClose()} size="5xl" scrollBehavior="inside">
      <ModalContent>
        <ModalHeader>{t('newsletter_content_editor.gallery_title')}</ModalHeader>
        <ModalBody className="pb-6">
          <Tabs
            selectedKey={tab}
            onSelectionChange={(key) => setTab(key as CategoryTab)}
            aria-label={t('newsletter_content_editor.gallery_title')}
            size="sm"
          >
            <Tab key="starter" id="starter" title={t('newsletter_content_editor.gallery_tab_starter')} />
            <Tab key="saved" id="saved" title={t('newsletter_content_editor.gallery_tab_saved')} />
            <Tab key="custom" id="custom" title={t('newsletter_content_editor.gallery_tab_custom')} />
          </Tabs>

          {visible.length === 0 ? (
            <p className="py-10 text-center text-sm text-muted">
              {t('newsletter_content_editor.gallery_empty')}
            </p>
          ) : (
            <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {visible.map((tpl) => (
                <Card key={tpl.id} className="overflow-hidden">
                  <CardBody className="gap-3 p-0">
                    {/* Preview */}
                    <div className="relative h-44 w-full overflow-hidden border-b border-border bg-surface-secondary">
                      {tpl.thumbnail ? (
                        <img
                          src={tpl.thumbnail}
                          alt={t('newsletter_content_editor.gallery_preview_alt')}
                          className="h-full w-full object-cover object-top"
                        />
                      ) : (
                        <div
                          className="pointer-events-none absolute left-0 top-0 origin-top-left"
                          style={{ width: 600, transform: 'scale(0.52)' }}
                        >
                          <iframe
                            title={t('newsletter_content_editor.gallery_preview_alt')}
                            srcDoc={tpl.content || ''}
                            sandbox=""
                            className="block bg-white"
                            style={{ width: 600, height: 700, border: 'none' }}
                            tabIndex={-1}
                            aria-hidden="true"
                          />
                        </div>
                      )}
                    </div>
                    {/* Meta + action */}
                    <div className="flex flex-1 flex-col gap-2 p-4">
                      <div>
                        <p className="text-sm font-semibold text-foreground">{tpl.name}</p>
                        {tpl.description && (
                          <p className="mt-1 line-clamp-2 text-xs text-muted">{tpl.description}</p>
                        )}
                      </div>
                      <Button
                        size="sm"
                        variant="secondary"
                        className="mt-auto w-full"
                        onPress={() => handlePick(tpl)}
                      >
                        {t('newsletter_content_editor.gallery_use')}
                      </Button>
                    </div>
                  </CardBody>
                </Card>
              ))}
            </div>
          )}
        </ModalBody>
      </ModalContent>
    </Modal>
  );
}

export default TemplateGalleryModal;
