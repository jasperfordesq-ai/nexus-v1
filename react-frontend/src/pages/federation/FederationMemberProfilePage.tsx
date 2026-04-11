// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Federation Member Profile Page
 *
 * Shows a federated member's profile from a partner community.
 * Route: /federation/members/:id
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { motion } from 'framer-motion';
import {
  Button,
  Avatar,
  Chip,
  Spinner,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Input,
  Textarea,
  useDisclosure,
} from '@heroui/react';
import {
  Globe,
  MapPin,
  MessageSquare,
  ArrowLeft,
  AlertTriangle,
  RefreshCw,
  Home,
  Compass,
  Car,
  User,
  UserPlus,
  Coins,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { Breadcrumbs } from '@/components/navigation';
import { PageMeta } from '@/components/seo';
import { useAuth, useTenant, useToast } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { resolveAvatarUrl } from '@/lib/helpers';
import { logError } from '@/lib/logger';
import type { FederatedMember } from '@/types/api';

const SERVICE_REACH_META: Record<string, { icon: typeof Home }> = {
  local_only: { icon: Home },
  remote_ok: { icon: Compass },
  travel_ok: { icon: Car },
};

export function FederationMemberProfilePage() {
  const { t } = useTranslation('federation');
  usePageTitle(t('member_profile.page_title'));
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { isAuthenticated } = useAuth();
  const { tenantPath } = useTenant();

  const [member, setMember] = useState<FederatedMember | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const toast = useToast();

  // External member IDs start with 'ext-' — these can't be fetched from the API
  const isExternalMember = id?.startsWith('ext-') ?? false;

  // AbortController ref to cancel stale requests
  const abortRef = useRef<AbortController | null>(null);

  // Stable refs for t/toast — avoids re-creating callbacks when i18n namespace loads
  const tRef = useRef(t);
  tRef.current = t;
  const toastRef = useRef(toast);
  toastRef.current = toast;

  const [connectionStatus, setConnectionStatus] = useState<string>('none');
  const [connectLoading, setConnectLoading] = useState(false);

  // Transaction modal
  const txModal = useDisclosure();
  const [txAmount, setTxAmount] = useState('');
  const [txDescription, setTxDescription] = useState('');
  const [txSending, setTxSending] = useState(false);

  const loadConnectionStatus = useCallback(async () => {
    if (!id || !member) return;
    try {
      const response = await api.get<{ status: string; connection_id: number | null }>(
        `/v2/federation/connections/status/${id}/${member.timebank.id}`
      );
      if (response.success && response.data) {
        setConnectionStatus(response.data.status);
      }
    } catch {
      // Non-critical - just means we can't show status
    }
  }, [id, member]);



  const loadMember = useCallback(async () => {
    if (!id || isExternalMember) return;
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;
    try {
      setIsLoading(true);
      setError(null);
      const response = await api.get<FederatedMember>(`/v2/federation/members/${id}`);
      if (controller.signal.aborted) return;
      if (response.success && response.data) {
        setMember(response.data);
      } else {
        setError(tRef.current('member_profile.not_found_error'));
      }
    } catch (err) {
      if (controller.signal.aborted) return;
      logError('Failed to load federated member profile', err);
      setError(tRef.current('member_profile.load_error'));
    } finally {
      setIsLoading(false);
    }
  }, [id, isExternalMember]);

  useEffect(() => {
    loadMember();
  }, [loadMember]);

  useEffect(() => {
    if (member) loadConnectionStatus();
  }, [member, loadConnectionStatus]);

  const handleConnect = async () => {
    if (!member) return;
    try {
      setConnectLoading(true);
      const response = await api.post('/v2/federation/connections', {
        receiver_id: member.id,
        receiver_tenant_id: member.timebank.id,
      });
      if (response.success) {
        toastRef.current.success(tRef.current('member_profile.connect_sent', 'Connection request sent!'));
        loadConnectionStatus();
      } else {
        toastRef.current.error(response.error || tRef.current('member_profile.connect_failed', 'Failed to send request'));
      }
    } catch (err) {
      logError('Failed to send connection request', err);
      toastRef.current.error(tRef.current('member_profile.connect_failed', 'Failed to send request'));
    } finally {
      setConnectLoading(false);
    }
  };

  const displayName = member
    ? (member.name?.trim() || `${member.first_name || ''} ${member.last_name || ''}`.trim() || 'Member')
    : 'Member';

  const reachKey = member?.service_reach ?? 'local_only';
  const reachMeta = SERVICE_REACH_META[reachKey] ?? SERVICE_REACH_META.local_only ?? { icon: Home };
  const ReachIcon = reachMeta.icon;

  // External members — show actions (message, send credits) instead of a dead-end 404
  if (isExternalMember && id) {
    // Parse ext-{partnerId}-{memberId}
    const parts = id.split('-');
    const extPartnerId = parts[1] ?? '';
    const extTenantId = `ext-${extPartnerId}`;

    return (
      <div className="space-y-6">
        <PageMeta title={t('member_profile.external_member_title', 'External Member')} noIndex />
        <Breadcrumbs
          items={[
            { label: t('member_profile.breadcrumb_federation'), href: tenantPath('/federation') },
            { label: t('member_profile.breadcrumb_members'), href: tenantPath('/federation/members') },
            { label: t('member_profile.external_member_title', 'External Member') },
          ]}
        />
        <GlassCard className="p-8 text-center">
          <Globe className="w-12 h-12 text-indigo-500 mx-auto mb-4" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary mb-2">
            {t('member_profile.external_member_title', 'External Member')}
          </h2>
          <p className="text-theme-muted mb-4 max-w-md mx-auto">
            {t('member_profile.external_member_description', "This member belongs to an external partner community. Their full profile is on their home platform.")}
          </p>
          <div className="flex flex-wrap gap-3 justify-center">
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              startContent={<MessageSquare className="w-4 h-4" aria-hidden="true" />}
              onPress={() => navigate(tenantPath(`/federation/messages?compose=true&to_user=${id}&to_tenant=${extTenantId}`))}
            >
              {t('member_profile.send_message')}
            </Button>
            <Button
              variant="flat"
              className="bg-theme-elevated text-theme-primary"
              startContent={<ArrowLeft className="w-4 h-4" aria-hidden="true" />}
              onPress={() => navigate(tenantPath('/federation/members'))}
            >
              {t('member_profile.back_to_members')}
            </Button>
          </div>
        </GlassCard>
      </div>
    );
  }

  // Loading
  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-20">
        <Spinner size="lg" label={t('member_profile.loading')} />
      </div>
    );
  }

  // Error
  if (error || !member) {
    return (
      <div className="space-y-6">
        <Breadcrumbs
          items={[
            { label: t('member_profile.breadcrumb_federation'), href: tenantPath('/federation') },
            { label: t('member_profile.breadcrumb_members'), href: tenantPath('/federation/members') },
            { label: t('member_profile.breadcrumb_profile') },
          ]}
        />
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary mb-2">
            {t('member_profile.not_found_heading')}
          </h2>
          <p className="text-theme-muted mb-4">
            {error || t('member_profile.not_found_description')}
          </p>
          <div className="flex gap-3 justify-center">
            <Button
              variant="flat"
              className="bg-theme-elevated text-theme-primary"
              startContent={<ArrowLeft className="w-4 h-4" aria-hidden="true" />}
              onPress={() => navigate(tenantPath('/federation/members'))}
            >
              {t('member_profile.back_to_members')}
            </Button>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
              onPress={loadMember}
            >
              {t('member_profile.try_again')}
            </Button>
          </div>
        </GlassCard>
      </div>
    );
  }

  const skills = member.skills ?? [];

  return (
    <div className="space-y-6">
      <PageMeta title={t('member_profile.page_title')} noIndex />
      {/* Breadcrumbs */}
      <Breadcrumbs
        items={[
          { label: t('member_profile.breadcrumb_federation'), href: tenantPath('/federation') },
          { label: t('member_profile.breadcrumb_members'), href: tenantPath('/federation/members') },
          { label: displayName },
        ]}
      />

      {/* Profile Header */}
      <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }}>
        <GlassCard className="p-6 md:p-8">
          <div className="flex flex-col sm:flex-row items-start gap-6">
            {/* Avatar */}
            <div className="relative flex-shrink-0">
              <Avatar
                src={resolveAvatarUrl(member.avatar)}
                name={displayName}
                className="w-24 h-24 ring-4 ring-indigo-500/20"
              />
              <div
                className="absolute -bottom-1 -right-1 w-7 h-7 rounded-full bg-indigo-500 flex items-center justify-center ring-2 ring-white dark:ring-gray-900"
                title={member.timebank.name}
              >
                <Globe className="w-4 h-4 text-white" aria-hidden="true" />
              </div>
            </div>

            {/* Info */}
            <div className="flex-1 min-w-0">
              <h1 className="text-2xl font-bold text-theme-primary">
                {displayName}
              </h1>

              {/* Community badge */}
              <Chip
                size="sm"
                variant="flat"
                className="mt-2 bg-indigo-500/10 text-indigo-600 dark:text-indigo-400"
                startContent={<Globe className="w-3.5 h-3.5" aria-hidden="true" />}
              >
                {member.timebank.name}
              </Chip>

              {/* Meta row */}
              <div className="flex flex-wrap items-center gap-4 mt-3 text-sm text-theme-muted">
                {member.location && (
                  <span className="flex items-center gap-1.5">
                    <MapPin className="w-4 h-4" aria-hidden="true" />
                    {member.location}
                  </span>
                )}
                <span className="flex items-center gap-1.5">
                  <ReachIcon className="w-4 h-4" aria-hidden="true" />
                  {t(`member_profile.reach_${reachKey}`)}
                </span>
              </div>

              {/* Actions */}
              <div className="flex flex-wrap gap-3 mt-4">
                {isAuthenticated && connectionStatus === 'none' && (
                  <Button
                    className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                    startContent={<UserPlus className="w-4 h-4" aria-hidden="true" />}
                    isLoading={connectLoading}
                    onPress={handleConnect}
                  >
                    {t('member_profile.connect', 'Connect')}
                  </Button>
                )}
                {connectionStatus === 'pending_sent' && (
                  <Chip
                    size="md"
                    variant="flat"
                    className="bg-amber-500/10 text-amber-600 dark:text-amber-400"
                  >
                    {t('member_profile.request_pending', 'Request Pending')}
                  </Chip>
                )}
                {connectionStatus === 'accepted' && (
                  <Chip
                    size="md"
                    variant="flat"
                    className="bg-green-500/10 text-green-600 dark:text-green-400"
                  >
                    {t('member_profile.connected', 'Connected')}
                  </Chip>
                )}
                {isAuthenticated && member.messaging_enabled && (
                  <Button
                    className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                    startContent={<MessageSquare className="w-4 h-4" aria-hidden="true" />}
                    onPress={() => {
                      const nameParam = member.name ? `&name=${encodeURIComponent(member.name)}` : '';
                      navigate(
                        tenantPath(`/federation/messages?compose=true&to_user=${member.id}&to_tenant=${member.timebank.id}${nameParam}`)
                      );
                    }}
                  >
                    {t('member_profile.send_message')}
                  </Button>
                )}
                {isAuthenticated && (
                  <Button
                    variant="flat"
                    className="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                    startContent={<Coins className="w-4 h-4" aria-hidden="true" />}
                    onPress={() => txModal.onOpen()}
                  >
                    {t('member_profile.send_credits', 'Send Credits')}
                  </Button>
                )}
                <Button
                  variant="flat"
                  className="bg-theme-elevated text-theme-primary"
                  startContent={<ArrowLeft className="w-4 h-4" aria-hidden="true" />}
                  onPress={() => navigate(tenantPath('/federation/members'))}
                >
                  {t('member_profile.back_to_members')}
                </Button>
              </div>
            </div>
          </div>
        </GlassCard>
      </motion.div>

      {/* Bio */}
      {member.bio && (
        <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.1 }}>
          <GlassCard className="p-6">
            <h2 className="text-lg font-semibold text-theme-primary mb-3 flex items-center gap-2">
              <User className="w-5 h-5 text-indigo-500" aria-hidden="true" />
              {t('member_profile.about')}
            </h2>
            <p className="text-theme-muted whitespace-pre-line">{member.bio}</p>
          </GlassCard>
        </motion.div>
      )}

      {/* Skills */}
      {skills.length > 0 && (
        <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.15 }}>
          <GlassCard className="p-6">
            <h2 className="text-lg font-semibold text-theme-primary mb-3">
              {t('member_profile.skills_interests')}
            </h2>
            <div className="flex flex-wrap gap-2">
              {skills.map((skill) => (
                <Chip
                  key={skill}
                  variant="flat"
                  className="bg-indigo-500/10 text-indigo-600 dark:text-indigo-400"
                >
                  {skill}
                </Chip>
              ))}
            </div>
          </GlassCard>
        </motion.div>
      )}
      {/* Send Credits Modal */}
      {member && (
        <Modal isOpen={txModal.isOpen} onOpenChange={txModal.onOpenChange} size="md">
          <ModalContent>
            {(onClose) => (
              <>
                <ModalHeader className="flex items-center gap-2">
                  <Coins className="w-5 h-5 text-emerald-500" />
                  {t('member_profile.send_credits_to', { name: member.name }) || `Send Credits to ${member.name}`}
                </ModalHeader>
                <ModalBody className="gap-4">
                  <Input
                    label={t('member_profile.amount_hours', 'Amount (hours)')}
                    placeholder="1-100"
                    type="number"
                    min={1}
                    max={100}
                    value={txAmount}
                    onValueChange={setTxAmount}
                    isRequired
                  />
                  <Textarea
                    label={t('member_profile.description', 'Description')}
                    placeholder={t('member_profile.tx_description_placeholder', 'What is this transfer for?')}
                    value={txDescription}
                    onValueChange={setTxDescription}
                    minRows={2}
                    isRequired
                  />
                  <div className="text-sm text-theme-muted bg-theme-elevated rounded-lg p-3">
                    <p>
                      {t('member_profile.tx_summary', {
                        amount: txAmount || '0',
                        name: member.name,
                        community: member.timebank?.name || '',
                      }) || `Transfer ${txAmount || '0'} hour(s) to ${member.name} at ${member.timebank?.name}`}
                    </p>
                  </div>
                </ModalBody>
                <ModalFooter>
                  <Button variant="flat" onPress={onClose}>
                    {t('member_profile.cancel', 'Cancel')}
                  </Button>
                  <Button
                    className="bg-gradient-to-r from-emerald-500 to-teal-600 text-white"
                    startContent={<Coins className="w-4 h-4" />}
                    isLoading={txSending}
                    isDisabled={!txAmount || parseInt(txAmount) < 1 || parseInt(txAmount) > 100 || !txDescription.trim()}
                    onPress={async () => {
                      setTxSending(true);
                      try {
                        const res = await api.post('/v2/federation/transactions', {
                          receiver_id: member.id,
                          receiver_tenant_id: member.timebank?.id ?? member.tenant_id,
                          amount: parseInt(txAmount),
                          description: txDescription.trim(),
                        });
                        if (res.success) {
                          toast.success(
                            t('member_profile.tx_success', 'Credits sent!'),
                            t('member_profile.tx_success_detail', {
                              amount: txAmount,
                              name: member.name,
                            }) || `${txAmount} hour(s) sent to ${member.name}`
                          );
                          setTxAmount('');
                          setTxDescription('');
                          onClose();
                        } else {
                          toast.error(
                            t('member_profile.tx_failed', 'Transfer failed'),
                            (res as { error?: string }).error || 'Unknown error'
                          );
                        }
                      } catch (err) {
                        logError('Federation transaction failed', err);
                        toast.error(t('member_profile.tx_failed', 'Transfer failed'));
                      } finally {
                        setTxSending(false);
                      }
                    }}
                  >
                    {t('member_profile.send_credits', 'Send Credits')}
                  </Button>
                </ModalFooter>
              </>
            )}
          </ModalContent>
        </Modal>
      )}
    </div>
  );
}

export default FederationMemberProfilePage;
