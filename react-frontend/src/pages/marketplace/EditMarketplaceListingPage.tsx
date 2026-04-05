// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * EditMarketplaceListingPage — Edit an existing marketplace listing.
 *
 * Loads the listing via GET /v2/marketplace/listings/{id}, pre-populates the
 * same form layout used by CreateMarketplaceListingPage, and saves changes
 * via PUT /v2/marketplace/listings/{id}.
 *
 * Features:
 * - Owner-only access (redirects non-owners)
 * - Existing image management (reorder, delete, add new)
 * - Dynamic category template fields
 * - AI description generation
 * - Redirect to listing detail on save
 * - Requires authentication
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { useNavigate, useParams, Link } from 'react-router-dom';
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
  MapPin,
  Truck,
  Package,
  DollarSign,
  FileText,
  Upload,
  Save,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useAuth, useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { usePageTitle } from '@/hooks';
import { PageMeta } from '@/components/seo/PageMeta';
import type { MarketplaceListingDetail } from '@/types/marketplace';

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
  file?: File;
  url: string;
  /** Existing image id from the server */
  serverId?: number;
  isExisting: boolean;
}

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

export function EditMarketplaceListingPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { t } = useTranslation('marketplace');
  usePageTitle(t('edit.page_title', 'Edit Listing - Marketplace'));
  const { isAuthenticated, user } = useAuth();
  const { tenantPath } = useTenant();
  const toast = useToast();
  const fileInputRef = useRef<HTMLInputElement>(null);

  // Loading / error states
  const [isLoadingListing, setIsLoadingListing] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);

  // Form state
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [categoryId, setCategoryId] = useState('');
  const [condition, setCondition] = useState('good');
  const [price, setPrice] = useState('');
  const [currency, setCurrency] = useState('EUR');
  const [priceType, setPriceType] = useState('fixed');
  const [location, setLocation] = useState('');
  const [deliveryMethod, setDeliveryMethod] = useState('pickup');
  const [quantity, setQuantity] = useState('1');
  const [images, setImages] = useState<ImagePreview[]>([]);
  const [removedImageIds, setRemovedImageIds] = useState<number[]>([]);
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

  // Load listing data
  useEffect(() => {
    if (!id || !isAuthenticated) return;
    let cancelled = false;

    const load = async () => {
      setIsLoadingListing(true);
      setLoadError(null);
      try {
        const response = await api.get<MarketplaceListingDetail>(`/v2/marketplace/listings/${id}`);
        if (cancelled) return;

        if (!response.success || !response.data) {
          setLoadError(t('edit.not_found', 'Listing not found'));
          return;
        }

        const listing = response.data;

        // Owner check
        if (!listing.is_own && listing.user?.id !== user?.id) {
          toast.error(t('edit.not_owner', 'You can only edit your own listings'));
          navigate(tenantPath(`/marketplace/${id}`), { replace: true });
          return;
        }

        // Populate form
        setTitle(listing.title);
        setDescription(listing.description || '');
        setCategoryId(listing.category?.id ? String(listing.category.id) : '');
        setCondition(listing.condition || 'good');
        setPrice(listing.price != null ? String(listing.price) : '');
        setCurrency(listing.price_currency || 'EUR');
        setPriceType(listing.price_type || 'fixed');
        setLocation(listing.location || '');
        setDeliveryMethod(listing.delivery_method || 'pickup');
        setQuantity(String(listing.quantity ?? 1));

        // Map existing images
        if (listing.images?.length) {
          setImages(
            listing.images.map((img) => ({
              id: `existing-${img.id}`,
              url: img.url,
              serverId: img.id,
              isExisting: true,
            }))
          );
        }

        // Template fields
        if (listing.template_data && typeof listing.template_data === 'object') {
          const fields: Record<string, string> = {};
          for (const [key, val] of Object.entries(listing.template_data)) {
            fields[key] = String(val ?? '');
          }
          setTemplateFields(fields);
        }
      } catch (err) {
        logError('Failed to load marketplace listing for edit', err);
        if (!cancelled) {
          setLoadError(t('edit.load_error', 'Failed to load listing'));
        }
      } finally {
        if (!cancelled) setIsLoadingListing(false);
      }
    };

    load();
    return () => { cancelled = true; };
  }, [id, isAuthenticated, user?.id, navigate, tenantPath, toast]);

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
          // Initialize missing template field values (keep existing ones)
          setTemplateFields((prev) => {
            const next = { ...prev };
            response.data!.fields.forEach((f) => {
              if (!(f.key in next)) next[f.key] = '';
            });
            return next;
          });
        } else if (!cancelled) {
          setCategoryTemplate([]);
        }
      } catch (err) {
        logError('Failed to load category template', err);
        if (!cancelled) setCategoryTemplate([]);
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
        isExisting: false,
      });
    }

    setImages((prev) => [...prev, ...newImages]);
  }, [images.length, toast]);

  const removeImage = useCallback((imgId: string) => {
    setImages((prev) => {
      const img = prev.find((i) => i.id === imgId);
      if (!img) return prev;

      // Track removed existing images for the API
      if (img.isExisting && img.serverId) {
        setRemovedImageIds((ids) => [...ids, img.serverId!]);
      } else if (!img.isExisting) {
        URL.revokeObjectURL(img.url);
      }

      return prev.filter((i) => i.id !== imgId);
    });
  }, []);

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
      const response = await api.post<{ description: string }>('/v2/marketplace/listings/generate-description', {
        title,
        category_id: categoryId || undefined,
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
    if (!title.trim()) { toast.error(t('create.title_required', 'Title is required')); return; }
    if (!description.trim()) { toast.error(t('create.description_required', 'Description is required')); return; }
    if (priceType !== 'free' && (!price || parseFloat(price) < 0)) {
      toast.error(t('create.price_invalid', 'Please enter a valid price'));
      return;
    }

    setIsSubmitting(true);
    try {
      // Upload new images
      let uploadedImageIds: number[] = [];
      const newImageFiles = images.filter((img) => !img.isExisting && img.file);
      if (newImageFiles.length > 0) {
        const formData = new FormData();
        newImageFiles.forEach((img, idx) => {
          formData.append(`images[${idx}]`, img.file!);
        });
        const uploadResponse = await api.upload<{ image_ids: number[] }>(
          '/v2/marketplace/listings/images',
          formData
        );
        if (uploadResponse.success && uploadResponse.data?.image_ids) {
          uploadedImageIds = uploadResponse.data.image_ids;
        }
      }

      // Build the image_ids array: existing (not removed) + newly uploaded
      const existingImageIds = images
        .filter((img) => img.isExisting && img.serverId)
        .map((img) => img.serverId!);

      const body: Record<string, unknown> = {
        title: title.trim(),
        description: description.trim(),
        condition,
        price_type: priceType,
        delivery_method: deliveryMethod,
        quantity: parseInt(quantity) || 1,
        image_ids: [...existingImageIds, ...uploadedImageIds],
        removed_image_ids: removedImageIds,
      };

      if (categoryId) body.category_id = parseInt(categoryId);
      if (priceType !== 'free' && price) body.price = parseFloat(price);
      if (currency) body.currency = currency;
      if (location.trim()) body.location = location.trim();

      // Include template fields
      const filledTemplateFields = Object.fromEntries(
        Object.entries(templateFields).filter(([, v]) => v.trim() !== '')
      );
      if (Object.keys(filledTemplateFields).length > 0) {
        body.template_fields = filledTemplateFields;
      }

      const response = await api.put(`/v2/marketplace/listings/${id}`, body);
      if (response.success) {
        toast.success(t('edit.updated_success', 'Listing updated successfully!'));
        // Cleanup blob URLs
        images.forEach((img) => {
          if (!img.isExisting) URL.revokeObjectURL(img.url);
        });
        navigate(tenantPath(`/marketplace/${id}`));
      } else {
        toast.error(response.error || t('edit.updated_error', 'Failed to update listing'));
      }
    } catch (err) {
      logError('Failed to update marketplace listing', err);
      toast.error(t('edit.updated_error_retry', 'Failed to update listing. Please try again.'));
    } finally {
      setIsSubmitting(false);
    }
  }, [
    id, title, description, categoryId, condition, price, currency, priceType,
    location, deliveryMethod, quantity, images, removedImageIds, templateFields,
    toast, navigate, tenantPath,
  ]);

  if (!isAuthenticated) return null;

  // Loading state
  if (isLoadingListing) {
    return (
      <div className="flex justify-center py-16">
        <Spinner size="lg" color="primary" />
      </div>
    );
  }

  // Error state
  if (loadError) {
    return (
      <div className="max-w-3xl mx-auto px-4 py-12">
        <EmptyState
          icon={<Package className="w-8 h-8" />}
          title={t('edit.not_found_title', 'Listing Not Found')}
          description={loadError}
          action={{
            label: t('listing.back_to_marketplace', 'Back to Marketplace'),
            onClick: () => navigate(tenantPath('/marketplace')),
          }}
        />
      </div>
    );
  }

  return (
    <>
      <PageMeta title={t('edit.page_title', 'Edit Listing - Marketplace')} noIndex={true} />

      <div className="max-w-3xl mx-auto px-4 py-6 space-y-6">
        {/* Header */}
        <div className="flex items-center gap-3">
          <Button
            as={Link}
            to={tenantPath(`/marketplace/${id}`)}
            variant="light"
            isIconOnly
            size="sm"
          >
            <ArrowLeft className="w-5 h-5" />
          </Button>
          <div>
            <h1 className="text-2xl font-bold text-foreground">{t('edit.title', 'Edit Listing')}</h1>
            <p className="text-sm text-default-500">{t('edit.subtitle', 'Update your marketplace listing')}</p>
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
                    alt={`${idx === 0 ? 'Cover' : `Image ${idx + 1}`}`}
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
                      selectedKeys={templateFields[field.key] ? [templateFields[field.key]] : []}
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

          <Input
            label={t('create.location_label', 'Location')}
            placeholder={t('create.location_placeholder', 'City, town, or area')}
            value={location}
            onValueChange={setLocation}
            startContent={<MapPin className="w-4 h-4 text-default-400" />}
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
            to={tenantPath(`/marketplace/${id}`)}
          >
            {t('common.cancel', 'Cancel')}
          </Button>
          <Button
            color="primary"
            size="lg"
            onPress={handleSubmit}
            isLoading={isSubmitting}
            startContent={!isSubmitting ? <Save className="w-4 h-4" /> : undefined}
          >
            {t('edit.save_changes', 'Save Changes')}
          </Button>
        </div>
      </div>
    </>
  );
}

export default EditMarketplaceListingPage;
