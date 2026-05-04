// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Group Files Tab (GR1)
 * File upload, download, folder organization within a group.
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import {
  Button,
  Spinner,
  Chip,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Input,
  Textarea,
  Dropdown,
  DropdownTrigger,
  DropdownMenu,
  DropdownItem,
  useDisclosure,
} from '@heroui/react';
import FolderOpen from 'lucide-react/icons/folder-open';
import Upload from 'lucide-react/icons/upload';
import Download from 'lucide-react/icons/download';
import Trash2 from 'lucide-react/icons/trash-2';
import File from 'lucide-react/icons/file';
import FileText from 'lucide-react/icons/file-text';
import FileImage from 'lucide-react/icons/file-image';
import FileVideo from 'lucide-react/icons/file-video';
import FileAudio from 'lucide-react/icons/file-audio';
import FileArchive from 'lucide-react/icons/file-archive';
import Search from 'lucide-react/icons/search';
import FolderPlus from 'lucide-react/icons/folder-plus';
import MoreVertical from 'lucide-react/icons/ellipsis-vertical';
import X from 'lucide-react/icons/x';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { formatRelativeTime } from '@/lib/helpers';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface GroupFile {
  id: number;
  group_id: number;
  file_name: string;
  file_path: string;
  file_type: string;
  file_size: number;
  uploaded_by: number;
  uploader_name: string;
  uploader_avatar: string | null;
  folder: string | null;
  description: string | null;
  download_count?: number;
  created_at: string;
}

interface Folder {
  folder: string;
  file_count: number;
}

