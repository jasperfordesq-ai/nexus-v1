/**
 * Exchange Detail Page - View and manage a single exchange
 *
 * Features:
 * - Status card with current exchange status
 * - Exchange details (listing, parties, hours)
 * - Timeline of status changes with stagger animation
 * - Message link to other party
 * - Action buttons for exchange workflow
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
  Textarea,
} from '@heroui/react';
import {
  ArrowRightLeft,
  CheckCircle,
  MessageSquare,
  Play,
  Check,
  X,
  XCircle,
  AlertTriangle,
  Clock,
  Circle,
  Ban,
  UserCheck,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { Breadcrumbs } from '@/components/navigation';
import { LoadingScreen, EmptyState } from '@/components/feedback';
import { useAuth, useToast, useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl, formatRelativeTime } from '@/lib/helpers';
import { EXCHANGE_STATUS_CONFIG, MAX_EXCHANGE_HOURS, getStatusIconBgClass } from '@/lib/exchange-status';
import type { Exchange, ExchangeHistoryEntry } from '@/types/api';

/* ───────────────────────── Timeline Helpers ───────────────────────── */

interface TimelineEntry {
  icon: React.ReactNode;
  colorClass: string;
  label: string;
  actor?: string | null;
  timestamp: string;
  notes?: string | null;
}

function getTimelineIcon(action: string, newStatus?: string | null): React.ReactNode {
  const iconClass = 'w-4 h-4';
  if (action === 'created' || newStatus === 'pending_provider' || newStatus === 'pending_broker') {
    return <Circle className={iconClass} />;
  }
  if (action === 'accepted' || newStatus === 'accepted') {
    return <Check className={iconClass} />;
  }
  if (action === 'declined' || newStatus === 'cancelled') {
    return <Ban className={iconClass} />;
  }
  if (action === 'started' || newStatus === 'in_progress') {
    return <Play className={iconClass} />;
  }
  if (action === 'completed' || newStatus === 'completed') {
    return <CheckCircle className={iconClass} />;
  }
  if (action === 'confirmed' || newStatus === 'pending_confirmation') {
    return <UserCheck className={iconClass} />;
  }
  if (newStatus === 'disputed') {
    return <AlertTriangle className={iconClass} />;
  }
  return <Clock className={iconClass} />;
}

function getTimelineColor(action: string, newStatus?: string | null): string {
  // Green for positive actions
  if (
    action === 'accepted' || action === 'confirmed' || action === 'completed' ||
    newStatus === 'accepted' || newStatus === 'completed'
  ) {
    return 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30';
  }
  // Red for negative actions
  if (
    action === 'declined' || action === 'cancelled' ||
    newStatus === 'cancelled' || newStatus === 'disputed'
  ) {
    return 'bg-red-500/20 text-red-400 border-red-500/30';
  }
  // Blue for neutral actions
  return 'bg-indigo-500/20 text-indigo-400 border-indigo-500/30';
}

function getTimelineLabel(action: string, newStatus?: string | null): string {
  if (action === 'created') return 'Exchange Created';
  if (action === 'accepted' || newStatus === 'accepted') return 'Exchange Accepted';
  if (action === 'declined') return 'Exchange Declined';
  if (action === 'started' || newStatus === 'in_progress') return 'Exchange Started';
  if (action === 'completed' || newStatus === 'completed') return 'Exchange Completed';
  if (action === 'confirmed') return 'Hours Confirmed';
  if (action === 'cancelled' || newStatus === 'cancelled') return 'Exchange Cancelled';
  if (newStatus === 'pending_confirmation') return 'Awaiting Confirmation';
  if (newStatus === 'pending_broker') return 'Sent to Broker';
  if (newStatus === 'disputed') return 'Exchange Disputed';
  // Fallback: capitalize the action
  return action.charAt(0).toUpperCase() + action.slice(1).replace(/_/g, ' ');
}

function buildTimeline(history: ExchangeHistoryEntry[]): TimelineEntry[] {
  return history.map((entry) => ({
    icon: getTimelineIcon(entry.action, entry.new_status),
    colorClass: getTimelineColor(entry.action, entry.new_status),
    label: getTimelineLabel(entry.action, entry.new_status),
    actor: entry.actor_name,
    timestamp: entry.created_at,
    notes: entry.notes,
  }));
}

/* ───────────────────────── Main Component ───────────────────────── */

