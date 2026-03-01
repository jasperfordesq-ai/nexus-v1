// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Team Documents Component (I6)
 *
 * Document management within a group/team workspace.
 * - File list with type icons, size, uploaded by
 * - Upload document button
 * - Delete documents (admin/owner)
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import {
  Button,
  Chip,
  Spinner,
  Avatar,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  useDisclosure,
} from '@heroui/react';
import {
  FileText,
  File,
  Image,
  Film,
  Music,
  Archive,
  Trash2,
  Download,
  Upload,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useAuth, useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl, resolveAssetUrl, formatRelativeTime } from '@/lib/helpers';

/* ───────────────────────── Types ───────────────────────── */

interface Document {
  id: number;
  group_id: number;
  user_id: number;
  filename: string;
  original_name: string;
  mime_type: string;
  size: number;
  url: string;
  created_at: string;
  uploader?: {
    id: number;
    name: string;
    avatar_url: string | null;
  } | null;
}

interface TeamDocumentsProps {
  groupId: number;
  isGroupAdmin: boolean;
}

/* ───────────────────────── Helpers ───────────────────────── */

function getFileIcon(mimeType: string) {
  if (mimeType.startsWith('image/')) return Image;
  if (mimeType.startsWith('video/')) return Film;
  if (mimeType.startsWith('audio/')) return Music;
  if (mimeType.includes('pdf') || mimeType.includes('document') || mimeType.includes('text'))
    return FileText;
  if (mimeType.includes('zip') || mimeType.includes('tar') || mimeType.includes('rar') || mimeType.includes('compressed'))
    return Archive;
  return File;
}

