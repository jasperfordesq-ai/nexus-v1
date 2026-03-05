// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Opportunity Detail Page — view a single volunteering opportunity,
 * its shifts, and apply.
 *
 * API: GET /api/v2/volunteering/opportunities/{id}
 *      POST /api/v2/volunteering/opportunities/{id}/apply
 */

import { useState, useEffect, useCallback } from 'react';
import { useParams, Link } from 'react-router-dom';
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
  Textarea,
  useDisclosure,
} from '@heroui/react';
import {
  MapPin,
  Calendar,
  Clock,
  Briefcase,
  Users,
  Building2,
  Wifi,
  Tag,
  ChevronRight,
  AlertTriangle,
  RefreshCw,
  Send,
  CheckCircle,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { LoadingScreen } from '@/components/feedback';
import { Breadcrumbs } from '@/components/navigation';
import { useAuth, useTenant } from '@/contexts';
import { useToast } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

/* ───────────────────────── Types ───────────────────────── */

interface Shift {
  id: number;
  start_time: string;
  end_time: string;
  capacity: number | null;
  signup_count: number;
  spots_available: number | null;
}

interface Application {
  id: number;
  status: string;
  message: string | null;
  created_at: string;
}

interface OpportunityDetail {
  id: number;
  title: string;
  description: string;
  location: string;
  skills_needed: string;
  start_date: string | null;
  end_date: string | null;
  is_active: boolean;
  is_remote: boolean;
  category: string | null;
  organization: { id: number; name: string; logo_url: string | null };
  created_at: string;
  shifts: Shift[];
  has_applied?: boolean;
  application?: Application | null;
}

/* ───────────────────────── Component ───────────────────────── */

export function OpportunityDetailPage() {
  const { id } = useParams<{ id: string }>();
  const { isAuthenticated } = useAuth();
  const { tenantPath } = useTenant();
  const toast = useToast();

  usePageTitle('Opportunity Details');

  const [opportunity, setOpportunity] = useState<OpportunityDetail | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Apply modal
  const applyModal = useDisclosure();
  const [applyMessage, setApplyMessage] = useState('');
  const [isApplying, setIsApplying] = useState(false);
  const [selectedShiftId, setSelectedShiftId] = useState<number | null>(null);

  const load = useCallback(async () => {
    if (!id) return;
    try {
      setIsLoading(true);
      setError(null);
      const response = await api.get<OpportunityDetail>(`/v2/volunteering/opportunities/${id}`);
      if (response.success && response.data) {
        setOpportunity(response.data);
      } else {
        setError('Opportunity not found.');
      }
    } catch (err) {
      logError('Failed to load opportunity', err);
      setError('Unable to load this opportunity. Please try again.');
    } finally {
      setIsLoading(false);
    }
  }, [id]);

  useEffect(() => {
    load();
  }, [load]);

  async function handleApply() {
    if (!id) return;
    try {
      setIsApplying(true);
      const body: Record<string, unknown> = { message: applyMessage };
      if (selectedShiftId) body.shift_id = selectedShiftId;

      const response = await api.post(`/v2/volunteering/opportunities/${id}/apply`, body);
      if (response.success) {
        toast.success('Application submitted successfully!');
        applyModal.onClose();
        setApplyMessage('');
        setSelectedShiftId(null);
        load(); // Refresh to show applied state
      } else {
        toast.error(response.error || 'Failed to apply.');
      }
    } catch (err) {
      logError('Failed to apply', err);
      toast.error('Something went wrong. Please try again.');
    } finally {
      setIsApplying(false);
    }
  }

  const formatDate = (d: string) => new Date(d).toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });

  if (isLoading) return <LoadingScreen />;

  if (error || !opportunity) {
    return (
      <div className="max-w-3xl mx-auto px-4 py-8">
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <p className="text-theme-muted mb-4">{error || 'Opportunity not found.'}</p>
          <Button
            className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
            startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
            onPress={load}
          >
            Try Again
          </Button>
        </GlassCard>
      </div>
    );
  }

  const opp = opportunity;
  const upcomingShifts = (opp.shifts || []).filter((s) => new Date(s.start_time) >= new Date());

  return (
    <div className="max-w-4xl mx-auto px-4 py-6 space-y-6">
      <Breadcrumbs
        items={[
          { label: 'Volunteering', href: tenantPath('/volunteering') },
          { label: opp.title },
        ]}
      />

      <motion.div initial={{ opacity: 0, y: 16 }} animate={{ opacity: 1, y: 0 }}>
        {/* Header Card */}
        <GlassCard className="p-6 space-y-5">
          <div className="flex items-start gap-4">
            <Avatar
              src={opp.organization.logo_url || undefined}
              name={opp.organization.name}
              size="lg"
              className="flex-shrink-0"
            />
            <div className="flex-1 min-w-0">
              <h1 className="text-2xl font-bold text-theme-primary">{opp.title}</h1>
              <Link
                to={tenantPath(`/organisations/${opp.organization.id}`)}
                className="text-indigo-500 hover:underline text-sm flex items-center gap-1 mt-1"
              >
                <Building2 className="w-3.5 h-3.5" aria-hidden="true" />
                {opp.organization.name}
                <ChevronRight className="w-3 h-3" aria-hidden="true" />
              </Link>
            </div>
          </div>

          {/* Status Chips */}
          <div className="flex flex-wrap gap-2">
            <Chip
              size="sm"
              variant="flat"
              color={opp.is_active ? 'success' : 'danger'}
            >
              {opp.is_active ? 'Active' : 'Closed'}
            </Chip>
            {opp.is_remote && (
              <Chip size="sm" variant="flat" color="secondary" startContent={<Wifi className="w-3 h-3" />}>
                Remote
              </Chip>
            )}
            {opp.category && (
              <Chip size="sm" variant="flat" color="primary" startContent={<Tag className="w-3 h-3" />}>
                {opp.category}
              </Chip>
            )}
            {opp.has_applied && (
              <Chip size="sm" variant="flat" color="success" startContent={<CheckCircle className="w-3 h-3" />}>
                Applied
              </Chip>
            )}
          </div>

          {/* Details Grid */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            {opp.location && (
              <div className="flex items-center gap-2 text-sm text-theme-muted">
                <MapPin className="w-4 h-4 flex-shrink-0" aria-hidden="true" />
                {opp.location}
              </div>
            )}
            {opp.start_date && (
              <div className="flex items-center gap-2 text-sm text-theme-muted">
                <Calendar className="w-4 h-4 flex-shrink-0" aria-hidden="true" />
                {formatDate(opp.start_date)}
                {opp.end_date && ` — ${formatDate(opp.end_date)}`}
              </div>
            )}
            {opp.skills_needed && (
              <div className="flex items-center gap-2 text-sm text-theme-muted sm:col-span-2">
                <Briefcase className="w-4 h-4 flex-shrink-0" aria-hidden="true" />
                {opp.skills_needed}
              </div>
            )}
          </div>

          {/* Description */}
          {opp.description && (
            <div className="prose prose-sm dark:prose-invert max-w-none">
              <p className="text-theme-secondary whitespace-pre-wrap">{opp.description}</p>
            </div>
          )}

          {/* Apply button */}
          {isAuthenticated && opp.is_active && !opp.has_applied && (
            <Button
              className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
              startContent={<Send className="w-4 h-4" aria-hidden="true" />}
              onPress={applyModal.onOpen}
            >
              Apply Now
            </Button>
          )}

          {opp.has_applied && opp.application && (
            <div className="flex items-center gap-2 p-3 rounded-lg bg-emerald-500/10 border border-emerald-500/30">
              <CheckCircle className="w-5 h-5 text-emerald-400" aria-hidden="true" />
              <div>
                <p className="text-sm font-medium text-emerald-400">
                  You have applied
                </p>
                <p className="text-xs text-theme-subtle">
                  Status: {opp.application.status} &middot; Applied {formatDate(opp.application.created_at)}
                </p>
              </div>
            </div>
          )}
        </GlassCard>
      </motion.div>

      {/* Shifts */}
      {upcomingShifts.length > 0 && (
        <motion.div initial={{ opacity: 0, y: 16 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.1 }}>
          <GlassCard className="p-6 space-y-4">
            <h2 className="text-lg font-semibold text-theme-primary flex items-center gap-2">
              <Clock className="w-5 h-5 text-indigo-400" aria-hidden="true" />
              Upcoming Shifts
            </h2>
            <div className="space-y-2">
              {upcomingShifts.map((shift) => (
                <div
                  key={shift.id}
                  className="flex items-center justify-between p-3 rounded-xl bg-theme-elevated border border-theme-default"
                >
                  <div className="flex items-center gap-3">
                    <Calendar className="w-4 h-4 text-theme-subtle" aria-hidden="true" />
                    <div>
                      <p className="text-sm font-medium text-theme-primary">
                        {new Date(shift.start_time).toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' })}
                      </p>
                      <p className="text-xs text-theme-subtle">
                        {new Date(shift.start_time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })} — {new Date(shift.end_time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                      </p>
                    </div>
                  </div>
                  <div className="flex items-center gap-2">
                    <Users className="w-4 h-4 text-theme-subtle" aria-hidden="true" />
                    <span className="text-xs text-theme-muted">
                      {shift.signup_count}{shift.capacity ? `/${shift.capacity}` : ''}
                    </span>
                    {(shift.spots_available === null || shift.spots_available > 0) ? (
                      <Chip size="sm" variant="flat" color="success">Open</Chip>
                    ) : (
                      <Chip size="sm" variant="flat" color="danger">Full</Chip>
                    )}
                  </div>
                </div>
              ))}
            </div>
          </GlassCard>
        </motion.div>
      )}

      {/* Apply Modal */}
      <Modal isOpen={applyModal.isOpen} onOpenChange={applyModal.onOpenChange}>
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader>Apply to {opp.title}</ModalHeader>
              <ModalBody>
                <Textarea
                  label="Message (optional)"
                  placeholder="Tell the organiser why you'd like to volunteer..."
                  value={applyMessage}
                  onValueChange={setApplyMessage}
                  minRows={3}
                />
                {upcomingShifts.length > 0 && (
                  <div className="space-y-2">
                    <p className="text-sm font-medium text-theme-muted">Select a shift (optional)</p>
                    {upcomingShifts.filter((s) => s.spots_available === null || s.spots_available > 0).map((shift) => (
                      <Button
                        key={shift.id}
                        size="sm"
                        variant={selectedShiftId === shift.id ? 'solid' : 'flat'}
                        className={selectedShiftId === shift.id
                          ? 'bg-gradient-to-r from-rose-500 to-pink-600 text-white w-full justify-start'
                          : 'bg-theme-elevated text-theme-muted w-full justify-start'
                        }
                        onPress={() => setSelectedShiftId(
                          selectedShiftId === shift.id ? null : shift.id
                        )}
                      >
                        {new Date(shift.start_time).toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' })} &middot; {new Date(shift.start_time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })} — {new Date(shift.end_time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                      </Button>
                    ))}
                  </div>
                )}
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose}>Cancel</Button>
                <Button
                  className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
                  onPress={handleApply}
                  isLoading={isApplying}
                >
                  Submit Application
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}

export default OpportunityDetailPage;
