// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Blog Page - Community news and blog posts
 *
 * Uses V2 API: GET /api/v2/blog, GET /api/v2/blog/categories
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import {
  Button,
  Chip,
  Input,
  Avatar,
} from '@heroui/react';
import {
  BookOpen,
  RefreshCw,
  AlertTriangle,
  Calendar,
  Clock,
  Eye,
  Search,
  User,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAssetUrl, resolveAvatarUrl } from '@/lib/helpers';
import { useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';

/* ───────────────────────── Types ───────────────────────── */

interface BlogPost {
  id: number;
  title: string;
  slug: string;
  excerpt: string;
  featured_image: string | null;
  published_at: string;
  created_at: string;
  views: number;
  reading_time: number;
  author: {
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

interface BlogCategory {
  id: number;
  name: string;
  slug: string;
  color: string;
  post_count: number;
}

/* ───────────────────────── Main Component ───────────────────────── */

export function BlogPage() {
  usePageTitle('Blog');
  const [posts, setPosts] = useState<BlogPost[]>([]);
  const [categories, setCategories] = useState<BlogCategory[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedCategory, setSelectedCategory] = useState<number | null>(null);
  const [hasMore, setHasMore] = useState(false);
  const [isPaginated, setIsPaginated] = useState(false);
  const [isLoadingMore, setIsLoadingMore] = useState(false);

  // Load categories on mount
  useEffect(() => {
    const loadCategories = async () => {
      try {
        const response = await api.get<BlogCategory[]>('/v2/blog/categories');
        if (response.success && response.data) {
          setCategories(Array.isArray(response.data) ? response.data : []);
        }
      } catch (err) {
        logError('Failed to load blog categories', err);
      }
    };
    loadCategories();
  }, []);

  const cursorRef = useRef<string | undefined>();

  const loadPosts = useCallback(async (append = false) => {
    try {
      if (append) {
        setIsLoadingMore(true);
      } else {
        setIsLoading(true);
        setError(null);
      }

      const params = new URLSearchParams();
      params.set('per_page', '12');
      if (append && cursorRef.current) params.set('cursor', cursorRef.current);
      if (searchQuery.trim()) params.set('search', searchQuery.trim());
      if (selectedCategory) params.set('category_id', String(selectedCategory));

      const response = await api.get<BlogPost[]>(
        `/v2/blog?${params}`
      );

      if (response.success && response.data) {
        const items = Array.isArray(response.data) ? response.data : [];

        if (append) {
          setPosts((prev) => [...prev, ...items]);
          setIsPaginated(true);
        } else {
          setPosts(items);
          setIsPaginated(false);
        }
        setHasMore(response.meta?.has_more ?? false);
        cursorRef.current = response.meta?.cursor ?? undefined;
      } else {
        if (!append) setError('Failed to load blog posts.');
      }
    } catch (err) {
      logError('Failed to load blog posts', err);
      if (!append) setError('Failed to load blog posts. Please try again.');
    } finally {
      setIsLoading(false);
      setIsLoadingMore(false);
    }
  }, [searchQuery, selectedCategory]);

  useEffect(() => {
    cursorRef.current = undefined;
    loadPosts();
  }, [searchQuery, selectedCategory, loadPosts]);

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: { opacity: 1, transition: { staggerChildren: 0.06 } },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 20 },
    visible: { opacity: 1, y: 0 },
  };

  const categoryColorMap: Record<string, string> = {
    blue: 'bg-blue-500/10 text-blue-500',
    gray: 'bg-gray-500/10 text-gray-500',
    fuchsia: 'bg-fuchsia-500/10 text-fuchsia-500',
    purple: 'bg-purple-500/10 text-purple-500',
    green: 'bg-emerald-500/10 text-emerald-500',
    red: 'bg-rose-500/10 text-rose-500',
    yellow: 'bg-amber-500/10 text-amber-500',
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-3">
          <BookOpen className="w-7 h-7 text-blue-400" aria-hidden="true" />
          Blog &amp; News
        </h1>
        <p className="text-theme-muted mt-1">Community stories, updates, and announcements</p>
      </div>

      {/* Search & Filters */}
      <div className="flex flex-col sm:flex-row gap-4">
        <div className="flex-1 max-w-md">
          <Input
            placeholder="Search posts..."
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
              className={!selectedCategory ? 'bg-gradient-to-r from-blue-500 to-indigo-600 text-white' : 'bg-theme-elevated text-theme-muted'}
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
                    ? 'bg-gradient-to-r from-blue-500 to-indigo-600 text-white'
                    : 'bg-theme-elevated text-theme-muted'
                }
                onPress={() => setSelectedCategory(cat.id)}
              >
                {cat.name}
                {cat.post_count > 0 && (
                  <span className="ml-1 opacity-70">({cat.post_count})</span>
                )}
              </Button>
            ))}
          </div>
        )}
      </div>

      {/* Error */}
      {error && !isLoading && (
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary mb-2">Unable to Load Posts</h2>
          <p className="text-theme-muted mb-4">{error}</p>
          <Button
            className="bg-gradient-to-r from-blue-500 to-indigo-600 text-white"
            startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
            onPress={() => loadPosts()}
          >
            Try Again
          </Button>
        </GlassCard>
      )}

      {/* Posts Grid */}
      {!error && (
        <>
          {isLoading ? (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              {[1, 2, 3, 4, 5, 6].map((i) => (
                <GlassCard key={i} className="overflow-hidden animate-pulse">
                  <div className="h-48 bg-theme-hover" />
                  <div className="p-5">
                    <div className="h-4 bg-theme-hover rounded w-1/4 mb-3" />
                    <div className="h-5 bg-theme-hover rounded w-3/4 mb-2" />
                    <div className="h-3 bg-theme-hover rounded w-full mb-1" />
                    <div className="h-3 bg-theme-hover rounded w-2/3 mb-4" />
                    <div className="h-3 bg-theme-hover rounded w-1/3" />
                  </div>
                </GlassCard>
              ))}
            </div>
          ) : posts.length === 0 ? (
            <EmptyState
              icon={<BookOpen className="w-12 h-12" aria-hidden="true" />}
              title="No posts found"
              description={
                searchQuery || selectedCategory
                  ? 'Try different search terms or clear your filters'
                  : 'No blog posts have been published yet'
              }
            />
          ) : (
            <>
              {/* Featured Post (first post gets larger treatment) */}
              {posts.length > 0 && !searchQuery && !selectedCategory && !isPaginated && (
                <motion.div
                  initial={{ opacity: 0, y: 20 }}
                  animate={{ opacity: 1, y: 0 }}
                >
                  <FeaturedPostCard post={posts[0]} categoryColors={categoryColorMap} />
                </motion.div>
              )}

              {/* Posts Grid */}
              <motion.div
                variants={containerVariants}
                initial="hidden"
                animate="visible"
                className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6"
              >
                {posts.slice(searchQuery || selectedCategory || isPaginated ? 0 : 1).map((post) => (
                  <motion.div key={post.id} variants={itemVariants}>
                    <BlogPostCard post={post} categoryColors={categoryColorMap} />
                  </motion.div>
                ))}
              </motion.div>

              {hasMore && (
                <div className="pt-4 text-center">
                  <Button
                    variant="flat"
                    className="bg-theme-elevated text-theme-muted"
                    onPress={() => loadPosts(true)}
                    isLoading={isLoadingMore}
                  >
                    Load More Posts
                  </Button>
                </div>
              )}
            </>
          )}
        </>
      )}
    </div>
  );
}

