// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Notifications Page - User notifications center
 */

import { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Skeleton } from '@heroui/react';
import {
  Bell,
  MessageSquare,
  ListTodo,
  Wallet,
  User,
  Calendar,
  Users,
  Award,
  Check,
  CheckCheck,
  Trash2,
  Settings,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useToast, useTenant, useNotifications } from '@/contexts';
import { api } from '@/lib/api';
import { formatRelativeTime } from '@/lib/helpers';
import { logError } from '@/lib/logger';
import { usePageTitle } from '@/hooks';
import type { Notification } from '@/types/api';

type NotificationFilter = 'all' | 'unread';

export function NotificationsPage() {
  const { t } = useTranslation('notifications');
  usePageTitle(t('page_title'));
  const { tenantPath } = useTenant();
  const toast = useToast();
  const { refreshCounts, markAsRead: contextMarkAsRead, markAllAsRead: contextMarkAllAsRead } = useNotifications();
  const [notifications, setNotifications] = useState<Notification[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [filter, setFilter] = useState<NotificationFilter>('all');

  useEffect(() => {
    loadNotifications();
  }, []);

  async function loadNotifications() {
    try {
      setIsLoading(true);
      setLoadError(null);
      const response = await api.get<Notification[]>('/v2/notifications?per_page=50');
      if (response.success && response.data) {
        setNotifications(response.data);
      } else {
        setLoadError(t('error_load'));
      }
    } catch (error) {
      logError('Failed to load notifications', error);
      setLoadError(t('error_load'));
    } finally {
      setIsLoading(false);
    }
  }

  async function markAsRead(id: number) {
    try {
      // Use context to update both local state and bell badge count
      await contextMarkAsRead(id);
      setNotifications((prev) =>
        prev.map((n) => (n.id === id ? { ...n, read_at: new Date().toISOString() } : n))
      );
    } catch (error) {
      logError('Failed to mark as read', error);
      toast.error(t('toast.mark_read_failed'));
    }
  }

  async function markAllAsRead() {
    try {
      // Use context to update both local state and bell badge count
      await contextMarkAllAsRead();
      setNotifications((prev) =>
        prev.map((n) => ({ ...n, read_at: n.read_at || new Date().toISOString() }))
      );
      toast.success(t('toast.all_read'));
    } catch (error) {
      logError('Failed to mark all as read', error);
      toast.error(t('toast.mark_all_failed'));
    }
  }

  async function deleteNotification(id: number) {
    try {
      const notification = notifications.find((n) => n.id === id);
      await api.delete(`/v2/notifications/${id}`);
      setNotifications((prev) => prev.filter((n) => n.id !== id));
      // If deleted notification was unread, refresh the bell badge count
      if (notification && !notification.read_at) {
        refreshCounts();
      }
    } catch (error) {
      logError('Failed to delete notification', error);
      toast.error(t('toast.delete_failed'));
    }
  }

  const filteredNotifications = notifications.filter((n) =>
    filter === 'all' ? true : !n.read_at
  );

  const unreadCount = notifications.filter((n) => !n.read_at).length;

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: {
      opacity: 1,
      transition: { staggerChildren: 0.05 },
    },
  };

  const itemVariants = {
    hidden: { opacity: 0, x: -20 },
    visible: { opacity: 1, x: 0 },
  };

  return (
    <div className="max-w-3xl mx-auto space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-3">
            <Bell className="w-7 h-7 text-indigo-600 dark:text-indigo-400" aria-hidden="true" />
            {t('title')}
            {unreadCount > 0 && (
              <span className="text-sm px-2 py-1 rounded-full bg-indigo-500 text-white font-medium">
                {t('unread_badge', { count: unreadCount })}
              </span>
            )}
          </h1>
          <p className="text-theme-muted mt-1">{t('subtitle')}</p>
        </div>

        <div className="flex gap-2">
          {unreadCount > 0 && (
            <Button
              variant="flat"
              size="sm"
              className="bg-theme-elevated text-theme-primary"
              startContent={<CheckCheck className="w-4 h-4" aria-hidden="true" />}
              onPress={markAllAsRead}
            >
              {t('mark_all_read')}
            </Button>
          )}
          <Link to={tenantPath("/settings")}>
            <Button
              variant="flat"
              size="sm"
              className="bg-theme-elevated text-theme-primary"
              isIconOnly
              aria-label={t('settings_aria')}
            >
              <Settings className="w-4 h-4" aria-hidden="true" />
            </Button>
          </Link>
        </div>
      </div>

      {/* Filter */}
      <div className="flex gap-2">
        <Button
          size="sm"
          variant={filter === 'all' ? 'solid' : 'flat'}
          className={filter === 'all' ? 'bg-theme-hover text-theme-primary' : 'bg-theme-elevated text-theme-muted'}
          onPress={() => setFilter('all')}
        >
          {t('filter_all')}
        </Button>
        <Button
          size="sm"
          variant={filter === 'unread' ? 'solid' : 'flat'}
          className={filter === 'unread' ? 'bg-theme-hover text-theme-primary' : 'bg-theme-elevated text-theme-muted'}
          onPress={() => setFilter('unread')}
        >
          {t('filter_unread', { count: unreadCount })}
        </Button>
      </div>

      {/* Load Error */}
      {loadError && !isLoading && (
        <GlassCard className="p-8 text-center">
          <Bell className="w-12 h-12 text-amber-500 mx-auto mb-4 opacity-50" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary mb-2">{t('error_title', 'Unable to load')}</h2>
          <p className="text-theme-muted mb-4">{loadError}</p>
          <Button
            className="bg-linear-to-r from-indigo-500 to-purple-600 text-white"
            onPress={loadNotifications}
          >
            {t('try_again', 'Try Again')}
          </Button>
        </GlassCard>
      )}

      {/* Notifications List */}
      {!loadError && isLoading ? (
        <div aria-label={t('loading_aria')} aria-busy="true" className="space-y-3">
          {Array.from({ length: 6 }).map((_, i) => (
            <GlassCard key={i} className="p-4">
              <div className="flex items-start gap-4">
                <Skeleton className="rounded-full flex-shrink-0"><div className="w-10 h-10 rounded-full bg-default-300" /></Skeleton>
                <div className="flex-1 space-y-2">
                  <Skeleton className="rounded-lg"><div className="h-4 rounded-lg bg-default-300 w-2/3" /></Skeleton>
                  <Skeleton className="rounded-lg"><div className="h-3 rounded-lg bg-default-200 w-1/3" /></Skeleton>
                </div>
              </div>
            </GlassCard>
          ))}
        </div>
      ) : filteredNotifications.length === 0 ? (
        <EmptyState
          icon={<Bell className="w-12 h-12" />}
          title={filter === 'unread' ? t('empty_caught_up') : t('empty_title')}
          description={filter === 'unread' ? t('empty_caught_up_desc') : t('empty_desc')}
        />
      ) : (
        <motion.div
          variants={containerVariants}
          initial="hidden"
          animate="visible"
          className="space-y-3"
        >
          {filteredNotifications.map((notification) => (
            <motion.div key={notification.id} variants={itemVariants}>
              <NotificationCard
                notification={notification}
                onMarkRead={() => markAsRead(notification.id)}
                onDelete={() => deleteNotification(notification.id)}
              />
            </motion.div>
          ))}
        </motion.div>
      )}
    </div>
  );
}

