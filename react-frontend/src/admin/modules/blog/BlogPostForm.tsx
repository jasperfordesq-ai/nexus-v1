// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Blog Post Form (Create / Edit)
 * Shared form for creating and editing blog posts.
 * Detects edit mode via URL param `:id`.
 * Parity: PHP Admin\BlogController::create() / edit()
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
  Divider,
  Spinner,
} from '@heroui/react';
const RichTextEditor = lazy(() =>
  import('../../components/RichTextEditor').then((m) => ({ default: m.RichTextEditor })),
);
import ArrowLeft from 'lucide-react/icons/arrow-left';
import Save from 'lucide-react/icons/save';
import Search from 'lucide-react/icons/search';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminBlog, adminCategories } from '../../api/adminApi';
import { PageHeader } from '../../components';
import type { AdminBlogPost, AdminCategory } from '../../api/types';
import { useTranslation } from 'react-i18next';

export function BlogPostForm() {
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

  // Original post data (for edit mode page title)
  const [post, setPost] = useState<AdminBlogPost | null>(null);

  // Form state
  const [title, setTitle] = useState('');
  const [slug, setSlug] = useState('');
  const [content, setContent] = useState('');
  const [excerpt, setExcerpt] = useState('');
  const [status, setStatus] = useState('draft');
  const [categoryId, setCategoryId] = useState('');
  const [featuredImage, setFeaturedImage] = useState('');

  // SEO fields
  const [metaTitle, setMetaTitle] = useState('');
  const [metaDescription, setMetaDescription] = useState('');
  const [noindex, setNoindex] = useState(false);

  // Categories for the dropdown
  const [categories, setCategories] = useState<AdminCategory[]>([]);

  // Validation
  const [errors, setErrors] = useState<Record<string, string>>({});

  // Dynamic page title
  usePageTitle(isEdit && post
    ? t('blog.page_title_edit_with_title', { title: post.title })
    : t('blog.page_title_create'));

  // Load categories
  useEffect(() => {
    async function loadCategories() {
      try {
        const res = await adminCategories.list({ type: 'blog' });
        if (res.success && res.data) {
          const data = res.data;
          if (Array.isArray(data)) {
            setCategories(data);
          }
        }
      } catch {
        // Categories are optional, don't block the form
      }
    }
    loadCategories();
  }, []);

  // Load post for edit mode
  const loadPost = useCallback(async () => {
    if (!id) return;

    setLoading(true);
    setLoadError(null);

    try {
      const res = await adminBlog.get(Number(id));

      if (res.success && res.data) {
        const postData = res.data as AdminBlogPost;
        setPost(postData);

        // Populate form fields
        setTitle(postData.title || '');
        setSlug(postData.slug || '');
        setContent(postData.content || '');
        setExcerpt(postData.excerpt || '');
        setStatus(postData.status || 'draft');
        setCategoryId(postData.category_id ? String(postData.category_id) : '');
        setFeaturedImage(postData.featured_image || '');
        setMetaTitle(postData.meta_title || '');
        setMetaDescription(postData.meta_description || '');
        setNoindex(postData.noindex || false);
      } else {
        setLoadError(res.error || t('blog.failed_to_load_blog_posts'));
      }
    } catch {
      setLoadError(t('blog.an_unexpected_error_occurred'));
    } finally {
      setLoading(false);
    }
  }, [id, t])


  useEffect(() => {
    if (isEdit) {
      loadPost();
    }
  }, [isEdit, loadPost]);

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
      newErrors.title = t('blog.title_required');
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
        excerpt: excerpt.trim(),
        status: status as 'draft' | 'published',
        featured_image: featuredImage.trim() || undefined,
        category_id: categoryId ? Number(categoryId) : undefined,
        meta_title: metaTitle.trim() || undefined,
        meta_description: metaDescription.trim() || undefined,
        noindex: noindex || undefined,
      };

      const res = isEdit
        ? await adminBlog.update(Number(id), payload)
        : await adminBlog.create(payload);

      if (res.success) {
        toast.success(isEdit ? t('blog.post_updated') : t('blog.post_created'));
        navigate(tenantPath('/admin/blog'));
      } else {
        toast.error(res.error || t('blog.an_unexpected_error_occurred'));
      }
    } catch {
      toast.error(t('blog.an_unexpected_error_occurred'));
    } finally {
      setSubmitting(false);
    }
  }

  // Loading state (edit mode)
  if (loading) {
    return (
      <div className="flex items-center justify-center py-20">
        <Spinner size="lg" label={t('blog.loading_post')} />
      </div>
    );
  }

  // Error state (edit mode)
  if (isEdit && (loadError || !post)) {
    return (
      <div>
        <PageHeader
          title={t('blog.page_title_edit')}
          actions={
            <Button
              variant="flat"
              startContent={<ArrowLeft size={16} />}
              onPress={() => navigate(tenantPath('/admin/blog'))}
            >
              {t('blog.back')}
            </Button>
          }
        />
        <Card className="max-w-2xl">
          <CardBody className="p-6">
            <p className="text-center text-danger">
              {loadError || t('blog.failed_to_load_blog_posts')}
            </p>
            <div className="mt-4 flex justify-center">
              <Button variant="flat" onPress={() => navigate(tenantPath('/admin/blog'))}>
                {t('blog.back')}
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
        title={isEdit && post?.title ? t('blog.page_title_edit_with_title', { title: post.title }) : t('blog.page_title_create')}
        actions={
          <Button
            variant="flat"
            startContent={<ArrowLeft size={16} />}
            onPress={() => navigate(tenantPath('/admin/blog'))}
          >
            {t('blog.back')}
          </Button>
        }
      />

      <form onSubmit={handleSubmit}>
        <Card className="max-w-3xl">
          <CardBody className="gap-5 p-6">
            {/* Title */}
            <Input
              label={t('blog.label_title')}
              placeholder={t('blog.placeholder_title')}
              value={title}
              onValueChange={setTitle}
              isRequired
              isInvalid={!!errors.title}
              errorMessage={errors.title}
              isDisabled={submitting}
            />

            {/* Slug */}
            <Input
              label={t('blog.label_slug')}
              placeholder={t('blog.placeholder_slug')}
              value={slug}
              onValueChange={setSlug}
              isDisabled={submitting}
              description={isEdit ? t('blog.slug_desc_edit') : t('blog.slug_desc_create')}
            />

            {/* Content */}
            <Suspense fallback={<Spinner size="sm" className="m-4" />}>
              <RichTextEditor
                label={t('blog.label_content')}
                placeholder={t('blog.placeholder_content')}
                value={content}
                onChange={setContent}
                isDisabled={submitting}
              />
            </Suspense>

            {/* Excerpt */}
            <Textarea
              label={t('blog.excerpt')}
              placeholder={t('blog.placeholder_excerpt')}
              value={excerpt}
              onValueChange={setExcerpt}
              minRows={2}
              maxRows={4}
              isDisabled={submitting}
            />

            {/* Status and Category row */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              {/* Status */}
              <Select
                label={t('blog.label_status')}
                placeholder={t('blog.placeholder_status')}
                selectedKeys={status ? [status] : []}
                onSelectionChange={(keys) => {
                  const selected = Array.from(keys)[0] as string;
                  if (selected) setStatus(selected);
                }}
                isDisabled={submitting}
              >
                <SelectItem key="draft">{t('content.draft')}</SelectItem>
                <SelectItem key="published">{t('content.published')}</SelectItem>
              </Select>

              {/* Category */}
              <Select
                label={t('blog.label_categories')}
                placeholder={t('blog.placeholder_category')}
                selectedKeys={categoryId ? [categoryId] : []}
                onSelectionChange={(keys) => {
                  const selected = Array.from(keys)[0] as string;
                  setCategoryId(selected || '');
                }}
                isDisabled={submitting}
              >
                {categories.map((cat) => (
                  <SelectItem key={String(cat.id)}>
                    {cat.name}
                  </SelectItem>
                ))}
              </Select>
            </div>

            {/* Featured Image URL */}
            <Input
              label={t('blog.featured_image')}
              placeholder={t('blog.placeholder_featured_image')}
              value={featuredImage}
              onValueChange={setFeaturedImage}
              isDisabled={submitting}
              description={t('blog.featured_image_desc')}
            />

            {/* SEO Override (Optional) */}
            <Divider />
            <div className="flex items-center gap-2 text-default-600">
              <Search size={16} />
              <span className="text-sm font-semibold">{t('blog.seo_override')}</span>
            </div>
            <Input
              label={t('blog.meta_title')}
              placeholder={t('blog.placeholder_meta_title')}
              value={metaTitle}
              onValueChange={setMetaTitle}
              isDisabled={submitting}
              description={t('blog.meta_title_desc')}
            />
            <Textarea
              label={t('blog.meta_description')}
              placeholder={t('blog.placeholder_meta_desc')}
              value={metaDescription}
              onValueChange={setMetaDescription}
              minRows={2}
              maxRows={3}
              isDisabled={submitting}
              description={t('blog.meta_desc_desc')}
            />
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium">{t('blog.noindex')}</p>
                <p className="text-xs text-default-400">{t('blog.noindex_desc')}</p>
              </div>
              <Switch
                isSelected={noindex}
                onValueChange={setNoindex}
                isDisabled={submitting}
                size="sm"
              />
            </div>

            {/* Submit */}
            <div className="flex justify-end gap-3 pt-2">
              <Button
                variant="flat"
                onPress={() => navigate(tenantPath('/admin/blog'))}
                isDisabled={submitting}
              >
                {t('blog.cancel')}
              </Button>
              <Button
                type="submit"
                color="primary"
                startContent={!submitting ? <Save size={16} /> : undefined}
                isLoading={submitting}
                isDisabled={submitting}
              >
                {isEdit ? t('blog.save_changes') : t('blog.page_title_create')}
              </Button>
            </div>
          </CardBody>
        </Card>
      </form>
    </div>
  );
}

export default BlogPostForm;
