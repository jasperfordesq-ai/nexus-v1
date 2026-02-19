// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Group Exchange Detail Page - View and manage a single group exchange
 *
 * Features:
 * - Breadcrumbs: Group Exchanges > Exchange Title
 * - Status badge and description
 * - Organizer info with avatar
 * - Split type display
 * - Participants table with name, role, hours, weight, confirmed status
 * - Action buttons based on status (organizer vs participant)
 * - Hour split preview table
 * - Completed: transaction receipt
 * - Cancelled: notice
 *
 * Route: /group-exchanges/:id
 */

import { useState, useEffect, useCallback } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import {
  Button,
  Avatar,
  Chip,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Input,
  Spinner,
} from '@heroui/react';
import {
  ArrowLeftRight,
  CheckCircle,
  Users,
  Scale,
  AlertTriangle,
  Play,
  UserPlus,
  X,
  XCircle,
  ArrowRight,
  Search,
  Plus,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { Breadcrumbs } from '@/components/navigation';
import { LoadingScreen, EmptyState } from '@/components/feedback';
import { useAuth, useToast, useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl } from '@/lib/helpers';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

type GroupExchangeStatus =
  | 'draft'
  | 'pending_participants'
  | 'pending_broker'
  | 'active'
  | 'pending_confirmation'
  | 'completed'
  | 'cancelled'
  | 'disputed';

interface GroupExchangeParticipant {
  id: number;
  group_exchange_id: number;
  user_id: number;
  role: 'provider' | 'receiver';
  hours: number;
  weight: number;
  confirmed: number;
  confirmed_at: string | null;
  notes: string | null;
  user_name: string;
  user_avatar: string | null;
  user_email: string | null;
  created_at: string;
}

interface GroupExchangeDetail {
  id: number;
  tenant_id: number;
  title: string;
  description: string | null;
  organizer_id: number;
  organizer_name: string;
  organizer_avatar: string | null;
  listing_id: number | null;
  status: GroupExchangeStatus;
  split_type: 'equal' | 'custom' | 'weighted';
  total_hours: number;
  broker_id: number | null;
  broker_notes: string | null;
  completed_at: string | null;
  created_at: string;
  updated_at: string;
  participants: GroupExchangeParticipant[];
  calculated_split: Record<string, Record<string, number>>;
}

interface SearchResult {
  id: number;
  name?: string;
  first_name?: string;
  last_name?: string;
  avatar_url?: string;
  avatar?: string;
  email?: string;
}

interface StatusConfig {
  label: string;
  color: 'default' | 'warning' | 'secondary' | 'primary' | 'success' | 'danger';
  bgClass: string;
}

const STATUS_CONFIGS: Record<GroupExchangeStatus, StatusConfig> = {
  draft: { label: 'Draft', color: 'default', bgClass: 'bg-gray-500/20 text-gray-400' },
  pending_participants: { label: 'Pending Participants', color: 'warning', bgClass: 'bg-amber-500/20 text-amber-400' },
  pending_broker: { label: 'Pending Broker Approval', color: 'secondary', bgClass: 'bg-purple-500/20 text-purple-400' },
  active: { label: 'Active', color: 'primary', bgClass: 'bg-indigo-500/20 text-indigo-400' },
  pending_confirmation: { label: 'Pending Confirmation', color: 'warning', bgClass: 'bg-amber-500/20 text-amber-400' },
  completed: { label: 'Completed', color: 'success', bgClass: 'bg-emerald-500/20 text-emerald-400' },
  cancelled: { label: 'Cancelled', color: 'danger', bgClass: 'bg-red-500/20 text-red-400' },
  disputed: { label: 'Disputed', color: 'danger', bgClass: 'bg-red-500/20 text-red-400' },
};

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function GroupExchangeDetailPage() {
  usePageTitle('Group Exchange');
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { user } = useAuth();
  const { tenantPath } = useTenant();
  const toast = useToast();

  const [exchange, setExchange] = useState<GroupExchangeDetail | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Cancel modal
  const [showCancelModal, setShowCancelModal] = useState(false);

  // Add participant modal
  const [showAddParticipantModal, setShowAddParticipantModal] = useState(false);
  const [addRole, setAddRole] = useState<'provider' | 'receiver'>('provider');
  const [searchQuery, setSearchQuery] = useState('');
  const [searchResults, setSearchResults] = useState<SearchResult[]>([]);
  const [isSearching, setIsSearching] = useState(false);

  // ─────────────────────────────────────────────────────────────────────────
  // Data loading
  // ─────────────────────────────────────────────────────────────────────────

  const loadExchange = useCallback(async () => {
    if (!id) return;

    try {
      setIsLoading(true);
      setError(null);
      const response = await api.get<{ data: GroupExchangeDetail }>(`/v2/group-exchanges/${id}`);

      if (response.success && response.data) {
        setExchange(response.data as unknown as GroupExchangeDetail);
      } else {
        setError('Exchange not found');
      }
    } catch (err) {
      setError('Exchange not found');
      logError('Failed to load group exchange', err);
    } finally {
      setIsLoading(false);
    }
  }, [id]);

  useEffect(() => {
    loadExchange();
  }, [loadExchange]);

  // ─────────────────────────────────────────────────────────────────────────
  // Derived state
  // ─────────────────────────────────────────────────────────────────────────

  const isOrganizer = exchange?.organizer_id === user?.id;
  const isParticipant = exchange?.participants?.some((p) => p.user_id === user?.id) ?? false;
  const currentUserParticipant = exchange?.participants?.find((p) => p.user_id === user?.id);
  const statusConfig = exchange ? STATUS_CONFIGS[exchange.status] || STATUS_CONFIGS.draft : STATUS_CONFIGS.draft;

  const providers = exchange?.participants?.filter((p) => p.role === 'provider') ?? [];
  const receivers = exchange?.participants?.filter((p) => p.role === 'receiver') ?? [];
  const allConfirmed = exchange?.participants?.every((p) => p.confirmed) ?? false;

  // Status-based action flags
  const canAddParticipants = isOrganizer && ['draft', 'pending_participants'].includes(exchange?.status ?? '');
  const canStartExchange = isOrganizer && ['draft', 'pending_participants'].includes(exchange?.status ?? '') && providers.length >= 1 && receivers.length >= 1;
  const canConfirm = isParticipant && exchange?.status === 'pending_confirmation' && currentUserParticipant && !currentUserParticipant.confirmed;
  const canComplete = isOrganizer && exchange?.status === 'pending_confirmation' && allConfirmed;
  const canCancel = isOrganizer && !['completed', 'cancelled'].includes(exchange?.status ?? '');

  // ─────────────────────────────────────────────────────────────────────────
  // Actions
  // ─────────────────────────────────────────────────────────────────────────

  async function handleUpdateStatus(newStatus: string) {
    if (!exchange) return;

    try {
      setIsSubmitting(true);
      await api.put(`/v2/group-exchanges/${exchange.id}`, { status: newStatus });

      // If just updating status, use the status endpoint approach
      // Since the update endpoint only handles field changes,
      // for status changes we rely on specific action endpoints
      if (newStatus === 'pending_confirmation') {
        // Transition to pending_confirmation via update
        await api.put(`/v2/group-exchanges/${exchange.id}`, {});
      }

      toast.success('Exchange updated');
      loadExchange();
    } catch (err) {
      toast.error('Failed to update exchange');
      logError('Failed to update exchange status', err);
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleConfirm() {
    if (!exchange) return;

    try {
      setIsSubmitting(true);
      await api.post(`/v2/group-exchanges/${exchange.id}/confirm`);
      toast.success('Hours confirmed!');
      loadExchange();
    } catch (err) {
      toast.error('Failed to confirm hours');
      logError('Failed to confirm participation', err);
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleComplete() {
    if (!exchange) return;

    try {
      setIsSubmitting(true);
      const response = await api.post<{ message: string; transaction_ids: number[] }>(
        `/v2/group-exchanges/${exchange.id}/complete`
      );

      if (response.success) {
        toast.success('Exchange completed!', 'All transactions have been created.');
        loadExchange();
      } else {
        toast.error('Failed to complete', response.error || 'An error occurred.');
      }
    } catch (err) {
      toast.error('Failed to complete exchange');
      logError('Failed to complete group exchange', err);
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleCancel() {
    if (!exchange) return;

    try {
      setIsSubmitting(true);
      await api.delete(`/v2/group-exchanges/${exchange.id}`);
      toast.success('Exchange cancelled');
      setShowCancelModal(false);
      navigate(tenantPath('/group-exchanges'));
    } catch (err) {
      toast.error('Failed to cancel exchange');
      logError('Failed to cancel group exchange', err);
    } finally {
      setIsSubmitting(false);
    }
  }

  // ─────────────────────────────────────────────────────────────────────────
  // Add participant search
  // ─────────────────────────────────────────────────────────────────────────

  async function handleSearchMembers(query: string) {
    setSearchQuery(query);

    if (query.trim().length < 2) {
      setSearchResults([]);
      return;
    }

    try {
      setIsSearching(true);
      const response = await api.get<{ data: SearchResult[] }>(`/v2/users?search=${encodeURIComponent(query.trim())}&limit=10`);

      if (response.success && response.data) {
        const results = Array.isArray(response.data) ? response.data : [];
        // Filter out existing participants
        const existingIds = new Set(exchange?.participants?.map((p) => p.user_id) ?? []);
        setSearchResults(results.filter((r: SearchResult) => !existingIds.has(r.id)));
      }
    } catch (err) {
      logError('Failed to search users', err);
    } finally {
      setIsSearching(false);
    }
  }

  async function handleAddParticipant(userId: number) {
    if (!exchange) return;

    try {
      setIsSubmitting(true);
      await api.post(`/v2/group-exchanges/${exchange.id}/participants`, {
        user_id: userId,
        role: addRole,
      });
      toast.success('Participant added');
      setSearchResults((prev) => prev.filter((r) => r.id !== userId));
      loadExchange();
    } catch (err) {
      toast.error('Failed to add participant');
      logError('Failed to add participant', err);
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleRemoveParticipant(userId: number) {
    if (!exchange) return;

    try {
      setIsSubmitting(true);
      await api.delete(`/v2/group-exchanges/${exchange.id}/participants/${userId}`);
      toast.success('Participant removed');
      loadExchange();
    } catch (err) {
      toast.error('Failed to remove participant');
      logError('Failed to remove participant', err);
    } finally {
      setIsSubmitting(false);
    }
  }

  // ─────────────────────────────────────────────────────────────────────────
  // Build split rows
  // ─────────────────────────────────────────────────────────────────────────

  function buildSplitRows(): { providerName: string; receiverName: string; amount: number }[] {
    if (!exchange?.calculated_split) return [];

    const rows: { providerName: string; receiverName: string; amount: number }[] = [];
    const participantMap = new Map(exchange.participants.map((p) => [String(p.user_id), p.user_name]));

    for (const [providerId, receivers] of Object.entries(exchange.calculated_split)) {
      for (const [receiverId, amount] of Object.entries(receivers)) {
        rows.push({
          providerName: participantMap.get(providerId) || `User #${providerId}`,
          receiverName: participantMap.get(receiverId) || `User #${receiverId}`,
          amount: amount as number,
        });
      }
    }

    return rows;
  }

  const splitRows = exchange ? buildSplitRows() : [];

  // ─────────────────────────────────────────────────────────────────────────
  // Render
  // ─────────────────────────────────────────────────────────────────────────

  if (isLoading) {
    return <LoadingScreen message="Loading group exchange..." />;
  }

  if (error || !exchange) {
    return (
      <EmptyState
        icon={<AlertTriangle className="w-12 h-12" />}
        title="Exchange Not Found"
        description={error || 'The group exchange you are looking for does not exist.'}
        action={
          <Link to={tenantPath('/group-exchanges')}>
            <Button className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white">
              View Group Exchanges
            </Button>
          </Link>
        }
      />
    );
  }

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      className="max-w-3xl mx-auto space-y-6"
    >
      {/* Breadcrumbs */}
      <Breadcrumbs items={[
        { label: 'Group Exchanges', href: tenantPath('/group-exchanges') },
        { label: exchange.title },
      ]} />

      {/* Status Card */}
      <GlassCard className="p-6">
        <div className="flex items-center gap-4 mb-4">
          <div className={`w-12 h-12 rounded-full flex items-center justify-center ${statusConfig.bgClass}`}>
            <ArrowLeftRight className="w-6 h-6" aria-hidden="true" />
          </div>
          <div>
            <h1 className="text-xl font-bold text-theme-primary">{exchange.title}</h1>
            <div className="flex items-center gap-2 mt-1">
              <Chip
                color={statusConfig.color}
                variant="flat"
                size="lg"
              >
                {statusConfig.label}
              </Chip>
              <Chip size="sm" variant="flat" className="bg-theme-elevated text-theme-muted capitalize">
                {exchange.split_type} split
              </Chip>
            </div>
          </div>
        </div>

        {exchange.description && (
          <p className="text-theme-muted mb-4">{exchange.description}</p>
        )}

        {/* Organizer */}
        <div className="flex items-center gap-3 mb-4">
          <Avatar
            src={resolveAvatarUrl(exchange.organizer_avatar)}
            name={exchange.organizer_name}
            size="sm"
          />
          <div>
            <p className="text-sm text-theme-muted">Organized by</p>
            <p className="font-medium text-theme-primary">
              {exchange.organizer_name}
              {isOrganizer && ' (You)'}
            </p>
          </div>
        </div>

        {/* Hours */}
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
          <div className="bg-theme-elevated rounded-lg p-4">
            <p className="text-sm text-theme-muted">Total Hours</p>
            <p className="text-2xl font-bold text-theme-primary">{Number(exchange.total_hours)}</p>
          </div>
          <div className="bg-theme-elevated rounded-lg p-4">
            <p className="text-sm text-theme-muted">Providers</p>
            <p className="text-2xl font-bold text-emerald-400">{providers.length}</p>
          </div>
          <div className="bg-theme-elevated rounded-lg p-4">
            <p className="text-sm text-theme-muted">Receivers</p>
            <p className="text-2xl font-bold text-amber-400">{receivers.length}</p>
          </div>
          <div className="bg-theme-elevated rounded-lg p-4">
            <p className="text-sm text-theme-muted">Created</p>
            <p className="text-sm font-medium text-theme-primary">
              <time dateTime={exchange.created_at}>
                {new Date(exchange.created_at).toLocaleDateString()}
              </time>
            </p>
          </div>
        </div>

        {/* Broker Notes */}
        {exchange.broker_notes && (
          <div className="bg-amber-500/10 rounded-lg p-4 mt-4">
            <h3 className="text-sm font-medium text-amber-400 mb-2">Broker Notes</h3>
            <p className="text-theme-primary">{exchange.broker_notes}</p>
          </div>
        )}

        {/* Completed notice */}
        {exchange.status === 'completed' && exchange.completed_at && (
          <div className="bg-emerald-500/10 rounded-lg p-4 mt-4">
            <h3 className="text-sm font-medium text-emerald-400 mb-1 flex items-center gap-2">
              <CheckCircle className="w-4 h-4" aria-hidden="true" />
              Exchange Completed
            </h3>
            <p className="text-theme-muted text-sm">
              Completed on{' '}
              <time dateTime={exchange.completed_at}>
                {new Date(exchange.completed_at).toLocaleDateString()}
              </time>
              . All transactions have been created.
            </p>
          </div>
        )}

        {/* Cancelled notice */}
        {exchange.status === 'cancelled' && (
          <div className="bg-red-500/10 rounded-lg p-4 mt-4">
            <h3 className="text-sm font-medium text-red-400 mb-1 flex items-center gap-2">
              <XCircle className="w-4 h-4" aria-hidden="true" />
              Exchange Cancelled
            </h3>
            <p className="text-theme-muted text-sm">
              This exchange has been cancelled. No transactions were created.
            </p>
          </div>
        )}

        {/* Actions */}
        {(canAddParticipants || canStartExchange || canConfirm || canComplete || canCancel) && (
          <div className="flex flex-wrap gap-3 pt-4 mt-4 border-t border-theme-default">
            {canAddParticipants && (
              <Button
                color="primary"
                variant="flat"
                startContent={<UserPlus className="w-4 h-4" aria-hidden="true" />}
                onPress={() => {
                  setShowAddParticipantModal(true);
                  setSearchQuery('');
                  setSearchResults([]);
                }}
              >
                Add Participants
              </Button>
            )}
            {canStartExchange && (
              <Button
                color="primary"
                startContent={<Play className="w-4 h-4" aria-hidden="true" />}
                onPress={() => handleUpdateStatus('pending_confirmation')}
                isLoading={isSubmitting}
              >
                Start Exchange
              </Button>
            )}
            {canConfirm && (
              <Button
                color="warning"
                startContent={<CheckCircle className="w-4 h-4" aria-hidden="true" />}
                onPress={handleConfirm}
                isLoading={isSubmitting}
              >
                Confirm My Hours
              </Button>
            )}
            {canComplete && (
              <Button
                color="success"
                startContent={<CheckCircle className="w-4 h-4" aria-hidden="true" />}
                onPress={handleComplete}
                isLoading={isSubmitting}
              >
                Complete Exchange
              </Button>
            )}
            {canCancel && (
              <Button
                color="danger"
                variant="flat"
                startContent={<XCircle className="w-4 h-4" aria-hidden="true" />}
                onPress={() => setShowCancelModal(true)}
              >
                Cancel Exchange
              </Button>
            )}
          </div>
        )}
      </GlassCard>

      {/* Participants */}
      <GlassCard className="p-6">
        <h2 className="text-xl font-semibold text-theme-primary mb-6 flex items-center gap-3">
          <Users className="w-5 h-5 text-indigo-400" aria-hidden="true" />
          Participants ({exchange.participants.length})
        </h2>

        {exchange.participants.length === 0 ? (
          <div className="text-center py-6">
            <Users className="w-10 h-10 text-theme-subtle mx-auto mb-2" aria-hidden="true" />
            <p className="text-theme-muted text-sm">No participants yet.</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-theme-default">
                  <th className="text-left py-2 px-3 text-theme-muted font-medium">Name</th>
                  <th className="text-left py-2 px-3 text-theme-muted font-medium">Role</th>
                  {exchange.split_type === 'custom' && (
                    <th className="text-right py-2 px-3 text-theme-muted font-medium">Hours</th>
                  )}
                  {exchange.split_type === 'weighted' && (
                    <th className="text-right py-2 px-3 text-theme-muted font-medium">Weight</th>
                  )}
                  <th className="text-center py-2 px-3 text-theme-muted font-medium">Confirmed</th>
                  {canAddParticipants && (
                    <th className="text-right py-2 px-3 text-theme-muted font-medium" />
                  )}
                </tr>
              </thead>
              <tbody>
                {exchange.participants.map((p) => (
                  <tr key={p.id} className="border-b border-theme-default/50">
                    <td className="py-3 px-3">
                      <div className="flex items-center gap-2">
                        <Avatar
                          src={resolveAvatarUrl(p.user_avatar)}
                          name={p.user_name}
                          size="sm"
                        />
                        <span className="text-theme-primary">
                          {p.user_name}
                          {p.user_id === user?.id && (
                            <span className="text-xs text-theme-subtle ml-1">(You)</span>
                          )}
                        </span>
                      </div>
                    </td>
                    <td className="py-3 px-3">
                      <Chip
                        size="sm"
                        variant="flat"
                        color={p.role === 'provider' ? 'success' : 'warning'}
                      >
                        {p.role}
                      </Chip>
                    </td>
                    {exchange.split_type === 'custom' && (
                      <td className="py-3 px-3 text-right text-theme-primary">{Number(p.hours)}h</td>
                    )}
                    {exchange.split_type === 'weighted' && (
                      <td className="py-3 px-3 text-right text-theme-primary">{Number(p.weight)}x</td>
                    )}
                    <td className="py-3 px-3 text-center">
                      {p.confirmed ? (
                        <span className="flex items-center justify-center gap-1 text-emerald-400 text-xs">
                          <CheckCircle className="w-4 h-4" aria-hidden="true" />
                          Confirmed
                        </span>
                      ) : (
                        <span className="text-theme-subtle text-xs">Pending</span>
                      )}
                    </td>
                    {canAddParticipants && (
                      <td className="py-3 px-3 text-right">
                        <Button
                          isIconOnly
                          size="sm"
                          variant="flat"
                          className="bg-red-500/20 text-red-400"
                          onPress={() => handleRemoveParticipant(p.user_id)}
                          aria-label={`Remove ${p.user_name}`}
                        >
                          <X className="w-4 h-4" />
                        </Button>
                      </td>
                    )}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </GlassCard>

      {/* Hour Split Preview */}
      {splitRows.length > 0 && (
        <GlassCard className="p-6">
          <h2 className="text-xl font-semibold text-theme-primary mb-6 flex items-center gap-3">
            <Scale className="w-5 h-5 text-indigo-400" aria-hidden="true" />
            Hour Split
          </h2>

          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-theme-default">
                  <th className="text-left py-2 px-3 text-theme-muted font-medium">Provider</th>
                  <th className="text-center py-2 px-3 text-theme-muted font-medium" aria-hidden="true" />
                  <th className="text-left py-2 px-3 text-theme-muted font-medium">Receiver</th>
                  <th className="text-right py-2 px-3 text-theme-muted font-medium">Hours</th>
                </tr>
              </thead>
              <tbody>
                {splitRows.map((row, idx) => (
                  <tr key={idx} className="border-b border-theme-default/50">
                    <td className="py-2 px-3 text-emerald-400">{row.providerName}</td>
                    <td className="py-2 px-3 text-center text-theme-subtle">
                      <ArrowRight className="w-4 h-4 inline" aria-label="gives to" />
                    </td>
                    <td className="py-2 px-3 text-amber-400">{row.receiverName}</td>
                    <td className="py-2 px-3 text-right font-medium text-theme-primary">{row.amount}h</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </GlassCard>
      )}

      {/* Cancel Confirmation Modal */}
      <Modal
        isOpen={showCancelModal}
        onClose={() => setShowCancelModal(false)}
        classNames={{
          base: 'bg-content1 border border-theme-default',
          header: 'border-b border-theme-default',
          body: 'py-6',
          footer: 'border-t border-theme-default',
        }}
      >
        <ModalContent>
          <ModalHeader className="text-theme-primary">Cancel Group Exchange?</ModalHeader>
          <ModalBody>
            <p className="text-theme-muted">
              Are you sure you want to cancel this group exchange? This action cannot be undone.
              No transactions will be created.
            </p>
          </ModalBody>
          <ModalFooter>
            <Button
              variant="flat"
              onPress={() => setShowCancelModal(false)}
              className="bg-theme-elevated text-theme-primary"
            >
              Keep Exchange
            </Button>
            <Button color="danger" onPress={handleCancel} isLoading={isSubmitting}>
              Cancel Exchange
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Add Participant Modal */}
      <Modal
        isOpen={showAddParticipantModal}
        onClose={() => setShowAddParticipantModal(false)}
        size="lg"
        classNames={{
          base: 'bg-content1 border border-theme-default',
          header: 'border-b border-theme-default',
          body: 'py-6',
          footer: 'border-t border-theme-default',
        }}
      >
        <ModalContent>
          <ModalHeader className="text-theme-primary">Add Participant</ModalHeader>
          <ModalBody>
            {/* Role selection */}
            <div className="flex gap-2 mb-4">
              <Button
                size="sm"
                variant={addRole === 'provider' ? 'solid' : 'flat'}
                color={addRole === 'provider' ? 'success' : 'default'}
                onPress={() => setAddRole('provider')}
                className={addRole !== 'provider' ? 'bg-theme-elevated text-theme-muted' : ''}
              >
                Provider
              </Button>
              <Button
                size="sm"
                variant={addRole === 'receiver' ? 'solid' : 'flat'}
                color={addRole === 'receiver' ? 'warning' : 'default'}
                onPress={() => setAddRole('receiver')}
                className={addRole !== 'receiver' ? 'bg-theme-elevated text-theme-muted' : ''}
              >
                Receiver
              </Button>
            </div>

            <Input
              placeholder="Search members by name..."
              value={searchQuery}
              onChange={(e) => handleSearchMembers(e.target.value)}
              startContent={<Search className="w-4 h-4 text-theme-muted" aria-hidden="true" />}
              endContent={isSearching ? <Spinner size="sm" /> : null}
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default',
              }}
              aria-label="Search members"
            />

            {searchResults.length > 0 && (
              <div className="mt-4 space-y-2 max-h-60 overflow-y-auto">
                {searchResults.map((result) => {
                  const displayName = result.name || [result.first_name, result.last_name].filter(Boolean).join(' ') || 'Unknown';
                  return (
                    <div
                      key={result.id}
                      className="flex items-center justify-between p-3 rounded-lg bg-theme-elevated"
                    >
                      <div className="flex items-center gap-3">
                        <Avatar
                          src={resolveAvatarUrl(result.avatar_url || result.avatar)}
                          name={displayName}
                          size="sm"
                        />
                        <div>
                          <p className="font-medium text-theme-primary text-sm">{displayName}</p>
                          {result.email && (
                            <p className="text-xs text-theme-subtle">{result.email}</p>
                          )}
                        </div>
                      </div>
                      <Button
                        size="sm"
                        variant="flat"
                        color={addRole === 'provider' ? 'success' : 'warning'}
                        onPress={() => handleAddParticipant(result.id)}
                        isLoading={isSubmitting}
                        startContent={<Plus className="w-3 h-3" aria-hidden="true" />}
                      >
                        Add as {addRole}
                      </Button>
                    </div>
                  );
                })}
              </div>
            )}

            {searchQuery.trim().length >= 2 && searchResults.length === 0 && !isSearching && (
              <div className="text-center py-4 text-theme-muted text-sm">
                No members found matching your search.
              </div>
            )}
          </ModalBody>
          <ModalFooter>
            <Button
              variant="flat"
              onPress={() => setShowAddParticipantModal(false)}
              className="bg-theme-elevated text-theme-primary"
            >
              Done
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </motion.div>
  );
}

export default GroupExchangeDetailPage;
