// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * GroupSignUpTab - View and manage group/team shift reservations (V3)
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { useTranslation } from 'react-i18next';
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
import Users from 'lucide-react/icons/users';
import UserPlus from 'lucide-react/icons/user-plus';
import Calendar from 'lucide-react/icons/calendar';
import Clock from 'lucide-react/icons/clock';
import Building2 from 'lucide-react/icons/building-2';
import MapPin from 'lucide-react/icons/map-pin';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import Crown from 'lucide-react/icons/crown';
import CheckCircle from 'lucide-react/icons/circle-check-big';
import Hourglass from 'lucide-react/icons/hourglass';
import XCircle from 'lucide-react/icons/circle-x';
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
  const { t } = useTranslation('volunteering');
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

  // Debounced member search state
  const [searchResults, setSearchResults] = useState<{ id: number; name: string; email: string }[]>([]);
  const [isSearching, setIsSearching] = useState(false);
  const searchDebounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const searchAbortRef = useRef<AbortController | null>(null);

  // AbortController ref to cancel stale requests
  const abortRef = useRef<AbortController | null>(null);

  // Stable refs for t/toast — avoids re-creating callbacks when i18n namespace loads
  const tRef = useRef(t);
  tRef.current = t;
  const toastRef = useRef(toast);
  toastRef.current = toast;

  const load = useCallback(async () => {
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    try {
      setIsLoading(true);
      setError(null);

      const response = await api.get<GroupReservation[]>(
        '/v2/volunteering/group-reservations'
      );

      if (controller.signal.aborted) return;
      if (response.success && response.data) {
        const items = Array.isArray(response.data) ? response.data : [];
        setReservations(items);
      } else {
        setError(tRef.current('group_signup.error_load', 'Failed to load group reservations.'));
      }
    } catch (err) {
      if (controller.signal.aborted) return;
      logError('Failed to load group reservations', err);
      setError(tRef.current('group_signup.error_load_generic', 'Unable to load group reservations. Please try again.'));
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const searchMembers = useCallback((query: string) => {
    // Clear any pending debounce
    if (searchDebounceRef.current) clearTimeout(searchDebounceRef.current);

    if (!query.trim() || query.trim().length < 2) {
      setSearchResults([]);
      setIsSearching(false);
      return;
    }

    setIsSearching(true);
    searchDebounceRef.current = setTimeout(async () => {
      // Abort any in-flight search request
      searchAbortRef.current?.abort();
      const controller = new AbortController();
      searchAbortRef.current = controller;

      try {
        const searchRes = await api.get<{ id: number; name: string; email: string }[]>(
          `/v2/users?search=${encodeURIComponent(query.trim())}&per_page=5`
        );
        if (controller.signal.aborted) return;
        if (searchRes.success && Array.isArray(searchRes.data)) {
          setSearchResults(searchRes.data);
        } else {
          setSearchResults([]);
        }
      } catch (err) {
        if (controller.signal.aborted) return;
        logError('Member search failed', err);
        setSearchResults([]);
      } finally {
        if (!controller.signal.aborted) {
          setIsSearching(false);
        }
      }
    }, 300);
  }, []);

  const openAddMemberModal = (reservationId: number) => {
    setSelectedReservationId(reservationId);
    setNewMemberEmail('');
    setAddError(null);
    setSearchResults([]);
    setIsSearching(false);
    onOpen();
  };

  // Clean up debounce timer and abort controller on unmount
  useEffect(() => {
    return () => {
      if (searchDebounceRef.current) clearTimeout(searchDebounceRef.current);
      searchAbortRef.current?.abort();
    };
  }, []);

  const handleAddMember = async () => {
    if (!selectedReservationId || !newMemberEmail.trim()) return;

    try {
      setIsAdding(true);
      setAddError(null);

      // Use cached search results if available, otherwise do a fresh lookup
      let matchedUser: { id: number; email: string } | undefined;
      const emailLower = newMemberEmail.trim().toLowerCase();

      if (searchResults.length > 0) {
        matchedUser = searchResults.find((u) => u.email?.toLowerCase() === emailLower);
      }

      // Fall back to a direct search if no cached match
      if (!matchedUser) {
        const searchRes = await api.get<{ id: number; email: string }[]>(
          `/v2/users?search=${encodeURIComponent(newMemberEmail.trim())}&per_page=5`
        );

        if (!searchRes.success || !Array.isArray(searchRes.data)) {
          setAddError(tRef.current('group_signup.lookup_error', 'Unable to look up member. Please try again.'));
          return;
        }

        matchedUser = searchRes.data.find((u) => u.email?.toLowerCase() === emailLower);
      }

      if (!matchedUser) {
        setAddError(tRef.current('group_signup.no_member_found', 'No member found with that email address.'));
        return;
      }

      const response = await api.post(
        `/v2/volunteering/group-reservations/${selectedReservationId}/members`,
        { user_id: matchedUser.id }
      );

      if (response.success) {
        toastRef.current.success(tRef.current('group_signup.member_added', 'Member added to the group.'));
        onClose();
        setNewMemberEmail('');
        setSearchResults([]);
        load();
      } else {
        setAddError(tRef.current('group_signup.add_member_error', 'Failed to add member. They may already be in the group or the email is invalid.'));
      }
    } catch (err) {
      logError('Failed to add member', err);
      setAddError(tRef.current('group_signup.add_member_error_generic', 'Unable to add member. Please check the email and try again.'));
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
          <h2 className="text-lg font-semibold text-theme-primary">{t('group_signup.title', 'Group Sign-ups')}</h2>
        </div>
        <Button
          size="sm"
          variant="flat"
          className="bg-theme-elevated text-theme-muted"
          startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
          onPress={load}
          isDisabled={isLoading}
        >
          {t('common.refresh', 'Refresh')}
        </Button>
      </div>

      {/* Error */}
      {error && !isLoading && (
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <p className="text-theme-muted mb-4">{error}</p>
          <Button className="bg-gradient-to-r from-rose-500 to-pink-600 text-white" onPress={load}>
            {t('common.try_again', 'Try Again')}
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
          title={t('group_signup.empty_title', 'No group sign-ups')}
          description={t('group_signup.empty_description', 'You are not part of any group shift reservations yet. Group sign-ups let you volunteer together with friends or team members.')}
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
                          {t('group_signup.leader', 'Leader')}
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
                          {t('group_signup.members', 'Members')} ({res.members.length}{res.max_members ? `/${res.max_members}` : ''})
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
                      {t('group_signup.created', 'Created')} {new Date(res.created_at).toLocaleDateString()}
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
                        {t('group_signup.add_member', 'Add Member')}
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
          <ModalHeader className="text-theme-primary">{t('group_signup.add_group_member', 'Add Group Member')}</ModalHeader>
          <ModalBody className="space-y-4">
            <p className="text-sm text-theme-muted">
              {t('group_signup.add_member_description', 'Enter the email address of the person you would like to add to this group reservation. They will receive an invitation to join.')}
            </p>
            <Input
              type="email"
              label={t('group_signup.email_label', 'Email Address')}
              placeholder={t('group_signup.email_placeholder', 'member@example.com')}
              value={newMemberEmail}
              onChange={(e) => {
                const val = e.target.value;
                setNewMemberEmail(val);
                searchMembers(val);
              }}
              isRequired
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default',
              }}
            />
            {isSearching && (
              <p className="text-xs text-theme-subtle">{t('group_signup.searching', 'Searching...')}</p>
            )}
            {!isSearching && searchResults.length > 0 && (
              <div className="space-y-1">
                {searchResults.map((user) => (
                  <Button
                    key={user.id}
                    variant="light"
                    className="w-full text-left px-3 py-2 rounded-lg bg-theme-elevated hover:bg-theme-hover text-sm text-theme-primary transition-colors h-auto min-w-0 justify-start"
                    onPress={() => {
                      setNewMemberEmail(user.email);
                      setSearchResults([]);
                    }}
                  >
                    <span className="font-medium">{user.name}</span>
                    <span className="text-theme-subtle ml-2">{user.email}</span>
                  </Button>
                ))}
              </div>
            )}
            {addError && (
              <p className="text-sm text-danger">{addError}</p>
            )}
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onClose} className="text-theme-muted">{t('common.cancel', 'Cancel')}</Button>
            <Button
              className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
              onPress={handleAddMember}
              isLoading={isAdding}
              isDisabled={!newMemberEmail.trim()}
              startContent={<UserPlus className="w-4 h-4" aria-hidden="true" />}
            >
              {t('group_signup.add_member', 'Add Member')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default GroupSignUpTab;
