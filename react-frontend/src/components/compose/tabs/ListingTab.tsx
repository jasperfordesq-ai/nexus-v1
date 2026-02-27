// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * ListingTab — quick listing creation form for the Compose Hub.
 * Now with character count and draft persistence.
 */

import { useState, useEffect, useCallback } from 'react';
import { Button, Input, Textarea, Select, SelectItem, Chip } from '@heroui/react';
import { useTranslation } from 'react-i18next';
import { useToast } from '@/contexts';
import { useDraftPersistence } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { PlaceAutocompleteInput } from '@/components/location';
import { AiAssistButton } from '../shared/AiAssistButton';
import { SdgGoalsPicker } from '../shared/SdgGoalsPicker';
import { CharacterCount } from '../shared/CharacterCount';
import { EmojiPicker } from '../shared/EmojiPicker';
import type { TabSubmitProps } from '../types';

interface Category {
  id: number;
  name: string;
}

const inputClasses = {
  input: 'bg-transparent text-[var(--text-primary)] text-base',
  inputWrapper: 'bg-[var(--surface-elevated)] border-[var(--border-default)] hover:border-[var(--color-primary)]/40',
};

const MAX_DESC_CHARS = 3000;

interface ListingDraft {
  title: string;
  description: string;
  type: 'offer' | 'request';
}