interface NotificationCardProps {
  notification: Notification;
  onMarkRead: () => void;
  onDelete: () => void;
}

function NotificationCard({ notification, onMarkRead, onDelete }: NotificationCardProps) {
  const { t } = useTranslation('notifications');
  const navigate = useNavigate();
  const { tenantPath } = useTenant();
  const isUnread = !notification.read_at;
  const hasLink = !!notification.link;

  function handleClick() {
    if (!notification.link) return;
    // Mark as read when navigating
    if (isUnread) onMarkRead();
    // The link from the API is a relative path like "/messages/123" — scope it to the tenant
    navigate(tenantPath(notification.link));
  }

  const iconMap: Record<string, { icon: React.ReactNode; color: string }> = {
    message: { icon: <MessageSquare className="w-5 h-5" />, color: 'indigo' },
    listing: { icon: <ListTodo className="w-5 h-5" />, color: 'emerald' },
    transaction: { icon: <Wallet className="w-5 h-5" />, color: 'amber' },
    connection: { icon: <User className="w-5 h-5" />, color: 'purple' },
    event: { icon: <Calendar className="w-5 h-5" />, color: 'rose' },
    group: { icon: <Users className="w-5 h-5" />, color: 'teal' },
    achievement: { icon: <Award className="w-5 h-5" />, color: 'orange' },
  };

  const { icon, color } = iconMap[notification.type] || { icon: <Bell className="w-5 h-5" />, color: 'gray' };

  const colorClasses: Record<string, string> = {
    indigo: 'bg-indigo-500/20 text-indigo-600 dark:text-indigo-400',
    emerald: 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400',
    amber: 'bg-amber-500/20 text-amber-600 dark:text-amber-400',
    purple: 'bg-purple-500/20 text-purple-600 dark:text-purple-400',
    rose: 'bg-rose-500/20 text-rose-600 dark:text-rose-400',
    teal: 'bg-teal-500/20 text-teal-600 dark:text-teal-400',
    orange: 'bg-orange-500/20 text-orange-600 dark:text-orange-400',
    gray: 'bg-theme-elevated text-theme-muted',
  };

  return (
    <GlassCard className={`p-4 ${isUnread ? 'ring-1 ring-indigo-500/30' : ''} ${hasLink ? 'hover:bg-theme-hover/50 transition-colors' : ''}`}>
      <div className="flex items-start gap-3 sm:gap-4">
        <div
          className={`flex items-start gap-3 sm:gap-4 flex-1 min-w-0 ${hasLink ? 'cursor-pointer' : ''}`}
          onClick={handleClick}
          role={hasLink ? 'link' : undefined}
          tabIndex={hasLink ? 0 : undefined}
          onKeyDown={hasLink ? (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); handleClick(); } } : undefined}
        >
          <div className={`p-2.5 rounded-full flex-shrink-0 ${colorClasses[color]}`}>
            {icon}
          </div>

          <div className="flex-1 min-w-0">
            <p className={`${isUnread ? 'text-theme-primary font-medium' : 'text-theme-muted'}`}>
              {notification.message || notification.body}
            </p>
            <p className="text-xs text-theme-subtle mt-1">
              {formatRelativeTime(notification.created_at)}
            </p>
          </div>
        </div>

        <div className="flex items-center gap-1 flex-shrink-0">
          {isUnread && (
            <Button
              isIconOnly
              variant="flat"
              size="sm"
              className="bg-theme-elevated text-theme-muted hover:text-theme-primary"
              onPress={onMarkRead}
              aria-label={t('mark_read_aria')}
            >
              <Check className="w-4 h-4" aria-hidden="true" />
            </Button>
          )}
          <Button
            isIconOnly
            variant="flat"
            size="sm"
            className="bg-theme-elevated text-theme-muted hover:text-red-500 dark:hover:text-red-400"
            onPress={onDelete}
            aria-label={t('delete_aria')}
          >
            <Trash2 className="w-4 h-4" aria-hidden="true" />
          </Button>
        </div>
      </div>
    </GlassCard>
  );
}

export default NotificationsPage;
