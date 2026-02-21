// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * EventTab — quick event creation form for the Compose Hub.
 * Simplified version of CreateEventPage for use in the compose modal.
 */

import { useState } from 'react';
import { Button, Input, Textarea, DatePicker, TimeInput } from '@heroui/react';
import type { DateInputValue, TimeInputValue } from '@heroui/react';
import { today, getLocalTimeZone } from '@internationalized/date';
import { useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { PlaceAutocompleteInput } from '@/components/location';
import { AiAssistButton } from '../shared/AiAssistButton';
import { SdgGoalsPicker } from '../shared/SdgGoalsPicker';
import type { TabSubmitProps } from '../types';

const inputClasses = {
  input: 'bg-transparent text-[var(--text-primary)]',
  inputWrapper: 'bg-[var(--surface-elevated)] border-[var(--border-default)] hover:border-[var(--color-primary)]/40',
};

/** Convert DateInputValue + optional TimeInputValue to ISO string */
function toIsoString(date: DateInputValue, time: TimeInputValue | null): string {
  const dateStr = date.toString(); // "2026-02-21"
  if (time) {
    const h = String(time.hour).padStart(2, '0');
    const m = String(time.minute).padStart(2, '0');
    return `${dateStr}T${h}:${m}:00`;
  }
  return `${dateStr}T00:00:00`;
}

export function EventTab({ onSuccess, onClose, groupId }: TabSubmitProps) {
  const toast = useToast();
  const { tenantPath } = useTenant();
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [startDate, setStartDate] = useState<DateInputValue | null>(null);
  const [startTime, setStartTime] = useState<TimeInputValue | null>(null);
  const [endDate, setEndDate] = useState<DateInputValue | null>(null);
  const [endTime, setEndTime] = useState<TimeInputValue | null>(null);
  const [location, setLocation] = useState('');
  const [latitude, setLatitude] = useState<number | undefined>();
  const [longitude, setLongitude] = useState<number | undefined>();
  const [sdgGoals, setSdgGoals] = useState<number[]>([]);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const canSubmit = title.trim().length > 0 && startDate !== null;

  const handleSubmit = async () => {
    if (!title.trim()) {
      toast.error('Please enter an event title');
      return;
    }
    if (!startDate) {
      toast.error('Please select a start date');
      return;
    }

    setIsSubmitting(true);
    try {
      const payload: Record<string, unknown> = {
        title: title.trim(),
        start_time: toIsoString(startDate, startTime),
      };
      if (description.trim()) payload.description = description.trim();
      if (endDate) payload.end_time = toIsoString(endDate, endTime);
      if (location) payload.location = location;
      if (latitude != null) payload.latitude = latitude;
      if (longitude != null) payload.longitude = longitude;
      if (groupId) payload.group_id = groupId;
      if (sdgGoals.length > 0) payload.sdg_goals = sdgGoals;

      const res = await api.post<{ id: number }>('/v2/events', payload);
      if (res.success) {
        const id = res.data?.id;
        toast.success(
          id
            ? `Event created! View it at ${tenantPath(`/events/${id}`)}`
            : 'Event created!'
        );
        onClose();
        onSuccess('event', id);
      }
    } catch (err) {
      logError('Failed to create event', err);
      toast.error('Failed to create event');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="space-y-4">
      <Input
        label="Event Title"
        placeholder="What's happening?"
        value={title}
        onChange={(e) => setTitle(e.target.value)}
        isRequired
        classNames={inputClasses}
      />

      <div>
        <Textarea
          label="Description"
          placeholder="Tell people about this event..."
          value={description}
          onChange={(e) => setDescription(e.target.value)}
          minRows={2}
          maxRows={5}
          classNames={inputClasses}
        />
        <div className="flex justify-end mt-1">
          <AiAssistButton
            type="event"
            title={title}
            onGenerated={setDescription}
          />
        </div>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <DatePicker
          label="Start Date"
          value={startDate}
          onChange={setStartDate}
          granularity="day"
          minValue={today(getLocalTimeZone())}
          isRequired
          classNames={{
            inputWrapper: 'bg-[var(--surface-elevated)] border-[var(--border-default)]',
          }}
        />
        <TimeInput
          label="Start Time"
          value={startTime}
          onChange={setStartTime}
          classNames={{
            inputWrapper: 'bg-[var(--surface-elevated)] border-[var(--border-default)]',
          }}
        />
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <DatePicker
          label="End Date (optional)"
          value={endDate}
          onChange={setEndDate}
          granularity="day"
          minValue={startDate || today(getLocalTimeZone())}
          classNames={{
            inputWrapper: 'bg-[var(--surface-elevated)] border-[var(--border-default)]',
          }}
        />
        <TimeInput
          label="End Time"
          value={endTime}
          onChange={setEndTime}
          classNames={{
            inputWrapper: 'bg-[var(--surface-elevated)] border-[var(--border-default)]',
          }}
        />
      </div>

      <PlaceAutocompleteInput
        label="Location (optional)"
        placeholder="Start typing an address..."
        value={location}
        onPlaceSelect={(place) => {
          setLocation(place.formattedAddress);
          setLatitude(place.lat);
          setLongitude(place.lng);
        }}
        onChange={setLocation}
      />

      <SdgGoalsPicker selected={sdgGoals} onChange={setSdgGoals} />

      <div className="flex flex-col-reverse sm:flex-row justify-end gap-2 pt-2">
        <Button
          variant="flat"
          onPress={onClose}
          className="text-[var(--text-muted)]"
        >
          Cancel
        </Button>
        <Button
          className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white shadow-lg shadow-indigo-500/20"
          onPress={handleSubmit}
          isLoading={isSubmitting}
          isDisabled={!canSubmit}
        >
          Create Event
        </Button>
      </div>
    </div>
  );
}