interface GroupFilesTabProps {
  groupId: number;
  isAdmin: boolean;
  isMember?: boolean;
  currentUserId?: number;
}

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function formatFileSize(bytes: number): string {
  if (bytes === 0) return '0 B';
  const units = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(1024));
  return `${(bytes / Math.pow(1024, i)).toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
}

function getFileIcon(mimeType: string) {
  if (mimeType.startsWith('image/')) return FileImage;
  if (mimeType.startsWith('video/')) return FileVideo;
  if (mimeType.startsWith('audio/')) return FileAudio;
  if (mimeType.includes('pdf') || mimeType.includes('document') || mimeType.includes('text'))
    return FileText;
  if (mimeType.includes('zip') || mimeType.includes('rar') || mimeType.includes('archive'))
    return FileArchive;
  return File;
}

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function GroupFilesTab({ groupId, isAdmin, isMember = true, currentUserId }: GroupFilesTabProps) {
  const { t } = useTranslation('groups');
  const toast = useToast();
  const uploadModal = useDisclosure();
  const fileInputRef = useRef<HTMLInputElement>(null);

  const [files, setFiles] = useState<GroupFile[]>([]);
  const [folders, setFolders] = useState<Folder[]>([]);
  const [loading, setLoading] = useState(true);
  const [uploading, setUploading] = useState(false);
  const [cursor, setCursor] = useState<string | null>(null);
  const [hasMore, setHasMore] = useState(false);
  const [search, setSearch] = useState('');
  const [activeFolder, setActiveFolder] = useState<string | null>(null);
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [uploadFolder, setUploadFolder] = useState('');
  const [uploadDescription, setUploadDescription] = useState('');
  const [deleting, setDeleting] = useState<number | null>(null);

  const loadFiles = useCallback(
    async (reset = false) => {
      try {
        if (reset) setLoading(true);

        const params = new URLSearchParams({ per_page: '20' });
        if (!reset && cursor) params.set('cursor', cursor);
        if (activeFolder) params.set('folder', activeFolder);
        if (search.trim()) params.set('q', search.trim());

        const resp = await api.get(`/v2/groups/${groupId}/files?${params}`);
        const data = (resp.data ?? {}) as { items?: GroupFile[]; cursor?: string | null; has_more?: boolean };

        if (reset) {
          setFiles(data.items ?? []);
        } else {
          setFiles((prev) => [...prev, ...(data.items ?? [])]);
        }
        setCursor(data.cursor ?? null);
        setHasMore(data.has_more ?? false);
      } catch (err) {
        logError('GroupFilesTab.loadFiles', err);
      } finally {
        setLoading(false);
      }
    },
    [groupId, cursor, activeFolder, search],
  );

  const loadFolders = useCallback(async () => {
    try {
      const resp = await api.get(`/v2/groups/${groupId}/files/folders`);
      setFolders((resp.data || []) as Folder[]);
    } catch (err) {
      logError('GroupFilesTab.loadFolders', err);
    }
  }, [groupId]);

  useEffect(() => {
    loadFiles(true);
    loadFolders();
  }, [groupId, activeFolder]); // eslint-disable-line react-hooks/exhaustive-deps

  // Debounced search
  useEffect(() => {
    const timeout = setTimeout(() => loadFiles(true), 300);
    return () => clearTimeout(timeout);
  }, [search]); // eslint-disable-line react-hooks/exhaustive-deps

  const handleUpload = async () => {
    if (!selectedFile) return;
    setUploading(true);

    try {
      const formData = new FormData();
      formData.append('file', selectedFile);
      if (uploadFolder.trim()) formData.append('folder', uploadFolder.trim());
      if (uploadDescription.trim()) formData.append('description', uploadDescription.trim());

      await api.upload(`/v2/groups/${groupId}/files`, formData);

      toast.success(t('files.upload_success', 'File uploaded successfully'));
      setSelectedFile(null);
      setUploadFolder('');
      setUploadDescription('');
      uploadModal.onClose();
      loadFiles(true);
      loadFolders();
    } catch (err) {
      logError('GroupFilesTab.upload', err);
      toast.error(t('files.upload_error', 'Failed to upload file'));
    } finally {
      setUploading(false);
    }
  };

  const handleDownload = async (file: GroupFile) => {
    try {
      await api.download(`/v2/groups/${groupId}/files/${file.id}/download`, {
        filename: file.file_name,
      });
    } catch (err) {
      logError('GroupFilesTab.download', err);
      toast.error(t('files.download_error', 'Failed to download file'));
    }
  };

  const handleDelete = async (fileId: number) => {
    setDeleting(fileId);
    try {
      await api.delete(`/v2/groups/${groupId}/files/${fileId}`);
      setFiles((prev) => prev.filter((f) => f.id !== fileId));
      toast.success(t('files.delete_success', 'File deleted'));
      loadFolders();
    } catch (err) {
      logError('GroupFilesTab.delete', err);
      toast.error(t('files.delete_error', 'Failed to delete file'));
    } finally {
      setDeleting(null);
    }
  };

  const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      if (file.size > 25 * 1024 * 1024) {
        toast.error(t('files.too_large', 'File exceeds 25MB limit'));
        return;
      }
      setSelectedFile(file);
      uploadModal.onOpen();
    }
  };

  // ─────────────────────────────────────────────────────────────────────
  // Render
  // ─────────────────────────────────────────────────────────────────────

  if (loading && files.length === 0) {
    return (
      <div className="flex justify-center py-12" aria-label={t('files.loading', 'Loading files')} aria-busy="true">
        <Spinner size="lg" />
      </div>
    );
  }

  return (
    <div className="space-y-4">
      {/* Toolbar */}
      <div className="flex flex-col sm:flex-row sm:items-center gap-3">
        <Input
          placeholder={t('files.search_placeholder', 'Search files...')}
          value={search}
          onValueChange={setSearch}
          startContent={<Search className="w-4 h-4 text-default-400" aria-hidden="true" />}
          className="flex-1"
          size="sm"
          aria-label={t('files.search_aria', 'Search files')}
        />
        {isMember && (
          <Button
            color="primary"
            size="sm"
            startContent={<Upload className="w-4 h-4" aria-hidden="true" />}
            onPress={() => fileInputRef.current?.click()}
          >
            {t('files.upload', 'Upload File')}
          </Button>
        )}
        <input
          ref={fileInputRef}
          type="file"
          className="hidden"
          onChange={handleFileSelect}
          aria-hidden="true"
        />
      </div>

      {/* Folder chips */}
      {folders.length > 0 && (
        <div className="flex flex-wrap gap-2">
          <Chip
            variant={activeFolder === null ? 'solid' : 'bordered'}
            color="primary"
            className="cursor-pointer"
            onClick={() => setActiveFolder(null)}
          >
            {t('files.all_files', 'All Files')}
          </Chip>
          {folders.map((folder) => (
            <Chip
              key={folder.folder}
              variant={activeFolder === folder.folder ? 'solid' : 'bordered'}
              color="primary"
              className="cursor-pointer"
              onClick={() => setActiveFolder(folder.folder)}
            >
              <FolderOpen className="w-3 h-3 mr-1 inline" aria-hidden="true" />
              {folder.folder} ({folder.file_count})
            </Chip>
          ))}
        </div>
      )}

      {/* File list */}
      {files.length === 0 ? (
        <EmptyState
          icon={<FolderOpen className="w-12 h-12" aria-hidden="true" />}
          title={t('files.empty_title', 'No files yet')}
          description={
            search
              ? t('files.no_results', 'No files match your search')
              : t('files.empty_description', 'Upload files to share with group members')
          }
        />
      ) : (
        <div className="space-y-2">
          {files.map((file) => {
            const FileIcon = getFileIcon(file.file_type);
            return (
              <GlassCard key={file.id} className="p-3">
                <div className="flex items-center gap-3">
                  <div className="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center flex-shrink-0">
                    <FileIcon className="w-5 h-5 text-primary" aria-hidden="true" />
                  </div>

                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium text-theme-primary truncate">
                      {file.file_name}
                    </p>
                    <div className="flex items-center gap-2 text-xs text-theme-subtle">
                      <span>{formatFileSize(file.file_size)}</span>
                      <span aria-hidden="true">&#183;</span>
                      <span>{file.uploader_name}</span>
                      <span aria-hidden="true">&#183;</span>
                      <span>{formatRelativeTime(file.created_at)}</span>
                      {file.folder && (
                        <>
                          <span aria-hidden="true">&#183;</span>
                          <Chip size="sm" variant="flat" className="text-xs h-5">
                            {file.folder}
                          </Chip>
                        </>
                      )}
                    </div>
                  </div>

                  <div className="flex items-center gap-1 flex-shrink-0">
                    <Button
                      isIconOnly
                      variant="light"
                      size="sm"
                      onPress={() => handleDownload(file)}
                      aria-label={t('files.download_aria', { name: file.file_name })}
                    >
                      <Download className="w-4 h-4" />
                    </Button>

                    {(isAdmin || file.uploaded_by === currentUserId) && (
                      <Dropdown>
                        <DropdownTrigger>
                          <Button
                            isIconOnly
                            variant="light"
                            size="sm"
                            aria-label={t('files.actions_aria', 'File actions')}
                          >
                            <MoreVertical className="w-4 h-4" />
                          </Button>
                        </DropdownTrigger>
                        <DropdownMenu>
                          <DropdownItem
                            key="delete"
                            className="text-danger"
                            color="danger"
                            startContent={<Trash2 className="w-4 h-4" />}
                            onPress={() => handleDelete(file.id)}
                            isDisabled={deleting === file.id}
                          >
                            {deleting === file.id
                              ? t('files.deleting', 'Deleting...')
                              : t('files.delete', 'Delete')}
                          </DropdownItem>
                        </DropdownMenu>
                      </Dropdown>
                    )}
                  </div>
                </div>
              </GlassCard>
            );
          })}
        </div>
      )}

      {/* Load more */}
      {hasMore && (
        <div className="flex justify-center pt-4">
          <Button variant="flat" size="sm" onPress={() => loadFiles(false)} isLoading={loading}>
            {t('files.load_more', 'Load More')}
          </Button>
        </div>
      )}

      {/* Upload modal */}
      <Modal isOpen={uploadModal.isOpen} onClose={uploadModal.onClose} size="md">
        <ModalContent>
          <ModalHeader>{t('files.upload_title', 'Upload File')}</ModalHeader>
          <ModalBody>
            {selectedFile && (
              <div className="space-y-4">
                <div className="flex items-center gap-3 p-3 bg-default-100 rounded-lg">
                  <File className="w-8 h-8 text-primary" aria-hidden="true" />
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium truncate">{selectedFile.name}</p>
                    <p className="text-xs text-theme-subtle">{formatFileSize(selectedFile.size)}</p>
                  </div>
                  <Button
                    isIconOnly
                    variant="light"
                    size="sm"
                    onPress={() => setSelectedFile(null)}
                    aria-label={t('files.remove_file', 'Remove file')}
                  >
                    <X className="w-4 h-4" />
                  </Button>
                </div>

                <Input
                  label={t('files.folder_label', 'Folder (optional)')}
                  placeholder={t('files.folder_placeholder', 'e.g. Documents, Photos')}
                  value={uploadFolder}
                  onValueChange={setUploadFolder}
                  startContent={<FolderPlus className="w-4 h-4 text-default-400" aria-hidden="true" />}
                  size="sm"
                />

                <Textarea
                  label={t('files.description_label', 'Description (optional)')}
                  placeholder={t('files.description_placeholder', 'Brief description of the file')}
                  value={uploadDescription}
                  onValueChange={setUploadDescription}
                  minRows={2}
                  size="sm"
                />
              </div>
            )}
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={uploadModal.onClose}>
              {t('files.cancel', 'Cancel')}
            </Button>
            <Button
              color="primary"
              onPress={handleUpload}
              isLoading={uploading}
              isDisabled={!selectedFile}
            >
              {t('files.upload_btn', 'Upload')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default GroupFilesTab;
