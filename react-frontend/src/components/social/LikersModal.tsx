// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * LikersModal — Shows who liked a piece of content with pagination.
 */

import { useState, useEffect, useCallback } from 'react';
import { Link } from 'react-router-dom';
import {
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  Button,
  Avatar,
  Spinner,
} from '@heroui/react';
import { Heart } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useTenant } from '@/contexts';
import { resolveAvatarUrl, formatRelativeTime } from '@/lib/helpers';
import type { LikerUser, LikersResult } from '@/hooks/useSocialInteractions';

export interface LikersModalProps {
  isOpen: boolean;
  onClose: () => void;
  loadLikers: (page?: number) => Promise<LikersResult>;
  likesCount: number;
}

export function LikersModal({ isOpen, onClose, loadLikers, likesCount }: LikersModalProps) {
  const { t } = useTranslation('social');
  const { tenantPath } = useTenant();

  const [likers, setLikers] = useState<LikerUser[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [page, setPage] = useState(1);
  const [hasMore, setHasMore] = useState(false);
  const [totalCount, setTotalCount] = useState(likesCount);

  const load = useCallback(async (pageNum: number, append = false) => {
    setIsLoading(true);
    const result = await loadLikers(pageNum);
    if (append) {
      setLikers((prev) => [...prev, ...result.likers]);
    } else {
      setLikers(result.likers);
    }
    setTotalCount(result.total_count);
    setHasMore(result.has_more);
    setIsLoading(false);
  }, [loadLikers]);

  useEffect(() => {
    if (isOpen) {
      setPage(1);
      void load(1);
    }
  }, [isOpen, load]);

  const handleLoadMore = () => {
    const nextPage = page + 1;
    setPage(nextPage);
    void load(nextPage, true);
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      size="sm"
      scrollBehavior="inside"
      classNames={{
        base: 'bg-[var(--glass-bg)] backdrop-blur-xl border border-[var(--glass-border)]',
        backdrop: 'bg-black/60 backdrop-blur-sm',
      }}
    >
      <ModalContent>
        <ModalHeader className="text-[var(--text-primary)]">
          <div className="flex items-center gap-3">
            <div className="w-8 h-8 rounded-lg bg-rose-500/10 flex items-center justify-center">
              <Heart className="w-4 h-4 text-rose-500 fill-rose-500" aria-hidden="true" />
            </div>
            {t('likers_title', 'Liked by')} {totalCount > 0 && `(${totalCount})`}
          </div>
        </ModalHeader>
        <ModalBody className="pb-4">
          {isLoading && likers.length === 0 ? (
            <div className="flex justify-center py-8">
              <Spinner size="md" />
            </div>
          ) : likers.length === 0 ? (
            <p className="text-sm text-[var(--text-subtle)] text-center py-6 italic">
              {t('no_likes', 'No likes yet')}
            </p>
          ) : (
            <div className="space-y-2">
              {likers.map((liker) => (
                <Link
                  key={liker.id}
                  to={tenantPath(`/profile/${liker.id}`)}
                  onClick={onClose}
                  className="flex items-center gap-3 p-2 rounded-lg hover:bg-[var(--surface-hover)] transition-colors"
                >
                  <Avatar
                    name={liker.name}
                    src={resolveAvatarUrl(liker.avatar_url)}
                    size="sm"
                    className="ring-2 ring-white/10"
                  />
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium text-[var(--text-primary)] truncate">
                      {liker.name}
                    </p>
                    <p className="text-xs text-[var(--text-subtle)]">
                      {formatRelativeTime(liker.liked_at)}
                    </p>
                  </div>
                </Link>
              ))}

              {hasMore && (
                <div className="pt-2 text-center">
                  <Button
                    size="sm"
                    variant="flat"
                    className="bg-[var(--surface-elevated)] text-[var(--text-muted)]"
                    onPress={handleLoadMore}
                    isLoading={isLoading}
                  >
                    {t('load_more', 'Load More')}
                  </Button>
                </div>
              )}
            </div>
          )}
        </ModalBody>
      </ModalContent>
    </Modal>
  );
}

export default LikersModal;
