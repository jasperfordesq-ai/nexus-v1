// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * SubAccountsManager - Manage linked sub-accounts
 *
 * Allows parents to manage child accounts with permission toggles.
 * Used in the Settings page.
 *
 * API: GET/POST /api/v2/users/me/sub-accounts
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import {
  Button,
  Input,
  Switch,
  Avatar,
  Chip,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Spinner,
  useDisclosure,
} from '@heroui/react';
import {
  Users,
  Plus,
  UserPlus,
  Shield,
  Trash2,
  Mail,
  AlertTriangle,
  CheckCircle,
  Clock,
  RefreshCw,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl } from '@/lib/helpers';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface SubAccount {
  id: number;
  child_user_id: number;
  child_name: string;
  child_email: string;
  child_avatar?: string;
  status: 'pending' | 'approved' | 'rejected';
  permissions: {
    can_post: boolean;
    can_message: boolean;
    can_exchange: boolean;
    can_join_events: boolean;
    can_join_groups: boolean;
  };
  created_at: string;
}

// ─────────────────────────────────────────────────────────────────────────────
// Main Component
// ─────────────────────────────────────────────────────────────────────────────

export function SubAccountsManager() {
  const toast = useToast();
  const { t } = useTranslation('settings');
  const { isOpen, onOpen, onClose } = useDisclosure();

  const [subAccounts, setSubAccounts] = useState<SubAccount[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Add form state
  const [addEmail, setAddEmail] = useState('');
  const [addName, setAddName] = useState('');
  const [isAdding, setIsAdding] = useState(false);

  // AbortController ref to cancel stale requests
  const abortRef = useRef<AbortController | null>(null);

  // Stable refs for t/toast — avoids re-creating callbacks when i18n namespace loads
  const tRef = useRef(t);
  tRef.current = t;
  const toastRef = useRef(toast);
  toastRef.current = toast;

  const loadSubAccounts = useCallback(async () => {
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    try {
      setIsLoading(true);
      setError(null);
      const response = await api.get<SubAccount[]>('/v2/users/me/sub-accounts');
      if (controller.signal.aborted) return;
      if (response.success && response.data) {
        setSubAccounts(response.data);
      } else {
        setError(tRef.current('sub_accounts.load_failed'));
      }
    } catch (err) {
      if (controller.signal.aborted) return;
      logError('Failed to load sub-accounts', err);
      setError(tRef.current('sub_accounts.load_failed'));
    } finally {
      if (!controller.signal.aborted) {
        setIsLoading(false);
      }
    }
  }, []);

  useEffect(() => {
    loadSubAccounts();
  }, [loadSubAccounts]);

  // Add sub-account
  const handleAdd = async () => {
    if (!addEmail.trim()) {
      toastRef.current.error(tRef.current('toasts.subaccount_enter_email'));
      return;
    }

    try {
      setIsAdding(true);
      const response = await api.post('/v2/users/me/sub-accounts', {
        email: addEmail.trim(),
        name: addName.trim() || undefined,
      });

      if (response.success) {
        toastRef.current.success(tRef.current('toasts.subaccount_request_sent'));
        setAddEmail('');
        setAddName('');
        onClose();
        loadSubAccounts();
      } else {
        toastRef.current.error(response.error || tRef.current('sub_accounts.add_failed'));
      }
    } catch (err) {
      logError('Failed to add sub-account', err);
      toastRef.current.error(tRef.current('toasts.subaccount_send_failed'));
    } finally {
      setIsAdding(false);
    }
  };

  // Update permissions
  const handlePermissionChange = async (accountId: number, permission: string, value: boolean) => {
    try {
      const response = await api.put(`/v2/users/me/sub-accounts/${accountId}/permissions`, {
        [permission]: value,
      });

      if (response.success) {
        setSubAccounts((prev) =>
          prev.map((sa) =>
            sa.id === accountId
              ? { ...sa, permissions: { ...sa.permissions, [permission]: value } }
              : sa
          )
        );
      } else {
        toastRef.current.error(response.error || tRef.current('toasts.subaccount_permission_failed'));
      }
    } catch (err) {
      logError('Failed to update permission', err);
      toastRef.current.error(tRef.current('toasts.subaccount_permission_failed'));
    }
  };

  // Remove sub-account
  const handleRemove = async (accountId: number) => {
    try {
      const response = await api.delete(`/v2/users/me/sub-accounts/${accountId}`);
      if (response.success) {
        toastRef.current.success(tRef.current('toasts.subaccount_removed'));
        setSubAccounts((prev) => prev.filter((sa) => sa.id !== accountId));
      } else {
        toastRef.current.error(response.error || tRef.current('sub_accounts.remove_failed'));
      }
    } catch (err) {
      logError('Failed to remove sub-account', err);
      toastRef.current.error(tRef.current('toasts.subaccount_remove_failed'));
    }
  };

  // Approve pending request
  const handleApprove = async (accountId: number) => {
    try {
      const response = await api.put(`/v2/users/me/sub-accounts/${accountId}/approve`);
      if (response.success) {
        toastRef.current.success(tRef.current('toasts.subaccount_approved'));
        loadSubAccounts();
      } else {
        toastRef.current.error(response.error || tRef.current('sub_accounts.approve_failed'));
      }
    } catch (err) {
      logError('Failed to approve sub-account', err);
      toastRef.current.error(tRef.current('toasts.subaccount_approve_failed'));
    }
  };

  const statusConfig: Record<string, { label: string; color: 'success' | 'warning' | 'danger'; icon: React.ReactNode }> = {
    approved: { label: t('sub_accounts.status_active'), color: 'success', icon: <CheckCircle className="w-3 h-3" /> },
    pending: { label: t('sub_accounts.status_pending'), color: 'warning', icon: <Clock className="w-3 h-3" /> },
    rejected: { label: t('sub_accounts.status_rejected'), color: 'danger', icon: <AlertTriangle className="w-3 h-3" /> },
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <Users className="w-5 h-5 text-indigo-500" aria-hidden="true" />
          <h3 className="font-semibold text-theme-primary">{t('sub_accounts.title')}</h3>
        </div>
        <Button
          size="sm"
          variant="flat"
          className="bg-indigo-500/10 text-indigo-600 dark:text-indigo-400"
          startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
          onPress={onOpen}
        >
          {t('sub_accounts.add_button')}
        </Button>
      </div>

      <p className="text-sm text-theme-subtle">
        {t('sub_accounts.description')}
      </p>

      {/* Loading */}
      {isLoading && (
        <div className="flex justify-center py-8">
          <Spinner size="lg" />
        </div>
      )}

      {/* Error */}
      {error && !isLoading && (
        <GlassCard className="p-6 text-center">
          <AlertTriangle className="w-8 h-8 text-amber-500 mx-auto mb-3" aria-hidden="true" />
          <p className="text-sm text-theme-muted mb-3">{error}</p>
          <Button
            size="sm"
            variant="flat"
            startContent={<RefreshCw className="w-3 h-3" aria-hidden="true" />}
            onPress={loadSubAccounts}
          >
            {t('sub_accounts.retry')}
          </Button>
        </GlassCard>
      )}

      {/* Sub Accounts List */}
      {!isLoading && !error && (
        <>
          {subAccounts.length === 0 ? (
            <EmptyState
              icon={<UserPlus className="w-10 h-10" aria-hidden="true" />}
              title={t('sub_accounts.empty_title')}
              description={t('sub_accounts.empty_description')}
              action={
                <Button
                  variant="flat"
                  className="bg-indigo-500/10 text-indigo-600 dark:text-indigo-400"
                  startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
                  onPress={onOpen}
                >
                  {t('sub_accounts.add_button')}
                </Button>
              }
            />
          ) : (
            <div className="space-y-3">
              {subAccounts.map((account) => {
                const status = statusConfig[account.status] ?? statusConfig['pending'] ?? { label: account.status, color: 'warning' as const, icon: null };
                return (
                  <GlassCard key={account.id} className="p-4">
                    <div className="flex items-start gap-4">
                      <Avatar
                        src={resolveAvatarUrl(account.child_avatar)}
                        name={account.child_name}
                        size="md"
                        className="ring-2 ring-theme-muted/20 flex-shrink-0"
                      />

                      <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-2 mb-1">
                          <h4 className="font-medium text-theme-primary">{account.child_name}</h4>
                          <Chip
                            size="sm"
                            variant="flat"
                            color={status.color}
                            startContent={status.icon}
                          >
                            {status.label}
                          </Chip>
                        </div>
                        <p className="text-xs text-theme-subtle">{account.child_email}</p>

                        {/* Permissions */}
                        {account.status === 'approved' && (
                          <div className="mt-3 space-y-2">
                            <p className="text-xs font-medium text-theme-muted flex items-center gap-1">
                              <Shield className="w-3 h-3" aria-hidden="true" />
                              {t('sub_accounts.permissions_label')}
                            </p>
                            <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
                              {Object.entries(account.permissions).map(([key, value]) => (
                                <div key={key} className="flex items-center gap-2">
                                  <Switch
                                    size="sm"
                                    isSelected={value}
                                    onValueChange={(v) => handlePermissionChange(account.id, key, v)}
                                    aria-label={key.replace(/_/g, ' ')}
                                  />
                                  <span className="text-xs text-theme-muted capitalize">
                                    {key.replace(/can_/g, '').replace(/_/g, ' ')}
                                  </span>
                                </div>
                              ))}
                            </div>
                          </div>
                        )}

                        {/* Pending actions */}
                        {account.status === 'pending' && (
                          <div className="mt-3 flex gap-2">
                            <Button
                              size="sm"
                              color="success"
                              variant="flat"
                              onPress={() => handleApprove(account.id)}
                            >
                              {t('sub_accounts.approve')}
                            </Button>
                            <Button
                              size="sm"
                              color="danger"
                              variant="flat"
                              onPress={() => handleRemove(account.id)}
                            >
                              {t('sub_accounts.decline')}
                            </Button>
                          </div>
                        )}
                      </div>

                      {/* Remove button */}
                      <Button
                        isIconOnly
                        size="sm"
                        variant="light"
                        color="danger"
                        onPress={() => handleRemove(account.id)}
                        aria-label={t('sub_accounts.remove_aria', { name: account.child_name })}
                      >
                        <Trash2 className="w-4 h-4" />
                      </Button>
                    </div>
                  </GlassCard>
                );
              })}
            </div>
          )}
        </>
      )}

      {/* Add Sub-Account Modal */}
      <Modal
        isOpen={isOpen}
        onClose={onClose}
        classNames={{
          base: 'bg-[var(--glass-bg)] backdrop-blur-xl border border-[var(--glass-border)]',
          backdrop: 'bg-black/60 backdrop-blur-sm',
        }}
      >
        <ModalContent>
          <ModalHeader className="text-theme-primary">
            <div className="flex items-center gap-3">
              <div className="w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-500/20 to-purple-500/20 flex items-center justify-center">
                <UserPlus className="w-4 h-4 text-indigo-500" aria-hidden="true" />
              </div>
              {t('sub_accounts.modal_title')}
            </div>
          </ModalHeader>
          <ModalBody>
            <p className="text-sm text-theme-muted mb-3">
              {t('sub_accounts.modal_description')}
            </p>
            <Input
              label={t('sub_accounts.email_label')}
              placeholder={t('sub_accounts.email_placeholder')}
              value={addEmail}
              onChange={(e) => setAddEmail(e.target.value)}
              type="email"
              startContent={<Mail className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default',
              }}
              autoFocus
            />
            <Input
              label={t('sub_accounts.name_label')}
              placeholder={t('sub_accounts.name_placeholder')}
              value={addName}
              onChange={(e) => setAddName(e.target.value)}
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default',
              }}
            />
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onClose} className="text-theme-muted">
              {t('sub_accounts.cancel')}
            </Button>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              onPress={handleAdd}
              isLoading={isAdding}
              isDisabled={!addEmail.trim()}
            >
              {t('sub_accounts.send_request')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default SubAccountsManager;
