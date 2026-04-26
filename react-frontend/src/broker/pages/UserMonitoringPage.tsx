// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * User Monitoring
 * View users currently under messaging monitoring restrictions.
 * Parity: PHP BrokerControlsController::monitoring()
 */

import { useState, useCallback, useEffect, useRef } from 'react';
import { Link } from 'react-router-dom';
import {
  Button,
  Chip,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Input,
  Textarea,
  Switch,
  Select,
  SelectItem,
  Avatar,
  Spinner,
} from '@heroui/react';
import ArrowLeft from 'lucide-react/icons/arrow-left';
import Eye from 'lucide-react/icons/eye';
import MessageCircleOff from 'lucide-react/icons/message-circle-off';
import UserPlus from 'lucide-react/icons/user-plus';
import UserMinus from 'lucide-react/icons/user-minus';
import X from 'lucide-react/icons/x';
import Search from 'lucide-react/icons/search';
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { resolveAvatarUrl } from '@/lib/helpers';
import { adminBroker, adminUsers } from '@/admin/api/adminApi';
import { DataTable, PageHeader, EmptyState, type Column } from '@/admin/components';
import type { MonitoredUser, AdminUser } from '@/admin/api/types';

export function UserMonitoring() {
  const { t } = useTranslation('broker');
  usePageTitle(t('monitoring.title'));
  const { tenantPath } = useTenant();
  const toast = useToast();

  const [items, setItems] = useState<MonitoredUser[]>([]);
  const [loading, setLoading] = useState(true);

  // Add to monitoring modal state
  const [monitoringModalOpen, setMonitoringModalOpen] = useState(false);
  const [monitoringReason, setMonitoringReason] = useState('');
  const [messagingDisabled, setMessagingDisabled] = useState(false);
  const [expiresDays, setExpiresDays] = useState('');
  const [monitoringLoading, setMonitoringLoading] = useState(false);
  const [removingId, setRemovingId] = useState<number | null>(null);

  // User search state
  const [selectedUser, setSelectedUser] = useState<AdminUser | null>(null);
  const [userSearchQuery, setUserSearchQuery] = useState('');
  const [userSearchResults, setUserSearchResults] = useState<AdminUser[]>([]);
  const [isSearching, setIsSearching] = useState(false);
  const [showDropdown, setShowDropdown] = useState(false);
  const [highlightedIndex, setHighlightedIndex] = useState(-1);
  const searchTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const dropdownRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  const loadItems = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminBroker.getMonitoring();
      if (res.success && Array.isArray(res.data)) {
        setItems(res.data);
      }
    } catch {
      toast.error(t('monitoring.load_failed'));
    } finally {
      setLoading(false);
    }
  }, [toast, t])


  useEffect(() => {
    loadItems();
  }, [loadItems]);

  // Debounced user search
  const handleUserSearch = useCallback((query: string) => {
    setUserSearchQuery(query);
    setHighlightedIndex(-1);

    if (searchTimeoutRef.current) {
      clearTimeout(searchTimeoutRef.current);
    }

    if (!query || query.length < 2) {
      setUserSearchResults([]);
      setShowDropdown(false);
      return;
    }

    searchTimeoutRef.current = setTimeout(async () => {
      setIsSearching(true);
      try {
        const res = await adminUsers.list({ search: query, limit: 10 });
        if (res.success && res.data) {
          const results = Array.isArray(res.data) ? res.data : (res.data as { items?: AdminUser[] }).items ?? [];
          setUserSearchResults(results);
          setShowDropdown(results.length > 0);
        }
      } catch {
        setUserSearchResults([]);
      } finally {
        setIsSearching(false);
      }
    }, 300);
  }, []);

  // Click outside to close dropdown
  useEffect(() => {
    const handleClickOutside = (e: MouseEvent) => {
      if (dropdownRef.current && !dropdownRef.current.contains(e.target as Node)) {
        setShowDropdown(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  // Keyboard navigation
  const handleKeyDown = useCallback((e: React.KeyboardEvent) => {
    if (!showDropdown || userSearchResults.length === 0) return;

    if (e.key === 'ArrowDown') {
      e.preventDefault();
      setHighlightedIndex((prev) => Math.min(prev + 1, userSearchResults.length - 1));
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      setHighlightedIndex((prev) => Math.max(prev - 1, 0));
    } else if (e.key === 'Enter' && highlightedIndex >= 0) {
      e.preventDefault();
      const user = userSearchResults[highlightedIndex];
      if (user) {
        setSelectedUser(user);
        setShowDropdown(false);
        setUserSearchQuery('');
        setUserSearchResults([]);
      }
    } else if (e.key === 'Escape') {
      setShowDropdown(false);
    }
  }, [showDropdown, userSearchResults, highlightedIndex]);

  const selectUser = useCallback((user: AdminUser) => {
    setSelectedUser(user);
    setShowDropdown(false);
    setUserSearchQuery('');
    setUserSearchResults([]);
  }, []);

  const clearSelectedUser = useCallback(() => {
    setSelectedUser(null);
    setUserSearchQuery('');
    setUserSearchResults([]);
    // Focus the search input after clearing
    setTimeout(() => inputRef.current?.focus(), 50);
  }, []);

  const resetModalState = useCallback(() => {
    setMonitoringModalOpen(false);
    setSelectedUser(null);
    setUserSearchQuery('');
    setUserSearchResults([]);
    setMonitoringReason('');
    setMessagingDisabled(false);
    setExpiresDays('');
    setShowDropdown(false);
    setHighlightedIndex(-1);
  }, []);

  const handleAddMonitoring = async () => {
    if (!selectedUser) {
      toast.error(t('monitoring.select_user_required'));
      return;
    }
    if (!monitoringReason.trim()) {
      toast.error(t('monitoring.reason_required'));
      return;
    }
    setMonitoringLoading(true);
    try {
      const res = await adminBroker.setMonitoring(selectedUser.id, {
        under_monitoring: true,
        reason: monitoringReason,
        messaging_disabled: messagingDisabled,
        ...(expiresDays ? { expires_days: Number(expiresDays) } : {}),
      });
      if (res?.success) {
        toast.success(t('monitoring.add_success'));
        resetModalState();
        loadItems();
      } else {
        toast.error(res?.error || t('monitoring.add_failed'));
      }
    } catch {
      toast.error(t('monitoring.add_failed'));
    } finally {
      setMonitoringLoading(false);
    }
  };

  const handleRemoveMonitoring = async (userId: number) => {
    if (!window.confirm(t('monitoring.confirm_remove'))) return;
    setRemovingId(userId);
    try {
      const res = await adminBroker.setMonitoring(userId, { under_monitoring: false });
      if (res?.success) {
        toast.success(t('monitoring.remove_success'));
        loadItems();
      } else {
        toast.error(res?.error || t('monitoring.remove_failed'));
      }
    } catch {
      toast.error(t('monitoring.remove_failed'));
    } finally {
      setRemovingId(null);
    }
  };

  const columns: Column<MonitoredUser>[] = [
    {
      key: 'user_name',
      label: t('monitoring.col_user'),
      sortable: true,
      render: (item) => (
        <span className="font-medium text-foreground">{item.user_name}</span>
      ),
    },
    {
      key: 'under_monitoring',
      label: t('monitoring.col_status'),
      render: (item) => (
        <div className="flex flex-wrap gap-1">
          <Chip
            size="sm"
            variant="flat"
            color={item.under_monitoring ? 'warning' : 'default'}
            startContent={<Eye size={12} />}
          >
            {t('monitoring.status_monitored')}
          </Chip>
          {item.messaging_disabled && (
            <Chip
              size="sm"
              variant="flat"
              color="danger"
              startContent={<MessageCircleOff size={12} />}
            >
              {t('monitoring.status_messaging_off')}
            </Chip>
          )}
        </div>
      ),
    },
    {
      key: 'monitoring_reason',
      label: t('monitoring.col_reason'),
      render: (item) => (
        <span className="text-sm text-default-600">
          {item.monitoring_reason || '—'}
        </span>
      ),
    },
    {
      key: 'monitoring_started_at',
      label: t('monitoring.col_started'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {item.monitoring_started_at
            ? new Date(item.monitoring_started_at).toLocaleDateString()
            : '—'
          }
        </span>
      ),
    },
    {
      key: 'monitoring_expires_at',
      label: t('monitoring.col_expires'),
      sortable: true,
      render: (item) => {
        if (!item.monitoring_expires_at) {
          return <span className="text-sm text-default-400">{t('monitoring.no_expiry')}</span>;
        }
        const expiresAt = new Date(item.monitoring_expires_at);
        const isExpired = expiresAt <= new Date();
        return (
          <Chip
            size="sm"
            variant="flat"
            color={isExpired ? 'danger' : 'default'}
          >
            {expiresAt.toLocaleDateString()}
          </Chip>
        );
      },
    },
    {
      key: 'actions',
      label: t('monitoring.col_actions'),
      render: (item) => (
        <Button
          isIconOnly
          size="sm"
          variant="flat"
          color="danger"
          onPress={() => handleRemoveMonitoring(item.user_id)}
          isLoading={removingId === item.user_id}
          aria-label={t('monitoring.remove_aria')}
        >
          <UserMinus size={14} />
        </Button>
      ),
    },
  ];

  return (
    <div>
      <PageHeader
        title={t('monitoring.page_title')}
        description={t('monitoring.page_description')}
        actions={
          <div className="flex gap-2">
            <Button
              color="primary"
              startContent={<UserPlus size={16} />}
              size="sm"
              onPress={() => setMonitoringModalOpen(true)}
            >
              {t('monitoring.add_button')}
            </Button>
            <Button
              as={Link}
              to={tenantPath('/broker')}
              variant="flat"
              startContent={<ArrowLeft size={16} />}
              size="sm"
            >
              {t('monitoring.back_button')}
            </Button>
          </div>
        }
      />

      {!loading && items.length === 0 ? (
        <EmptyState
          icon={Eye}
          title={t('monitoring.empty_title')}
          description={t('monitoring.empty_description')}
        />
      ) : (
        <DataTable
          columns={columns}
          data={items}
          isLoading={loading}
          searchable={false}
          onRefresh={loadItems}
        />
      )}

      {/* Add to Monitoring Modal */}
      <Modal
        isOpen={monitoringModalOpen}
        onClose={resetModalState}
        size="md"
      >
        <ModalContent>
          <ModalHeader className="flex items-center gap-2">
            <UserPlus size={20} className="text-primary" />
            {t('monitoring.modal_title')}
          </ModalHeader>
          <ModalBody>
            {/* User search / selection */}
            {selectedUser ? (
              <div className="flex items-center gap-3 rounded-lg border border-divider p-3">
                <Avatar
                  src={resolveAvatarUrl(selectedUser.avatar_url ?? selectedUser.avatar) || undefined}
                  name={selectedUser.name}
                  size="sm"
                />
                <div className="min-w-0 flex-1">
                  <p className="text-sm font-medium text-foreground truncate">{selectedUser.name}</p>
                  <p className="text-xs text-default-500 truncate">{selectedUser.email}</p>
                </div>
                <Chip size="sm" variant="flat" color={selectedUser.status === 'active' ? 'success' : 'default'}>
                  {t(`status.${selectedUser.status}`, { defaultValue: selectedUser.status })}
                </Chip>
                <Button
                  isIconOnly
                  size="sm"
                  variant="light"
                  onPress={clearSelectedUser}
                  aria-label={t('monitoring.clear_selection_aria')}
                >
                  <X size={14} />
                </Button>
              </div>
            ) : (
              <div ref={dropdownRef} className="relative">
                <Input
                  ref={inputRef}
                  label={t('monitoring.search_user_label')}
                  placeholder={t('monitoring.search_user_placeholder')}
                  variant="bordered"
                  isRequired
                  value={userSearchQuery}
                  onValueChange={handleUserSearch}
                  onKeyDown={handleKeyDown}
                  onFocus={() => {
                    if (userSearchResults.length > 0) setShowDropdown(true);
                  }}
                  startContent={<Search size={16} className="text-default-400" />}
                  endContent={isSearching ? <Spinner size="sm" /> : null}
                  autoComplete="off"
                />
                {showDropdown && (
                  <ul
                    className="absolute left-0 right-0 top-full z-50 mt-1 max-h-60 overflow-y-auto rounded-lg border border-divider bg-content1 shadow-lg"
                    role="listbox"
                  >
                    {userSearchResults.map((user, index) => (
                      <li
                        key={user.id}
                        role="option"
                        aria-selected={index === highlightedIndex}
                        className={`flex cursor-pointer items-center gap-3 px-3 py-2.5 transition-colors ${
                          index === highlightedIndex
                            ? 'bg-primary/10'
                            : 'hover:bg-default-100'
                        }`}
                        onMouseEnter={() => setHighlightedIndex(index)}
                        onMouseDown={(e) => {
                          e.preventDefault(); // Prevent input blur
                          selectUser(user);
                        }}
                      >
                        <Avatar
                          src={resolveAvatarUrl(user.avatar_url ?? user.avatar) || undefined}
                          name={user.name}
                          size="sm"
                          className="shrink-0"
                        />
                        <div className="min-w-0 flex-1">
                          <p className="text-sm font-medium text-foreground truncate">{user.name}</p>
                          <p className="text-xs text-default-500 truncate">{user.email}</p>
                        </div>
                        <Chip size="sm" variant="flat" color={user.status === 'active' ? 'success' : 'default'}>
                          {t(`status.${user.status}`, { defaultValue: user.status })}
                        </Chip>
                      </li>
                    ))}
                  </ul>
                )}
                {userSearchQuery.length >= 2 && !isSearching && userSearchResults.length === 0 && (
                  <p className="mt-1 text-xs text-default-400">{t('monitoring.no_users_found')}</p>
                )}
              </div>
            )}

            <Textarea
              label={t('monitoring.reason_label')}
              placeholder={t('monitoring.reason_placeholder')}
              value={monitoringReason}
              onValueChange={setMonitoringReason}
              minRows={3}
              variant="bordered"
              isRequired
            />
            <div className="flex items-center justify-between py-1">
              <span className="text-sm text-default-600">{t('monitoring.disable_messaging')}</span>
              <Switch
                isSelected={messagingDisabled}
                onValueChange={setMessagingDisabled}
                size="sm"
              />
            </div>
            <Select
              label={t('monitoring.duration_label')}
              placeholder={t('monitoring.duration_placeholder')}
              variant="bordered"
              selectedKeys={expiresDays ? [expiresDays] : []}
              onSelectionChange={(keys) => {
                const val = Array.from(keys)[0] as string | undefined;
                setExpiresDays(val ?? '');
              }}
            >
              <SelectItem key="7">{t('monitoring.duration_7_days')}</SelectItem>
              <SelectItem key="14">{t('monitoring.duration_14_days')}</SelectItem>
              <SelectItem key="30">{t('monitoring.duration_30_days')}</SelectItem>
              <SelectItem key="60">{t('monitoring.duration_60_days')}</SelectItem>
              <SelectItem key="90">{t('monitoring.duration_90_days')}</SelectItem>
            </Select>
          </ModalBody>
          <ModalFooter>
            <Button
              variant="flat"
              onPress={resetModalState}
              isDisabled={monitoringLoading}
            >
              {t('monitoring.cancel_button')}
            </Button>
            <Button
              color="primary"
              onPress={handleAddMonitoring}
              isLoading={monitoringLoading}
              isDisabled={!selectedUser}
              startContent={!monitoringLoading && <UserPlus size={14} />}
            >
              {t('monitoring.add_button')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default UserMonitoring;
