// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * SOC14 — AppreciationWallPage at /users/:userId/appreciations
 * Public feed of thank-you notes received by a user, with reaction buttons.
 */

import { useEffect, useState, useCallback } from 'react';
import { useParams, Link } from 'react-router-dom';
import { Avatar, Button, Card, CardBody, Pagination } from '@heroui/react';
import Heart from 'lucide-react/icons/heart';
import Sparkles from 'lucide-react/icons/sparkles';
import Star from 'lucide-react/icons/star';
import MessageCircleHeart from 'lucide-react/icons/message-circle-heart';
import { useTranslation } from 'react-i18next';
import { api } from '@/lib/api';
import { usePageTitle } from '@/hooks';
import { LoadingScreen, EmptyState } from '@/components/feedback';
import { useAuth, useTenant } from '@/contexts';

interface Appreciation {
  id: number;
  sender_id: number;
  receiver_id: number;
  message: string;
  is_public: boolean;
  reactions_count: number;
  created_at: string;
  sender?: { id: number; name: string | null; avatar_url: string | null } | null;
  my_reaction?: string | null;
}

const REACTIONS: Array<{ key: string; icon: React.ComponentType<{ className?: string }>; label: string }> = [
  { key: 'heart', icon: Heart, label: 'Heart' },
  { key: 'clap', icon: Sparkles, label: 'Clap' },
  { key: 'star', icon: Star, label: 'Star' },
];

export default function AppreciationWallPage() {
  const { t } = useTranslation('common');
  const { userId } = useParams<{ userId: string }>();
  const { tenantPath } = useTenant();
  const { user } = useAuth();
  const [items, setItems] = useState<Appreciation[]>([]);
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [loading, setLoading] = useState(true);
  usePageTitle(t('appreciations.wall_title'));

  const load = useCallback(async (p: number) => {
    if (!userId) return;
    setLoading(true);
    try {
      const res = await api.get<Appreciation[]>(`/v2/users/${userId}/appreciations?page=${p}`);
      if (res.success && Array.isArray(res.data)) {
        setItems(res.data);
        const meta = (res as unknown as { meta?: { total_pages?: number } }).meta;
        if (meta?.total_pages) setTotalPages(meta.total_pages);
      }
    } finally {
      setLoading(false);
    }
  }, [userId]);

  useEffect(() => {
    void load(page);
  }, [page, load]);

  const react = useCallback(async (apprId: number, reactionType: string) => {
    if (!user) return;
    try {
      await api.post(`/v2/appreciations/${apprId}/react`, { reaction_type: reactionType });
      // Optimistic refresh
      setItems((prev) =>
        prev.map((a) =>
          a.id === apprId
            ? {
                ...a,
                my_reaction: a.my_reaction === reactionType ? null : reactionType,
                reactions_count: a.my_reaction === reactionType
                  ? Math.max(0, a.reactions_count - 1)
                  : a.my_reaction
                    ? a.reactions_count
                    : a.reactions_count + 1,
              }
            : a,
        ),
      );
    } catch {
      // ignore
    }
  }, [user]);

  if (loading && items.length === 0) return <LoadingScreen />;

  return (
    <div className="container mx-auto px-4 py-6 max-w-2xl">
      <h1 className="text-2xl font-bold flex items-center gap-2 mb-6">
        <MessageCircleHeart className="w-6 h-6 text-[var(--color-primary)]" />
        {t('appreciations.wall_title')}
      </h1>

      {items.length === 0 ? (
        <EmptyState
          title={t('appreciations.empty_title')}
          description={t('appreciations.empty_desc')}
        />
      ) : (
        <div className="space-y-3">
          {items.map((a) => (
            <Card key={a.id}>
              <CardBody className="space-y-2">
                <div className="flex items-center gap-2">
                  <Avatar src={a.sender?.avatar_url ?? undefined} name={a.sender?.name ?? ''} size="sm" />
                  <Link to={tenantPath(`/profile/${a.sender_id}`)} className="font-medium hover:underline">
                    {a.sender?.name ?? t('common.someone')}
                  </Link>
                  <span className="text-xs text-[var(--text-muted)] ml-auto">
                    {new Date(a.created_at).toLocaleDateString()}
                  </span>
                </div>
                <p className="text-[var(--text-primary)]">{a.message}</p>
                <div className="flex items-center gap-2 pt-1">
                  {REACTIONS.map(({ key, icon: Icon }) => (
                    <Button
                      key={key}
                      size="sm"
                      variant={a.my_reaction === key ? 'flat' : 'light'}
                      color={a.my_reaction === key ? 'primary' : 'default'}
                      onPress={() => react(a.id, key)}
                      isDisabled={!user}
                      startContent={<Icon className="w-4 h-4" />}
                      aria-label={t(`appreciations.reaction_${key}`, key)}
                    >
                      {key === 'heart' ? t('appreciations.react_heart') :
                       key === 'clap' ? t('appreciations.react_clap') :
                       t('appreciations.react_star')}
                    </Button>
                  ))}
                  {a.reactions_count > 0 && (
                    <span className="text-sm text-[var(--text-muted)] ml-2">
                      {a.reactions_count}
                    </span>
                  )}
                </div>
              </CardBody>
            </Card>
          ))}
        </div>
      )}

      {totalPages > 1 && (
        <div className="flex justify-center mt-6">
          <Pagination total={totalPages} page={page} onChange={setPage} />
        </div>
      )}
    </div>
  );
}
