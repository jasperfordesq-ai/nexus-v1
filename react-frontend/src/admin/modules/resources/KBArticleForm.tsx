// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Knowledge Base Article Form (Create / Edit)
 *
 * Two creation modes:
 * - "Write" — rich text editor (HTML content)
 * - "Upload" — drag-and-drop a file (.md, .pdf, .docx, .txt)
 *
 * Supports file attachments on existing articles.
 * Detects edit mode via URL param `:id`.
 */

import { useState, useEffect, useCallback, useRef, lazy, Suspense } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import {
  Card,
  CardBody,
  Input,
  Button,
  Select,
  SelectItem,
  Switch,
  Textarea,
  Spinner,
  Tabs,
  Tab,
  Chip,
  Divider,
} from '@heroui/react';
const RichTextEditor = lazy(() =>
  import('../../components/RichTextEditor').then((m) => ({ default: m.RichTextEditor })),
);
import { ArrowLeft, Save, Upload, FileText, Trash2, Download, X, Youtube } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminKb } from '../../api/adminApi';
import { api, API_BASE } from '@/lib/api';
import { PageHeader } from '../../components';
import { useTranslation } from 'react-i18next';

interface KBAttachment {
  id: number;
  file_name: string;
  file_url: string;
  mime_type: string;
  file_size: number;
}

interface KBArticle {
  id: number;
  title: string;
  slug: string;
  content: string;
  content_type: string;
  category_id: number | null;
  parent_article_id: number | null;
  sort_order: number;
  is_published: boolean;
  attachments: KBAttachment[];
  [key: string]: unknown;
}

interface ResourceCategory {
  id: number;
  name: string;
  slug: string;
  parent_id: number | null;
}

interface ParentArticleOption {
  id: number;
  title: string;
}

function formatFileSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

const ACCEPTED_FILE_TYPES = '.md,.pdf,.doc,.docx,.txt,.csv,.xls,.xlsx';

function resolveAttachmentUrl(fileUrl: string): string {
  if (fileUrl.startsWith('/api/')) {
    const apiOrigin = API_BASE.replace(/\/api\/?$/, '');
    return apiOrigin + fileUrl;
  }
  return fileUrl;
}

