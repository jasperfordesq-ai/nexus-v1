// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * WaitlistTab - View and manage shift waitlist positions (V1)
 */

import { useState, useEffect, useCallback } from 'react';
import { motion } from 'framer-motion';
import {
  Button,
  Chip,
} from '@heroui/react';
import {
  Clock,
  Users,
  X,
  MapPin,
  Calendar,
  Building2,
  AlertTriangle,
  RefreshCw,
  Hash,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

/* ───────────────────────── Types ───────────────────────── */

interface WaitlistEntry {
  id: number;
  position: number;
  shift: {
    id: number;
    start_time: string;
    end_time: string;
    capacity: number | null;
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
  joined_at: string;
}

/* ───────────────────────── Component ───────────────────────── */

export function WaitlistTab() {
  const [entries, setEntries] = useState<WaitlistEntry[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [removingId, setRemovingId] = useState<number | null>(null);

  const load = useCallback(async () => {
    try {
      setIsLoading(true);
      setError(null);

      const response = await api.get<{ data: WaitlistEntry[] }>(
        '/v2/volunteering/my-waitlists'
      );

      if (response.success && response.data) {
        const items = Array.isArray(response.data) ? response.data : [];
        setEntries(items);
      } else {
        setError('Failed to load waitlist entries.');
      }
    } catch (err) {
      logError('Failed to load waitlists', err);
      setError('Unable to load your waitlist entries. Please try again.');
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const handleLeaveWaitlist = async (shiftId: number) => {
    try {
      setRemovingId(shiftId);
      const response = await api.delete(`/v2/volunteering/shifts/${shiftId}/waitlist`);
      if (response.success) {
        setEntries((prev) => prev.filter((e) => e.shift.id !== shiftId));
      }
    } catch (err) {
      logError('Failed to leave waitlist', err);
    } finally {
      setRemovingId(null);
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
          <Clock className="w-5 h-5 text-amber-400" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary">My Waitlists</h2>
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
      {!error && !isLoading && entries.length === 0 && (
        <EmptyState
          icon={<Clock className="w-12 h-12" aria-hidden="true" />}
          title="No waitlist entries"
          description="You are not currently on any shift waitlists. When a shift is full, you can join the waitlist and be notified when a spot opens up."
        />
      )}

      {/* Waitlist Entries */}
      {!error && !isLoading && entries.length > 0 && (
        <motion.div
          variants={containerVariants}
          initial="hidden"
          animate="visible"
          className="space-y-4"
        >
          {entries.map((entry) => (
            <motion.div key={entry.id} variants={itemVariants}>
              <GlassCard className="p-5">
                <div className="flex items-start justify-between gap-4">
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 mb-2 flex-wrap">
                      <h3 className="font-semibold text-theme-primary text-lg">
                        {entry.opportunity.title}
                      </h3>
                      <Chip
                        size="sm"
                        color="warning"
                        variant="flat"
                        startContent={<Hash className="w-3 h-3" />}
                      >
                        Position {entry.position}
                      </Chip>
                    </div>

                    <div className="flex flex-wrap items-center gap-3 text-xs text-theme-subtle mb-2">
                      <span className="flex items-center gap-1">
                        <Building2 className="w-3 h-3" aria-hidden="true" />
                        {entry.organization.name}
                      </span>
                      {entry.opportunity.location && (
                        <span className="flex items-center gap-1">
                          <MapPin className="w-3 h-3" aria-hidden="true" />
                          {entry.opportunity.location}
                        </span>
                      )}
                      <span className="flex items-center gap-1">
                        <Calendar className="w-3 h-3" aria-hidden="true" />
                        {new Date(entry.shift.start_time).toLocaleDateString()}
                      </span>
                      <span className="flex items-center gap-1">
                        <Clock className="w-3 h-3" aria-hidden="true" />
                        {new Date(entry.shift.start_time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                        {' - '}
                        {new Date(entry.shift.end_time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                      </span>
                      {entry.shift.capacity && (
                        <span className="flex items-center gap-1">
                          <Users className="w-3 h-3" aria-hidden="true" />
                          {entry.shift.capacity} spots
                        </span>
                      )}
                    </div>

                    <p className="text-xs text-theme-subtle">
                      Joined waitlist {new Date(entry.joined_at).toLocaleDateString()}
                    </p>
                  </div>

                  <Button
                    size="sm"
                    variant="flat"
                    color="danger"
                    startContent={<X className="w-4 h-4" aria-hidden="true" />}
                    onPress={() => handleLeaveWaitlist(entry.shift.id)}
                    isLoading={removingId === entry.shift.id}
                  >
                    Leave
                  </Button>
                </div>
              </GlassCard>
            </motion.div>
          ))}
        </motion.div>
      )}
    </div>
  );
}

export default WaitlistTab;
