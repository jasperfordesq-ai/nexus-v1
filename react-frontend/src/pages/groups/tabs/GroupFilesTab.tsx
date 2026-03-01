// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Group Files Tab (GR1)
 * File sharing within a group — upload, download, delete.
 * Drag-and-drop or file picker upload, list with type icons.
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
  Progress,
} from '@heroui/react';
import {
  Upload,
  Download,
  Trash2,
  FileText,
  FileImage,
  FileVideo,
  FileAudio,
  FileArchive,
  FileSpreadsheet,
  File,
  FolderOpen,
  CloudUpload,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useToast } from '@/contexts';
import { api, API_BASE } from '@/lib/api';
import { logError } from '@/lib/logger';
import { formatRelativeTime } from '@/lib/helpers';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface GroupFile {
  id: number;
  file_name: string;
  original_name: string;
  file_size: number;
  mime_type: string;
  uploaded_by: {
    id: number;
    name: string;
  };
  download_count?: number;
  created_at: string;
}

interface GroupFilesTabProps {
  groupId: number;
  isAdmin: boolean;
  isMember: boolean;
}

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function getFileIcon(mimeType: string) {
  if (mimeType.startsWith('image/')) return <FileImage className="w-5 h-5 text-blue-400" />;
  if (mimeType.startsWith('video/')) return <FileVideo className="w-5 h-5 text-purple-400" />;
  if (mimeType.startsWith('audio/')) return <FileAudio className="w-5 h-5 text-green-400" />;
  if (mimeType.includes('zip') || mimeType.includes('rar') || mimeType.includes('tar'))
    return <FileArchive className="w-5 h-5 text-amber-400" />;
  if (mimeType.includes('spreadsheet') || mimeType.includes('csv') || mimeType.includes('excel'))
    return <FileSpreadsheet className="w-5 h-5 text-emerald-400" />;
  if (mimeType.includes('pdf') || mimeType.includes('document') || mimeType.includes('text'))
    return <FileText className="w-5 h-5 text-red-400" />;
  return <File className="w-5 h-5 text-default-400" />;
}

