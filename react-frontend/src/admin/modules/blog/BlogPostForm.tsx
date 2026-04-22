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
import { ArrowLeft, Save, Search } from 'lucide-react';
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
  usePageTitle(isEdit && post ? `Admin - ${"Edit"}: ${post.title}` : `Admin - ${"Create"} ${"Blog"}`);

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
        setLoadError(res.error || "Failed to load blog posts");
      }
    } catch {
      setLoadError("An unexpected error occurred");
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
        toast.success(isEdit ? t('blog.post_updated', 'Post updated successfully') : t('blog.post_created', 'Post created successfully'));
        navigate(tenantPath('/admin/blog'));
      } else {
        toast.error(res.error || "An unexpected error occurred");
      }
    } catch {
      toast.error("An unexpected error occurred");
    } finally {
      setSubmitting(false);
    }
  }

  // Loading state (edit mode)
  if (loading) {
    return (
      <div className="flex items-center justify-center py-20">
        <Spinner size="lg" label={"Loading federation..."} />
      </div>
    );
  }

  // Error state (edit mode)
  if (isEdit && (loadError || !post)) {
    return (
      <div>
        <PageHeader
          title={`${"Edit"} ${"Blog"}`}
          actions={
            <Button
              variant="flat"
              startContent={<ArrowLeft size={16} />}
              onPress={() => navigate(tenantPath('/admin/blog'))}
            >
              {"Back"}
            </Button>
          }
        />
        <Card className="max-w-2xl">
          <CardBody className="p-6">
            <p className="text-center text-danger">
              {loadError || "Failed to load blog posts"}
            </p>
            <div className="mt-4 flex justify-center">
              <Button variant="flat" onPress={() => navigate(tenantPath('/admin/blog'))}>
                {"Back"}
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
        title={isEdit ? `${"Edit"}: ${post?.title}` : `${"Create"} ${"Blog"}`}
        actions={
          <Button
            variant="flat"
            startContent={<ArrowLeft size={16} />}
            onPress={() => navigate(tenantPath('/admin/blog'))}
          >
            {"Back"}
          </Button>
        }
      />

      <form onSubmit={handleSubmit}>
        <Card className="max-w-3xl">
          <CardBody className="gap-5 p-6">
            {/* Title */}
            <Input
              label={"Name"}
              placeholder={t('blog.placeholder_title', 'Enter post title')}
              value={title}
              onValueChange={setTitle}
              isRequired
              isInvalid={!!errors.title}
              errorMessage={errors.title}
              isDisabled={submitting}
            />

            {/* Slug */}
            <Input
              label={"Slug"}
              placeholder={t('blog.placeholder_slug', 'Auto-generated from title')}
              value={slug}
              onValueChange={setSlug}
              isDisabled={submitting}
              description={isEdit ? t('blog.slug_desc_edit', 'Edit to customize the URL. Leave as-is to keep current slug.') : t('blog.slug_desc_create', 'Auto-generated from title, or type a custom slug.')}
            />

            {/* Content */}
            <Suspense fallback={<Spinner size="sm" className="m-4" />}>
              <RichTextEditor
                label={"Content"}
                placeholder={t('blog.placeholder_content', 'Write the blog post content...')}
                value={content}
                onChange={setContent}
                isDisabled={submitting}
              />
            </Suspense>

            {/* Excerpt */}
            <Textarea
              label={t('blog.excerpt', 'Excerpt')}
              placeholder={t('blog.placeholder_excerpt', 'A short summary of the post')}
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
                label={"Status"}
                placeholder={t('blog.placeholder_status', 'Select status')}
                selectedKeys={status ? [status] : []}
                onSelectionChange={(keys) => {
                  const selected = Array.from(keys)[0] as string;
                  if (selected) setStatus(selected);
                }}
                isDisabled={submitting}
              >
                <SelectItem key="draft">{t('content.draft', 'Draft')}</SelectItem>
                <SelectItem key="published">{t('content.published', 'Published')}</SelectItem>
              </Select>

              {/* Category */}
              <Select
                label={"Categories"}
                placeholder={t('blog.placeholder_category', 'Select a category')}
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
              label={t('blog.featured_image', 'Featured Image URL')}
              placeholder="https://example.com/image.jpg"
              value={featuredImage}
              onValueChange={setFeaturedImage}
              isDisabled={submitting}
              description={t('blog.featured_image_desc', 'URL to the featured image for this post.')}
            />

            {/* SEO Override (Optional) */}
            <Divider />
            <div className="flex items-center gap-2 text-default-600">
              <Search size={16} />
              <span className="text-sm font-semibold">{t('blog.seo_override', 'SEO Override (Optional)')}</span>
            </div>
            <Input
              label={t('blog.meta_title', 'Meta Title')}
              placeholder={t('blog.placeholder_meta_title', 'Custom tab title for search engines')}
              value={metaTitle}
              onValueChange={setMetaTitle}
              isDisabled={submitting}
              description={t('blog.meta_title_desc', 'Overrides the page title in search results. Leave blank to use post title.')}
            />
            <Textarea
              label={t('blog.meta_description', 'Meta Description')}
              placeholder={t('blog.placeholder_meta_desc', 'Custom description for search engine results')}
              value={metaDescription}
              onValueChange={setMetaDescription}
              minRows={2}
              maxRows={3}
              isDisabled={submitting}
              description={t('blog.meta_desc_desc', 'Appears as the snippet in search results. Leave blank to use excerpt.')}
            />
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium">{t('blog.noindex', 'NoIndex (Hide from Google)')}</p>
                <p className="text-xs text-default-400">{t('blog.noindex_desc', 'Prevent search engines from indexing this post')}</p>
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
                {"Cancel"}
              </Button>
              <Button
                type="submit"
                color="primary"
                startContent={!submitting ? <Save size={16} /> : undefined}
                isLoading={submitting}
                isDisabled={submitting}
              >
                {isEdit ? "Save Changes" : `${"Create"} ${"Blog"}`}
              </Button>
            </div>
          </CardBody>
        </Card>
      </form>
    </div>
  );
}

export default BlogPostForm;