/* ───────────────────────── Featured Post Card ───────────────────────── */

interface PostCardProps {
  post: BlogPost;
  categoryColors: Record<string, string>;
}

function FeaturedPostCard({ post, categoryColors }: PostCardProps) {
  const { tenantPath } = useTenant();
  const imageUrl = post.featured_image ? resolveAssetUrl(post.featured_image) : null;

  return (
    <Link to={tenantPath(`/blog/${post.slug}`)} className="block group mb-6">
      <GlassCard className="overflow-hidden">
        <div className="flex flex-col md:flex-row">
          {/* Image */}
          <div className="md:w-1/2 h-48 md:h-72 bg-gradient-to-br from-blue-500/20 to-indigo-600/20 flex items-center justify-center overflow-hidden">
            {imageUrl ? (
              <img
                src={imageUrl}
                alt={post.title}
                className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                loading="lazy"
              />
            ) : (
              <BookOpen className="w-16 h-16 text-blue-300 opacity-50" aria-hidden="true" />
            )}
          </div>

          {/* Content */}
          <div className="md:w-1/2 p-4 sm:p-6 flex flex-col justify-center">
            {post.category && (
              <Chip
                size="sm"
                variant="flat"
                className={`mb-3 w-fit ${categoryColors[post.category.color] ?? categoryColors.blue}`}
              >
                {post.category.name}
              </Chip>
            )}
            <h2 className="text-xl font-bold text-theme-primary mb-2 group-hover:text-blue-500 transition-colors line-clamp-2">
              {post.title}
            </h2>
            <p className="text-sm text-theme-muted mb-4 line-clamp-3">{post.excerpt}</p>

            <div className="flex items-center gap-4 text-xs text-theme-subtle">
              <span className="flex items-center gap-1">
                <Avatar name={post.author.name} src={resolveAvatarUrl(post.author.avatar)} size="sm" className="w-5 h-5" />
                {post.author.name}
              </span>
              <span className="flex items-center gap-1">
                <Calendar className="w-3 h-3" aria-hidden="true" />
                {new Date(post.published_at).toLocaleDateString()}
              </span>
              <span className="flex items-center gap-1">
                <Clock className="w-3 h-3" aria-hidden="true" />
                {post.reading_time} min read
              </span>
            </div>
          </div>
        </div>
      </GlassCard>
    </Link>
  );
}

