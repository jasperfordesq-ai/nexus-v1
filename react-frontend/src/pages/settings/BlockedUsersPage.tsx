// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Avatar, Modal, ModalContent, ModalHeader, ModalBody, ModalFooter } from '@heroui/react';
import { ArrowLeft, ShieldOff, UserX } from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { EmptyState, LoadingScreen } from '@/components/feedback';
import { useToast, useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { PageMeta } from '@/components/seo';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl } from '@/lib/helpers';

interface BlockedUser {
  block_id: number;
  user_id: number;
  name: string;
  first_name: string;
  last_name: string;
  avatar_url: string | null;
  reason: string | null;
  blocked_at: string;
}

export function BlockedUsersPage() {
  const { t } = useTranslation('settings');
  usePageTitle(t('blocked_users.title'));
  const toast = useToast();
  const { tenantPath } = useTenant();
  const [blockedUsers, setBlockedUsers] = useState<BlockedUser[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [unblockTarget, setUnblockTarget] = useState<BlockedUser | null>(null);
  const [isUnblocking, setIsUnblocking] = useState(false);

  const loadBlockedUsers = useCallback(async () => {
    try {
      setIsLoading(true);
      const response = await api.get<BlockedUser[]>('/v2/users/blocked');
      if (response.success && response.data) {
        setBlockedUsers(response.data);
      }
    } catch (err) {
      logError('Failed to load blocked users', err);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    loadBlockedUsers();
  }, [loadBlockedUsers]);

  async function handleUnblock() {
    if (!unblockTarget) return;
    setIsUnblocking(true);
    try {
      const response = await api.delete(`/v2/users/${unblockTarget.user_id}/block`);
      if (response.success) {
        setBlockedUsers((prev) => prev.filter((u) => u.user_id !== unblockTarget.user_id));
        toast.success(
          t('blocked_users.unblocked'),
          t('blocked_users.unblocked_desc', { name: unblockTarget.name })
        );
      }
    } catch (err) {
      logError('Failed to unblock user', err);
      toast.error(t('error_title', { ns: 'common' }), t('blocked_users.unblock_error'));
    } finally {
      setIsUnblocking(false);
      setUnblockTarget(null);
    }
  }

  if (isLoading) {
    return <LoadingScreen />;
  }

  return (
    <div className="max-w-3xl mx-auto space-y-6">
      <PageMeta title={t('blocked_users.title')} noIndex />

      {/* Header */}
      <div className="flex items-center gap-4">
        <Link to={tenantPath('/settings?tab=privacy')}>
          <Button isIconOnly variant="light" aria-label={t('back', { ns: 'common' })}>
            <ArrowLeft className="w-5 h-5" />
          </Button>
        </Link>
        <div>
          <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-3">
            <ShieldOff className="w-7 h-7 text-red-500" />
            {t('blocked_users.title')}
          </h1>
          <p className="text-theme-muted mt-1">{t('blocked_users.subtitle')}</p>
        </div>
      </div>

      {/* List */}
      {blockedUsers.length === 0 ? (
        <EmptyState
          icon={<UserX className="w-12 h-12" />}
          title={t('blocked_users.empty')}
          description={t('blocked_users.empty_desc')}
        />
      ) : (
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          className="space-y-3"
        >
          {blockedUsers.map((user) => (
            <GlassCard key={user.user_id} className="p-4">
              <div className="flex items-center gap-4">
                <Avatar
                  src={resolveAvatarUrl(user.avatar_url)}
                  name={user.name}
                  size="md"
                  className="ring-2 ring-theme-default"
                />
                <div className="flex-1 min-w-0">
                  <h3 className="font-semibold text-theme-primary truncate">{user.name}</h3>
                  <p className="text-xs text-theme-subtle">
                    {t('blocked_users.blocked_on', {
                      date: new Date(user.blocked_at).toLocaleDateString(undefined, {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric',
                      }),
                    })}
                  </p>
                </div>
                <Button
                  size="sm"
                  variant="flat"
                  className="bg-red-500/10 text-red-600 dark:text-red-400"
                  onPress={() => setUnblockTarget(user)}
                >
                  {t('blocked_users.unblock')}
                </Button>
              </div>
            </GlassCard>
          ))}
        </motion.div>
      )}

      {/* Unblock confirmation modal */}
      <Modal
        isOpen={!!unblockTarget}
        onClose={() => setUnblockTarget(null)}
        classNames={{
          base: 'bg-content1 border border-theme-default',
          header: 'border-b border-theme-default',
        }}
      >
        <ModalContent>
          <ModalHeader className="text-theme-primary">
            {t('blocked_users.unblock_confirm', { name: unblockTarget?.name })}
          </ModalHeader>
          <ModalBody>
            <p className="text-theme-muted">
              {t('blocked_users.unblock_confirm_body')}
            </p>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={() => setUnblockTarget(null)}>
              {t('cancel', { ns: 'common' })}
            </Button>
            <Button
              color="primary"
              onPress={handleUnblock}
              isLoading={isUnblocking}
            >
              {t('blocked_users.unblock')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default BlockedUsersPage;
