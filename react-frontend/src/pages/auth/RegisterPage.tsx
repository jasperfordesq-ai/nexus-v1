// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Registration Page
 *
 * Step-by-step registration form for better mobile UX:
 * - Step 1: Community & Profile Type
 * - Step 2: Personal Details (name, location, phone)
 * - Step 3: Account Setup (email, password)
 * - Step 4: Terms & Consent
 *
 * Desktop shows all fields, mobile shows one step at a time
 */

import { useState, useEffect, useRef, useCallback, type FormEvent } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { Button, Input, Checkbox, Divider, Select, SelectItem, Progress } from '@heroui/react';
import { motion, AnimatePresence } from 'framer-motion';
import {
  User,
  Mail,
  Lock,
  Eye,
  EyeOff,
  ArrowLeft,
  ArrowRight,
  Loader2,
  Building2,
  Phone,
  Check,
  X,
  ChevronLeft,
  MailCheck,
  ShieldCheck,
  Ticket,
  Clock,
  Users,
} from 'lucide-react';
import { useTranslation, Trans } from 'react-i18next';
import { useAuth, useTenant } from '@/contexts';
import type { RegisterResult } from '@/contexts/AuthContext';
import { usePageTitle } from '@/hooks';
import { GlassCard } from '@/components/ui';
import { PlaceAutocompleteInput } from '@/components/location';
import { api, tokenManager } from '@/lib/api';
import { PASSWORD_REQUIREMENTS, isPasswordValid, getPasswordStrength } from '@/lib/validation';

interface Tenant {
  id: number;
  name: string;
  slug: string;
  domain?: string;
  tagline?: string;
  logo_url?: string;
}

type ProfileType = 'individual' | 'organisation';

const STEPS = [
  { id: 1, title: 'Community', shortTitle: 'Community' },
  { id: 2, title: 'Your Details', shortTitle: 'Details' },
  { id: 3, title: 'Account', shortTitle: 'Account' },
  { id: 4, title: 'Terms', shortTitle: 'Terms' },
];