export function ExchangeDetailPage() {
  usePageTitle('Exchange Details');
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { user } = useAuth();
  const { tenantPath } = useTenant();
  const toast = useToast();

  const [exchange, setExchange] = useState<Exchange | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Modal states
  const [showDeclineModal, setShowDeclineModal] = useState(false);
  const [showConfirmModal, setShowConfirmModal] = useState(false);
  const [showCancelModal, setShowCancelModal] = useState(false);
  const [declineReason, setDeclineReason] = useState('');
  const [confirmHours, setConfirmHours] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  const loadExchange = useCallback(async () => {
    if (!id) return;

    try {
      setIsLoading(true);
      setError(null);
      const response = await api.get<Exchange>(`/v2/exchanges/${id}`);
      if (response.success && response.data) {
        setExchange(response.data);
        setConfirmHours(response.data.proposed_hours.toString());
      } else {
        setError('Exchange not found');
      }
    } catch (err) {
      setError('Exchange not found');
      logError('Failed to load exchange', err);
    } finally {
      setIsLoading(false);
    }
  }, [id]);

  useEffect(() => {
    loadExchange();
  }, [loadExchange]);

  const isRequester = exchange?.requester_id === user?.id;
  const isProvider = exchange?.provider_id === user?.id;

  // Determine the other party for messaging
  const otherParty = isRequester ? exchange?.provider : exchange?.requester;
  const otherPartyId = isRequester ? exchange?.provider_id : exchange?.requester_id;

  // Exchange is "active" (not completed or cancelled) — show message link
  const isActive = exchange
    ? !['completed', 'cancelled'].includes(exchange.status)
    : false;

  async function handleAccept() {
    if (!exchange) return;

    try {
      setIsSubmitting(true);
      await api.post(`/v2/exchanges/${exchange.id}/accept`);
      toast.success('Exchange accepted!');
      loadExchange();
    } catch (err) {
      toast.error('Failed to accept exchange');
      logError('Failed to accept exchange', err);
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleDecline() {
    if (!exchange) return;

    try {
      setIsSubmitting(true);
      await api.post(`/v2/exchanges/${exchange.id}/decline`, { reason: declineReason });
      toast.success('Exchange declined');
      setShowDeclineModal(false);
      loadExchange();
    } catch (err) {
      toast.error('Failed to decline exchange');
      logError('Failed to decline exchange', err);
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleStart() {
    if (!exchange) return;

    try {
      setIsSubmitting(true);
      await api.post(`/v2/exchanges/${exchange.id}/start`);
      toast.success('Exchange started!');
      loadExchange();
    } catch (err) {
      toast.error('Failed to start exchange');
      logError('Failed to start exchange', err);
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleComplete() {
    if (!exchange) return;

    try {
      setIsSubmitting(true);
      await api.post(`/v2/exchanges/${exchange.id}/complete`);
      toast.success('Exchange marked as complete. Please confirm hours.');
      loadExchange();
    } catch (err) {
      toast.error('Failed to complete exchange');
      logError('Failed to complete exchange', err);
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleConfirm() {
    if (!exchange) return;

    const hours = parseFloat(confirmHours);
    if (isNaN(hours) || hours <= 0) {
      toast.error('Please enter valid hours');
      return;
    }

    if (hours > MAX_EXCHANGE_HOURS) {
      toast.error(`Maximum ${MAX_EXCHANGE_HOURS} hours per exchange`);
      return;
    }

    try {
      setIsSubmitting(true);
      await api.post(`/v2/exchanges/${exchange.id}/confirm`, { hours });
      toast.success('Hours confirmed!');
      setShowConfirmModal(false);
      loadExchange();
    } catch (err) {
      toast.error('Failed to confirm hours');
      logError('Failed to confirm hours', err);
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleCancel() {
    if (!exchange) return;

    try {
      setIsSubmitting(true);
      await api.delete(`/v2/exchanges/${exchange.id}`);
      toast.success('Exchange cancelled');
      setShowCancelModal(false);
      navigate(tenantPath('/exchanges'));
    } catch (err) {
      toast.error('Failed to cancel exchange');
      logError('Failed to cancel exchange', err);
    } finally {
      setIsSubmitting(false);
    }
  }

  if (isLoading) {
    return <LoadingScreen message="Loading exchange..." />;
  }

  if (error || !exchange) {
    return (
      <EmptyState
        icon={<AlertTriangle className="w-12 h-12" />}
        title="Exchange Not Found"
        description={error || 'The exchange you are looking for does not exist'}
        action={
          <Link to={tenantPath("/exchanges")}>
            <Button className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white">
              View My Exchanges
            </Button>
          </Link>
        }
      />
    );
  }

  const statusConfig = EXCHANGE_STATUS_CONFIG[exchange.status];

  // Determine which actions are available
  const canAccept = isProvider && exchange.status === 'pending_provider';
  const canDecline = isProvider && exchange.status === 'pending_provider';
  const canStart = isProvider && exchange.status === 'accepted';
  const canComplete = isProvider && exchange.status === 'in_progress';
  const canConfirm =
    exchange.status === 'pending_confirmation' &&
    ((isRequester && !exchange.requester_confirmed_at) ||
      (isProvider && !exchange.provider_confirmed_at));
  const canCancel =
    isRequester &&
    ['pending_provider', 'pending_broker', 'accepted'].includes(exchange.status);

  // Build timeline from status_history
  const timeline = exchange.status_history ? buildTimeline(exchange.status_history) : [];

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      className="max-w-3xl mx-auto space-y-6"
    >
      {/* Breadcrumbs */}
      <Breadcrumbs items={[
        { label: 'Exchanges', href: tenantPath('/exchanges') },
        { label: exchange?.listing?.title || 'Exchange' },
      ]} />

      {/* Status Card */}
      <GlassCard className="p-6">
        <div className="flex items-center gap-4 mb-4">
          <div className={`w-12 h-12 rounded-full flex items-center justify-center ${getStatusIconBgClass(statusConfig.color)}`}>
            <ArrowRightLeft className="w-6 h-6" aria-hidden="true" />
          </div>
          <div>
            <Chip
              color={statusConfig.color}
              variant="flat"
              size="lg"
            >
              {statusConfig.label}
            </Chip>
            <p className="text-sm text-theme-muted mt-1">{statusConfig.description}</p>
          </div>
        </div>
      </GlassCard>

      {/* Exchange Details */}
      <GlassCard className="p-6">
        <h2 className="text-xl font-semibold text-theme-primary mb-4">
          Exchange Details
        </h2>

        {/* Listing */}
        <div className="mb-6">
          <h3 className="text-sm font-medium text-theme-muted mb-2">Service</h3>
          <Link to={tenantPath(`/listings/${exchange.listing_id}`)} className="hover:underline">
            <p className="text-lg font-semibold text-theme-primary">
              {exchange.listing?.title || 'Service Exchange'}
            </p>
          </Link>
        </div>

        {/* Parties */}
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-6">
          <div>
            <h3 className="text-sm font-medium text-theme-muted mb-2">Requester</h3>
            <div className="flex items-center gap-3">
              <Avatar
                src={resolveAvatarUrl(exchange.requester?.avatar)}
                name={exchange.requester?.name || 'Unknown'}
                size="sm"
              />
              <div>
                <p className="font-medium text-theme-primary">
                  {exchange.requester?.name || 'Unknown'}
                  {isRequester && ' (You)'}
                </p>
                {exchange.requester_confirmed_at && (
                  <p className="text-xs text-emerald-400 flex items-center gap-1">
                    <CheckCircle className="w-3 h-3" aria-hidden="true" />
                    Confirmed {exchange.requester_confirmed_hours}h
                  </p>
                )}
              </div>
            </div>
          </div>

          <div>
            <h3 className="text-sm font-medium text-theme-muted mb-2">Provider</h3>
            <div className="flex items-center gap-3">
              <Avatar
                src={resolveAvatarUrl(exchange.provider?.avatar)}
                name={exchange.provider?.name || 'Unknown'}
                size="sm"
              />
              <div>
                <p className="font-medium text-theme-primary">
                  {exchange.provider?.name || 'Unknown'}
                  {isProvider && ' (You)'}
                </p>
                {exchange.provider_confirmed_at && (
                  <p className="text-xs text-emerald-400 flex items-center gap-1">
                    <CheckCircle className="w-3 h-3" aria-hidden="true" />
                    Confirmed {exchange.provider_confirmed_hours}h
                  </p>
                )}
              </div>
            </div>
          </div>
        </div>

        {/* Message Other Party */}
        {isActive && otherParty && otherPartyId && (
          <div className="mb-6">
            <Link to={tenantPath(`/messages/new/${otherPartyId}`)}>
              <Button
                variant="flat"
                className="bg-indigo-500/10 text-indigo-400 hover:bg-indigo-500/20"
                startContent={<MessageSquare className="w-4 h-4" aria-hidden="true" />}
              >
                Message {otherParty.name || 'Other Party'}
              </Button>
            </Link>
          </div>
        )}

        {/* Hours */}
        <div className="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-6">
          <div className="bg-theme-elevated rounded-lg p-4">
            <p className="text-sm text-theme-muted">Proposed Hours</p>
            <p className="text-2xl font-bold text-theme-primary">{exchange.proposed_hours}</p>
          </div>
          {exchange.final_hours && (
            <div className="bg-emerald-500/10 rounded-lg p-4">
              <p className="text-sm text-emerald-400">Final Hours</p>
              <p className="text-2xl font-bold text-emerald-400">{exchange.final_hours}</p>
            </div>
          )}
          <div className="bg-theme-elevated rounded-lg p-4">
            <p className="text-sm text-theme-muted">Created</p>
            <p className="text-sm font-medium text-theme-primary">
              <time dateTime={exchange.created_at}>
                {new Date(exchange.created_at).toLocaleDateString()}
              </time>
            </p>
          </div>
        </div>

        {/* Message */}
        {exchange.message && (
          <div className="bg-theme-elevated rounded-lg p-4 mb-6">
            <h3 className="text-sm font-medium text-theme-muted mb-2 flex items-center gap-2">
              <MessageSquare className="w-4 h-4" aria-hidden="true" />
              Message from requester
            </h3>
            <p className="text-theme-primary">{exchange.message}</p>
          </div>
        )}

        {/* Broker Notes */}
        {exchange.broker_notes && (
          <div className="bg-amber-500/10 rounded-lg p-4 mb-6">
            <h3 className="text-sm font-medium text-amber-400 mb-2">Broker Notes</h3>
            <p className="text-theme-primary">{exchange.broker_notes}</p>
          </div>
        )}

        {/* Actions */}
        {(canAccept || canDecline || canStart || canComplete || canConfirm || canCancel) && (
          <div className="flex flex-wrap gap-3 pt-4 border-t border-theme-default">
            {canAccept && (
              <Button
                color="success"
                startContent={<Check className="w-4 h-4" aria-hidden="true" />}
                onClick={handleAccept}
                isLoading={isSubmitting}
              >
                Accept Request
              </Button>
            )}
            {canDecline && (
              <Button
                color="danger"
                variant="flat"
                startContent={<X className="w-4 h-4" aria-hidden="true" />}
                onClick={() => setShowDeclineModal(true)}
              >
                Decline
              </Button>
            )}
            {canStart && (
              <Button
                color="primary"
                startContent={<Play className="w-4 h-4" aria-hidden="true" />}
                onClick={handleStart}
                isLoading={isSubmitting}
              >
                Start Exchange
              </Button>
            )}
            {canComplete && (
              <Button
                color="success"
                startContent={<CheckCircle className="w-4 h-4" aria-hidden="true" />}
                onClick={handleComplete}
                isLoading={isSubmitting}
              >
                Mark Complete
              </Button>
            )}
            {canConfirm && (
              <Button
                color="warning"
                startContent={<CheckCircle className="w-4 h-4" aria-hidden="true" />}
                onClick={() => setShowConfirmModal(true)}
              >
                Confirm Hours
              </Button>
            )}
            {canCancel && (
              <Button
                color="danger"
                variant="flat"
                startContent={<XCircle className="w-4 h-4" aria-hidden="true" />}
                onClick={() => setShowCancelModal(true)}
              >
                Cancel Exchange
              </Button>
            )}
          </div>
        )}
      </GlassCard>

      {/* Exchange Timeline */}
      {timeline.length > 0 && (
        <GlassCard className="p-6">
          <h2 className="text-xl font-semibold text-theme-primary mb-6 flex items-center gap-3">
            <Clock className="w-5 h-5 text-indigo-400" aria-hidden="true" />
            Exchange Timeline
          </h2>

          <div className="relative">
            {/* Vertical connector line */}
            <div
              className="absolute left-[17px] top-3 bottom-3 w-0.5 bg-theme-default"
              aria-hidden="true"
            />

            <motion.div
              initial="hidden"
              animate="visible"
              variants={{
                hidden: { opacity: 0 },
                visible: {
                  opacity: 1,
                  transition: { staggerChildren: 0.1 },
                },
              }}
              className="space-y-0"
            >
              {timeline.map((entry, index) => (
                <motion.div
                  key={index}
                  variants={{
                    hidden: { opacity: 0, x: -20 },
                    visible: { opacity: 1, x: 0 },
                  }}
                  className="relative flex items-start gap-4 pb-6 last:pb-0"
                >
                  {/* Icon circle */}
                  <div
                    className={`relative z-10 w-9 h-9 rounded-full flex items-center justify-center border-2 flex-shrink-0 ${entry.colorClass}`}
                  >
                    {entry.icon}
                  </div>

                  {/* Content */}
                  <div className="flex-1 min-w-0 pt-1">
                    <div className="flex items-center gap-2 flex-wrap">
                      <span className="font-semibold text-sm text-theme-primary">
                        {entry.label}
                      </span>
                      {entry.actor && (
                        <Chip size="sm" variant="flat" className="text-xs bg-theme-elevated text-theme-muted">
                          {entry.actor}
                        </Chip>
                      )}
                    </div>
                    <p className="text-xs text-theme-subtle mt-0.5">
                      <time dateTime={entry.timestamp}>
                        {formatRelativeTime(entry.timestamp)}
                        {' \u00b7 '}
                        {new Date(entry.timestamp).toLocaleString()}
                      </time>
                    </p>
                    {entry.notes && (
                      <p className="text-sm text-theme-muted mt-1 bg-theme-elevated rounded-lg px-3 py-2">
                        {entry.notes}
                      </p>
                    )}
                  </div>
                </motion.div>
              ))}
            </motion.div>
          </div>
        </GlassCard>
      )}

      {/* Decline Modal */}
      <Modal
        isOpen={showDeclineModal}
        onClose={() => setShowDeclineModal(false)}
        classNames={{
          base: 'bg-theme-card border border-theme-default',
          header: 'border-b border-theme-default',
          body: 'py-6',
          footer: 'border-t border-theme-default',
        }}
      >
        <ModalContent>
          <ModalHeader className="text-theme-primary">Decline Exchange Request</ModalHeader>
          <ModalBody>
            <Textarea
              label="Reason (optional)"
              placeholder="Let them know why you're declining..."
              value={declineReason}
              onChange={(e) => setDeclineReason(e.target.value)}
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default',
              }}
            />
          </ModalBody>
          <ModalFooter>
            <Button
              variant="flat"
              onClick={() => setShowDeclineModal(false)}
              className="bg-theme-elevated text-theme-primary"
            >
              Cancel
            </Button>
            <Button color="danger" onClick={handleDecline} isLoading={isSubmitting}>
              Decline Request
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Confirm Hours Modal */}
      <Modal
        isOpen={showConfirmModal}
        onClose={() => setShowConfirmModal(false)}
        classNames={{
          base: 'bg-theme-card border border-theme-default',
          header: 'border-b border-theme-default',
          body: 'py-6',
          footer: 'border-t border-theme-default',
        }}
      >
        <ModalContent>
          <ModalHeader className="text-theme-primary">Confirm Hours Worked</ModalHeader>
          <ModalBody>
            <p className="text-theme-muted mb-4">
              How many hours were actually worked for this exchange?
            </p>
            <Input
              type="number"
              label="Hours"
              placeholder="Enter hours"
              value={confirmHours}
              onChange={(e) => setConfirmHours(e.target.value)}
              min="0.5"
              max={MAX_EXCHANGE_HOURS}
              step="0.5"
              endContent={<span className="text-theme-muted">hours</span>}
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default',
              }}
            />
            <p className="text-xs text-theme-muted mt-2">
              Originally proposed: {exchange?.proposed_hours} hours (max: {MAX_EXCHANGE_HOURS})
            </p>
          </ModalBody>
          <ModalFooter>
            <Button
              variant="flat"
              onClick={() => setShowConfirmModal(false)}
              className="bg-theme-elevated text-theme-primary"
            >
              Cancel
            </Button>
            <Button color="success" onClick={handleConfirm} isLoading={isSubmitting}>
              Confirm Hours
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Cancel Confirmation Modal */}
      <Modal
        isOpen={showCancelModal}
        onClose={() => setShowCancelModal(false)}
        classNames={{
          base: 'bg-theme-card border border-theme-default',
          header: 'border-b border-theme-default',
          body: 'py-6',
          footer: 'border-t border-theme-default',
        }}
      >
        <ModalContent>
          <ModalHeader className="text-theme-primary">Cancel Exchange?</ModalHeader>
          <ModalBody>
            <p className="text-theme-muted">
              Are you sure you want to cancel this exchange? This action cannot be undone.
            </p>
          </ModalBody>
          <ModalFooter>
            <Button
              variant="flat"
              onClick={() => setShowCancelModal(false)}
              className="bg-theme-elevated text-theme-primary"
            >
              Keep Exchange
            </Button>
            <Button color="danger" onClick={handleCancel} isLoading={isSubmitting}>
              Cancel Exchange
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </motion.div>
  );
}

export default ExchangeDetailPage;
