// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * AvailabilityGrid - Weekly availability calendar grid
 *
 * Displays a Mon-Sun grid with time slots. Used on profile pages
 * (read-only) and in settings (editable).
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { Button, Spinner, Tooltip } from '@heroui/react';
import { Calendar, Save, RefreshCw, AlertTriangle } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

export interface AvailabilitySlot {
  id?: number;
  day_of_week: number; // Backend convention: 0 = Sunday, 6 = Saturday
  start_time: string;  // "09:00"
  end_time: string;    // "17:00"
}

interface AvailabilityData {
  weekly: AvailabilitySlot[];
  timezone?: string;
}

// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────

const DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const FULL_DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
const TIME_SLOTS = [
  '06:00', '07:00', '08:00', '09:00', '10:00', '11:00',
  '12:00', '13:00', '14:00', '15:00', '16:00', '17:00',
  '18:00', '19:00', '20:00', '21:00',
];

// ─────────────────────────────────────────────────────────────────────────────
// AvailabilityGrid Component
// ─────────────────────────────────────────────────────────────────────────────

export function AvailabilityGrid({
  userId,
  editable = false,
  compact = false,
}: {
  userId?: string | number;
  editable?: boolean;
  compact?: boolean;
}) {
  const toast = useToast();
  const { t } = useTranslation('settings');
  const { t: tAvail } = useTranslation('availability');
  const [slots, setSlots] = useState<Map<string, boolean>>(new Map());
  const [isLoading, setIsLoading] = useState(true);
  const [isSaving, setIsSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [isDirty, setIsDirty] = useState(false);

  // AbortController ref to cancel stale requests
  const abortRef = useRef<AbortController | null>(null);

  // Stable refs for t/toast — avoids re-creating callbacks when i18n namespace loads
  const tRef = useRef(t);
  tRef.current = t;
  const toastRef = useRef(toast);
  toastRef.current = toast;

  // Build key for slot map (day = grid index: 0=Mon, 6=Sun)
  const slotKey = (day: number, time: string) => `${day}-${time}`;

  // Backend uses 0=Sunday, 6=Saturday; grid uses 0=Monday, 6=Sunday
  const backendDayToGrid = (d: number) => (d + 6) % 7;
  const gridDayToBackend = (d: number) => (d + 1) % 7;

  // Load availability
  const loadAvailability = useCallback(async () => {
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    try {
      setIsLoading(true);
      setError(null);

      const endpoint = userId
        ? `/v2/users/${userId}/availability`
        : '/v2/users/me/availability';

      const response = await api.get<AvailabilityData>(endpoint);

      if (controller.signal.aborted) return;

      if (response.success && response.data) {
        const newSlots = new Map<string, boolean>();
        const weekly = response.data.weekly || [];

        weekly.forEach((slot) => {
          // Convert backend day (0=Sun) to grid index (0=Mon)
          const gridDay = backendDayToGrid(slot.day_of_week);
          // MySQL TIME columns return "HH:MM:SS"; normalize to "HH:MM"
          const startTime = slot.start_time.substring(0, 5);
          const endTime = slot.end_time.substring(0, 5);
          const startIdx = TIME_SLOTS.indexOf(startTime);
          // end_time may be past the last grid slot (e.g., "22:00")
          let endIdx = TIME_SLOTS.indexOf(endTime);
          if (endIdx < 0 && startIdx >= 0) endIdx = TIME_SLOTS.length;
          if (startIdx >= 0) {
            for (let i = startIdx; i < endIdx; i++) {
              const ts = TIME_SLOTS[i];
              // Rows exist only for available slots; is_available isn't a DB column
              if (ts) newSlots.set(slotKey(gridDay, ts), true);
            }
          }
        });

        setSlots(newSlots);
      }
    } catch (err) {
      if (controller.signal.aborted) return;
      logError('Failed to load availability', err);
      setError('Failed to load availability');
    } finally {
      if (!controller.signal.aborted) {
        setIsLoading(false);
      }
    }
  }, [userId]);

  useEffect(() => {
    loadAvailability();
  }, [loadAvailability]);

  // Toggle a slot
  const toggleSlot = (day: number, time: string) => {
    if (!editable) return;
    const key = slotKey(day, time);
    setSlots((prev) => {
      const next = new Map(prev);
      next.set(key, !next.get(key));
      return next;
    });
    setIsDirty(true);
  };

  // Save availability
  const handleSave = async () => {
    try {
      setIsSaving(true);

      // Convert slot map to availability slots
      const availabilitySlots: AvailabilitySlot[] = [];

      for (let day = 0; day < 7; day++) {
        let rangeStart: string | null = null;

        for (let i = 0; i < TIME_SLOTS.length; i++) {
          const ts = TIME_SLOTS[i];
          if (!ts) continue;
          const isAvail = slots.get(slotKey(day, ts)) || false;

          if (isAvail && !rangeStart) {
            rangeStart = ts;
          } else if (!isAvail && rangeStart) {
            availabilitySlots.push({
              day_of_week: gridDayToBackend(day),
              start_time: rangeStart,
              end_time: ts,
            });
            rangeStart = null;
          }
        }

        // Close any open range at end of day
        if (rangeStart) {
          availabilitySlots.push({
            day_of_week: gridDayToBackend(day),
            start_time: rangeStart,
            end_time: '22:00',
          });
        }
      }

      const response = await api.put('/v2/users/me/availability', {
        slots: availabilitySlots,
      });

      if (response.success) {
        toastRef.current.success(tRef.current('toasts.availability_saved'));
        setIsDirty(false);
      } else {
        toastRef.current.error(response.error || tRef.current('toasts.availability_save_failed'));
      }
    } catch (err) {
      logError('Failed to save availability', err);
      toastRef.current.error(tRef.current('toasts.availability_save_failed'));
    } finally {
      setIsSaving(false);
    }
  };

  if (isLoading) {
    return (
      <div className="flex justify-center py-8">
        <Spinner size="lg" />
      </div>
    );
  }

  if (error) {
    return (
      <GlassCard className="p-6 text-center">
        <AlertTriangle className="w-8 h-8 text-amber-500 mx-auto mb-3" aria-hidden="true" />
        <p className="text-sm text-theme-muted mb-3">{error}</p>
        <Button
          size="sm"
          variant="flat"
          className="bg-theme-elevated text-theme-muted"
          startContent={<RefreshCw className="w-3 h-3" aria-hidden="true" />}
          onPress={loadAvailability}
        >
          Retry
        </Button>
      </GlassCard>
    );
  }

  // Check if any availability is set
  const hasAvailability = slots.size > 0 && Array.from(slots.values()).some(Boolean);

  if (!editable && !hasAvailability) {
    return null; // Don't show empty grid on profiles
  }

  const displaySlots = compact
    ? TIME_SLOTS.filter((_, i) => i % 2 === 0) // Show every other slot in compact mode
    : TIME_SLOTS;

  return (
    <div className="space-y-4">
      {/* Header */}
      {editable && (
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            <Calendar className="w-5 h-5 text-indigo-500" aria-hidden="true" />
            <h3 className="font-semibold text-theme-primary">{tAvail('set_your_availability')}</h3>
          </div>
          {isDirty && (
            <Button
              size="sm"
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              startContent={<Save className="w-3.5 h-3.5" aria-hidden="true" />}
              onPress={handleSave}
              isLoading={isSaving}
            >
              Save
            </Button>
          )}
        </div>
      )}

      {editable && (
        <p className="text-sm text-theme-subtle">
          Click on time slots to mark when you are available. Your availability helps other members find the best time to connect.
        </p>
      )}

      {/* Grid */}
      <div className="overflow-x-auto -mx-2 px-2">
        <div className="min-w-[640px]">
          {/* Day Headers */}
          <div className="grid grid-cols-[60px_repeat(7,1fr)] gap-1 mb-1">
            <div /> {/* Empty corner cell */}
            {DAYS.map((day) => (
              <div key={day} className="text-center">
                <span className="text-xs font-medium text-theme-muted">{day}</span>
              </div>
            ))}
          </div>

          {/* Time Rows */}
          <div className="space-y-0.5">
            {displaySlots.map((time) => (
              <div key={time} className="grid grid-cols-[60px_repeat(7,1fr)] gap-0.5">
                {/* Time Label */}
                <div className="flex items-center justify-end pr-2">
                  <span className="text-[10px] text-theme-subtle">{time}</span>
                </div>

                {/* Day Cells */}
                {DAYS.map((_, dayIdx) => {
                  const key = slotKey(dayIdx, time);
                  const isAvail = slots.get(key) || false;

                  return (
                    <Tooltip
                      key={key}
                      content={`${FULL_DAYS[dayIdx] ?? ''} ${time} - ${isAvail ? tAvail('available') : tAvail('unavailable')}`}
                      delay={300}
                      closeDelay={0}
                      size="sm"
                    >
                      <Button
                        onPress={() => toggleSlot(dayIdx, time)}
                        isDisabled={!editable}
                        className={`
                          h-6 rounded-sm transition-all min-w-0 p-0
                          ${isAvail
                            ? 'bg-emerald-500/60 hover:bg-emerald-500/80 border border-emerald-500/30'
                            : 'bg-theme-elevated hover:bg-theme-hover border border-theme-default'
                          }
                          ${editable ? 'cursor-pointer' : 'cursor-default'}
                        `}
                        variant="flat"
                        aria-label={`${FULL_DAYS[dayIdx] ?? ''} ${time}: ${isAvail ? tAvail('available') : tAvail('unavailable')}`}
                      />
                    </Tooltip>
                  );
                })}
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Legend */}
      <div className="flex items-center gap-4 text-xs text-theme-subtle">
        <div className="flex items-center gap-1.5">
          <div className="w-4 h-4 rounded-sm bg-emerald-500/60 border border-emerald-500/30" />
          <span>{tAvail('available')}</span>
        </div>
        <div className="flex items-center gap-1.5">
          <div className="w-4 h-4 rounded-sm bg-theme-elevated border border-theme-default" />
          <span>{tAvail('unavailable')}</span>
        </div>
      </div>
    </div>
  );
}

export default AvailabilityGrid;
