// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * CreateMarketplaceListingPage — Multi-section form to create a new listing.
 *
 * Sections (single scrollable page):
 * 1. Photos — drag-and-drop image upload (up to 20 images)
 * 2. Details — title, description, category, condition
 * 3. Pricing — price, currency, price type
 * 4. Delivery — location, shipping, delivery method
 *
 * Features:
 * - Dynamic category template fields
 * - AI description generation
 * - Form validation before submit
 * - Redirect to listing detail on success
 * - Requires authentication
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import {
  Button,
  Input,
  Textarea,
  Select,
  SelectItem,
  RadioGroup,
  Radio,
  Spinner,
  Chip,
} from '@heroui/react';
import {
  Camera,
  X,
  Plus,
  Sparkles,
  ArrowLeft,
  Truck,
  Package,
  DollarSign,
  FileText,
  Upload,
  Video,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { useAuth, useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { usePageTitle } from '@/hooks';
import { PageMeta } from '@/components/seo/PageMeta';
import { PlaceAutocompleteInput } from '@/components/location';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface MarketplaceCategory {
  id: number;
  name: string;
  slug: string;
}

interface TemplateField {
  key: string;
  label: string;
  type: 'text' | 'number' | 'select';
  options?: string[];
  required?: boolean;
}

interface ImagePreview {
  id: string;
  file: File;
  url: string;
}

// Labels here serve as fallback defaults; translated labels are applied via
// t('condition.*'), t('price_type.*'), and t('delivery_method.*') in the JSX.
const CONDITIONS = [
  { value: 'new', label: 'New', tKey: 'condition.new' },
  { value: 'like_new', label: 'Like New', tKey: 'condition.like_new' },
  { value: 'good', label: 'Good', tKey: 'condition.good' },
  { value: 'fair', label: 'Fair', tKey: 'condition.fair' },
  { value: 'poor', label: 'Poor', tKey: 'condition.poor' },
] as const;

const PRICE_TYPES = [
  { value: 'fixed', label: 'Fixed Price', tKey: 'price_type.fixed' },
  { value: 'negotiable', label: 'Negotiable', tKey: 'price_type.negotiable' },
  { value: 'free', label: 'Free', tKey: 'price_type.free' },
] as const;

const DELIVERY_METHODS = [
  { value: 'pickup', label: 'Pickup Only', tKey: 'delivery_method.pickup' },
  { value: 'shipping', label: 'Shipping Only', tKey: 'delivery_method.shipping' },
  { value: 'both', label: 'Pickup or Shipping', tKey: 'delivery_method.both' },
] as const;

const MAX_IMAGES = 20;

// ─────────────────────────────────────────────────────────────────────────────
// Main Component
// ─────────────────────────────────────────────────────────────────────────────

export function CreateMarketplaceListingPage() {
  const navigate = useNavigate();
  const { t } = useTranslation('marketplace');
  usePageTitle(t('create.page_title', 'Sell Something - Marketplace'));
  const { isAuthenticated } = useAuth();
  const { tenantPath } = useTenant();
  const toast = useToast();
  const fileInputRef = useRef<HTMLInputElement>(null);

  // Form state
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [categoryId, setCategoryId] = useState('');
  const [condition, setCondition] = useState('good');
  const [price, setPrice] = useState('');
  const [currency, setCurrency] = useState('EUR');
  const [priceType, setPriceType] = useState('fixed');
  const [location, setLocation] = useState('');
  const [latitude, setLatitude] = useState<number | undefined>();
  const [longitude, setLongitude] = useState<number | undefined>();
  const [deliveryMethod, setDeliveryMethod] = useState('pickup');
  const [quantity, setQuantity] = useState('1');
  const [images, setImages] = useState<ImagePreview[]>([]);
  const [videoFile, setVideoFile] = useState<File | null>(null);
  const [videoPreviewUrl, setVideoPreviewUrl] = useState<string | null>(null);
  const videoInputRef = useRef<HTMLInputElement>(null);
  const [templateFields, setTemplateFields] = useState<Record<string, string>>({});

  // Data state
  const [categories, setCategories] = useState<MarketplaceCategory[]>([]);
  const [categoryTemplate, setCategoryTemplate] = useState<TemplateField[]>([]);
  const [isLoadingCategories, setIsLoadingCategories] = useState(true);
  const [isLoadingTemplate, setIsLoadingTemplate] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isGeneratingDesc, setIsGeneratingDesc] = useState(false);

  // Redirect if not authenticated
  useEffect(() => {
    if (!isAuthenticated) {
      navigate(tenantPath('/auth/login'), { replace: true });
    }
  }, [isAuthenticated, navigate, tenantPath]);

  // Load categories
  useEffect(() => {
    let cancelled = false;
    const load = async () => {
      try {
        const response = await api.get<MarketplaceCategory[]>('/v2/marketplace/categories');
        if (!cancelled && response.success && response.data) {
          setCategories(response.data);
        }
      } catch (err) {
        logError('Failed to load marketplace categories', err);
      } finally {
        if (!cancelled) setIsLoadingCategories(false);
      }
    };
    load();
    return () => { cancelled = true; };
  }, []);

  // Load category template when category changes
  useEffect(() => {
    if (!categoryId) {
      setCategoryTemplate([]);
      setTemplateFields({});
      return;
    }

    let cancelled = false;
    const load = async () => {
      setIsLoadingTemplate(true);
      try {
        const response = await api.get<{ fields: TemplateField[] }>(
          `/v2/marketplace/categories/${categoryId}/template`
        );
        if (!cancelled && response.success && response.data?.fields) {
          setCategoryTemplate(response.data.fields);
          // Initialize template field values
          const initial: Record<string, string> = {};
          response.data.fields.forEach((f) => { initial[f.key] = ''; });
          setTemplateFields(initial);
        } else if (!cancelled) {
          setCategoryTemplate([]);
          setTemplateFields({});
        }
      } catch (err) {
        logError('Failed to load category template', err);
        if (!cancelled) {
          setCategoryTemplate([]);
          setTemplateFields({});
        }
      } finally {
        if (!cancelled) setIsLoadingTemplate(false);
      }
    };
    load();
    return () => { cancelled = true; };
  }, [categoryId]);

  // Image handling
  const handleImageSelect = useCallback((files: FileList | null) => {
    if (!files) return;
    const remaining = MAX_IMAGES - images.length;
    if (remaining <= 0) {
      toast.error(t('create.max_images_error', 'Maximum {{max}} images allowed', { max: MAX_IMAGES }));
      return;
    }

    const newImages: ImagePreview[] = [];
    const fileArray = Array.from(files).slice(0, remaining);

    for (const file of fileArray) {
      if (!file.type.startsWith('image/')) {
        toast.error(t('create.not_image_error', '{{name}} is not an image', { name: file.name }));
        continue;
      }
      if (file.size > 10 * 1024 * 1024) {
        toast.error(t('create.size_limit_error', '{{name}} exceeds 10MB limit', { name: file.name }));
        continue;
      }
      newImages.push({
        id: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
        file,
        url: URL.createObjectURL(file),
      });
    }

    setImages((prev) => [...prev, ...newImages]);
  }, [images.length, toast]);

  const removeImage = useCallback((id: string) => {
    setImages((prev) => {
      const img = prev.find((i) => i.id === id);
      if (img) URL.revokeObjectURL(img.url);
      return prev.filter((i) => i.id !== id);
    });
  }, []);

  // Video handling
  const MAX_VIDEO_SIZE = 50 * 1024 * 1024; // 50MB
  const ALLOWED_VIDEO_TYPES = ['video/mp4', 'video/webm', 'video/quicktime'];

  const handleVideoSelect = useCallback((files: FileList | null) => {
    if (!files || files.length === 0) return;
    const file = files[0] as File;

    if (!ALLOWED_VIDEO_TYPES.includes(file.type)) {
      toast.error(t('create.video_type_error', 'Only MP4, WebM, and MOV video files are allowed.'));
      return;
    }
    if (file.size > MAX_VIDEO_SIZE) {
      toast.error(t('create.video_size_error', '{{name}} exceeds the 50MB video size limit.', { name: file.name }));
      return;
    }

    // Revoke previous preview URL
    if (videoPreviewUrl) URL.revokeObjectURL(videoPreviewUrl);

    setVideoFile(file);
    setVideoPreviewUrl(URL.createObjectURL(file));
  }, [videoPreviewUrl, toast, t]);

  const removeVideo = useCallback(() => {
    if (videoPreviewUrl) URL.revokeObjectURL(videoPreviewUrl);
    setVideoFile(null);
    setVideoPreviewUrl(null);
  }, [videoPreviewUrl]);

  // Drag and drop
  const handleDrop = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    handleImageSelect(e.dataTransfer.files);
  }, [handleImageSelect]);

  const handleDragOver = useCallback((e: React.DragEvent) => {
    e.preventDefault();
  }, []);

  // AI description generation
  const handleGenerateDescription = useCallback(async () => {
    if (!title) {
      toast.error(t('create.enter_title_first', 'Enter a title first'));
      return;
    }
    setIsGeneratingDesc(true);
    try {
      const selectedCategory = categories.find((c) => String(c.id) === categoryId);
      const response = await api.post<{ description: string }>('/v2/marketplace/listings/generate-description', {
        title,
        category: selectedCategory?.name || undefined,
        condition,
      });
      if (response.success && response.data?.description) {
        setDescription(response.data.description);
        toast.success(t('create.description_generated', 'Description generated'));
      } else {
        toast.error(t('create.description_generate_failed', 'Failed to generate description'));
      }
    } catch (err) {
      logError('Failed to generate description', err);
      toast.error(t('create.description_generate_failed', 'Failed to generate description'));
    } finally {
      setIsGeneratingDesc(false);
    }
  }, [title, categoryId, condition, toast]);

  // Submit
  const handleSubmit = useCallback(async () => {
    // Validation
    if (!title.trim()) { toast.error(t('create.title_required', 'Title is required')); return; }
    if (!description.trim()) { toast.error(t('create.description_required', 'Description is required')); return; }
    if (priceType !== 'free' && (!price || parseFloat(price) < 0)) {
      toast.error(t('create.price_invalid', 'Please enter a valid price'));
      return;
    }

    setIsSubmitting(true);
    try {
      // Create listing first
      const body: Record<string, unknown> = {
        title: title.trim(),
        description: description.trim(),
        condition,
        price_type: priceType,
        delivery_method: deliveryMethod,
        quantity: parseInt(quantity) || 1,
        status: 'active',
      };

      if (categoryId) body.category_id = parseInt(categoryId);
      if (priceType !== 'free' && price) body.price = parseFloat(price);
      if (currency) body.price_currency = currency;
      if (location.trim()) body.location = location.trim();
      if (latitude !== undefined) body.latitude = latitude;
      if (longitude !== undefined) body.longitude = longitude;

      // Include template fields
      const filledTemplateFields = Object.fromEntries(
        Object.entries(templateFields).filter(([, v]) => v.trim() !== '')
      );
      if (Object.keys(filledTemplateFields).length > 0) {
        body.template_data = filledTemplateFields;
      }

      const response = await api.post<{ id: number }>('/v2/marketplace/listings', body);
      if (!response.success || !response.data?.id) {
        toast.error(response.error || t('create.created_error', 'Failed to create listing'));
        return;
      }

      const listingId = response.data.id;

      // Then upload images to the created listing
      if (images.length > 0) {
        const formData = new FormData();
        images.forEach((img, idx) => {
          formData.append(`images[${idx}]`, img.file);
        });
        await api.upload(`/v2/marketplace/listings/${listingId}/images`, formData);
      }

      // Upload video if one was selected
      if (videoFile) {
        const videoFormData = new FormData();
        videoFormData.append('video', videoFile);
        await api.upload(`/v2/marketplace/listings/${listingId}/video`, videoFormData);
      }

      toast.success(t('create.created_success', 'Listing created successfully!'));
      // Cleanup blob URLs
      images.forEach((img) => URL.revokeObjectURL(img.url));
      if (videoPreviewUrl) URL.revokeObjectURL(videoPreviewUrl);
      navigate(tenantPath(`/marketplace/${listingId}`));
    } catch (err) {
      logError('Failed to create marketplace listing', err);
      toast.error(t('create.created_error_retry', 'Failed to create listing. Please try again.'));
    } finally {
      setIsSubmitting(false);
    }
  }, [
    title, description, categoryId, condition, price, currency, priceType,
    location, deliveryMethod, quantity, images, templateFields, toast, navigate, tenantPath,
  ]);

  if (!isAuthenticated) return null;

  return (
    <>
      <PageMeta title={t('create.page_title', 'Sell Something - Marketplace')} noIndex={true} />

      <div className="max-w-3xl mx-auto px-4 py-6 space-y-6">
        {/* Header */}
        <div className="flex items-center gap-3">
          <Button
            as={Link}
            to={tenantPath('/marketplace')}
            variant="light"
            isIconOnly
            size="sm"
          >
            <ArrowLeft className="w-5 h-5" />
          </Button>
          <div>
            <h1 className="text-2xl font-bold text-foreground">{t('create.title', 'Sell Something')}</h1>
            <p className="text-sm text-default-500">{t('create.subtitle', 'Create a new marketplace listing')}</p>
          </div>
        </div>

        {/* Section 1: Photos */}
        <GlassCard className="p-6 space-y-4">
          <h2 className="text-lg font-semibold text-foreground flex items-center gap-2">
            <Camera className="w-5 h-5 text-primary" />
            {t('create.photos', 'Photos')}
            <span className="text-sm font-normal text-default-400">
              {t('create.photos_count', '({{current}}/{{max}})', { current: images.length, max: MAX_IMAGES })}
            </span>
          </h2>

          {/* Drop zone */}
          <div
            onDrop={handleDrop}
            onDragOver={handleDragOver}
            onClick={() => fileInputRef.current?.click()}
            className="border-2 border-dashed border-default-300 rounded-xl p-8 text-center cursor-pointer
              hover:border-primary hover:bg-primary/5 transition-colors"
          >
            <Upload className="w-10 h-10 text-default-300 mx-auto mb-3" />
            <p className="text-sm text-default-500">
              {t('create.drop_zone_text', 'Drag and drop images here, or click to browse')}
            </p>
            <p className="text-xs text-default-400 mt-1">
              {t('create.drop_zone_limits', 'Up to {{max}} images, max 10MB each. JPG, PNG, WebP.', { max: MAX_IMAGES })}
            </p>
            <input
              ref={fileInputRef}
              type="file"
              accept="image/*"
              multiple
              onChange={(e) => handleImageSelect(e.target.files)}
              className="hidden"
            />
          </div>

          {/* Image previews */}
          {images.length > 0 && (
            <div className="grid grid-cols-4 sm:grid-cols-5 md:grid-cols-6 gap-3">
              {images.map((img, idx) => (
                <div key={img.id} className="relative aspect-square rounded-lg overflow-hidden group">
                  <img
                    src={img.url}
                    alt={`Upload ${idx + 1}`}
                    className="w-full h-full object-cover"
                  />
                  {idx === 0 && (
                    <div className="absolute top-1 left-1">
                      <Chip size="sm" color="primary" variant="solid" className="text-[10px]">
                        {t('create.cover', 'Cover')}
                      </Chip>
                    </div>
                  )}
                  <button
                    onClick={() => removeImage(img.id)}
                    className="absolute top-1 right-1 p-1 rounded-full bg-danger/90 text-white
                      opacity-0 group-hover:opacity-100 transition-opacity"
                    aria-label={t('create.remove_image', 'Remove image')}
                  >
                    <X className="w-3 h-3" />
                  </button>
                </div>
              ))}

              {images.length < MAX_IMAGES && (
                <button
                  onClick={() => fileInputRef.current?.click()}
                  className="aspect-square rounded-lg border-2 border-dashed border-default-300 flex flex-col items-center justify-center
                    hover:border-primary hover:bg-primary/5 transition-colors"
                >
                  <Plus className="w-5 h-5 text-default-400" />
                  <span className="text-xs text-default-400 mt-1">{t('create.add', 'Add')}</span>
                </button>
              )}
            </div>
          )}
        </GlassCard>

        {/* Section 1b: Video */}
        <GlassCard className="p-6 space-y-4">
          <h2 className="text-lg font-semibold text-foreground flex items-center gap-2">
            <Video className="w-5 h-5 text-primary" />
            {t('create.video', 'Video')}
            <span className="text-sm font-normal text-default-400">
              {t('create.video_optional', '(optional, max 1 video, 50MB)')}
            </span>
          </h2>

          {videoPreviewUrl ? (
            <div className="relative aspect-video rounded-xl overflow-hidden bg-black">
              <video
                src={videoPreviewUrl}
                controls
                preload="metadata"
                className="w-full h-full object-contain"
              />
              <button
                onClick={removeVideo}
                className="absolute top-2 right-2 p-1.5 rounded-full bg-danger/90 text-white
                  hover:bg-danger transition-colors"
                aria-label={t('create.remove_video', 'Remove video')}
              >
                <X className="w-4 h-4" />
              </button>
            </div>
          ) : (
            <div
              onClick={() => videoInputRef.current?.click()}
              className="border-2 border-dashed border-default-300 rounded-xl p-6 text-center cursor-pointer
                hover:border-primary hover:bg-primary/5 transition-colors"
            >
              <Video className="w-8 h-8 text-default-300 mx-auto mb-2" />
              <p className="text-sm text-default-500">
                {t('create.video_drop_zone', 'Click to add a video')}
              </p>
              <p className="text-xs text-default-400 mt-1">
                {t('create.video_limits', 'MP4, WebM, or MOV. Max 50MB.')}
              </p>
              <input
                ref={videoInputRef}
                type="file"
                accept="video/mp4,video/webm,video/quicktime"
                onChange={(e) => handleVideoSelect(e.target.files)}
                className="hidden"
              />
            </div>
          )}
        </GlassCard>

        {/* Section 2: Details */}
        <GlassCard className="p-6 space-y-4">
          <h2 className="text-lg font-semibold text-foreground flex items-center gap-2">
            <FileText className="w-5 h-5 text-primary" />
            {t('create.details', 'Details')}
          </h2>

          <Input
            label={t('create.title_label', 'Title')}
            placeholder={t('create.title_placeholder', 'What are you selling?')}
            value={title}
            onValueChange={setTitle}
            isRequired
            maxLength={120}
            description={t('create.title_char_count', '{{count}}/120 characters', { count: title.length })}
          />

          <div className="space-y-2">
            <Textarea
              label={t('create.description_label', 'Description')}
              placeholder={t('create.description_placeholder', 'Describe your item in detail...')}
              value={description}
              onValueChange={setDescription}
              isRequired
              minRows={4}
              maxRows={10}
            />
            <div className="flex justify-end">
              <Button
                variant="flat"
                size="sm"
                startContent={<Sparkles className="w-3.5 h-3.5" />}
                onPress={handleGenerateDescription}
                isLoading={isGeneratingDesc}
                isDisabled={!title.trim()}
              >
                {t('create.generate_with_ai', 'Generate with AI')}
              </Button>
            </div>
          </div>

          <Select
            label={t('create.category_label', 'Category')}
            placeholder={t('create.category_placeholder', 'Select a category')}
            selectedKeys={categoryId ? [categoryId] : []}
            onSelectionChange={(keys) => {
              const selected = Array.from(keys)[0];
              setCategoryId(selected ? String(selected) : '');
            }}
            isLoading={isLoadingCategories}
          >
            {categories.map((cat) => (
              <SelectItem key={String(cat.id)}>
                {cat.name}
              </SelectItem>
            ))}
          </Select>

          <Select
            label={t('create.condition_label', 'Condition')}
            placeholder={t('create.condition_placeholder', 'Select condition')}
            selectedKeys={[condition]}
            onSelectionChange={(keys) => {
              const selected = Array.from(keys)[0];
              if (selected) setCondition(String(selected));
            }}
            isRequired
          >
            {CONDITIONS.map((c) => (
              <SelectItem key={c.value}>
                {t(c.tKey, c.label)}
              </SelectItem>
            ))}
          </Select>

          <Input
            label={t('create.quantity_label', 'Quantity')}
            type="number"
            min={1}
            value={quantity}
            onValueChange={setQuantity}
            className="max-w-[150px]"
          />

          {/* Dynamic template fields */}
          {isLoadingTemplate && (
            <div className="flex items-center gap-2 py-2">
              <Spinner size="sm" />
              <span className="text-sm text-default-400">{t('create.loading_category_fields', 'Loading category fields...')}</span>
            </div>
          )}
          {categoryTemplate.length > 0 && (
            <div className="space-y-3 pt-2">
              <p className="text-sm font-medium text-default-500">{t('create.category_specific_details', 'Category-specific details')}</p>
              {categoryTemplate.map((field) => {
                if (field.type === 'select' && field.options) {
                  return (
                    <Select
                      key={field.key}
                      label={field.label}
                      placeholder={`Select ${field.label.toLowerCase()}`}
                      selectedKeys={templateFields[field.key] ? [templateFields[field.key]].filter(Boolean) as string[] : []}
                      onSelectionChange={(keys) => {
                        const selected = Array.from(keys)[0];
                        setTemplateFields((prev) => ({
                          ...prev,
                          [field.key]: selected ? String(selected) : '',
                        }));
                      }}
                      isRequired={field.required}
                    >
                      {field.options.map((opt) => (
                        <SelectItem key={opt}>{opt}</SelectItem>
                      ))}
                    </Select>
                  );
                }
                return (
                  <Input
                    key={field.key}
                    label={field.label}
                    placeholder={`Enter ${field.label.toLowerCase()}`}
                    type={field.type === 'number' ? 'number' : 'text'}
                    value={templateFields[field.key] || ''}
                    onValueChange={(val) =>
                      setTemplateFields((prev) => ({ ...prev, [field.key]: val }))
                    }
                    isRequired={field.required}
                  />
                );
              })}
            </div>
          )}
        </GlassCard>

        {/* Section 3: Pricing */}
        <GlassCard className="p-6 space-y-4">
          <h2 className="text-lg font-semibold text-foreground flex items-center gap-2">
            <DollarSign className="w-5 h-5 text-primary" />
            {t('create.pricing', 'Pricing')}
          </h2>

          <RadioGroup
            label={t('create.price_type_label', 'Price Type')}
            value={priceType}
            onValueChange={setPriceType}
            orientation="horizontal"
          >
            {PRICE_TYPES.map((pt) => (
              <Radio key={pt.value} value={pt.value}>
                {t(pt.tKey, pt.label)}
              </Radio>
            ))}
          </RadioGroup>

          {priceType !== 'free' && (
            <div className="flex gap-3">
              <Input
                label={t('create.price_label', 'Price')}
                placeholder="0.00"
                type="number"
                min={0}
                step={0.01}
                value={price}
                onValueChange={setPrice}
                isRequired
                className="flex-1"
                startContent={
                  <span className="text-default-400 text-sm">{currency}</span>
                }
              />
              <Select
                label={t('create.currency_label', 'Currency')}
                selectedKeys={[currency]}
                onSelectionChange={(keys) => {
                  const selected = Array.from(keys)[0];
                  if (selected) setCurrency(String(selected));
                }}
                className="w-32"
              >
                <SelectItem key="EUR">EUR</SelectItem>
                <SelectItem key="GBP">GBP</SelectItem>
                <SelectItem key="USD">USD</SelectItem>
                <SelectItem key="CAD">CAD</SelectItem>
                <SelectItem key="AUD">AUD</SelectItem>
                <SelectItem key="NZD">NZD</SelectItem>
                <SelectItem key="CHF">CHF</SelectItem>
                <SelectItem key="SEK">SEK</SelectItem>
                <SelectItem key="NOK">NOK</SelectItem>
                <SelectItem key="DKK">DKK</SelectItem>
                <SelectItem key="PLN">PLN</SelectItem>
                <SelectItem key="JPY">JPY</SelectItem>
              </Select>
            </div>
          )}
        </GlassCard>

        {/* Section 4: Delivery */}
        <GlassCard className="p-6 space-y-4">
          <h2 className="text-lg font-semibold text-foreground flex items-center gap-2">
            <Truck className="w-5 h-5 text-primary" />
            {t('create.location_delivery', 'Location & Delivery')}
          </h2>

          <PlaceAutocompleteInput
            label={t('create.location_label', 'Location')}
            placeholder={t('create.location_placeholder', 'City, town, or area')}
            value={location}
            onChange={setLocation}
            onPlaceSelect={(place) => {
              setLocation(place.formattedAddress);
              setLatitude(place.lat);
              setLongitude(place.lng);
            }}
            onClear={() => {
              setLocation('');
              setLatitude(undefined);
              setLongitude(undefined);
            }}
            classNames={{
              inputWrapper: 'bg-theme-elevated border-theme-default',
              label: 'text-theme-muted',
            }}
          />

          <RadioGroup
            label={t('create.delivery_method_label', 'Delivery Method')}
            value={deliveryMethod}
            onValueChange={setDeliveryMethod}
            orientation="horizontal"
          >
            {DELIVERY_METHODS.map((dm) => (
              <Radio key={dm.value} value={dm.value}>
                {t(dm.tKey, dm.label)}
              </Radio>
            ))}
          </RadioGroup>
        </GlassCard>

        {/* Submit */}
        <div className="flex gap-3 justify-end pb-8">
          <Button
            variant="flat"
            as={Link}
            to={tenantPath('/marketplace')}
          >
            {t('create.cancel', 'Cancel')}
          </Button>
          <Button
            color="primary"
            size="lg"
            onPress={handleSubmit}
            isLoading={isSubmitting}
            startContent={!isSubmitting ? <Package className="w-4 h-4" /> : undefined}
          >
            {t('create.publish', 'Publish Listing')}
          </Button>
        </div>
      </div>
    </>
  );
}

export default CreateMarketplaceListingPage;