function formatFileSize(bytes: number): string {
  if (bytes === 0) return '0 B';
  const k = 1024;
  const sizes = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

function getFileExtension(filename: string): string {
  const parts = filename.split('.');
  return parts.length > 1 ? parts[parts.length - 1].toUpperCase() : '';
}

/* ───────────────────────── Main Component ───────────────────────── */

export function TeamDocuments({ groupId, isGroupAdmin }: TeamDocumentsProps) {
  const { t } = useTranslation('ideation');
  const { user } = useAuth();
  const toast = useToast();

  const [documents, setDocuments] = useState<Document[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isUploading, setIsUploading] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);

  // Delete confirmation
  const { isOpen: isDeleteOpen, onOpen: onDeleteOpen, onClose: onDeleteClose } = useDisclosure();
  const [deleteDocId, setDeleteDocId] = useState<number | null>(null);
  const [isDeleting, setIsDeleting] = useState(false);

  const isAdmin = isGroupAdmin || (user?.role && ['admin', 'tenant_admin', 'tenant_super_admin', 'super_admin'].includes(user.role));

  const fetchDocuments = useCallback(async () => {
    try {
      setIsLoading(true);
      const response = await api.get<Document[]>(`/v2/groups/${groupId}/documents`);
      if (response.success && response.data) {
        setDocuments(Array.isArray(response.data) ? response.data : []);
      }
    } catch (err) {
      logError('Failed to fetch documents', err);
    } finally {
      setIsLoading(false);
    }
  }, [groupId]);

  useEffect(() => {
    fetchDocuments();
  }, [fetchDocuments]);

  const handleUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    // 10 MB limit
    if (file.size > 10 * 1024 * 1024) {
      toast.error(t('documents.max_size'));
      return;
    }

    setIsUploading(true);
    try {
      const formData = new FormData();
      formData.append('file', file);

      await api.upload(`/v2/groups/${groupId}/documents`, formData);
      toast.success(t('toast.document_uploaded'));
      fetchDocuments();
    } catch (err) {
      logError('Failed to upload document', err);
      toast.error(t('toast.error_generic'));
    } finally {
      setIsUploading(false);
      // Reset file input
      if (fileInputRef.current) {
        fileInputRef.current.value = '';
      }
    }
  };

  const handleDeleteConfirm = (docId: number) => {
    setDeleteDocId(docId);
    onDeleteOpen();
  };

  const handleDelete = async () => {
    if (!deleteDocId) return;

    setIsDeleting(true);
    try {
      await api.delete(`/v2/team-documents/${deleteDocId}`);
      toast.success(t('toast.document_deleted'));
      setDocuments(prev => prev.filter(d => d.id !== deleteDocId));
      onDeleteClose();
    } catch (err) {
      logError('Failed to delete document', err);
      toast.error(t('toast.error_generic'));
    } finally {
      setIsDeleting(false);
      setDeleteDocId(null);
    }
  };

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex items-center justify-between">
        <h3 className="text-lg font-semibold text-[var(--color-text)] flex items-center gap-2">
          <FileText className="w-5 h-5" />
          {t('documents.title')}
        </h3>
        <div>
          <input
            ref={fileInputRef}
            type="file"
            className="hidden"
            onChange={handleUpload}
            accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.png,.jpg,.jpeg,.gif,.svg,.zip,.rar"
          />
          <Button
            color="primary"
            size="sm"
            isLoading={isUploading}
            startContent={<Upload className="w-4 h-4" />}
            onPress={() => fileInputRef.current?.click()}
          >
            {t('documents.upload')}
          </Button>
        </div>
      </div>

      <p className="text-xs text-[var(--color-text-tertiary)]">
        {t('documents.max_size')}
      </p>

      {/* Loading */}
      {isLoading && (
        <div className="flex justify-center py-8">
          <Spinner size="md" />
        </div>
      )}

      {/* Empty */}
      {!isLoading && documents.length === 0 && (
        <EmptyState
          icon={<FileText className="w-10 h-10 text-theme-subtle" />}
          title={t('documents.empty_title')}
          description={t('documents.empty_description')}
        />
      )}

      {/* Document List */}
      {!isLoading && documents.length > 0 && (
        <div className="space-y-2">
          {documents.map((doc) => {
            const IconComponent = getFileIcon(doc.mime_type);
            const ext = getFileExtension(doc.original_name);

            return (
              <GlassCard key={doc.id} className="p-3">
                <div className="flex items-center gap-3">
                  {/* File Icon */}
                  <div className="w-10 h-10 rounded-lg bg-[var(--color-surface-hover)] flex items-center justify-center shrink-0">
                    <IconComponent className="w-5 h-5 text-[var(--color-text-tertiary)]" />
                  </div>

                  {/* File Info */}
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium text-[var(--color-text)] truncate">
                      {doc.original_name}
                    </p>
                    <div className="flex items-center gap-3 text-xs text-[var(--color-text-tertiary)]">
                      {ext && (
                        <Chip size="sm" variant="flat" className="text-[10px] h-4">{ext}</Chip>
                      )}
                      <span>{formatFileSize(doc.size)}</span>
                      {doc.uploader && (
                        <span className="flex items-center gap-1">
                          <Avatar
                            src={resolveAvatarUrl(doc.uploader.avatar_url)}
                            size="sm"
                            className="w-3.5 h-3.5"
                            name={doc.uploader.name}
                          />
                          {doc.uploader.name}
                        </span>
                      )}
                      <span>{formatRelativeTime(doc.created_at)}</span>
                    </div>
                  </div>

                  {/* Actions */}
                  <div className="flex items-center gap-1 shrink-0">
                    <Button
                      isIconOnly
                      variant="light"
                      size="sm"
                      as="a"
                      href={resolveAssetUrl(doc.url)}
                      target="_blank"
                      rel="noopener noreferrer"
                      aria-label="Download"
                    >
                      <Download className="w-4 h-4 text-[var(--color-text-tertiary)]" />
                    </Button>
                    {(isAdmin || user?.id === doc.user_id) && (
                      <Button
                        isIconOnly
                        variant="light"
                        size="sm"
                        onPress={() => handleDeleteConfirm(doc.id)}
                        aria-label={t('comments.delete')}
                      >
                        <Trash2 className="w-4 h-4 text-[var(--color-text-tertiary)]" />
                      </Button>
                    )}
                  </div>
                </div>
              </GlassCard>
            );
          })}
        </div>
      )}

      {/* Delete Confirmation Modal */}
      <Modal isOpen={isDeleteOpen} onClose={onDeleteClose}>
        <ModalContent>
          <ModalHeader>{t('documents.title')}</ModalHeader>
          <ModalBody>
            <p className="text-[var(--color-text-secondary)]">
              {t('documents.delete_confirm')}
            </p>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onDeleteClose}>
              {t('form.cancel')}
            </Button>
            <Button
              color="danger"
              isLoading={isDeleting}
              onPress={handleDelete}
            >
              {t('comments.delete')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default TeamDocuments;
