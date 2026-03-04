// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * ListingTab — quick listing creation form for the Compose Hub.
 * Now with character count and draft persistence.
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { Button, Input, Textarea, Select, SelectItem, Chip } from '@heroui/react';
import { ImagePlus, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useToast } from '@/contexts';
import { useDraftPersistence } from '@/hooks';
import { useMediaQuery } from '@/hooks/useMediaQuery';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { compressImage } from '@/lib/compress-image';
import { PlaceAutocompleteInput } from '@/components/location';
import { AiAssistButton } from '../shared/AiAssistButton';
import { SdgGoalsPicker } from '../shared/SdgGoalsPicker';
import { CharacterCount } from '../shared/CharacterCount';
import { EmojiPicker } from '../shared/EmojiPicker';
import { useComposeSubmit } from '../ComposeSubmitContext';
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
  const { register, unregister } = useComposeSubmit();
  const isMobile = useMediaQuery('(max-width: 639px)');
  const submitRef = useRef<() => void>(() => {});

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
  const [imageFile, setImageFile] = useState<File | null>(null);
  const [imagePreview, setImagePreview] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const imageInputRef = useRef<HTMLInputElement>(null);

  // Cleanup object URL on unmount
  useEffect(() => {
    return () => {
      if (imagePreview) URL.revokeObjectURL(imagePreview);
    };
  }, [imagePreview]);

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

  const gradientClass = draft.type === 'offer'
    ? 'from-emerald-500 to-teal-600'
    : 'from-amber-500 to-orange-600';

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

  const handleImageSelect = useCallback(async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (imageInputRef.current) imageInputRef.current.value = '';
    if (!file) return;

    if (!file.type.startsWith('image/')) {
      toast.error(t('compose.image_select_error'));
      return;
    }
    if (file.size > 5 * 1024 * 1024) {
      toast.error(t('compose.image_size_error', { size: 5 }));
      return;
    }

    try {
      const compressed = await compressImage(file);
      if (imagePreview) URL.revokeObjectURL(imagePreview);
      setImageFile(compressed);
      setImagePreview(URL.createObjectURL(compressed));
    } catch {
      toast.error(t('compose.image_select_error'));
    }
  }, [imagePreview, toast, t]);

  const handleImageRemove = useCallback(() => {
    if (imagePreview) URL.revokeObjectURL(imagePreview);
    setImageFile(null);
    setImagePreview(null);
  }, [imagePreview]);

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
        const listingId = res.data?.id;

        // Upload image if selected (after listing creation, same pattern as CreateListingPage)
        if (imageFile && listingId) {
          try {
            await api.upload(`/v2/listings/${listingId}/image`, imageFile, 'image');
          } catch (imgErr) {
            logError('Failed to upload listing image', imgErr);
          }
        }

        clearDraft();
        handleImageRemove();
        toast.success(t('compose.listing_created'));
        onClose();
        onSuccess('listing', listingId);
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
  submitRef.current = handleSubmit;

  // Register submit capabilities for mobile header button
  useEffect(() => {
    register({
      canSubmit,
      isSubmitting,
      onSubmit: () => submitRef.current(),
      buttonLabel: t('compose.create_listing'),
      gradientClass,
    });
    return unregister;
  }, [canSubmit, isSubmitting, gradientClass, register, unregister, t]);

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

      {/* Image upload */}
      <div>
        <input
          ref={imageInputRef}
          type="file"
          accept="image/jpeg,image/png,image/webp"
          className="hidden"
          onChange={handleImageSelect}
        />
        {imagePreview ? (
          <div className="relative inline-block rounded-xl overflow-hidden border border-[var(--border-default)]">
            <img
              src={imagePreview}
              alt="Listing preview"
              className="h-24 w-auto max-w-full object-cover rounded-xl"
            />
            <Button
              isIconOnly
              variant="flat"
              size="sm"
              className="absolute top-1 right-1 bg-black/60 text-white min-w-7 w-7 h-7 backdrop-blur-sm"
              onPress={handleImageRemove}
              aria-label={t('compose.image_remove_aria')}
            >
              <X className="w-3.5 h-3.5" />
            </Button>
          </div>
        ) : (
          <Button
            size="sm"
            variant="flat"
            className="bg-[var(--surface-elevated)] text-[var(--text-muted)] hover:text-[var(--color-primary)] min-h-[44px]"
            startContent={<ImagePlus className="w-4 h-4" aria-hidden="true" />}
            onPress={() => imageInputRef.current?.click()}
          >
            {t('compose.image_add')}
          </Button>
        )}
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

      <div className={`flex items-center justify-between pt-1 ${isMobile ? 'sticky bottom-0 bg-[var(--surface-base)] py-3 border-t border-[var(--border-default)]' : ''}`}>
        <div className="flex items-center gap-1">
          <EmojiPicker onSelect={handleEmojiSelect} />
        </div>

        {!isMobile && (
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
        )}
      </div>
    </div>
  );
}