function formatFileSize(bytes: number): string {
  if (bytes === 0) return '0 B';
  const k = 1024;
  const sizes = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return `${parseFloat((bytes / Math.pow(k, i)).toFixed(1))} ${sizes[i]}`;
}

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function GroupFilesTab({ groupId, isAdmin, isMember }: GroupFilesTabProps) {
  const toast = useToast();
  const fileInputRef = useRef<HTMLInputElement>(null);
  const dropZoneRef = useRef<HTMLDivElement>(null);

  const [files, setFiles] = useState<GroupFile[]>([]);
  const [loading, setLoading] = useState(true);
  const [uploading, setUploading] = useState(false);
  const [uploadProgress, setUploadProgress] = useState(0);
  const [isDragging, setIsDragging] = useState(false);
  const [deleteTarget, setDeleteTarget] = useState<GroupFile | null>(null);
  const [deleting, setDeleting] = useState(false);

  // ─── Load files ───
  const loadFiles = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get(`/api/v2/groups/${groupId}/files`);
      if (res.success) {
        const payload = res.data;
        setFiles(Array.isArray(payload) ? payload : (payload as { files?: GroupFile[] })?.files ?? []);
      }
    } catch (err) {
      logError('GroupFilesTab.loadFiles', err);
      toast.error('Failed to load files');
    }
    setLoading(false);
  }, [groupId, toast]);

  useEffect(() => { loadFiles(); }, [loadFiles]);

  // ─── Upload ───
  const handleUpload = useCallback(async (selectedFiles: FileList | File[]) => {
    if (!selectedFiles.length) return;
    setUploading(true);
    setUploadProgress(0);

    try {
      const totalFiles = selectedFiles.length;
      for (let i = 0; i < totalFiles; i++) {
        const file = selectedFiles[i];
        const formData = new FormData();
        formData.append('file', file);

        await api.upload(`/api/v2/groups/${groupId}/files`, formData);
        setUploadProgress(Math.round(((i + 1) / totalFiles) * 100));
      }
      toast.success(`${totalFiles} file(s) uploaded`);
      loadFiles();
    } catch (err) {
      logError('GroupFilesTab.upload', err);
      toast.error('Failed to upload file(s)');
    }

    setUploading(false);
    setUploadProgress(0);
  }, [groupId, toast, loadFiles]);

  // ─── Download ───
  const handleDownload = useCallback((file: GroupFile) => {
    const token = localStorage.getItem('nexus_access_token');
    const url = `${API_BASE}/api/v2/groups/${groupId}/files/${file.id}/download`;
    const link = document.createElement('a');
    link.href = url + (token ? `?token=${token}` : '');
    link.download = file.original_name;
    link.target = '_blank';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  }, [groupId]);

  // ─── Delete ───
  const handleDelete = useCallback(async () => {
    if (!deleteTarget) return;
    setDeleting(true);
    try {
      await api.delete(`/api/v2/groups/${groupId}/files/${deleteTarget.id}`);
      toast.success('File deleted');
      setFiles((prev) => prev.filter((f) => f.id !== deleteTarget.id));
      setDeleteTarget(null);
    } catch (err) {
      logError('GroupFilesTab.delete', err);
      toast.error('Failed to delete file');
    }
    setDeleting(false);
  }, [groupId, deleteTarget, toast]);

  // ─── Drag & drop ───
  const handleDragOver = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDragging(true);
  }, []);

  const handleDragLeave = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDragging(false);
  }, []);

  const handleDrop = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDragging(false);
    if (e.dataTransfer.files.length) {
      handleUpload(e.dataTransfer.files);
    }
  }, [handleUpload]);

  // ─── Render ───
  return (
    <GlassCard className="p-6">
      <div className="flex justify-between items-center mb-4">
        <h2 className="text-lg font-semibold text-theme-primary flex items-center gap-2">
          <FolderOpen className="w-5 h-5" aria-hidden="true" />
          Files
        </h2>
        {isMember && (
          <Button
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
            size="sm"
            startContent={<Upload className="w-4 h-4" />}
            isLoading={uploading}
            onPress={() => fileInputRef.current?.click()}
          >
            Upload
          </Button>
        )}
      </div>

      {/* Hidden file input */}
      <input
        ref={fileInputRef}
        type="file"
        multiple
        className="hidden"
        onChange={(e) => e.target.files && handleUpload(e.target.files)}
      />

      {/* Upload progress */}
      {uploading && (
        <div className="mb-4">
          <Progress
            value={uploadProgress}
            color="primary"
            size="sm"
            className="max-w-full"
            aria-label="Upload progress"
          />
          <p className="text-xs text-theme-subtle mt-1">Uploading... {uploadProgress}%</p>
        </div>
      )}

      {/* Drop zone (when member) */}
      {isMember && (
        <div
          ref={dropZoneRef}
          onDragOver={handleDragOver}
          onDragLeave={handleDragLeave}
          onDrop={handleDrop}
          className={`mb-4 border-2 border-dashed rounded-xl p-8 text-center transition-colors ${
            isDragging
              ? 'border-primary bg-primary/5'
              : 'border-theme-default hover:border-theme-hover'
          }`}
        >
          <CloudUpload className={`w-10 h-10 mx-auto mb-2 ${isDragging ? 'text-primary' : 'text-theme-subtle'}`} />
          <p className="text-sm text-theme-subtle">
            Drag and drop files here, or{' '}
            <button
              type="button"
              className="text-primary hover:underline"
              onClick={() => fileInputRef.current?.click()}
            >
              browse
            </button>
          </p>
        </div>
      )}

      {/* File list */}
      {loading ? (
        <div className="flex justify-center py-8">
          <Spinner size="lg" />
        </div>
      ) : files.length === 0 ? (
        <EmptyState
          icon={<FolderOpen className="w-12 h-12" />}
          title="No files yet"
          description={isMember ? 'Upload files to share with the group' : 'No files have been shared in this group'}
        />
      ) : (
        <div className="space-y-2">
          {files.map((file) => (
            <div
              key={file.id}
              className="flex items-center gap-3 p-3 rounded-lg bg-theme-elevated hover:bg-theme-hover transition-colors"
            >
              <div className="flex-shrink-0 w-10 h-10 rounded-lg bg-theme-hover flex items-center justify-center">
                {getFileIcon(file.mime_type)}
              </div>

              <div className="flex-1 min-w-0">
                <p className="font-medium text-theme-primary text-sm truncate">
                  {file.original_name}
                </p>
                <div className="flex items-center gap-2 text-xs text-theme-subtle">
                  <span>{formatFileSize(file.file_size)}</span>
                  <span className="text-theme-muted">·</span>
                  <span>{file.uploaded_by.name}</span>
                  <span className="text-theme-muted">·</span>
                  <span>{formatRelativeTime(file.created_at)}</span>
                </div>
              </div>

              {file.download_count !== undefined && (
                <Chip size="sm" variant="flat" className="bg-theme-hover text-theme-subtle">
                  {file.download_count} downloads
                </Chip>
              )}

              <div className="flex items-center gap-1">
                <Button
                  isIconOnly
                  variant="light"
                  size="sm"
                  aria-label="Download file"
                  onPress={() => handleDownload(file)}
                >
                  <Download className="w-4 h-4" />
                </Button>
                {(isAdmin || file.uploaded_by.id === parseInt(localStorage.getItem('nexus_user_id') || '0')) && (
                  <Button
                    isIconOnly
                    variant="light"
                    size="sm"
                    color="danger"
                    aria-label="Delete file"
                    onPress={() => setDeleteTarget(file)}
                  >
                    <Trash2 className="w-4 h-4" />
                  </Button>
                )}
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Delete confirmation modal */}
      <Modal
        isOpen={!!deleteTarget}
        onOpenChange={(open) => !open && setDeleteTarget(null)}
        classNames={{
          base: 'bg-content1 border border-theme-default',
          header: 'border-b border-theme-default',
          footer: 'border-t border-theme-default',
        }}
      >
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader className="text-theme-primary">Delete File</ModalHeader>
              <ModalBody>
                <p className="text-theme-secondary">
                  Are you sure you want to delete <strong>{deleteTarget?.original_name}</strong>? This action cannot be undone.
                </p>
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose}>Cancel</Button>
                <Button color="danger" isLoading={deleting} onPress={handleDelete}>Delete</Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </GlassCard>
  );
}

export default GroupFilesTab;
