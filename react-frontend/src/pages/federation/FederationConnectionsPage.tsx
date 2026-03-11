// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Federation Connections Page
 * Route: /federation/connections
 */

import { useState, useEffect, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import {
  Button,
  Avatar,
  Chip,
  Spinner,
  Tab,
  Tabs,
} from '@heroui/react';
import {
  UserPlus,
  UserCheck,
  Clock,
  Send,
  Globe,
  MessageSquare,
  Trash2,
  Check,
  X,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { Breadcrumbs } from '@/components/navigation';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { api } from '@/lib/api';
import { resolveAvatarUrl, formatRelativeTime } from '@/lib/helpers';
import { logError } from '@/lib/logger';

interface FederationConnection {
  id: number;
  user_id: number;
  tenant_id: number;
  name: string;
  avatar_url?: string;
  tenant_name: string;
  message?: string;
  created_at: string;
}

type TabKey = 'accepted' | 'pending_received' | 'pending_sent';

export function FederationConnectionsPage() {
  const { t } = useTranslation('federation');
  usePageTitle(t('connections.page_title', 'Connections'));
  const navigate = useNavigate();
  const { tenantPath } = useTenant();
  const toast = useToast();

  const [activeTab, setActiveTab] = useState<TabKey>('accepted');
  const [connections, setConnections] = useState<FederationConnection[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState<number | null>(null);

  const loadConnections = useCallback(async (tab: TabKey) => {
    try {
      setIsLoading(true);
      const response = await api.get<FederationConnection[]>(
        `/v2/federation/connections?status=${tab}`
      );
      if (response.success && response.data) {
        setConnections(response.data);
      } else {
        setConnections([]);
      }
    } catch (err) {
      logError('Failed to load federation connections', err);
      setConnections([]);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    loadConnections(activeTab);
  }, [activeTab, loadConnections]);

  const handleTabChange = (key: React.Key) => {
    setActiveTab(key as TabKey);
  };
  const handleAction = async (
    connectionId: number,
    action: 'accept' | 'reject' | 'remove',
  ) => {
    try {
      setActionLoading(connectionId);
      let response;
      if (action === 'remove') {
        response = await api.delete(`/v2/federation/connections/${connectionId}`);
      } else {
        response = await api.post(`/v2/federation/connections/${connectionId}/${action}`);
      }
      if (response.success) {
        const msgs: Record<string, string> = {
          accept: t('connections.accepted_success', 'Connection accepted!'),
          reject: t('connections.rejected_success', 'Connection request declined'),
          remove: t('connections.removed_success', 'Connection removed'),
        };
        toast.success(msgs[action]);
        loadConnections(activeTab);
      } else {
        toast.error(response.error || t('connections.action_failed', 'Action failed'));
      }
    } catch (err) {
      logError(`Failed to ${action} connection`, err);
      toast.error(t('connections.action_failed', 'Action failed'));
    } finally {
      setActionLoading(null);
    }
  };
  return (
    <div className="space-y-6">
      <Breadcrumbs
        items={[
          { label: t('connections.breadcrumb_federation', 'Federation'), href: tenantPath('/federation') },
          { label: t('connections.breadcrumb_connections', 'Connections') },
        ]}
      />

      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-theme-primary">
            {t('connections.title', 'Federation Connections')}
          </h1>
          <p className="text-theme-muted mt-1">
            {t('connections.subtitle', 'Manage your cross-community connections')}
          </p>
        </div>
      </div>

      <Tabs
        selectedKey={activeTab}
        onSelectionChange={handleTabChange}
        variant="underlined"
        classNames={{
          tabList: 'gap-4',
          tab: 'text-theme-muted data-[selected=true]:text-indigo-500',
          cursor: 'bg-indigo-500',
        }}
      >
        <Tab key="accepted" title={<div className="flex items-center gap-2"><UserCheck className="w-4 h-4" />{t('connections.tab_connected', 'Connected')}</div>} />
        <Tab key="pending_received" title={<div className="flex items-center gap-2"><Clock className="w-4 h-4" />{t('connections.tab_received', 'Received')}</div>} />
        <Tab key="pending_sent" title={<div className="flex items-center gap-2"><Send className="w-4 h-4" />{t('connections.tab_sent', 'Sent')}</div>} />
      </Tabs>

      {isLoading ? (
        <div className="flex items-center justify-center py-16">
          <Spinner size="lg" label={t('connections.loading', 'Loading connections...')} />
        </div>
      ) : connections.length === 0 ? (
        <GlassCard className="p-8 text-center">
          <div className="w-16 h-16 rounded-full bg-indigo-500/10 flex items-center justify-center mx-auto mb-4">
            {activeTab === 'accepted' && <UserCheck className="w-8 h-8 text-indigo-500" />}
            {activeTab === 'pending_received' && <UserPlus className="w-8 h-8 text-indigo-500" />}
            {activeTab === 'pending_sent' && <Send className="w-8 h-8 text-indigo-500" />}
          </div>
          <h3 className="text-lg font-semibold text-theme-primary mb-2">
            {activeTab === 'accepted' && t('connections.empty_connected', 'No connections yet')}
            {activeTab === 'pending_received' && t('connections.empty_received', 'No pending requests')}
            {activeTab === 'pending_sent' && t('connections.empty_sent', 'No sent requests')}
          </h3>
          <p className="text-theme-muted mb-4">
            {activeTab === 'accepted' && t('connections.empty_connected_desc', 'Connect with members from partner communities to grow your network.')}
            {activeTab === 'pending_received' && t('connections.empty_received_desc', 'When someone sends you a connection request, it will appear here.')}
            {activeTab === 'pending_sent' && t('connections.empty_sent_desc', 'Connection requests you send will appear here.')}
          </p>
          {activeTab === 'accepted' && (
            <Button className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white" startContent={<Globe className="w-4 h-4" />} onPress={() => navigate(tenantPath('/federation/members'))}>
              {t('connections.browse_members', 'Browse Federation Members')}
            </Button>
          )}
        </GlassCard>      ) : (
        <div className="grid gap-4">
          <AnimatePresence mode="popLayout">
            {connections.map((conn, index) => (
              <motion.div key={conn.id} initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0, x: -100 }} transition={{ delay: index * 0.05 }}>
                <ConnectionCard connection={conn} tab={activeTab} actionLoading={actionLoading} onAction={handleAction}
                  onMessage={(userId, tenantId) => navigate(tenantPath(`/federation/messages?compose=true&to_user=${userId}&to_tenant=${tenantId}`))}
                  onViewProfile={(userId) => navigate(tenantPath(`/federation/members/${userId}`))}
                />
              </motion.div>
            ))}
          </AnimatePresence>
        </div>
      )}
    </div>
  );
}
interface ConnectionCardProps {
  connection: FederationConnection;
  tab: TabKey;
  actionLoading: number | null;
  onAction: (id: number, action: 'accept' | 'reject' | 'remove') => void;
  onMessage: (userId: number, tenantId: number) => void;
  onViewProfile: (userId: number) => void;
}

function ConnectionCard({ connection, tab, actionLoading, onAction, onMessage, onViewProfile }: ConnectionCardProps) {
  const { t } = useTranslation('federation');
  const isActioning = actionLoading === connection.id;
  const displayName = connection.name || t('connections.unknown_member', 'Federation Member');

  return (
    <GlassCard className="p-4 sm:p-5">
      <div className="flex flex-col sm:flex-row items-start gap-4">
        <div className="flex items-center gap-3 flex-1 min-w-0 cursor-pointer" onClick={() => onViewProfile(connection.user_id)} role="button" tabIndex={0} onKeyDown={(e) => e.key === 'Enter' && onViewProfile(connection.user_id)}>
          <div className="relative flex-shrink-0">
            <Avatar src={resolveAvatarUrl(connection.avatar_url)} name={displayName} className="w-12 h-12 ring-2 ring-indigo-500/20" />
            <div className="absolute -bottom-0.5 -right-0.5 w-5 h-5 rounded-full bg-indigo-500 flex items-center justify-center ring-1 ring-white dark:ring-gray-900" title={connection.tenant_name}>
              <Globe className="w-3 h-3 text-white" />
            </div>
          </div>
          <div className="min-w-0 flex-1">
            <p className="font-semibold text-theme-primary truncate">{displayName}</p>
            <div className="flex items-center gap-2 text-sm text-theme-muted">
              <Globe className="w-3.5 h-3.5 flex-shrink-0" />
              <span className="truncate">{connection.tenant_name}</span>
            </div>
            {connection.message && tab === 'pending_received' && (
              <p className="text-sm text-theme-muted mt-1 line-clamp-2 italic">&#8220;{connection.message}&#8221;</p>
            )}
            <p className="text-xs text-theme-muted mt-1">{formatRelativeTime(connection.created_at)}</p>
          </div>
        </div>
        <div className="flex items-center gap-2 flex-shrink-0 w-full sm:w-auto">
          {tab === 'accepted' && (
            <>
              <Button size="sm" variant="flat" className="bg-indigo-500/10 text-indigo-600 dark:text-indigo-400" startContent={<MessageSquare className="w-3.5 h-3.5" />} onPress={() => onMessage(connection.user_id, connection.tenant_id)}>
                {t('connections.message', 'Message')}
              </Button>
              <Button size="sm" variant="flat" className="text-danger" isIconOnly isLoading={isActioning} onPress={() => onAction(connection.id, 'remove')} aria-label={t('connections.remove', 'Remove connection')}>
                <Trash2 className="w-3.5 h-3.5" />
              </Button>
            </>
          )}
          {tab === 'pending_received' && (
            <>
              <Button size="sm" className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white" startContent={<Check className="w-3.5 h-3.5" />} isLoading={isActioning} onPress={() => onAction(connection.id, 'accept')}>
                {t('connections.accept', 'Accept')}
              </Button>
              <Button size="sm" variant="flat" className="text-danger" startContent={<X className="w-3.5 h-3.5" />} isLoading={isActioning} onPress={() => onAction(connection.id, 'reject')}>
                {t('connections.decline', 'Decline')}
              </Button>
            </>
          )}
          {tab === 'pending_sent' && (
            <Chip size="sm" variant="flat" className="bg-amber-500/10 text-amber-600 dark:text-amber-400" startContent={<Clock className="w-3 h-3" />}>
              {t('connections.pending', 'Pending')}
            </Chip>
          )}
        </div>
      </div>
    </GlassCard>
  );
}

export default FederationConnectionsPage;
