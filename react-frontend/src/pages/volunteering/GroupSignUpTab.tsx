// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * GroupSignUpTab - View and manage group/team shift reservations (V3)
 */

import { useState, useEffect, useCallback } from 'react';
import { motion } from 'framer-motion';
import {
  Button,
  Chip,
  Avatar,
  AvatarGroup,
  Input,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  useDisclosure,
} from '@heroui/react';
import {
  Users,
  UserPlus,
  Calendar,
  Clock,
  Building2,
  MapPin,
  AlertTriangle,
  RefreshCw,
  Crown,
  CheckCircle,
  Hourglass,
  XCircle,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { useToast } from '@/contexts';
import { resolveAvatarUrl } from '@/lib/helpers';

/* ───────────────────────── Types ───────────────────────── */

interface GroupMember {
  id: number;
  name: string;
  avatar_url: string | null;
  status: 'confirmed' | 'pending' | 'declined';
}

interface GroupReservation {
  id: number;
  group_name: string;
  status: 'confirmed' | 'pending' | 'cancelled';
  is_leader: boolean;
  shift: {
    id: number;
    start_time: string;
    end_time: string;
  };
  opportunity: {
    id: number;
    title: string;
    location: string;
  };
  organization: {
    id: number;
    name: string;
    logo_url: string | null;
  };
  members: GroupMember[];
  max_members: number | null;
  created_at: string;
}

/* ───────────────────────── Component ───────────────────────── */

export function GroupSignUpTab() {
  const toast = useToast();
  const [reservations, setReservations] = useState<GroupReservation[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Add member modal
  const { isOpen, onOpen, onClose } = useDisclosure();
  const [selectedReservationId, setSelectedReservationId] = useState<number | null>(null);
  const [newMemberEmail, setNewMemberEmail] = useState('');
  const [isAdding, setIsAdding] = useState(false);
  const [addError, setAddError] = useState<string | null>(null);

  const load = useCallback(async () => {
    try {
      setIsLoading(true);
      setError(null);

      const response = await api.get<GroupReservation[]>(
        '/v2/volunteering/group-reservations'
      );

      if (response.success && response.data) {
        const items = Array.isArray(response.data) ? response.data : [];
        setReservations(items);
      } else {
        setError('Failed to load group reservations.');
      }
    } catch (err) {
      logError('Failed to load group reservations', err);
      setError('Unable to load group reservations. Please try again.');
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const openAddMemberModal = (reservationId: number) => {
    setSelectedReservationId(reservationId);
    setNewMemberEmail('');
    setAddError(null);
    onOpen();
  };

  const handleAddMember = async () => {
    if (!selectedReservationId || !newMemberEmail.trim()) return;

    try {
      setIsAdding(true);
      setAddError(null);

      const response = await api.post(
        `/v2/volunteering/group-reservations/${selectedReservationId}/members`,
        { email: newMemberEmail.trim() }
      );

      if (response.success) {
        toast.success('Member added to the group.');
        onClose();
        setNewMemberEmail('');
        load();
      } else {
        setAddError('Failed to add member. They may already be in the group or the email is invalid.');
      }
    } catch (err) {
      logError('Failed to add member', err);
      setAddError('Unable to add member. Please check the email and try again.');
    } finally {
      setIsAdding(false);
    }
  };

  const statusColor = (status: string) => {
    switch (status) {
      case 'confirmed': return 'success';
      case 'cancelled': return 'danger';
      default: return 'warning';
    }
  };

  const memberStatusIcon = (status: string) => {
    switch (status) {
      case 'confirmed': return <CheckCircle className="w-3 h-3 text-emerald-500" />;
      case 'declined': return <XCircle className="w-3 h-3 text-red-500" />;
      default: return <Hourglass className="w-3 h-3 text-amber-500" />;
    }
  };

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: { opacity: 1, transition: { staggerChildren: 0.05 } },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 20 },
    visible: { opacity: 1, y: 0 },
  };

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <Users className="w-5 h-5 text-violet-400" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary">Group Sign-ups</h2>
        </div>
        <Button
          size="sm"
          variant="flat"
          className="bg-theme-elevated text-theme-muted"
          startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
          onPress={load}
          isDisabled={isLoading}
        >
          Refresh
        </Button>
      </div>

      {/* Error */}
      {error && !isLoading && (
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <p className="text-theme-muted mb-4">{error}</p>
          <Button className="bg-gradient-to-r from-rose-500 to-pink-600 text-white" onPress={load}>
            Try Again
          </Button>
        </GlassCard>
      )}

      {/* Loading */}
      {!error && isLoading && (
        <div className="space-y-4">
          {[1, 2, 3].map((i) => (
            <GlassCard key={i} className="p-5 animate-pulse">
              <div className="h-5 bg-theme-hover rounded w-1/3 mb-3" />
              <div className="h-3 bg-theme-hover rounded w-2/3 mb-3" />
              <div className="h-3 bg-theme-hover rounded w-1/4" />
            </GlassCard>
          ))}
        </div>
      )}

      {/* Empty */}
      {!error && !isLoading && reservations.length === 0 && (
        <EmptyState
          icon={<Users className="w-12 h-12" aria-hidden="true" />}
          title="No group sign-ups"
          description="You are not part of any group shift reservations yet. Group sign-ups let you volunteer together with friends or team members."
        />
      )}

      {/* Reservations List */}
      {!error && !isLoading && reservations.length > 0 && (
        <motion.div
          variants={containerVariants}
          initial="hidden"
          animate="visible"
          className="space-y-4"
        >
          {reservations.map((res) => (
            <motion.div key={res.id} variants={itemVariants}>
              <GlassCard className="p-5">
                <div className="flex items-start justify-between gap-4">
                  <div className="flex-1 min-w-0">
                    {/* Header */}
                    <div className="flex items-center gap-2 mb-2 flex-wrap">
                      <h3 className="font-semibold text-theme-primary text-lg">
                        {res.group_name}
                      </h3>
                      <Chip
                        size="sm"
                        variant="flat"
                        color={statusColor(res.status)}
                      >
                        {res.status.charAt(0).toUpperCase() + res.status.slice(1)}
                      </Chip>
                      {res.is_leader && (
                        <Chip
                          size="sm"
                          variant="flat"
                          color="warning"
                          startContent={<Crown className="w-3 h-3" />}
                        >
                          Leader
                        </Chip>
                      )}
                    </div>

                    {/* Shift details */}
                    <p className="text-sm font-medium text-theme-primary mb-1">
                      {res.opportunity.title}
                    </p>

                    <div className="flex flex-wrap items-center gap-3 text-xs text-theme-subtle mb-3">
                      <span className="flex items-center gap-1">
                        <Building2 className="w-3 h-3" aria-hidden="true" />
                        {res.organization.name}
                      </span>
                      {res.opportunity.location && (
                        <span className="flex items-center gap-1">
                          <MapPin className="w-3 h-3" aria-hidden="true" />
                          {res.opportunity.location}
                        </span>
                      )}
                      <span className="flex items-center gap-1">
                        <Calendar className="w-3 h-3" aria-hidden="true" />
                        {new Date(res.shift.start_time).toLocaleDateString()}
                      </span>
                      <span className="flex items-center gap-1">
                        <Clock className="w-3 h-3" aria-hidden="true" />
                        {new Date(res.shift.start_time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                        {' - '}
                        {new Date(res.shift.end_time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                      </span>
                    </div>

                    {/* Members */}
                    <div className="space-y-2">
                      <div className="flex items-center justify-between">
                        <p className="text-xs font-medium text-theme-muted flex items-center gap-1">
                          <Users className="w-3 h-3" aria-hidden="true" />
                          Members ({res.members.length}{res.max_members ? `/${res.max_members}` : ''})
                        </p>
                      </div>

                      {/* Avatar group */}
                      {res.members.length > 0 && (
                        <AvatarGroup max={5} size="sm">
                          {res.members.map((member) => (
                            <Avatar
                              key={member.id}
                              name={member.name}
                              src={resolveAvatarUrl(member.avatar_url) || undefined}
                              size="sm"
                            />
                          ))}
                        </AvatarGroup>
                      )}

                      {/* Member list */}
                      <div className="flex flex-wrap gap-2">
                        {res.members.map((member) => (
                          <div
                            key={member.id}
                            className="flex items-center gap-1 text-xs text-theme-subtle"
                          >
                            {memberStatusIcon(member.status)}
                            <span>{member.name}</span>
                          </div>
                        ))}
                      </div>
                    </div>

                    <p className="text-xs text-theme-subtle mt-2">
                      Created {new Date(res.created_at).toLocaleDateString()}
                    </p>
                  </div>

                  {/* Add member button for leaders */}
                  {res.is_leader && res.status !== 'cancelled' && (
                    <div className="flex-shrink-0">
                      <Button
                        size="sm"
                        className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
                        startContent={<UserPlus className="w-4 h-4" aria-hidden="true" />}
                        onPress={() => openAddMemberModal(res.id)}
                        isDisabled={res.max_members !== null && res.members.length >= res.max_members}
                      >
                        Add Member
                      </Button>
                    </div>
                  )}
                </div>
              </GlassCard>
            </motion.div>
          ))}
        </motion.div>
      )}

      {/* Add Member Modal */}
      <Modal isOpen={isOpen} onClose={onClose} size="md" classNames={{
        base: 'bg-content1 border border-theme-default',
      }}>
        <ModalContent>
          <ModalHeader className="text-theme-primary">Add Group Member</ModalHeader>
          <ModalBody className="space-y-4">
            <p className="text-sm text-theme-muted">
              Enter the email address of the person you would like to add to this group reservation.
              They will receive an invitation to join.
            </p>
            <Input
              type="email"
              label="Email Address"
              placeholder="member@example.com"
              value={newMemberEmail}
              onChange={(e) => setNewMemberEmail(e.target.value)}
              isRequired
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default',
              }}
            />
            {addError && (
              <p className="text-sm text-danger">{addError}</p>
            )}
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onClose} className="text-theme-muted">Cancel</Button>
            <Button
              className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
              onPress={handleAddMember}
              isLoading={isAdding}
              isDisabled={!newMemberEmail.trim()}
              startContent={<UserPlus className="w-4 h-4" aria-hidden="true" />}
            >
              Add Member
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default GroupSignUpTab;
