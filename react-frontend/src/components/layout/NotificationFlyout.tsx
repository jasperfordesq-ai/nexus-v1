// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Notification Flyout Panel
 * Rich popover showing recent notifications without leaving the current page.
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Popover,
  PopoverTrigger,
  PopoverContent,
  Button,
  Skeleton,
} from '@heroui/react';
import {
  Bell,
  MessageSquare,
  ListTodo,
  Wallet,
  User,
  Calendar,
  Users,
  Award,
  CheckCheck,
  ExternalLink,
  Info,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useNotifications, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { formatRelativeTime } from '@/lib/helpers';
import { logError } from '@/lib/logger';
import type { Notification } from '@/types/api';

const TYPE_ICONS: Record<string, typeof Bell> = {
  message: MessageSquare,
  listing: ListTodo,
  transaction: Wallet,
  connection: User,
  event: Calendar,
  group: Users,
  achievement: Award,
  system: Info,
};

export function NotificationFlyout() {
  const { t } = useTranslation('notifications');
  const navigate = useNavigate();
  const { unreadCount, markAsRead, markAllAsRead } = useNotifications();
  const { tenantPath } = useTenant();

  const [isOpen, setIsOpen] = useState(false);
  const [notifications, setNotifications] = useState<Notification[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const fetchIdRef = useRef(0);

  // Always re-fetch when popover opens — ensures sync with page deletions/reads
  const fetchNotifications = useCallback(async () => {
    const fetchId = ++fetchIdRef.current;
    try {
      setIsLoading(true);
      const response = await api.get<Notification[]>('/v2/notifications?per_page=8');
      // Only apply if this is still the latest fetch
      if (fetchId === fetchIdRef.current && response.success && response.data) {
        setNotifications(response.data);
      }
    } catch (error) {
      logError('Failed to load notifications for flyout', error);
    } finally {
      if (fetchId === fetchIdRef.current) {
        setIsLoading(false);
      }
    }
  }, []);

  // Re-fetch every time the popover opens
  useEffect(() => {
    if (isOpen) fetchNotifications();
  }, [isOpen, fetchNotifications]);

  const handleOpenChange = useCallback((open: boolean) => setIsOpen(open), []);

  const handleMarkAllRead = useCallback(async () => {
    await markAllAsRead();
    setNotifications(prev => prev.map(n => ({ ...n, read_at: n.read_at || new Date().toISOString() })));
  }, [markAllAsRead]);

  const handleNotificationClick = useCallback((notification: Notification) => {
    setIsOpen(false);
    // Mark as read in context so bell badge updates
    if (!notification.read_at) {
      markAsRead(notification.id);
      setNotifications(prev => prev.map(n =>
        n.id === notification.id ? { ...n, read_at: new Date().toISOString() } : n
      ));
    }
    navigate(notification.link ? tenantPath(notification.link) : tenantPath('/notifications'));
  }, [navigate, tenantPath, markAsRead]);

  const handleViewAll = useCallback(() => {
    setIsOpen(false);
    navigate(tenantPath('/notifications'));
  }, [navigate, tenantPath]);

  const getIcon = (type: string) => {
    const Icon = TYPE_ICONS[type] || Bell;
    return <Icon className="w-4 h-4 shrink-0" aria-hidden="true" />;
  };

  return (
    <Popover
      placement="bottom-end"
      isOpen={isOpen}
      onOpenChange={handleOpenChange}
      shouldBlockScroll={false}
      backdrop="transparent"
      offset={8}
    >
      <PopoverTrigger>
        <Button
          isIconOnly
          variant="light"
          size="sm"
          className={`relative text-theme-muted hover:text-theme-primary ${unreadCount > 0 ? 'text-indigo-500 dark:text-indigo-400' : ''}`}
          aria-label={`Notifications${unreadCount > 0 ? `, ${unreadCount} unread` : ''}`}
        >
          <Bell className="w-4 h-4 sm:w-5 sm:h-5" aria-hidden="true" />
          {unreadCount > 0 && (
            <span className="absolute -top-0.5 -right-0.5 flex items-center justify-center min-w-[16px] h-4 px-1 text-[10px] font-bold text-white bg-danger rounded-full leading-none">
              {unreadCount > 99 ? '99+' : unreadCount}
            </span>
          )}
        </Button>
      </PopoverTrigger>
      <PopoverContent className="p-0 bg-[var(--surface-dropdown)] border border-[var(--border-default)] shadow-2xl rounded-xl w-[360px] max-w-[90vw]">
        <div className="flex items-center justify-between px-4 py-3 border-b border-[var(--border-default)]">
          <h3 className="text-sm font-semibold text-theme-primary">
            {t('title')}
            {unreadCount > 0 && (
              <span className="ml-2 text-xs font-normal text-theme-subtle">
                ({unreadCount} {t('flyout.unread_count')})
              </span>
            )}
          </h3>
          {unreadCount > 0 && (
            <Button
              variant="light"
              size="sm"
              className="text-xs text-indigo-500 dark:text-indigo-400 h-7 min-w-0 px-2 gap-1"
              onPress={handleMarkAllRead}
            >
              <CheckCheck className="w-3.5 h-3.5" aria-hidden="true" />
              {t('mark_all_read')}
            </Button>
          )}
        </div>
        <div className="max-h-[400px] overflow-y-auto">
          {isLoading ? (
            <div className="p-4 space-y-3">
              {[1, 2, 3].map(i => (
                <div key={i} className="flex gap-3">
                  <Skeleton className="w-8 h-8 rounded-full shrink-0" />
                  <div className="flex-1 space-y-1.5">
                    <Skeleton className="h-3 w-3/4 rounded" />
                    <Skeleton className="h-3 w-1/2 rounded" />
                  </div>
                </div>
              ))}
            </div>
          ) : notifications.length === 0 ? (
            <div className="py-8 text-center">
              <Bell className="w-8 h-8 mx-auto text-theme-subtle mb-2 opacity-40" />
              <p className="text-sm text-theme-subtle">{t('flyout.empty')}</p>
            </div>
          ) : (
            <div className="py-1">
              {notifications.map(notification => {
                const isUnread = !notification.read_at;
                return (
                  <button
                    key={notification.id}
                    onClick={() => handleNotificationClick(notification)}
                    className={`w-full flex items-start gap-3 px-4 py-3 text-left transition-colors hover:bg-theme-hover ${
                      isUnread ? 'bg-indigo-50/50 dark:bg-indigo-500/5' : ''
                    }`}
                  >
                    <div className={`mt-0.5 p-1.5 rounded-full shrink-0 ${
                      isUnread
                        ? 'bg-indigo-100 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400'
                        : 'bg-[var(--surface-elevated)] text-theme-subtle'
                    }`}>
                      {getIcon(notification.type)}
                    </div>
                    <div className="flex-1 min-w-0">
                      <p className={`text-sm leading-snug ${isUnread ? 'font-medium text-theme-primary' : 'text-theme-secondary'}`}>
                        {notification.message || notification.body || notification.title}
                      </p>
                      <p className="text-xs text-theme-subtle mt-0.5">
                        {formatRelativeTime(notification.created_at)}
                      </p>
                    </div>
                    {isUnread && (
                      <span className="w-2 h-2 mt-2 rounded-full bg-indigo-500 shrink-0" aria-label={t('flyout.unread_dot_aria')} />
                    )}
                  </button>
                );
              })}
            </div>
          )}
        </div>
        <div className="border-t border-[var(--border-default)] px-4 py-2.5">
          <Button
            variant="light"
            fullWidth
            size="sm"
            className="text-sm text-indigo-500 dark:text-indigo-400 h-8 gap-1.5"
            onPress={handleViewAll}
          >
            {t('flyout.view_all')}
            <ExternalLink className="w-3.5 h-3.5" aria-hidden="true" />
          </Button>
        </div>
      </PopoverContent>
    </Popover>
  );
}

export default NotificationFlyout;
