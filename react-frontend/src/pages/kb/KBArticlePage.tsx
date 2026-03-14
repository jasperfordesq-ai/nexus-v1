// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * R4 - Knowledge Base Article Page
 *
 * Displays a single KB article with:
 * - Full content rendering
 * - Nested child articles navigation
 * - Parent breadcrumb
 * - "Was this helpful?" feedback buttons
 *
 * API: GET /api/v2/kb/{id}
 *      POST /api/v2/kb/{id}/feedback
 */

import { useState, useEffect, useCallback } from 'react';
import { useParams, Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import {
  Button,
  Chip,
  Spinner,
  Divider,
} from '@heroui/react';
import {
  BookOpen,
  ChevronRight,
  ThumbsUp,
  ThumbsDown,
  Clock,
  Eye,
  FileText,
  ArrowLeft,
  Folder,
  RefreshCw,
  AlertTriangle,
  CheckCircle,
} from 'lucide-react';
import DOMPurify from 'dompurify';
import { GlassCard } from '@/components/ui';
import { useTenant, useToast } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { formatRelativeTime } from '@/lib/helpers';

import { useTranslation } from 'react-i18next';
/* ───────────────────────── Types ───────────────────────── */

interface KBArticleFull {
  id: number;
  title: string;
  slug: string;
  content: string;
  excerpt: string | null;
  category: string | null;
  parent_id: number | null;
  parent_title: string | null;
  is_published: boolean;
  view_count: number;
  helpful_count: number;
  not_helpful_count: number;
  created_at: string;
  updated_at: string;
  children: KBChild[];
}

interface KBChild {
  id: number;
  title: string;
  slug: string;
  excerpt: string | null;
}

/* ───────────────────────── Component ───────────────────────── */

export function KBArticlePage() {
  const { id } = useParams<{ id: string }>();
  const { tenantPath } = useTenant();
  const toast = useToast();
  const { t } = useTranslation('kb');

  const [article, setArticle] = useState<KBArticleFull | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [feedbackGiven, setFeedbackGiven] = useState<'helpful' | 'not_helpful' | null>(null);
  const [isSubmittingFeedback, setIsSubmittingFeedback] = useState(false);

  usePageTitle(article?.title || t('title'));

  const loadArticle = useCallback(async () => {
    if (!id) return;
    try {
      setIsLoading(true);
      setError(null);
      setFeedbackGiven(null);
      const response = await api.get<KBArticleFull>(`/v2/kb/${id}`);
      if (response.success && response.data) {
        setArticle(response.data);
      } else {
        setError(t('error.article_not_found'));
      }
    } catch (err) {
      logError('Failed to load KB article', err);
      setError(t('error.article_load_retry'));
    } finally {
      setIsLoading(false);
    }
  }, [id]);

  useEffect(() => {
    loadArticle();
  }, [loadArticle]);

  const handleFeedback = async (isHelpful: boolean) => {
    if (!article || feedbackGiven) return;

    try {
      setIsSubmittingFeedback(true);
      const response = await api.post(`/v2/kb/${article.id}/feedback`, {
        is_helpful: isHelpful,
      });

      if (response.success) {
        setFeedbackGiven(isHelpful ? 'helpful' : 'not_helpful');
        toast.success(t('feedback_thanks'));
        // Update local counts
        setArticle((prev) => {
          if (!prev) return prev;
          return {
            ...prev,
            helpful_count: prev.helpful_count + (isHelpful ? 1 : 0),
            not_helpful_count: prev.not_helpful_count + (!isHelpful ? 1 : 0),
          };
        });
      }
    } catch (err) {
      logError('Failed to submit KB feedback', err);
      toast.error(t('feedback_failed'));
    } finally {
      setIsSubmittingFeedback(false);
    }
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-20">
        <Spinner size="lg" color="primary" />
      </div>
    );
  }

  if (error) {
    return (
      <div className="space-y-6">
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary mb-2">{t('error.article_title')}</h2>
          <p className="text-theme-muted mb-4">{error}</p>
          <div className="flex gap-2 justify-center">
            <Link to={tenantPath('/kb')}>
              <Button
                variant="flat"
                className="bg-theme-elevated text-theme-primary"
                startContent={<ArrowLeft className="w-4 h-4" aria-hidden="true" />}
              >
                {t('back_to_kb')}
              </Button>
            </Link>
            <Button
              className="bg-gradient-to-r from-blue-500 to-indigo-600 text-white"
              startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
              onPress={loadArticle}
            >
              {t("try_again")}
            </Button>
          </div>
        </GlassCard>
      </div>
    );
  }

  if (!article) return null;

  return (
    <div className="space-y-6 max-w-4xl mx-auto">
      {/* Breadcrumb */}
      <nav className="flex items-center gap-2 text-sm text-theme-muted flex-wrap">
        <Link
          to={tenantPath('/kb')}
          className="hover:text-theme-primary transition-colors flex items-center gap-1"
        >
          <BookOpen className="w-3.5 h-3.5" aria-hidden="true" />
          {t("title")}
        </Link>
        {article.category && (
          <>
            <ChevronRight className="w-3 h-3 text-theme-subtle" aria-hidden="true" />
            <span className="text-theme-subtle">{article.category}</span>
          </>
        )}
        {article.parent_id && article.parent_title && (
          <>
            <ChevronRight className="w-3 h-3 text-theme-subtle" aria-hidden="true" />
            <Link
              to={tenantPath(`/kb/${article.parent_id}`)}
              className="hover:text-theme-primary transition-colors"
            >
              {article.parent_title}
            </Link>
          </>
        )}
        <ChevronRight className="w-3 h-3 text-theme-subtle" aria-hidden="true" />
        <span className="text-theme-primary font-medium truncate">{article.title}</span>
      </nav>

      {/* Article Header */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
      >
        <GlassCard className="p-6 sm:p-8">
          <div className="mb-4">
            <h1 className="text-2xl sm:text-3xl font-bold text-theme-primary mb-3">
              {article.title}
            </h1>
            <div className="flex items-center gap-3 flex-wrap text-xs text-theme-subtle">
              {article.category && (
                <Chip size="sm" variant="flat" className="bg-blue-500/10 text-blue-400">
                  {article.category}
                </Chip>
              )}
              <span className="flex items-center gap-1">
                <Clock className="w-3 h-3" aria-hidden="true" />
                {t("updated", { time: formatRelativeTime(article.updated_at) })}
              </span>
              <span className="flex items-center gap-1">
                <Eye className="w-3 h-3" aria-hidden="true" />
                {t("views", { count: article.view_count })}
              </span>
            </div>
          </div>

          <Divider className="my-4" />

          {/* Article Content */}
          <div
            className="prose prose-sm dark:prose-invert max-w-none text-theme-primary
              prose-headings:text-theme-primary prose-p:text-theme-secondary
              prose-a:text-blue-400 prose-a:no-underline hover:prose-a:underline
              prose-strong:text-theme-primary prose-code:text-blue-400
              prose-pre:bg-theme-elevated prose-pre:border prose-pre:border-theme-default
              prose-img:rounded-lg prose-blockquote:border-blue-400
              prose-li:text-theme-secondary"
            dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(article.content) }}
          />
        </GlassCard>
      </motion.div>

      {/* Child Articles */}
      {article.children && article.children.length > 0 && (
        <motion.div
          initial={{ opacity: 0, y: 15 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.1 }}
        >
          <GlassCard className="overflow-hidden">
            <div className="px-5 py-3 bg-theme-hover/30 border-b border-theme-default">
              <h2 className="text-sm font-semibold text-theme-primary flex items-center gap-2">
                <Folder className="w-4 h-4 text-blue-400" aria-hidden="true" />
                {t("related_articles")}
                <Chip size="sm" variant="flat" className="text-[10px] bg-theme-elevated text-theme-subtle">
                  {article.children.length}
                </Chip>
              </h2>
            </div>
            <div className="divide-y divide-theme-default">
              {article.children.map((child) => (
                <Link
                  key={child.id}
                  to={tenantPath(`/kb/${child.id}`)}
                  className="flex items-center gap-4 px-5 py-3 hover:bg-theme-hover/30 transition-colors group"
                >
                  <div className="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center flex-shrink-0">
                    <FileText className="w-4 h-4 text-blue-400" aria-hidden="true" />
                  </div>
                  <div className="flex-1 min-w-0">
                    <h3 className="text-sm font-medium text-theme-primary group-hover:text-blue-400 transition-colors">
                      {child.title}
                    </h3>
                    {child.excerpt && (
                      <p className="text-xs text-theme-muted line-clamp-1 mt-0.5">{child.excerpt}</p>
                    )}
                  </div>
                  <ChevronRight className="w-4 h-4 text-theme-subtle group-hover:text-blue-400 transition-colors flex-shrink-0" aria-hidden="true" />
                </Link>
              ))}
            </div>
          </GlassCard>
        </motion.div>
      )}

      {/* Feedback Section */}
      <motion.div
        initial={{ opacity: 0, y: 15 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.2 }}
      >
        <GlassCard className="p-5 text-center">
          {feedbackGiven ? (
            <div className="flex items-center justify-center gap-2">
              <CheckCircle className="w-5 h-5 text-emerald-400" aria-hidden="true" />
              <p className="text-sm text-theme-primary font-medium">
                {t('feedback_thanks')}
              </p>
            </div>
          ) : (
            <>
              <p className="text-sm text-theme-primary font-medium mb-3">
                {t('feedback.question')}
              </p>
              <div className="flex items-center justify-center gap-3">
                <Button
                  variant="flat"
                  className="bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/20"
                  startContent={<ThumbsUp className="w-4 h-4" aria-hidden="true" />}
                  onPress={() => handleFeedback(true)}
                  isLoading={isSubmittingFeedback}
                >
                  {t('feedback.yes', { count: article.helpful_count })}
                </Button>
                <Button
                  variant="flat"
                  className="bg-red-500/10 text-red-400 hover:bg-red-500/20"
                  startContent={<ThumbsDown className="w-4 h-4" aria-hidden="true" />}
                  onPress={() => handleFeedback(false)}
                  isLoading={isSubmittingFeedback}
                >
                  {t('feedback.no', { count: article.not_helpful_count })}
                </Button>
              </div>
            </>
          )}
        </GlassCard>
      </motion.div>

      {/* Back link */}
      <div className="flex justify-center">
        <Link to={tenantPath('/kb')}>
          <Button
            variant="flat"
            className="bg-theme-elevated text-theme-muted"
            startContent={<ArrowLeft className="w-4 h-4" aria-hidden="true" />}
          >
            {t('back_to_kb')}
          </Button>
        </Link>
      </div>
    </div>
  );
}

export default KBArticlePage;
