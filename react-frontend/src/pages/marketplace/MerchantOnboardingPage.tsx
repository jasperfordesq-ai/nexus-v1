// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * MerchantOnboardingPage — Self-serve business onboarding wizard (AG48).
 *
 * 4-step wizard:
 *   1. Business Profile  — identity, seller type, registration
 *   2. Location & Hours  — address fields + weekly schedule grid
 *   3. Profile Photo     — avatar upload (+ optional cover image)
 *   4. Complete          — summary + "Launch your shop" CTA → badge grant
 *
 * Feature gate: marketplace must be enabled.
 * Auth required.
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Button,
  Input,
  Textarea,
  RadioGroup,
  Radio,
  Spinner,
  Chip,
  Switch,
} from '@heroui/react';
import Building2 from 'lucide-react/icons/building-2';
import MapPin from 'lucide-react/icons/map-pin';
import Clock from 'lucide-react/icons/clock';
import Camera from 'lucide-react/icons/camera';
import PartyPopper from 'lucide-react/icons/party-popper';
import Award from 'lucide-react/icons/award';
import CheckCircle2 from 'lucide-react/icons/circle-check';
import ArrowRight from 'lucide-react/icons/arrow-right';
import ArrowLeft from 'lucide-react/icons/arrow-left';
import Upload from 'lucide-react/icons/upload';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { useAuth, useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { usePageTitle } from '@/hooks';
import { PageMeta } from '@/components/seo/PageMeta';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface SellerProfile {
  id?: number;
  seller_type?: string;
  business_name?: string;
  display_name?: string;
  bio?: string;
  business_registration?: string;
  business_address?: string | Record<string, string>;
  opening_hours?: string | Record<string, { open: string; close: string } | null>;
  avatar_url?: string;
  cover_image_url?: string;
  onboarding_completed_at?: string | null;
}

interface OnboardingStatus {
  has_profile: boolean;
  onboarding_completed: boolean;
  profile: SellerProfile | null;
}

interface CompleteResult extends SellerProfile {
  badge_granted: boolean;
}

type DayKey = 'mon' | 'tue' | 'wed' | 'thu' | 'fri' | 'sat' | 'sun';

interface DayHours {
  open: string;
  close: string;
}

interface AddressFields {
  street: string;
  city: string;
  postal_code: string;
  country: string;
}

// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────

const DAYS: DayKey[] = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
const TOTAL_STEPS = 4;

const defaultHours: DayHours = { open: '09:00', close: '18:00' };

// ─────────────────────────────────────────────────────────────────────────────
// Step indicator component
// ─────────────────────────────────────────────────────────────────────────────

interface StepIndicatorProps {
  current: number;
  labels: string[];
}

function StepIndicator({ current, labels }: StepIndicatorProps) {
  return (
    <div className="flex items-center justify-center gap-0 mb-8">
      {labels.map((label, idx) => {
        const step = idx + 1;
        const isComplete = step < current;
        const isActive = step === current;
        return (
          <div key={step} className="flex items-center">
            <div className="flex flex-col items-center">
              <div
                className={[
                  'w-9 h-9 rounded-full flex items-center justify-center text-sm font-semibold border-2 transition-colors',
                  isComplete
                    ? 'bg-success border-success text-white'
                    : isActive
                      ? 'bg-primary border-primary text-white'
                      : 'bg-[var(--color-surface)] border-default-300 text-default-400',
                ].join(' ')}
              >
                {isComplete ? <CheckCircle2 size={16} /> : step}
              </div>
              <span
                className={[
                  'mt-1 text-xs hidden sm:block max-w-[72px] text-center leading-tight',
                  isActive ? 'text-primary font-medium' : 'text-default-400',
                ].join(' ')}
              >
                {label}
              </span>
            </div>
            {idx < labels.length - 1 && (
              <div
                className={[
                  'h-0.5 w-12 sm:w-16 mx-1 mb-4 transition-colors',
                  isComplete ? 'bg-success' : 'bg-default-200',
                ].join(' ')}
              />
            )}
          </div>
        );
      })}
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Main Component
// ─────────────────────────────────────────────────────────────────────────────

export function MerchantOnboardingPage() {
  const { t } = useTranslation('merchant_onboarding');
  const { isAuthenticated } = useAuth();
  const { showToast } = useToast();
  const { hasFeature, tenantPath } = useTenant();
  const navigate = useNavigate();

  usePageTitle(t('meta.title'));

  // ── State ─────────────────────────────────────────────────────────────────
  const [step, setStep] = useState(1);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [completed, setCompleted] = useState(false);
  const [badgeGranted, setBadgeGranted] = useState(false);

  // Step 1
  const [sellerType, setSellerType] = useState<'private' | 'business'>('business');
  const [businessName, setBusinessName] = useState('');
  const [displayName, setDisplayName] = useState('');
  const [bio, setBio] = useState('');
  const [businessReg, setBusinessReg] = useState('');

  // Step 2 — address
  const [address, setAddress] = useState<AddressFields>({
    street: '',
    city: '',
    postal_code: '',
    country: '',
  });

  // Step 2 — opening hours: key → DayHours | null (null = closed)
  const [openingHours, setOpeningHours] = useState<Record<DayKey, DayHours | null>>({
    mon: { ...defaultHours },
    tue: { ...defaultHours },
    wed: { ...defaultHours },
    thu: { ...defaultHours },
    fri: { ...defaultHours },
    sat: { ...defaultHours },
    sun: null,
  });

  // Step 3
  const [avatarUrl, setAvatarUrl] = useState('');
  const [coverImageUrl, setCoverImageUrl] = useState('');
  const [avatarPreview, setAvatarPreview] = useState('');
  const [coverPreview, setCoverPreview] = useState('');
  const [uploadingAvatar, setUploadingAvatar] = useState(false);
  const [uploadingCover, setUploadingCover] = useState(false);

  const avatarInputRef = useRef<HTMLInputElement>(null);
  const coverInputRef = useRef<HTMLInputElement>(null);

  // ── Auth / feature guard ──────────────────────────────────────────────────
  useEffect(() => {
    if (!isAuthenticated) {
      navigate(tenantPath('/auth/login'), { replace: true });
      return;
    }
    if (!hasFeature('marketplace')) {
      navigate(tenantPath('/marketplace'), { replace: true });
      return;
    }
  }, [isAuthenticated, navigate, tenantPath, hasFeature]);

  // ── Load existing status ──────────────────────────────────────────────────
  useEffect(() => {
    if (!isAuthenticated || !hasFeature('marketplace')) return;

    (async () => {
      try {
        const res = await api.get<OnboardingStatus>('/v2/merchant-onboarding/status');
        const status = res.data;

        if (status.onboarding_completed) {
          setCompleted(true);
          setLoading(false);
          return;
        }

        if (status.profile) {
          const p = status.profile;
          if (p.seller_type === 'private' || p.seller_type === 'business') {
            setSellerType(p.seller_type);
          }
          if (p.business_name) setBusinessName(p.business_name);
          if (p.display_name) setDisplayName(p.display_name);
          if (p.bio) setBio(p.bio);
          if (p.business_registration) setBusinessReg(p.business_registration);
          if (p.avatar_url) {
            setAvatarUrl(p.avatar_url);
            setAvatarPreview(p.avatar_url);
          }
          if (p.cover_image_url) {
            setCoverImageUrl(p.cover_image_url);
            setCoverPreview(p.cover_image_url);
          }
          // Hydrate address
          if (p.business_address) {
            const addr =
              typeof p.business_address === 'string'
                ? (JSON.parse(p.business_address) as AddressFields)
                : (p.business_address as AddressFields);
            setAddress({
              street: addr.street ?? '',
              city: addr.city ?? '',
              postal_code: addr.postal_code ?? '',
              country: addr.country ?? '',
            });
          }
          // Hydrate opening hours
          if (p.opening_hours) {
            const raw =
              typeof p.opening_hours === 'string'
                ? (JSON.parse(p.opening_hours) as Record<string, DayHours | null>)
                : (p.opening_hours as Record<string, DayHours | null>);
            setOpeningHours(prev => {
              const next = { ...prev };
              (Object.keys(raw) as DayKey[]).forEach(d => {
                next[d] = raw[d];
              });
              return next;
            });
          }
        }
      } catch (err) {
        logError('MerchantOnboardingPage: status load', err);
      } finally {
        setLoading(false);
      }
    })();
  }, [isAuthenticated, hasFeature]);

  // ── Step savers ───────────────────────────────────────────────────────────
  const saveStep1 = useCallback(async (): Promise<boolean> => {
    setSaving(true);
    try {
      await api.post('/v2/merchant-onboarding/step-1', {
        seller_type: sellerType,
        business_name: businessName,
        display_name: displayName,
        bio,
        business_registration: businessReg || undefined,
      });
      return true;
    } catch (err) {
      logError('MerchantOnboardingPage: saveStep1', err);
      showToast(t('errors.save_failed'), 'error');
      return false;
    } finally {
      setSaving(false);
    }
  }, [sellerType, businessName, displayName, bio, businessReg, showToast, t]);

  const saveStep2 = useCallback(async (): Promise<boolean> => {
    setSaving(true);
    try {
      await api.post('/v2/merchant-onboarding/step-2', {
        business_address: address,
        opening_hours: openingHours,
      });
      return true;
    } catch (err) {
      logError('MerchantOnboardingPage: saveStep2', err);
      showToast(t('errors.save_failed'), 'error');
      return false;
    } finally {
      setSaving(false);
    }
  }, [address, openingHours, showToast, t]);

  const saveStep3 = useCallback(async (): Promise<boolean> => {
    if (!avatarUrl) return true; // photo is optional — allow skipping
    setSaving(true);
    try {
      await api.post('/v2/merchant-onboarding/step-3', {
        avatar_url: avatarUrl || undefined,
        cover_image_url: coverImageUrl || undefined,
      });
      return true;
    } catch (err) {
      logError('MerchantOnboardingPage: saveStep3', err);
      showToast(t('errors.save_failed'), 'error');
      return false;
    } finally {
      setSaving(false);
    }
  }, [avatarUrl, coverImageUrl, showToast, t]);

  const handleComplete = useCallback(async () => {
    setSaving(true);
    try {
      const res = await api.post<CompleteResult>('/v2/merchant-onboarding/complete', {});
      setBadgeGranted(res.data.badge_granted ?? false);
      setCompleted(true);
    } catch (err) {
      logError('MerchantOnboardingPage: complete', err);
      showToast(t('errors.complete_failed'), 'error');
    } finally {
      setSaving(false);
    }
  }, [showToast, t]);

  // ── Navigation ────────────────────────────────────────────────────────────
  const handleNext = useCallback(async () => {
    let ok = false;
    if (step === 1) ok = await saveStep1();
    else if (step === 2) ok = await saveStep2();
    else if (step === 3) ok = await saveStep3();
    if (ok) setStep(s => s + 1);
  }, [step, saveStep1, saveStep2, saveStep3]);

  const handleBack = useCallback(() => {
    setStep(s => Math.max(1, s - 1));
  }, []);

  // ── File upload helpers ───────────────────────────────────────────────────
  const handleAvatarFile = useCallback(
    async (file: File) => {
      setUploadingAvatar(true);
      try {
        const res = await api.upload<{ url: string }>('/v2/marketplace/seller/profile', file, 'avatar');
        const url = res.data?.url ?? '';
        setAvatarUrl(url);
        setAvatarPreview(URL.createObjectURL(file));
      } catch {
        // Fallback: show URL input instead (graceful degradation)
        setAvatarPreview(URL.createObjectURL(file));
      } finally {
        setUploadingAvatar(false);
      }
    },
    []
  );

  const handleCoverFile = useCallback(
    async (file: File) => {
      setUploadingCover(true);
      try {
        const res = await api.upload<{ url: string }>('/v2/marketplace/seller/profile', file, 'cover_image');
        const url = res.data?.url ?? '';
        setCoverImageUrl(url);
        setCoverPreview(URL.createObjectURL(file));
      } catch {
        setCoverPreview(URL.createObjectURL(file));
      } finally {
        setUploadingCover(false);
      }
    },
    []
  );

  // ── Opening hours helpers ─────────────────────────────────────────────────
  const toggleDay = (day: DayKey) => {
    setOpeningHours(prev => ({
      ...prev,
      [day]: prev[day] === null ? { ...defaultHours } : null,
    }));
  };

  const setDayTime = (day: DayKey, field: 'open' | 'close', value: string) => {
    setOpeningHours(prev => ({
      ...prev,
      [day]: prev[day] ? { ...prev[day]!, [field]: value } : prev[day],
    }));
  };

  // ── Step labels ───────────────────────────────────────────────────────────
  const stepLabels = [
    t('steps.profile'),
    t('steps.location'),
    t('steps.photo'),
    t('steps.complete'),
  ];

  // ── Loading ───────────────────────────────────────────────────────────────
  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-[60vh]">
        <Spinner size="lg" />
      </div>
    );
  }

  // ── Already completed ─────────────────────────────────────────────────────
  if (completed && step < TOTAL_STEPS) {
    return (
      <div className="max-w-xl mx-auto px-4 py-12 text-center space-y-6">
        <PartyPopper size={56} className="mx-auto text-warning" />
        <h1 className="text-3xl font-bold text-[var(--color-text)]">{t('success_title')}</h1>
        {badgeGranted && (
          <Chip color="warning" variant="flat" startContent={<Award size={14} />}>
            {t('badge_earned')}
          </Chip>
        )}
        <Button
          color="primary"
          size="lg"
          endContent={<ArrowRight size={16} />}
          onPress={() => navigate(tenantPath('/marketplace/listings/new'))}
        >
          {t('success_cta')}
        </Button>
      </div>
    );
  }

  // ─────────────────────────────────────────────────────────────────────────
  // Render
  // ─────────────────────────────────────────────────────────────────────────

  return (
    <div className="max-w-2xl mx-auto px-4 py-8">
      <PageMeta
        title={t('meta.title')}
        description={t('meta.description')}
      />

      {/* Hero */}
      <div className="text-center mb-8">
        <Building2 size={44} className="mx-auto mb-3 text-primary" />
        <h1 className="text-2xl sm:text-3xl font-bold text-[var(--color-text)]">
          {t('hero_title')}
        </h1>
        <p className="mt-2 text-default-500 text-sm sm:text-base">
          {t('hero_subtitle')}
        </p>
        <p className="mt-1 text-xs text-default-400">
          {t('step_of', { current: step, total: TOTAL_STEPS })}
        </p>
      </div>

      {/* Step indicator */}
      <StepIndicator current={step} labels={stepLabels} />

      <GlassCard className="p-6">

        {/* ── Step 1: Business Profile ─────────────────────────────────── */}
        {step === 1 && (
          <div className="space-y-5">
            <h2 className="text-lg font-semibold text-[var(--color-text)] flex items-center gap-2">
              <Building2 size={20} className="text-primary" />
              {t('steps.profile')}
            </h2>

            <RadioGroup
              label={t('seller_type')}
              orientation="horizontal"
              value={sellerType}
              onValueChange={v => setSellerType(v as 'private' | 'business')}
            >
              <Radio value="private">{t('private')}</Radio>
              <Radio value="business">{t('business')}</Radio>
            </RadioGroup>

            {sellerType === 'business' && (
              <Input
                label={t('business_name')}
                variant="bordered"
                value={businessName}
                onValueChange={setBusinessName}
                isRequired
              />
            )}

            <Input
              label={t('display_name')}
              variant="bordered"
              value={displayName}
              onValueChange={setDisplayName}
            />

            <Textarea
              label={t('bio')}
              variant="bordered"
              value={bio}
              onValueChange={setBio}
              minRows={3}
              maxRows={6}
            />

            {sellerType === 'business' && (
              <Input
                label={t('business_reg')}
                variant="bordered"
                value={businessReg}
                onValueChange={setBusinessReg}
              />
            )}
          </div>
        )}

        {/* ── Step 2: Location & Hours ─────────────────────────────────── */}
        {step === 2 && (
          <div className="space-y-5">
            <h2 className="text-lg font-semibold text-[var(--color-text)] flex items-center gap-2">
              <MapPin size={20} className="text-primary" />
              {t('steps.location')}
            </h2>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Input
                label={t('address_street')}
                variant="bordered"
                className="sm:col-span-2"
                value={address.street}
                onValueChange={v => setAddress(a => ({ ...a, street: v }))}
              />
              <Input
                label={t('address_city')}
                variant="bordered"
                value={address.city}
                onValueChange={v => setAddress(a => ({ ...a, city: v }))}
              />
              <Input
                label={t('address_postal')}
                variant="bordered"
                value={address.postal_code}
                onValueChange={v => setAddress(a => ({ ...a, postal_code: v }))}
              />
              <Input
                label={t('address_country')}
                variant="bordered"
                className="sm:col-span-2"
                value={address.country}
                onValueChange={v => setAddress(a => ({ ...a, country: v }))}
              />
            </div>

            {/* Opening hours grid */}
            <div>
              <p className="text-sm font-medium text-[var(--color-text)] mb-3 flex items-center gap-2">
                <Clock size={16} className="text-primary" />
                {t('opening_hours')}
              </p>
              <div className="space-y-2">
                {DAYS.map(day => {
                  const hours = openingHours[day];
                  const isOpen = hours !== null;
                  return (
                    <div key={day} className="flex items-center gap-3">
                      <span className="w-20 text-sm text-default-600 shrink-0">
                        {t(`days.${day}`)}
                      </span>
                      <Switch
                        size="sm"
                        isSelected={isOpen}
                        onValueChange={() => toggleDay(day)}
                        aria-label={isOpen ? t('open') : t('closed')}
                      />
                      <span className="text-xs text-default-400 w-14">
                        {isOpen ? t('open') : t('closed')}
                      </span>
                      {isOpen && (
                        <div className="flex items-center gap-2 flex-1">
                          <input
                            type="time"
                            value={hours.open}
                            onChange={e => setDayTime(day, 'open', e.target.value)}
                            className="border border-default-300 rounded-lg px-2 py-1 text-sm bg-[var(--color-surface)] text-[var(--color-text)] w-28"
                          />
                          <span className="text-default-400 text-xs">–</span>
                          <input
                            type="time"
                            value={hours.close}
                            onChange={e => setDayTime(day, 'close', e.target.value)}
                            className="border border-default-300 rounded-lg px-2 py-1 text-sm bg-[var(--color-surface)] text-[var(--color-text)] w-28"
                          />
                        </div>
                      )}
                    </div>
                  );
                })}
              </div>
            </div>
          </div>
        )}

        {/* ── Step 3: Profile Photo ────────────────────────────────────── */}
        {step === 3 && (
          <div className="space-y-6">
            <h2 className="text-lg font-semibold text-[var(--color-text)] flex items-center gap-2">
              <Camera size={20} className="text-primary" />
              {t('steps.photo')}
            </h2>

            {/* Avatar */}
            <div className="flex flex-col items-center gap-4">
              {avatarPreview ? (
                <img
                  src={avatarPreview}
                  alt="avatar"
                  className="w-24 h-24 rounded-full object-cover border-2 border-primary"
                />
              ) : (
                <div className="w-24 h-24 rounded-full bg-default-100 flex items-center justify-center border-2 border-dashed border-default-300">
                  <Camera size={32} className="text-default-400" />
                </div>
              )}
              <input
                ref={avatarInputRef}
                type="file"
                accept="image/*"
                className="hidden"
                onChange={e => {
                  const file = e.target.files?.[0];
                  if (file) handleAvatarFile(file);
                }}
              />
              <Button
                variant="bordered"
                startContent={uploadingAvatar ? <Spinner size="sm" /> : <Upload size={16} />}
                isDisabled={uploadingAvatar}
                onPress={() => avatarInputRef.current?.click()}
              >
                {t('upload_photo')}
              </Button>
              {/* Fallback: manual URL input if upload fails */}
              {!avatarPreview && (
                <Input
                  label="or paste image URL"
                  variant="flat"
                  size="sm"
                  value={avatarUrl}
                  onValueChange={v => {
                    setAvatarUrl(v);
                    setAvatarPreview(v);
                  }}
                  className="max-w-xs"
                />
              )}
            </div>

            {/* Cover image */}
            <div className="space-y-3">
              {coverPreview && (
                <img
                  src={coverPreview}
                  alt="cover"
                  className="w-full h-32 object-cover rounded-xl border border-default-200"
                />
              )}
              <input
                ref={coverInputRef}
                type="file"
                accept="image/*"
                className="hidden"
                onChange={e => {
                  const file = e.target.files?.[0];
                  if (file) handleCoverFile(file);
                }}
              />
              <Button
                variant="flat"
                size="sm"
                startContent={uploadingCover ? <Spinner size="sm" /> : <Upload size={14} />}
                isDisabled={uploadingCover}
                onPress={() => coverInputRef.current?.click()}
              >
                {t('upload_cover')}
              </Button>
            </div>
          </div>
        )}

        {/* ── Step 4: Complete ─────────────────────────────────────────── */}
        {step === 4 && !completed && (
          <div className="space-y-5">
            <h2 className="text-lg font-semibold text-[var(--color-text)]">
              {t('complete_title')}
            </h2>
            <p className="text-sm text-default-500">{t('complete_summary')}</p>

            {/* Summary card */}
            <div className="rounded-xl border border-default-200 p-4 space-y-2 text-sm">
              {displayName && (
                <div className="flex gap-2">
                  <span className="font-medium text-default-600 w-32 shrink-0">{t('display_name')}:</span>
                  <span className="text-[var(--color-text)]">{displayName}</span>
                </div>
              )}
              {businessName && (
                <div className="flex gap-2">
                  <span className="font-medium text-default-600 w-32 shrink-0">{t('business_name')}:</span>
                  <span className="text-[var(--color-text)]">{businessName}</span>
                </div>
              )}
              {bio && (
                <div className="flex gap-2">
                  <span className="font-medium text-default-600 w-32 shrink-0">{t('bio')}:</span>
                  <span className="text-[var(--color-text)] line-clamp-2">{bio}</span>
                </div>
              )}
              {address.city && (
                <div className="flex gap-2">
                  <span className="font-medium text-default-600 w-32 shrink-0">{t('address_city')}:</span>
                  <span className="text-[var(--color-text)]">{address.city}, {address.country}</span>
                </div>
              )}
              {avatarPreview && (
                <div className="flex items-center gap-2 pt-1">
                  <img
                    src={avatarPreview}
                    alt="avatar"
                    className="w-10 h-10 rounded-full object-cover border border-default-200"
                  />
                </div>
              )}
            </div>

            <Button
              color="primary"
              size="lg"
              className="w-full font-semibold"
              isLoading={saving}
              isDisabled={saving}
              onPress={handleComplete}
            >
              {saving ? t('launching') : t('launch')}
            </Button>
          </div>
        )}

        {/* ── Step 4: Success ──────────────────────────────────────────── */}
        {step === 4 && completed && (
          <div className="text-center space-y-5 py-4">
            <PartyPopper size={52} className="mx-auto text-warning" />
            <h2 className="text-2xl font-bold text-[var(--color-text)]">
              {t('success_title')}
            </h2>
            {badgeGranted && (
              <Chip color="warning" variant="flat" startContent={<Award size={14} />} size="lg">
                {t('badge_earned')}
              </Chip>
            )}
            <Button
              color="primary"
              size="lg"
              endContent={<ArrowRight size={16} />}
              onPress={() => navigate(tenantPath('/marketplace/listings/new'))}
            >
              {t('success_cta')}
            </Button>
          </div>
        )}

        {/* ── Navigation buttons ───────────────────────────────────────── */}
        {step < 4 && (
          <div className="flex justify-between mt-6 pt-4 border-t border-default-100">
            <Button
              variant="flat"
              startContent={<ArrowLeft size={16} />}
              isDisabled={step === 1 || saving}
              onPress={handleBack}
            >
              {t('back')}
            </Button>
            <Button
              color="primary"
              endContent={saving ? undefined : <ArrowRight size={16} />}
              isLoading={saving}
              isDisabled={saving}
              onPress={handleNext}
            >
              {saving ? t('saving') : t('next')}
            </Button>
          </div>
        )}
      </GlassCard>
    </div>
  );
}
