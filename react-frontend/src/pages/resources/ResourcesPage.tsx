// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Resources Page - Community shared resources library
 *
 * Uses V2 API: GET /api/v2/resources, GET /api/v2/resources/categories
 * Upload: POST /api/v2/resources (multipart form data)
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { motion } from 'framer-motion';
import {
  Button,
  Input,
  Textarea,
  Select,
  SelectItem,
  Chip,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Progress,
  useDisclosure,
} from '@heroui/react';
import {
  FolderOpen,
  RefreshCw,
  AlertTriangle,
  Search,
  Download,
  FileText,
  FileSpreadsheet,
  FileImage,
  File,
  Calendar,
  User,
  Upload,
  X,
  CheckCircle,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useAuth, useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { formatRelativeTime } from '@/lib/helpers';
import { usePageTitle } from '@/hooks';

/* ───────────────────────── Types ───────────────────────── */

interface Resource {
  id: number;
  title: string;
  description: string;
  file_url: string;
  file_path: string;
  created_at: string;
  uploader: {
    id: number;
    name: string;
    avatar: string | null;
  };
  category: {
    id: number;
    name: string;
    color: string;
  } | null;
}

interface ResourceCategory {
  id: number;
  name: string;
  slug: string;
  color: string;
  resource_count: number;
}

/* ───────────────────────── Constants ───────────────────────── */

const ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv', 'jpg', 'png', 'gif', 'svg'];
const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB

/* ───────────────────────── Helpers ───────────────────────── */

function getFileIcon(path: string) {
  const ext = path.split('.').pop()?.toLowerCase() || '';

  if (['pdf'].includes(ext)) return <FileText className="w-5 h-5 text-red-400" aria-hidden="true" />;
  if (['doc', 'docx', 'txt', 'rtf', 'odt'].includes(ext)) return <FileText className="w-5 h-5 text-blue-400" aria-hidden="true" />;
  if (['xls', 'xlsx', 'csv', 'ods'].includes(ext)) return <FileSpreadsheet className="w-5 h-5 text-emerald-400" aria-hidden="true" />;
  if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'].includes(ext)) return <FileImage className="w-5 h-5 text-purple-400" aria-hidden="true" />;

  return <File className="w-5 h-5 text-gray-400" aria-hidden="true" />;
}

function getFileExtension(path: string): string {
  return path.split('.').pop()?.toUpperCase() || 'FILE';
}

function formatFileSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

/* ───────────────────────── Main Component ───────────────────────── */

export function ResourcesPage() {
  usePageTitle('Resources');
  const { isAuthenticated } = useAuth();
  const toast = useToast();
  const [resources, setResources] = useState<Resource[]>([]);
  const [categories, setCategories] = useState<ResourceCategory[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedCategory, setSelectedCategory] = useState<number | null>(null);
  const [hasMore, setHasMore] = useState(false);
  const [cursor, setCursor] = useState<string | undefined>();
  const [isLoadingMore, setIsLoadingMore] = useState(false);

  // Upload modal state
  const uploadModal = useDisclosure();
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [uploadTitle, setUploadTitle] = useState('');
  const [uploadDescription, setUploadDescription] = useState('');
  const [uploadCategoryId, setUploadCategoryId] = useState<string>('');
  const [uploadFile, setUploadFile] = useState<File | null>(null);
  const [isDragging, setIsDragging] = useState(false);
  const [isUploading, setIsUploading] = useState(false);
  const [uploadProgress, setUploadProgress] = useState(0);

  // Load categories on mount
  useEffect(() => {
    const loadCategories = async () => {
      try {
        const response = await api.get<ResourceCategory[]>('/v2/resources/categories');
        if (response.success && response.data) {
          setCategories(Array.isArray(response.data) ? response.data : []);
        }
      } catch (err) {
        logError('Failed to load resource categories', err);
      }
    };
    loadCategories();
  }, []);

  const loadResources = useCallback(async (append = false) => {
    try {
      if (append) {
        setIsLoadingMore(true);
      } else {
        setIsLoading(true);
        setError(null);
      }

      const params = new URLSearchParams();
      params.set('per_page', '20');
      if (append && cursor) params.set('cursor', cursor);
      if (searchQuery.trim()) params.set('search', searchQuery.trim());
      if (selectedCategory) params.set('category_id', String(selectedCategory));

      const response = await api.get<Resource[]>(
        `/v2/resources?${params}`
      );

      if (response.success && response.data) {
        const items = Array.isArray(response.data) ? response.data : [];

        if (append) {
          setResources((prev) => [...prev, ...items]);
        } else {
          setResources(items);
        }
        setHasMore(response.meta?.has_more ?? false);
        setCursor(response.meta?.cursor ?? undefined);
      } else {
        if (!append) setError('Failed to load resources.');
      }
    } catch (err) {
      logError('Failed to load resources', err);
      if (!append) setError('Failed to load resources. Please try again.');
    } finally {
      setIsLoading(false);
      setIsLoadingMore(false);
    }
  }, [cursor, searchQuery, selectedCategory]);

  useEffect(() => {
    setCursor(undefined);
    loadResources();
  }, [searchQuery, selectedCategory, loadResources]);

  // ─── Upload Handlers ────────────────────────────────────────────────

  function validateFile(file: File): string | null {
    const ext = file.name.split('.').pop()?.toLowerCase() || '';
    if (!ALLOWED_EXTENSIONS.includes(ext)) {
      return `File type .${ext} is not allowed. Supported: ${ALLOWED_EXTENSIONS.join(', ')}`;
    }
    if (file.size > MAX_FILE_SIZE) {
      return `File is too large (${formatFileSize(file.size)}). Maximum size is 10MB.`;
    }
    return null;
  }

  function handleFileSelect(file: File) {
    const validationError = validateFile(file);
    if (validationError) {
      toast.error('Invalid file', validationError);
      return;
    }
    setUploadFile(file);
  }

  function handleFileInputChange(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0];
    if (file) handleFileSelect(file);
    // Reset input so the same file can be re-selected
    if (fileInputRef.current) fileInputRef.current.value = '';
  }

  function handleDragOver(e: React.DragEvent) {
    e.preventDefault();
    e.stopPropagation();
    setIsDragging(true);
  }

  function handleDragLeave(e: React.DragEvent) {
    e.preventDefault();
    e.stopPropagation();
    setIsDragging(false);
  }

  function handleDrop(e: React.DragEvent) {
    e.preventDefault();
    e.stopPropagation();
    setIsDragging(false);
    const file = e.dataTransfer.files?.[0];
    if (file) handleFileSelect(file);
  }

  function resetUploadForm() {
    setUploadTitle('');
    setUploadDescription('');
    setUploadCategoryId('');
    setUploadFile(null);
    setUploadProgress(0);
    setIsUploading(false);
  }

  async function handleUploadSubmit() {
    if (!uploadFile) {
      toast.error('No file selected', 'Please select a file to upload');
      return;
    }
    if (!uploadTitle.trim()) {
      toast.error('Title required', 'Please enter a title for the resource');
      return;
    }

    try {
      setIsUploading(true);
      setUploadProgress(10);

      const formData = new FormData();
      formData.append('title', uploadTitle.trim());
      formData.append('description', uploadDescription.trim());
      if (uploadCategoryId) formData.append('category_id', uploadCategoryId);
      formData.append('file', uploadFile);

      // Simulate progress since the upload method doesn't provide real progress
      const progressInterval = setInterval(() => {
        setUploadProgress((prev) => Math.min(prev + 15, 85));
      }, 500);

      const response = await api.upload<Resource>('/v2/resources', formData);

      clearInterval(progressInterval);
      setUploadProgress(100);

      if (response.success) {
        toast.success('Resource uploaded', 'Your resource has been shared with the community');
        uploadModal.onClose();
        resetUploadForm();
        // Reload resources list
        setCursor(undefined);
        loadResources();
      } else {
        toast.error('Upload failed', response.error || 'Failed to upload resource. Please try again.');
      }
    } catch (err) {
      logError('Failed to upload resource', err);
      toast.error('Upload failed', 'Failed to upload resource. Please try again.');
    } finally {
      setIsUploading(false);
      setUploadProgress(0);
    }
  }

  const categoryColorMap: Record<string, string> = {
    blue: 'bg-blue-500/10 text-blue-500',
    gray: 'bg-gray-500/10 text-gray-500',
    fuchsia: 'bg-fuchsia-500/10 text-fuchsia-500',
    purple: 'bg-purple-500/10 text-purple-500',
    green: 'bg-emerald-500/10 text-emerald-500',
    red: 'bg-rose-500/10 text-rose-500',
    yellow: 'bg-amber-500/10 text-amber-500',
  };

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: { opacity: 1, transition: { staggerChildren: 0.05 } },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 20 },
    visible: { opacity: 1, y: 0 },
  };

  const inputClassNames = {
    input: 'bg-transparent text-theme-primary',
    inputWrapper: 'bg-theme-elevated border-theme-default',
    label: 'text-theme-muted',
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between flex-wrap gap-4">
        <div>
          <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-3">
            <FolderOpen className="w-7 h-7 text-amber-400" aria-hidden="true" />
            Resources
          </h1>
          <p className="text-theme-muted mt-1">Shared documents and files for the community</p>
        </div>

        {isAuthenticated && (
          <Button
            className="bg-gradient-to-r from-amber-500 to-orange-600 text-white"
            startContent={<Upload className="w-4 h-4" aria-hidden="true" />}
            onPress={uploadModal.onOpen}
          >
            Upload Resource
          </Button>
        )}
      </div>

      {/* Search & Filters */}
      <div className="flex flex-col sm:flex-row gap-4">
        <div className="flex-1 max-w-md">
          <Input
            placeholder="Search resources..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            startContent={<Search className="w-4 h-4 text-theme-muted" aria-hidden="true" />}
            classNames={{
              input: 'bg-transparent text-theme-primary',
              inputWrapper: 'bg-theme-elevated border-theme-default',
            }}
          />
        </div>

        {categories.length > 0 && (
          <div className="flex gap-2 flex-wrap">
            <Button
              size="sm"
              variant={!selectedCategory ? 'solid' : 'flat'}
              className={!selectedCategory ? 'bg-gradient-to-r from-amber-500 to-orange-600 text-white' : 'bg-theme-elevated text-theme-muted'}
              onPress={() => setSelectedCategory(null)}
            >
              All
            </Button>
            {categories.map((cat) => (
              <Button
                key={cat.id}
                size="sm"
                variant={selectedCategory === cat.id ? 'solid' : 'flat'}
                className={
                  selectedCategory === cat.id
                    ? 'bg-gradient-to-r from-amber-500 to-orange-600 text-white'
                    : 'bg-theme-elevated text-theme-muted'
                }
                onPress={() => setSelectedCategory(cat.id)}
              >
                {cat.name}
              </Button>
            ))}
          </div>
        )}
      </div>

      {/* Error */}
      {error && !isLoading && (
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary mb-2">Unable to Load Resources</h2>
          <p className="text-theme-muted mb-4">{error}</p>
          <Button
            className="bg-gradient-to-r from-amber-500 to-orange-600 text-white"
            startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
            onPress={() => loadResources()}
          >
            Try Again
          </Button>
        </GlassCard>
      )}

      {/* Resources List */}
      {!error && (
        <>
          {isLoading ? (
            <div className="space-y-3">
              {[1, 2, 3, 4, 5].map((i) => (
                <GlassCard key={i} className="p-4 animate-pulse">
                  <div className="flex items-center gap-4">
                    <div className="w-10 h-10 rounded-lg bg-theme-hover" />
                    <div className="flex-1">
                      <div className="h-4 bg-theme-hover rounded w-1/3 mb-2" />
                      <div className="h-3 bg-theme-hover rounded w-2/3" />
                    </div>
                    <div className="w-20 h-8 bg-theme-hover rounded" />
                  </div>
                </GlassCard>
              ))}
            </div>
          ) : resources.length === 0 ? (
            <EmptyState
              icon={<FolderOpen className="w-12 h-12" aria-hidden="true" />}
              title="No resources found"
              description={
                searchQuery || selectedCategory
                  ? 'Try different search terms or clear your filters'
                  : 'No resources have been shared yet'
              }
            />
          ) : (
            <motion.div
              variants={containerVariants}
              initial="hidden"
              animate="visible"
              className="space-y-3"
            >
              {resources.map((resource) => (
                <motion.div key={resource.id} variants={itemVariants}>
                  <GlassCard className="p-4 hover:bg-theme-hover/30 transition-colors">
                    <div className="flex items-center gap-4">
                      {/* File Icon */}
                      <div className="w-10 h-10 rounded-lg bg-theme-elevated flex items-center justify-center flex-shrink-0">
                        {getFileIcon(resource.file_path)}
                      </div>

                      {/* Details */}
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-2 flex-wrap mb-1">
                          <h3 className="font-semibold text-theme-primary text-sm truncate">
                            {resource.title}
                          </h3>
                          <Chip size="sm" variant="flat" className="text-xs bg-theme-elevated text-theme-subtle">
                            {getFileExtension(resource.file_path)}
                          </Chip>
                          {resource.category && (
                            <Chip
                              size="sm"
                              variant="flat"
                              className={`text-xs ${categoryColorMap[resource.category.color] ?? categoryColorMap.gray}`}
                            >
                              {resource.category.name}
                            </Chip>
                          )}
                        </div>

                        {resource.description && (
                          <p className="text-xs text-theme-muted line-clamp-1 mb-1">
                            {resource.description}
                          </p>
                        )}

                        <div className="flex items-center gap-3 text-xs text-theme-subtle">
                          <span className="flex items-center gap-1">
                            <User className="w-3 h-3" aria-hidden="true" />
                            {resource.uploader.name}
                          </span>
                          <span className="flex items-center gap-1">
                            <Calendar className="w-3 h-3" aria-hidden="true" />
                            {formatRelativeTime(resource.created_at)}
                          </span>
                        </div>
                      </div>

                      {/* Download */}
                      <a
                        href={resource.file_url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="flex-shrink-0"
                      >
                        <Button
                          size="sm"
                          variant="flat"
                          className="bg-theme-elevated text-theme-muted"
                          startContent={<Download className="w-3.5 h-3.5" aria-hidden="true" />}
                        >
                          Open
                        </Button>
                      </a>
                    </div>
                  </GlassCard>
                </motion.div>
              ))}

              {hasMore && (
                <div className="pt-4 text-center">
                  <Button
                    variant="flat"
                    className="bg-theme-elevated text-theme-muted"
                    onPress={() => loadResources(true)}
                    isLoading={isLoadingMore}
                  >
                    Load More
                  </Button>
                </div>
              )}
            </motion.div>
          )}
        </>
      )}

      {/* Upload Resource Modal */}
      <Modal
        isOpen={uploadModal.isOpen}
        onClose={() => {
          if (!isUploading) {
            uploadModal.onClose();
            resetUploadForm();
          }
        }}
        size="lg"
        classNames={{
          base: 'bg-content1 border border-theme-default',
          header: 'border-b border-theme-default',
          body: 'py-6',
          footer: 'border-t border-theme-default',
        }}
      >
        <ModalContent>
          <ModalHeader className="text-theme-primary flex items-center gap-2">
            <Upload className="w-5 h-5 text-amber-500" aria-hidden="true" />
            Upload Resource
          </ModalHeader>
          <ModalBody>
            <div className="space-y-4">
              {/* Title */}
              <Input
                label="Title"
                placeholder="Resource title"
                value={uploadTitle}
                onChange={(e) => setUploadTitle(e.target.value)}
                isRequired
                classNames={inputClassNames}
                isDisabled={isUploading}
              />

              {/* Description */}
              <Textarea
                label="Description"
                placeholder="Brief description of this resource..."
                value={uploadDescription}
                onChange={(e) => setUploadDescription(e.target.value)}
                minRows={2}
                maxRows={4}
                classNames={{
                  input: 'bg-transparent text-theme-primary',
                  inputWrapper: 'bg-theme-elevated border-theme-default',
                  label: 'text-theme-muted',
                }}
                isDisabled={isUploading}
              />

              {/* Category */}
              {categories.length > 0 && (
                <Select
                  label="Category"
                  placeholder="Select a category"
                  selectedKeys={uploadCategoryId ? [uploadCategoryId] : []}
                  onSelectionChange={(keys) => {
                    const value = Array.from(keys)[0] as string;
                    setUploadCategoryId(value || '');
                  }}
                  classNames={{
                    trigger: 'bg-theme-elevated border-theme-default',
                    value: 'text-theme-primary',
                    label: 'text-theme-muted',
                  }}
                  isDisabled={isUploading}
                >
                  {categories.map((cat) => (
                    <SelectItem key={String(cat.id)}>{cat.name}</SelectItem>
                  ))}
                </Select>
              )}

              {/* File Upload Area */}
              <div>
                <p className="text-sm text-theme-muted mb-2">File</p>
                <input
                  ref={fileInputRef}
                  type="file"
                  accept={ALLOWED_EXTENSIONS.map((ext) => `.${ext}`).join(',')}
                  onChange={handleFileInputChange}
                  className="hidden"
                  aria-label="Select file to upload"
                />

                {uploadFile ? (
                  <div className="flex items-center gap-3 p-4 rounded-xl bg-theme-elevated border border-theme-default">
                    <div className="w-10 h-10 rounded-lg bg-emerald-500/20 flex items-center justify-center flex-shrink-0">
                      <CheckCircle className="w-5 h-5 text-emerald-500" aria-hidden="true" />
                    </div>
                    <div className="flex-1 min-w-0">
                      <p className="text-sm font-medium text-theme-primary truncate">{uploadFile.name}</p>
                      <p className="text-xs text-theme-subtle">{formatFileSize(uploadFile.size)}</p>
                    </div>
                    <Button
                      isIconOnly
                      size="sm"
                      variant="light"
                      className="text-theme-muted hover:text-red-500 min-w-0 w-8 h-8"
                      onPress={() => setUploadFile(null)}
                      isDisabled={isUploading}
                      aria-label="Remove file"
                    >
                      <X className="w-4 h-4" aria-hidden="true" />
                    </Button>
                  </div>
                ) : (
                  <div
                    role="button"
                    tabIndex={0}
                    className={`
                      flex flex-col items-center justify-center p-4 sm:p-8 rounded-xl border-2 border-dashed transition-colors cursor-pointer
                      ${isDragging
                        ? 'border-amber-500 bg-amber-500/10'
                        : 'border-theme-default bg-theme-elevated hover:border-amber-400 hover:bg-amber-500/5'
                      }
                    `}
                    onClick={() => fileInputRef.current?.click()}
                    onKeyDown={(e) => {
                      if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        fileInputRef.current?.click();
                      }
                    }}
                    onDragOver={handleDragOver}
                    onDragLeave={handleDragLeave}
                    onDrop={handleDrop}
                    aria-label="Drop file here or click to browse"
                  >
                    <Upload className="w-8 h-8 text-theme-subtle mb-3" aria-hidden="true" />
                    <p className="text-sm font-medium text-theme-primary mb-1">
                      {isDragging ? 'Drop your file here' : 'Drag and drop or click to browse'}
                    </p>
                    <p className="text-xs text-theme-subtle text-center">
                      PDF, DOC, DOCX, XLS, XLSX, TXT, CSV, JPG, PNG, GIF, SVG (max 10MB)
                    </p>
                  </div>
                )}
              </div>

              {/* Upload Progress */}
              {isUploading && (
                <div className="space-y-2">
                  <Progress
                    value={uploadProgress}
                    className="w-full"
                    classNames={{
                      indicator: 'bg-gradient-to-r from-amber-500 to-orange-600',
                      track: 'bg-theme-elevated',
                    }}
                    aria-label="Upload progress"
                  />
                  <p className="text-xs text-theme-subtle text-center">
                    Uploading... {uploadProgress}%
                  </p>
                </div>
              )}
            </div>
          </ModalBody>
          <ModalFooter>
            <Button
              variant="flat"
              className="bg-theme-elevated text-theme-primary"
              onPress={() => {
                uploadModal.onClose();
                resetUploadForm();
              }}
              isDisabled={isUploading}
            >
              Cancel
            </Button>
            <Button
              className="bg-gradient-to-r from-amber-500 to-orange-600 text-white"
              startContent={!isUploading ? <Upload className="w-4 h-4" aria-hidden="true" /> : undefined}
              onPress={handleUploadSubmit}
              isLoading={isUploading}
              isDisabled={!uploadFile || !uploadTitle.trim()}
            >
              Upload
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default ResourcesPage;
