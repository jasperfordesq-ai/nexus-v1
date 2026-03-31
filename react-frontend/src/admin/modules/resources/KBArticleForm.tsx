// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Knowledge Base Article Form (Create / Edit)
 * Shared form for creating and editing KB articles.
 * Detects edit mode via URL param `:id`.
 */

import { useState, useEffect, useCallback, lazy, Suspense } from 'react';
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
} from '@heroui/react';
const RichTextEditor = lazy(() =>
  import('../../components/RichTextEditor').then((m) => ({ default: m.RichTextEditor })),
);
import { ArrowLeft, Save } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminKb } from '../../api/adminApi';
import { api } from '@/lib/api';
import { PageHeader } from '../../components';
import { useTranslation } from 'react-i18next';

interface KBArticle {
  id: number;
  title: string;
  slug: string;
  content: string;
  content_type: string;
  category_id: number | null;
  category: string | null;
  parent_article_id: number | null;
  parent_title: string | null;
  sort_order: number;
  is_published: boolean;
  view_count: number;
  helpful_count: number;
  not_helpful_count: number;
  author_name: string;
  children: KBArticle[];
  created_at: string;
  updated_at: string;
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

export function KBArticleForm() {
  const { t } = useTranslation('admin');
  const { id } = useParams<{ id: string }>();
  const isEdit = !!id;
  const { tenantPath } = useTenant();
  const toast = useToast();
  const navigate = useNavigate();

  // Loading states
  const [loading, setLoading] = useState(isEdit);
  const [submitting, setSubmitting] = useState(false);
  const [loadError, setLoadError] = useState<string | null>(null);

  // Original article data (for edit mode page title)
  const [article, setArticle] = useState<KBArticle | null>(null);

  // Form state
  const [title, setTitle] = useState('');
  const [slug, setSlug] = useState('');
  const [content, setContent] = useState('');
  const [excerpt, setExcerpt] = useState('');
  const [isPublished, setIsPublished] = useState(false);
  const [categoryId, setCategoryId] = useState('');
  const [parentArticleId, setParentArticleId] = useState('');
  const [sortOrder, setSortOrder] = useState('0');

  // Dropdowns data
  const [categories, setCategories] = useState<ResourceCategory[]>([]);
  const [parentArticles, setParentArticles] = useState<ParentArticleOption[]>([]);

  // Validation
  const [errors, setErrors] = useState<Record<string, string>>({});

  // Dynamic page title
  usePageTitle(
    isEdit && article
      ? `Admin - ${t('breadcrumbs.edit')}: ${article.title}`
      : `Admin - ${t('breadcrumbs.create')} ${t('resources.page_title')}`,
  );

  // Load categories
  useEffect(() => {
    async function loadCategories() {
      try {
        const res = await api.get('/v2/resources/categories/tree?flat=1');
        if (res.success && res.data) {
          const data = res.data;
          if (Array.isArray(data)) {
            setCategories(data);
          }
        }
      } catch {
        // Categories are optional
      }
    }
    loadCategories();
  }, []);

  // Load parent articles (all articles for this tenant, for nesting)
  useEffect(() => {
    async function loadParentArticles() {
      try {
        const res = await api.get('/v2/kb?per_page=100&include_unpublished=1');
        if (res.success && res.data) {
          const items = Array.isArray(res.data)
            ? res.data
            : (res.data as { items?: ParentArticleOption[] }).items || [];
          // Filter out current article in edit mode
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

        // Populate form fields
        setTitle(data.title || '');
        setSlug(data.slug || '');
        setContent(data.content || '');
        setExcerpt('');
        setIsPublished(!!data.is_published);
        setCategoryId(data.category_id ? String(data.category_id) : '');
        setParentArticleId(data.parent_article_id ? String(data.parent_article_id) : '');
        setSortOrder(String(data.sort_order ?? 0));
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
    if (isEdit) {
      loadArticle();
    }
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
        content_type: 'html' as const,
        is_published: isPublished,
        category_id: categoryId ? Number(categoryId) : null,
        parent_article_id: parentArticleId ? Number(parentArticleId) : null,
        sort_order: Number(sortOrder) || 0,
      };

      const res = isEdit
        ? await adminKb.update(Number(id), payload)
        : await adminKb.create(payload);

      if (res.success) {
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

  // Loading state (edit mode)
  if (loading) {
    return (
      <div className="flex items-center justify-center py-20">
        <Spinner size="lg" label={t('federation.loading')} />
      </div>
    );
  }

  // Error state (edit mode)
  if (isEdit && (loadError || !article)) {
    return (
      <div>
        <PageHeader
          title={`${t('breadcrumbs.edit')} ${t('resources.page_title')}`}
          actions={
            <Button
              variant="flat"
              startContent={<ArrowLeft size={16} />}
              onPress={() => navigate(tenantPath('/admin/resources'))}
            >
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
          <Button
            variant="flat"
            startContent={<ArrowLeft size={16} />}
            onPress={() => navigate(tenantPath('/admin/resources'))}
          >
            {t('common.back')}
          </Button>
        }
      />

      <form onSubmit={handleSubmit}>
        <Card className="max-w-3xl">
          <CardBody className="gap-5 p-6">
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

            {/* Content */}
            <Suspense fallback={<Spinner size="sm" className="m-4" />}>
              <RichTextEditor
                label={t('content.page_title')}
                placeholder={t('resources.placeholder_content', 'Write the article content...')}
                value={content}
                onChange={setContent}
                isDisabled={submitting}
              />
            </Suspense>

            {/* Excerpt (optional) */}
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

            {/* Published toggle + Category row */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              {/* Category */}
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

              {/* Parent Article */}
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
                description={t(
                  'resources.sort_order_desc',
                  'Lower numbers appear first. Default is 0.',
                )}
              />

              <div className="flex items-center justify-between rounded-lg border border-default-200 px-4 py-3">
                <div>
                  <p className="text-sm font-medium">
                    {t('content.published', 'Published')}
                  </p>
                  <p className="text-xs text-default-400">
                    {t(
                      'resources.publish_desc',
                      'Published articles are visible to all users',
                    )}
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
