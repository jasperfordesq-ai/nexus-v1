// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * PostAnalyticsModal — Shows analytics data for a post.
 * Only accessible by the post author or admins.
 */

import { useState, useEffect } from 'react';
import {
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  Card,
  CardBody,
  Spinner,
} from '@heroui/react';
import { Eye, Heart, MessageCircle, Repeat2, Users, BarChart3 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

interface PostAnalytics {
  post_id: number;
  views_count: number;
  likes_count: number;
  comments_count: number;
  shares_count: number;
  reactions_breakdown: Record<string, number>;
  reach_estimate: number;
}

export interface PostAnalyticsModalProps {
  isOpen: boolean;
  onClose: () => void;
  postId: number;
}

const REACTION_EMOJI: Record<string, string> = {
  like: '\u2764\uFE0F',
  love: '\uD83D\uDE0D',
  celebrate: '\uD83C\uDF89',
  support: '\uD83D\uDCAA',
  insightful: '\uD83D\uDCA1',
  funny: '\uD83D\uDE02',
};

export function PostAnalyticsModal({ isOpen, onClose, postId }: PostAnalyticsModalProps) {
  const { t } = useTranslation('feed');
  const [data, setData] = useState<PostAnalytics | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!isOpen) return;

    setLoading(true);
    setError(null);

    api.get<PostAnalytics>(`/v2/feed/posts/${postId}/analytics`)
      .then((res) => {
        if (res.success && res.data) {
          setData(res.data);
        } else {
          setError(res.error || t('failed_to_load_analytics'));
        }
      })
      .catch((err) => {
        logError('Failed to load post analytics', err);
        setError(t('failed_to_load_analytics'));
      })
      .finally(() => setLoading(false));
  }, [isOpen, postId]);

  const stats = data ? [
    { label: t('analytics.views', 'Views'), value: data.views_count, icon: Eye, color: 'text-blue-500', bg: 'bg-blue-500/10' },
    { label: t('analytics.likes', 'Likes'), value: data.likes_count, icon: Heart, color: 'text-rose-500', bg: 'bg-rose-500/10' },
    { label: t('analytics.comments', 'Comments'), value: data.comments_count, icon: MessageCircle, color: 'text-emerald-500', bg: 'bg-emerald-500/10' },
    { label: t('analytics.shares', 'Shares'), value: data.shares_count, icon: Repeat2, color: 'text-purple-500', bg: 'bg-purple-500/10' },
    { label: t('analytics.reach', 'Reach'), value: data.reach_estimate, icon: Users, color: 'text-amber-500', bg: 'bg-amber-500/10' },
  ] : [];

  return (
    <Modal isOpen={isOpen} onClose={onClose} size="lg">
      <ModalContent>
        <ModalHeader className="flex items-center gap-2">
          <BarChart3 className="w-5 h-5 text-[var(--color-primary)]" aria-hidden="true" />
          {t('analytics.title', 'Post Analytics')}
        </ModalHeader>
        <ModalBody className="pb-6">
          {loading ? (
            <div className="flex justify-center py-12">
              <Spinner size="lg" />
            </div>
          ) : error ? (
            <div className="text-center py-8 text-[var(--text-muted)]">
              <p>{error}</p>
            </div>
          ) : data ? (
            <div className="space-y-6">
              {/* Stat Cards */}
              <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                {stats.map((stat) => {
                  const Icon = stat.icon;
                  return (
                    <Card key={stat.label} shadow="none" className="border border-[var(--border-default)]">
                      <CardBody className="p-4 text-center">
                        <div className={`mx-auto w-10 h-10 rounded-full ${stat.bg} flex items-center justify-center mb-2`}>
                          <Icon className={`w-5 h-5 ${stat.color}`} aria-hidden="true" />
                        </div>
                        <p className="text-2xl font-bold text-[var(--text-primary)]">
                          {stat.value.toLocaleString()}
                        </p>
                        <p className="text-xs text-[var(--text-muted)]">{stat.label}</p>
                      </CardBody>
                    </Card>
                  );
                })}
              </div>

              {/* Reactions Breakdown */}
              {Object.keys(data.reactions_breakdown).length > 0 && (
                <div>
                  <h4 className="text-sm font-semibold text-[var(--text-primary)] mb-3">
                    {t('analytics.reactions_breakdown', 'Reactions Breakdown')}
                  </h4>
                  <div className="space-y-2">
                    {Object.entries(data.reactions_breakdown)
                      .sort(([, a], [, b]) => b - a)
                      .map(([type, count]) => {
                        const totalReactions = Object.values(data.reactions_breakdown).reduce((s, c) => s + c, 0);
                        const pct = totalReactions > 0 ? Math.round((count / totalReactions) * 100) : 0;
                        return (
                          <div key={type} className="flex items-center gap-3">
                            <span className="text-lg w-8 text-center" aria-hidden="true">
                              {REACTION_EMOJI[type] || type}
                            </span>
                            <div className="flex-1">
                              <div className="flex items-center justify-between mb-1">
                                <span className="text-xs text-[var(--text-secondary)] capitalize">{type}</span>
                                <span className="text-xs text-[var(--text-muted)]">{count} ({pct}%)</span>
                              </div>
                              <div className="h-2 rounded-full bg-[var(--surface-hover)] overflow-hidden">
                                <div
                                  className="h-full rounded-full bg-gradient-to-r from-[var(--color-primary)] to-[var(--color-primary-light,var(--color-primary))] transition-all"
                                  style={{ width: `${pct}%` }}
                                />
                              </div>
                            </div>
                          </div>
                        );
                      })}
                  </div>
                </div>
              )}
            </div>
          ) : null}
        </ModalBody>
      </ModalContent>
    </Modal>
  );
}