export function RegisterPage() {
  const { t } = useTranslation('auth');
  usePageTitle('Create Account');
  const navigate = useNavigate();
  const { register, isAuthenticated, isLoading, error, clearError } = useAuth();
  const { tenant, tenantSlug, tenantPath } = useTenant();
  const [searchParams] = useSearchParams();

  // Step state (1-4)
  const [currentStep, setCurrentStep] = useState(1);
  const [isMobile, setIsMobile] = useState(false);

  // Bot protection
  const [formStartTime] = useState(() => Date.now());
  const honeypotRef = useRef<HTMLInputElement>(null);

  // Tenant state
  const [tenants, setTenants] = useState<Tenant[]>([]);
  const [selectedTenantId, setSelectedTenantId] = useState<string>('');
  const [tenantsLoading, setTenantsLoading] = useState(true);

  // Form state - Basic
  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirm, setPasswordConfirm] = useState('');
  const [showPassword, setShowPassword] = useState(false);

  // Form state - Profile
  const [profileType, setProfileType] = useState<ProfileType>('individual');
  const [organizationName, setOrganizationName] = useState('');

  // Form state - Contact
  const [location, setLocation] = useState('');
  const [latitude, setLatitude] = useState<number | undefined>();
  const [longitude, setLongitude] = useState<number | undefined>();
  const [phone, setPhone] = useState('');

  // E.164 phone validation (optional field — only validate if user enters something)
  const isPhoneValid = (value: string) => {
    if (!value.trim()) return true; // Empty is fine (optional field)
    return /^\+[1-9]\d{1,14}$/.test(value.replace(/[\s\-()]/g, ''));
  };
  const phoneError = phone.trim() && !isPhoneValid(phone) ? t('register.phone_error', { defaultValue: 'Enter a valid international number (e.g. +1 555 123 4567)' }) : '';

  // Form state - Consents
  const [termsAccepted, setTermsAccepted] = useState(false);
  const [newsletterOptIn, setNewsletterOptIn] = useState(false);

  // Invite code state (for invite_only tenants)
  const [inviteCode, setInviteCode] = useState('');
  const [inviteCodeValid, setInviteCodeValid] = useState<boolean | null>(null);
  const [inviteCodeChecking, setInviteCodeChecking] = useState(false);
  const [requiresInviteCode, setRequiresInviteCode] = useState(false);

  // Post-registration pending state (verification/approval required)
  const [pendingResult, setPendingResult] = useState<RegisterResult | null>(null);

  // Detect mobile viewport
  useEffect(() => {
    const checkMobile = () => setIsMobile(window.innerWidth < 640);
    checkMobile();
    window.addEventListener('resize', checkMobile);
    return () => window.removeEventListener('resize', checkMobile);
  }, []);

  // Fetch available tenants on mount, with ?tenant= hint support (TRS-001 Phase 0)
  useEffect(() => {
    const fetchTenants = async () => {
      try {
        const response = await api.get<Tenant[]>('/v2/tenants', { skipAuth: true, skipTenant: true });
        if (response.success && response.data) {
          setTenants(response.data);

          // Priority: TenantContext (custom domain) > URL slug > ?tenant= > auto-select single
          const tenantHint = tenantSlug || searchParams.get('tenant');
          const contextMatch = tenant?.id
            ? response.data.find((t) => t.id === tenant.id)
            : null;
          const hintMatch = tenantHint
            ? response.data.find((t) => t.slug === tenantHint)
            : null;

          const match = contextMatch || hintMatch;
          if (match) {
            setSelectedTenantId(String(match.id));
            tokenManager.setTenantId(match.id);
          } else if (response.data.length === 1) {
            setSelectedTenantId(String(response.data[0].id));
            tokenManager.setTenantId(response.data[0].id);
          }
        }
      } catch (err) {
        console.error('[RegisterPage] Failed to fetch tenants:', err);
      } finally {
        setTenantsLoading(false);
      }
    };
    fetchTenants();
  }, [tenantSlug, searchParams, tenant?.id]);

  // Fetch registration info when tenant is resolved (to know if invite code is required)
  useEffect(() => {
    const effectiveTenantId = selectedTenantId || (tenant?.id ? String(tenant.id) : '');
    if (!effectiveTenantId) return;

    const fetchRegInfo = async () => {
      try {
        const res = await api.get<{ registration_mode: string; requires_invite_code: boolean }>('/v2/auth/registration-info', { skipAuth: true });
        if (res.success && res.data) {
          setRequiresInviteCode(res.data.requires_invite_code);
        }
      } catch {
        // Non-critical — default to no invite code required
      }
    };
    fetchRegInfo();
  }, [selectedTenantId, tenant?.id]);

  // Handle tenant selection
  const handleTenantChange = useCallback((keys: unknown) => {
    const selectedKeys = keys as Set<string>;
    const tenantId = Array.from(selectedKeys)[0] || '';
    setSelectedTenantId(tenantId);
    if (tenantId) {
      tokenManager.setTenantId(tenantId);
    }
  }, []);

  // Handle profile type selection
  const handleProfileTypeChange = useCallback((keys: unknown) => {
    const selectedKeys = keys as Set<string>;
    const type = (Array.from(selectedKeys)[0] as ProfileType) || 'individual';
    setProfileType(type);
    if (type === 'individual') {
      setOrganizationName('');
    }
  }, []);

  // Redirect after successful registration
  useEffect(() => {
    if (isAuthenticated) {
      navigate(tenantPath('/dashboard'), { replace: true });
    }
  }, [isAuthenticated, navigate, tenantPath]);

  // Clear error when form changes
  useEffect(() => {
    if (error) {
      clearError();
    }
  }, [firstName, lastName, email, password, passwordConfirm, location, phone]); // eslint-disable-line react-hooks/exhaustive-deps

  // Validate invite code (debounced on blur)
  const validateInviteCode = useCallback(async () => {
    if (!inviteCode.trim() || inviteCode.trim().length < 4) {
      setInviteCodeValid(null);
      return;
    }
    setInviteCodeChecking(true);
    try {
      const res = await api.post<{ valid: boolean; reason: string | null }>('/v2/auth/validate-invite', { code: inviteCode.trim().toUpperCase() }, { skipAuth: true });
      if (res.success && res.data) {
        setInviteCodeValid(res.data.valid);
      }
    } catch {
      setInviteCodeValid(null);
    } finally {
      setInviteCodeChecking(false);
    }
  }, [inviteCode]);

  // Validation for each step
  // tenant?.id means TenantContext already resolved the tenant (custom domain or slug route)
  const tenantSelected = !!tenant?.id || tenants.length === 0 || tenants.length === 1 || !!selectedTenantId;
  const isStep1Valid = tenantSelected && (!requiresInviteCode || inviteCodeValid === true);
  const isStep2Valid =
    firstName.trim() &&
    lastName.trim() &&
    (profileType === 'individual' || organizationName.trim()) &&
    isPhoneValid(phone);
  const isStep3Valid =
    email.trim() &&
    password.trim() &&
    passwordConfirm.trim() &&
    isPasswordValid(password) &&
    password === passwordConfirm;
  const isStep4Valid = termsAccepted;

  const canProceed = useCallback(() => {
    switch (currentStep) {
      case 1:
        return isStep1Valid;
      case 2:
        return isStep2Valid;
      case 3:
        return isStep3Valid;
      case 4:
        return isStep4Valid;
      default:
        return false;
    }
  }, [currentStep, isStep1Valid, isStep2Valid, isStep3Valid, isStep4Valid]);

  const handleNext = useCallback(() => {
    if (currentStep < 4 && canProceed()) {
      setCurrentStep(currentStep + 1);
    }
  }, [currentStep, canProceed]);

  const handleBack = useCallback(() => {
    if (currentStep > 1) {
      setCurrentStep(currentStep - 1);
    }
  }, [currentStep]);

  const handleSubmit = useCallback(async (e: FormEvent) => {
    e.preventDefault();

    // Bot protection checks
    const honeypotValue = honeypotRef.current?.value;
    if (honeypotValue) {
      // Bot detected - silently fail
      return;
    }

    const timeTaken = Date.now() - formStartTime;
    if (timeTaken < 5000) {
      // Form submitted too fast (< 5 seconds) - likely a bot.
      // Intentionally silent: showing an error would tell bots exactly what to adjust.
      clearError();
      return;
    }

    // Password validation
    if (!isPasswordValid(password)) {
      return;
    }

    if (password !== passwordConfirm) {
      return;
    }

    // Get selected tenant — fall back to TenantContext (custom domain) if no explicit selection
    const selectedTenant = tenants.find((t) => String(t.id) === selectedTenantId);
    const tenantId = selectedTenant?.id || parseInt(selectedTenantId) || tenant?.id || undefined;

    const result = await register({
      first_name: firstName,
      last_name: lastName,
      email,
      password,
      password_confirmation: passwordConfirm,
      tenant_id: tenantId,
      profile_type: profileType,
      organization_name: profileType === 'organisation' ? organizationName : undefined,
      location: location || undefined,
      latitude,
      longitude,
      phone: phone || undefined,
      terms_accepted: termsAccepted,
      newsletter_opt_in: newsletterOptIn,
      invite_code: requiresInviteCode ? inviteCode.trim().toUpperCase() : undefined,
    });

    if (result.success) {
      // If verification, approval, or waitlist is required, show pending screen
      if (result.requiresVerification || result.requiresApproval || result.requiresWaitlist) {
        setPendingResult(result);
        return;
      }
      // No gates — redirect to dashboard (fully authenticated)
      navigate(tenantPath('/dashboard'), { replace: true });
    }
  }, [
    formStartTime, clearError, password, passwordConfirm, tenants, selectedTenantId,
    tenant?.id, register, firstName, lastName, email, profileType, organizationName,
    location, latitude, longitude, phone, termsAccepted, newsletterOptIn,
    requiresInviteCode, inviteCode, navigate, tenantPath,
  ]);

  const passwordValid = isPasswordValid(password);
  const passwordsMatch = password === passwordConfirm;

  const isFormValid =
    firstName.trim() &&
    lastName.trim() &&
    email.trim() &&
    password.trim() &&
    passwordConfirm.trim() &&
    termsAccepted &&
    passwordValid &&
    passwordsMatch &&
    (profileType === 'individual' || organizationName.trim()) &&
    (tenants.length === 0 || !!selectedTenantId || !!tenant?.id) &&
    (!requiresInviteCode || inviteCodeValid === true);

  // Step progress percentage
  const progressPercent = (currentStep / STEPS.length) * 100;

  // Render step content
  const renderStepContent = (step: number) => {
    switch (step) {
      case 1:
        return (
          <div className="space-y-4">
            {/* Tenant Selector - Only show if multiple tenants AND tenant not already known from context */}
            {!tenantsLoading && tenants.length > 1 && !tenant?.id && (
              <Select
                label={t('register.community_label')}
                placeholder={t('register.community_placeholder')}
                selectedKeys={selectedTenantId ? new Set([selectedTenantId]) : new Set()}
                onSelectionChange={handleTenantChange}
                startContent={<Building2 className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
                isRequired
                classNames={{
                  trigger:
                    'bg-white/90 dark:bg-white/10 backdrop-blur-xl border border-gray-200 dark:border-white/10',
                  label: 'text-theme-muted',
                  value: 'text-theme-primary',
                  popoverContent:
                    'bg-content1 border border-theme-default',
                }}
              >
                {tenants.map((t) => (
                  <SelectItem
                    key={String(t.id)}
                    textValue={t.name}
                    classNames={{
                      base: 'text-gray-900 dark:text-white data-[hover=true]:bg-gray-100 dark:data-[hover=true]:bg-white/10',
                    }}
                  >
                    <div className="flex flex-col">
                      <span className="text-gray-900 dark:text-white">{t.name}</span>
                      {t.tagline && (
                        <span className="text-gray-500 dark:text-gray-400 text-xs">
                          {t.tagline}
                        </span>
                      )}
                    </div>
                  </SelectItem>
                ))}
              </Select>
            )}

            {/* Show auto-detected community (custom domain or context-resolved tenant) */}
            {tenant?.id && (
              <div className="p-3 rounded-xl bg-white/90 dark:bg-white/10 backdrop-blur-xl border border-gray-200 dark:border-white/10">
                <div className="flex items-center gap-3">
                  <Building2 className="w-5 h-5 text-indigo-500 dark:text-indigo-400" aria-hidden="true" />
                  <div>
                    <p className="text-gray-900 dark:text-white font-medium">{tenant.name}</p>
                    {tenant.tagline && (
                      <p className="text-gray-500 dark:text-gray-400 text-xs">{tenant.tagline}</p>
                    )}
                  </div>
                </div>
              </div>
            )}

            {/* Show selected tenant if only one (and context not already set) */}
            {!tenant?.id && !tenantsLoading && tenants.length === 1 && (
              <div className="p-3 rounded-xl bg-white/90 dark:bg-white/10 backdrop-blur-xl border border-gray-200 dark:border-white/10">
                <div className="flex items-center gap-3">
                  <Building2 className="w-5 h-5 text-indigo-500 dark:text-indigo-400" aria-hidden="true" />
                  <div>
                    <p className="text-gray-900 dark:text-white font-medium">{tenants[0].name}</p>
                    {tenants[0].tagline && (
                      <p className="text-gray-500 dark:text-gray-400 text-xs">
                        {tenants[0].tagline}
                      </p>
                    )}
                  </div>
                </div>
              </div>
            )}

            {/* No tenants message */}
            {!tenantsLoading && tenants.length === 0 && !tenant?.id && (
              <div className="p-3 rounded-xl bg-white/90 dark:bg-white/10 backdrop-blur-xl border border-gray-200 dark:border-white/10 text-center">
                <p className="text-theme-muted text-sm">
                  {t('register.joining', { name: tenant?.name || 'NEXUS' })}
                </p>
              </div>
            )}

            {/* Profile Type */}
            <Select
              label={t('register.profile_type_label')}
              placeholder={t('register.profile_type_placeholder')}
              selectedKeys={new Set([profileType])}
              onSelectionChange={handleProfileTypeChange}
              isRequired
              classNames={{
                trigger:
                  'bg-white/90 dark:bg-white/10 backdrop-blur-xl border border-gray-200 dark:border-white/10',
                label: 'text-theme-muted',
                value: 'text-theme-primary',
                popoverContent:
                  'bg-content1 border border-theme-default',
              }}
            >
              <SelectItem
                key="individual"
                textValue={t('register.type_individual')}
                classNames={{
                  base: 'text-gray-900 dark:text-white data-[hover=true]:bg-gray-100 dark:data-[hover=true]:bg-white/10',
                }}
              >
                {t('register.type_individual')}
              </SelectItem>
              <SelectItem
                key="organisation"
                textValue={t('register.type_organisation')}
                classNames={{
                  base: 'text-gray-900 dark:text-white data-[hover=true]:bg-gray-100 dark:data-[hover=true]:bg-white/10',
                }}
              >
                {t('register.type_organisation')}
              </SelectItem>
            </Select>

            {/* Organisation Name - Only show for organisations */}
            {profileType === 'organisation' && (
              <Input
                type="text"
                label={t('register.org_name_label')}
                placeholder={t('register.org_name_placeholder')}
                value={organizationName}
                onChange={(e) => setOrganizationName(e.target.value)}
                startContent={<Building2 className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
                isRequired
                classNames={{
                  inputWrapper:
                    'glass-card backdrop-blur-lg border-glass-border hover:border-glass-border-hover',
                  label: 'text-theme-muted',
                  input: 'text-theme-primary placeholder:text-theme-subtle',
                }}
              />
            )}

            {/* Invite Code - Only show for invite_only tenants */}
            {requiresInviteCode && (
              <Input
                type="text"
                label={t('register.invite_code_label', { defaultValue: 'Invite Code' })}
                placeholder={t('register.invite_code_placeholder', { defaultValue: 'Enter your invite code' })}
                value={inviteCode}
                onChange={(e) => {
                  setInviteCode(e.target.value.toUpperCase());
                  setInviteCodeValid(null);
                }}
                onBlur={validateInviteCode}
                startContent={<Ticket className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
                endContent={
                  inviteCodeChecking ? (
                    <Loader2 className="w-4 h-4 animate-spin text-theme-subtle" aria-hidden="true" />
                  ) : inviteCodeValid === true ? (
                    <Check className="w-4 h-4 text-emerald-500" aria-hidden="true" />
                  ) : inviteCodeValid === false ? (
                    <X className="w-4 h-4 text-red-500" aria-hidden="true" />
                  ) : null
                }
                isRequired
                isInvalid={inviteCodeValid === false}
                errorMessage={inviteCodeValid === false ? t('register.invite_code_invalid', { defaultValue: 'This invite code is invalid or has been used' }) : ''}
                description={inviteCodeValid !== false ? t('register.invite_code_description', { defaultValue: 'You need an invite code from a community administrator to register' }) : undefined}
                classNames={{
                  inputWrapper:
                    'glass-card backdrop-blur-lg border-glass-border hover:border-glass-border-hover',
                  label: 'text-theme-muted',
                  input: 'text-theme-primary placeholder:text-theme-subtle uppercase tracking-widest',
                  description: 'text-theme-subtle text-xs',
                }}
              />
            )}
          </div>
        );

      case 2:
        return (
          <div className="space-y-4">
            {/* Name Fields */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Input
                type="text"
                label={t('register.first_name_label')}
                placeholder={t('register.first_name_placeholder')}
                value={firstName}
                onChange={(e) => setFirstName(e.target.value)}
                isRequired
                autoComplete="given-name"
                classNames={{
                  inputWrapper:
                    'glass-card backdrop-blur-lg border-glass-border hover:border-glass-border-hover',
                  label: 'text-theme-muted',
                  input: 'text-theme-primary placeholder:text-theme-subtle',
                }}
              />

              <Input
                type="text"
                label={t('register.last_name_label')}
                placeholder={t('register.last_name_placeholder')}
                value={lastName}
                onChange={(e) => setLastName(e.target.value)}
                isRequired
                autoComplete="family-name"
                description={
                  profileType === 'organisation' ? t('register.admin_only_note') : undefined
                }
                classNames={{
                  inputWrapper:
                    'glass-card backdrop-blur-lg border-glass-border hover:border-glass-border-hover',
                  label: 'text-theme-muted',
                  input: 'text-theme-primary placeholder:text-theme-subtle',
                  description: 'text-theme-subtle text-xs',
                }}
              />
            </div>

            {/* Location */}
            <PlaceAutocompleteInput
              label={t('register.location_label')}
              placeholder={t('register.location_placeholder')}
              value={location}
              onChange={(val) => setLocation(val)}
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
                inputWrapper: 'glass-card backdrop-blur-lg border-glass-border hover:border-glass-border-hover',
                label: 'text-theme-muted',
                input: 'text-theme-primary placeholder:text-theme-subtle',
              }}
            />

            {/* Phone */}
            <Input
              type="tel"
              label={t('register.phone_label')}
              placeholder={t('register.phone_placeholder')}
              value={phone}
              onChange={(e) => setPhone(e.target.value)}
              startContent={<Phone className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
              autoComplete="tel"
              isInvalid={!!phoneError}
              errorMessage={phoneError}
              description={phoneError ? undefined : t('register.phone_admin_note')}
              classNames={{
                inputWrapper:
                  'glass-card backdrop-blur-lg border-glass-border hover:border-glass-border-hover',
                label: 'text-theme-muted',
                input: 'text-theme-primary placeholder:text-theme-subtle',
                description: 'text-theme-subtle text-xs',
              }}
            />
          </div>
        );

      case 3:
        return (
          <div className="space-y-4">
            {/* Email */}
            <Input
              type="email"
              label={t('register.email_label')}
              placeholder={t('register.email_placeholder')}
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              startContent={<Mail className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
              isRequired
              autoComplete="email"
              classNames={{
                inputWrapper:
                  'glass-card backdrop-blur-lg border-glass-border hover:border-glass-border-hover',
                label: 'text-theme-muted',
                input: 'text-theme-primary placeholder:text-theme-subtle',
              }}
            />

            {/* Password */}
            <div>
              <Input
                type={showPassword ? 'text' : 'password'}
                label={t('register.password_label')}
                placeholder={t('register.password_placeholder')}
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                startContent={<Lock className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
                endContent={
                  <Button
                    isIconOnly
                    size="sm"
                    variant="light"
                    className="min-w-0 w-auto h-auto p-0 text-theme-subtle"
                    onPress={() => setShowPassword(!showPassword)}
                    aria-label={showPassword ? 'Hide password' : 'Show password'}
                  >
                    {showPassword ? (
                      <EyeOff className="w-4 h-4" aria-hidden="true" />
                    ) : (
                      <Eye className="w-4 h-4" aria-hidden="true" />
                    )}
                  </Button>
                }
                isRequired
                autoComplete="new-password"
                classNames={{
                  inputWrapper:
                    'glass-card backdrop-blur-lg border-glass-border hover:border-glass-border-hover',
                  label: 'text-theme-muted',
                  input: 'text-theme-primary placeholder:text-theme-subtle',
                }}
              />

              {/* Password strength indicator */}
              {password && (
                <div className="mt-2 space-y-2">
                  <Progress
                    value={getPasswordStrength(password)}
                    color={
                      getPasswordStrength(password) < 40
                        ? 'danger'
                        : getPasswordStrength(password) < 80
                          ? 'warning'
                          : 'success'
                    }
                    size="sm"
                    aria-label="Password strength"
                  />
                  <ul className="space-y-1 text-xs">
                    {PASSWORD_REQUIREMENTS.map((req) => {
                      const passed = req.test(password);
                      return (
                        <li
                          key={req.id}
                          className={`flex items-center gap-1.5 ${
                            passed
                              ? 'text-emerald-500 dark:text-emerald-400'
                              : 'text-theme-subtle'
                          }`}
                        >
                          {passed ? (
                            <Check className="w-3 h-3" aria-hidden="true" />
                          ) : (
                            <X className="w-3 h-3" aria-hidden="true" />
                          )}
                          {req.label}
                        </li>
                      );
                    })}
                  </ul>
                </div>
              )}
            </div>

            {/* Confirm Password */}
            <Input
              type={showPassword ? 'text' : 'password'}
              label={t('register.confirm_password_label')}
              placeholder={t('register.confirm_password_placeholder')}
              value={passwordConfirm}
              onChange={(e) => setPasswordConfirm(e.target.value)}
              startContent={<Lock className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
              isRequired
              autoComplete="new-password"
              isInvalid={passwordConfirm.length > 0 && !passwordsMatch}
              errorMessage={
                passwordConfirm.length > 0 && !passwordsMatch ? t('register.passwords_must_match') : ''
              }
              classNames={{
                inputWrapper:
                  'glass-card backdrop-blur-lg border-glass-border hover:border-glass-border-hover',
                label: 'text-theme-muted',
                input: 'text-theme-primary placeholder:text-theme-subtle',
              }}
            />
          </div>
        );

      case 4:
        return (
          <div className="space-y-4">
            {/* Consents */}
            <div className="space-y-3">
              <Checkbox
                isSelected={termsAccepted}
                onValueChange={setTermsAccepted}
                size="sm"
                classNames={{
                  label: 'text-theme-muted text-sm',
                }}
              >
                <span>
                  <Trans
                    i18nKey="register.terms_agreement"
                    t={t}
                    components={{
                      termsLink: <Link to={tenantPath('/terms')} className="text-indigo-600 dark:text-indigo-400 hover:underline" />,
                      privacyLink: <Link to={tenantPath('/privacy')} className="text-indigo-600 dark:text-indigo-400 hover:underline" />,
                    }}
                  />
                </span>
              </Checkbox>

              <Checkbox
                isSelected={newsletterOptIn}
                onValueChange={setNewsletterOptIn}
                size="sm"
                classNames={{
                  label: 'text-theme-muted text-sm',
                }}
              >
                {t('register.newsletter_opt_in')}
              </Checkbox>
            </div>

            {/* Data Protection Notice */}
            <div className="p-3 rounded-lg bg-gray-100 dark:bg-white/5 border border-gray-200 dark:border-white/10 text-xs text-theme-muted space-y-2">
              <p className="font-medium text-theme-primary">{t('register.data_protection_title')}</p>
              <p>
                {t('register.data_protection_body')}
              </p>
              <p>
                <Link
                  to={tenantPath('/privacy')}
                  className="text-indigo-600 dark:text-indigo-400 hover:underline"
                >
                  {t('register.data_protection_privacy_link')}
                </Link>
              </p>
            </div>
          </div>
        );

      default:
        return null;
    }
  };

  // Desktop: Show all fields at once
  // Mobile: Show step-by-step
  const renderForm = () => {
    if (!isMobile) {
      // Desktop view - all fields visible
      return (
        <form onSubmit={handleSubmit} className="space-y-4">
          {/* Honeypot - hidden from users, visible to bots */}
          <div className="hidden" aria-hidden="true">
            <label htmlFor="website">Website</label>
            <input
              ref={honeypotRef}
              type="text"
              name="website"
              id="website"
              tabIndex={-1}
              autoComplete="off"
            />
          </div>

          {/* All steps content */}
          {renderStepContent(1)}
          {renderStepContent(2)}
          {renderStepContent(3)}
          {renderStepContent(4)}

          <Button
            type="submit"
            isLoading={isLoading}
            isDisabled={!isFormValid}
            className="w-full bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-medium"
            size="lg"
            spinner={<Loader2 className="w-4 h-4 animate-spin" aria-hidden="true" />}
          >
            {t('register.submit')}
          </Button>
        </form>
      );
    }

    // Mobile view - step by step
    return (
      <form onSubmit={handleSubmit} className="space-y-4">
        {/* Honeypot - hidden from users, visible to bots */}
        <div className="hidden" aria-hidden="true">
          <label htmlFor="website">Website</label>
          <input
            ref={honeypotRef}
            type="text"
            name="website"
            id="website"
            tabIndex={-1}
            autoComplete="off"
          />
        </div>

        {/* Step indicator */}
        <div className="mb-6">
          <div className="flex justify-between items-center mb-2">
            <span className="text-sm font-medium text-theme-primary">
              {t('register.step_indicator', { step: currentStep, total: STEPS.length })}
            </span>
            <span className="text-sm text-theme-muted">{t(`register.step_${['community','details','account','terms'][currentStep - 1]}`)}</span>
          </div>
          <Progress
            value={progressPercent}
            color="primary"
            size="sm"
            aria-label={`Registration progress: step ${currentStep} of ${STEPS.length}`}
          />
          {/* Step dots */}
          <div className="flex justify-between mt-2">
            {STEPS.map((step) => (
              <Button
                key={step.id}
                variant="light"
                size="sm"
                className={`flex flex-col items-center min-w-0 h-auto p-1 gap-0 ${
                  step.id === currentStep
                    ? 'text-indigo-600 dark:text-indigo-400'
                    : step.id < currentStep
                      ? 'text-theme-muted'
                      : 'text-theme-subtle'
                }`}
                onPress={() => step.id < currentStep && setCurrentStep(step.id)}
                isDisabled={step.id > currentStep}
                aria-label={`Go to step ${step.id}: ${t(`register.step_${['community','details','account','terms'][step.id - 1]}`)}`}
              >
                <div
                  className={`w-2.5 h-2.5 rounded-full ${
                    step.id === currentStep
                      ? 'bg-indigo-600 dark:bg-indigo-400'
                      : step.id < currentStep
                        ? 'bg-emerald-500'
                        : 'bg-theme-elevated'
                  }`}
                />
                <span className="text-[10px] mt-1 hidden xs:block">{t(`register.step_${['community','details','account','terms'][step.id - 1]}`)}</span>
              </Button>
            ))}
          </div>
        </div>

        {/* Animated step content */}
        <AnimatePresence mode="wait">
          <motion.div
            key={currentStep}
            initial={{ opacity: 0, x: 20 }}
            animate={{ opacity: 1, x: 0 }}
            exit={{ opacity: 0, x: -20 }}
            transition={{ duration: 0.2 }}
          >
            {renderStepContent(currentStep)}
          </motion.div>
        </AnimatePresence>

        {/* Navigation buttons */}
        <div className="flex gap-3 pt-4">
          {currentStep > 1 && (
            <Button
              type="button"
              variant="flat"
              onPress={handleBack}
              className="flex-1 bg-theme-elevated text-theme-secondary"
              startContent={<ChevronLeft className="w-4 h-4" aria-hidden="true" />}
            >
              {t('register.back')}
            </Button>
          )}

          {currentStep < 4 ? (
            <Button
              type="button"
              onPress={handleNext}
              isDisabled={!canProceed()}
              className={`${currentStep === 1 ? 'w-full' : 'flex-1'} bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-medium`}
              endContent={<ArrowRight className="w-4 h-4" aria-hidden="true" />}
            >
              {t('register.continue')}
            </Button>
          ) : (
            <Button
              type="submit"
              isLoading={isLoading}
              isDisabled={!isFormValid}
              className="flex-1 bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-medium"
              spinner={<Loader2 className="w-4 h-4 animate-spin" aria-hidden="true" />}
            >
              {t('register.submit')}
            </Button>
          )}
        </div>
      </form>
    );
  };

  // ── Pending Registration Success Screen ──────────────────────────────────
  if (pendingResult) {
    return (
      <div className="min-h-screen flex items-center justify-center p-4 py-12">
        <div className="fixed inset-0 overflow-hidden pointer-events-none">
          <div className="blob blob-indigo" />
          <div className="blob blob-purple" />
          <div className="blob blob-cyan" />
        </div>

        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.5 }}
          className="w-full max-w-md relative z-10"
        >
          <GlassCard className="p-6 sm:p-8">
            <div className="text-center">
              <motion.div
                initial={{ scale: 0.8 }}
                animate={{ scale: 1 }}
                className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-emerald-500/20 to-teal-500/20 mb-4"
              >
                {pendingResult.requiresWaitlist ? (
                  <Users className="w-8 h-8 text-indigo-500 dark:text-indigo-400" />
                ) : pendingResult.requiresVerification ? (
                  <MailCheck className="w-8 h-8 text-emerald-500 dark:text-emerald-400" />
                ) : (
                  <ShieldCheck className="w-8 h-8 text-emerald-500 dark:text-emerald-400" />
                )}
              </motion.div>

              <h1 className="text-xl sm:text-2xl font-bold text-theme-primary mb-2">
                {pendingResult.requiresWaitlist ? t('register.waitlist_title', { defaultValue: "You're on the waitlist!" }) : t('register.success_title', { defaultValue: 'Registration Successful!' })}
              </h1>

              <div className="space-y-3 mt-4 text-left">
                {pendingResult.requiresWaitlist && (
                  <div className="p-3 rounded-xl bg-indigo-500/10 border border-indigo-500/20 text-sm">
                    <div className="flex items-start gap-3">
                      <Clock className="w-5 h-5 text-indigo-500 dark:text-indigo-400 flex-shrink-0 mt-0.5" />
                      <div>
                        <p className="font-medium text-indigo-600 dark:text-indigo-400">
                          {pendingResult.waitlistPosition
                            ? t('register.waitlist_position', { defaultValue: 'Position #{{position}} on the waitlist', position: pendingResult.waitlistPosition })
                            : t('register.waitlist_joined', { defaultValue: 'Added to the waitlist' })}
                        </p>
                        <p className="text-indigo-600/80 dark:text-indigo-300/80 mt-1">
                          {t('register.waitlist_body', { defaultValue: "We'll send you an email when a spot opens up. Thank you for your patience!" })}
                        </p>
                      </div>
                    </div>
                  </div>
                )}

                {pendingResult.requiresVerification && (
                  <div className="p-3 rounded-xl bg-blue-500/10 border border-blue-500/20 text-sm">
                    <div className="flex items-start gap-3">
                      <MailCheck className="w-5 h-5 text-blue-500 dark:text-blue-400 flex-shrink-0 mt-0.5" />
                      <div>
                        <p className="font-medium text-blue-600 dark:text-blue-400">{t('register.verify_email_title', { defaultValue: 'Verify your email' })}</p>
                        <p className="text-blue-600/80 dark:text-blue-300/80 mt-1">
                          {t('register.verify_email_body', { defaultValue: "We've sent a verification link to <strong>{{email}}</strong>. Please check your inbox and click the link to verify your email address.", email })}
                        </p>
                      </div>
                    </div>
                  </div>
                )}

                {pendingResult.requiresApproval && (
                  <div className="p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-sm">
                    <div className="flex items-start gap-3">
                      <ShieldCheck className="w-5 h-5 text-amber-500 dark:text-amber-400 flex-shrink-0 mt-0.5" />
                      <div>
                        <p className="font-medium text-amber-600 dark:text-amber-400">{t('register.approval_title', { defaultValue: 'Awaiting admin approval' })}</p>
                        <p className="text-amber-600/80 dark:text-amber-300/80 mt-1">
                          {t('register.approval_body', { defaultValue: "Your account will be reviewed by a community administrator. You'll receive an email once your account has been approved." })}
                        </p>
                      </div>
                    </div>
                  </div>
                )}
              </div>

              <p className="text-theme-muted text-sm mt-6">
                {pendingResult.requiresWaitlist
                  ? t('register.waitlist_next', { defaultValue: "You'll receive an email when your account is activated." })
                  : pendingResult.requiresVerification && pendingResult.requiresApproval
                    ? t('register.next_verify_approve', { defaultValue: 'Once your email is verified and your account is approved, you can log in.' })
                    : pendingResult.requiresVerification
                      ? t('register.next_verify', { defaultValue: 'Once your email is verified, you can log in.' })
                      : t('register.next_approve', { defaultValue: 'Once your account is approved, you can log in.' })}
              </p>

              <Button
                className="w-full mt-6 bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-medium"
                size="lg"
                onPress={() => navigate(tenantPath('/login'))}
              >
                {t('register.go_to_login', { defaultValue: 'Go to Login' })}
              </Button>
            </div>
          </GlassCard>
        </motion.div>
      </div>
    );
  }

  return (
    <div className="min-h-screen flex items-center justify-center p-4 py-12">
      {/* Background blobs */}
      <div className="fixed inset-0 overflow-hidden pointer-events-none">
        <div className="blob blob-indigo" />
        <div className="blob blob-purple" />
        <div className="blob blob-cyan" />
      </div>

      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.5 }}
        className="w-full max-w-md relative z-10"
      >
        <GlassCard className="p-6 sm:p-8">
          {/* Header */}
          <div className="text-center mb-6 sm:mb-8">
            <motion.div
              initial={{ scale: 0.8 }}
              animate={{ scale: 1 }}
              className="inline-flex items-center justify-center w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-gradient-to-br from-indigo-500/20 to-purple-500/20 mb-4"
            >
              <User className="w-7 h-7 sm:w-8 sm:h-8 text-indigo-600 dark:text-indigo-400" aria-hidden="true" />
            </motion.div>
            <h1 className="text-xl sm:text-2xl font-bold text-theme-primary">{t('register.title')}</h1>
            <p className="text-theme-muted mt-2 text-sm sm:text-base">
              {t('register.subtitle_with_name', { name: tenant?.name || 'NEXUS' })}
            </p>
          </div>

          {/* Error Alert */}
          {error && (
            <motion.div
              initial={{ opacity: 0, y: -10 }}
              animate={{ opacity: 1, y: 0 }}
              className="mb-6 p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm"
            >
              {error}
            </motion.div>
          )}

          {/* Form */}
          {renderForm()}

          {/* Divider */}
          <Divider className="my-6 bg-theme-elevated" />

          {/* Login Link */}
          <p className="text-center text-theme-muted text-sm">
            {t('register.have_account')}{' '}
            <Link
              to={tenantPath('/login')}
              className="text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300 font-medium transition-colors"
            >
              {t('register.sign_in_link')}
            </Link>
          </p>
        </GlassCard>

        {/* Back to home link */}
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          transition={{ delay: 0.3 }}
          className="mt-6 text-center"
        >
          <Link
            to={tenantPath('/')}
            className="inline-flex items-center gap-2 text-theme-subtle hover:text-theme-muted text-sm transition-colors"
          >
            <ArrowLeft className="w-4 h-4" aria-hidden="true" />
            {t('register.back_to_home')}
          </Link>
        </motion.div>
      </motion.div>
    </div>
  );
}

export default RegisterPage;