/* ───────────────────────── Blog Post Card ───────────────────────── */

function BlogPostCard({ post, categoryColors }: PostCardProps) {
  const { tenantPath } = useTenant();
  const imageUrl = post.featured_image ? resolveAssetUrl(post.featured_image) : null;

  return (
    <Link to={tenantPath(`/blog/${post.slug}`)} className="block group h-full">
      <GlassCard className="overflow-hidden h-full flex flex-col">
        {/* Image */}
        <div className="h-48 bg-gradient-to-br from-blue-500/10 to-indigo-600/10 flex items-center justify-center overflow-hidden">
          {imageUrl ? (
            <img
              src={imageUrl}
              alt={post.title}
              className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
              loading="lazy"
            />
          ) : (
            <BookOpen className="w-12 h-12 text-blue-300 opacity-30" aria-hidden="true" />
          )}
        </div>

        {/* Content */}
        <div className="p-5 flex-1 flex flex-col">
          {post.category && (
            <Chip
              size="sm"
              variant="flat"
              className={`mb-2 w-fit ${categoryColors[post.category.color] ?? categoryColors.blue}`}
            >
              {post.category.name}
            </Chip>
          )}

          <h3 className="font-semibold text-theme-primary group-hover:text-blue-500 transition-colors mb-2 line-clamp-2">
            {post.title}
          </h3>

          <p className="text-sm text-theme-muted mb-4 flex-1 line-clamp-3">{post.excerpt}</p>

          <div className="flex items-center justify-between text-xs text-theme-subtle mt-auto pt-3 border-t border-theme-default">
            <span className="flex items-center gap-1">
              <User className="w-3 h-3" aria-hidden="true" />
              {post.author.name}
            </span>
            <div className="flex items-center gap-3">
              <span className="flex items-center gap-1">
                <Clock className="w-3 h-3" aria-hidden="true" />
                {post.reading_time}m
              </span>
              {post.views > 0 && (
                <span className="flex items-center gap-1">
                  <Eye className="w-3 h-3" aria-hidden="true" />
                  {post.views}
                </span>
              )}
            </div>
          </div>
        </div>
      </GlassCard>
    </Link>
  );
}

export default BlogPage;