export function KBArticleForm() {
  const { t } = useTranslation('admin');
  const { id } = useParams<{ id: string }>();
  const isEdit = !!id;
  const { tenantPath } = useTenant();
  const toast = useToast();
  const navigate = useNavigate();
  const fileInputRef = useRef<HTMLInputElement>(null);

  // Loading states
  const [loading, setLoading] = useState(isEdit);
  const [submitting, setSubmitting] = useState(false);
  const [loadError, setLoadError] = useState<string | null>(null);

  // Original article data
  const [article, setArticle] = useState<KBArticle | null>(null);

  // Mode: "write" (rich text) or "upload" (file)
  const [mode, setMode] = useState<'write' | 'upload'>('write');

  // Form state
  const [title, setTitle] = useState('');
  const [slug, setSlug] = useState('');
  const [content, setContent] = useState('');
  const [contentType, setContentType] = useState<'html' | 'markdown' | 'plain'>('html');
  const [excerpt, setExcerpt] = useState('');
  const [isPublished, setIsPublished] = useState(false);
  const [categoryId, setCategoryId] = useState('');
  const [parentArticleId, setParentArticleId] = useState('');
  const [sortOrder, setSortOrder] = useState('0');
  const [videoUrl, setVideoUrl] = useState('');

  // File upload state
  const [pendingFile, setPendingFile] = useState<File | null>(null);
  const [isDragging, setIsDragging] = useState(false);

  // Attachments (edit mode)
  const [attachments, setAttachments] = useState<KBAttachment[]>([]);
  const [uploadingAttachment, setUploadingAttachment] = useState(false);

  // Dropdowns data
  const [categories, setCategories] = useState<ResourceCategory[]>([]);
  const [parentArticles, setParentArticles] = useState<ParentArticleOption[]>([]);

  // Validation
  const [errors, setErrors] = useState<Record<string, string>>({});

  usePageTitle(
    isEdit && article
      ? `Admin - ${t('breadcrumbs.edit')}: ${article.title}`
      : `Admin - ${t('breadcrumbs.create')} ${t('resources.page_title')}`,
  );

  // Load categories (from main categories table, type=resource)
  useEffect(() => {
    async function loadCategories() {
      try {
        const res = await api.get('/v2/admin/categories?type=resource');
        if (res.success && res.data) {
          const data = Array.isArray(res.data) ? res.data : (res.data as { items?: ResourceCategory[] }).items || [];
          setCategories(data);
        }
      } catch {
        // Categories are optional
      }
    }
    loadCategories();
  }, []);

  // Load parent articles
  useEffect(() => {
    async function loadParentArticles() {
      try {
        const res = await api.get('/v2/kb?per_page=100&include_unpublished=1');
        if (res.success && res.data) {
          const items = Array.isArray(res.data)
            ? res.data
            : (res.data as { items?: ParentArticleOption[] }).items || [];
          const filtered = id
            ? items.filter((a: ParentArticleOption) => a.id !== Number(id))
            : items;
          setParentArticles(filtered);
        }
      } catch {
        // Parent articles are optional
      }
    }
    loadParentArticles();
  }, [id]);

  // Load article for edit mode
  const loadArticle = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    setLoadError(null);

    try {
      const res = await adminKb.get(Number(id));
      if (res.success && res.data) {
        const data = res.data as KBArticle;
        setArticle(data);
        setTitle(data.title || '');
        setSlug(data.slug || '');
        setContent(data.content || '');
        setContentType((data.content_type as 'html' | 'markdown' | 'plain') || 'html');
        setIsPublished(!!data.is_published);
        setCategoryId(data.category_id ? String(data.category_id) : '');
        setParentArticleId(data.parent_article_id ? String(data.parent_article_id) : '');
        setSortOrder(String(data.sort_order ?? 0));
        setVideoUrl((data as Record<string, unknown>).video_url as string || '');
        setAttachments(data.attachments || []);
        // Set mode based on content_type
        setMode(data.content_type === 'markdown' ? 'upload' : 'write');
      } else {
        setLoadError(t('resources.failed_to_load_resources'));
      }
    } catch {
      setLoadError(t('resources.an_unexpected_error_occurred'));
    } finally {
      setLoading(false);
    }
  }, [id, t]);

  useEffect(() => {
    if (isEdit) loadArticle();
  }, [isEdit, loadArticle]);

  // Auto-generate slug from title (only in create mode)
  useEffect(() => {
    if (!isEdit && title) {
      const generated = title
        .toLowerCase()
        .replace(/[^a-z0-9]+/gi, '-')
        .replace(/^-+|-+$/g, '');
      setSlug(generated);
    }
  }, [title, isEdit]);

  // ── File handling ───────────────────────────────────────────

  function processFile(file: File) {
    const ext = file.name.split('.').pop()?.toLowerCase();

    if (ext === 'md') {
      // Read markdown content
      const reader = new FileReader();
      reader.onload = (e) => {
        const text = e.target?.result as string;
        setContent(text);
        setContentType('markdown');

        // Extract title from first # heading
        const headingMatch = text.match(/^#\s+(.+)$/m);
        if (headingMatch?.[1] && !title) {
          setTitle(headingMatch[1].trim());
        } else if (!title) {
          // Fallback: use filename
          setTitle(file.name.replace(/\.md$/, '').replace(/[_-]+/g, ' '));
        }

        // Extract excerpt from first non-heading paragraph
        const lines = text.split('\n').filter((l) => l.trim() && !l.startsWith('#'));
        const firstLine = lines[0];
        if (firstLine) {
          setExcerpt(firstLine.trim().substring(0, 200));
        }
      };
      reader.readAsText(file);
      setPendingFile(file);
    } else if (ext === 'pdf') {
      // PDF — store as attachment, user writes excerpt
      setPendingFile(file);
      setContentType('html');
      if (!title) {
        setTitle(file.name.replace(/\.pdf$/, '').replace(/[_-]+/g, ' '));
      }
    } else {
      // Other file types — store as attachment
      setPendingFile(file);
      if (!title) {
        setTitle(file.name.replace(/\.[^.]+$/, '').replace(/[_-]+/g, ' '));
      }
    }
  }

  function handleDrop(e: React.DragEvent) {
    e.preventDefault();
    setIsDragging(false);
    const file = e.dataTransfer.files[0];
    if (file) processFile(file);
  }

  function handleFileSelect(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0];
    if (file) processFile(file);
  }

  // ── Attachment management (edit mode) ──────────────────────

  async function uploadAttachment(file: File) {
    if (!id) return;
    setUploadingAttachment(true);
    try {
      const res = await api.upload(`/v2/kb/${id}/attachments`, file);
      if (res.success && res.data) {
        setAttachments((prev) => [...prev, res.data as KBAttachment]);
        toast.success(t('resources.attachment_uploaded', 'File attached successfully'));
      } else {
        toast.error(res.error || t('resources.failed_to_upload_attachment'));
      }
    } catch {
      toast.error(t('resources.failed_to_upload_attachment'));
    } finally {
      setUploadingAttachment(false);
    }
  }

  async function deleteAttachment(attachmentId: number) {
    if (!id) return;
    try {
      const res = await api.delete(`/v2/kb/${id}/attachments/${attachmentId}`);
      if (res.success !== false) {
        setAttachments((prev) => prev.filter((a) => a.id !== attachmentId));
        toast.success(t('resources.attachment_deleted', 'Attachment removed'));
      }
    } catch {
      toast.error(t('resources.failed_to_delete_attachment'));
    }
  }

  // ── Form submission ────────────────────────────────────────

  function validate(): boolean {
    const newErrors: Record<string, string> = {};
    if (!title.trim()) {
      newErrors.title = t('blog.title_required', 'Title is required');
    }
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!validate()) return;
    setSubmitting(true);

    try {
      const payload = {
        title: title.trim(),
        slug: slug.trim() || undefined,
        content,
        content_type: contentType,
        is_published: isPublished,
        category_id: categoryId ? Number(categoryId) : null,
        parent_article_id: parentArticleId ? Number(parentArticleId) : null,
        sort_order: Number(sortOrder) || 0,
        video_url: videoUrl.trim() || null,
      };

      const res = isEdit
        ? await adminKb.update(Number(id), payload)
        : await adminKb.create(payload);

      if (res.success) {
        // If we have a pending file and the article was just created, upload it as attachment
        const articleId = isEdit ? Number(id) : (res.data as KBArticle)?.id;
        if (pendingFile && articleId) {
          await api.upload(`/v2/kb/${articleId}/attachments`, pendingFile);
        }

        toast.success(
          isEdit
            ? t('resources.article_updated', 'Article updated successfully')
            : t('resources.article_created', 'Article created successfully'),
        );
        navigate(tenantPath('/admin/resources'));
      } else {
        toast.error(res.error || t('resources.an_unexpected_error_occurred'));
      }
    } catch {
      toast.error(t('resources.an_unexpected_error_occurred'));
    } finally {
      setSubmitting(false);
    }
  }

  // ── Render ─────────────────────────────────────────────────

  if (loading) {
    return (
      <div className="flex items-center justify-center py-20">
        <Spinner size="lg" label={t('federation.loading')} />
      </div>
    );
  }

  if (isEdit && (loadError || !article)) {
    return (
      <div>
        <PageHeader
          title={`${t('breadcrumbs.edit')} ${t('resources.page_title')}`}
          actions={
            <Button variant="flat" startContent={<ArrowLeft size={16} />}
              onPress={() => navigate(tenantPath('/admin/resources'))}>
              {t('common.back')}
            </Button>
          }
        />
        <Card className="max-w-2xl">
          <CardBody className="p-6">
            <p className="text-center text-danger">
              {loadError || t('resources.failed_to_load_resources')}
            </p>
            <div className="mt-4 flex justify-center">
              <Button variant="flat" onPress={() => navigate(tenantPath('/admin/resources'))}>
                {t('common.back')}
              </Button>
            </div>
          </CardBody>
        </Card>
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title={
          isEdit
            ? `${t('breadcrumbs.edit')}: ${article?.title}`
            : `${t('breadcrumbs.create')} ${t('resources.page_title')}`
        }
        actions={
          <Button variant="flat" startContent={<ArrowLeft size={16} />}
            onPress={() => navigate(tenantPath('/admin/resources'))}>
            {t('common.back')}
          </Button>
        }
      />

      <form onSubmit={handleSubmit}>
        <Card className="max-w-3xl">
          <CardBody className="gap-5 p-6">
            {/* Mode tabs (create only — in edit mode, mode is inferred from content_type) */}
            {!isEdit && (
              <Tabs
                selectedKey={mode}
                onSelectionChange={(key) => {
                  setMode(key as 'write' | 'upload');
                  if (key === 'write') {
                    setContentType('html');
                    setPendingFile(null);
                  }
                }}
                variant="underlined"
                size="sm"
                classNames={{ tabList: 'mb-2' }}
              >
                <Tab key="write" title={
                  <span className="flex items-center gap-1.5">
                    <FileText size={14} /> {t('resources.mode_write', 'Write')}
                  </span>
                } />
                <Tab key="upload" title={
                  <span className="flex items-center gap-1.5">
                    <Upload size={14} /> {t('resources.mode_upload', 'Upload File')}
                  </span>
                } />
              </Tabs>
            )}

            {/* Title */}
            <Input
              label={t('content.label_name')}
              placeholder={t('resources.placeholder_title', 'Enter article title')}
              value={title}
              onValueChange={setTitle}
              isRequired
              isInvalid={!!errors.title}
              errorMessage={errors.title}
              isDisabled={submitting}
            />

            {/* Slug */}
            <Input
              label={t('federation.col_slug')}
              placeholder={t('resources.placeholder_slug', 'Auto-generated from title')}
              value={slug}
              onValueChange={setSlug}
              isDisabled={submitting}
              description={
                isEdit
                  ? t('blog.slug_desc_edit', 'Edit to customize the URL. Leave as-is to keep current slug.')
                  : t('blog.slug_desc_create', 'Auto-generated from title, or type a custom slug.')
              }
            />

            {/* Content — Write mode */}
            {mode === 'write' && contentType !== 'markdown' && (
              <Suspense fallback={<Spinner size="sm" className="m-4" />}>
                <RichTextEditor
                  label={t('content.page_title')}
                  placeholder={t('resources.placeholder_content', 'Write the article content...')}
                  value={content}
                  onChange={setContent}
                  isDisabled={submitting}
                  showMarkdownImport
                />
              </Suspense>
            )}

            {/* Content — Markdown edit (when editing a markdown article) */}
            {(mode === 'write' || mode === 'upload') && contentType === 'markdown' && content && (
              <Textarea
                label={t('resources.markdown_content', 'Markdown Content')}
                value={content}
                onValueChange={setContent}
                minRows={12}
                maxRows={30}
                isDisabled={submitting}
                classNames={{ input: 'font-mono text-sm' }}
                description={t('resources.markdown_desc', 'Raw markdown — will be rendered on the public page.')}
              />
            )}

            {/* Content — Upload mode drop zone */}
            {mode === 'upload' && !content && (
              <div
                onDragOver={(e) => { e.preventDefault(); setIsDragging(true); }}
                onDragLeave={() => setIsDragging(false)}
                onDrop={handleDrop}
                onClick={() => fileInputRef.current?.click()}
                className={`
                  flex flex-col items-center justify-center gap-3 rounded-lg border-2 border-dashed p-10 cursor-pointer transition-colors
                  ${isDragging
                    ? 'border-primary bg-primary/5'
                    : 'border-default-300 hover:border-primary hover:bg-default-50'
                  }
                `}
              >
                <Upload size={32} className="text-default-400" />
                <div className="text-center">
                  <p className="text-sm font-medium text-foreground">
                    {t('resources.drop_file', 'Drop a file here or click to browse')}
                  </p>
                  <p className="text-xs text-default-400 mt-1">
                    {t('resources.supported_formats', 'Supported: Markdown (.md), PDF, Word (.doc, .docx), Text (.txt)')}
                  </p>
                </div>
                <input
                  ref={fileInputRef}
                  type="file"
                  accept={ACCEPTED_FILE_TYPES}
                  onChange={handleFileSelect}
                  className="hidden"
                />
              </div>
            )}

            {/* Pending file indicator */}
            {pendingFile && (
              <div className="flex items-center gap-3 rounded-lg border border-default-200 px-4 py-3">
                <FileText size={18} className="text-primary flex-shrink-0" />
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-medium text-foreground truncate">{pendingFile.name}</p>
                  <p className="text-xs text-default-400">{formatFileSize(pendingFile.size)}</p>
                </div>
                <Chip size="sm" variant="flat" color="primary">
                  {pendingFile.name.split('.').pop()?.toUpperCase()}
                </Chip>
                <Button
                  isIconOnly size="sm" variant="light" color="danger"
                  onPress={() => {
                    setPendingFile(null);
                    if (contentType === 'markdown') {
                      setContent('');
                      setContentType('html');
                    }
                  }}
                  aria-label="Remove file"
                >
                  <X size={14} />
                </Button>
              </div>
            )}

            {/* Excerpt */}
            <Textarea
              label={t('blog.excerpt', 'Excerpt')}
              placeholder={t('resources.placeholder_excerpt', 'A short summary of the article (optional)')}
              value={excerpt}
              onValueChange={setExcerpt}
              minRows={2}
              maxRows={4}
              isDisabled={submitting}
              description={t(
                'resources.excerpt_desc',
                'Brief description shown in article listings. If blank, the first lines of content are used.',
              )}
            />

            {/* YouTube Video */}
            <Input
              label={t('resources.video_url', 'YouTube Video URL')}
              placeholder="https://www.youtube.com/watch?v=..."
              value={videoUrl}
              onValueChange={setVideoUrl}
              isDisabled={submitting}
              startContent={<Youtube size={16} className="text-red-500 flex-shrink-0" />}
              description={t(
                'resources.video_url_desc',
                'Paste a YouTube link to embed a video at the top of the article.',
              )}
            />

            {/* Category + Parent */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <Select
                label={t('breadcrumbs.categories')}
                placeholder={t('resources.placeholder_category', 'Select a category (optional)')}
                selectedKeys={categoryId ? [categoryId] : []}
                onSelectionChange={(keys) => {
                  const selected = Array.from(keys)[0] as string;
                  setCategoryId(selected || '');
                }}
                isDisabled={submitting}
              >
                {categories.map((cat) => (
                  <SelectItem key={String(cat.id)}>{cat.name}</SelectItem>
                ))}
              </Select>

              <Select
                label={t('resources.parent_article', 'Parent Article')}
                placeholder={t('resources.placeholder_parent', 'None (top-level)')}
                selectedKeys={parentArticleId ? [parentArticleId] : []}
                onSelectionChange={(keys) => {
                  const selected = Array.from(keys)[0] as string;
                  setParentArticleId(selected || '');
                }}
                isDisabled={submitting}
              >
                {parentArticles.map((a) => (
                  <SelectItem key={String(a.id)}>{a.title}</SelectItem>
                ))}
              </Select>
            </div>

            {/* Sort Order + Published */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <Input
                label={t('resources.sort_order', 'Sort Order')}
                placeholder="0"
                type="number"
                value={sortOrder}
                onValueChange={setSortOrder}
                isDisabled={submitting}
                description={t('resources.sort_order_desc', 'Lower numbers appear first. Default is 0.')}
              />

              <div className="flex items-center justify-between rounded-lg border border-default-200 px-4 py-3">
                <div>
                  <p className="text-sm font-medium">{t('content.published', 'Published')}</p>
                  <p className="text-xs text-default-400">
                    {t('resources.publish_desc', 'Published articles are visible to all users')}
                  </p>
                </div>
                <Switch
                  isSelected={isPublished}
                  onValueChange={setIsPublished}
                  isDisabled={submitting}
                  size="sm"
                />
              </div>
            </div>

            {/* Attachments section (edit mode) */}
            {isEdit && (
              <>
                <Divider />
                <div className="space-y-3">
                  <div className="flex items-center justify-between">
                    <h3 className="text-sm font-semibold text-foreground">
                      {t('resources.attachments', 'Attachments')}
                      {attachments.length > 0 && (
                        <Chip size="sm" variant="flat" className="ml-2">{attachments.length}</Chip>
                      )}
                    </h3>
                    <Button
                      size="sm"
                      variant="flat"
                      color="primary"
                      startContent={<Upload size={14} />}
                      isLoading={uploadingAttachment}
                      onPress={() => {
                        const input = document.createElement('input');
                        input.type = 'file';
                        input.accept = ACCEPTED_FILE_TYPES;
                        input.onchange = (e) => {
                          const file = (e.target as HTMLInputElement).files?.[0];
                          if (file) uploadAttachment(file);
                        };
                        input.click();
                      }}
                    >
                      {t('resources.add_attachment', 'Add File')}
                    </Button>
                  </div>

                  {attachments.length === 0 && (
                    <p className="text-xs text-default-400">
                      {t('resources.no_attachments', 'No files attached yet.')}
                    </p>
                  )}

                  {attachments.map((att) => (
                    <div key={att.id} className="flex items-center gap-3 rounded-lg border border-default-200 px-4 py-2.5">
                      <FileText size={16} className="text-primary flex-shrink-0" />
                      <div className="flex-1 min-w-0">
                        <p className="text-sm font-medium text-foreground truncate">{att.file_name}</p>
                        <p className="text-xs text-default-400">
                          {att.mime_type} — {formatFileSize(att.file_size)}
                        </p>
                      </div>
                      <div className="flex gap-1">
                        <Button
                          isIconOnly size="sm" variant="flat" color="default"
                          as="a" href={resolveAttachmentUrl(att.file_url)} download={att.file_name}
                          target="_blank" rel="noopener noreferrer"
                          aria-label="Download"
                        >
                          <Download size={14} />
                        </Button>
                        <Button
                          isIconOnly size="sm" variant="flat" color="danger"
                          onPress={() => deleteAttachment(att.id)}
                          aria-label="Delete attachment"
                        >
                          <Trash2 size={14} />
                        </Button>
                      </div>
                    </div>
                  ))}
                </div>
              </>
            )}

            {/* Submit */}
            <div className="flex justify-end gap-3 pt-2">
              <Button
                variant="flat"
                onPress={() => navigate(tenantPath('/admin/resources'))}
                isDisabled={submitting}
              >
                {t('cancel')}
              </Button>
              <Button
                type="submit"
                color="primary"
                startContent={!submitting ? <Save size={16} /> : undefined}
                isLoading={submitting}
                isDisabled={submitting}
              >
                {isEdit
                  ? t('federation.save_changes')
                  : `${t('breadcrumbs.create')} ${t('resources.page_title')}`}
              </Button>
            </div>
          </CardBody>
        </Card>
      </form>
    </div>
  );
}

export default KBArticleForm;
