// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * CustomPage - Dynamic CMS page renderer
 *
 * Fetches and displays published pages created via the admin Page Builder.
 * Uses V2 API: GET /api/v2/pages/{slug}
 */

import { useState, useEffect, useCallback } from 'react';
import { useParams, Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Spinner } from '@heroui/react';
import { ArrowLeft, AlertTriangle, FileText } from 'lucide-react';
import DOMPurify from 'dompurify';
import { GlassCard } from '@/components/ui';
import { Breadcrumbs } from '@/components/navigation';
import { PageMeta } from '@/components/seo';
import { useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

interface PageData {
  id: number;
  title: string;
  slug: string;
  content: string;
  meta_description: string;
  created_at: string;
  updated_at: string;
}

export function CustomPage() {
  const { slug } = useParams<{ slug: string }>();
  const { tenantPath, branding, tenant, isLoading: tenantLoading } = useTenant();

  const [page, setPage] = useState<PageData | null>(null);
  const [loading, setLoading] = useState(true);
  const [notFound, setNotFound] = useState(false);

  usePageTitle(page?.title || 'Page');

  // tenantId captured as primitive to avoid object reference churn in deps
  const tenantId = tenant?.id ?? null;

  const loadPage = useCallback(async () => {
    // Wait for tenant bootstrap to complete before fetching — avoids stale closure
    // where tenantId is null and context_tenant is omitted from the request.
    if (!slug || tenantLoading) return;
    setLoading(true);
    setNotFound(false);

    try {
      // Include context_tenant so the PHP API can resolve the correct tenant for
      // unauthenticated requests (where X-Tenant-ID header is not available).
      const tenantParam = tenantId ? `?context_tenant=${tenantId}` : '';
      const res = await api.get<PageData>(`/v2/pages/${encodeURIComponent(slug)}${tenantParam}`);
      if (res.success && res.data) {
        setPage(res.data);
      } else {
        setNotFound(true);
      }
    } catch (err) {
      logError('Failed to load page', err);
      setNotFound(true);
    } finally {
      setLoading(false);
    }
  }, [slug, tenantId, tenantLoading]);

  useEffect(() => {
    loadPage();
  }, [loadPage]);

  if (loading) {
    return (
      <div className="flex justify-center items-center py-24">
        <Spinner size="lg" />
      </div>
    );
  }

  if (notFound || !page) {
    return (
      <div className="max-w-3xl mx-auto px-4 py-16 text-center">
        <PageMeta title="Page Not Found" />
        <GlassCard className="p-8">
          <AlertTriangle className="w-12 h-12 text-warning mx-auto mb-4" />
          <h1 className="text-2xl font-bold text-theme-primary mb-2">Page Not Found</h1>
          <p className="text-theme-secondary mb-6">
            The page you&apos;re looking for doesn&apos;t exist or is no longer available.
          </p>
          <Link to={tenantPath('/')}>
            <Button color="primary" startContent={<ArrowLeft size={16} />}>
              Back to Home
            </Button>
          </Link>
        </GlassCard>
      </div>
    );
  }

  return (
    <>
      <PageMeta
        title={`${page.title} | ${branding.name}`}
        description={page.meta_description || undefined}
      />

      <div className="max-w-4xl mx-auto px-4 py-8 space-y-6">
        <Breadcrumbs items={[
          { label: 'Home', href: tenantPath('/') },
          { label: page.title },
        ]} />

        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.4 }}
        >
          <GlassCard className="p-6 sm:p-8">
            <div className="flex items-center gap-3 mb-6">
              <FileText className="w-6 h-6 text-primary" />
              <h1 className="text-2xl sm:text-3xl font-bold text-theme-primary">
                {page.title}
              </h1>
            </div>

            {page.content && (
              <div
                className="
                  prose prose-neutral dark:prose-invert max-w-none
                  [&_a]:text-primary [&_a]:underline
                  [&_img]:rounded-xl [&_img]:max-w-full
                  [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:mt-8 [&_h2]:mb-4
                  [&_h3]:text-lg [&_h3]:font-semibold [&_h3]:mt-6 [&_h3]:mb-3
                  [&_ul]:list-disc [&_ul]:pl-6
                  [&_ol]:list-decimal [&_ol]:pl-6
                  [&_blockquote]:border-l-4 [&_blockquote]:border-primary [&_blockquote]:pl-4 [&_blockquote]:italic
                  [&_code]:bg-theme-elevated [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:rounded [&_code]:text-sm
                  [&_pre]:bg-theme-elevated [&_pre]:p-4 [&_pre]:rounded-xl [&_pre]:overflow-x-auto
                "
                dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(page.content) }}
              />
            )}
          </GlassCard>
        </motion.div>
      </div>
    </>
  );
}

export default CustomPage;
