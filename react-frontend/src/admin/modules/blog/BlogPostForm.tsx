/**
 * Admin Blog Post Form (Create / Edit)
 * Shared form for creating and editing blog posts.
 * Detects edit mode via URL param `:id`.
 * Parity: PHP Admin\BlogController::create() / edit()
 */

import { useState, useEffect, useCallback } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import {
  Card,
  CardBody,
  Input,
  Button,
  Select,
  SelectItem,
  Textarea,
  Spinner,
} from '@heroui/react';
import { RichTextEditor } from '../../components';
import { ArrowLeft, Save } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminBlog, adminCategories } from '../../api/adminApi';
import { PageHeader } from '../../components';
import type { AdminBlogPost, AdminCategory } from '../../api/types';

export function BlogPostForm() {
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

  // Categories for the dropdown
  const [categories, setCategories] = useState<AdminCategory[]>([]);

  // Validation
  const [errors, setErrors] = useState<Record<string, string>>({});

  // Dynamic page title
  usePageTitle(isEdit && post ? `Admin - Edit: ${post.title}` : 'Admin - Create Post');

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
      } else {
        setLoadError(res.error || 'Failed to load blog post');
      }
    } catch {
      setLoadError('An unexpected error occurred while loading the post');
    } finally {
      setLoading(false);
    }
  }, [id]);

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
      newErrors.title = 'Title is required';
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
        content,
        excerpt: excerpt.trim(),
        status: status as 'draft' | 'published',
        featured_image: featuredImage.trim() || undefined,
        category_id: categoryId ? Number(categoryId) : undefined,
      };

      const res = isEdit
        ? await adminBlog.update(Number(id), payload)
        : await adminBlog.create(payload);

      if (res.success) {
        toast.success(isEdit ? 'Post updated successfully' : 'Post created successfully');
        navigate(tenantPath('/admin/blog'));
      } else {
        toast.error(res.error || `Failed to ${isEdit ? 'update' : 'create'} post`);
      }
    } catch {
      toast.error('An unexpected error occurred');
    } finally {
      setSubmitting(false);
    }
  }

  // Loading state (edit mode)
  if (loading) {
    return (
      <div className="flex items-center justify-center py-20">
        <Spinner size="lg" label="Loading post..." />
      </div>
    );
  }

  // Error state (edit mode)
  if (isEdit && (loadError || !post)) {
    return (
      <div>
        <PageHeader
          title="Edit Post"
          actions={
            <Button
              variant="flat"
              startContent={<ArrowLeft size={16} />}
              onPress={() => navigate(tenantPath('/admin/blog'))}
            >
              Back to Blog
            </Button>
          }
        />
        <Card className="max-w-2xl">
          <CardBody className="p-6">
            <p className="text-center text-danger">
              {loadError || 'Blog post not found'}
            </p>
            <div className="mt-4 flex justify-center">
              <Button variant="flat" onPress={() => navigate(tenantPath('/admin/blog'))}>
                Return to Blog List
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
        title={isEdit ? `Edit Post: ${post?.title}` : 'Create Post'}
        actions={
          <Button
            variant="flat"
            startContent={<ArrowLeft size={16} />}
            onPress={() => navigate(tenantPath('/admin/blog'))}
          >
            Back to Blog
          </Button>
        }
      />

      <form onSubmit={handleSubmit}>
        <Card className="max-w-3xl">
          <CardBody className="gap-5 p-6">
            {/* Title */}
            <Input
              label="Title"
              placeholder="Enter post title"
              value={title}
              onValueChange={setTitle}
              isRequired
              isInvalid={!!errors.title}
              errorMessage={errors.title}
              isDisabled={submitting}
            />

            {/* Slug (read-only) */}
            <Input
              label="Slug"
              placeholder="Auto-generated from title"
              value={slug}
              isReadOnly
              isDisabled
              description={isEdit ? 'Slug will update automatically if title changes.' : 'Auto-generated from the title.'}
            />

            {/* Content */}
            <RichTextEditor
              label="Content"
              placeholder="Write the blog post content..."
              value={content}
              onChange={setContent}
              isDisabled={submitting}
            />

            {/* Excerpt */}
            <Textarea
              label="Excerpt"
              placeholder="A short summary of the post"
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
                label="Status"
                placeholder="Select status"
                selectedKeys={status ? [status] : []}
                onSelectionChange={(keys) => {
                  const selected = Array.from(keys)[0] as string;
                  if (selected) setStatus(selected);
                }}
                isDisabled={submitting}
              >
                <SelectItem key="draft">Draft</SelectItem>
                <SelectItem key="published">Published</SelectItem>
              </Select>

              {/* Category */}
              <Select
                label="Category"
                placeholder="Select a category"
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
              label="Featured Image URL"
              placeholder="https://example.com/image.jpg"
              value={featuredImage}
              onValueChange={setFeaturedImage}
              isDisabled={submitting}
              description="URL to the featured image for this post."
            />

            {/* Submit */}
            <div className="flex justify-end gap-3 pt-2">
              <Button
                variant="flat"
                onPress={() => navigate(tenantPath('/admin/blog'))}
                isDisabled={submitting}
              >
                Cancel
              </Button>
              <Button
                type="submit"
                color="primary"
                startContent={!submitting ? <Save size={16} /> : undefined}
                isLoading={submitting}
              >
                {isEdit ? 'Save Changes' : 'Create Post'}
              </Button>
            </div>
          </CardBody>
        </Card>
      </form>
    </div>
  );
}

export default BlogPostForm;
