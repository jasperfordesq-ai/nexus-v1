// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Avatar, Chip, Modal, ModalContent, ModalHeader, ModalBody, ModalFooter } from '@heroui/react';
import ArrowLeft from 'lucide-react/icons/arrow-left';
import ShieldOff from 'lucide-react/icons/shield-off';
import UserX from 'lucide-react/icons/user-x';
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
    <div className="mx-auto max-w-5xl space-y-6 p-4 sm:p-0">
      <PageMeta title={t('blocked_users.title')} noIndex />

      {/* Header */}
      <header className="overflow-hidden rounded-2xl border border-theme-default bg-theme-surface">
        <div className="flex flex-col gap-5 p-6 sm:p-8 lg:flex-row lg:items-center lg:justify-between">
          <div className="flex max-w-2xl items-start gap-4">
            <Link to={tenantPath('/settings?tab=privacy')}>
              <Button isIconOnly variant="flat" className="bg-theme-elevated text-theme-primary" aria-label={t('back', { ns: 'common' })}>
                <ArrowLeft className="h-5 w-5" />
              </Button>
            </Link>
            <div>
              <Chip size="sm" variant="flat" color="danger" className="mb-3 font-medium">
                {t('blocked_users.privacy_badge')}
              </Chip>
              <h1 className="flex items-center gap-3 text-3xl font-bold leading-tight text-theme-primary sm:text-4xl">
                <ShieldOff className="h-8 w-8 text-[var(--color-error)]" />
                {t('blocked_users.title')}
              </h1>
              <p className="mt-2 text-sm leading-6 text-theme-muted sm:text-base">{t('blocked_users.subtitle')}</p>
            </div>
          </div>
          <div className="rounded-xl border border-theme-default bg-theme-elevated px-4 py-3 lg:min-w-64">
            <span className="block text-xs font-medium uppercase tracking-wide text-theme-subtle">{t('blocked_users.summary_label')}</span>
            <span className="mt-1 block font-semibold text-theme-primary">{t('blocked_users.count', { count: blockedUsers.length })}</span>
          </div>
        </div>
      </header>

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
          className="grid gap-3"
        >
          {blockedUsers.map((user) => (
            <GlassCard key={user.user_id} className="p-4 sm:p-5">
              <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
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
                  className="bg-red-500/10 text-red-600 dark:text-red-400 sm:ml-auto"
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