export function ListingTab({ onSuccess, onClose, templateData }: TabSubmitProps) {
  const { t } = useTranslation('feed');
  const toast = useToast();

  const [draft, setDraft, clearDraft] = useDraftPersistence<ListingDraft>(
    'compose-draft-listing',
    { title: '', description: '', type: 'offer' },
  );

  const [categoryId, setCategoryId] = useState('');
  const [hoursEstimate, setHoursEstimate] = useState('1');
  const [location, setLocation] = useState('');
  const [latitude, setLatitude] = useState<number | undefined>();
  const [longitude, setLongitude] = useState<number | undefined>();
  const [sdgGoals, setSdgGoals] = useState<number[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Apply template data when selected from TemplatePicker
  useEffect(() => {
    if (templateData) {
      setDraft((prev) => ({
        ...prev,
        title: templateData.title || prev.title,
        description: templateData.content,
      }));
    }
  }, [templateData, setDraft]);

  const canSubmit = draft.title.trim().length > 0 && draft.description.trim().length > 0;

  const setTitle = useCallback(
    (v: string) => setDraft((prev) => ({ ...prev, title: v })),
    [setDraft],
  );
  const setDescription = useCallback(
    (v: string) => setDraft((prev) => ({ ...prev, description: v })),
    [setDraft],
  );
  const setType = useCallback(
    (v: 'offer' | 'request') => setDraft((prev) => ({ ...prev, type: v })),
    [setDraft],
  );

  const handleEmojiSelect = useCallback(
    (emoji: string) => {
      setDraft((prev) => ({ ...prev, description: prev.description + emoji }));
    },
    [setDraft],
  );

  useEffect(() => {
    async function loadCategories() {
      try {
        const res = await api.get<Category[]>('/v2/categories');
        if (res.success && res.data) {
          setCategories(Array.isArray(res.data) ? res.data : []);
        }
      } catch (err) {
        logError('Failed to load categories', err);
      }
    }
    loadCategories();
  }, []);

  const handleSubmit = async () => {
    if (!canSubmit) return;

    setIsSubmitting(true);
    try {
      const payload: Record<string, unknown> = {
        title: draft.title.trim(),
        description: draft.description.trim(),
        type: draft.type,
        hours_estimate: parseFloat(hoursEstimate) || 1,
      };
      if (categoryId) payload.category_id = parseInt(categoryId);
      if (location) payload.location = location;
      if (latitude != null) payload.latitude = latitude;
      if (longitude != null) payload.longitude = longitude;
      if (sdgGoals.length > 0) payload.sdg_goals = sdgGoals;

      const res = await api.post<{ id: number }>('/v2/listings', payload);
      if (res.success) {
        const id = res.data?.id;
        clearDraft();
        toast.success(t('compose.listing_created'));
        onClose();
        onSuccess('listing', id);
      } else {
        toast.error(t('compose.listing_failed'));
      }
    } catch (err) {
      logError('Failed to create listing', err);
      toast.error(t('compose.listing_failed'));
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="space-y-4">
      <Input
        label={t('compose.listing_title_label')}
        placeholder={t('compose.listing_title_placeholder')}
        value={draft.title}
        onChange={(e) => setTitle(e.target.value)}
        isRequired
        classNames={inputClasses}
      />

      <div className="flex gap-2">
        <Chip
          size="sm"
          variant={draft.type === 'offer' ? 'solid' : 'flat'}
          className={`cursor-pointer transition-all ${
            draft.type === 'offer'
              ? 'bg-gradient-to-r from-emerald-500 to-teal-600 text-white'
              : 'bg-[var(--surface-elevated)] text-[var(--text-muted)]'
          }`}
          onClick={() => setType('offer')}
        >
          {t('compose.listing_offering')}
        </Chip>
        <Chip
          size="sm"
          variant={draft.type === 'request' ? 'solid' : 'flat'}
          className={`cursor-pointer transition-all ${
            draft.type === 'request'
              ? 'bg-gradient-to-r from-amber-500 to-orange-600 text-white'
              : 'bg-[var(--surface-elevated)] text-[var(--text-muted)]'
          }`}
          onClick={() => setType('request')}
        >
          {t('compose.listing_looking_for')}
        </Chip>
      </div>

      <div>
        <div className="flex items-end gap-2">
          <div className="flex-1">
            <Textarea
              label={t('compose.description_label')}
              placeholder={t('compose.listing_desc_placeholder')}
              value={draft.description}
              onChange={(e) => setDescription(e.target.value)}
              isRequired
              minRows={3}
              maxRows={6}
              classNames={inputClasses}
            />
            <CharacterCount current={draft.description.length} max={MAX_DESC_CHARS} />
          </div>
        </div>
        <div className="flex justify-end mt-1">
          <AiAssistButton
            type="listing"
            title={draft.title}
            context={{ type: draft.type }}
            onGenerated={setDescription}
          />
        </div>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        {categories.length > 0 && (
          <Select
            label={t('compose.category_label')}
            placeholder={t('compose.category_placeholder')}
            selectedKeys={categoryId ? [categoryId] : []}
            onSelectionChange={(keys) => setCategoryId(Array.from(keys)[0] as string)}
            classNames={{
              trigger: 'bg-[var(--surface-elevated)] border-[var(--border-default)]',
              value: 'text-[var(--text-primary)]',
            }}
          >
            {categories.map((c) => (
              <SelectItem key={String(c.id)} textValue={c.name}>
                {c.name}
              </SelectItem>
            ))}
          </Select>
        )}
        <Input
          type="number"
          label={t('compose.estimated_hours_label')}
          placeholder="1"
          value={hoursEstimate}
          onChange={(e) => setHoursEstimate(e.target.value)}
          min={0.5}
          step={0.5}
          classNames={inputClasses}
        />
      </div>

      <PlaceAutocompleteInput
        label={t('compose.location_label')}
        placeholder={t('compose.location_placeholder')}
        value={location}
        onPlaceSelect={(place) => {
          setLocation(place.formattedAddress);
          setLatitude(place.lat);
          setLongitude(place.lng);
        }}
        onChange={setLocation}
      />

      <SdgGoalsPicker selected={sdgGoals} onChange={setSdgGoals} />

      <div className="flex items-center justify-between pt-1">
        <div className="flex items-center gap-1">
          <EmojiPicker onSelect={handleEmojiSelect} />
        </div>

        <div className="flex items-center gap-2">
        <Button
          variant="flat"
          size="sm"
          onPress={onClose}
          className="text-[var(--text-muted)]"
        >
          {t('compose.cancel')}
        </Button>
        <Button
          size="sm"
          className={`text-white shadow-lg ${
            draft.type === 'offer'
              ? 'bg-gradient-to-r from-emerald-500 to-teal-600 shadow-emerald-500/20'
              : 'bg-gradient-to-r from-amber-500 to-orange-600 shadow-amber-500/20'
          }`}
          onPress={handleSubmit}
          isLoading={isSubmitting}
          isDisabled={!canSubmit}
        >
          {t('compose.create_listing')}
        </Button>
        </div>
      </div>
    </div>
  );
}
