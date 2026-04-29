// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * SellerPickupSlotsPage — AG45 click-and-collect: seller manages pickup time slots.
 */

import { useEffect, useState, useCallback } from 'react';
import {
  Button,
  Input,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Switch,
  useDisclosure,
  Spinner,
  Chip,
} from '@heroui/react';
import Calendar from 'lucide-react/icons/calendar';
import Plus from 'lucide-react/icons/plus';
import Trash2 from 'lucide-react/icons/trash-2';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { useAuth, useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { usePageTitle } from '@/hooks';

interface PickupSlot {
  id: number;
  slot_start: string;
  slot_end: string;
  capacity: number;
  booked_count: number;
  remaining?: number;
  is_recurring: boolean;
  is_active: boolean;
}

export function SellerPickupSlotsPage() {
  const { t } = useTranslation('common');
  usePageTitle(t('marketplace.pickup.slots_title', 'Pickup Slots'));
  const { isAuthenticated } = useAuth();
  const toast = useToast();
  const { isOpen, onOpen, onClose } = useDisclosure();

  const [slots, setSlots] = useState<PickupSlot[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  const [slotStart, setSlotStart] = useState('');
  const [slotEnd, setSlotEnd] = useState('');
  const [capacity, setCapacity] = useState(5);
  const [isRecurring, setIsRecurring] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get<PickupSlot[]>('/v2/marketplace/seller/pickup-slots');
      if (res.success && res.data) setSlots(res.data);
    } catch (err) {
      logError('SellerPickupSlotsPage: load failed', err);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    if (isAuthenticated) {
      load();
    }
  }, [isAuthenticated, load]);

  const handleCreate = async () => {
    if (!slotStart || !slotEnd) {
      toast.error(t('marketplace.pickup.slot_times_required', 'Start and end times are required'));
      return;
    }
    setSaving(true);
    try {
      const res = await api.post<PickupSlot>('/v2/marketplace/seller/pickup-slots', {
        slot_start: new Date(slotStart).toISOString(),
        slot_end: new Date(slotEnd).toISOString(),
        capacity,
        is_recurring: isRecurring,
        is_active: true,
      });
      if (res.success) {
        toast.success(t('marketplace.pickup.slot_created', 'Pickup slot created'));
        onClose();
        setSlotStart('');
        setSlotEnd('');
        setCapacity(5);
        setIsRecurring(false);
        load();
      } else {
        toast.error(res.error || t('marketplace.pickup.slot_create_failed', 'Failed to create slot'));
      }
    } catch (err) {
      logError('SellerPickupSlotsPage: create failed', err);
      toast.error(t('marketplace.pickup.slot_create_failed', 'Failed to create slot'));
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (id: number) => {
    try {
      const res = await api.delete(`/v2/marketplace/seller/pickup-slots/${id}`);
      if (res.success) {
        toast.success(t('marketplace.pickup.slot_deleted', 'Slot deleted'));
        setSlots((prev) => prev.filter((s) => s.id !== id));
      }
    } catch (err) {
      logError('SellerPickupSlotsPage: delete failed', err);
    }
  };

  const formatRange = (s: string, e: string) => {
    try {
      const a = new Date(s);
      const b = new Date(e);
      return `${a.toLocaleString()} → ${b.toLocaleTimeString()}`;
    } catch {
      return `${s} → ${e}`;
    }
  };

  return (
    <div className="max-w-5xl mx-auto px-4 py-6 space-y-6">
      <div className="flex items-center justify-between flex-wrap gap-4">
        <div>
          <h1 className="text-2xl font-bold text-foreground flex items-center gap-2">
            <Calendar className="w-7 h-7 text-primary" />
            {t('marketplace.pickup.slots_title', 'Pickup Slots')}
          </h1>
          <p className="text-default-500 text-sm mt-1">
            {t('marketplace.pickup.slots_subtitle', 'Offer click-and-collect pickup windows for buyers.')}
          </p>
        </div>
        <Button color="primary" startContent={<Plus className="w-4 h-4" />} onPress={onOpen}>
          {t('marketplace.pickup.new_slot', 'New Slot')}
        </Button>
      </div>

      {loading ? (
        <div className="flex justify-center py-16">
          <Spinner size="lg" color="primary" />
        </div>
      ) : slots.length === 0 ? (
        <GlassCard className="p-8 text-center">
          <p className="text-default-500">
            {t('marketplace.pickup.no_slots', 'No pickup slots yet. Create one to enable click-and-collect.')}
          </p>
        </GlassCard>
      ) : (
        <div className="grid gap-3">
          {slots.map((s) => (
            <GlassCard key={s.id} className="p-4 flex items-center justify-between gap-4 flex-wrap">
              <div>
                <p className="font-semibold text-foreground">{formatRange(s.slot_start, s.slot_end)}</p>
                <div className="flex gap-2 mt-1 flex-wrap">
                  <Chip size="sm" variant="flat" color="primary">
                    {t('marketplace.pickup.capacity', 'Capacity')}: {s.booked_count}/{s.capacity}
                  </Chip>
                  {s.is_recurring && (
                    <Chip size="sm" variant="flat" color="secondary">
                      {t('marketplace.pickup.recurring', 'Recurring')}
                    </Chip>
                  )}
                  {!s.is_active && (
                    <Chip size="sm" variant="flat" color="warning">
                      {t('marketplace.pickup.inactive', 'Inactive')}
                    </Chip>
                  )}
                </div>
              </div>
              <Button
                size="sm"
                variant="flat"
                color="danger"
                isIconOnly
                onPress={() => handleDelete(s.id)}
                aria-label={t('common.delete', 'Delete')}
              >
                <Trash2 className="w-4 h-4" />
              </Button>
            </GlassCard>
          ))}
        </div>
      )}

      <Modal isOpen={isOpen} onClose={onClose}>
        <ModalContent>
          <ModalHeader>{t('marketplace.pickup.new_slot', 'New Pickup Slot')}</ModalHeader>
          <ModalBody className="space-y-3">
            <Input
              type="datetime-local"
              label={t('marketplace.pickup.slot_start', 'Start')}
              value={slotStart}
              onValueChange={setSlotStart}
            />
            <Input
              type="datetime-local"
              label={t('marketplace.pickup.slot_end', 'End')}
              value={slotEnd}
              onValueChange={setSlotEnd}
            />
            <Input
              type="number"
              label={t('marketplace.pickup.capacity', 'Capacity')}
              value={String(capacity)}
              onValueChange={(v) => setCapacity(Math.max(1, parseInt(v) || 1))}
              min={1}
            />
            <Switch isSelected={isRecurring} onValueChange={setIsRecurring}>
              {t('marketplace.pickup.recurring_weekly', 'Repeat weekly')}
            </Switch>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onClose}>
              {t('common.cancel', 'Cancel')}
            </Button>
            <Button color="primary" onPress={handleCreate} isLoading={saving}>
              {t('common.create', 'Create')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default SellerPickupSlotsPage;
